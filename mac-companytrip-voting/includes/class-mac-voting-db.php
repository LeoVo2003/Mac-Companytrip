<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Voting_DB {
    public const COMPANY_EMAIL_DOMAIN = 'macusaone.com';
    public const COMPANY_EMAIL_DOMAINS = array('macusaone.com', 'yesoffice.vn', 'macmarketing.vn');
    public const STAFF_TEAM_NO = 7;
    public const STAFF_TEAM_NAME = 'Hoa tiêu';
    public const DEFAULT_STAFF_PASSWORD = 'Mac-123';
    public const DEFAULT_VOTE_DURATION_MINUTES = 5;
    public const DEFAULT_CHECKIN_DURATION_MINUTES = 15;
    public const CHECKIN_MAX_PER_CHECKPOINT = 150;
    public const CHECKIN_WINDOW_MINUTES = 15;
    public const RANK_LADDER = array(50, 40, 30, 20, 10, 0);
    private const DB_VERSION = '1.7.0';

    public static function table(string $name): string {
        global $wpdb;
        return $wpdb->prefix . 'mac_vote_' . $name;
    }

    public static function activate(): void {
        self::migrate_legacy_categories();
        self::install_schema();
        self::seed_reference_data();
        self::seed_checkpoints();
        self::ensure_games();
        MAC_Points::seed_categories();
        self::ensure_vote_page();
        self::ensure_results_page();
        self::ensure_checkin_page();
        self::ensure_admin_page();
        if (get_option('mac_voting_public_enabled', null) === false) {
            add_option('mac_voting_public_enabled', '0', '', false);
        }
        MAC_Checkin::register_roles();
        self::register_rewrites();
        update_option('mac_voting_db_version', self::DB_VERSION, false);
        update_option('mac_voting_plugin_version', MAC_VOTING_VERSION, false);
    }

    public static function maybe_upgrade(): void {
        if (get_option('mac_voting_db_version') !== self::DB_VERSION) {
            self::activate();
        } else {
            self::ensure_admin_page();
            self::ensure_staff_team();
            self::ensure_games();
        }
        if (get_option('mac_voting_plugin_version') !== MAC_VOTING_VERSION) {
            if (version_compare((string) get_option('mac_voting_plugin_version', '0'), '1.8.8', '<')) {
                self::reset_dashboard_passwords();
            }
            self::register_rewrites();
            update_option('mac_voting_plugin_version', MAC_VOTING_VERSION, false);
        }
    }

    /**
     * v1.8.8: đồng bộ mật khẩu mọi tài khoản BTC / Super admin về mật khẩu mặc định chung.
     */
    public static function reset_dashboard_passwords(): void {
        $users = get_users(array(
            'role__in' => array(MAC_Checkin::ROLE, MAC_Checkin::SUPER_ROLE),
            'fields' => array('ID'),
        ));
        foreach ($users as $user) {
            wp_set_password(self::DEFAULT_STAFF_PASSWORD, (int) $user->ID);
        }
    }

    public static function register_rewrites(): void {
        if (!isset($GLOBALS['wp_rewrite'])) {
            return;
        }
        $page_id = (int) get_option('mac_voting_page_id');
        if ($page_id) add_rewrite_rule('^cham-diem-van-nghe/?$', 'index.php?page_id=' . $page_id, 'top');
        $results_page_id = (int) get_option('mac_voting_results_page_id');
        if ($results_page_id) add_rewrite_rule('^ket-qua-van-nghe/?$', 'index.php?page_id=' . $results_page_id, 'top');
        $checkin_page_id = (int) get_option('mac_voting_checkin_page_id');
        if ($checkin_page_id) add_rewrite_rule('^company-trip-checkin/?$', 'index.php?page_id=' . $checkin_page_id, 'top');
        $admin_page_id = (int) get_option('mac_voting_admin_page_id');
        if ($admin_page_id) add_rewrite_rule('^company-trip-admin/?$', 'index.php?page_id=' . $admin_page_id, 'top');
        add_rewrite_rule('^company-trip/q/([^/]+)/?$', 'index.php?mac_qr_token=$matches[1]', 'top');
        flush_rewrite_rules(false);
    }

    private static function migrate_legacy_categories(): void {
        global $wpdb;
        $legacy = self::table('point_categories');
        $target = self::table('thidua_rounds');
        $legacy_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy)) === $legacy;
        $target_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $target)) === $target;
        if ($legacy_exists && !$target_exists) {
            $wpdb->query("RENAME TABLE $legacy TO $target");
        }
    }

    private static function ensure_games(): void {
        global $wpdb;
        $table = self::table('games');
        $now = self::utc_now();
        $items = array(
            1 => 'Trò chơi lớn 1',
            2 => 'Trò chơi lớn 2',
            3 => 'Trò chơi lớn 3',
        );
        foreach ($items as $id => $name) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table (id,name,sort_order,created_at) VALUES (%d,%s,%d,%s)",
                $id,
                $name,
                $id,
                $now
            ));
        }
    }

    private static function install_schema(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $teams = self::table('teams');
        $voters = self::table('voters');
        $performances = self::table('performances');
        $rounds = self::table('rounds');
        $slots = self::table('slots');
        $ballots = self::table('ballots');
        $grants = self::table('revote_grants');
        $audit = self::table('audit');
        $checkpoints = self::table('checkpoints');
        $checkins = self::table('checkins');
        $results_table = self::table('checkpoint_results');
        $points = self::table('team_points');
        $rounds_thidua = self::table('thidua_rounds');
        $windows = self::table('checkpoint_windows');
        $exemptions = self::table('exemptions');
        $games = self::table('games');

        $queries = array(
            "CREATE TABLE $teams (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                team_no tinyint(3) unsigned NOT NULL,
                name varchar(100) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY team_no (team_no),
                UNIQUE KEY name (name)
            ) $charset;",
            "CREATE TABLE $voters (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                full_name varchar(190) NOT NULL,
                search_name varchar(190) NOT NULL,
                employee_code varchar(100) NULL,
                email varchar(190) NULL,
                team_id bigint(20) unsigned NOT NULL,
                phone_last4_hash char(64) NULL,
                qr_version smallint(5) unsigned NOT NULL DEFAULT 1,
                status varchar(20) NOT NULL DEFAULT 'ACTIVE',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY employee_code (employee_code),
                UNIQUE KEY email (email),
                KEY search_name (search_name),
                KEY team_status (team_id,status)
            ) $charset;",
            "CREATE TABLE $performances (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                team_id bigint(20) unsigned NOT NULL,
                title varchar(190) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY team_id (team_id)
            ) $charset;",
            "CREATE TABLE $rounds (
                id tinyint(3) unsigned NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'DRAFT',
                opened_at datetime NULL,
                closes_at datetime NULL,
                closed_at datetime NULL,
                PRIMARY KEY  (id),
                KEY status (status)
            ) $charset;",
            "CREATE TABLE $slots (
                id tinyint(3) unsigned NOT NULL,
                round_id tinyint(3) unsigned NOT NULL,
                position tinyint(3) unsigned NOT NULL,
                performance_id bigint(20) unsigned NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY round_position (round_id,position),
                UNIQUE KEY performance_id (performance_id)
            ) $charset;",
            "CREATE TABLE $ballots (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                request_id char(36) NOT NULL,
                voter_id bigint(20) unsigned NOT NULL,
                performance_id bigint(20) unsigned NOT NULL,
                round_id tinyint(3) unsigned NOT NULL,
                style_score tinyint(3) unsigned NOT NULL,
                staging_score tinyint(3) unsigned NOT NULL,
                teamwork_score tinyint(3) unsigned NOT NULL,
                total_score smallint(5) unsigned NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'VALID',
                active_key varchar(10) NULL DEFAULT 'VALID',
                created_at datetime NOT NULL,
                revoked_at datetime NULL,
                revoked_by bigint(20) unsigned NULL,
                revoke_reason varchar(500) NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY request_id (request_id),
                UNIQUE KEY one_valid_ballot (voter_id,performance_id,active_key),
                KEY performance_status (performance_id,status),
                KEY voter_performance (voter_id,performance_id)
            ) $charset;",
            "CREATE TABLE $grants (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                voter_id bigint(20) unsigned NOT NULL,
                performance_id bigint(20) unsigned NOT NULL,
                unused_key varchar(10) NULL DEFAULT 'UNUSED',
                granted_by bigint(20) unsigned NOT NULL,
                reason varchar(500) NOT NULL,
                created_at datetime NOT NULL,
                used_at datetime NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY one_unused_grant (voter_id,performance_id,unused_key),
                KEY voter_performance (voter_id,performance_id)
            ) $charset;",
            "CREATE TABLE $audit (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                actor_type varchar(20) NOT NULL,
                actor_id varchar(100) NULL,
                action varchar(80) NOT NULL,
                entity_type varchar(50) NOT NULL,
                entity_id varchar(100) NULL,
                details_json longtext NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY created_at (created_at),
                KEY action (action)
            ) $charset;",
            "CREATE TABLE $checkpoints (
                id tinyint(3) unsigned NOT NULL,
                name varchar(190) NOT NULL,
                description varchar(500) NULL,
                max_points int(11) unsigned NOT NULL DEFAULT 0,
                status varchar(20) NOT NULL DEFAULT 'DRAFT',
                opened_at datetime NULL,
                closes_at datetime NULL,
                closed_at datetime NULL,
                finalized_at datetime NULL,
                PRIMARY KEY  (id),
                KEY status (status)
            ) $charset;",
            "CREATE TABLE $checkins (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                checkpoint_id tinyint(3) unsigned NOT NULL,
                voter_id bigint(20) unsigned NOT NULL,
                team_id bigint(20) unsigned NOT NULL,
                scanned_by bigint(20) unsigned NOT NULL,
                scanned_at datetime NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY one_checkin (checkpoint_id,voter_id),
                KEY checkpoint_team (checkpoint_id,team_id),
                KEY scanned_at (scanned_at)
            ) $charset;",
            "CREATE TABLE $results_table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                checkpoint_id tinyint(3) unsigned NOT NULL,
                team_id bigint(20) unsigned NOT NULL,
                eligible_count smallint(5) unsigned NOT NULL DEFAULT 0,
                checked_in_count smallint(5) unsigned NOT NULL DEFAULT 0,
                completed_at datetime NULL,
                rank_no smallint(5) unsigned NULL,
                points int(11) NOT NULL DEFAULT 0,
                finalized_at datetime NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY checkpoint_team (checkpoint_id,team_id),
                KEY ranking (checkpoint_id,completed_at)
            ) $charset;",
            "CREATE TABLE $points (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                team_id bigint(20) unsigned NOT NULL,
                source_type varchar(30) NOT NULL,
                source_id varchar(100) NOT NULL,
                points int(11) NOT NULL,
                note varchar(500) NULL,
                created_by bigint(20) unsigned NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY source_team (source_type,source_id,team_id),
                KEY team_id (team_id)
            ) $charset;",
            "CREATE TABLE $rounds_thidua (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(190) NOT NULL,
                points int(11) NOT NULL DEFAULT 10,
                sort_order smallint(5) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY name (name)
            ) $charset;",
            "CREATE TABLE $windows (
                checkpoint_id tinyint(3) unsigned NOT NULL,
                team_id bigint(20) unsigned NOT NULL,
                window_opens_at datetime NULL,
                window_closes_at datetime NULL,
                PRIMARY KEY  (checkpoint_id,team_id)
            ) $charset;",
            "CREATE TABLE $exemptions (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                checkpoint_id tinyint(3) unsigned NOT NULL,
                voter_id bigint(20) unsigned NOT NULL,
                reason varchar(500) NOT NULL DEFAULT '',
                created_by bigint(20) unsigned NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY one_exemption (checkpoint_id,voter_id)
            ) $charset;",
            "CREATE TABLE $games (
                id tinyint(3) unsigned NOT NULL,
                name varchar(190) NOT NULL,
                sort_order smallint(5) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id)
            ) $charset;",
        );

        foreach ($queries as $query) {
            dbDelta($query);
        }
    }

    private static function seed_reference_data(): void {
        global $wpdb;
        $now = current_time('mysql', true);
        $team_names = array('La Bàn', 'Hải Đồ', 'Đèn Hiệu', 'Viking', 'Sao Bắc Cực', 'Hải Đăng');
        $teams = self::table('teams');
        $performances = self::table('performances');
        $rounds = self::table('rounds');
        $slots = self::table('slots');

        foreach ($team_names as $index => $name) {
            $number = $index + 1;
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $teams (team_no,name,created_at) VALUES (%d,%s,%s)",
                $number,
                $name,
                $now
            ));
            $team_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $teams WHERE team_no=%d", $number));
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $performances (team_id,title,created_at) VALUES (%d,%s,%s)",
                $team_id,
                'Tiết mục ' . $name,
                $now
            ));
        }

        for ($round = 1; $round <= 3; $round++) {
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $rounds (id,status) VALUES (%d,'DRAFT')", $round));
            for ($position = 1; $position <= 2; $position++) {
                $slot_id = (($round - 1) * 2) + $position;
                $performance_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT p.id FROM $performances p JOIN $teams t ON t.id=p.team_id WHERE t.team_no=%d",
                    $slot_id
                ));
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO $slots (id,round_id,position,performance_id) VALUES (%d,%d,%d,%d)",
                    $slot_id,
                    $round,
                    $position,
                    $performance_id
                ));
            }
        }
        self::ensure_staff_team();
    }

    private static function seed_checkpoints(): void {
        global $wpdb;
        $table = self::table('checkpoints');
        $items = array(
            1 => array('Xuất phát công ty', 'Chuẩn bị đi từ công ty — lần 1'),
            2 => array('Đi nhà hàng đêm 1', 'Từ khách sạn tập trung ra nhà hàng — lần 2'),
            3 => array('Team Building sáng ngày 2', 'Tập trung chơi Team Building — lần 3'),
            4 => array('Gala tối ngày 2', 'Tập trung Gala — lần 4'),
        );
        foreach ($items as $id => $item) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table (id,name,description,status) VALUES (%d,%s,%s,'DRAFT')",
                $id,
                $item[0],
                $item[1]
            ));
        }
    }

    private static function ensure_vote_page(): void {
        $page_id = (int) get_option('mac_voting_page_id');
        if ($page_id && get_post($page_id)) {
            return;
        }
        $existing = get_page_by_path('cham-diem-van-nghe');
        if ($existing instanceof WP_Post) {
            update_option('mac_voting_page_id', $existing->ID, false);
            return;
        }
        $page_id = wp_insert_post(array(
            'post_title'   => 'Chấm Điểm Văn Nghệ',
            'post_name'    => 'cham-diem-van-nghe',
            'post_content' => '[mac_companytrip_vote]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ));
        if (!is_wp_error($page_id)) {
            update_option('mac_voting_page_id', (int) $page_id, false);
        }
    }

    private static function ensure_results_page(): void {
        $page_id = (int) get_option('mac_voting_results_page_id');
        if ($page_id && get_post($page_id)) {
            return;
        }
        $existing = get_page_by_path('ket-qua-van-nghe');
        if ($existing instanceof WP_Post) {
            update_option('mac_voting_results_page_id', $existing->ID, false);
            return;
        }
        $page_id = wp_insert_post(array(
            'post_title'   => 'Kết Quả Văn Nghệ',
            'post_name'    => 'ket-qua-van-nghe',
            'post_content' => '[mac_companytrip_results]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ));
        if (!is_wp_error($page_id)) {
            update_option('mac_voting_results_page_id', (int) $page_id, false);
        }
    }

    private static function ensure_checkin_page(): void {
        $page_id = (int) get_option('mac_voting_checkin_page_id');
        if ($page_id && get_post($page_id)) {
            return;
        }
        $existing = get_page_by_path('company-trip-checkin');
        if ($existing instanceof WP_Post) {
            update_option('mac_voting_checkin_page_id', $existing->ID, false);
            return;
        }
        $page_id = wp_insert_post(array(
            'post_title'   => 'Check-in Company Trip',
            'post_name'    => 'company-trip-checkin',
            'post_content' => '[mac_companytrip_checkin]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ));
        if (!is_wp_error($page_id)) {
            update_option('mac_voting_checkin_page_id', (int) $page_id, false);
        }
    }

    private static function ensure_admin_page(): void {
        $page_id = (int) get_option('mac_voting_admin_page_id');
        if ($page_id && get_post($page_id)) {
            return;
        }
        $existing = get_page_by_path('company-trip-admin');
        if ($existing instanceof WP_Post) {
            update_option('mac_voting_admin_page_id', $existing->ID, false);
            return;
        }
        $page_id = wp_insert_post(array(
            'post_title'   => 'Dashboard Company Trip',
            'post_name'    => 'company-trip-admin',
            'post_content' => '[mac_companytrip_admin]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ));
        if (!is_wp_error($page_id)) {
            update_option('mac_voting_admin_page_id', (int) $page_id, false);
        }
    }

    public static function utc_now(): string {
        return current_time('mysql', true);
    }

    /**
     * Đổi datetime UTC trong database sang giờ Hà Nội (UTC+7) để hiển thị.
     */
    public static function hanoi_time(?string $value, string $format = 'H:i d/m/Y'): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value . ' UTC');
        if ($ts === false) {
            return $value;
        }
        return gmdate($format, $ts + 7 * HOUR_IN_SECONDS);
    }

    public static function deadline_from_minutes(int $minutes): string {
        return gmdate('Y-m-d H:i:s', time() + ($minutes * MINUTE_IN_SECONDS));
    }

    public static function duration_minutes(int $minutes, int $default): int {
        return max(1, min(120, $minutes ?: $default));
    }

    public static function expire_open_round(): void {
        global $wpdb;
        $rounds = self::table('rounds');
        $now = self::utc_now();
        $wpdb->query($wpdb->prepare(
            "UPDATE $rounds SET status='CLOSED',closed_at=%s WHERE status='OPEN' AND closes_at IS NOT NULL AND closes_at<=%s",
            $now, $now
        ));
    }

    public static function normalize_name(string $value): string {
        $value = remove_accents(wp_strip_all_tags($value));
        $value = mb_strtolower(trim($value), 'UTF-8');
        return preg_replace('/\s+/u', ' ', $value) ?: '';
    }

    public static function title_case(string $value): string {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        if ($value === '') {
            return $value;
        }
        $words = explode(' ', $value);
        foreach ($words as &$word) {
            $word = mb_strtoupper(mb_substr($word, 0, 1), 'UTF-8') . mb_strtolower(mb_substr($word, 1), 'UTF-8');
        }
        unset($word);
        return implode(' ', $words);
    }

    public static function normalize_company_email(string $value, string $preferred_domain = ''): string {
        $value = mb_strtolower(trim($value), 'UTF-8');
        if ($value === '') {
            return '';
        }
        $domain = self::normalize_email_domain($preferred_domain);
        if (strpos($value, '@') === false) {
            $value .= '@' . $domain;
        }
        $email = sanitize_email($value);
        if (!$email || $email !== $value || !is_email($email)) {
            return '';
        }
        $parts = explode('@', $email, 2);
        $host = $parts[1] ?? '';
        if (empty($parts[0]) || !in_array($host, self::COMPANY_EMAIL_DOMAINS, true)) {
            return '';
        }
        return $parts[0] . '@' . $host;
    }

    public static function normalize_email_domain(string $domain): string {
        $domain = mb_strtolower(ltrim(trim($domain), '@'), 'UTF-8');
        if (in_array($domain, self::COMPANY_EMAIL_DOMAINS, true)) {
            return $domain;
        }
        return self::COMPANY_EMAIL_DOMAIN;
    }

    public static function is_staff_team_no(int $team_no): bool {
        return $team_no === self::STAFF_TEAM_NO;
    }

    public static function staff_team_id(): int {
        global $wpdb;
        self::ensure_staff_team();
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::table('teams') . ' WHERE team_no=%d',
            self::STAFF_TEAM_NO
        ));
    }

    public static function competing_team_ids(): array {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            'SELECT id FROM ' . self::table('teams') . ' WHERE team_no<>%d ORDER BY team_no',
            self::STAFF_TEAM_NO
        ));
        return array_map('intval', $ids ?: array());
    }

    public static function ensure_staff_team(): void {
        global $wpdb;
        $teams = self::table('teams');
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id,name FROM $teams WHERE team_no=%d", self::STAFF_TEAM_NO), ARRAY_A);
        if ($existing) {
            if ($existing['name'] !== self::STAFF_TEAM_NAME) {
                $wpdb->update($teams, array('name' => self::STAFF_TEAM_NAME), array('id' => (int) $existing['id']));
            }
            return;
        }
        $wpdb->insert($teams, array(
            'team_no' => self::STAFF_TEAM_NO,
            'name' => self::STAFF_TEAM_NAME,
            'created_at' => self::utc_now(),
        ), array('%d', '%s', '%s'));
    }

    public static function company_email_from_username(string $username): string {
        return self::normalize_company_email($username);
    }

    public static function is_voting_enabled(): bool {
        return (string) get_option('mac_voting_public_enabled', '0') === '1';
    }

    public static function set_voting_enabled(bool $enabled): void {
        update_option('mac_voting_public_enabled', $enabled ? '1' : '0', false);
    }

    public static function audit(string $actor_type, ?string $actor_id, string $action, string $entity_type, ?string $entity_id = null, array $details = array()): void {
        global $wpdb;
        $wpdb->insert(
            self::table('audit'),
            array(
                'actor_type'  => $actor_type,
                'actor_id'    => $actor_id,
                'action'      => $action,
                'entity_type' => $entity_type,
                'entity_id'   => $entity_id,
                'details_json'=> wp_json_encode($details, JSON_UNESCAPED_UNICODE),
                'created_at'  => self::utc_now(),
            ),
            array('%s','%s','%s','%s','%s','%s','%s')
        );
    }

    public static function vote_page_url(): string {
        $page_id = (int) get_option('mac_voting_page_id');
        return $page_id ? (string) get_permalink($page_id) : home_url('/cham-diem-van-nghe/');
    }

    public static function results_page_url(): string {
        $page_id = (int) get_option('mac_voting_results_page_id');
        return $page_id ? (string) get_permalink($page_id) : home_url('/ket-qua-van-nghe/');
    }

    public static function checkin_page_url(): string {
        $page_id = (int) get_option('mac_voting_checkin_page_id');
        return $page_id ? (string) get_permalink($page_id) : home_url('/company-trip-checkin/');
    }

    public static function admin_page_url(): string {
        $page_id = (int) get_option('mac_voting_admin_page_id');
        return $page_id ? (string) get_permalink($page_id) : home_url('/company-trip-admin/');
    }

    public static function reveal_state(): array {
        $state = get_option('mac_voting_reveal_state', array());
        $allowed = array('IDLE', 'ROLLING', 'DECOY', 'THIRD', 'SECOND', 'FINAL');
        $stage = is_array($state) ? strtoupper((string) ($state['stage'] ?? 'IDLE')) : 'IDLE';
        if (!in_array($stage, $allowed, true)) $stage = 'IDLE';
        return array(
            'stage' => $stage,
            'revision' => max(0, (int) (is_array($state) ? ($state['revision'] ?? 0) : 0)),
            'changedAt' => max(0, (int) (is_array($state) ? ($state['changedAt'] ?? 0) : 0)),
        );
    }

    public static function set_reveal_state(string $stage): array {
        $allowed = array('IDLE', 'ROLLING', 'DECOY', 'THIRD', 'SECOND', 'FINAL');
        $stage = strtoupper($stage);
        if (!in_array($stage, $allowed, true)) $stage = 'IDLE';
        $current = self::reveal_state();
        $state = array(
            'stage' => $stage,
            'revision' => (int) $current['revision'] + 1,
            'changedAt' => (int) round(microtime(true) * 1000),
        );
        update_option('mac_voting_reveal_state', $state, false);
        return $state;
    }
}
