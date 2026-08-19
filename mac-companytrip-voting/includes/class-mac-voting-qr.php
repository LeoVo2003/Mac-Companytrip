<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Voting_QR {
    // 16 hex chars = 64-bit MAC: plenty for event QRs while keeping the QR small enough to scan reliably.
    private const SIG_LEN = 16;

    public static function token_for_voter(int $voter_id, int $qr_version): string {
        $encoded = self::base64url(wp_json_encode(array(
            'i' => $voter_id,
            'v' => $qr_version,
        )));
        return $encoded . '.' . substr(hash_hmac('sha256', $encoded, wp_salt('auth')), 0, self::SIG_LEN);
    }

    public static function url_for_voter(int $voter_id, int $qr_version): string {
        return home_url('/company-trip/q/' . self::token_for_voter($voter_id, $qr_version));
    }

    public static function extract_token(string $raw): string {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('#/company-trip/q/([^/?#\s]+)#', $raw, $matches)) {
            return rawurldecode($matches[1]);
        }
        if (preg_match('#[?&](?:mac_qr_token|token|qr)=([^&#\s]+)#i', $raw, $matches)) {
            return rawurldecode($matches[1]);
        }
        return $raw;
    }

    public static function verify(string $raw, bool $allow_unsigned = false) {
        global $wpdb;
        $scanned = array('scanned' => mb_substr($raw, 0, 120));
        $token = self::extract_token($raw);
        if (!$token || strpos($token, '.') === false) {
            return new WP_Error('qr_bad_format', 'QR không đúng định dạng của hệ thống (thiếu mã xác thực).', array('status' => 400) + $scanned);
        }
        $parts = explode('.', $token, 2);
        $encoded = $parts[0];
        $signature = $parts[1];
        $expected = hash_hmac('sha256', $encoded, wp_salt('auth'));
        $signature_ok = self::signature_matches($expected, $signature);
        if (!$signature_ok && !$allow_unsigned) {
            return new WP_Error('qr_bad_signature', 'QR không khớp chữ ký của website này. Hãy đảm bảo dashboard và máy quét cùng mở trên một miền.', array('status' => 400) + $scanned);
        }
        $payload = json_decode(self::base64url_decode($encoded), true);
        $voter_id = is_array($payload) ? ($payload['voter_id'] ?? $payload['i'] ?? null) : null;
        if (!is_array($payload) || !is_numeric($voter_id) || (int) $voter_id <= 0) {
            return new WP_Error('qr_bad_format', 'QR không đúng định dạng của hệ thống.', array('status' => 400) + $scanned);
        }
        $voters = MAC_Voting_DB::table('voters');
        $teams = MAC_Voting_DB::table('teams');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT v.id,v.full_name,v.email,v.team_id,v.qr_version,v.status,t.name AS team_name,t.team_no
             FROM $voters v JOIN $teams t ON t.id=v.team_id WHERE v.id=%d",
            (int) $voter_id
        ), ARRAY_A);
        if (!$row) {
            return new WP_Error('invalid_qr', 'QR không hợp lệ.', array('status' => 400));
        }
        $row['full_name'] = MAC_Voting_DB::title_case((string) $row['full_name']);
        if ($row['status'] !== 'ACTIVE') {
            return new WP_Error('qr_inactive', 'QR của ' . $row['full_name'] . ' không dùng được vì nhân sự không còn trạng thái ACTIVE.', array('status' => 400));
        }
        if ((int) ($payload['qr_version'] ?? $payload['v'] ?? 0) !== (int) $row['qr_version']) {
            return new WP_Error('qr_stale', 'QR đã cũ. Hãy quét QR mới nhất trong email mới hoặc mục Nhân sự & QR.', array('status' => 400) + $scanned);
        }
        $row['mac_signature_ok'] = $signature_ok;
        return $row;
    }

    public static function regenerate(int $voter_id): int {
        global $wpdb;
        $voters = MAC_Voting_DB::table('voters');
        $current = (int) $wpdb->get_var($wpdb->prepare("SELECT qr_version FROM $voters WHERE id=%d", $voter_id));
        if (!$current) {
            return 0;
        }
        $next = $current + 1;
        $wpdb->update(
            $voters,
            array('qr_version' => $next, 'updated_at' => MAC_Voting_DB::utc_now()),
            array('id' => $voter_id),
            array('%d', '%s'),
            array('%d')
        );
        return $next;
    }

    public static function handle_public_request(): void {
        $token = get_query_var('mac_qr_token');
        if (!$token) {
            return;
        }
        nocache_headers();
        if (!MAC_Voting_DB::is_voting_enabled()) {
            self::render_locked();
            exit;
        }
        $voter = self::verify(sanitize_text_field(wp_unslash($token)));
        if (is_wp_error($voter)) {
            MAC_Voting_DB::audit('SYSTEM', null, 'QR_LOGIN_FAILED', 'qr', null, array(
                'reason' => $voter->get_error_code(),
            ));
            self::render_invalid();
            exit;
        }
        MAC_Voting_Auth::create_session((int) $voter['id']);
        MAC_Voting_DB::audit('VOTER', (string) $voter['id'], 'QR_LOGIN_SUCCESS', 'voter', (string) $voter['id'], array(
            'method' => 'qr',
        ));
        wp_safe_redirect(add_query_arg('from', 'qr', MAC_Voting_DB::vote_page_url()));
        exit;
    }

    private static function render_locked(): void {
        self::render_generic(
            'Chương trình chưa mở',
            'Cổng chấm điểm văn nghệ chưa được ban tổ chức bật. Vui lòng đợi thông báo.'
        );
    }

    private static function render_invalid(): void {
        self::render_generic(
            'QR không hợp lệ',
            'Mã này không còn hiệu lực. Hãy dùng QR trong email mới nhất hoặc đăng nhập bằng username.'
        );
    }

    private static function render_generic(string $title, string $message): void {
        $logo = esc_url(MAC_VOTING_URL . 'assets/mac-marketing-logo.png');
        $title_attr = esc_html($title);
        $message_attr = esc_html($message);
        status_header(200);
        echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>MAC Company Trip</title><style>
            :root { color-scheme: light; }
            body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; background:#f5f5f7; color:#111827; font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
            main { width:min(100%,440px); padding:32px 24px; border:1px solid #e4e7ec; border-radius:24px; background:#fff; text-align:center; box-shadow:0 1px 3px rgba(16,24,40,.05); }
            img { width:120px; height:auto; }
            p.kicker { margin:16px 0 8px; color:#667085; font-size:12px; font-weight:700; letter-spacing:.12em; }
            h1 { margin:0 0 8px; font-size:24px; letter-spacing:-.03em; }
            p.copy { margin:0; color:#667085; font-size:15px; line-height:1.5; }
        </style></head><body><main><img src="' . $logo . '" alt="MAC Marketing"><p class="kicker">MAC COMPANY TRIP</p><h1>' . $title_attr . '</h1><p class="copy">' . $message_attr . '</p></main></body></html>';
    }

    private static function signature_matches(string $expected, string $signature): bool {
        if ($signature === '' || strlen($signature) > strlen($expected)) {
            return false;
        }
        if (strlen($signature) === strlen($expected)) {
            return hash_equals($expected, $signature);
        }
        // Compact tokens carry a truncated MAC; compare prefix with a safe minimum length.
        if (strlen($signature) >= self::SIG_LEN) {
            return hash_equals(substr($expected, 0, strlen($signature)), $signature);
        }
        return false;
    }

    private static function base64url(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64url_decode(string $value): string {
        $value = rtrim($value, '=');
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
