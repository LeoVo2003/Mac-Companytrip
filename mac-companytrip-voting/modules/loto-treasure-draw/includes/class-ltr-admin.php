<?php
if (!defined('ABSPATH')) exit;

class LTR_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_ltr_add_prize', [__CLASS__, 'handle_add_prize']);
        add_action('admin_post_ltr_delete_prize', [__CLASS__, 'handle_delete_prize']);
        add_action('admin_post_ltr_reset_all', [__CLASS__, 'handle_reset_all']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    public static function menu() {
        add_menu_page(
            'Lô Tô Kho Báu',
            '🏴‍☠️ Lô Tô Kho Báu',
            'manage_options',
            'ltr-control',
            [__CLASS__, 'render_control'],
            'dashicons-flag',
            26
        );
        add_submenu_page('ltr-control', 'Điều khiển quay thưởng', '🧭 Điều khiển', 'manage_options', 'ltr-control', [__CLASS__, 'render_control']);
        add_submenu_page('ltr-control', 'Kho tàng phần thưởng', '💰 Kho tàng', 'manage_options', 'ltr-prizes', [__CLASS__, 'render_prizes']);
        add_submenu_page('ltr-control', 'Màn hình LED', '📺 Màn hình LED', 'manage_options', 'ltr-display-info', [__CLASS__, 'render_display_info']);
    }

    public static function enqueue($hook) {
        if (strpos($hook, 'ltr-') === false) {
            return;
        }

        wp_enqueue_style('ltr-admin', LTR_PLUGIN_URL . 'assets/css/admin.css', [], LTR_VERSION);

        if ($hook === 'toplevel_page_ltr-control') {
            wp_enqueue_script('ltr-admin-control', LTR_PLUGIN_URL . 'assets/js/admin-control.js', [], LTR_VERSION, true);
            wp_localize_script('ltr-admin-control', 'LTR_DATA', [
                'root'  => esc_url_raw(rest_url('ltr/v1/')),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        }

        if (strpos($hook, 'ltr-prizes') !== false) {
            wp_enqueue_media();
            wp_enqueue_script('ltr-admin-prizes', LTR_PLUGIN_URL . 'assets/js/admin-prizes.js', ['jquery'], LTR_VERSION, true);
        }
    }

    public static function handle_add_prize() {
        if (!current_user_can('manage_options')) {
            wp_die('Không đủ quyền.');
        }
        check_admin_referer('ltr_add_prize');

        $name     = isset($_POST['prize_name']) ? sanitize_text_field(wp_unslash($_POST['prize_name'])) : '';
        $qty      = isset($_POST['prize_qty']) ? (int) $_POST['prize_qty'] : 1;
        $image_id = isset($_POST['prize_image_id']) ? (int) $_POST['prize_image_id'] : 0;

        if ($name !== '') {
            LTR_Prizes::add_prize($name, $image_id, $qty);
        }

        wp_safe_redirect(admin_url('admin.php?page=ltr-prizes&added=1'));
        exit;
    }

    public static function handle_delete_prize() {
        if (!current_user_can('manage_options')) {
            wp_die('Không đủ quyền.');
        }
        check_admin_referer('ltr_delete_prize');

        $id = isset($_POST['prize_id']) ? sanitize_text_field(wp_unslash($_POST['prize_id'])) : '';
        if ($id !== '') {
            LTR_Prizes::delete_prize($id);
        }

        wp_safe_redirect(admin_url('admin.php?page=ltr-prizes&deleted=1'));
        exit;
    }

    public static function handle_reset_all() {
        if (!current_user_can('manage_options')) {
            wp_die('Không đủ quyền.');
        }
        check_admin_referer('ltr_reset_all');

        LTR_Prizes::reset_all();

        wp_safe_redirect(admin_url('admin.php?page=ltr-prizes&reset=1'));
        exit;
    }

    public static function render_control() {
        include LTR_PLUGIN_DIR . 'includes/views/control.php';
    }

    public static function render_prizes() {
        include LTR_PLUGIN_DIR . 'includes/views/prizes.php';
    }

    public static function render_display_info() {
        include LTR_PLUGIN_DIR . 'includes/views/display-info.php';
    }
}
