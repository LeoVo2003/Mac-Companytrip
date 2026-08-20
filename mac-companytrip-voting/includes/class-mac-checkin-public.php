<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Checkin_Public {
    public static function init(): void {
        add_shortcode('mac_companytrip_checkin', array(__CLASS__, 'shortcode'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
        add_filter('body_class', array(__CLASS__, 'body_class'));
        add_filter('show_admin_bar', array(__CLASS__, 'hide_admin_bar'));
        add_action('template_redirect', array(__CLASS__, 'require_login'), 1);
    }

    public static function register_assets(): void {
        wp_register_style('mac-voting-checkin', MAC_VOTING_URL . 'assets/checkin.css', array(), MAC_VOTING_VERSION);
        wp_register_script('mac-voting-jsqr', MAC_VOTING_URL . 'assets/jsqr.js', array(), MAC_VOTING_VERSION, true);
        wp_register_script('mac-voting-checkin', MAC_VOTING_URL . 'assets/checkin.js', array('mac-voting-jsqr'), MAC_VOTING_VERSION, true);
        if (self::is_checkin_page()) {
            wp_enqueue_style('mac-voting-checkin');
            wp_enqueue_script('mac-voting-checkin');
        }
    }

    public static function require_login(): void {
        if (!self::is_checkin_page()) {
            return;
        }
        if (!is_user_logged_in()) {
            $next = MAC_Voting_DB::admin_page_url();
            $next .= (strpos($next, '?') === false ? '?' : '&') . 'redirect=' . rawurlencode(MAC_Voting_DB::checkin_page_url());
            wp_safe_redirect($next);
            exit;
        }
        if (!MAC_Checkin::can_scan()) {
            wp_die('Tài khoản này không có quyền check-in. Hãy dùng tài khoản BTC được cấp.', 'Không có quyền', array('response' => 403));
        }
    }

    private static function is_checkin_page(): bool {
        global $post;
        $page_id = (int) get_option('mac_voting_checkin_page_id');
        return ($page_id && is_page($page_id))
            || ($post instanceof WP_Post && has_shortcode($post->post_content, 'mac_companytrip_checkin'));
    }

    public static function body_class(array $classes): array {
        if (self::is_checkin_page()) {
            $classes[] = 'mac-checkin-page';
        }
        return $classes;
    }

    public static function hide_admin_bar($show) {
        if (self::is_checkin_page()) {
            return false;
        }
        return $show;
    }

    public static function shortcode(): string {
        wp_enqueue_style('mac-voting-checkin');
        wp_enqueue_script('mac-voting-checkin');
        $rest_url = esc_url(rest_url('mac-voting/v1/'));
        $nonce = esc_attr(wp_create_nonce('wp_rest'));
        $logo = esc_url(MAC_VOTING_URL . 'assets/mac-marketing-logo.png');
        $logout = esc_url(wp_logout_url(MAC_Voting_DB::admin_page_url()));
        $dashboard = esc_url(MAC_Voting_DB::admin_page_url());
        ob_start();
        ?>
        <div id="mac-checkin-app" class="mac-checkin-app"
             data-rest-url="<?php echo $rest_url; ?>"
             data-nonce="<?php echo $nonce; ?>"
             data-logo="<?php echo $logo; ?>"
             data-logout="<?php echo $logout; ?>"
             data-dashboard="<?php echo $dashboard; ?>">
            <div class="mc-loading">
                <img src="<?php echo $logo; ?>" alt="MAC Marketing">
                <span>Đang mở trang Quét QR check-in...</span>
            </div>
            <noscript>Bạn cần bật JavaScript và camera để check-in.</noscript>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
