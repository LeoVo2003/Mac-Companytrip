<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Voting_Public {
    public static function init(): void {
        add_shortcode('mac_companytrip_vote', array(__CLASS__, 'shortcode'));
        add_shortcode('mac_companytrip_total_results', array(__CLASS__, 'results_shortcode'));
        add_shortcode('mac_companytrip_art_results', array(__CLASS__, 'art_shortcode'));
        add_action('init', array(__CLASS__, 'register_rewrite'));
        add_filter('query_vars', array(__CLASS__, 'query_vars'));
        add_action('template_redirect', array('MAC_Voting_QR', 'handle_public_request'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
        add_filter('body_class', array(__CLASS__, 'body_class'));
    }

    public static function register_rewrite(): void {
        $page_id = (int) get_option('mac_voting_page_id');
        if ($page_id) add_rewrite_rule('^cham-diem-van-nghe/?$', 'index.php?page_id=' . $page_id, 'top');
        $results_page_id = (int) get_option('mac_voting_results_page_id');
        if ($results_page_id) add_rewrite_rule('^ket-qua-van-nghe/?$', 'index.php?page_id=' . $results_page_id, 'top');
        $total_page_id = (int) get_option('mac_voting_total_page_id');
        if ($total_page_id) add_rewrite_rule('^ket-qua-tong/?$', 'index.php?page_id=' . $total_page_id, 'top');
        $checkin_page_id = (int) get_option('mac_voting_checkin_page_id');
        if ($checkin_page_id) add_rewrite_rule('^company-trip-checkin/?$', 'index.php?page_id=' . $checkin_page_id, 'top');
        $admin_page_id = (int) get_option('mac_voting_admin_page_id');
        if ($admin_page_id) add_rewrite_rule('^company-trip-admin/?$', 'index.php?page_id=' . $admin_page_id, 'top');
        add_rewrite_rule('^company-trip/q/([^/]+)/?$', 'index.php?mac_qr_token=$matches[1]', 'top');
    }

    public static function query_vars(array $vars): array {
        $vars[] = 'mac_qr_token';
        return $vars;
    }

    public static function register_assets(): void {
        wp_register_style('mac-voting-public', MAC_VOTING_URL . 'assets/public.css', array(), MAC_VOTING_VERSION);
        wp_register_style('mac-voting-ui-refinements', MAC_VOTING_URL . 'assets/ui-refinements.css', array('mac-voting-public'), MAC_VOTING_VERSION);
        wp_register_script('mac-voting-public', MAC_VOTING_URL . 'assets/public.js', array(), MAC_VOTING_VERSION, true);
        wp_register_style('mac-voting-results', MAC_VOTING_URL . 'assets/results.css', array(), MAC_VOTING_VERSION);
        wp_register_script('mac-voting-results', MAC_VOTING_URL . 'assets/results.js', array(), MAC_VOTING_VERSION, true);
        wp_register_style('mac-voting-art', MAC_VOTING_URL . 'assets/art.css', array(), MAC_VOTING_VERSION);
        wp_register_script('mac-voting-art', MAC_VOTING_URL . 'assets/art.js', array(), MAC_VOTING_VERSION, true);

        // Enqueue before wp_head so themes do not miss the stylesheet. The
        // shortcode also enqueues as a fallback for page-builder previews.
        if (self::is_vote_page()) {
            wp_enqueue_style('mac-voting-public');
            wp_enqueue_style('mac-voting-ui-refinements');
            wp_enqueue_script('mac-voting-public');
        }
        if (self::is_total_page()) {
            wp_enqueue_style('mac-voting-results');
            wp_enqueue_script('mac-voting-results');
        }
        if (self::is_art_page()) {
            wp_enqueue_style('mac-voting-art');
            wp_enqueue_script('mac-voting-art');
        }
    }

    private static function is_vote_page(): bool {
        global $post;
        $page_id = (int) get_option('mac_voting_page_id');
        return ($page_id && is_page($page_id))
            || ($post instanceof WP_Post && has_shortcode($post->post_content, 'mac_companytrip_vote'));
    }

    private static function is_total_page(): bool {
        global $post;
        $page_id = (int) get_option('mac_voting_total_page_id');
        return ($page_id && is_page($page_id))
            || ($post instanceof WP_Post && has_shortcode($post->post_content, 'mac_companytrip_total_results'));
    }

    private static function is_art_page(): bool {
        global $post;
        $page_id = (int) get_option('mac_voting_results_page_id');
        return ($page_id && is_page($page_id))
            || ($post instanceof WP_Post && has_shortcode($post->post_content, 'mac_companytrip_art_results'));
    }

    public static function body_class(array $classes): array {
        if (self::is_vote_page()) {
            $classes[] = 'mac-voting-page';
        }
        if (self::is_total_page()) {
            $classes[] = 'mac-results-page';
        }
        if (self::is_art_page()) {
            $classes[] = 'mac-art-page';
        }
        return $classes;
    }

    public static function shortcode(): string {
        wp_enqueue_style('mac-voting-public');
        wp_enqueue_style('mac-voting-ui-refinements');
        wp_enqueue_script('mac-voting-public');
        $rest_url = esc_url(rest_url('mac-voting/v1/'));
        $nonce = esc_attr(wp_create_nonce('wp_rest'));
        $logo = esc_url(MAC_VOTING_URL . 'assets/mac-marketing-logo.png');
        ob_start();
        ?>
        <div id="mac-voting-app" class="mac-voting-app" aria-live="polite"
             data-rest-url="<?php echo $rest_url; ?>"
             data-nonce="<?php echo $nonce; ?>"
             data-logo="<?php echo $logo; ?>">
            <div class="mac-loading">
                <img src="<?php echo $logo; ?>" alt="MAC Marketing">
                <span>Đang tải hệ thống chấm điểm...</span>
            </div>
            <noscript>Bạn cần bật JavaScript để sử dụng hệ thống chấm điểm.</noscript>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function results_shortcode(): string {
        wp_enqueue_style('mac-voting-results');
        wp_enqueue_script('mac-voting-results');
        $endpoint = esc_url(rest_url('mac-voting/v1/results-total'));
        $logo = esc_url(MAC_VOTING_URL . 'assets/mac-marketing-logo.png');
        ob_start();
        ?>
        <div id="mac-results-app" class="mac-results-app" data-endpoint="<?php echo $endpoint; ?>" data-logo="<?php echo $logo; ?>">
            <div class="mr-loading" role="status">
                <img src="<?php echo $logo; ?>" alt="MAC Marketing">
                <span>Đang kết nối màn hình công bố…</span>
            </div>
            <noscript>Bạn cần bật JavaScript để xem kết quả.</noscript>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    // Màn trình chiếu văn nghệ: đua thuyền 6 làn, điều khiển bằng nút trong dashboard.
    public static function art_shortcode(): string {
        wp_enqueue_style('mac-voting-art');
        wp_enqueue_script('mac-voting-art');
        $endpoint = esc_url(rest_url('mac-voting/v1/results'));
        $logo = esc_url(MAC_VOTING_URL . 'assets/mac-marketing-logo.png');
        ob_start();
        ?>
        <div id="mac-art-app" class="mac-art-app" data-endpoint="<?php echo $endpoint; ?>" data-logo="<?php echo $logo; ?>">
            <div class="ar-error" role="status">
                <img src="<?php echo $logo; ?>" alt="MAC Marketing">
                <span>Đang kết nối màn đua thuyền…</span>
            </div>
            <noscript>Bạn cần bật JavaScript để xem kết quả.</noscript>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
