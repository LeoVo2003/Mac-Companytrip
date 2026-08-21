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
        add_action('wp_ajax_mac_vote_reveal_total', array(__CLASS__, 'ajax_reveal_total'));
        add_action('wp_ajax_mac_vote_toggle_scores', array(__CLASS__, 'ajax_toggle_scores'));
        add_action('wp_ajax_mac_vote_team', array(__CLASS__, 'ajax_team'));
        add_action('wp_ajax_mac_vote_swap', array(__CLASS__, 'ajax_swap'));
        add_action('wp_ajax_mac_vote_ballot', array(__CLASS__, 'ajax_ballot'));
        add_action('wp_ajax_mac_vote_import', array(__CLASS__, 'ajax_import'));
        add_action('wp_ajax_mac_vote_gate', array(__CLASS__, 'ajax_gate'));
        add_action('wp_ajax_mac_vote_checkpoint', array(__CLASS__, 'ajax_checkpoint'));
        add_action('wp_ajax_mac_vote_qr', array(__CLASS__, 'ajax_qr'));
        add_action('wp_ajax_mac_vote_staff', array(__CLASS__, 'ajax_staff'));
        add_action('wp_ajax_mac_vote_person', array(__CLASS__, 'ajax_person'));
        add_action('wp_ajax_mac_vote_points', array(__CLASS__, 'ajax_points'));
        add_action('wp_ajax_mac_vote_games', array(__CLASS__, 'ajax_games'));
        add_action('wp_ajax_mac_vote_exemption', array(__CLASS__, 'ajax_exemption'));
        add_action('wp_ajax_mac_vote_seed_demo', array(__CLASS__, 'ajax_seed_demo'));
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
                <?php echo self::loading_markup(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Loader "bánh xe" (cảm hứng lucky-hound-44, phối màu thương hiệu MAC)
     * hiển thị trong lúc dashboard đang dựng giao diện.
     */
    public static function loading_markup(): string {
        $spokes = '';
        for ($i = 0; $i < 9; $i++) {
            $rot = $i * 40;
            $delay = $i * 0.2;
            $spokes .= '<span class="mac-loader-spoke" style="--rot:' . $rot . 'deg;--delay:' . $delay . 's"><span class="mac-loader-ball"></span></span>';
        }
        return '<div class="mac-admin-loading" role="status" aria-live="polite">'
            . '<div class="mac-loader-wheel" aria-hidden="true">' . $spokes . '</div>'
            . '<span class="mac-admin-loading-text">Đang tải dashboard...</span>'
            . '</div>';
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
        $reset_rounds = $wpdb->query("UPDATE $rounds SET status='DRAFT', opened_at=NULL, closes_at=NULL, closed_at=NULL");
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

    /**
     * Nút "Áp dữ liệu demo" (chỉ super admin, đặt kín ở sidebar): ghi thẳng một bộ
     * dữ liệu ảo hoàn chỉnh vào database — 48 nhân sự demo, 240 phiếu hợp lệ,
     * điểm check-in / trò chơi / thi đua — dùng để diễn tập màn hình công bố.
     * Bấm nhiều lần chỉ ghi đè đúng bộ demo, không nhân bản.
     */
    public static function ajax_seed_demo(): void {
        self::guard();
        $error = self::seed_demo_data();
        if (is_wp_error($error)) {
            wp_send_json_error(array('message' => $error->get_error_message()), 500);
        }
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'DEMO_DATA_SEEDED', 'event', null, array(
            'demoVoters' => 48,
            'demoBallots' => 240,
        ));
        wp_send_json_success(array(
            'message' => 'Đã áp dữ liệu demo: 48 nhân sự ảo, 240 phiếu và điểm check-in · trò chơi · thi đua.',
            'overview' => self::overview(),
        ));
    }

    private static function seed_demo_data() {
        global $wpdb;
        $voters = MAC_Voting_DB::table('voters');
        $teams_table = MAC_Voting_DB::table('teams');
        $performances_table = MAC_Voting_DB::table('performances');
        $slots = MAC_Voting_DB::table('slots');
        $ballots = MAC_Voting_DB::table('ballots');
        $grants = MAC_Voting_DB::table('revote_grants');
        $checkins = MAC_Voting_DB::table('checkins');
        $exemptions = MAC_Voting_DB::table('exemptions');
        $points = MAC_Voting_DB::table('team_points');
        $thidua_rounds = MAC_Voting_DB::table('thidua_rounds');
        $now = MAC_Voting_DB::utc_now();

        $teams = $wpdb->get_results($wpdb->prepare(
            "SELECT id,team_no FROM $teams_table WHERE team_no<>%d ORDER BY team_no",
            MAC_Voting_DB::STAFF_TEAM_NO
        ), ARRAY_A) ?: array();
        if (count($teams) < 6) {
            return new WP_Error('missing_teams', 'Cần đủ 6 team thi đấu trước khi áp dữ liệu demo.');
        }
        $team_id_by_no = array();
        foreach ($teams as $team) {
            $team_id_by_no[(int) $team['team_no']] = (int) $team['id'];
        }
        $schedule = $wpdb->get_results(
            "SELECT s.round_id, p.id AS performance_id, t.team_no
             FROM $slots s
             JOIN $performances_table p ON p.id=s.performance_id
             JOIN $teams_table t ON t.id=p.team_id",
            ARRAY_A
        ) ?: array();
        if (count($schedule) < 6) {
            return new WP_Error('missing_schedule', 'Lịch biểu diễn chưa đủ 6 tiết mục để tạo phiếu demo.');
        }

        // Điểm trung bình phiếu mục tiêu của từng đội (thang 150).
        $vote_targets = array(1 => 132, 2 => 121, 3 => 141, 4 => 108, 5 => 127, 6 => 114);
        // Điểm 4 trạm check-in (tối đa 150đ/trạm).
        $checkin_matrix = array(
            1 => array(150, 140, 130, 140),
            2 => array(145, 150, 140, 150),
            3 => array(130, 135, 140, 135),
            4 => array(120, 125, 115, 135),
            5 => array(150, 145, 150, 145),
            6 => array(125, 130, 130, 130),
        );
        // Hạng từng game (thang 50 · 40 · 30 · 20 · 10 · 0).
        $game_ranks = array(
            1 => array(5 => 1, 2 => 2, 1 => 3, 3 => 4, 6 => 5, 4 => 6),
            2 => array(2 => 1, 5 => 2, 3 => 3, 1 => 4, 4 => 5, 6 => 6),
            3 => array(1 => 1, 3 => 2, 5 => 3, 6 => 4, 2 => 5, 4 => 6),
        );
        // Hạng 2 vòng thi đua mặc định.
        $thidua_ranks = array(
            array(3 => 1, 5 => 2, 1 => 3, 2 => 4, 6 => 5),
            array(5 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5),
        );
        $family_names = array('Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Phan', 'Vũ', 'Đặng', 'Bùi', 'Đỗ');
        $middle_names = array('Văn', 'Thị', 'Minh', 'Thanh', 'Ngọc', 'Xuân', 'Hữu', 'Đức', 'Quang', 'Hải');
        $given_names = array('An', 'Bình', 'Châu', 'Duy', 'Giang', 'Hà', 'Khoa', 'Linh', 'Mỹ', 'Nam', 'Oanh', 'Phúc', 'Quân', 'Sơn', 'Tài', 'Trang', 'Uyên', 'Việt', 'Vy', 'Yến');
        // Nhiễu cộng vào điểm mục tiêu; tổng mỗi chu kỳ 40 phiếu = 0 để giữ đúng điểm trung bình.
        $variations = array_merge(
            array(5, -5, 3, -3, 2, -2, 4, -4, 1, -1),
            array(5, -5, 3, -3, 2, -2, 4, -4, 1, -1),
            array(5, -5, 3, -3, 2, -2, 4, -4, 1, -1),
            array(5, -5, 3, -3, 2, -2, 4, -4, 1, -1)
        );

        $wpdb->query('START TRANSACTION');

        // Dọn bộ demo cũ (nếu có) để bấm lại không nhân bản dữ liệu.
        $demo_ids = $wpdb->get_col("SELECT id FROM $voters WHERE email LIKE 'demo.%@" . MAC_Voting_DB::COMPANY_EMAIL_DOMAIN . "'");
        if ($demo_ids) {
            $in = implode(',', array_map('intval', $demo_ids));
            $wpdb->query("DELETE FROM $ballots WHERE voter_id IN ($in)");
            $wpdb->query("DELETE FROM $grants WHERE voter_id IN ($in)");
            $wpdb->query("DELETE FROM $checkins WHERE voter_id IN ($in)");
            $wpdb->query("DELETE FROM $exemptions WHERE voter_id IN ($in)");
            $wpdb->query("DELETE FROM $voters WHERE id IN ($in)");
        }
        // Thay toàn bộ điểm chấm manual bằng bộ demo để màn hình tổng khớp kịch bản.
        $wpdb->query("DELETE FROM $points WHERE source_type='CHECKIN' AND source_id LIKE 'CHECKPOINT\\_%'");
        $wpdb->query($wpdb->prepare("DELETE FROM $points WHERE source_type=%s", MAC_Games::SOURCE));
        $wpdb->query($wpdb->prepare("DELETE FROM $points WHERE source_type IN (%s,%s)", MAC_Points::SOURCE, 'CATEGORY'));

        // 1) Nhân sự demo: 8 người/team, email demo.* để nhận diện và dọn khi cần.
        $demo_voters = array();
        $index = 0;
        foreach ($teams as $team) {
            $team_no = (int) $team['team_no'];
            $team_id = (int) $team['id'];
            for ($member = 1; $member <= 8; $member++) {
                $full_name = $family_names[($index * 7) % 10] . ' '
                    . $middle_names[(int) (($index * 3 + floor($index / 10)) % 10)] . ' '
                    . $given_names[($index * 11 + 5) % 20];
                $email = 'demo.' . $team_no . '.' . $member . '@' . MAC_Voting_DB::COMPANY_EMAIL_DOMAIN;
                $inserted = $wpdb->insert($voters, array(
                    'full_name' => $full_name,
                    'search_name' => MAC_Voting_DB::normalize_name($full_name),
                    'employee_code' => 'DEMO-' . $team_no . '-' . $member,
                    'email' => $email,
                    'team_id' => $team_id,
                    'status' => 'ACTIVE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ));
                if ($inserted === false) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('seed_failed', 'Không ghi được nhân sự demo vào database.');
                }
                $demo_voters[] = array('id' => (int) $wpdb->insert_id, 'team_no' => $team_no);
                $index++;
            }
        }

        // 2) Phiếu demo: mỗi người chấm 5 tiết mục của đội khác, tổng điểm bám mục tiêu.
        $counters = array();
        foreach ($demo_voters as $voter) {
            foreach ($schedule as $slot) {
                $performer_no = (int) $slot['team_no'];
                if ($performer_no === (int) $voter['team_no']) {
                    continue;
                }
                $counter = isset($counters[$performer_no]) ? $counters[$performer_no] : 0;
                $counters[$performer_no] = $counter + 1;
                $total = max(0, min(150, $vote_targets[$performer_no] + $variations[$counter % 40]));
                $style = min(50, (int) floor($total * 0.36));
                $staging = min(50, (int) floor($total * 0.34));
                $teamwork = max(0, $total - $style - $staging);
                $inserted = $wpdb->insert($ballots, array(
                    'request_id' => wp_generate_uuid4(),
                    'voter_id' => $voter['id'],
                    'performance_id' => (int) $slot['performance_id'],
                    'round_id' => (int) $slot['round_id'],
                    'style_score' => $style,
                    'staging_score' => $staging,
                    'teamwork_score' => $teamwork,
                    'total_score' => $total,
                    'status' => 'VALID',
                    'active_key' => 'VALID',
                    'created_at' => $now,
                ));
                if ($inserted === false) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('seed_failed', 'Không ghi được phiếu demo vào database.');
                }
            }
        }

        // 3) Sổ điểm team_points: check-in, trò chơi và thi đua theo kịch bản.
        $admin_id = get_current_user_id();
        $point_rows = array();
        foreach ($checkin_matrix as $team_no => $checkpoint_scores) {
            foreach ($checkpoint_scores as $position => $score) {
                $point_rows[] = array($team_id_by_no[$team_no], 'CHECKIN', 'CHECKPOINT_' . ($position + 1), $score, 'Trạm ' . ($position + 1) . ' · demo');
            }
        }
        $games = MAC_Games::games();
        foreach ($games as $game) {
            $ranks = $game_ranks[(int) $game['id']] ?? array();
            foreach ($ranks as $team_no => $rank) {
                $game_points = $rank >= 1 ? (int) MAC_Voting_DB::RANK_LADDER[$rank - 1] : 0;
                if ($game_points <= 0) {
                    continue;
                }
                $point_rows[] = array($team_id_by_no[$team_no], MAC_Games::SOURCE, MAC_Games::SOURCE . '_' . (int) $game['id'], $game_points, $game['name'] . ' · Hạng ' . $rank);
            }
        }
        $rounds = $wpdb->get_results("SELECT id,name FROM $thidua_rounds ORDER BY sort_order,id", ARRAY_A) ?: array();
        foreach ($rounds as $position => $round) {
            $ranks = $thidua_ranks[$position] ?? array();
            foreach ($ranks as $team_no => $rank) {
                $round_points = $rank >= 1 ? (int) MAC_Voting_DB::RANK_LADDER[$rank - 1] : 0;
                if ($round_points <= 0) {
                    continue;
                }
                $point_rows[] = array($team_id_by_no[$team_no], MAC_Points::SOURCE, (string) (int) $round['id'], $round_points, $round['name']);
            }
        }
        foreach ($point_rows as $row) {
            $inserted = $wpdb->insert($points, array(
                'team_id' => $row[0],
                'source_type' => $row[1],
                'source_id' => $row[2],
                'points' => $row[3],
                'note' => $row[4],
                'created_by' => $admin_id,
                'created_at' => $now,
                'updated_at' => $now,
            ));
            if ($inserted === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('seed_failed', 'Không ghi được điểm demo vào database.');
            }
        }

        $wpdb->query('COMMIT');
        return true;
    }

    public static function ajax_round(): void {
        self::guard();
        global $wpdb;
        $round_id = absint($_POST['roundId'] ?? 0);
        $operation = sanitize_key($_POST['operation'] ?? '');
        $minutes = MAC_Voting_DB::duration_minutes(absint($_POST['durationMinutes'] ?? 0), MAC_Voting_DB::DEFAULT_VOTE_DURATION_MINUTES);
        $rounds = MAC_Voting_DB::table('rounds');
        $round = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rounds WHERE id=%d", $round_id), ARRAY_A);
        if (!$round) wp_send_json_error(array('message' => 'Lượt không tồn tại.'), 404);
        if (($operation === 'open' || $operation === 'reopen') && !MAC_Voting_DB::is_voting_enabled()) {
            wp_send_json_error(array(
                'message' => 'Cổng văn nghệ đang tắt. Hãy bật cổng văn nghệ trước rồi mới mở vote.',
                'code' => 'gate_off',
            ), 409);
        }
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
            $wpdb->update($rounds, array('status' => 'OPEN', 'opened_at' => MAC_Voting_DB::utc_now(), 'closes_at' => MAC_Voting_DB::deadline_from_minutes($minutes), 'closed_at' => null), array('id' => $round_id), array('%s','%s','%s','%s'), array('%d'));
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
            $updated = $wpdb->update($rounds, array('status' => 'OPEN', 'opened_at' => MAC_Voting_DB::utc_now(), 'closes_at' => MAC_Voting_DB::deadline_from_minutes($minutes), 'closed_at' => null), array('id' => $round_id), array('%s','%s','%s','%s'), array('%d'));
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

    public static function ajax_toggle_scores(): void {
        self::guard();
        $hidden = !empty($_POST['hidden']);
        MAC_Voting_DB::set_scores_hidden($hidden);
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'RESULTS_TOTAL_SCORES_' . ($hidden ? 'HIDDEN' : 'SHOWN'), 'reveal', null, array());
        wp_send_json_success(array(
            'message' => $hidden ? 'Đã ẩn điểm trên màn hình trình chiếu.' : 'Đã hiện điểm trên màn hình trình chiếu.',
            'overview' => self::overview(),
        ));
    }

    public static function ajax_reveal_total(): void {
        self::guard();
        $next = strtoupper(sanitize_text_field(wp_unslash($_POST['stage'] ?? '')));
        $allowed = array('IDLE', 'ROLLING', 'RANK65', 'TEASE43', 'RANK43', 'RANK12', 'TWIST', 'REVEAL3', 'FINAL');
        if (!in_array($next, $allowed, true)) {
            wp_send_json_error(array('message' => 'Trạng thái công bố không hợp lệ.'), 400);
        }
        $current = MAC_Voting_DB::total_reveal_state();
        $transitions = array(
            'IDLE' => 'ROLLING',
            'ROLLING' => 'RANK65',
            'RANK65' => 'TEASE43',
            'TEASE43' => 'RANK43',
            'RANK43' => 'TWIST',
            'TWIST' => 'REVEAL3',
            'REVEAL3' => 'FINAL',
            // RANK12 giữ làm trạng thái legacy (bản cũ): vẫn cho tiến lên TWIST nếu dashboard còn kẹt ở step này.
            'RANK12' => 'TWIST',
        );
        if ($next !== 'IDLE' && ($transitions[$current['stage']] ?? '') !== $next) {
            wp_send_json_error(array('message' => 'Tín hiệu không đúng thứ tự. Hãy tải lại dashboard và thử lại.'), 409);
        }
        $totals = null;
        if ($next === 'ROLLING') {
            $snapshot = array();
            foreach ((MAC_Points::dashboard()['teams'] ?? array()) as $board_row) {
                $snapshot[(int) $board_row['teamId']] = (int) $board_row['total'];
            }
            if (count($snapshot) < 6) {
                wp_send_json_error(array('message' => 'Cần đủ 6 đội trong bảng tổng điểm trước khi công bố.'), 409);
            }
            $totals = $snapshot;
        }
        $state = MAC_Voting_DB::set_total_reveal_state($next, $totals);
        $messages = array(
            'IDLE' => 'Đã đưa màn hình tổng kết về trạng thái chờ.',
            'ROLLING' => 'Màn hình tổng kết đang tung điểm nhẹ nhàng cho 6 đội.',
            'RANK65' => 'Đã lộ diện hạng 6 và hạng 5 (80%) · badge khuyến khích.',
            'TEASE43' => 'Đang nhấp nháy nhá hàng top 4 — nhấn lần nữa để lộ diện.',
            'RANK43' => 'Đã lộ diện hạng 4-5-6 cùng mốc 80% · badge khuyến khích.',
            'RANK12' => 'Hai đội dẫn đầu đã bước lên cùng mốc 6 ô.',
            'TWIST' => 'Ba đội dẫn đầu đang cùng tung điểm bám đuổi.',
            'REVEAL3' => 'Đã lộ diện hạng ba · hai đội còn lại tiếp tục tung điểm.',
            'FINAL' => 'Đã công bố quán quân Company Trip.',
        );
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'RESULTS_TOTAL_REVEAL_' . $next, 'reveal', (string) $state['revision'], array(
            'previousStage' => $current['stage'],
            'stage' => $next,
        ));
        wp_send_json_success(array('message' => $messages[$next], 'overview' => self::overview()));
    }

    // Phân tích trùng điểm trên snapshot tổng kết để cảnh báo MC trước khi bấm kịch bản.
    public static function total_tie_warnings(): array {
        $state = MAC_Voting_DB::total_reveal_state();
        if ($state['stage'] === 'IDLE' || !count($state['totals'])) {
            return array();
        }
        $totals = $state['totals'];
        arsort($totals);
        $values = array_values($totals);
        $count = count($values);
        $ranks = array();
        $rank = 0;
        $previous = null;
        foreach ($values as $index => $total) {
            if ($previous === null || (int) $previous !== (int) $total) {
                $rank = $index + 1;
            }
            $ranks[] = $rank;
            $previous = $total;
        }
        $warnings = array();
        $candidate_count = count(array_filter($ranks, static fn($r): bool => $r <= 3));
        if ($candidate_count > 3) {
            $warnings[] = 'Trùng điểm khiến nhóm tranh cúp có ' . $candidate_count . ' đội — cú twist sẽ có ' . $candidate_count . ' cột cùng tung điểm.';
        }
        $threshold_step1 = (int) $values[max(0, $count - 2)];
        $threshold_step2 = (int) $values[max(0, $count - 3)];
        $revealed_step1 = 0;
        $revealed_step2 = 0;
        foreach ($values as $index => $total) {
            if ((int) $total <= $threshold_step1 && $ranks[$index] >= 3) {
                $revealed_step1 += 1;
            }
            if ((int) $total <= $threshold_step2 && $ranks[$index] >= 4) {
                $revealed_step2 += 1;
            }
        }
        if ($revealed_step2 <= $revealed_step1) {
            $warnings[] = 'Do trùng điểm, bước 02 (hạng 4-5-6) có thể không lộ thêm đội nào — cứ bấm tiếp để vào cú twist.';
        }
        return $warnings;
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
                'full_name' => MAC_Voting_DB::title_case($name), 'search_name' => MAC_Voting_DB::normalize_name($name),
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
        // Tự đóng lượt vote / trạm check-in đã hết hạn để admin và máy quét luôn đồng bộ.
        MAC_Voting_DB::expire_open_round();
        MAC_Checkin::expire_active_checkpoint();
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
        foreach ($recent as &$recent_row) {
            $recent_row['created_at'] = MAC_Voting_DB::hanoi_time($recent_row['created_at'], 'd/m/Y H:i');
        }
        unset($recent_row);
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
            'totalReveal' => MAC_Voting_DB::total_reveal_state(),
            'totalScoresHidden' => MAC_Voting_DB::scores_hidden(),
            'totalTieWarnings' => self::total_tie_warnings(),
            'votingEnabled' => MAC_Voting_DB::is_voting_enabled(),
            'checkpoints' => MAC_Checkin::checkpoints(),
            'checkinBoard' => self::checkin_overview_board(),
            'exemptions' => self::exemptions_board(),
            'games' => array(
                'list' => MAC_Games::games(),
                'board' => MAC_Games::board(),
            ),
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
            $row['full_name'] = MAC_Voting_DB::title_case((string) $row['full_name']);
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

    private static function exemptions_board(): array {
        $board = array();
        foreach (MAC_Checkin::checkpoints() as $checkpoint) {
            $board[(int) $checkpoint['id']] = MAC_Checkin::exemptions((int) $checkpoint['id']);
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
        $minutes = MAC_Voting_DB::duration_minutes(absint($_POST['durationMinutes'] ?? 0), MAC_Voting_DB::DEFAULT_CHECKIN_DURATION_MINUTES);
        if ($operation === 'open') {
            $result = MAC_Checkin::open_checkpoint($checkpoint_id, $minutes);
        } elseif ($operation === 'reopen') {
            $result = MAC_Checkin::reopen_checkpoint($checkpoint_id, $minutes);
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

    public static function ajax_person(): void {
        self::guard();
        $operation = sanitize_key(wp_unslash($_POST['operation'] ?? ''));
        if ($operation === 'grant') {
            self::grant_person_role();
            return;
        }
        if ($operation !== 'add') {
            wp_send_json_error(array('message' => 'Thao tác không hợp lệ.'), 400);
        }
        global $wpdb;
        $voters = MAC_Voting_DB::table('voters');
        $teams = MAC_Voting_DB::table('teams');
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $team_id = absint($_POST['teamId'] ?? 0);
        $employee = strtoupper(sanitize_text_field(wp_unslash($_POST['employee'] ?? '')));
        $email_raw = mb_strtolower(trim((string) wp_unslash($_POST['email'] ?? '')), 'UTF-8');
        $role = sanitize_key(wp_unslash($_POST['role'] ?? ''));
        $password = trim((string) wp_unslash($_POST['password'] ?? ''));
        if (mb_strlen($name, 'UTF-8') < 2) wp_send_json_error(array('message' => 'Họ tên phải có ít nhất 2 ký tự.'), 400);
        $team = $wpdb->get_row($wpdb->prepare("SELECT id,team_no,name FROM $teams WHERE id=%d", $team_id), ARRAY_A);
        if (!$team) wp_send_json_error(array('message' => 'Team không hợp lệ.'), 400);
        $email = '';
        if ($email_raw !== '') {
            // Chấp nhận mọi domain email (cho agency); chỉ cần đúng định dạng.
            $email = sanitize_email($email_raw);
            if (!is_email($email)) wp_send_json_error(array('message' => 'Email không hợp lệ. Có thể để trống email.'), 400);
            if ((int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $voters WHERE email=%s", $email))) {
                wp_send_json_error(array('message' => 'Email này đã thuộc một nhân sự khác trong hệ thống.'), 409);
            }
        }
        if ($employee !== '' && (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $voters WHERE employee_code=%s", $employee))) {
            wp_send_json_error(array('message' => 'Mã NV này đã tồn tại.'), 409);
        }
        $now = MAC_Voting_DB::utc_now();
        $inserted = $wpdb->insert($voters, array(
            'full_name' => MAC_Voting_DB::title_case($name),
            'search_name' => MAC_Voting_DB::normalize_name($name),
            'employee_code' => $employee !== '' ? $employee : null,
            'email' => $email !== '' ? $email : null,
            'team_id' => $team_id,
            'phone_last4_hash' => '',
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ));
        if ($inserted === false) wp_send_json_error(array('message' => 'Không lưu được nhân sự: ' . ($wpdb->last_error ?: 'lỗi database')), 500);
        $voter_id = (int) $wpdb->insert_id;
        $account = null;
        if ($role === 'btc' || $role === 'super') {
            $account = self::create_dashboard_account($name, $email, $password, $role);
            if (is_wp_error($account)) {
                wp_send_json_error(array('message' => 'Đã thêm nhân sự nhưng chưa tạo được tài khoản: ' . $account->get_error_message()), 500);
            }
        }
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'PERSON_ADDED', 'voter', (string) $voter_id, array('role' => $role !== '' ? $role : 'none'));
        $response = array(
            'message' => $account ? ('Đã thêm ' . $account['name'] . ' và tạo tài khoản ' . ($role === 'super' ? 'Super admin' : 'BTC') . '.') : 'Đã thêm nhân sự.',
            'overview' => self::overview(),
        );
        if ($account) {
            $response['account'] = $account;
        }
        wp_send_json_success($response);
    }

    private static function create_dashboard_account(string $name, string $email, string $password, string $kind) {
        if ($email !== '' && get_user_by('email', $email)) {
            return new WP_Error('email_used', 'Email này đã là tài khoản WordPress sẵn. Hãy gán team cho họ ở khối Tài khoản Quét QR check-in trong tab Check-in.');
        }
        $display = MAC_Voting_DB::title_case($name);
        $pass = $password !== '' ? $password : MAC_Voting_DB::DEFAULT_STAFF_PASSWORD;
        if ($email !== '') {
            $base = (string) strstr($email, '@', true);
        } else {
            $ascii = mb_strtolower(remove_accents($name), 'UTF-8');
            $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
        }
        $login = sanitize_user($base, true);
        if ($login === '') {
            $login = 'nguoi-dung';
        }
        $candidate = $login;
        $suffix = 1;
        while (username_exists($candidate)) {
            $suffix++;
            $candidate = $login . $suffix;
        }
        $user_id = wp_insert_user(array(
            'user_login' => $candidate,
            'user_email' => $email,
            'user_pass' => $pass,
            'display_name' => $display,
            'role' => $kind === 'super' ? MAC_Checkin::SUPER_ROLE : MAC_Checkin::ROLE,
        ));
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        if ($kind === 'btc') {
            update_user_meta((int) $user_id, MAC_Checkin::TEAM_META, MAC_Voting_DB::competing_team_ids());
        }
        return array(
            'login' => $candidate,
            'password' => $pass,
            'name' => $display,
            'email' => $email,
            'kind' => $kind,
        );
    }

    /**
     * Gán vai trò BTC / Super admin cho một nhân sự đã có trong danh sách (tạo tài khoản WordPress nếu chưa có).
     */
    private static function grant_person_role(): void {
        $voter_id = absint($_POST['voterId'] ?? 0);
        $role = sanitize_key(wp_unslash($_POST['role'] ?? ''));
        $password = trim((string) wp_unslash($_POST['password'] ?? ''));
        if (!in_array($role, array('btc', 'super'), true)) {
            wp_send_json_error(array('message' => 'Vai trò không hợp lệ.'), 400);
        }
        global $wpdb;
        $voters = MAC_Voting_DB::table('voters');
        $voter = $wpdb->get_row($wpdb->prepare("SELECT id,full_name,email FROM $voters WHERE id=%d", $voter_id), ARRAY_A);
        if (!$voter) {
            wp_send_json_error(array('message' => 'Nhân sự không tồn tại.'), 404);
        }
        $email = mb_strtolower(trim((string) $voter['email']), 'UTF-8');
        if ($email === '' || !is_email($email)) {
            wp_send_json_error(array('message' => 'Nhân sự này chưa có email nên không tạo được tài khoản.'), 400);
        }
        $kind_label = $role === 'super' ? 'Super admin' : 'BTC';
        $user = get_user_by('email', $email);
        if ($user) {
            $pass = $password !== '' ? $password : MAC_Voting_DB::DEFAULT_STAFF_PASSWORD;
            wp_set_password($pass, (int) $user->ID);
            self::apply_dashboard_role((int) $user->ID, $role);
            $account = array(
                'login' => $user->user_login,
                'password' => $pass,
                'name' => $user->display_name,
                'email' => $email,
                'kind' => $role,
            );
        } else {
            $account = self::create_dashboard_account((string) $voter['full_name'], $email, $password, $role);
            if (is_wp_error($account)) {
                wp_send_json_error(array('message' => $account->get_error_message()), 500);
            }
        }
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'PERSON_GRANTED_ROLE', 'voter', (string) $voter_id, array('role' => $role));
        wp_send_json_success(array(
            'message' => 'Đã cấp quyền ' . $kind_label . ' cho ' . $account['name'] . '.',
            'overview' => self::overview(),
            'account' => $account,
        ));
    }

    /**
     * Thêm vai trò dashboard (giữ nguyên các vai trò WordPress khác như administrator).
     */
    private static function apply_dashboard_role(int $user_id, string $kind): void {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        $user->add_role($kind === 'super' ? MAC_Checkin::SUPER_ROLE : MAC_Checkin::ROLE);
        $user->remove_role($kind === 'super' ? MAC_Checkin::ROLE : MAC_Checkin::SUPER_ROLE);
        if ($kind === 'btc') {
            update_user_meta($user_id, MAC_Checkin::TEAM_META, MAC_Voting_DB::competing_team_ids());
        } else {
            delete_user_meta($user_id, MAC_Checkin::TEAM_META);
        }
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
            'add' => 'Đã thêm lần thi đua.',
            'rename' => 'Đã đổi tên lần thi đua.',
            'delete' => 'Đã xóa lần thi đua.',
            'award' => 'Đã cập nhật điểm team.',
        );
        wp_send_json_success(array(
            'message' => $messages[$operation] ?? 'Đã cập nhật.',
            'overview' => self::overview(),
            'categoryId' => $operation === 'add' ? (int) $result : 0,
        ));
    }

    public static function ajax_games(): void {
        self::guard();
        $operation = sanitize_key($_POST['operation'] ?? 'rank');
        if ($operation !== 'rank') {
            wp_send_json_error(array('message' => 'Thao tác không hợp lệ.'), 400);
            return;
        }
        $result = MAC_Games::set_rank(
            absint($_POST['gameId'] ?? 0),
            absint($_POST['teamId'] ?? 0),
            intval($_POST['rank'] ?? 0)
        );
        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            wp_send_json_error(array('message' => $result->get_error_message()), is_array($error_data) ? (int) ($error_data['status'] ?? 400) : 400);
        }
        wp_send_json_success(array(
            'message' => 'Đã cập nhật xếp hạng trò chơi.',
            'overview' => self::overview(),
        ));
    }

    public static function ajax_exemption(): void {
        self::guard();
        $operation = sanitize_key($_POST['operation'] ?? '');
        if (!in_array($operation, array('set', 'clear'), true)) {
            wp_send_json_error(array('message' => 'Thao tác không hợp lệ.'), 400);
            return;
        }
        $result = MAC_Checkin::set_exemption(
            absint($_POST['checkpointId'] ?? 0),
            absint($_POST['voterId'] ?? 0),
            $operation === 'set',
            sanitize_text_field(wp_unslash($_POST['reason'] ?? ''))
        );
        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            wp_send_json_error(array('message' => $result->get_error_message()), is_array($error_data) ? (int) ($error_data['status'] ?? 400) : 400);
        }
        wp_send_json_success(array(
            'message' => $operation === 'set' ? 'Đã miễn check-in.' : 'Đã bỏ miễn check-in.',
            'overview' => self::overview(),
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
            fputcsv($out, array($row['checkpoint_name'], '#' . $row['team_no'] . ' ' . $row['team_name'], $row['full_name'], $row['email'], $row['checkin_status'], MAC_Voting_DB::hanoi_time($row['scanned_at'], 'd/m/Y H:i'), $scanned_by));
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
