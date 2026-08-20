<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Checkin {
    public const POINT_SOURCE = 'CHECKIN_PROPORTIONAL';
    public const ROLE = 'mac_btc_checkin';
    public const CAP = 'mac_checkin';
    public const SUPER_ROLE = 'mac_companytrip_super_admin';
    public const SUPER_CAP = 'mac_manage_companytrip';
    public const TEAM_META = 'mac_checkin_team_ids';

    public static function register_roles(): void {
        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap(self::CAP)) {
            $admin->add_cap(self::CAP);
        }
        if ($admin && !$admin->has_cap(self::SUPER_CAP)) {
            $admin->add_cap(self::SUPER_CAP);
        }
        $super = get_role(self::SUPER_ROLE);
        if (!$super) {
            add_role(self::SUPER_ROLE, 'MAC Company Trip Super Admin', array(
                'read' => true,
                self::CAP => true,
                self::SUPER_CAP => true,
            ));
        } else {
            $super->add_cap(self::CAP);
            $super->add_cap(self::SUPER_CAP);
        }
        if (!get_role(self::ROLE)) {
            add_role(self::ROLE, 'MAC BTC Check-in', array(
                'read' => true,
                self::CAP => true,
            ));
        }
    }

    public static function can_scan(): bool {
        return current_user_can(self::CAP) || current_user_can('manage_options');
    }

    public static function is_super(): bool {
        return current_user_can(self::SUPER_CAP) || current_user_can('manage_options');
    }

    public static function is_super_user($user): bool {
        return user_can($user, self::SUPER_CAP) || user_can($user, 'manage_options');
    }

    public static function is_admin_scanner(): bool {
        return self::is_super();
    }

    public static function allowed_team_ids(): array {
        $competing = MAC_Voting_DB::competing_team_ids();
        if (self::is_admin_scanner()) {
            return $competing;
        }
        $raw = get_user_meta(get_current_user_id(), self::TEAM_META, true);
        if (!is_array($raw)) {
            return array();
        }
        $assigned = array_values(array_filter(array_map('intval', $raw)));
        return array_values(array_intersect($assigned, $competing));
    }

    public static function checkpoints(): array {
        global $wpdb;
        $table = MAC_Voting_DB::table('checkpoints');
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id", ARRAY_A) ?: array();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
        }
        unset($row);
        return $rows;
    }

    public static function checkpoint_duration_minutes(array $checkpoint): int {
        $opens = !empty($checkpoint['opened_at']) ? strtotime((string) $checkpoint['opened_at']) : 0;
        $closes = !empty($checkpoint['closes_at']) ? strtotime((string) $checkpoint['closes_at']) : 0;
        if ($opens && $closes && $closes > $opens) {
            return max(1, (int) round(($closes - $opens) / 60));
        }
        return MAC_Voting_DB::CHECKIN_WINDOW_MINUTES;
    }

    public static function active_checkpoint(): ?array {
        foreach (self::checkpoints() as $row) {
            if ($row['status'] === 'OPEN') {
                return $row;
            }
        }
        return null;
    }

    public static function expire_active_checkpoint(): void {
        $active = self::active_checkpoint();
        if (!$active || empty($active['closes_at']) || $active['closes_at'] > MAC_Voting_DB::utc_now()) {
            return;
        }
        self::close_checkpoint((int) $active['id']);
    }

    public static function open_checkpoint(int $checkpoint_id, int $minutes = 0) {
        global $wpdb;
        $table = MAC_Voting_DB::table('checkpoints');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $checkpoint_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Trạm check-in không tồn tại.', array('status' => 404));
        }
        if ($row['status'] === 'OPEN') {
            return $row;
        }
        $open = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE status='OPEN' AND id!=%d LIMIT 1", $checkpoint_id));
        if ($open) {
            return new WP_Error('checkpoint_already_open', sprintf('Trạm %d vẫn đang mở. Hãy đóng trạm này trước.', $open), array(
                'status' => 409,
                'openCheckpointId' => $open,
            ));
        }
        $minutes = MAC_Voting_DB::duration_minutes($minutes, MAC_Voting_DB::DEFAULT_CHECKIN_DURATION_MINUTES);
        $wpdb->update(
            $table,
            array('status' => 'OPEN', 'opened_at' => MAC_Voting_DB::utc_now(), 'closes_at' => MAC_Voting_DB::deadline_from_minutes($minutes), 'closed_at' => null, 'finalized_at' => null),
            array('id' => $checkpoint_id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'CHECKPOINT_OPENED', 'checkpoint', (string) $checkpoint_id);
        return self::checkpoint_row($checkpoint_id);
    }

    public static function reopen_checkpoint(int $checkpoint_id, int $minutes = 0) {
        global $wpdb;
        $table = MAC_Voting_DB::table('checkpoints');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $checkpoint_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Trạm check-in không tồn tại.', array('status' => 404));
        }
        if ($row['status'] !== 'CLOSED') {
            return new WP_Error('invalid_state', 'Chỉ mở lại được trạm đã đóng.', array('status' => 409));
        }
        $open = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE status='OPEN' AND id!=%d LIMIT 1", $checkpoint_id));
        if ($open) {
            return new WP_Error('checkpoint_already_open', sprintf('Trạm %d vẫn đang mở. Hãy đóng trạm này trước.', $open), array(
                'status' => 409,
                'openCheckpointId' => $open,
            ));
        }
        $minutes = MAC_Voting_DB::duration_minutes($minutes, MAC_Voting_DB::DEFAULT_CHECKIN_DURATION_MINUTES);
        $wpdb->update(
            $table,
            array('status' => 'OPEN', 'opened_at' => MAC_Voting_DB::utc_now(), 'closes_at' => MAC_Voting_DB::deadline_from_minutes($minutes), 'closed_at' => null, 'finalized_at' => null),
            array('id' => $checkpoint_id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'CHECKPOINT_REOPENED', 'checkpoint', (string) $checkpoint_id);
        return self::checkpoint_row($checkpoint_id);
    }

    public static function close_checkpoint(int $checkpoint_id) {
        global $wpdb;
        $table = MAC_Voting_DB::table('checkpoints');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $checkpoint_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Trạm check-in không tồn tại.', array('status' => 404));
        }
        if ($row['status'] !== 'OPEN') {
            return new WP_Error('invalid_state', 'Trạm không đang mở.', array('status' => 409));
        }
        $wpdb->query('START TRANSACTION');
        $results = self::recalculate_checkpoint($checkpoint_id, true);
        if (is_wp_error($results)) {
            $wpdb->query('ROLLBACK');
            return $results;
        }
        $now = MAC_Voting_DB::utc_now();
        $updated = $wpdb->update(
            $table,
            array('status' => 'CLOSED', 'closed_at' => $now, 'finalized_at' => $now),
            array('id' => $checkpoint_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        if ($updated === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Không thể đóng trạm.', array('status' => 500));
        }
        $wpdb->query('COMMIT');
        $awards = array();
        foreach ($results as $item) {
            $awards[] = array(
                'teamId' => (int) $item['teamId'],
                'teamName' => $item['teamName'],
                'teamNumber' => (int) $item['teamNumber'],
                'rank' => $item['awardedRank'] ?? null,
                'points' => (int) ($item['awardedPoints'] ?? 0),
            );
        }
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'CHECKPOINT_CLOSED', 'checkpoint', (string) $checkpoint_id);
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'CHECKPOINT_POINTS_FINALIZED', 'checkpoint', (string) $checkpoint_id, array(
            'checkpointId' => (int) $checkpoint_id,
            'checkpointName' => $row['name'],
            'awards' => $awards,
        ));
        return self::checkpoint_row($checkpoint_id);
    }

    public static function scan(string $token, int $checkpoint_id) {
        global $wpdb;
        if (!self::can_scan()) {
            return new WP_Error('forbidden', 'Bạn không có quyền check-in.', array('status' => 403));
        }
        $checkpoint = self::checkpoint_row($checkpoint_id);
        if (!$checkpoint) {
            return new WP_Error('not_found', 'Trạm check-in không tồn tại.', array('status' => 404));
        }
        if ($checkpoint['status'] !== 'OPEN') {
            return new WP_Error('checkpoint_closed', 'Trạm này chưa mở hoặc đã đóng.', array('status' => 409));
        }
        $voter = MAC_Voting_QR::verify($token, true);
        if (is_wp_error($voter)) {
            MAC_Voting_DB::audit('STAFF', (string) get_current_user_id(), 'QR_LOGIN_FAILED', 'checkin', null, array(
                'reason' => $voter->get_error_code(),
                'checkpointId' => $checkpoint_id,
                'rawPrefix' => mb_substr($token, 0, 60),
            ));
            return $voter;
        }
        if (empty($voter['mac_signature_ok'])) {
            MAC_Voting_DB::audit('STAFF', (string) get_current_user_id(), 'QR_SIGNATURE_FALLBACK', 'checkin', (string) $voter['id'], array(
                'checkpointId' => $checkpoint_id,
                'rawPrefix' => mb_substr($token, 0, 60),
            ));
        }
        if (MAC_Voting_DB::is_staff_team_no((int) $voter['team_no'])) {
            return new WP_Error('staff_team', 'Tài khoản BTC không check-in như đội thi.', array('status' => 409));
        }
        $team_id = (int) $voter['team_id'];
        $allowed = self::allowed_team_ids();
        if (!in_array($team_id, $allowed, true)) {
            MAC_Voting_DB::audit('STAFF', (string) get_current_user_id(), 'CHECKIN_WRONG_TEAM_ATTEMPT', 'voter', (string) $voter['id'], array(
                'checkpointId' => $checkpoint_id,
                'voterTeamId' => $team_id,
            ));
            return new WP_Error('wrong_team', $voter['full_name'] . ' thuộc Team ' . (int) $voter['team_no'] . ' ' . $voter['team_name'] . '.', array(
                'status' => 409,
                'code' => 'WRONG_TEAM',
                'voter' => self::public_voter($voter),
            ));
        }
        $checkins = MAC_Voting_DB::table('checkins');
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $checkins WHERE checkpoint_id=%d AND voter_id=%d",
            $checkpoint_id,
            (int) $voter['id']
        ), ARRAY_A);
        if ($existing) {
            MAC_Voting_DB::audit('STAFF', (string) get_current_user_id(), 'CHECKIN_DUPLICATE_ATTEMPT', 'voter', (string) $voter['id'], array(
                'checkpointId' => $checkpoint_id,
            ));
            return new WP_Error('already_checked_in', $voter['full_name'] . ' đã check-in lúc ' . MAC_Voting_DB::hanoi_time($existing['scanned_at']) . '.', array(
                'status' => 409,
                'code' => 'ALREADY_CHECKED_IN',
                'voter' => self::public_voter($voter),
                'scannedAt' => MAC_Voting_DB::hanoi_time($existing['scanned_at']),
                'teamProgress' => self::team_progress($checkpoint_id, $team_id),
            ));
        }
        $now = MAC_Voting_DB::utc_now();
        $windows = MAC_Voting_DB::table('checkpoint_windows');
        $window_minutes = self::checkpoint_duration_minutes($checkpoint);
        $window = self::team_window($checkpoint_id, $team_id);
        if (!$window) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $windows (checkpoint_id,team_id,window_opens_at,window_closes_at) VALUES (%d,%d,%s,%s)",
                $checkpoint_id,
                $team_id,
                $now,
                MAC_Voting_DB::deadline_from_minutes($window_minutes)
            ));
            $window = self::team_window($checkpoint_id, $team_id);
        }
        if ($window && !empty($window['window_closes_at']) && $window['window_closes_at'] <= $now) {
            MAC_Voting_DB::audit('STAFF', (string) get_current_user_id(), 'CHECKIN_WINDOW_LOCKED_ATTEMPT', 'voter', (string) $voter['id'], array(
                'checkpointId' => $checkpoint_id,
                'teamId' => $team_id,
            ));
            return new WP_Error('window_locked', sprintf('Cửa sổ %d phút của Team %d đã khóa, không ghi nhận thêm.', $window_minutes, (int) $voter['team_no']), array(
                'status' => 409,
                'code' => 'WINDOW_LOCKED',
                'voter' => self::public_voter($voter),
                'teamProgress' => self::team_progress($checkpoint_id, $team_id),
            ));
        }
        $inserted = $wpdb->insert($checkins, array(
            'checkpoint_id' => $checkpoint_id,
            'voter_id' => (int) $voter['id'],
            'team_id' => $team_id,
            'scanned_by' => get_current_user_id(),
            'scanned_at' => $now,
            'created_at' => $now,
        ), array('%d', '%d', '%d', '%d', '%s', '%s'));
        if (!$inserted) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $checkins WHERE checkpoint_id=%d AND voter_id=%d",
                $checkpoint_id,
                (int) $voter['id']
            ), ARRAY_A);
            if ($existing) {
                return new WP_Error('already_checked_in', $voter['full_name'] . ' đã check-in lúc ' . MAC_Voting_DB::hanoi_time($existing['scanned_at']) . '.', array(
                    'status' => 409,
                    'code' => 'ALREADY_CHECKED_IN',
                    'voter' => self::public_voter($voter),
                    'scannedAt' => MAC_Voting_DB::hanoi_time($existing['scanned_at']),
                    'teamProgress' => self::team_progress($checkpoint_id, $team_id),
                ));
            }
            return new WP_Error('db_error', 'Không ghi nhận được check-in.', array('status' => 500));
        }
        self::recalculate_checkpoint($checkpoint_id, false);
        MAC_Voting_DB::audit('STAFF', (string) get_current_user_id(), 'CHECKIN_SCANNED', 'voter', (string) $voter['id'], array(
            'checkpointId' => $checkpoint_id,
            'teamId' => $team_id,
        ));
        $progress = self::team_progress($checkpoint_id, $team_id);
        return array(
            'voter' => self::public_voter($voter),
            'checkin' => array(
                'checkpointId' => $checkpoint_id,
                'scannedAt' => $now,
            ),
            'teamProgress' => $progress,
        );
    }

    public static function team_progress(int $checkpoint_id, int $team_id): array {
        global $wpdb;
        $voters = MAC_Voting_DB::table('voters');
        $checkins = MAC_Voting_DB::table('checkins');
        $teams = MAC_Voting_DB::table('teams');
        $exemptions = MAC_Voting_DB::table('exemptions');
        $window = self::team_window($checkpoint_id, $team_id);
        $now = MAC_Voting_DB::utc_now();
        $window_closes_at = $window && !empty($window['window_opens_at']) ? $window['window_closes_at'] : null;
        $window_locked = $window_closes_at !== null && $window_closes_at <= $now;
        $team = $wpdb->get_row($wpdb->prepare("SELECT id,name,team_no FROM $teams WHERE id=%d", $team_id), ARRAY_A);
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT v.id,v.full_name,v.email,c.scanned_at
             FROM $voters v
             LEFT JOIN $checkins c ON c.voter_id=v.id AND c.checkpoint_id=%d
             WHERE v.team_id=%d AND v.status='ACTIVE'
             ORDER BY v.full_name",
            $checkpoint_id,
            $team_id
        ), ARRAY_A) ?: array();
        $exempt_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT voter_id,reason FROM $exemptions WHERE checkpoint_id=%d",
            $checkpoint_id
        ), ARRAY_A) ?: array();
        $exempt_map = array();
        foreach ($exempt_rows as $exempt_row) {
            $exempt_map[(int) $exempt_row['voter_id']] = (string) $exempt_row['reason'];
        }
        $eligible = 0;
        $checked = array();
        $missing = array();
        $exempted = array();
        foreach ($members as $member) {
            $member_id = (int) $member['id'];
            $item = array(
                'id' => $member_id,
                'fullName' => MAC_Voting_DB::title_case((string) $member['full_name']),
                'email' => $member['email'],
                'scannedAt' => MAC_Voting_DB::hanoi_time($member['scanned_at']),
            );
            if (isset($exempt_map[$member_id])) {
                $item['reason'] = $exempt_map[$member_id];
                $exempted[] = $item;
                continue;
            }
            $eligible++;
            $in_window = $member['scanned_at'] && ($window_closes_at === null || $member['scanned_at'] <= $window_closes_at);
            if ($in_window) {
                $checked[] = $item;
            } else {
                $missing[] = $item;
            }
        }
        $completed = $eligible > 0 && count($checked) === $eligible;
        $completed_at = null;
        if ($completed) {
            $times = array_column($checked, 'scannedAt');
            $completed_at = max($times);
        }
        $snapshot = $wpdb->get_row($wpdb->prepare(
            "SELECT rank_no,points,completed_at FROM " . MAC_Voting_DB::table('checkpoint_results') . " WHERE checkpoint_id=%d AND team_id=%d",
            $checkpoint_id,
            $team_id
        ), ARRAY_A);
        return array(
            'teamId' => $team_id,
            'teamName' => $team ? $team['name'] : '',
            'teamNumber' => $team ? (int) $team['team_no'] : 0,
            'checkedIn' => count($checked),
            'eligible' => $eligible,
            'missing' => count($missing),
            'completed' => $completed,
            'completedAt' => $completed_at,
            'windowOpensAt' => $window && !empty($window['window_opens_at']) ? $window['window_opens_at'] : null,
            'windowClosesAt' => $window_closes_at,
            'windowLocked' => $window_locked,
            'exemptedCount' => count($exempted),
            'exemptedMembers' => $exempted,
            'temporaryRank' => $snapshot && $snapshot['rank_no'] !== null ? (int) $snapshot['rank_no'] : null,
            'temporaryPoints' => $snapshot ? (int) $snapshot['points'] : 0,
            'checkedInMembers' => $checked,
            'missingMembers' => $missing,
        );
    }

    public static function team_window(int $checkpoint_id, int $team_id): ?array {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . MAC_Voting_DB::table('checkpoint_windows') . ' WHERE checkpoint_id=%d AND team_id=%d',
            $checkpoint_id,
            $team_id
        ), ARRAY_A);
    }

    public static function exemptions(int $checkpoint_id): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT e.id,e.voter_id,e.reason,e.created_at,v.full_name,v.team_id
             FROM ' . MAC_Voting_DB::table('exemptions') . ' e
             JOIN ' . MAC_Voting_DB::table('voters') . ' v ON v.id=e.voter_id
             WHERE e.checkpoint_id=%d
             ORDER BY v.full_name',
            $checkpoint_id
        ), ARRAY_A) ?: array();
        $items = array();
        foreach ($rows as $row) {
            $items[] = array(
                'id' => (int) $row['id'],
                'voterId' => (int) $row['voter_id'],
                'fullName' => MAC_Voting_DB::title_case((string) $row['full_name']),
                'teamId' => (int) $row['team_id'],
                'reason' => $row['reason'],
                'createdAt' => $row['created_at'],
            );
        }
        return $items;
    }

    public static function set_exemption(int $checkpoint_id, int $voter_id, bool $exempt, string $reason = '') {
        global $wpdb;
        $checkpoint = self::checkpoint_row($checkpoint_id);
        if (!$checkpoint) {
            return new WP_Error('not_found', 'Trạm check-in không tồn tại.', array('status' => 404));
        }
        if ($checkpoint['status'] === 'CLOSED') {
            return new WP_Error('checkpoint_closed', 'Trạm đã đóng, không thay đổi được danh sách miễn.', array('status' => 409));
        }
        $voter = $wpdb->get_row($wpdb->prepare(
            'SELECT id,full_name,team_id FROM ' . MAC_Voting_DB::table('voters') . ' WHERE id=%d',
            $voter_id
        ), ARRAY_A);
        if (!$voter) {
            return new WP_Error('voter_not_found', 'Không tìm thấy nhân sự.', array('status' => 404));
        }
        if (MAC_Voting_DB::is_staff_team_no((int) $wpdb->get_var($wpdb->prepare(
            'SELECT team_no FROM ' . MAC_Voting_DB::table('teams') . ' WHERE id=%d',
            (int) $voter['team_id']
        )))) {
            return new WP_Error('staff_team', 'Tài khoản BTC không nằm trong danh sách check-in.', array('status' => 409));
        }
        $table = MAC_Voting_DB::table('exemptions');
        $now = MAC_Voting_DB::utc_now();
        if ($exempt) {
            $reason = mb_substr(trim(wp_strip_all_tags($reason)), 0, 500, 'UTF-8');
            if ($reason === '') {
                return new WP_Error('reason_required', 'Cần ghi lý do miễn check-in.', array('status' => 400));
            }
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $table (checkpoint_id,voter_id,reason,created_by,created_at) VALUES (%d,%d,%s,%d,%s)
                 ON DUPLICATE KEY UPDATE reason=VALUES(reason),created_by=VALUES(created_by),created_at=VALUES(created_at)",
                $checkpoint_id,
                $voter_id,
                $reason,
                get_current_user_id(),
                $now
            ));
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'CHECKIN_EXEMPTION_SET', 'voter', (string) $voter_id, array(
                'checkpointId' => $checkpoint_id,
                'fullName' => $voter['full_name'],
                'reason' => $reason,
            ));
        } else {
            $wpdb->delete($table, array('checkpoint_id' => $checkpoint_id, 'voter_id' => $voter_id), array('%d', '%d'));
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'CHECKIN_EXEMPTION_CLEARED', 'voter', (string) $voter_id, array(
                'checkpointId' => $checkpoint_id,
                'fullName' => $voter['full_name'],
            ));
        }
        self::recalculate_checkpoint($checkpoint_id, false);
        return self::team_progress($checkpoint_id, (int) $voter['team_id']);
    }

    public static function checkpoint_board(int $checkpoint_id): array {
        global $wpdb;
        $teams = $wpdb->get_results($wpdb->prepare(
            'SELECT id,name,team_no FROM ' . MAC_Voting_DB::table('teams') . ' WHERE team_no<>%d ORDER BY team_no',
            MAC_Voting_DB::STAFF_TEAM_NO
        ), ARRAY_A) ?: array();
        $board = array();
        foreach ($teams as $team) {
            $board[] = self::team_progress($checkpoint_id, (int) $team['id']);
        }
        usort($board, static function(array $a, array $b): int {
            if ($a['completed'] !== $b['completed']) {
                return $a['completed'] ? -1 : 1;
            }
            if ($a['completed'] && $b['completed']) {
                return strcmp((string) $a['completedAt'], (string) $b['completedAt']);
            }
            return $a['teamNumber'] <=> $b['teamNumber'];
        });
        return $board;
    }

    public static function bootstrap(): array {
        $checkpoint = self::active_checkpoint();
        $team_ids = self::allowed_team_ids();
        $progress = array();
        if ($checkpoint) {
            foreach ($team_ids as $team_id) {
                $progress[] = self::team_progress((int) $checkpoint['id'], (int) $team_id);
            }
        }
        $user = wp_get_current_user();
        return array(
            'staff' => array(
                'id' => (int) $user->ID,
                'name' => $user->display_name,
                'isAdmin' => self::is_admin_scanner(),
            ),
            'activeCheckpoint' => $checkpoint,
            'windowMinutes' => $checkpoint ? self::checkpoint_duration_minutes($checkpoint) : MAC_Voting_DB::CHECKIN_WINDOW_MINUTES,
            'allowedTeams' => $progress,
            'checkinUrl' => MAC_Voting_DB::checkin_page_url(),
        );
    }

    public static function staff_assignments(): array {
        $users = get_users(array(
            'role__in' => array(self::ROLE, self::SUPER_ROLE, 'administrator'),
            'orderby' => 'display_name',
        ));
        $items = array();
        foreach ($users as $user) {
            $team_ids = get_user_meta($user->ID, self::TEAM_META, true);
            $items[] = array(
                'id' => (int) $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'isAdmin' => self::is_super_user($user),
                'teamIds' => is_array($team_ids) ? array_values(array_map('intval', $team_ids)) : array(),
            );
        }
        return $items;
    }

    public static function save_staff(int $user_id, array $team_ids) {
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('not_found', 'Không tìm thấy tài khoản.', array('status' => 404));
        }
        $team_ids = array_values(array_unique(array_filter(array_map('intval', $team_ids))));
        update_user_meta($user_id, self::TEAM_META, $team_ids);
        if (!self::is_super_user($user) && !in_array(self::ROLE, (array) $user->roles, true)) {
            $user->add_role(self::ROLE);
        }
        return true;
    }

    public static function ensure_staff_user(string $name, string $email, string $password = '', string $kind = 'btc') {
        if (!is_email($email)) {
            return new WP_Error('invalid', 'Email tài khoản dashboard không hợp lệ.');
        }
        $kind = $kind === 'super' ? 'super' : 'btc';
        $pass = trim($password) !== '' ? trim($password) : MAC_Voting_DB::DEFAULT_STAFF_PASSWORD;
        $team_ids = MAC_Voting_DB::competing_team_ids();
        $user = get_user_by('email', $email);
        $wp_role = $kind === 'super' ? self::SUPER_ROLE : self::ROLE;
        if ($user) {
            if ($kind === 'super') {
                if (!user_can($user, 'manage_options')) {
                    $user->set_role(self::SUPER_ROLE);
                }
            } elseif (!self::is_super_user($user) && !in_array(self::ROLE, (array) $user->roles, true)) {
                $user->add_role(self::ROLE);
            }
            wp_set_password($pass, $user->ID);
            if ($kind === 'btc' && !self::is_super_user($user)) {
                $existing = get_user_meta($user->ID, self::TEAM_META, true);
                if (!is_array($existing) || !$existing) {
                    update_user_meta($user->ID, self::TEAM_META, $team_ids);
                }
            }
            return array(
                'created' => false,
                'password' => $pass,
                'email' => $email,
                'name' => $name,
                'kind' => $kind,
            );
        }
        $login = sanitize_user((string) strstr($email, '@', true), true);
        if ($login === '') {
            $login = sanitize_user($email, true);
        }
        if (username_exists($login)) {
            $login = sanitize_user(str_replace('@', '.', $email), true);
        }
        $user_id = wp_insert_user(array(
            'user_login' => $login,
            'user_email' => $email,
            'user_pass' => $pass,
            'display_name' => $name,
            'role' => $wp_role,
        ));
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        if ($kind === 'btc') {
            update_user_meta((int) $user_id, self::TEAM_META, $team_ids);
        }
        return array(
            'created' => true,
            'password' => $pass,
            'email' => $email,
            'name' => $name,
            'kind' => $kind,
        );
    }

    public static function reset_event_data(): void {
        global $wpdb;
        $checkpoints = MAC_Voting_DB::table('checkpoints');
        $checkins = MAC_Voting_DB::table('checkins');
        $results = MAC_Voting_DB::table('checkpoint_results');
        $points = MAC_Voting_DB::table('team_points');
        $windows = MAC_Voting_DB::table('checkpoint_windows');
        $exemptions = MAC_Voting_DB::table('exemptions');
        $wpdb->query("UPDATE $checkpoints SET status='DRAFT', opened_at=NULL, closes_at=NULL, closed_at=NULL, finalized_at=NULL");
        $wpdb->query("DELETE FROM $checkins");
        $wpdb->query("DELETE FROM $results");
        $wpdb->query("DELETE FROM $windows");
        $wpdb->query("DELETE FROM $exemptions");
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $points WHERE source_type IN (%s,%s,%s,%s)",
            'CHECKIN',
            'GAME',
            'THIDUA',
            'CATEGORY'
        ));
        MAC_Voting_DB::set_voting_enabled(false);
    }

    public static function team_points_board(): array {
        global $wpdb;
        $teams = MAC_Voting_DB::table('teams');
        $points = MAC_Voting_DB::table('team_points');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.id,t.team_no,t.name FROM $teams t WHERE t.team_no<>%d ORDER BY t.team_no",
            MAC_Voting_DB::STAFF_TEAM_NO
        ), ARRAY_A) ?: array();
        $ledger = $wpdb->get_results($wpdb->prepare(
            "SELECT team_id,source_id,points FROM $points WHERE source_type=%s",
            'CHECKIN'
        ), ARRAY_A) ?: array();
        $map = array();
        foreach ($ledger as $row) {
            $map[(int) $row['team_id']][$row['source_id']] = (int) $row['points'];
        }
        $board = array();
        foreach ($rows as $team) {
            $team_id = (int) $team['id'];
            $scores = array();
            $total = 0;
            for ($checkpoint = 1; $checkpoint <= 4; $checkpoint++) {
                $value = $map[$team_id]['CHECKPOINT_' . $checkpoint] ?? 0;
                $scores[] = $value;
                $total += $value;
            }
            $board[] = array(
                'teamId' => $team_id,
                'teamNumber' => (int) $team['team_no'],
                'teamName' => $team['name'],
                'checkpoints' => $scores,
                'total' => $total,
            );
        }
        usort($board, static fn(array $a, array $b): int => $b['total'] <=> $a['total'] ?: $a['teamNumber'] <=> $b['teamNumber']);
        return $board;
    }

    private static function recalculate_checkpoint(int $checkpoint_id, bool $finalize) {
        global $wpdb;
        $checkpoint = self::checkpoint_row($checkpoint_id);
        if (!$checkpoint) {
            return new WP_Error('not_found', 'Trạm check-in không tồn tại.', array('status' => 404));
        }
        $teams = $wpdb->get_results($wpdb->prepare(
            'SELECT id FROM ' . MAC_Voting_DB::table('teams') . ' WHERE team_no<>%d ORDER BY team_no',
            MAC_Voting_DB::STAFF_TEAM_NO
        ), ARRAY_A) ?: array();
        $snapshots = array();
        foreach ($teams as $team) {
            $progress = self::team_progress($checkpoint_id, (int) $team['id']);
            $snapshots[] = $progress;
        }
        $results = MAC_Voting_DB::table('checkpoint_results');
        $points_table = MAC_Voting_DB::table('team_points');
        $now = MAC_Voting_DB::utc_now();
        foreach ($snapshots as $index => $row) {
            $rank = null;
            $max_points = MAC_Voting_DB::CHECKIN_MAX_PER_CHECKPOINT;
            $points = $row['eligible'] > 0 ? (int) round(($max_points * $row['checkedIn']) / $row['eligible']) : 0;
            $existing_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $results WHERE checkpoint_id=%d AND team_id=%d",
                $checkpoint_id,
                $row['teamId']
            ));
            $payload = array(
                'checkpoint_id' => $checkpoint_id,
                'team_id' => $row['teamId'],
                'eligible_count' => $row['eligible'],
                'checked_in_count' => $row['checkedIn'],
                'completed_at' => $row['completedAt'],
                'rank_no' => $rank,
                'points' => $points,
                'finalized_at' => $finalize ? $now : null,
            );
            if ($existing_id) {
                $wpdb->update($results, $payload, array('id' => $existing_id));
            } else {
                $wpdb->insert($results, $payload);
            }
            $row['awardedRank'] = $rank;
            $row['awardedPoints'] = $points;
            $snapshots[$index] = $row;
            if ($finalize) {
                $source_id = 'CHECKPOINT_' . $checkpoint_id;
                $point_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $points_table WHERE source_type=%s AND source_id=%s AND team_id=%d",
                    'CHECKIN',
                    $source_id,
                    $row['teamId']
                ));
                $point_row = array(
                    'team_id' => $row['teamId'],
                    'source_type' => 'CHECKIN',
                    'source_id' => $source_id,
                    'points' => $points,
                    'note' => 'Trạm ' . $checkpoint_id . ' · tỷ lệ có mặt',
                    'created_by' => get_current_user_id(),
                    'updated_at' => $now,
                );
                if ($point_id) {
                    $wpdb->update($points_table, $point_row, array('id' => $point_id));
                } else {
                    $point_row['created_at'] = $now;
                    $wpdb->insert($points_table, $point_row);
                }
            }
        }
        return $snapshots;
    }

    private static function checkpoint_row(int $checkpoint_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . MAC_Voting_DB::table('checkpoints') . ' WHERE id=%d',
            $checkpoint_id
        ), ARRAY_A);
        if (!$row) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        return $row;
    }

    private static function public_voter(array $voter): array {
        return array(
            'id' => (int) $voter['id'],
            'fullName' => MAC_Voting_DB::title_case((string) $voter['full_name']),
            'teamId' => (int) $voter['team_id'],
            'teamName' => $voter['team_name'],
            'teamNumber' => (int) $voter['team_no'],
        );
    }
}
