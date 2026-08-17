<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Checkin {
    public const ROLE = 'mac_btc_checkin';
    public const CAP = 'mac_checkin';
    public const TEAM_META = 'mac_checkin_team_ids';

    private const POINTS = array(
        1 => 50,
        2 => 30,
        3 => 20,
        4 => 10,
        5 => -10,
        6 => -20,
    );

    public static function register_roles(): void {
        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap(self::CAP)) {
            $admin->add_cap(self::CAP);
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

    public static function is_admin_scanner(): bool {
        return current_user_can('manage_options');
    }

    public static function allowed_team_ids(): array {
        global $wpdb;
        if (self::is_admin_scanner()) {
            $teams = MAC_Voting_DB::table('teams');
            return array_map('intval', $wpdb->get_col("SELECT id FROM $teams ORDER BY team_no"));
        }
        $raw = get_user_meta(get_current_user_id(), self::TEAM_META, true);
        if (!is_array($raw)) {
            return array();
        }
        return array_values(array_filter(array_map('intval', $raw)));
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

    public static function active_checkpoint(): ?array {
        foreach (self::checkpoints() as $row) {
            if ($row['status'] === 'OPEN') {
                return $row;
            }
        }
        return null;
    }

    public static function open_checkpoint(int $checkpoint_id) {
        global $wpdb;
        $table = MAC_Voting_DB::table('checkpoints');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $checkpoint_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Mốc check-in không tồn tại.', array('status' => 404));
        }
        if ($row['status'] === 'OPEN') {
            return $row;
        }
        $open = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE status='OPEN' AND id!=%d LIMIT 1", $checkpoint_id));
        if ($open) {
            return new WP_Error('checkpoint_already_open', sprintf('Mốc %d vẫn đang mở. Hãy đóng mốc này trước.', $open), array(
                'status' => 409,
                'openCheckpointId' => $open,
            ));
        }
        $wpdb->update(
            $table,
            array('status' => 'OPEN', 'opened_at' => MAC_Voting_DB::utc_now(), 'closed_at' => null, 'finalized_at' => null),
            array('id' => $checkpoint_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'CHECKPOINT_OPENED', 'checkpoint', (string) $checkpoint_id);
        return self::checkpoint_row($checkpoint_id);
    }

    public static function reopen_checkpoint(int $checkpoint_id) {
        global $wpdb;
        $table = MAC_Voting_DB::table('checkpoints');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $checkpoint_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Mốc check-in không tồn tại.', array('status' => 404));
        }
        if ($row['status'] !== 'CLOSED') {
            return new WP_Error('invalid_state', 'Chỉ mở lại được mốc đã đóng.', array('status' => 409));
        }
        $open = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE status='OPEN' AND id!=%d LIMIT 1", $checkpoint_id));
        if ($open) {
            return new WP_Error('checkpoint_already_open', sprintf('Mốc %d vẫn đang mở. Hãy đóng mốc này trước.', $open), array(
                'status' => 409,
                'openCheckpointId' => $open,
            ));
        }
        $wpdb->update(
            $table,
            array('status' => 'OPEN', 'opened_at' => MAC_Voting_DB::utc_now(), 'closed_at' => null, 'finalized_at' => null),
            array('id' => $checkpoint_id),
            array('%s', '%s', '%s', '%s'),
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
            return new WP_Error('not_found', 'Mốc check-in không tồn tại.', array('status' => 404));
        }
        if ($row['status'] !== 'OPEN') {
            return new WP_Error('invalid_state', 'Mốc không đang mở.', array('status' => 409));
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
            return new WP_Error('db_error', 'Không thể đóng mốc.', array('status' => 500));
        }
        $wpdb->query('COMMIT');
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'CHECKPOINT_CLOSED', 'checkpoint', (string) $checkpoint_id);
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'CHECKPOINT_POINTS_FINALIZED', 'checkpoint', (string) $checkpoint_id, array(
            'teams' => count($results),
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
            return new WP_Error('not_found', 'Mốc check-in không tồn tại.', array('status' => 404));
        }
        if ($checkpoint['status'] !== 'OPEN') {
            return new WP_Error('checkpoint_closed', 'Mốc này chưa mở hoặc đã đóng.', array('status' => 409));
        }
        $voter = MAC_Voting_QR::verify($token);
        if (is_wp_error($voter)) {
            MAC_Voting_DB::audit('STAFF', (string) get_current_user_id(), 'QR_LOGIN_FAILED', 'checkin', null, array(
                'reason' => 'invalid_qr',
                'checkpointId' => $checkpoint_id,
            ));
            return $voter;
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
            return new WP_Error('already_checked_in', $voter['full_name'] . ' đã check-in lúc ' . $existing['scanned_at'] . '.', array(
                'status' => 409,
                'code' => 'ALREADY_CHECKED_IN',
                'voter' => self::public_voter($voter),
                'scannedAt' => $existing['scanned_at'],
                'teamProgress' => self::team_progress($checkpoint_id, $team_id),
            ));
        }
        $now = MAC_Voting_DB::utc_now();
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
                return new WP_Error('already_checked_in', $voter['full_name'] . ' đã check-in lúc ' . $existing['scanned_at'] . '.', array(
                    'status' => 409,
                    'code' => 'ALREADY_CHECKED_IN',
                    'voter' => self::public_voter($voter),
                    'scannedAt' => $existing['scanned_at'],
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
        $eligible = count($members);
        $checked = array();
        $missing = array();
        foreach ($members as $member) {
            $item = array(
                'id' => (int) $member['id'],
                'fullName' => $member['full_name'],
                'email' => $member['email'],
                'scannedAt' => $member['scanned_at'],
            );
            if ($member['scanned_at']) {
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
            'temporaryRank' => $snapshot && $snapshot['rank_no'] !== null ? (int) $snapshot['rank_no'] : null,
            'temporaryPoints' => $snapshot ? (int) $snapshot['points'] : 0,
            'checkedInMembers' => $checked,
            'missingMembers' => $missing,
        );
    }

    public static function checkpoint_board(int $checkpoint_id): array {
        global $wpdb;
        $teams = $wpdb->get_results('SELECT id,name,team_no FROM ' . MAC_Voting_DB::table('teams') . ' ORDER BY team_no', ARRAY_A) ?: array();
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
            'allowedTeams' => $progress,
            'checkinUrl' => MAC_Voting_DB::checkin_page_url(),
        );
    }

    public static function staff_assignments(): array {
        $users = get_users(array(
            'role__in' => array(self::ROLE, 'administrator'),
            'orderby' => 'display_name',
        ));
        $items = array();
        foreach ($users as $user) {
            $team_ids = get_user_meta($user->ID, self::TEAM_META, true);
            $items[] = array(
                'id' => (int) $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'isAdmin' => user_can($user, 'manage_options'),
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
        if (!user_can($user, 'manage_options') && !in_array(self::ROLE, (array) $user->roles, true)) {
            $user->add_role(self::ROLE);
        }
        return true;
    }

    public static function reset_event_data(): void {
        global $wpdb;
        $checkpoints = MAC_Voting_DB::table('checkpoints');
        $checkins = MAC_Voting_DB::table('checkins');
        $results = MAC_Voting_DB::table('checkpoint_results');
        $points = MAC_Voting_DB::table('team_points');
        $wpdb->query("UPDATE $checkpoints SET status='DRAFT', opened_at=NULL, closed_at=NULL, finalized_at=NULL");
        $wpdb->query("DELETE FROM $checkins");
        $wpdb->query("DELETE FROM $results");
        $wpdb->query($wpdb->prepare("DELETE FROM $points WHERE source_type=%s", 'CHECKIN'));
        MAC_Voting_DB::set_voting_enabled(false);
    }

    public static function team_points_board(): array {
        global $wpdb;
        $teams = MAC_Voting_DB::table('teams');
        $points = MAC_Voting_DB::table('team_points');
        $rows = $wpdb->get_results("SELECT t.id,t.team_no,t.name FROM $teams t ORDER BY t.team_no", ARRAY_A) ?: array();
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
        $teams = $wpdb->get_results('SELECT id FROM ' . MAC_Voting_DB::table('teams') . ' ORDER BY team_no', ARRAY_A) ?: array();
        $snapshots = array();
        foreach ($teams as $team) {
            $progress = self::team_progress($checkpoint_id, (int) $team['id']);
            $snapshots[] = $progress;
        }
        $completed = array_values(array_filter($snapshots, static fn(array $row): bool => $row['completed'] && $row['completedAt']));
        usort($completed, static fn(array $a, array $b): int => strcmp((string) $a['completedAt'], (string) $b['completedAt']));
        $ranks = array();
        foreach ($completed as $index => $row) {
            $ranks[$row['teamId']] = $index + 1;
        }
        $results = MAC_Voting_DB::table('checkpoint_results');
        $points_table = MAC_Voting_DB::table('team_points');
        $now = MAC_Voting_DB::utc_now();
        foreach ($snapshots as $row) {
            $rank = $ranks[$row['teamId']] ?? null;
            $points = ($finalize && $rank) ? (self::POINTS[$rank] ?? 0) : 0;
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
                'points' => $finalize ? $points : 0,
                'finalized_at' => $finalize ? $now : null,
            );
            if ($existing_id) {
                $wpdb->update($results, $payload, array('id' => $existing_id));
            } else {
                $wpdb->insert($results, $payload);
            }
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
                    'note' => 'Mốc ' . $checkpoint_id,
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
            'fullName' => $voter['full_name'],
            'teamId' => (int) $voter['team_id'],
            'teamName' => $voter['team_name'],
            'teamNumber' => (int) $voter['team_no'],
        );
    }
}
