<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Admin_Public {
    public static function init(): void {
        add_shortcode('mac_companytrip_admin', array(__CLASS__, 'shortcode'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
        add_filter('body_class', array(__CLASS__, 'body_class'));
        add_filter('show_admin_bar', array(__CLASS__, 'hide_admin_bar'));
        add_action('admin_init', array(__CLASS__, 'redirect_staff_from_wp_admin'));
    }

    public static function register_assets(): void {
        wp_register_style('mac-voting-admin', MAC_VOTING_URL . 'assets/admin.css', array(), MAC_VOTING_VERSION);
        wp_register_style('mac-voting-admin-qr', MAC_VOTING_URL . 'assets/admin-qr.css', array('mac-voting-admin'), MAC_VOTING_VERSION);
        wp_register_style('mac-voting-ui-refinements', MAC_VOTING_URL . 'assets/ui-refinements.css', array('mac-voting-admin-qr'), MAC_VOTING_VERSION);
        wp_register_script('mac-voting-qrcode', MAC_VOTING_URL . 'assets/qrcode.bundle.js', array(), MAC_VOTING_VERSION, true);
        wp_register_script('mac-voting-admin', MAC_VOTING_URL . 'assets/admin.js', array('mac-voting-qrcode'), MAC_VOTING_VERSION, true);
        wp_register_script('mac-voting-admin-login', MAC_VOTING_URL . 'assets/admin-login.js', array(), MAC_VOTING_VERSION, true);
        if (!self::is_admin_page()) {
            return;
        }
        wp_enqueue_style('mac-voting-admin');
        wp_enqueue_style('mac-voting-admin-qr');
        wp_enqueue_style('mac-voting-ui-refinements');
        if (is_user_logged_in() && MAC_Voting_Admin::can_access_dashboard()) {
            wp_enqueue_script('mac-voting-qrcode');
            wp_enqueue_script('mac-voting-admin');
            wp_localize_script('mac-voting-admin', 'MACVotingAdmin', MAC_Voting_Admin::script_config());
        } else {
            wp_enqueue_script('mac-voting-admin-login');
        }
    }

    public static function redirect_staff_from_wp_admin(): void {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (!is_user_logged_in() || current_user_can('manage_options') || !current_user_can(MAC_Checkin::CAP)) {
            return;
        }
        wp_safe_redirect(MAC_Voting_DB::admin_page_url());
        exit;
    }

    public static function body_class(array $classes): array {
        if (self::is_admin_page()) {
            $classes[] = 'mac-admin-public-page';
        }
        return $classes;
    }

    public static function hide_admin_bar($show) {
        if (self::is_admin_page()) {
            return false;
        }
        return $show;
    }

    private static function is_admin_page(): bool {
        global $post;
        $page_id = (int) get_option('mac_voting_admin_page_id');
        return ($page_id && is_page($page_id))
            || ($post instanceof WP_Post && has_shortcode($post->post_content, 'mac_companytrip_admin'));
    }

    public static function shortcode(): string {
        $logo = esc_url(MAC_VOTING_URL . 'assets/mac-marketing-logo.png');
        if (!is_user_logged_in()) {
            return self::login_markup($logo);
        }
        if (!MAC_Voting_Admin::can_access_dashboard()) {
            wp_enqueue_style('mac-voting-admin');
            wp_enqueue_style('mac-voting-ui-refinements');
            $logout = esc_url(wp_logout_url(MAC_Voting_DB::admin_page_url()));
            return '<div class="ma-login"><img src="' . $logo . '" alt="MAC Marketing"><h1>Không có quyền</h1><p>Tài khoản này không vào được dashboard Company Trip.</p><a class="ma-primary" href="' . $logout . '">Đăng xuất</a></div>';
        }
        wp_enqueue_style('mac-voting-admin');
        wp_enqueue_style('mac-voting-admin-qr');
        wp_enqueue_style('mac-voting-ui-refinements');
        wp_enqueue_script('mac-voting-qrcode');
        wp_enqueue_script('mac-voting-admin');
        ob_start();
        ?>
        <div class="mac-admin-wrap mac-admin-public">
            <div id="mac-voting-admin" class="mac-admin-app">
                <div class="mac-admin-loading">Đang tải dashboard...</div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function login_markup(string $logo): string {
        wp_enqueue_style('mac-voting-admin');
        wp_enqueue_style('mac-voting-ui-refinements');
        wp_enqueue_script('mac-voting-admin-login');
        $rest = esc_url(rest_url('mac-voting/v1/admin/login'));
        $redirect = isset($_GET['redirect']) ? esc_url_raw(wp_unslash((string) $_GET['redirect'])) : MAC_Voting_DB::admin_page_url();
        $checkin = MAC_Voting_DB::checkin_page_url();
        if ($redirect !== $checkin && strpos((string) $redirect, $checkin) !== 0) {
            $redirect = MAC_Voting_DB::admin_page_url();
        }
        ob_start();
        ?>
        <div class="ma-login" id="ma-admin-login" data-login-url="<?php echo $rest; ?>" data-redirect="<?php echo esc_url($redirect); ?>">
            <img src="<?php echo $logo; ?>" alt="MAC Marketing">
            <p class="ma-login-kicker">COMPANY TRIP</p>
            <h1>Đăng nhập dashboard</h1>
            <p>Super admin thao tác toàn bộ. Admin xem dashboard và vào máy quét check-in.</p>
            <form id="ma-login-form">
                <label for="ma-login-user">Username</label>
                <div class="ma-login-email">
                    <input id="ma-login-user" name="username" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required placeholder="ten.nguoidung">
                    <b>@macusaone.com</b>
                </div>
                <label for="ma-login-pass">Mật khẩu</label>
                <input id="ma-login-pass" name="password" type="password" autocomplete="current-password" required>
                <p id="ma-login-error" class="ma-login-error" role="alert" hidden></p>
                <button type="submit" class="ma-primary">Đăng nhập</button>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
