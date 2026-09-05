<?php
if (!defined('ABSPATH')) exit;

class LTR_REST {

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('ltr/v1', '/state', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_state'],
            'permission_callback' => '__return_true', // Màn hình LED cần đọc công khai, không cần đăng nhập.
        ]);

        register_rest_route('ltr/v1', '/draw', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'do_draw'],
            'permission_callback' => [__CLASS__, 'check_operator'],
        ]);

        register_rest_route('ltr/v1', '/undo', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'do_undo'],
            'permission_callback' => [__CLASS__, 'check_operator'],
        ]);

        register_rest_route('ltr/v1', '/ready', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'do_ready'],
            'permission_callback' => [__CLASS__, 'check_operator'],
        ]);
    }

    public static function check_admin() {
        return current_user_can('manage_options');
    }

    /**
     * Cho phép thao tác (dò la bàn / hoàn tác / sẵn sàng) nếu là admin đã đăng
     * nhập, HOẶC nếu request đi kèm đúng token bí mật của màn hình LED — nhờ
     * vậy có thể bấm điều khiển trực tiếp ngay trên màn hình LED mà không cần
     * đăng nhập qua trang quản trị riêng.
     */
    public static function check_operator(WP_REST_Request $req) {
        if (current_user_can('manage_options')) {
            return true;
        }
        $token    = LTR_Prizes::get_display_token();
        $provided = (string) $req->get_param('key');
        return $token && $provided && hash_equals($token, $provided);
    }

    public static function get_state(WP_REST_Request $req) {
        nocache_headers();

        $event  = LTR_Prizes::get_current_event();
        $prizes = LTR_Prizes::get_prizes();
        $history = LTR_Prizes::get_history();

        $prizes_summary = array_map(function ($p) {
            return [
                'id'        => $p['id'],
                'name'      => $p['name'],
                'remaining' => (int) $p['remaining'],
                'total'     => (int) $p['total'],
            ];
        }, $prizes);

        return new WP_REST_Response([
            'event'   => $event,
            'prizes'  => $prizes_summary,
            'history' => array_values($history),
        ], 200);
    }

    public static function do_draw(WP_REST_Request $req) {
        $result = LTR_Prizes::draw();
        if (is_wp_error($result)) {
            return new WP_REST_Response(['message' => $result->get_error_message()], 400);
        }
        return new WP_REST_Response(['event' => $result], 200);
    }

    public static function do_undo(WP_REST_Request $req) {
        $result = LTR_Prizes::undo_last();
        if (is_wp_error($result)) {
            return new WP_REST_Response(['message' => $result->get_error_message()], 400);
        }
        return new WP_REST_Response(['ok' => true], 200);
    }

    public static function do_ready(WP_REST_Request $req) {
        LTR_Prizes::ready_next();
        return new WP_REST_Response(['ok' => true], 200);
    }
}
