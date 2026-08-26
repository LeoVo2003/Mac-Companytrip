<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Checkin_REST {
    private const NS = 'mac-voting/v1';

    public static function init(): void {
        add_action('rest_api_init', array(__CLASS__, 'routes'));
    }

    public static function routes(): void {
        register_rest_route(self::NS, '/checkin/bootstrap', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'bootstrap'),
            'permission_callback' => array(__CLASS__, 'can_checkin'),
        ));
        register_rest_route(self::NS, '/checkin/scan', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'scan'),
            'permission_callback' => array(__CLASS__, 'can_checkin'),
        ));
        register_rest_route(self::NS, '/checkin/team/(?P<teamId>\\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'team'),
            'permission_callback' => array(__CLASS__, 'can_checkin'),
        ));
    }

    public static function can_checkin() {
        if (MAC_Checkin::can_scan()) {
            return true;
        }
        return new WP_Error('forbidden', 'Bạn không có quyền check-in.', array('status' => 403));
    }

    public static function bootstrap(): WP_REST_Response {
        MAC_Checkin::expire_active_checkpoint();
        $response = rest_ensure_response(MAC_Checkin::bootstrap());
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        return $response;
    }

    public static function scan(WP_REST_Request $request) {
        MAC_Checkin::expire_active_checkpoint();
        $result = MAC_Checkin::scan(
            sanitize_text_field((string) $request->get_param('token')),
            absint($request->get_param('checkpointId'))
        );
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function team(WP_REST_Request $request) {
        MAC_Checkin::expire_active_checkpoint();
        $team_id = absint($request['teamId']);
        $checkpoint_id = absint($request->get_param('checkpointId'));
        if (!$checkpoint_id) {
            $active = MAC_Checkin::active_checkpoint();
            $checkpoint_id = $active ? (int) $active['id'] : 0;
        }
        if (!$checkpoint_id || !$team_id) {
            return new WP_Error('invalid', 'Thiếu trạm hoặc team.', array('status' => 400));
        }
        // v1.10.0: mọi scanner đều đọc được progress mọi team — không còn giới hạn theo team gán.
        return rest_ensure_response(MAC_Checkin::team_progress($checkpoint_id, $team_id));
    }
}
