<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Voting_Auth {
    private const COOKIE = 'mac_vote_session';
    private const TTL = 604800;

    public static function create_session(int $voter_id): string {
        $payload = array(
            'voter_id' => $voter_id,
            'expires'  => time() + self::TTL,
            'nonce'    => wp_generate_uuid4(),
        );
        $encoded = self::base64url(wp_json_encode($payload));
        $signature = hash_hmac('sha256', $encoded, wp_salt('auth'));
        $token = $encoded . '.' . $signature;
        setcookie(self::COOKIE, $token, array(
            'expires'  => time() + self::TTL,
            'path'     => COOKIEPATH ?: '/',
            'domain'   => COOKIE_DOMAIN ?: '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
        $_COOKIE[self::COOKIE] = $token;
        return $token;
    }

    public static function voter_id(): ?int {
        $token = isset($_COOKIE[self::COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE])) : '';
        if (!$token || strpos($token, '.') === false) {
            return null;
        }
        [$encoded, $signature] = explode('.', $token, 2);
        $expected = hash_hmac('sha256', $encoded, wp_salt('auth'));
        if (!hash_equals($expected, $signature)) {
            return null;
        }
        $json = self::base64url_decode($encoded);
        $payload = json_decode($json, true);
        if (!is_array($payload) || empty($payload['voter_id']) || empty($payload['expires']) || (int) $payload['expires'] < time()) {
            return null;
        }
        return (int) $payload['voter_id'];
    }

    public static function logout(): void {
        setcookie(self::COOKIE, '', array(
            'expires'  => time() - HOUR_IN_SECONDS,
            'path'     => COOKIEPATH ?: '/',
            'domain'   => COOKIE_DOMAIN ?: '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
        unset($_COOKIE[self::COOKIE]);
    }

    public static function client_ip(): string {
        $candidates = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                return sanitize_text_field(wp_unslash($_SERVER[$key]));
            }
        }
        return 'unknown';
    }

    public static function login_rate_key(string $identity): string {
        return 'mac_vote_login_' . hash('sha256', mb_strtolower(trim($identity), 'UTF-8') . ':' . self::client_ip());
    }

    public static function ensure_login_allowed(string $identity) {
        $attempt = get_transient(self::login_rate_key($identity));
        if (is_array($attempt) && !empty($attempt['blocked_until']) && (int) $attempt['blocked_until'] > time()) {
            return new WP_Error('rate_limited', 'Bạn đã thử quá nhiều lần. Vui lòng đợi một phút rồi thử lại.', array('status' => 429));
        }
        return true;
    }

    public static function failed_login(string $identity): void {
        $key = self::login_rate_key($identity);
        $attempt = get_transient($key);
        $count = is_array($attempt) ? ((int) ($attempt['count'] ?? 0) + 1) : 1;
        set_transient($key, array(
            'count'         => $count,
            'blocked_until' => $count >= 5 ? time() + 60 : 0,
        ), 60);
    }

    public static function clear_login_attempts(string $identity): void {
        delete_transient(self::login_rate_key($identity));
    }

    private static function base64url(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64url_decode(string $value): string {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
