<?php
if (!defined('ABSPATH')) exit;

class LTR_Display {

    public static function init() {
        add_action('template_redirect', [__CLASS__, 'maybe_render']);
    }

    public static function maybe_render() {
        if (isset($_GET['ltr_display'])) {
            self::render();
            exit;
        }
    }

    public static function render() {
        $rest_root = esc_url_raw(rest_url('ltr/v1/'));
        $css_url   = LTR_PLUGIN_URL . 'assets/css/display.css';
        $js_url    = LTR_PLUGIN_URL . 'assets/js/display.js';
        $key       = LTR_Prizes::get_display_token();

        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        include LTR_PLUGIN_DIR . 'includes/views/display.php';
    }
}
