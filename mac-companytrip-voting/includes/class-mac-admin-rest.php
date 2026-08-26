<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Admin_REST {
    private const NS = 'mac-voting/v1';

    public static function init(): void {
        add_action('rest_api_init', array(__CLASS__, 'routes'));
    }

    public static function routes(): void {
        register_rest_route(self::NS, '/admin/login', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'login'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(self::NS, '/admin/logout', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'logout'),
            'permission_callback' => '__return_true',
        ));
    }

    public static function login(WP_REST_Request $request) {
        $username = sanitize_text_field((string) $request->get_param('username'));
        $password = (string) $request->get_param('password');
        $domain = sanitize_text_field((string) $request->get_param('domain'));
        $email = MAC_Voting_DB::normalize_company_email($username, $domain);
        $rate_identity = $email ?: mb_strtolower(trim($username), 'UTF-8');
        if (!$email || $password === '') {
            return new WP_Error('invalid_login', 'Nhập username email công ty và mật khẩu.', array('status' => 400));
        }
        $allowed = MAC_Voting_Auth::ensure_login_allowed('admin_' . $rate_identity);
        if (is_wp_error($allowed)) {
            return $allowed;
        }
        $user = get_user_by('email', $email);
        if (!$user) {
            // Tài khoản HDV / scanner có thể đăng nhập bằng username thô (vd: hdv.xe1).
            $by_login = get_user_by('login', sanitize_user($username, true));
            if ($by_login && (user_can($by_login, MAC_Checkin::CAP) || user_can($by_login, MAC_Bus::CAP_ROLLCALL))) {
                $user = $by_login;
            }
        }
        if (!$user) {
            MAC_Voting_Auth::failed_login('admin_' . $rate_identity);
            return new WP_Error('invalid_login', 'Sai tài khoản hoặc mật khẩu.', array('status' => 401));
        }
        $signed = wp_signon(array(
            'user_login' => $user->user_login,
            'user_password' => $password,
            'remember' => true,
        ), is_ssl());
        if (is_wp_error($signed)) {
            MAC_Voting_Auth::failed_login('admin_' . $rate_identity);
            return new WP_Error('invalid_login', 'Sai tài khoản hoặc mật khẩu.', array('status' => 401));
        }
        if (!MAC_Checkin::is_super_user($signed) && !user_can($signed, MAC_Checkin::CAP)) {
            wp_logout();
            return new WP_Error('forbidden', 'Tài khoản này không vào được dashboard.', array('status' => 403));
        }
        MAC_Voting_Auth::clear_login_attempts('admin_' . $rate_identity);
        $role = MAC_Checkin::is_super_user($signed) ? 'super' : 'admin';
        MAC_Voting_DB::audit('ADMIN', (string) $signed->ID, 'DASHBOARD_LOGIN', 'user', (string) $signed->ID, array('role' => $role));
        $redirect = MAC_Voting_DB::admin_page_url();
        $requested = (string) $request->get_param('redirect');
        if ($requested !== '' && $role === 'admin') {
            $checkin = MAC_Voting_DB::checkin_page_url();
            if (strpos($requested, $checkin) === 0) {
                $redirect = $checkin;
            }
        }
        return rest_ensure_response(array(
            'ok' => true,
            'role' => $role,
            'redirect' => $redirect,
        ));
    }

    public static function logout() {
        wp_logout();
        return rest_ensure_response(array(
            'ok' => true,
            'redirect' => MAC_Voting_DB::admin_page_url(),
        ));
    }
}
