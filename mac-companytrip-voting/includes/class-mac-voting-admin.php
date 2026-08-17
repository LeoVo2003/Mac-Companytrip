<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Voting_Admin {
    public static function init(): void {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('wp_ajax_mac_vote_overview', array(__CLASS__, 'ajax_overview'));
        add_action('wp_ajax_mac_vote_reset_event', array(__CLASS__, 'ajax_reset_event'));
        add_action('wp_ajax_mac_vote_round', array(__CLASS__, 'ajax_round'));
        add_action('wp_ajax_mac_vote_reveal', array(__CLASS__, 'ajax_reveal'));
        add_action('wp_ajax_mac_vote_team', array(__CLASS__, 'ajax_team'));
        add_action('wp_ajax_mac_vote_swap', array(__CLASS__, 'ajax_swap'));
        add_action('wp_ajax_mac_vote_ballot', array(__CLASS__, 'ajax_ballot'));
        add_action('wp_ajax_mac_vote_import', array(__CLASS__, 'ajax_import'));
        add_action('wp_ajax_mac_vote_gate', array(__CLASS__, 'ajax_gate'));
        add_action('wp_ajax_mac_vote_checkpoint', array(__CLASS__, 'ajax_checkpoint'));
        add_action('wp_ajax_mac_vote_qr', array(__CLASS__, 'ajax_qr'));
        add_action('wp_ajax_mac_vote_staff', array(__CLASS__, 'ajax_staff'));
        add_action('wp_ajax_mac_vote_points', array(__CLASS__, 'ajax_points'));
        add_action('admin_post_mac_vote_export', array(__CLASS__, 'export_csv'));
        add_action('admin_post_mac_vote_template', array(__CLASS__, 'template_csv'));
        add_action('admin_post_mac_vote_export_checkin', array(__CLASS__, 'export_checkin_csv'));
    }

    public static function menu(): void {
        add_menu_page(
            'MAC Company Trip', 'MAC Company Trip', 'manage_options', 'mac-voting', array(__CLASS__, 'page'),
            'dashicons-awards', 3
        );
    }

    public static function assets(string $hook): void {
        if ($hook !== 'toplevel_page_mac-voting') return;
        wp_enqueue_style('mac-voting-admin', MAC_VOTING_URL . 'assets/admin.css', array(), MAC_VOTING_VERSION);
        wp_enqueue_style('mac-voting-admin-qr', MAC_VOTING_URL . 'assets/admin-qr.css', array('mac-voting-admin'), MAC_VOTING_VERSION);
        wp_enqueue_style('mac-voting-ui-refinements', MAC_VOTING_URL . 'assets/ui-refinements.css', array('mac-voting-admin-qr'), MAC_VOTING_VERSION);
        wp_enqueue_script('mac-voting-qrcode', MAC_VOTING_URL . 'assets/qrcode.bundle.js', array(), MAC_VOTING_VERSION, true);
        wp_enqueue_script('mac-voting-admin', MAC_VOTING_URL . 'assets/admin.js', array('mac-voting-qrcode'), MAC_VOTING_VERSION, true);
        wp_localize_script('mac-voting-admin', 'MACVotingAdmin', self::script_config());
    }

    public static function page(): void {
        $public_url = MAC_Voting_DB::admin_page_url();
        ?>
        <div class="wrap mac-admin-wrap">
            <p class="ma-wp-admin-note">Dashboard chính chạy ngoài wp-admin: <a href="<?php echo esc_url($public_url); ?>"><?php echo esc_html($public_url); ?></a></p>
            <div id="mac-voting-admin" class="mac-admin-app">
                <div class="mac-admin-loading"><span class="spinner is-active"></span> Đang tải dashboard...</div>
            </div>
        </div>
        <?php
    }

    public static function can_access_dashboard(): bool {
        return MAC_Checkin::is_super() || current_user_can(MAC_Checkin::CAP);
    }

    public static function script_config(): array {
        $is_super = MAC_Checkin::is_super();
        return array(
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('mac_voting_admin'),
            'role'     => $is_super ? 'super' : 'admin',
            'voteUrl'  => MAC_Voting_DB::vote_page_url(),
            'resultsUrl' => MAC_Voting_DB::results_page_url(),
            'checkinUrl' => MAC_Voting_DB::checkin_page_url(),
            'adminUrl' => MAC_Voting_DB::admin_page_url(),
            'logoutUrl'=> wp_logout_url(MAC_Voting_DB::admin_page_url()),
            'logo'     => MAC_VOTING_URL . 'assets/mac-marketing-logo.png',
            'exportUrl'=> wp_nonce_url(admin_url('admin-post.php?action=mac_vote_export'), 'mac_vote_export'),
            'checkinExportUrl'=> wp_nonce_url(admin_url('admin-post.php?action=mac_vote_export_checkin'), 'mac_vote_export_checkin'),
            'templateUrl'=> wp_nonce_url(admin_url('admin-post.php?action=mac_vote_template'), 'mac_vote_template'),
            'permalinkWarning' => get_option('permalink_structure') === '',
            'permalinkSettingsUrl' => admin_url('options-permalink.php'),
        );
    }

    private static function guard(string $level = 'super'): void {
        check_ajax_referer('mac_voting_admin', 'nonce');
        if ($level === 'staff' && self::can_access_dashboard()) {
            return;
        }
        if ($level === 'super' && MAC_Checkin::is_super()) {
            return;
        }
        wp_send_json_error(array('message' => 'Không có quyền.'), 403);
    }

    public static function ajax_overview(): void {
        self::guard('staff');
        wp_send_json_success(self::overview_payload());
    }

    public static function ajax_reset_event(): void {
        self::guard();
        $confirmation = strtoupper(trim(sanitize_text_field(wp_unslash($_POST['confirmation'] ?? ''))));
        if ($confirmation !== 'RESET') {
            wp_send_json_error(array('message' => 'Vui lòng nhập đúng RESET để xác nhận.'), 400);
        }

        global $wpdb;
        $rounds = MAC_Voting_DB::table('rounds');
        $ballots = MAC_Voting_DB::table('ballots');
        $grants = MAC_Voting_DB::table('revote_grants');

        $wpdb->query('START TRANSACTION');
        $reset_rounds = $wpdb->query("UPDATE $rounds SET status='DRAFT', opened_at=NULL, closed_at=NULL");
        $deleted_grants = $wpdb->query("DELETE FROM $grants");
        $deleted_ballots = $wpdb->query("DELETE FROM $ballots");
        if (in_array(false, array($reset_rounds, $deleted_grants, $deleted_ballots), true)) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => 'Không thể đặt lại sự kiện. Dữ liệu chưa bị thay đổi.'), 500);
        }
        MAC_Checkin::reset_event_data();
        MAC_Points::reset_awards();
        MAC_Points::reset_history();
        $wpdb->query('COMMIT');
        MAC_Voting_DB::set_reveal_state('IDLE');

        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'EVENT_RESET', 'event', null, array(
            'deletedBallots' => (int) $deleted_ballots,
            'deletedRevoteGrants' => (int) $deleted_grants,
            'resetRounds' => (int) $reset_rounds,
            'keptVoters' => true,
            'keptSchedule' => true,
            'keptQr' => true,
        ));
        wp_send_json_success(array(
            'message' => 'Đã đặt lại sự kiện. Phiếu, check-in, điểm hạng mục và lịch sử cộng điểm đã về trạng thái ban đầu.',
            'overview' => self::overview(),
        ));
    }

    public static function ajax_round(): void {
        self::guard();
        global $wpdb;
        $round_id = absint($_POST['roundId'] ?? 0);
        $operation = sanitize_key($_POST['operation'] ?? '');
        $rounds = MAC_Voting_DB::table('rounds');
        $round = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rounds WHERE id=%d", $round_id), ARRAY_A);
        if (!$round) wp_send_json_error(array('message' => 'Lượt không tồn tại.'), 404);
        if ($operation === 'open') {
            if ($round['status'] !== 'DRAFT') wp_send_json_error(array('message' => 'Lượt đã khóa, không thể mở lại.'), 409);
            $other_open = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $rounds WHERE id!=%d AND status='OPEN' LIMIT 1", $round_id));
            if ($other_open) wp_send_json_error(array(
                'message' => sprintf('Lượt %d vẫn đang mở. Hãy đóng lượt này trước.', $other_open),
                'code' => 'round_already_open',
                'openRoundId' => $other_open,
            ), 409);
            $earlier = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $rounds WHERE id<%d AND status!='CLOSED'", $round_id));
            if ($earlier) wp_send_json_error(array('message' => 'Hãy hoàn tất lượt trước.'), 409);
            $wpdb->update($rounds, array('status' => 'OPEN', 'opened_at' => MAC_Voting_DB::utc_now(), 'closed_at' => null), array('id' => $round_id), array('%s','%s','%s'), array('%d'));
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'ROUND_OPENED', 'round', (string) $round_id);
        } elseif ($operation === 'reopen') {
            if ($round['status'] !== 'CLOSED') wp_send_json_error(array('message' => 'Chỉ mở lại được lượt đã đóng.'), 409);
            $other_open = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $rounds WHERE id!=%d AND status='OPEN' LIMIT 1", $round_id));
            if ($other_open) wp_send_json_error(array(
                'message' => sprintf('Lượt %d vẫn đang mở. Hãy đóng lượt này trước.', $other_open),
                'code' => 'round_already_open',
                'openRoundId' => $other_open,
            ), 409);
            $previous_closed_at = $round['closed_at'];
            $updated = $wpdb->update($rounds, array('status' => 'OPEN', 'opened_at' => MAC_Voting_DB::utc_now(), 'closed_at' => null), array('id' => $round_id), array('%s','%s','%s'), array('%d'));
            if ($updated === false) wp_send_json_error(array('message' => 'Không thể mở lại lượt. Vui lòng thử lại.'), 500);
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'ROUND_REOPENED', 'round', (string) $round_id, array('previousClosedAt' => $previous_closed_at));
        } elseif ($operation === 'close') {
            if ($round['status'] !== 'OPEN') wp_send_json_error(array('message' => 'Lượt không ở trạng thái đang mở.'), 409);
            $wpdb->update($rounds, array('status' => 'CLOSED', 'closed_at' => MAC_Voting_DB::utc_now()), array('id' => $round_id), array('%s','%s'), array('%d'));
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'ROUND_CLOSED', 'round', (string) $round_id);
        } else {
            wp_send_json_error(array('message' => 'Thao tác không hợp lệ.'), 400);
        }
        wp_send_json_success(self::overview());
    }

    public static function ajax_reveal(): void {
        self::guard();
        $next = strtoupper(sanitize_text_field(wp_unslash($_POST['stage'] ?? '')));
        $allowed = array('IDLE', 'ROLLING', 'DECOY', 'THIRD', 'SECOND', 'FINAL');
        if (!in_array($next, $allowed, true)) {
            wp_send_json_error(array('message' => 'Trạng thái công bố không hợp lệ.'), 400);
        }
        $current = MAC_Voting_DB::reveal_state();
        $transitions = array(
            'IDLE' => 'ROLLING',
            'ROLLING' => 'DECOY',
            'DECOY' => 'THIRD',
            'THIRD' => 'SECOND',
            'SECOND' => 'FINAL',
        );
        if ($next !== 'IDLE' && ($transitions[$current['stage']] ?? '') !== $next) {
            wp_send_json_error(array('message' => 'Tín hiệu không đúng thứ tự. Hãy tải lại dashboard và thử lại.'), 409);
        }
        if ($next === 'ROLLING') {
            global $wpdb;
            $rounds = MAC_Voting_DB::table('rounds');
            $teams = MAC_Voting_DB::table('teams');
            $performances = MAC_Voting_DB::table('performances');
            $slots = MAC_Voting_DB::table('slots');
            $ballots = MAC_Voting_DB::table('ballots');
            $unfinished = (int) $wpdb->get_var("SELECT COUNT(*) FROM $rounds WHERE status!='CLOSED'");
            if ($unfinished) {
                wp_send_json_error(array('message' => 'Hãy đóng đủ cả 3 lượt vote trước khi bắt đầu công bố.'), 409);
            }
            $scheduled = (int) $wpdb->get_var("SELECT COUNT(*) FROM $slots WHERE performance_id IS NOT NULL");
            if ($scheduled !== 6) {
                wp_send_json_error(array('message' => 'Lịch biểu diễn phải có đủ 6 tiết mục trước khi công bố.'), 409);
            }
            $missing = $wpdb->get_col("SELECT t.name FROM $performances p
                JOIN $slots s ON s.performance_id=p.id
                JOIN $teams t ON t.id=p.team_id
                LEFT JOIN $ballots b ON b.performance_id=p.id AND b.status='VALID'
                GROUP BY p.id,t.id
                HAVING COUNT(b.id)=0
                ORDER BY t.team_no") ?: array();
            if ($missing) {
                wp_send_json_error(array('message' => 'Chưa thể xếp hạng vì chưa có phiếu hợp lệ cho: ' . implode(', ', $missing) . '.'), 409);
            }
        }
        $state = MAC_Voting_DB::set_reveal_state($next);
        $messages = array(
            'IDLE' => 'Đã đưa màn hình công bố về trạng thái chờ.',
            'ROLLING' => 'Màn hình đang tung điểm ngẫu nhiên cho 6 đội.',
            'DECOY' => 'Đã chốt cú lừa bằng điểm thật của ba đội cuối.',
            'THIRD' => 'Đã công bố đội xếp hạng ba.',
            'SECOND' => 'Đã công bố đội xếp hạng nhì.',
            'FINAL' => 'Đã công bố quán quân.',
        );
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'RESULTS_REVEAL_' . $next, 'reveal', (string) $state['revision'], array(
            'previousStage' => $current['stage'],
            'stage' => $next,
        ));
        wp_send_json_success(array('message' => $messages[$next], 'overview' => self::overview()));
    }

    public static function ajax_team(): void {
        self::guard();
        global $wpdb;
        $operation = sanitize_key($_POST['operation'] ?? '');
        $team_id = absint($_POST['teamId'] ?? 0);
        $name = trim(sanitize_text_field(wp_unslash($_POST['name'] ?? '')));
        $teams = MAC_Voting_DB::table('teams');
        $voters = MAC_Voting_DB::table('voters');
        $performances = MAC_Voting_DB::table('performances');
        $slots = MAC_Voting_DB::table('slots');
        $ballots = MAC_Voting_DB::table('ballots');
        $grants = MAC_Voting_DB::table('revote_grants');

        if (in_array($operation, array('add', 'rename'), true)) {
            $name_length = mb_strlen($name, 'UTF-8');
            if ($name_length < 2 || $name_length > 100) {
                wp_send_json_error(array('message' => 'Tên team phải có từ 2 đến 100 ký tự.'), 400);
            }
            $duplicate_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $teams WHERE name=%s AND id!=%d LIMIT 1", $name, $team_id));
            if ($duplicate_id) wp_send_json_error(array('message' => 'Tên team này đã tồn tại.'), 409);
        }

        if ($operation === 'add') {
            $used_numbers = array_map('intval', $wpdb->get_col("SELECT team_no FROM $teams ORDER BY team_no"));
            $team_no = 1;
            while (in_array($team_no, $used_numbers, true) && $team_no < 255) $team_no++;
            if ($team_no >= 255 && in_array(255, $used_numbers, true)) wp_send_json_error(array('message' => 'Đã đạt giới hạn số team.'), 409);

            $wpdb->query('START TRANSACTION');
            $inserted_team = $wpdb->insert($teams, array('team_no' => $team_no, 'name' => $name, 'created_at' => MAC_Voting_DB::utc_now()), array('%d','%s','%s'));
            $new_team_id = (int) $wpdb->insert_id;
            $inserted_performance = $inserted_team ? $wpdb->insert($performances, array('team_id' => $new_team_id, 'title' => 'Tiết mục ' . $name, 'created_at' => MAC_Voting_DB::utc_now()), array('%d','%s','%s')) : false;
            if (!$inserted_team || !$inserted_performance) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(array('message' => 'Không thể thêm team. Dữ liệu chưa bị thay đổi.'), 500);
            }
            $wpdb->query('COMMIT');
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'TEAM_CREATED', 'team', (string) $new_team_id, array('teamNo' => $team_no, 'name' => $name));
            $message = "Đã thêm team #$team_no $name. Hãy chọn team vào một slot nếu team sẽ biểu diễn.";
        } elseif ($operation === 'rename') {
            $team = $wpdb->get_row($wpdb->prepare("SELECT * FROM $teams WHERE id=%d", $team_id), ARRAY_A);
            if (!$team) wp_send_json_error(array('message' => 'Team không tồn tại.'), 404);
            if (MAC_Voting_DB::is_staff_team_no((int) $team['team_no'])) {
                wp_send_json_error(array('message' => 'Team Hoa tiêu dành cho BTC, không đổi tên.'), 400);
            }
            $old_name = (string) $team['name'];
            $wpdb->query('START TRANSACTION');
            $updated_team = $wpdb->update($teams, array('name' => $name), array('id' => $team_id), array('%s'), array('%d'));
            $updated_performance = $wpdb->update($performances, array('title' => 'Tiết mục ' . $name), array('team_id' => $team_id), array('%s'), array('%d'));
            if (in_array(false, array($updated_team, $updated_performance), true)) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(array('message' => 'Không thể đổi tên team. Dữ liệu chưa bị thay đổi.'), 500);
            }
            $wpdb->query('COMMIT');
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'TEAM_RENAMED', 'team', (string) $team_id, array('previousName' => $old_name, 'name' => $name));
            $message = "Đã đổi tên team #{$team['team_no']} thành $name.";
        } elseif ($operation === 'delete') {
            $team = $wpdb->get_row($wpdb->prepare(
                "SELECT t.*,p.id AS performance_id,
                    (SELECT COUNT(*) FROM $voters v WHERE v.team_id=t.id) AS voter_count,
                    (SELECT COUNT(*) FROM $slots s WHERE s.performance_id=p.id) AS slot_count,
                    (SELECT COUNT(*) FROM $ballots b WHERE b.performance_id=p.id OR b.voter_id IN (SELECT v2.id FROM $voters v2 WHERE v2.team_id=t.id)) AS ballot_count
                 FROM $teams t LEFT JOIN $performances p ON p.team_id=t.id WHERE t.id=%d",
                $team_id
            ), ARRAY_A);
            if (!$team) wp_send_json_error(array('message' => 'Team không tồn tại.'), 404);
            if (MAC_Voting_DB::is_staff_team_no((int) $team['team_no'])) {
                wp_send_json_error(array('message' => 'Team Hoa tiêu dành cho BTC, không xóa.'), 400);
            }
            if ((int) $team['voter_count'] > 0) wp_send_json_error(array('message' => 'Team còn ' . (int) $team['voter_count'] . ' nhân sự. Hãy chuyển nhân sự sang team khác trước.'), 409);
            if ((int) $team['ballot_count'] > 0) wp_send_json_error(array('message' => 'Team còn dữ liệu phiếu. Hãy xuất kết quả và đặt lại sự kiện trước khi xóa.'), 409);

            $performance_id = (int) $team['performance_id'];
            $wpdb->query('START TRANSACTION');
            if ($performance_id) $wpdb->delete($grants, array('performance_id' => $performance_id), array('%d'));
            $deleted_performance = $performance_id ? $wpdb->delete($performances, array('id' => $performance_id), array('%d')) : 1;
            $deleted_team = $wpdb->delete($teams, array('id' => $team_id), array('%d'));
            if ($deleted_team !== 1 || $deleted_performance === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(array('message' => 'Không thể xóa team. Dữ liệu chưa bị thay đổi.'), 500);
            }
            $wpdb->query('COMMIT');
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'TEAM_DELETED', 'team', (string) $team_id, array('teamNo' => (int) $team['team_no'], 'name' => $team['name']));
            $message = 'Đã xóa team #' . (int) $team['team_no'] . ' ' . $team['name'] . '.';
        } else {
            wp_send_json_error(array('message' => 'Thao tác team không hợp lệ.'), 400);
        }

        wp_send_json_success(array('message' => $message, 'overview' => self::overview()));
    }

    public static function ajax_swap(): void {
        self::guard();
        global $wpdb;
        $slot_id = absint($_POST['slotId'] ?? 0);
        $performance_id = absint($_POST['performanceId'] ?? 0);
        $slots = MAC_Voting_DB::table('slots');
        $rounds = MAC_Voting_DB::table('rounds');
        $performances = MAC_Voting_DB::table('performances');
        $source = $wpdb->get_row($wpdb->prepare("SELECT s.*,r.status FROM $slots s JOIN $rounds r ON r.id=s.round_id WHERE s.id=%d", $slot_id), ARRAY_A);
        $target = $wpdb->get_row($wpdb->prepare("SELECT s.*,r.status FROM $slots s JOIN $rounds r ON r.id=s.round_id WHERE s.performance_id=%d", $performance_id), ARRAY_A);
        $performance_exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $performances WHERE id=%d", $performance_id));
        if (!$source || !$performance_exists) wp_send_json_error(array('message' => 'Slot hoặc tiết mục không tồn tại.'), 404);
        if ($source['status'] !== 'DRAFT' || ($target && $target['status'] !== 'DRAFT')) wp_send_json_error(array('message' => 'Chỉ đổi được team ở lượt chưa mở.'), 409);
        if (!$target || (int) $source['id'] !== (int) $target['id']) {
            $wpdb->query('START TRANSACTION');
            $wpdb->update($slots, array('performance_id' => null), array('id' => (int) $source['id']), array('%s'), array('%d'));
            if ($target) $wpdb->update($slots, array('performance_id' => (int) $source['performance_id']), array('id' => (int) $target['id']), array('%d'), array('%d'));
            $wpdb->update($slots, array('performance_id' => $performance_id), array('id' => (int) $source['id']), array('%d'), array('%d'));
            $wpdb->query('COMMIT');
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'SCHEDULE_SWAPPED', 'slot', (string) $slot_id, array('otherSlotId' => $target ? (int) $target['id'] : null));
        }
        wp_send_json_success(self::overview());
    }

    public static function ajax_ballot(): void {
        self::guard();
        global $wpdb;
        $ballot_id = absint($_POST['ballotId'] ?? 0);
        $operation = sanitize_key($_POST['operation'] ?? '');
        $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));
        if (mb_strlen($reason, 'UTF-8') < 3) wp_send_json_error(array('message' => 'Vui lòng nhập lý do.'), 400);
        $ballots = MAC_Voting_DB::table('ballots');
        $ballot = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ballots WHERE id=%d", $ballot_id), ARRAY_A);
        if (!$ballot) wp_send_json_error(array('message' => 'Phiếu không tồn tại.'), 404);
        if ($operation === 'revoke') {
            if ($ballot['status'] !== 'VALID') wp_send_json_error(array('message' => 'Phiếu đã bị hủy.'), 409);
            $wpdb->update($ballots, array(
                'status' => 'REVOKED', 'active_key' => null, 'revoked_at' => MAC_Voting_DB::utc_now(),
                'revoked_by' => get_current_user_id(), 'revoke_reason' => $reason,
            ), array('id' => $ballot_id), array('%s','%s','%s','%d','%s'), array('%d'));
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'BALLOT_REVOKED', 'ballot', (string) $ballot_id, array('reason' => $reason));
        } elseif ($operation === 'revote') {
            if ($ballot['status'] !== 'REVOKED') wp_send_json_error(array('message' => 'Chỉ cấp vote lại cho phiếu đã hủy.'), 409);
            $grants = MAC_Voting_DB::table('revote_grants');
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $grants (voter_id,performance_id,unused_key,granted_by,reason,created_at) VALUES (%d,%d,'UNUSED',%d,%s,%s)",
                (int) $ballot['voter_id'], (int) $ballot['performance_id'], get_current_user_id(), $reason, MAC_Voting_DB::utc_now()
            ));
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'REVOTE_GRANTED', 'ballot', (string) $ballot_id, array('reason' => $reason));
        } else {
            wp_send_json_error(array('message' => 'Thao tác không hợp lệ.'), 400);
        }
        wp_send_json_success(self::overview());
    }

    public static function ajax_import(): void {
        self::guard();
        $dry_run = !empty($_POST['dryRun']);
        if ((int) ($_FILES['file']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) wp_send_json_error(array('message' => 'WordPress không nhận được file upload. Mã lỗi: ' . (int) $_FILES['file']['error']), 400);
        if (empty($_FILES['file']['tmp_name'])) wp_send_json_error(array('message' => 'Vui lòng chọn file CSV.'), 400);
        if ((int) $_FILES['file']['size'] > 5 * MB_IN_BYTES) wp_send_json_error(array('message' => 'File không được lớn hơn 5 MB.'), 400);
        $handle = fopen($_FILES['file']['tmp_name'], 'rb');
        if (!$handle) wp_send_json_error(array('message' => 'Không thể đọc file.'), 400);
        $first_line = (string) fgets($handle);
        $delimiter = substr_count($first_line, ';') > substr_count($first_line, ',') ? ';' : ',';
        rewind($handle);
        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        if (!$header) wp_send_json_error(array('message' => 'File CSV rỗng.'), 400);
        if (isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $headers = array_map(static function($value): string { return MAC_Voting_DB::normalize_name((string) $value); }, $header);
        $aliases = array(
            'name' => array('ho ten','ten','full name'), 'employee' => array('ma nv','ma nhan vien','employee code'),
            'team' => array('team','doi'), 'email' => array('email','mail','email cong ty'), 'status' => array('trang thai','status'),
            'role' => array('vai tro','role','btc','chuc vu'), 'password' => array('mat khau','password','pass'),
        );
        $columns = array();
        foreach ($aliases as $key => $names) {
            foreach ($names as $name) {
                $index = array_search($name, $headers, true);
                if ($index !== false) { $columns[$key] = $index; break; }
            }
        }
        if (!isset($columns['name'], $columns['team'], $columns['email'])) wp_send_json_error(array('message' => 'CSV phải có cột Họ tên, Team, Email.'), 400);

        global $wpdb;
        $teams_table = MAC_Voting_DB::table('teams');
        $voters = MAC_Voting_DB::table('voters');
        $teams = $wpdb->get_results("SELECT * FROM $teams_table ORDER BY team_no", ARRAY_A);
        $inserted = 0; $updated = 0; $line = 1; $errors = array(); $identity_rows = array(); $pending_staff = array();
        $wpdb->query('START TRANSACTION');
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $line++;
            $name = sanitize_text_field($row[$columns['name']] ?? '');
            $team_value = sanitize_text_field($row[$columns['team']] ?? '');
            $email_value = sanitize_text_field($row[$columns['email']] ?? '');
            $email = MAC_Voting_DB::normalize_company_email($email_value);
            if (!$name && !$team_value && !$email_value) continue;
            $team = null;
            foreach ($teams as $candidate) {
                if (MAC_Voting_DB::normalize_name($team_value) === MAC_Voting_DB::normalize_name($candidate['name']) || preg_match('/#?\s*' . (int) $candidate['team_no'] . '\b/', $team_value)) {
                    $team = $candidate; break;
                }
            }
            $role_text = isset($columns['role']) ? (string) ($row[$columns['role']] ?? '') : '';
            $password_text = isset($columns['password']) ? (string) ($row[$columns['password']] ?? '') : '';
            $staff_kind = self::csv_staff_kind($role_text);
            if (!$staff_kind && $team && MAC_Voting_DB::is_staff_team_no((int) $team['team_no'])) {
                $staff_kind = 'btc';
            }
            if ($staff_kind) {
                $staff_team_id = MAC_Voting_DB::staff_team_id();
                $team = array('id' => $staff_team_id, 'team_no' => MAC_Voting_DB::STAFF_TEAM_NO, 'name' => MAC_Voting_DB::STAFF_TEAM_NAME);
            }
            $row_issues = array();
            if (!$name) $row_issues[] = 'thiếu họ tên';
            if (!$team) $row_issues[] = 'team không hợp lệ: ' . ($team_value ?: '(trống)');
            if (!$email) $row_issues[] = 'email phải thuộc @macusaone.com, @yesoffice.vn hoặc @macmarketing.vn';
            if ($row_issues) { $errors[] = "Dòng $line: " . implode(', ', $row_issues); continue; }
            $employee = isset($columns['employee']) ? sanitize_text_field($row[$columns['employee']] ?? '') : '';
            $status_text = isset($columns['status']) ? MAC_Voting_DB::normalize_name((string) ($row[$columns['status']] ?? '')) : 'hoat dong';
            $status = in_array($status_text, array('inactive','khong','khong hoat dong','0','false'), true) ? 'INACTIVE' : 'ACTIVE';
            $employee = $employee ? strtoupper($employee) : '';
            $email_existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $voters WHERE email=%s", $email));
            $employee_existing_id = $employee ? (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $voters WHERE employee_code=%s", $employee)) : 0;
            if ($email_existing_id && $employee_existing_id && $email_existing_id !== $employee_existing_id) {
                $errors[] = "Dòng $line: email và Mã NV đang thuộc hai nhân sự khác nhau";
                continue;
            }
            $existing_id = $email_existing_id ?: $employee_existing_id;
            if (!$existing_id && !$employee) {
                $matches = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM $voters WHERE search_name=%s AND team_id=%d ORDER BY id",
                    MAC_Voting_DB::normalize_name($name),
                    (int) $team['id']
                ));
                if (count($matches) === 1) {
                    $existing_id = (int) $matches[0];
                } elseif (count($matches) > 1) {
                    $errors[] = "Dòng $line: có nhiều người trùng họ tên và team; hãy thêm Mã NV để mapping đúng dữ liệu cũ";
                    continue;
                }
            }
            $identity_key = $email;
            if (isset($identity_rows[$identity_key])) {
                $errors[] = "Dòng $line trùng email với dòng " . $identity_rows[$identity_key];
                continue;
            }
            $identity_rows[$identity_key] = $line;
            $conflicting_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $voters WHERE email=%s AND id!=%d LIMIT 1",
                $email,
                $existing_id
            ));
            if ($conflicting_id) {
                $errors[] = "Dòng $line: email đã thuộc một nhân sự khác trong hệ thống";
                continue;
            }
            if ($staff_kind) {
                $pending_staff[] = array('name' => $name, 'email' => $email, 'password' => $password_text, 'kind' => $staff_kind);
            }
            $data = array(
                'full_name' => $name, 'search_name' => MAC_Voting_DB::normalize_name($name),
                'employee_code' => $employee ?: null, 'email' => $email, 'team_id' => (int) $team['id'],
                'phone_last4_hash' => '', 'status' => $status, 'updated_at' => MAC_Voting_DB::utc_now(),
            );
            if ($dry_run) {
                if ($existing_id) $updated++;
                else $inserted++;
                continue;
            }
            if ($existing_id) {
                $saved = $wpdb->update($voters, $data, array('id' => $existing_id));
                if ($saved === false) $errors[] = "Dòng $line không lưu được: " . ($wpdb->last_error ?: 'lỗi database');
                else $updated++;
            } else {
                $data['created_at'] = MAC_Voting_DB::utc_now();
                $saved = $wpdb->insert($voters, $data);
                if ($saved === false) $errors[] = "Dòng $line không lưu được: " . ($wpdb->last_error ?: 'lỗi database');
                else $inserted++;
            }
        }
        fclose($handle);
        if ($errors) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => implode('; ', array_slice($errors, 0, 8))), 400);
        }
        if ($dry_run) {
            $wpdb->query('ROLLBACK');
            wp_send_json_success(array(
                'message' => "Server đã kiểm tra đủ " . ($inserted + $updated) . " người: $inserted mới, $updated sẽ cập nhật. Chưa có dữ liệu nào được ghi.",
                'inserted' => $inserted,
                'updated' => $updated,
            ));
        }
        $collision = $wpdb->get_row("SELECT MIN(full_name) AS full_name,email,COUNT(*) AS total FROM $voters WHERE status='ACTIVE' AND email IS NOT NULL GROUP BY email HAVING COUNT(*)>1 LIMIT 1", ARRAY_A);
        if ($collision) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => 'Email đăng nhập của ' . ($collision['full_name'] ?: 'một nhân sự') . ' đang bị trùng. Hãy kiểm tra dữ liệu đã import.'), 409);
        }
        $wpdb->query('COMMIT');
        $staff_accounts = array();
        $staff_errors = array();
        foreach ($pending_staff as $item) {
            $created = MAC_Checkin::ensure_staff_user($item['name'], $item['email'], $item['password'], $item['kind'] ?? 'btc');
            if (is_wp_error($created)) {
                $staff_errors[] = $item['email'] . ': ' . $created->get_error_message();
                continue;
            }
            if (!empty($created['password'])) {
                $staff_accounts[] = $created;
            }
        }
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'VOTERS_IMPORTED', 'voter', null, array('inserted' => $inserted, 'updated' => $updated, 'staff' => count($pending_staff)));
        $message = "Đã import: $inserted mới, $updated cập nhật.";
        if ($pending_staff) {
            $message .= ' Đã cấp tài khoản Super admin/BTC cho ' . count($pending_staff) . ' người.';
        }
        if ($staff_errors) {
            $message .= ' Một số tài khoản BTC chưa tạo được: ' . implode('; ', array_slice($staff_errors, 0, 3));
        }
        wp_send_json_success(array(
            'message' => $message,
            'overview' => self::overview(),
            'staffAccounts' => $staff_accounts,
        ));
    }

    private static function overview_payload(): array {
        return self::overview();
    }

    private static function overview(): array {
        global $wpdb;
        $teams = MAC_Voting_DB::table('teams'); $voters = MAC_Voting_DB::table('voters');
        $performances = MAC_Voting_DB::table('performances'); $rounds = MAC_Voting_DB::table('rounds');
        $slots = MAC_Voting_DB::table('slots'); $ballots = MAC_Voting_DB::table('ballots');
        $round_rows = $wpdb->get_results("SELECT * FROM $rounds ORDER BY id", ARRAY_A) ?: array();
        $slot_rows = $wpdb->get_results("SELECT s.*,p.title,t.id AS team_id,t.name AS team_name,t.team_no FROM $slots s JOIN $performances p ON p.id=s.performance_id JOIN $teams t ON t.id=p.team_id ORDER BY s.id", ARRAY_A) ?: array();
        foreach ($round_rows as &$round) {
            $round['id'] = (int) $round['id']; $round['slots'] = array_values(array_filter($slot_rows, static fn($slot): bool => (int) $slot['round_id'] === (int) $round['id']));
        }
        unset($round);
        $results = $wpdb->get_results("SELECT p.id AS performance_id,t.id AS team_id,t.team_no,t.name AS team_name,COUNT(b.id) AS voter_count,AVG(b.total_score) AS average_score,AVG(b.style_score) AS style_average,AVG(b.staging_score) AS staging_average,AVG(b.teamwork_score) AS teamwork_average FROM $performances p JOIN $slots s ON s.performance_id=p.id JOIN $teams t ON t.id=p.team_id LEFT JOIN $ballots b ON b.performance_id=p.id AND b.status='VALID' GROUP BY p.id,t.id ORDER BY average_score DESC,t.team_no", ARRAY_A) ?: array();
        $recent = $wpdb->get_results("SELECT b.id,b.status,b.total_score,b.style_score,b.staging_score,b.teamwork_score,b.created_at,b.revoke_reason,v.full_name,vt.name AS voter_team,pt.name AS performance_team,EXISTS(SELECT 1 FROM " . MAC_Voting_DB::table('revote_grants') . " g WHERE g.voter_id=b.voter_id AND g.performance_id=b.performance_id AND g.unused_key='UNUSED') AS has_revote_grant FROM $ballots b JOIN $voters v ON v.id=b.voter_id JOIN $teams vt ON vt.id=v.team_id JOIN $performances p ON p.id=b.performance_id JOIN $teams pt ON pt.id=p.team_id ORDER BY b.created_at DESC LIMIT 100", ARRAY_A) ?: array();
        $team_rows = $wpdb->get_results("SELECT t.id,t.team_no,t.name,p.id AS performance_id,
                (SELECT COUNT(*) FROM $voters v WHERE v.team_id=t.id) AS voter_count,
                EXISTS(SELECT 1 FROM $slots s WHERE s.performance_id=p.id) AS is_scheduled,
                (SELECT COUNT(*) FROM $ballots b WHERE b.performance_id=p.id OR b.voter_id IN (SELECT v2.id FROM $voters v2 WHERE v2.team_id=t.id)) AS ballot_count
            FROM $teams t LEFT JOIN $performances p ON p.team_id=t.id ORDER BY t.team_no", ARRAY_A) ?: array();
        foreach ($team_rows as &$team_row) {
            $team_row['isStaff'] = MAC_Voting_DB::is_staff_team_no((int) $team_row['team_no']);
        }
        unset($team_row);
        return array(
            'rounds' => $round_rows, 'results' => $results, 'ballots' => $recent,
            'reveal' => MAC_Voting_DB::reveal_state(),
            'votingEnabled' => MAC_Voting_DB::is_voting_enabled(),
            'checkpoints' => MAC_Checkin::checkpoints(),
            'checkinBoard' => self::checkin_overview_board(),
            'teamPoints' => MAC_Checkin::team_points_board(),
            'totalBoard' => MAC_Points::dashboard(),
            'staff' => MAC_Checkin::staff_assignments(),
            'assignableUsers' => self::assignable_users(),
            'voters' => self::voter_rows(),
            'teams' => $team_rows,
            'performances' => $wpdb->get_results("SELECT p.id,t.id AS team_id,t.name AS team_name,t.team_no FROM $performances p JOIN $teams t ON t.id=p.team_id ORDER BY t.team_no", ARRAY_A) ?: array(),
            'stats' => array(
                'activeVoters' => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $voters v JOIN $teams t ON t.id=v.team_id WHERE v.status='ACTIVE' AND t.team_no<>%d",
                    MAC_Voting_DB::STAFF_TEAM_NO
                )),
                'missingEmailVoters' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $voters WHERE status='ACTIVE' AND (email IS NULL OR email='')"),
                'validBallots' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $ballots WHERE status='VALID'"),
                'revokedBallots' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $ballots WHERE status='REVOKED'"),
                'openCheckpointId' => ($active = MAC_Checkin::active_checkpoint()) ? (int) $active['id'] : 0,
            ),
        );
    }

    private static function voter_rows(): array {
        global $wpdb;
        $voters = MAC_Voting_DB::table('voters');
        $teams = MAC_Voting_DB::table('teams');
        $rows = $wpdb->get_results("SELECT v.id,v.full_name,v.email,v.employee_code,v.status,v.qr_version,t.id AS team_id,t.name AS team_name,t.team_no
            FROM $voters v JOIN $teams t ON t.id=v.team_id ORDER BY t.team_no,v.full_name", ARRAY_A) ?: array();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['qrUrl'] = MAC_Voting_QR::url_for_voter((int) $row['id'], (int) $row['qr_version']);
        }
        unset($row);
        return $rows;
    }

    private static function csv_staff_kind(string $value): string {
        $role = MAC_Voting_DB::normalize_name($value);
        if (in_array($role, array('super', 'super admin', 'supper admin', 'superadmin', 'quan tri'), true)) {
            return 'super';
        }
        if (in_array($role, array('btc', 'ban to chuc', 'scanner', 'checkin', 'check in', 'admin'), true)) {
            return 'btc';
        }
        return '';
    }

    private static function checkin_overview_board(): array {
        $board = array();
        foreach (MAC_Checkin::checkpoints() as $checkpoint) {
            $board[] = array(
                'checkpoint' => $checkpoint,
                'teams' => MAC_Checkin::checkpoint_board((int) $checkpoint['id']),
            );
        }
        return $board;
    }

    private static function assignable_users(): array {
        $users = get_users(array('orderby' => 'display_name', 'number' => 200));
        $items = array();
        foreach ($users as $user) {
            $items[] = array(
                'id' => (int) $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
            );
        }
        return $items;
    }

    public static function ajax_gate(): void {
        self::guard();
        $enabled = !empty($_POST['enabled']) && $_POST['enabled'] !== '0';
        MAC_Voting_DB::set_voting_enabled($enabled);
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), $enabled ? 'VOTING_MODULE_ENABLED' : 'VOTING_MODULE_DISABLED', 'setting', 'mac_voting_public_enabled');
        wp_send_json_success(array(
            'message' => $enabled ? 'Đã bật cổng văn nghệ. QR cá nhân và username đã mở.' : 'Đã tắt cổng văn nghệ.',
            'overview' => self::overview(),
        ));
    }

    public static function ajax_checkpoint(): void {
        self::guard();
        $checkpoint_id = absint($_POST['checkpointId'] ?? 0);
        $operation = sanitize_key($_POST['operation'] ?? '');
        if ($operation === 'open') {
            $result = MAC_Checkin::open_checkpoint($checkpoint_id);
        } elseif ($operation === 'reopen') {
            $result = MAC_Checkin::reopen_checkpoint($checkpoint_id);
        } elseif ($operation === 'close') {
            $result = MAC_Checkin::close_checkpoint($checkpoint_id);
        } else {
            wp_send_json_error(array('message' => 'Thao tác không hợp lệ.'), 400);
            return;
        }
        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code(),
                'openCheckpointId' => is_array($error_data) ? ($error_data['openCheckpointId'] ?? null) : null,
            ), is_array($error_data) ? (int) ($error_data['status'] ?? 400) : 400);
        }
        wp_send_json_success(self::overview_payload());
    }

    public static function ajax_qr(): void {
        self::guard();
        $voter_id = absint($_POST['voterId'] ?? 0);
        $operation = sanitize_key($_POST['operation'] ?? '');
        global $wpdb;
        $voters = MAC_Voting_DB::table('voters');
        $voter = $wpdb->get_row($wpdb->prepare("SELECT * FROM $voters WHERE id=%d", $voter_id), ARRAY_A);
        if (!$voter) {
            wp_send_json_error(array('message' => 'Không tìm thấy nhân sự.'), 404);
        }
        if ($operation === 'regenerate') {
            $version = MAC_Voting_QR::regenerate($voter_id);
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'QR_REGENERATED', 'voter', (string) $voter_id, array('qrVersion' => $version));
            wp_send_json_success(array(
                'message' => 'Đã tạo QR mới. QR cũ không còn hiệu lực.',
                'overview' => self::overview(),
            ));
        }
        if ($operation === 'email') {
            if (empty($voter['email'])) {
                wp_send_json_error(array('message' => 'Nhân sự này chưa có email.'), 400);
            }
            $png = self::decode_png((string) ($_POST['png'] ?? ''));
            if (is_wp_error($png)) {
                wp_send_json_error(array('message' => $png->get_error_message()), 400);
            }
            $sent = self::send_qr_email($voter, $png);
            if (is_wp_error($sent)) {
                wp_send_json_error(array('message' => $sent->get_error_message()), 500);
            }
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'QR_EMAIL_SENT', 'voter', (string) $voter_id, array('email' => $voter['email']));
            wp_send_json_success(array('message' => 'Đã gửi QR tới ' . $voter['email'] . '.'));
        }
        wp_send_json_error(array('message' => 'Thao tác không hợp lệ.'), 400);
    }

    public static function ajax_staff(): void {
        self::guard();
        $user_id = absint($_POST['userId'] ?? 0);
        $team_ids = array_map('intval', (array) ($_POST['teamIds'] ?? array()));
        if (isset($_POST['teamIds']) && is_string($_POST['teamIds'])) {
            $decoded = json_decode(wp_unslash($_POST['teamIds']), true);
            $team_ids = is_array($decoded) ? array_map('intval', $decoded) : array();
        }
        $saved = MAC_Checkin::save_staff($user_id, $team_ids);
        if (is_wp_error($saved)) {
            wp_send_json_error(array('message' => $saved->get_error_message()), 400);
        }
        wp_send_json_success(array('message' => 'Đã cập nhật quyền check-in.', 'overview' => self::overview()));
    }

    public static function ajax_points(): void {
        self::guard();
        $operation = sanitize_key($_POST['operation'] ?? '');
        if ($operation === 'add') {
            $result = MAC_Points::add_category(sanitize_text_field(wp_unslash($_POST['name'] ?? '')));
        } elseif ($operation === 'rename') {
            $result = MAC_Points::rename_category(
                absint($_POST['categoryId'] ?? 0),
                sanitize_text_field(wp_unslash($_POST['name'] ?? ''))
            );
        } elseif ($operation === 'delete') {
            $result = MAC_Points::delete_category(absint($_POST['categoryId'] ?? 0));
        } elseif ($operation === 'award') {
            $result = MAC_Points::award(
                absint($_POST['categoryId'] ?? 0),
                absint($_POST['teamId'] ?? 0),
                intval($_POST['points'] ?? 0)
            );
        } else {
            wp_send_json_error(array('message' => 'Thao tác không hợp lệ.'), 400);
            return;
        }
        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            wp_send_json_error(array('message' => $result->get_error_message()), is_array($error_data) ? (int) ($error_data['status'] ?? 400) : 400);
        }
        $messages = array(
            'add' => 'Đã thêm hạng mục.',
            'rename' => 'Đã đổi tên hạng mục.',
            'delete' => 'Đã xóa hạng mục.',
            'award' => 'Đã cập nhật điểm team.',
        );
        wp_send_json_success(array(
            'message' => $messages[$operation] ?? 'Đã cập nhật.',
            'overview' => self::overview(),
            'categoryId' => $operation === 'add' ? (int) $result : 0,
        ));
    }

    private static function decode_png(string $value) {
        $value = trim($value);
        if (strpos($value, 'base64,') !== false) {
            $value = substr($value, strpos($value, 'base64,') + 7);
        }
        $binary = base64_decode($value, true);
        if (!$binary || strlen($binary) > 400000 || strpos($binary, "\x89PNG") !== 0) {
            return new WP_Error('invalid_png', 'Ảnh QR không hợp lệ.');
        }
        return $binary;
    }

    private static function send_qr_email(array $voter, string $png) {
        $tmp = wp_tempnam('mac-qr');
        if (!$tmp || !file_put_contents($tmp, $png)) {
            return new WP_Error('io', 'Không tạo được file QR để gửi mail.');
        }
        $png_path = $tmp . '.png';
        rename($tmp, $png_path);
        $cid_hook = static function($phpmailer) use ($png_path): void {
            $phpmailer->addEmbeddedImage($png_path, 'mac-qr', 'qr-ca-nhan.png', 'base64', 'image/png');
        };
        add_action('phpmailer_init', $cid_hook);
        $team = '';
        if (!empty($voter['team_id'])) {
            global $wpdb;
            $teams = MAC_Voting_DB::table('teams');
            $team_row = $wpdb->get_row($wpdb->prepare("SELECT team_no,name FROM $teams WHERE id=%d", (int) $voter['team_id']), ARRAY_A);
            if ($team_row) {
                $team = '#' . (int) $team_row['team_no'] . ' ' . $team_row['name'];
            }
        }
        $subject = 'QR cá nhân MAC Company Trip — ' . $voter['full_name'];
        $html = '<div style="font-family:Inter,Arial,sans-serif;color:#111827;background:#f5f5f7;padding:24px">';
        $html .= '<div style="max-width:480px;margin:0 auto;background:#fff;border:1px solid #e4e7ec;border-radius:18px;padding:24px;text-align:center">';
        $html .= '<p style="margin:0 0 8px;color:#667085;letter-spacing:.12em;font-size:12px;font-weight:700">MAC COMPANY TRIP</p>';
        $html .= '<h1 style="margin:0 0 12px;font-size:24px">QR cá nhân của bạn</h1>';
        $html .= '<p style="margin:0 0 16px;color:#667085">Xin chào <strong>' . esc_html($voter['full_name']) . '</strong>' . ($team ? ' · ' . esc_html($team) : '') . '</p>';
        $html .= '<img src="cid:mac-qr" alt="QR cá nhân" width="220" height="220" style="width:220px;height:220px;border:1px solid #e4e7ec;border-radius:12px;padding:8px">';
        $html .= '<p style="margin:16px 0 0;color:#667085;font-size:14px;line-height:1.5">Đưa QR này cho BTC khi check-in. Tối văn nghệ, tự quét QR để vào chấm điểm khi ban tổ chức bật cổng.</p>';
        $html .= '</div></div>';
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($voter['email'], $subject, $html, $headers, array($png_path));
        remove_action('phpmailer_init', $cid_hook);
        @unlink($png_path);
        if (!$sent) {
            return new WP_Error('mail', 'WordPress không gửi được email. Kiểm tra cấu hình mail của site.');
        }
        return true;
    }

    public static function template_csv(): void {
        if (!MAC_Checkin::is_super() || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'mac_vote_template')) wp_die('Không có quyền.');
        self::csv_headers('mau-import-nhan-su.csv');
        $out = fopen('php://output', 'wb'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array('Họ tên','Mã NV','Team','Email','Trạng thái','Vai trò','Mật khẩu'));
        fputcsv($out, array('Nguyễn Văn A','MAC001','#1 La Bàn','nguyenvana@macusaone.com','Hoạt động','',''));
        fputcsv($out, array('Trần Thị B','MAC002','#1 La Bàn','tranthib','Hoạt động','',''));
        fputcsv($out, array('Lê Văn C','MAC003','#2 Hải Đồ','levanc@macusaone.com','Hoạt động','',''));
        fputcsv($out, array('Phạm Thị D','MAC004','#3 Đèn Hiệu','phamthid','Hoạt động','',''));
        fputcsv($out, array('Hoàng Văn E','MAC005','#4 Viking','hoangvane@macusaone.com','Hoạt động','',''));
        fputcsv($out, array('Vũ Thị F','MAC006','#5 Sao Bắc Cực','vuthif','Hoạt động','',''));
        fputcsv($out, array('Đặng Văn G','MAC007','#6 Hải Đăng','dangvang@macusaone.com','Hoạt động','',''));
        fputcsv($out, array('Ngô Thị H','MAC008','#2 Hải Đồ','ngothih','Không hoạt động','',''));
        fputcsv($out, array('Trần Văn BTC','MAC100','#7 Hoa tiêu','tranvanbtc','Hoạt động','BTC',''));
        fputcsv($out, array('Lê Thị Super','MAC101','#7 Hoa tiêu','lethisuper@macmarketing.vn','Hoạt động','Super admin',''));
        fclose($out); exit;
    }

    public static function export_csv(): void {
        if (!MAC_Checkin::is_super() || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'mac_vote_export')) wp_die('Không có quyền.');
        global $wpdb;
        self::csv_headers('ket-qua-companytrip.csv');
        $out = fopen('php://output', 'wb'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array('Hạng','Team','Số phiếu','Điểm trung bình','Phong cách','Dàn dựng','Đồng đội'));
        $previous_score = null; $current_rank = 0;
        foreach (self::overview()['results'] as $index => $row) {
            if ($row['average_score'] === null) {
                $display_rank = '—';
            } else {
                if ($previous_score === null || (float) $previous_score !== (float) $row['average_score']) $current_rank = $index + 1;
                $display_rank = $current_rank; $previous_score = $row['average_score'];
            }
            fputcsv($out, array($display_rank, '#' . $row['team_no'] . ' ' . $row['team_name'], $row['voter_count'], $row['average_score'] ?? 'Chưa có lượt vote', $row['style_average'] ?? '', $row['staging_average'] ?? '', $row['teamwork_average'] ?? ''));
        }
        fputcsv($out, array()); fputcsv($out, array('CHI TIẾT PHIẾU'));
        fputcsv($out, array('Người chấm','Team người chấm','Tiết mục','Tổng điểm','Trạng thái','Thời gian'));
        foreach (self::overview()['ballots'] as $row) fputcsv($out, array($row['full_name'],$row['voter_team'],$row['performance_team'],$row['total_score'],$row['status'],$row['created_at']));
        fclose($out); exit;
    }

    public static function export_checkin_csv(): void {
        if (!MAC_Checkin::is_super() || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'mac_vote_export_checkin')) wp_die('Không có quyền.');
        global $wpdb;
        self::csv_headers('checkin-companytrip.csv');
        $out = fopen('php://output', 'wb'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array('Checkpoint','Team','Họ tên','Email','Trạng thái check-in','Scanned at','Scanned by'));
        $checkpoints = MAC_Voting_DB::table('checkpoints');
        $checkins = MAC_Voting_DB::table('checkins');
        $voters = MAC_Voting_DB::table('voters');
        $teams = MAC_Voting_DB::table('teams');
        $rows = $wpdb->get_results("SELECT c.name AS checkpoint_name,t.team_no,t.name AS team_name,v.full_name,v.email,
                CASE WHEN i.id IS NULL THEN 'Chưa check-in' ELSE 'Đã check-in' END AS checkin_status,
                i.scanned_at,i.scanned_by
            FROM $voters v
            JOIN $teams t ON t.id=v.team_id
            CROSS JOIN $checkpoints c
            LEFT JOIN $checkins i ON i.voter_id=v.id AND i.checkpoint_id=c.id
            WHERE v.status='ACTIVE'
            ORDER BY c.id,t.team_no,v.full_name", ARRAY_A) ?: array();
        foreach ($rows as $row) {
            $scanned_by = '';
            if (!empty($row['scanned_by'])) {
                $user = get_userdata((int) $row['scanned_by']);
                $scanned_by = $user ? $user->display_name : (string) $row['scanned_by'];
            }
            fputcsv($out, array($row['checkpoint_name'], '#' . $row['team_no'] . ' ' . $row['team_name'], $row['full_name'], $row['email'], $row['checkin_status'], $row['scanned_at'], $scanned_by));
        }
        fputcsv($out, array());
        fputcsv($out, array('XẾP HẠNG'));
        fputcsv($out, array('Checkpoint','Team','Eligible','Checked in','Completed at','Rank','Points'));
        foreach (MAC_Checkin::checkpoints() as $checkpoint) {
            foreach (MAC_Checkin::checkpoint_board((int) $checkpoint['id']) as $team) {
                fputcsv($out, array($checkpoint['name'], '#' . $team['teamNumber'] . ' ' . $team['teamName'], $team['eligible'], $team['checkedIn'], $team['completedAt'], $team['temporaryRank'], $team['temporaryPoints']));
            }
        }
        fclose($out); exit;
    }

    private static function csv_headers(string $filename): void {
        nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $filename . '"');
    }
}
