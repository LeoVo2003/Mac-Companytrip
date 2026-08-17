<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Voting_DB {
    public const COMPANY_EMAIL_DOMAIN = 'macusaone.com';
    private const DB_VERSION = '1.6.0';

    public static function table(string $name): string {
        global $wpdb;
        return $wpdb->prefix . 'mac_vote_' . $name;
    }

    public static function activate(): void {
        self::install_schema();
        self::seed_reference_data();
        self::seed_checkpoints();
        MAC_Points::seed_categories();
        self::ensure_vote_page();
        self::ensure_results_page();
        self::ensure_checkin_page();
        self::ensure_admin_page();
        if (get_option('mac_voting_public_enabled', null) === false) {
            add_option('mac_voting_public_enabled', '0', '', false);
        }
        MAC_Checkin::register_roles();
        if (isset($GLOBALS['wp_rewrite'])) {
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
        update_option('mac_voting_db_version', self::DB_VERSION, false);
    }

    public static function maybe_upgrade(): void {
        if (get_option('mac_voting_db_version') !== self::DB_VERSION) {
            self::activate();
            return;
        }
        self::ensure_admin_page();
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
        $categories = self::table('point_categories');

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
                status varchar(20) NOT NULL DEFAULT 'DRAFT',
                opened_at datetime NULL,
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
            "CREATE TABLE $categories (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(190) NOT NULL,
                points int(11) NOT NULL DEFAULT 10,
                sort_order smallint(5) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY name (name)
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

    public static function normalize_name(string $value): string {
        $value = remove_accents(wp_strip_all_tags($value));
        $value = mb_strtolower(trim($value), 'UTF-8');
        return preg_replace('/\s+/u', ' ', $value) ?: '';
    }

    public static function normalize_company_email(string $value): string {
        $value = mb_strtolower(trim($value), 'UTF-8');
        if ($value === '') {
            return '';
        }
        if (strpos($value, '@') === false) {
            $value .= '@' . self::COMPANY_EMAIL_DOMAIN;
        }
        $email = sanitize_email($value);
        if (!$email || $email !== $value || !is_email($email) || substr($email, -strlen('@' . self::COMPANY_EMAIL_DOMAIN)) !== '@' . self::COMPANY_EMAIL_DOMAIN) {
            return '';
        }
        $parts = explode('@', $email, 2);
        if (empty($parts[0]) || ($parts[1] ?? '') !== self::COMPANY_EMAIL_DOMAIN) {
            return '';
        }
        return $parts[0] . '@' . self::COMPANY_EMAIL_DOMAIN;
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
