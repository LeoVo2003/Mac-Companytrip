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
        add_action('wp_ajax_mac_vote_reset_checkin', array(__CLASS__, 'ajax_reset_checkin'));
        add_action('wp_ajax_mac_vote_reset_art', array(__CLASS__, 'ajax_reset_art'));
        add_action('wp_ajax_mac_vote_reset_games', array(__CLASS__, 'ajax_reset_games'));
        add_action('wp_ajax_mac_vote_reset_thidua', array(__CLASS__, 'ajax_reset_thidua'));
        add_action('wp_ajax_mac_vote_round', array(__CLASS__, 'ajax_round'));
        add_action('wp_ajax_mac_vote_reveal', array(__CLASS__, 'ajax_reveal'));
        add_action('wp_ajax_mac_vote_reveal_total', array(__CLASS__, 'ajax_reveal_total'));
        add_action('wp_ajax_mac_vote_toggle_scores', array(__CLASS__, 'ajax_toggle_scores'));
        add_action('wp_ajax_mac_vote_toggle_art_theme', array(__CLASS__, 'ajax_toggle_art_theme'));
        add_action('wp_ajax_mac_vote_bus_open', array(__CLASS__, 'ajax_bus_open'));
        add_action('wp_ajax_mac_vote_bus_close', array(__CLASS__, 'ajax_bus_close'));
        add_action('wp_ajax_mac_vote_bus_capacity', array(__CLASS__, 'ajax_bus_capacity'));
        add_action('wp_ajax_mac_vote_bus_move', array(__CLASS__, 'ajax_bus_move'));
        add_action('wp_ajax_mac_vote_bus_assign', array(__CLASS__, 'ajax_bus_assign'));
        add_action('wp_ajax_mac_vote_bus_add_manual', array(__CLASS__, 'ajax_bus_add_manual'));
        add_action('wp_ajax_mac_vote_bus_remove', array(__CLASS__, 'ajax_bus_remove'));
        add_action('wp_ajax_mac_vote_bus_reset', array(__CLASS__, 'ajax_bus_reset'));
        add_action('wp_ajax_mac_vote_guide_save', array(__CLASS__, 'ajax_guide_save'));
        add_action('wp_ajax_mac_vote_rollcall', array(__CLASS__, 'ajax_rollcall'));
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
        add_action('admin_post_mac_vote_export', array(__CLASS__, 'export_results_xlsx'));
        add_action('admin_post_mac_vote_template', array(__CLASS__, 'template_xlsx'));
        add_action('admin_post_mac_vote_export_checkin', array(__CLASS__, 'export_checkin_xlsx'));
        add_action('admin_post_mac_vote_export_bus', array(__CLASS__, 'export_bus_xlsx'));
        add_action('admin_post_mac_vote_export_all_buses', array(__CLASS__, 'export_all_buses_xlsx'));
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
            'role'     => $is_super ? 'super' : (MAC_Bus::is_guide() ? 'guide' : 'admin'),
            'busStaff' => current_user_can(MAC_Checkin::CAP),
            'voteUrl'  => MAC_Voting_DB::vote_page_url(),
            'resultsUrl' => MAC_Voting_DB::results_page_url(),
            'artResultsUrl' => MAC_Voting_DB::art_results_page_url(),
            'checkinUrl' => MAC_Voting_DB::checkin_page_url(),
            'adminUrl' => MAC_Voting_DB::admin_page_url(),
            'logoutUrl'=> wp_logout_url(MAC_Voting_DB::admin_page_url()),
            'logo'     => MAC_VOTING_URL . 'assets/mac-marketing-logo.png',
            'exportUrl'=> wp_nonce_url(admin_url('admin-post.php?action=mac_vote_export'), 'mac_vote_export'),
            'checkinExportUrl'=> wp_nonce_url(admin_url('admin-post.php?action=mac_vote_export_checkin'), 'mac_vote_export_checkin'),
            'templateUrl'=> wp_nonce_url(admin_url('admin-post.php?action=mac_vote_template'), 'mac_vote_template'),
            'busExportUrl'=> wp_nonce_url(admin_url('admin-post.php?action=mac_vote_export_bus'), 'mac_vote_export_bus'),
            'allBusesExportUrl'=> wp_nonce_url(admin_url('admin-post.php?action=mac_vote_export_all_buses'), 'mac_vote_export_all_buses'),
            'permalinkWarning' => get_option('permalink_structure') === '',
            'permalinkSettingsUrl' => admin_url('options-permalink.php'),
        );
    }

    private static function guard(string $level = 'super'): void {
        check_ajax_referer('mac_voting_admin', 'nonce');
        if ($level === 'staff' && self::can_access_dashboard()) {
            return;
        }
        // operator: Super Admin + BTC/Hoa tiêu (không gồm HDV) — chấm thi đua, xếp game.
        if ($level === 'operator' && self::can_access_dashboard() && !MAC_Bus::is_guide()) {
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
        $wpdb->query('START TRANSACTION');
        $art = self::reset_art_data();
        $checkin = MAC_Checkin::reset_checkin_data();
        $games = MAC_Games::reset_ranks();
        $thidua = MAC_Points::reset_awards();
        if (is_wp_error($art) || !$checkin || !$games || !$thidua) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => 'Không thể đặt lại sự kiện. Dữ liệu chưa bị thay đổi.'), 500);
        }
        MAC_Points::reset_history();
        $wpdb->query('COMMIT');
        self::reset_total_presentation();
        MAC_Bus::reset_assignment();

        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'EVENT_RESET', 'event', null, array(
            'deletedBallots' => (int) $art['deletedBallots'],
            'deletedRevoteGrants' => (int) $art['deletedRevoteGrants'],
            'resetRounds' => (int) $art['resetRounds'],
            'keptVoters' => true,
            'keptSchedule' => true,
            'keptQr' => true,
        ));
        wp_send_json_success(array(
            'message' => 'Đã đặt lại sự kiện. Phiếu, check-in, điểm hạng mục và lịch sử cộng điểm đã về trạng thái ban đầu.',
            'overview' => self::overview(),
        ));
    }

    /** Reset duy nhất cho module Văn nghệ; luôn giữ nhân sự, team và lịch biểu diễn. */
    private static function reset_art_data() {
        global $wpdb;
        $rounds = MAC_Voting_DB::table('rounds');
        $ballots = MAC_Voting_DB::table('ballots');
        $grants = MAC_Voting_DB::table('revote_grants');
        $reset_rounds = $wpdb->query("UPDATE $rounds SET status='DRAFT', opened_at=NULL, closes_at=NULL, closed_at=NULL");
        $deleted_grants = $wpdb->query("DELETE FROM $grants");
        $deleted_ballots = $wpdb->query("DELETE FROM $ballots");
        if (in_array(false, array($reset_rounds, $deleted_grants, $deleted_ballots), true)) {
            return new WP_Error('art_reset_failed', 'Không thể đặt lại dữ liệu Văn nghệ.');
        }
        MAC_Voting_DB::set_voting_enabled(false);
        MAC_Voting_DB::set_reveal_state('IDLE');
        return array(
            'resetRounds' => (int) $reset_rounds,
            'deletedBallots' => (int) $deleted_ballots,
            'deletedRevoteGrants' => (int) $deleted_grants,
        );
    }

    /** Mọi thay đổi dữ liệu điểm đều phải đưa màn tổng kết về trạng thái chờ, tránh hiện snapshot cũ. */
    private static function reset_total_presentation(): void {
        MAC_Voting_DB::set_total_reveal_state('IDLE');
        MAC_Voting_DB::set_scores_hidden(false);
    }

    public static function ajax_reset_checkin(): void {
        self::guard();
        if (!MAC_Checkin::reset_checkin_data()) {
            wp_send_json_error(array('message' => 'Không thể đặt lại Check-in.'), 500);
        }
        self::reset_total_presentation();
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'CHECKIN_RESET', 'checkin', null, array());
        wp_send_json_success(array('message' => 'Đã đặt lại Check-in. Trạm, lượt quét, miễn check-in và điểm Check-in đã được xóa.', 'overview' => self::overview()));
    }

    public static function ajax_reset_art(): void {
        self::guard();
        $result = self::reset_art_data();
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }
        self::reset_total_presentation();
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'ART_RESET', 'art', null, $result);
        wp_send_json_success(array('message' => 'Đã đặt lại Văn nghệ. Phiếu và quyền vote lại đã được xóa; lịch biểu diễn vẫn giữ nguyên.', 'overview' => self::overview()));
    }

    public static function ajax_reset_games(): void {
        self::guard();
        if (!MAC_Games::reset_ranks()) {
            wp_send_json_error(array('message' => 'Không thể đặt lại điểm Trò chơi lớn.'), 500);
        }
        self::reset_total_presentation();
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'GAMES_RESET', 'games', null, array());
        wp_send_json_success(array('message' => 'Đã đặt lại điểm Trò chơi lớn. Ba game vẫn được giữ nguyên.', 'overview' => self::overview()));
    }

    public static function ajax_reset_thidua(): void {
        self::guard();
        if (!MAC_Points::reset_awards()) {
            wp_send_json_error(array('message' => 'Không thể đặt lại điểm Thi đua.'), 500);
        }
        self::reset_total_presentation();
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'THIDUA_RESET', 'thidua', null, array());
        wp_send_json_success(array('message' => 'Đã đặt lại điểm Thi đua. Các hạng mục vẫn được giữ nguyên.', 'overview' => self::overview()));
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
        // Hạng 2 vòng thi đua mặc định (đủ cả 6 team, hạng 6 = 0đ explicit để hạng mục hoàn tất).
        $thidua_ranks = array(
            array(3 => 1, 5 => 2, 1 => 3, 2 => 4, 6 => 5, 4 => 6),
            array(5 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5, 6 => 6),
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

    /**
     * Lập kế hoạch công bố theo các hạng thực sự tồn tại. Competition ranking có
     * thể bỏ hạng khi đồng điểm (1,1,3...), nên không được ép MC đi qua slot rỗng.
     */
    private static function art_reveal_plan(array $results, ?string $current_stage = null): array {
        $stage_by_rank = array(6 => 'SIXTH', 5 => 'FIFTH', 4 => 'FOURTH', 3 => 'THIRD', 2 => 'SECOND', 1 => 'FINAL');
        $rank_counts = array();
        $rank = 0;
        $previous_score = null;
        foreach ($results as $index => $row) {
            if ($row['average_score'] === null) continue;
            $score = round((float) $row['average_score'], 6);
            if ($previous_score === null || abs($previous_score - $score) > 0.000001) {
                $rank = $index + 1;
            }
            $rank_counts[$rank] = ($rank_counts[$rank] ?? 0) + 1;
            $previous_score = $score;
        }
        $current_stage = $current_stage ?: MAC_Voting_DB::reveal_state()['stage'];
        $rank_by_stage = array_flip($stage_by_rank);
        $next_stage = null;
        if ($current_stage === 'IDLE') {
            $next_stage = 'ROLLING';
        } elseif ($current_stage === 'ROLLING') {
            $available = array_keys($rank_counts);
            if ($available) $next_stage = $stage_by_rank[max($available)] ?? null;
        } elseif (isset($rank_by_stage[$current_stage])) {
            $current_rank = (int) $rank_by_stage[$current_stage];
            $available = array_values(array_filter(array_keys($rank_counts), static fn($candidate): bool => (int) $candidate < $current_rank));
            if ($available) $next_stage = $stage_by_rank[max($available)] ?? null;
        }
        return array(
            'nextStage' => $next_stage,
            'rankCounts' => $rank_counts,
        );
    }

    public static function ajax_reveal(): void {
        self::guard();
        $next = strtoupper(sanitize_text_field(wp_unslash($_POST['stage'] ?? '')));
        $allowed = array('IDLE', 'ROLLING', 'SIXTH', 'FIFTH', 'FOURTH', 'THIRD', 'SECOND', 'FINAL');
        if (!in_array($next, $allowed, true)) {
            wp_send_json_error(array('message' => 'Trạng thái công bố không hợp lệ.'), 400);
        }
        $current = MAC_Voting_DB::reveal_state();
        global $wpdb;
        $performances = MAC_Voting_DB::table('performances');
        $teams = MAC_Voting_DB::table('teams');
        $slots = MAC_Voting_DB::table('slots');
        $ballots = MAC_Voting_DB::table('ballots');
        $rank_results = $wpdb->get_results("SELECT p.id AS performance_id,t.id AS team_id,t.team_no,t.name AS team_name,AVG(b.total_score) AS average_score
            FROM $performances p JOIN $slots s ON s.performance_id=p.id JOIN $teams t ON t.id=p.team_id
            LEFT JOIN $ballots b ON b.performance_id=p.id AND b.status='VALID'
            GROUP BY p.id,t.id ORDER BY average_score DESC,t.team_no", ARRAY_A) ?: array();
        $plan = self::art_reveal_plan($rank_results, $current['stage']);
        if ($next !== 'IDLE' && $plan['nextStage'] !== $next) {
            wp_send_json_error(array('message' => 'Tín hiệu không đúng thứ tự. Hãy tải lại dashboard và thử lại.'), 409);
        }
        if ($next === 'ROLLING') {
            $rounds = MAC_Voting_DB::table('rounds');
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
            'ROLLING' => 'Spotlight đang tìm kiếm giữa 6 đội.',
            'SIXTH' => 'Đã công bố nhóm xếp hạng sáu.',
            'FIFTH' => 'Đã công bố nhóm xếp hạng năm.',
            'FOURTH' => 'Đã công bố nhóm xếp hạng tư.',
            'THIRD' => 'Đã công bố nhóm xếp hạng ba.',
            'SECOND' => 'Đã công bố nhóm xếp hạng nhì.',
            'FINAL' => 'Đã công bố nhóm quán quân.',
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

    public static function ajax_toggle_art_theme(): void {
        self::guard();
        $light = !empty($_POST['light']);
        MAC_Voting_DB::set_art_light_theme($light);
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'ART_THEME_' . ($light ? 'LIGHT' : 'DARK'), 'reveal', null, array());
        wp_send_json_success(array(
            'message' => $light ? 'Màn văn nghệ chuyển sang tone đại dương.' : 'Màn văn nghệ về tone tối.',
            'overview' => self::overview_payload(),
        ));
    }

    /* ------------------------- Phân xe (Super Admin) ------------------------- */

    public static function ajax_bus_open(): void {
        self::guard();
        $result = MAC_Bus::open_bus(absint($_POST['busId'] ?? 0));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 409);
        }
        wp_send_json_success(array('message' => 'Đã mở xe theo thao tác thủ công.', 'overview' => self::overview_payload()));
    }

    public static function ajax_bus_close(): void {
        self::guard();
        $result = MAC_Bus::close_bus(absint($_POST['busId'] ?? 0));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 409);
        }
        wp_send_json_success(array('message' => 'Đã chốt xe thủ công — xe kế trong hàng chờ sẽ tự mở.', 'overview' => self::overview_payload()));
    }

    public static function ajax_bus_capacity(): void {
        self::guard();
        $result = MAC_Bus::save_capacity(absint($_POST['busId'] ?? 0), absint($_POST['capacity'] ?? 0));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 409);
        }
        wp_send_json_success(array('message' => 'Đã cập nhật sức chứa tối đa của xe.', 'overview' => self::overview_payload()));
    }

    public static function ajax_bus_move(): void {
        self::guard();
        $to_bus = absint($_POST['toBus'] ?? 0);
        $member_id = absint($_POST['memberId'] ?? 0);
        if ($member_id) {
            // Chuyển theo member — áp dụng cho cả người thêm thủ công.
            $result = MAC_Bus::move_member_by_id($member_id, $to_bus);
        } else {
            $result = MAC_Bus::move_voter(absint($_POST['voterId'] ?? 0), $to_bus);
        }
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 409);
        }
        wp_send_json_success(array('message' => 'Đã chuyển xe.', 'overview' => self::overview_payload()));
    }

    public static function ajax_bus_reset(): void {
        self::guard();
        $result = MAC_Bus::reset_assignment();
        wp_send_json_success(array('message' => 'Đã reset đợt phân xe về trạng thái ban đầu.', 'overview' => self::overview_payload()));
    }

    public static function ajax_bus_assign(): void {
        self::guard('staff');
        $voter_id = absint($_POST['voterId'] ?? 0);
        // BTC/Hoa tiêu chỉ được pick người team 7 (kể cả chính mình) vào xe; nhân viên QR do Super Admin điều phối.
        if (!MAC_Checkin::is_super() && !MAC_Bus::voter_is_staff($voter_id)) {
            wp_send_json_error(array('message' => 'BTC chỉ được thêm BTC/Hoa tiêu vào xe. Nhân viên QR do Super Admin điều phối.'), 403);
        }
        $result = MAC_Bus::assign_voter($voter_id, absint($_POST['toBus'] ?? 0));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 409);
        }
        wp_send_json_success(array('message' => 'Đã gán vào xe.', 'overview' => self::overview_payload()));
    }

    public static function ajax_bus_add_manual(): void {
        self::guard();
        $result = MAC_Bus::add_manual_person(absint($_POST['busId'] ?? 0), sanitize_text_field(wp_unslash((string) ($_POST['manualName'] ?? ''))));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }
        wp_send_json_success(array('message' => 'Đã thêm vào xe.', 'overview' => self::overview_payload()));
    }

    public static function ajax_bus_remove(): void {
        self::guard();
        $result = MAC_Bus::remove_member(absint($_POST['memberId'] ?? 0));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 404);
        }
        wp_send_json_success(array('message' => 'Đã xóa khỏi xe.', 'overview' => self::overview_payload()));
    }

    public static function ajax_guide_save(): void {
        self::guard();
        $result = MAC_Bus::save_guide(
            sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? ''))),
            sanitize_text_field(wp_unslash((string) ($_POST['login'] ?? ''))),
            sanitize_text_field(wp_unslash((string) ($_POST['password'] ?? ''))),
            absint($_POST['busId'] ?? 0)
        );
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }
        wp_send_json_success(array(
            'message' => ($result['created'] ? 'Đã tạo tài khoản HDV.' : 'Đã cập nhật tài khoản HDV.') . ' Xe ' . (int) $result['busId'] . ' · đăng nhập ' . $result['login'] . ' · mật khẩu ' . $result['password'],
            'overview' => self::overview_payload(),
        ));
    }

    /* ------------------------- Điểm danh trên xe (HDV) ------------------------- */

    public static function ajax_rollcall(): void {
        check_ajax_referer('mac_voting_admin', 'nonce');
        $bus_id = absint($_POST['busId'] ?? 0);
        if (!MAC_Bus::can_rollcall($bus_id)) {
            wp_send_json_error(array('message' => 'Bạn không có quyền điểm danh xe này.'), 403);
        }
        $operation = sanitize_key((string) ($_POST['operation'] ?? 'state'));
        if ($operation === 'new_round' && !MAC_Checkin::is_super() && !current_user_can(MAC_Bus::CAP_ROLLCALL)) {
            wp_send_json_error(array('message' => 'Chỉ HDV và Super Admin được tạo lượt điểm danh mới.'), 403);
        }
        if ($operation === 'new_round') {
            $state = MAC_Bus::new_rollcall($bus_id);
            $message = 'Đã tạo lượt điểm danh mới — lịch sử lượt cũ vẫn giữ.';
        } elseif ($operation === 'toggle') {
            $state = MAC_Bus::toggle_mark($bus_id, absint($_POST['memberId'] ?? 0), !empty($_POST['present']));
            $message = 'Đã cập nhật điểm danh.';
        } else {
            $state = MAC_Bus::rollcall_state($bus_id);
            $message = 'Đã tải lại danh sách xe.';
        }
        if (is_wp_error($state)) {
            wp_send_json_error(array('message' => $state->get_error_message()), 403);
        }
        wp_send_json_success(array('message' => $message, 'myBus' => $state, 'overview' => self::overview_payload()));
    }

    public static function ajax_reveal_total(): void {
        self::guard();
        $next = strtoupper(sanitize_text_field(wp_unslash($_POST['stage'] ?? '')));
        $allowed = array('IDLE', 'ROLLING', 'RANK65', 'TEASE43', 'RANK43', 'RANK12', 'TWIST', 'REVEAL3', 'SECOND', 'FINAL');
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
            'REVEAL3' => 'SECOND',
            'SECOND' => 'FINAL',
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
            'SECOND' => 'Đã công bố á quân và quán quân Company Trip.',
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
        if (empty($_FILES['file']['tmp_name'])) wp_send_json_error(array('message' => 'Vui lòng chọn file XLSX.'), 400);
        if ((int) $_FILES['file']['size'] > 5 * MB_IN_BYTES) wp_send_json_error(array('message' => 'File không được lớn hơn 5 MB.'), 400);
        $filename = sanitize_file_name((string) ($_FILES['file']['name'] ?? ''));
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xlsx') wp_send_json_error(array('message' => 'Chỉ chấp nhận file XLSX.'), 400);
        $workbook = MAC_XLSX::read_first_sheet((string) $_FILES['file']['tmp_name']);
        if (is_wp_error($workbook)) wp_send_json_error(array('message' => $workbook->get_error_message()), 400);
        $source_rows = $workbook['rows'] ?? array();
        $aliases = array(
            'name' => array('ho & ten','ho ten','ten','full name'), 'employee' => array('ma nv','ma nhan vien','employee code'),
            'team' => array('team','doi'), 'email' => array('email','mail','email cong ty'), 'status' => array('trang thai','status'),
            'role' => array('vai tro','role','btc','chuc vu'), 'password' => array('mat khau','password','pass'),
            'birth_year' => array('nam sinh','year of birth','birth year'), 'gender' => array('gioi tinh','gender'),
            'citizen_id' => array('cccd','cmnd','can cuoc cong dan'), 'phone' => array('sdt','so dien thoai','dien thoai','phone'),
            'room_type' => array('loai phong','room type'), 'room' => array('phong','so phong','room'),
            'note' => array('note','ghi chu'),
            'bus_rider' => array('di xe','xe','bus','phuong tien'),
        );
        // File thực tế có thể có dòng trống / dòng tiêu đề phụ trước hàng tiêu đề thật:
        // quét 10 dòng đầu, chọn dòng khớp nhiều alias nhất (ưu tiên dòng có HỌ & TÊN).
        $header_row = 0;
        $headers = array();
        $best_score = 0;
        foreach ($source_rows as $candidate_index => $candidate_row) {
            if ((int) $candidate_index > 25) break;
            $normalized_row = array_map(static function($value): string { return MAC_Voting_DB::normalize_name((string) $value); }, $candidate_row);
            $hits = 0;
            foreach ($aliases as $alias_names) { if (array_intersect($alias_names, $normalized_row)) $hits++; }
            $has_name_column = in_array('ho & ten', $normalized_row, true) || in_array('ho ten', $normalized_row, true);
            $score = $hits + ($has_name_column ? 100 : 0);
            if ($hits >= 3 && $score > $best_score) {
                $best_score = $score;
                $header_row = (int) $candidate_index;
                $headers = $normalized_row;
            }
            if ($has_name_column && $hits >= 3) break;
        }
        if (!$header_row) {
            wp_send_json_error(array('message' => 'Không tìm thấy hàng tiêu đề (HỌ & TÊN, TEAM, EMAIL…) trong 25 dòng đầu của sheet đầu tiên.'), 400);
        }
        $header = $source_rows[$header_row];
        $columns = array();
        foreach ($aliases as $key => $names) {
            foreach ($names as $name) {
                $index = array_search($name, $headers, true);
                if ($index !== false) { $columns[$key] = $index; break; }
            }
        }
        $required = array('name' => 'HỌ & TÊN', 'birth_year' => 'NĂM SINH', 'gender' => 'GIỚI TÍNH', 'citizen_id' => 'CCCD', 'phone' => 'SĐT', 'room_type' => 'LOẠI PHÒNG', 'room' => 'PHÒNG', 'team' => 'TEAM', 'email' => 'EMAIL', 'note' => 'NOTE');
        $missing = array();
        foreach ($required as $key => $label) if (!isset($columns[$key])) $missing[] = $label;
        if ($missing) wp_send_json_error(array('message' => 'XLSX thiếu cột: ' . implode(', ', $missing) . '.'), 400);

        global $wpdb;
        $teams_table = MAC_Voting_DB::table('teams');
        $voters = MAC_Voting_DB::table('voters');
        $teams = $wpdb->get_results("SELECT * FROM $teams_table ORDER BY team_no", ARRAY_A);
        $inserted = 0; $updated = 0; $companions = 0; $non_bus = 0; $line = 1; $errors = array(); $identity_rows = array(); $pending_staff = array();
        $preview_rows = array(); $room_groups = array();
        // Pass 1: dựng nhóm người thân theo ô NOTE gộp — người chính có thể nằm giữa nhóm.
        $family_groups = array();
        $invalid_groups = array();
        foreach ($source_rows as $pre_index => $pre_cells) {
            if ((int) $pre_index <= $header_row) continue;
            $pre_note = sanitize_text_field($pre_cells[$columns['note']] ?? '');
            if (!self::is_family_note($pre_note)) continue;
            $pre_ref = self::xlsx_merge_ref($workbook['merges'] ?? array(), (int) $pre_index, $columns['note'] + 1);
            if ($pre_ref === '') continue;
            if (!isset($family_groups[$pre_ref])) $family_groups[$pre_ref] = array('lines' => array(), 'primaries' => array());
            $family_groups[$pre_ref]['lines'][] = (int) $pre_index;
            $pre_team = trim((string) ($pre_cells[$columns['team']] ?? ''));
            $pre_email = trim((string) ($pre_cells[$columns['email']] ?? ''));
            if ($pre_team !== '' && $pre_email !== '') $family_groups[$pre_ref]['primaries'][] = (int) $pre_index;
        }
        foreach ($family_groups as $pre_ref => $group_info) {
            $primary_count = count($group_info['primaries']);
            if ($primary_count === 1) continue;
            $invalid_groups[$pre_ref] = true;
            $span = min($group_info['lines']) . '–' . max($group_info['lines']);
            $errors[] = $primary_count === 0
                ? "Dòng $span: nhóm NOTE gộp \"Người thân\" không có người chính (cần đúng 1 dòng có Team + Email)"
                : "Dòng $span: nhóm NOTE gộp có $primary_count người chính (cần đúng 1 dòng có Team + Email)";
        }
        $group_primary = array();
        $group_companion_count = array();
        $pending_companions = array();
        $wpdb->query('START TRANSACTION');
        foreach ($source_rows as $sheet_row => $row) {
            if ((int) $sheet_row <= $header_row) continue;
            $line = (int) $sheet_row;
            if (!array_filter($row, static function($value): bool { return trim((string) $value) !== ''; })) continue;
            $name = sanitize_text_field($row[$columns['name']] ?? '');
            $team_value = sanitize_text_field($row[$columns['team']] ?? '');
            $email_value = sanitize_text_field($row[$columns['email']] ?? '');
            $note = sanitize_text_field($row[$columns['note']] ?? '');
            if ($name === '' && $email_value === '') continue; // dòng placeholder sót lại từ ô gộp phòng
            $note_merge = self::xlsx_merge_ref($workbook['merges'] ?? array(), $line, $columns['note'] + 1);
            $in_family_group = $note_merge !== '' && isset($family_groups[$note_merge]);
            $is_companion = $team_value === '' && $email_value === '';
            if ($is_companion && !$in_family_group) {
                $errors[] = "Dòng $line: thiếu Team/Email nhưng không nằm trong ô NOTE gộp \"Người thân\"";
                continue;
            }
            if (!$is_companion && self::is_family_note($note) && $note_merge === '') {
                $errors[] = "Dòng $line: NOTE Người thân phải được gộp theo chiều dọc với người đi kèm";
            }
            if (count($preview_rows) < 100) $preview_rows[] = array_values(array_map('strval', $row));
            if ($is_companion) {
                if ($name === '') { $errors[] = "Dòng $line: người đi kèm thiếu họ tên"; continue; }
                $pending_companions[] = array('line' => $line, 'row' => $row, 'group' => $note_merge);
                continue;
            }
            $email = MAC_Voting_DB::normalize_company_email($email_value);
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
            if ($email_value !== '' && $email === '') $row_issues[] = 'email phải thuộc @macusaone.com, @yesoffice.vn hoặc @macmarketing.vn';
            if ($row_issues) { $errors[] = "Dòng $line: " . implode(', ', $row_issues); continue; }
            $employee = isset($columns['employee']) ? sanitize_text_field($row[$columns['employee']] ?? '') : '';
            if (!$employee && preg_match('/^\s*((?:NVG?|MAC)[-\s]?\d+)\s*-/iu', $name, $employee_match)) $employee = $employee_match[1];
            $status_text = isset($columns['status']) ? MAC_Voting_DB::normalize_name((string) ($row[$columns['status']] ?? '')) : 'hoat dong';
            $status = in_array($status_text, array('inactive','khong','khong hoat dong','0','false'), true) ? 'INACTIVE' : 'ACTIVE';
            $employee = $employee ? strtoupper($employee) : '';
            $email_existing_id = $email !== '' ? (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $voters WHERE email=%s", $email)) : 0;
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
            $identity_key = $email !== '' ? $email : ('row:' . $line);
            if (isset($identity_rows[$identity_key])) {
                $errors[] = "Dòng $line trùng email với dòng " . $identity_rows[$identity_key];
                continue;
            }
            $identity_rows[$identity_key] = $line;
            $conflicting_id = $email !== '' ? (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $voters WHERE email=%s AND id!=%d LIMIT 1",
                $email,
                $existing_id
            )) : 0;
            if ($conflicting_id) {
                $errors[] = "Dòng $line: email đã thuộc một nhân sự khác trong hệ thống";
                continue;
            }
            if ($staff_kind) {
                $pending_staff[] = array('name' => $name, 'email' => $email, 'password' => $password_text, 'kind' => $staff_kind);
            }
            $room_type = sanitize_text_field($row[$columns['room_type']] ?? '');
            $room_no = sanitize_text_field($row[$columns['room']] ?? '');
            $room_merge = self::xlsx_merge_ref($workbook['merges'] ?? array(), $line, $columns['room'] + 1);
            $room_group = $room_merge !== '' ? ('merge:' . $room_merge) : ($room_no !== '' ? ('room:' . MAC_Voting_DB::normalize_name($room_type) . ':' . MAC_Voting_DB::normalize_name($room_no)) : '');
            if ($room_group !== '') $room_groups[$room_group] = (int) ($room_groups[$room_group] ?? 0) + 1;
            // Cột ĐI XE tùy chọn: để trống = đi xe; ghi "Không" = chỉ đi chơi chung, không phân xe.
            $bus_rider = 1;
            if (isset($columns['bus_rider'])) {
                $rider_value = MAC_Voting_DB::normalize_name((string) ($row[$columns['bus_rider']] ?? ''));
                if (in_array($rider_value, array('khong', 'ko', 'k', 'no', '0', 'false', 'khong di', 'khong di xe'), true)) $bus_rider = 0;
            }
            if ($bus_rider === 0) $non_bus++;
            $data = array(
                'full_name' => self::format_import_name($name, $employee), 'search_name' => MAC_Voting_DB::normalize_name($name),
                'employee_code' => $employee ?: null, 'email' => $email !== '' ? $email : null, 'team_id' => (int) $team['id'],
                'birth_year' => sanitize_text_field($row[$columns['birth_year']] ?? ''),
                'gender' => sanitize_text_field($row[$columns['gender']] ?? ''),
                'citizen_id' => sanitize_text_field($row[$columns['citizen_id']] ?? ''),
                'phone' => sanitize_text_field($row[$columns['phone']] ?? ''),
                'room_type' => $room_type, 'room_no' => $room_no, 'room_group' => $room_group ?: null,
                'note' => $note,
                'primary_voter_id' => null,
                'bus_rider' => $bus_rider,
                'import_order' => max(0, $line - $header_row - 1),
                'phone_last4_hash' => '', 'status' => $status, 'updated_at' => MAC_Voting_DB::utc_now(),
            );
            if ($dry_run) {
                if ($existing_id) $updated++;
                else $inserted++;
            } else {
                if ($existing_id) {
                    $saved = $wpdb->update($voters, $data, array('id' => $existing_id));
                    if ($saved === false) { $errors[] = "Dòng $line không lưu được: " . ($wpdb->last_error ?: 'lỗi database'); continue; }
                    $updated++;
                } else {
                    $data['created_at'] = MAC_Voting_DB::utc_now();
                    $saved = $wpdb->insert($voters, $data);
                    if ($saved === false) { $errors[] = "Dòng $line không lưu được: " . ($wpdb->last_error ?: 'lỗi database'); continue; }
                    $inserted++;
                    $existing_id = (int) $wpdb->insert_id;
                }
                $wpdb->query($wpdb->prepare("UPDATE $voters SET status='INACTIVE',updated_at=%s WHERE primary_voter_id=%d AND status='COMPANION'", MAC_Voting_DB::utc_now(), $existing_id));
            }
            if ($in_family_group) {
                $group_primary[$note_merge] = array('id' => (int) $existing_id, 'team_id' => (int) $team['id'], 'name' => $name);
            }
        }
        // Pass 2: nối người đi kèm vào người chính cùng nhóm NOTE (đứng trước hay giữa nhóm đều được).
        foreach ($pending_companions as $pc) {
            $pc_line = $pc['line'];
            $pc_row = $pc['row'];
            $pc_group = $pc['group'];
            if (isset($invalid_groups[$pc_group])) continue;
            $primary = $group_primary[$pc_group] ?? null;
            if (!$primary) {
                $errors[] = "Dòng $pc_line: nhóm người thân không có người chính hợp lệ (người chính cần Team + Email hợp lệ)";
                continue;
            }
            $cname = sanitize_text_field($pc_row[$columns['name']] ?? '');
            $c_search = MAC_Voting_DB::normalize_name($cname);
            $c_existing = 0;
            if (!$dry_run && (int) $primary['id'] > 0) {
                $c_existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $voters WHERE primary_voter_id=%d AND search_name=%s ORDER BY id LIMIT 1", $primary['id'], $c_search));
            }
            $c_identity = 'companion:' . ($primary['id'] ?: $pc_group) . ':' . $c_search;
            if (isset($identity_rows[$c_identity])) {
                $errors[] = "Dòng $pc_line trùng người đi kèm với dòng " . $identity_rows[$c_identity];
                continue;
            }
            $identity_rows[$c_identity] = $pc_line;
            $c_room_type = sanitize_text_field($pc_row[$columns['room_type']] ?? '');
            $c_room_no = sanitize_text_field($pc_row[$columns['room']] ?? '');
            $c_room_merge = self::xlsx_merge_ref($workbook['merges'] ?? array(), $pc_line, $columns['room'] + 1);
            $c_room_group = $c_room_merge !== '' ? ('merge:' . $c_room_merge) : ($c_room_no !== '' ? ('room:' . MAC_Voting_DB::normalize_name($c_room_type) . ':' . MAC_Voting_DB::normalize_name($c_room_no)) : '');
            if ($c_room_group !== '') $room_groups[$c_room_group] = (int) ($room_groups[$c_room_group] ?? 0) + 1;
            $c_data = array(
                'full_name' => self::format_import_name($cname, ''), 'search_name' => $c_search,
                'employee_code' => null, 'email' => null, 'team_id' => (int) $primary['team_id'],
                'birth_year' => sanitize_text_field($pc_row[$columns['birth_year']] ?? ''),
                'gender' => sanitize_text_field($pc_row[$columns['gender']] ?? ''),
                'citizen_id' => sanitize_text_field($pc_row[$columns['citizen_id']] ?? ''),
                'phone' => sanitize_text_field($pc_row[$columns['phone']] ?? ''),
                'room_type' => $c_room_type, 'room_no' => $c_room_no, 'room_group' => $c_room_group ?: null,
                'note' => 'Đi kèm ' . $primary['name'],
                'primary_voter_id' => (int) $primary['id'] ?: null,
                'bus_rider' => 1,
                'import_order' => max(0, $pc_line - $header_row - 1),
                'phone_last4_hash' => '', 'status' => 'COMPANION', 'updated_at' => MAC_Voting_DB::utc_now(),
            );
            if ($dry_run) {
                if ($c_existing) $updated++; else $inserted++;
                $companions++;
                $group_companion_count[$pc_group] = (int) ($group_companion_count[$pc_group] ?? 0) + 1;
                continue;
            }
            if ($c_existing) {
                $saved = $wpdb->update($voters, $c_data, array('id' => $c_existing));
                if ($saved === false) { $errors[] = "Dòng $pc_line không lưu được: " . ($wpdb->last_error ?: 'lỗi database'); continue; }
                $updated++;
            } else {
                $c_data['created_at'] = MAC_Voting_DB::utc_now();
                $saved = $wpdb->insert($voters, $c_data);
                if ($saved === false) { $errors[] = "Dòng $pc_line không lưu được: " . ($wpdb->last_error ?: 'lỗi database'); continue; }
                $inserted++;
            }
            $companions++;
            $group_companion_count[$pc_group] = (int) ($group_companion_count[$pc_group] ?? 0) + 1;
        }
        foreach ($family_groups as $ref => $group_info) {
            if (isset($invalid_groups[$ref])) continue;
            if (count($group_info['primaries']) === 1 && (int) ($group_companion_count[$ref] ?? 0) === 0) {
                $errors[] = 'Dòng ' . $group_info['primaries'][0] . ': NOTE Người thân chưa gộp với dòng người đi kèm nào';
            }
        }
        $family_group_count = 0;
        foreach ($family_groups as $ref => $group_info) {
            if (count($group_info['primaries']) === 1 && (int) ($group_companion_count[$ref] ?? 0) > 0) $family_group_count++;
        }
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
                'companions' => $companions,
                'nonBusRiders' => $non_bus,
                'familyGroups' => $family_group_count,
                'roomGroups' => count(array_filter($room_groups, static function($count): bool { return (int) $count > 1; })),
                'headers' => array_values(array_map('strval', $header)),
                'previewRows' => $preview_rows,
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
        $message = "Đã import: $inserted mới, $updated cập nhật; $companions người đi kèm.";
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

    private static function xlsx_merge_ref(array $merges, int $row, int $col): string {
        foreach ($merges as $merge) {
            if ($row >= (int) $merge['startRow'] && $row <= (int) $merge['endRow'] && $col >= (int) $merge['startCol'] && $col <= (int) $merge['endCol']) {
                return (string) $merge['ref'];
            }
        }
        return '';
    }

    private static function is_family_note(string $note): bool {
        $value = MAC_Voting_DB::normalize_name($note);
        foreach (array('nguoi than', 'nguoi di kem', 'di kem', 'di cung', 'nguoi yeu') as $marker) {
            if (strpos($value, $marker) !== false) return true;
        }
        return (bool) preg_match('/(?:^|\s)(cha|me|bo|vo|chong|con)(?:\s|$)/u', $value);
    }

    private static function format_import_name(string $name, string $employee): string {
        $display = MAC_Voting_DB::title_case($name);
        if ($employee !== '' && preg_match('/^\s*[^\s]+\s*-/u', $display, $match)) {
            $display = strtoupper((string) $match[0]) . ltrim(mb_substr($display, mb_strlen((string) $match[0], 'UTF-8'), null, 'UTF-8'));
        }
        return $display;
    }

    private static function overview_payload(): array {
        $payload = self::overview();
        // HDV chỉ cần check-in + xe của mình: lột bỏ toàn bộ dữ liệu điểm/phiếu/nhân sự.
        if (MAC_Bus::is_guide()) {
            foreach (array('totalBoard', 'results', 'ballots', 'rounds', 'performances', 'games', 'teamPoints', 'voters', 'assignableUsers', 'staff', 'buses', 'artRevealPlan') as $key) {
                unset($payload[$key]);
            }
        }
        return $payload;
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
            'artRevealPlan' => self::art_reveal_plan($results),
            'totalReveal' => MAC_Voting_DB::total_reveal_state(),
            'totalScoresHidden' => MAC_Voting_DB::scores_hidden(),
            'artLightTheme' => MAC_Voting_DB::art_light_theme(),
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
            'buses' => MAC_Checkin::can_scan() ? MAC_Bus::admin_state() : null,
            'myBus' => MAC_Bus::is_guide() ? MAC_Bus::rollcall_state(max(1, MAC_Bus::guide_bus_id(get_current_user_id()))) : null,
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
        $rows = $wpdb->get_results("SELECT v.id,v.full_name,v.email,v.employee_code,v.status,v.qr_version,v.birth_year,v.gender,v.citizen_id,v.phone,v.room_type,v.room_no,v.room_group,v.note,v.primary_voter_id,t.id AS team_id,t.name AS team_name,t.team_no
            FROM $voters v JOIN $teams t ON t.id=v.team_id ORDER BY t.team_no,v.full_name", ARRAY_A) ?: array();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['primary_voter_id'] = $row['primary_voter_id'] !== null ? (int) $row['primary_voter_id'] : null;
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
            'votingEnabled' => $enabled,
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
        $active = MAC_Checkin::active_checkpoint();
        $payload = array(
            'message' => $operation === 'close' ? 'Đã đóng và chốt trạm check-in.' : ($operation === 'reopen' ? 'Đã mở lại trạm với cửa sổ team mới.' : 'Đã mở trạm check-in.'),
            'checkpoints' => MAC_Checkin::checkpoints(),
            'checkinBoard' => self::checkin_overview_board(),
            'openCheckpointId' => $active ? (int) $active['id'] : 0,
        );
        // Chỉ khi đóng trạm mới phải tải lại các bảng điểm nặng;
        // mở/mở lại chỉ thay đổi trạng thái và cửa sổ check-in.
        if ($operation === 'close') {
            $payload['teamPoints'] = MAC_Checkin::team_points_board();
            $payload['totalBoard'] = MAC_Points::dashboard();
        }
        wp_send_json_success($payload);
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
        if (($voter['status'] ?? '') === 'COMPANION') {
            wp_send_json_error(array('message' => 'Người đi kèm không có QR riêng — quét QR của người chính để đưa cả nhóm vào xe.'), 400);
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
        self::guard('operator');
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
        } elseif ($operation === 'clear') {
            $result = MAC_Points::clear_award(
                absint($_POST['categoryId'] ?? 0),
                absint($_POST['teamId'] ?? 0)
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
            'clear' => 'Đã xóa điểm team khỏi hạng mục.',
        );
        wp_send_json_success(array(
            'message' => $messages[$operation] ?? 'Đã cập nhật.',
            'totalBoard' => MAC_Points::dashboard(),
            'categoryId' => $operation === 'add' ? (int) $result : 0,
        ));
    }

    public static function ajax_games(): void {
        self::guard('operator');
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

    public static function template_xlsx(): void {
        if (!MAC_Checkin::is_super() || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'mac_vote_template')) wp_die('Không có quyền.');
        MAC_XLSX::output('Mẫu import nhân sự.xlsx', array(array(
            'name' => 'Nhân sự',
            'rows' => array(
                array('HỌ & TÊN','NĂM SINH','GIỚI TÍNH','CCCD','SĐT','LOẠI PHÒNG','PHÒNG','TEAM','EMAIL','NOTE','ĐI XE'),
                array('NV-177 - Lý Tư Đình','1997','Nữ','','','Triple','34','HẢI ĐỒ','bambinette.ly@macusaone.com','Người thân',''),
                array('Lý Cẩm Uy','2016','Nam','','','','','','','',''),
                array('NV-059 - Nguyễn Ngô Minh Huy','1995','Nam','','','Twin','33','SAO BẮC CỰC','huy.nguyen@macusaone.com','',''),
                array('NV-083 - Tạ Thanh Tú','1995','Nam','','','','','ĐÈN HIỆU','tu.ta@macusaone.com','',''),
                array('NV-099 - Trần Đi Chơi','1990','Nam','','','','','HẢI ĐỒ','choi.tran@macusaone.com','','Không'),
            ),
            'merges' => array(array(2, 6, 3, 6), array(2, 7, 3, 7), array(2, 10, 3, 10), array(4, 6, 5, 6), array(4, 7, 5, 7)),
            'rowStyles' => array(2 => 2, 3 => 2, 4 => 3, 5 => 3),
            'widths' => array(34, 12, 12, 18, 16, 16, 12, 20, 32, 20, 10),
            'autoFilter' => true,
        )));
    }

    public static function export_results_xlsx(): void {
        if (!MAC_Checkin::is_super() || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'mac_vote_export')) wp_die('Không có quyền.');
        global $wpdb;
        $overview = self::overview();
        $rows = array();

        // 1) Văn nghệ
        $rows[] = array('VĂN NGHỆ · KẾT QUẢ BẦU CHỌN');
        $rows[] = array('Hạng','Team','Số phiếu','Điểm trung bình','Phong cách','Dàn dựng','Đồng đội');
        $previous_score = null; $current_rank = 0;
        foreach ($overview['results'] as $index => $row) {
            if ($row['average_score'] === null) {
                $display_rank = '—';
            } else {
                if ($previous_score === null || (float) $previous_score !== (float) $row['average_score']) $current_rank = $index + 1;
                $display_rank = $current_rank; $previous_score = $row['average_score'];
            }
            $rows[] = array($display_rank, '#' . $row['team_no'] . ' ' . $row['team_name'], $row['voter_count'], $row['average_score'] ?? 'Chưa có lượt vote', $row['style_average'] ?? '', $row['staging_average'] ?? '', $row['teamwork_average'] ?? '');
        }
        $rows[] = array(); $rows[] = array('CHI TIẾT PHIẾU');
        $rows[] = array('Người chấm','Team người chấm','Tiết mục','Tổng điểm','Trạng thái','Thời gian');
        foreach ($overview['ballots'] as $row) $rows[] = array($row['full_name'],$row['voter_team'],$row['performance_team'],$row['total_score'],$row['status'],$row['created_at']);

        // 2) Tổng điểm 4 mặt trận
        $rows[] = array(); $rows[] = array('TỔNG ĐIỂM · 4 MẶT TRẬN');
        $rows[] = array('Hạng','Team','Check-in','Trò chơi','Văn nghệ','Thi đua','Tổng');
        foreach (($overview['totalBoard']['teams'] ?? array()) as $row) {
            $rows[] = array($row['rank'] ?? '—', $row['teamName'], $row['checkin'], $row['games'], $row['vote'], $row['thidua'], $row['total']);
        }

        // 3) Check-in từng trạm
        $rows[] = array(); $rows[] = array('CHECK-IN TỪNG TRẠM');
        $rows[] = array('Trạm','Team','Họ tên','Email','Trạng thái','Scanned at','Người quét');
        $checkpoints = MAC_Voting_DB::table('checkpoints');
        $checkins = MAC_Voting_DB::table('checkins');
        $voters = MAC_Voting_DB::table('voters');
        $teams = MAC_Voting_DB::table('teams');
        $rows = array_merge($rows, self::checkin_matrix_rows($wpdb, $checkpoints, $checkins, $voters, $teams, false));

        // 4) Miễn check-in
        $rows[] = array(); $rows[] = array('MIỄN CHECK-IN');
        $rows[] = array('Trạm','Họ tên','Lý do');
        foreach (MAC_Checkin::checkpoints() as $cp) {
            foreach (MAC_Checkin::exemptions((int) $cp['id']) as $ex) {
                $rows[] = array('Trạm ' . $cp['id'] . ' ' . $cp['name'], $ex['fullName'], $ex['reason']);
            }
        }

        // 5) Trò chơi lớn
        $rows[] = array(); $rows[] = array('TRÒ CHƠI LỚN');
        $rows[] = array('Game','Team','Hạng','Điểm');
        $game_names = array();
        foreach (MAC_Games::games() as $g) $game_names[(int) $g['id']] = $g['name'];
        foreach ((MAC_Games::board() ?? array()) as $row) {
            foreach (($row['cells'] ?? array()) as $cell) {
                $rows[] = array($game_names[(int) $cell['gameId']] ?? ('Game ' . $cell['gameId']), $row['teamName'], $cell['rank'] ?: 'Chưa xếp', $cell['points']);
            }
        }

        // 6) Thi đua
        $rows[] = array(); $rows[] = array('THI ĐUA');
        $rows[] = array('Hạng mục','Team','Hạng','Điểm','Được tính');
        foreach (($overview['totalBoard']['categories'] ?? array()) as $cat) {
            foreach (($overview['totalBoard']['teams'] ?? array()) as $team) {
                $cell = null;
                foreach (($team['cells'] ?? array()) as $c) { if ((string) ($c['categoryId'] ?? '') === (string) $cat['id']) { $cell = $c; break; } }
                $ladder = array(50, 40, 30, 20, 10, 0);
                $cell_rank = 0;
                if ($cell && $cell['hasScore']) { $pos = array_search((int) $cell['points'], $ladder, true); $cell_rank = $pos === false ? 0 : $pos + 1; }
                $rows[] = array($cat['name'], $team['teamName'], $cell_rank ? ('Hạng ' . $cell_rank) : 'Không tham gia', $cell['points'] ?? 0, ($cat['isComplete'] ?? false) ? 'Có' : 'Không');
            }
        }

        // 7) Phân xe
        $bus_state = MAC_Bus::admin_state();
        $rows[] = array(); $rows[] = array('PHÂN XE');
        $rows[] = array('Xe','Trạng thái','NV QR','Người đi kèm','BTC/Hoa tiêu + thủ công','Tổng');
        foreach ($bus_state['buses'] as $bus) $rows[] = array($bus['name'], $bus['status'], $bus['employees'], $bus['companions'], $bus['staff'], $bus['total']);
        $rows[] = array(); $rows[] = array('MANIFEST TỪNG XE');
        $rows[] = array('Xe','Họ tên','Năm sinh','Giới tính','CCCD','SĐT','Loại phòng','Phòng','Email','Team','Loại','Nguồn');
        foreach ($bus_state['buses'] as $bus) {
            foreach (($bus['manifest'] ?? array()) as $m) {
                $rows[] = array($bus['name'], $m['name'], $m['birthYear'], $m['gender'], $m['citizenId'], $m['phone'], $m['roomType'], $m['roomNo'], $m['email'], $m['teamNo'] ? ('#' . $m['teamNo'] . ' ' . $m['teamName']) : '—', $m['memberType'], $m['source']);
            }
        }
        $rows[] = array(); $rows[] = array('CHƯA PHÂN XE');
        $rows[] = array('Họ tên','Team');
        foreach (($bus_state['unassigned'] ?? array()) as $u) $rows[] = array($u['name'], '#' . $u['teamNo'] . ' ' . $u['teamName']);

        // 8) Điểm danh trên xe
        $rows[] = array(); $rows[] = array('ĐIỂM DANH TRÊN XE');
        $rows[] = array('Xe','Lượt','Thời gian','Họ tên','Có mặt');
        $rollcalls = $wpdb->get_results("SELECT r.id,r.bus_id,r.sequence_no,r.created_at,b.name AS bus_name,
                m2.manual_name,v.full_name,m.present
            FROM " . MAC_Voting_DB::table('bus_rollcalls') . " r
            JOIN " . MAC_Voting_DB::table('buses') . ' b ON b.id=r.bus_id
            LEFT JOIN ' . MAC_Voting_DB::table('bus_rollcall_marks') . " m ON m.rollcall_id=r.id
            LEFT JOIN " . MAC_Voting_DB::table('bus_members') . ' m2 ON m2.id=m.bus_member_id
            LEFT JOIN ' . MAC_Voting_DB::table('voters') . " v ON v.id=m2.voter_id
            ORDER BY r.bus_id,r.sequence_no,v.full_name,m2.manual_name", ARRAY_A) ?: array();
        foreach ($rollcalls as $row) {
            if ($row['full_name'] === null && $row['manual_name'] === null) continue;
            $rows[] = array($row['bus_name'], 'Lượt ' . $row['sequence_no'], $row['created_at'], MAC_Voting_DB::title_case((string) ($row['full_name'] ?? $row['manual_name'])), $row['present'] ? '✓' : '○');
        }

        // 9) Nhân sự
        $rows[] = array(); $rows[] = array('NHÂN SỰ');
        $rows[] = array('Họ tên','Năm sinh','Giới tính','CCCD','SĐT','Loại phòng','Phòng','Team','Email','Note','Mã NV','Trạng thái');
        $people = $wpdb->get_results("SELECT v.full_name,v.birth_year,v.gender,v.citizen_id,v.phone,v.room_type,v.room_no,t.team_no,t.name AS team_name,v.email,v.note,v.employee_code,v.status
            FROM $voters v JOIN $teams t ON t.id=v.team_id ORDER BY t.team_no,v.full_name", ARRAY_A) ?: array();
        foreach ($people as $row) $rows[] = array($row['full_name'], $row['birth_year'], $row['gender'], $row['citizen_id'], $row['phone'], $row['room_type'], $row['room_no'], '#' . $row['team_no'] . ' ' . $row['team_name'], $row['email'], $row['note'], $row['employee_code'], $row['status']);
        MAC_XLSX::output('Kết quả Company Trip.xlsx', array(array('name' => 'Tổng hợp', 'rows' => $rows, 'widths' => array(24, 24, 24, 18, 18, 18, 18, 18, 24, 24, 18, 18))));
    }

    /** Ma trận check-in (mỗi người × mỗi trạm) dùng chung cho export tổng và export check-in. */
    private static function checkin_matrix_rows($wpdb, string $checkpoints, string $checkins, string $voters, string $teams, bool $hanoi_time): array {
        $rows = $wpdb->get_results("SELECT c.name AS checkpoint_name,t.team_no,t.name AS team_name,v.full_name,v.email,
                CASE WHEN i.id IS NULL THEN 'Chưa check-in' ELSE 'Đã check-in' END AS checkin_status,
                i.scanned_at,i.scanned_by
            FROM $voters v
            JOIN $teams t ON t.id=v.team_id
            CROSS JOIN $checkpoints c
            LEFT JOIN $checkins i ON i.voter_id=v.id AND i.checkpoint_id=c.id
            WHERE v.status='ACTIVE'
            ORDER BY c.id,t.team_no,v.full_name", ARRAY_A) ?: array();
        $out = array();
        foreach ($rows as $row) {
            $scanned_by = '';
            if (!empty($row['scanned_by'])) {
                $user = get_userdata((int) $row['scanned_by']);
                $scanned_by = $user ? $user->display_name : (string) $row['scanned_by'];
            }
            $scanned_at = (string) ($row['scanned_at'] ?? '');
            if ($hanoi_time && $scanned_at !== '') $scanned_at = MAC_Voting_DB::hanoi_time($scanned_at, 'd/m/Y H:i');
            $out[] = array($row['checkpoint_name'], '#' . $row['team_no'] . ' ' . $row['team_name'], $row['full_name'], $row['email'], $row['checkin_status'], $scanned_at, $scanned_by);
        }
        return $out;
    }

    public static function export_checkin_xlsx(): void {
        if (!MAC_Checkin::is_super() || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'mac_vote_export_checkin')) wp_die('Không có quyền.');
        global $wpdb;
        $rows = array(array('Checkpoint','Team','Họ tên','Email','Trạng thái check-in','Scanned at','Scanned by'));
        $rows = array_merge($rows, self::checkin_matrix_rows(
            $wpdb,
            MAC_Voting_DB::table('checkpoints'),
            MAC_Voting_DB::table('checkins'),
            MAC_Voting_DB::table('voters'),
            MAC_Voting_DB::table('teams'),
            true
        ));
        $rows[] = array();
        $rows[] = array('XẾP HẠNG');
        $rows[] = array('Checkpoint','Team','Eligible','Checked in','Completed at','Rank','Points');
        foreach (MAC_Checkin::checkpoints() as $checkpoint) {
            foreach (MAC_Checkin::checkpoint_board((int) $checkpoint['id']) as $team) {
                $rows[] = array($checkpoint['name'], '#' . $team['teamNumber'] . ' ' . $team['teamName'], $team['eligible'], $team['checkedIn'], $team['completedAt'], $team['temporaryRank'], $team['temporaryPoints']);
            }
        }
        MAC_XLSX::output('Check-in Company Trip.xlsx', array(array('name' => 'Check-in', 'rows' => $rows, 'widths' => array(24, 22, 32, 32, 20, 20, 24))));
    }

    public static function export_bus_xlsx(): void {
        $bus_id = absint($_GET['bus_id'] ?? 0);
        if (!self::can_export_bus($bus_id) || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'mac_vote_export_bus')) wp_die('Không có quyền.');
        $bus = null;
        foreach (MAC_Bus::admin_state()['buses'] as $candidate) if ((int) $candidate['id'] === $bus_id) $bus = $candidate;
        if (!$bus) wp_die('Xe không tồn tại.');
        $rows = array(array('HỌ & TÊN','NĂM SINH','GIỚI TÍNH','CCCD','SĐT','LOẠI PHÒNG','EMAIL','XE'));
        foreach ($bus['manifest'] as $member) {
            $rows[] = array($member['name'], $member['birthYear'], $member['gender'], $member['citizenId'], $member['phone'], $member['roomType'], $member['email'], $bus['name']);
        }
        MAC_XLSX::output('Danh sách ' . $bus['name'] . '.xlsx', array(array(
            'name' => $bus['name'], 'rows' => $rows, 'widths' => array(34, 12, 12, 20, 18, 18, 34, 12), 'autoFilter' => true,
        )));
    }

    public static function export_all_buses_xlsx(): void {
        if (!MAC_Checkin::is_super() || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'mac_vote_export_all_buses')) wp_die('Không có quyền.');
        $state = MAC_Bus::admin_state();
        if (count($state['buses']) !== MAC_Bus::BUS_COUNT || count(array_filter($state['buses'], static function(array $bus): bool { return $bus['status'] === 'CLOSED'; })) !== MAC_Bus::BUS_COUNT) {
            wp_die('Chỉ xuất danh sách tổng sau khi đã đóng đủ 5 xe.');
        }
        $members = array();
        foreach ($state['buses'] as $bus) {
            foreach ($bus['manifest'] as $member) {
                $member['_busOrder'] = (int) $bus['sortOrder'];
                $members[] = $member;
            }
        }
        $group_counts = array();
        $group_first_order = array();
        foreach ($members as $index => $member) {
            $group = (string) ($member['roomGroup'] ?? '');
            if ($group === '') continue;
            $group_counts[$group] = (int) ($group_counts[$group] ?? 0) + 1;
            $order = $member['importOrder'] !== null ? (int) $member['importOrder'] : (100000 + $index);
            $group_first_order[$group] = isset($group_first_order[$group]) ? min($group_first_order[$group], $order) : $order;
        }
        usort($members, static function(array $a, array $b) use ($group_first_order): int {
            $group_a = (string) ($a['roomGroup'] ?? '');
            $group_b = (string) ($b['roomGroup'] ?? '');
            $order_a = $group_a !== '' ? ($group_first_order[$group_a] ?? 100000) : ($a['importOrder'] ?? (100000 + $a['_busOrder']));
            $order_b = $group_b !== '' ? ($group_first_order[$group_b] ?? 100000) : ($b['importOrder'] ?? (100000 + $b['_busOrder']));
            if ((int) $order_a !== (int) $order_b) return (int) $order_a <=> (int) $order_b;
            if ($group_a !== $group_b) return strcmp($group_a, $group_b);
            return ((int) ($a['importOrder'] ?? 100000)) <=> ((int) ($b['importOrder'] ?? 100000));
        });
        $rows = array(array('HỌ & TÊN','NĂM SINH','GIỚI TÍNH','CCCD','SĐT','EMAIL','CHUNG PHÒNG'));
        $row_styles = array();
        $merges = array();
        $palette = array();
        $palette_index = 0;
        $excel_row = 2;
        for ($index = 0; $index < count($members); $index++) {
            $member = $members[$index];
            $group = (string) ($member['roomGroup'] ?? '');
            $is_shared = $group !== '' && (int) ($group_counts[$group] ?? 0) > 1;
            $label = '';
            if ($is_shared) {
                if (!isset($palette[$group])) {
                    $palette[$group] = 2 + ($palette_index % 7);
                    $palette_index++;
                }
                $row_styles[$excel_row] = $palette[$group];
                $previous_group = $index > 0 ? (string) ($members[$index - 1]['roomGroup'] ?? '') : '';
                if ($previous_group !== $group) {
                    $label = 'Chung phòng';
                    $merge_down = (int) $group_counts[$group] - 1;
                    if ($merge_down > 0) $merges[] = array($excel_row, 7, $excel_row + $merge_down, 7);
                }
            }
            $rows[] = array($member['name'], $member['birthYear'], $member['gender'], $member['citizenId'], $member['phone'], $member['email'], $label);
            $excel_row++;
        }
        MAC_XLSX::output('Tổng danh sách 5 xe.xlsx', array(array(
            'name' => 'Tổng 5 xe', 'rows' => $rows, 'rowStyles' => $row_styles, 'merges' => $merges,
            'widths' => array(36, 12, 12, 20, 18, 34, 20), 'autoFilter' => true,
        )));
    }

    private static function can_export_bus(int $bus_id): bool {
        if (MAC_Checkin::is_super()) return true;
        if (MAC_Bus::is_guide()) return MAC_Bus::guide_bus_id(get_current_user_id()) === $bus_id;
        return current_user_can(MAC_Checkin::CAP);
    }

}
