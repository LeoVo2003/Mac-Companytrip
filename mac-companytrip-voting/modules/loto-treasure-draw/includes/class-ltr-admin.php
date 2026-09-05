<?php
if (!defined('ABSPATH')) exit;

/**
 * Cầu nối admin-ajax cho tab "Lô Tô" trong dashboard /company-trip-admin.
 *
 * Module này KHÔNG còn menu wp-admin riêng. Mọi thao tác (dò la bàn, sẵn sàng,
 * hoàn tác, thêm/xóa phần thưởng, đặt lại) đi qua wp_ajax_mac_vote_loto_* — cùng
 * mẫu nonce + phân quyền với phần còn lại của dashboard MAC. Màn hình LED vẫn
 * dùng REST ltr/v1 (token) như cũ; cả hai đều gọi chung LTR_Prizes.
 */
class LTR_Admin {

    public static function init() {
        add_action('wp_ajax_mac_vote_loto_state', [__CLASS__, 'ajax_state']);
        add_action('wp_ajax_mac_vote_loto_draw', [__CLASS__, 'ajax_draw']);
        add_action('wp_ajax_mac_vote_loto_ready', [__CLASS__, 'ajax_ready']);
        add_action('wp_ajax_mac_vote_loto_undo', [__CLASS__, 'ajax_undo']);
        add_action('wp_ajax_mac_vote_loto_add_prize', [__CLASS__, 'ajax_add_prize']);
        add_action('wp_ajax_mac_vote_loto_delete_prize', [__CLASS__, 'ajax_delete_prize']);
        add_action('wp_ajax_mac_vote_loto_reset', [__CLASS__, 'ajax_reset']);
    }

    /**
     * MAC_Voting_Admin::guard() là private nên module tự kiểm tra, dùng đúng
     * nonce 'mac_voting_admin' và cùng bộ vai trò:
     *   staff    = vào được dashboard (xem trạng thái)
     *   operator = Super + BTC/Hoa tiêu (không gồm HDV) — quay/sẵn sàng/hoàn tác
     *   super    = Super Admin — thêm/xóa/đặt lại phần thưởng
     */
    private static function guard($level = 'super') {
        check_ajax_referer('mac_voting_admin', 'nonce');
        if (!class_exists('MAC_Voting_Admin') || !class_exists('MAC_Checkin') || !class_exists('MAC_Bus')) {
            wp_send_json_error(['message' => 'Module Lô Tô chưa được nạp đúng cách.'], 500);
        }
        $can_access = MAC_Voting_Admin::can_access_dashboard();
        if ($level === 'staff' && $can_access) {
            return;
        }
        if ($level === 'operator' && $can_access && !MAC_Bus::is_guide()) {
            return;
        }
        if ($level === 'super' && MAC_Checkin::is_super()) {
            return;
        }
        wp_send_json_error(['message' => 'Không có quyền.'], 403);
    }

    private static function can_operate() {
        return MAC_Voting_Admin::can_access_dashboard() && !MAC_Bus::is_guide();
    }

    private static function state_payload() {
        $prizes          = LTR_Prizes::get_prizes();
        $summary         = [];
        $remaining_total = 0;
        foreach ($prizes as $p) {
            $remaining        = isset($p['remaining']) ? (int) $p['remaining'] : 0;
            $remaining_total += $remaining;
            $summary[] = [
                'id'        => isset($p['id']) ? (string) $p['id'] : '',
                'name'      => isset($p['name']) ? (string) $p['name'] : '',
                'image_url' => isset($p['image_url']) ? (string) $p['image_url'] : '',
                'remaining' => $remaining,
                'total'     => isset($p['total']) ? (int) $p['total'] : 0,
            ];
        }
        return [
            'prizes'         => $summary,
            'history'        => array_values(LTR_Prizes::get_history()),
            'remainingTotal' => $remaining_total,
            'displayUrl'     => add_query_arg('ltr_display', '1', home_url('/')),
            'can'            => [
                'operate' => self::can_operate(),
                'write'   => MAC_Checkin::is_super(),
            ],
        ];
    }

    public static function ajax_state() {
        self::guard('staff');
        wp_send_json_success(self::state_payload());
    }

    public static function ajax_draw() {
        self::guard('operator');
        $result = LTR_Prizes::draw();
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }
        wp_send_json_success(['message' => 'Đã dò la bàn — màn hình LED đang chạy hành trình tìm kho báu.']);
    }

    public static function ajax_ready() {
        self::guard('operator');
        LTR_Prizes::ready_next();
        wp_send_json_success(['message' => 'Đã sẵn sàng cho lượt quay tiếp theo.']);
    }

    public static function ajax_undo() {
        self::guard('operator');
        $result = LTR_Prizes::undo_last();
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }
        wp_send_json_success(['message' => 'Đã hoàn tác lượt quay cuối cùng.']);
    }

    public static function ajax_add_prize() {
        self::guard();

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $qty  = isset($_POST['qty']) ? max(1, (int) $_POST['qty']) : 1;
        if ($name === '') {
            wp_send_json_error(['message' => 'Vui lòng nhập tên phần thưởng.'], 400);
        }

        $has_file  = isset($_FILES['file']) && isset($_FILES['file']['name']) && $_FILES['file']['name'] !== '';
        $image_url = isset($_POST['imageUrl']) ? esc_url_raw(wp_unslash($_POST['imageUrl'])) : '';

        // Hỗ trợ CẢ HAI: tải ảnh từ thiết bị HOẶC dán URL ảnh.
        if ($has_file) {
            if ((int) $_FILES['file']['size'] > 5 * 1024 * 1024) {
                wp_send_json_error(['message' => 'Ảnh không được lớn hơn 5 MB.'], 400);
            }
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $attachment_id = media_handle_upload('file', 0);
            if (is_wp_error($attachment_id)) {
                wp_send_json_error(['message' => $attachment_id->get_error_message()], 400);
            }
            $mime = get_post_mime_type($attachment_id);
            if (!$mime || strpos($mime, 'image/') !== 0) {
                wp_delete_attachment($attachment_id, true);
                wp_send_json_error(['message' => 'Tệp tải lên không phải là ảnh hợp lệ.'], 400);
            }
            LTR_Prizes::add_prize($name, $attachment_id, $qty);
        } elseif ($image_url !== '') {
            LTR_Prizes::add_prize($name, 0, $qty, $image_url);
        } else {
            LTR_Prizes::add_prize($name, 0, $qty);
        }

        wp_send_json_success(['message' => 'Đã thêm phần thưởng vào kho tàng.']);
    }

    public static function ajax_delete_prize() {
        self::guard();
        $id = isset($_POST['prizeId']) ? sanitize_text_field(wp_unslash($_POST['prizeId'])) : '';
        if ($id === '') {
            wp_send_json_error(['message' => 'Thiếu phần thưởng cần xóa.'], 400);
        }
        LTR_Prizes::delete_prize($id);
        wp_send_json_success(['message' => 'Đã xóa phần thưởng khỏi kho tàng.']);
    }

    public static function ajax_reset() {
        self::guard();
        LTR_Prizes::reset_all();
        wp_send_json_success(['message' => 'Đã đặt lại toàn bộ kho tàng và lịch sử quay.']);
    }
}
