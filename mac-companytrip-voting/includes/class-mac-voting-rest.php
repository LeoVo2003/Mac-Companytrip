<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Voting_REST {
    private const NS = 'mac-voting/v1';

    public static function init(): void {
        add_action('rest_api_init', array(__CLASS__, 'routes'));
    }

    public static function routes(): void {
        register_rest_route(self::NS, '/bootstrap', array(
            'methods' => 'GET', 'callback' => array(__CLASS__, 'bootstrap'), 'permission_callback' => '__return_true',
        ));
        register_rest_route(self::NS, '/login', array(
            'methods' => 'POST', 'callback' => array(__CLASS__, 'login'), 'permission_callback' => '__return_true',
        ));
        register_rest_route(self::NS, '/logout', array(
            'methods' => 'POST', 'callback' => array(__CLASS__, 'logout'), 'permission_callback' => '__return_true',
        ));
        register_rest_route(self::NS, '/submit', array(
            'methods' => 'POST', 'callback' => array(__CLASS__, 'submit'), 'permission_callback' => array(__CLASS__, 'has_voter_session'),
        ));
        register_rest_route(self::NS, '/results', array(
            'methods' => 'GET', 'callback' => array(__CLASS__, 'results'), 'permission_callback' => '__return_true',
        ));
        register_rest_route(self::NS, '/results-total', array(
            'methods' => 'GET', 'callback' => array(__CLASS__, 'results_total'), 'permission_callback' => '__return_true',
        ));
    }

    public static function has_voter_session() {
        return MAC_Voting_Auth::voter_id() ?: new WP_Error('unauthorized', 'Bạn cần đăng nhập để chấm điểm.', array('status' => 401));
    }

    public static function bootstrap(): WP_REST_Response {
        if (!MAC_Voting_DB::is_voting_enabled()) {
            return rest_ensure_response(array('enabled' => false, 'authenticated' => false));
        }
        $voter_id = MAC_Voting_Auth::voter_id();
        if (!$voter_id) {
            return rest_ensure_response(array('enabled' => true, 'authenticated' => false));
        }
        $state = self::vote_state($voter_id);
        if (is_wp_error($state)) {
            MAC_Voting_Auth::logout();
            return rest_ensure_response(array('enabled' => true, 'authenticated' => false));
        }
        return rest_ensure_response(array('enabled' => true, 'authenticated' => true, 'state' => $state));
    }

    public static function login(WP_REST_Request $request) {
        if (!MAC_Voting_DB::is_voting_enabled()) {
            return new WP_Error('voting_disabled', 'Chương trình chưa mở.', array('status' => 403));
        }
        global $wpdb;
        $submitted_username = sanitize_text_field((string) $request->get_param('username'));
        $domain = sanitize_text_field((string) $request->get_param('domain'));
        $email = MAC_Voting_DB::normalize_company_email($submitted_username, $domain);
        $rate_identity = $email ?: mb_strtolower(trim($submitted_username), 'UTF-8');
        if (!$email) {
            return new WP_Error('invalid_login', 'Vui lòng nhập đúng username email công ty.', array('status' => 400));
        }
        $allowed = MAC_Voting_Auth::ensure_login_allowed($rate_identity);
        if (is_wp_error($allowed)) {
            return $allowed;
        }
        $voters = MAC_Voting_DB::table('voters');
        $teams = MAC_Voting_DB::table('teams');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT v.id,v.full_name,v.email,v.status,t.name AS team_name,t.team_no
             FROM $voters v JOIN $teams t ON t.id=v.team_id WHERE v.email=%s",
            $email
        ), ARRAY_A);
        if (!$row || $row['status'] !== 'ACTIVE') {
            MAC_Voting_Auth::failed_login($rate_identity);
            MAC_Voting_DB::audit('SYSTEM', $rate_identity, 'USERNAME_LOGIN_FAILED', 'voter', null, array('username' => $rate_identity));
            return new WP_Error('invalid_login', 'Email này chưa có trong danh sách chấm điểm. Vui lòng liên hệ ban tổ chức.', array('status' => 401));
        }
        if (MAC_Voting_DB::is_staff_team_no((int) $row['team_no'])) {
            return new WP_Error('staff_team', 'Tài khoản BTC đăng nhập dashboard, không dùng trang chấm điểm.', array('status' => 403));
        }
        $voter_id = (int) $row['id'];
        MAC_Voting_Auth::clear_login_attempts($rate_identity);
        MAC_Voting_Auth::create_session($voter_id);
        MAC_Voting_DB::audit('VOTER', (string) $voter_id, 'USERNAME_LOGIN_SUCCESS', 'voter', (string) $voter_id, array('method' => 'company_email'));
        return rest_ensure_response(array(
            'voter' => array(
                'id' => $voter_id, 'fullName' => $row['full_name'], 'teamName' => $row['team_name'], 'teamNumber' => (int) $row['team_no'],
            ),
            'state' => self::vote_state($voter_id),
        ));
    }

    public static function logout(): WP_REST_Response {
        MAC_Voting_Auth::logout();
        return rest_ensure_response(array('ok' => true));
    }

    public static function results(): WP_REST_Response {
        global $wpdb;
        $teams = MAC_Voting_DB::table('teams');
        $performances = MAC_Voting_DB::table('performances');
        $slots = MAC_Voting_DB::table('slots');
        $ballots = MAC_Voting_DB::table('ballots');
        $rows = $wpdb->get_results("SELECT p.id AS performance_id,t.id AS team_id,t.team_no,t.name AS team_name,
                COUNT(b.id) AS voter_count,AVG(b.total_score) AS average_score
            FROM $performances p
            JOIN $slots s ON s.performance_id=p.id
            JOIN $teams t ON t.id=p.team_id
            LEFT JOIN $ballots b ON b.performance_id=p.id AND b.status='VALID'
            GROUP BY p.id,t.id
            ORDER BY average_score DESC,t.team_no", ARRAY_A) ?: array();
        $state = MAC_Voting_DB::reveal_state();
        $rank = 0;
        $previous_score = null;
        foreach ($rows as $index => &$row) {
            if ($row['average_score'] !== null && ($previous_score === null || (float) $previous_score !== (float) $row['average_score'])) {
                $rank = $index + 1;
            }
            $row['rank'] = $row['average_score'] === null ? null : $rank;
            $previous_score = $row['average_score'];
        }
        unset($row);
        $featured_ids = array_map('intval', array_column(array_slice($rows, -3), 'team_id'));
        $public_teams = array_map(static function(array $row) use ($state, $featured_ids): array {
            $is_decoy_featured = $state['stage'] === 'DECOY' && in_array((int) $row['team_id'], $featured_ids, true);
            $minimum_revealed_rank = array(
                'THIRD' => 3,
                'SECOND' => 2,
                'FINAL' => 1,
            )[$state['stage']] ?? null;
            $is_rank_revealed = $minimum_revealed_rank !== null
                && $row['rank'] !== null
                && (int) $row['rank'] >= $minimum_revealed_rank;
            $show_score = $is_decoy_featured || $is_rank_revealed;
            return array(
                'id' => (int) $row['team_id'],
                'number' => (int) $row['team_no'],
                'name' => $row['team_name'],
                'score' => $show_score && $row['average_score'] !== null ? round((float) $row['average_score'], 2) : null,
                'rank' => $is_rank_revealed ? $row['rank'] : null,
                'voterCount' => $is_rank_revealed ? (int) $row['voter_count'] : null,
                'featured' => $is_decoy_featured,
            );
        }, $rows);
        usort($public_teams, static fn(array $a, array $b): int => $a['number'] <=> $b['number']);
        $response = rest_ensure_response(array(
            'stage' => $state['stage'],
            'revision' => $state['revision'],
            'changedAt' => $state['changedAt'],
            'serverTime' => (int) round(microtime(true) * 1000),
            'teams' => $public_teams,
        ));
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        return $response;
    }

    public static function results_total(): WP_REST_Response {
        global $wpdb;
        $teams_table = MAC_Voting_DB::table('teams');
        $state = MAC_Voting_DB::total_reveal_state();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,team_no,name FROM $teams_table WHERE team_no<>%d ORDER BY team_no",
            MAC_Voting_DB::STAFF_TEAM_NO
        ), ARRAY_A) ?: array();
        $totals = $state['totals'];
        if (!$totals) {
            // Chưa mở màn: dùng điểm live (màn IDLE chỉ hiện dấu gạch nên không lộ số).
            foreach ((MAC_Points::dashboard()['teams'] ?? array()) as $board_row) {
                $totals[(int) $board_row['teamId']] = (int) $board_row['total'];
            }
        }
        $rows = array_map(static function(array $row) use ($totals): array {
            $row['total'] = isset($totals[(int) $row['id']]) ? (int) $totals[(int) $row['id']] : 0;
            return $row;
        }, $rows);
        usort($rows, static fn(array $a, array $b): int => ($b['total'] <=> $a['total']) ?: ((int) $a['team_no'] <=> (int) $b['team_no']));
        $rank = 0;
        $previous_total = null;
        foreach ($rows as $index => &$row) {
            if ($previous_total === null || (int) $previous_total !== (int) $row['total']) {
                $rank = $index + 1;
            }
            $row['rank'] = $rank;
            $previous_total = $row['total'];
        }
        unset($row);
        // Thang lộ hạng đếm theo SỐ ĐỘI TỪ DƯỚI LÊN thay vì ngưỡng hạng cứng, để trùng điểm
        // không làm lép bước nào: RANK65 lộ 2 đội cuối, RANK43 lộ 4 đội cuối, FINAL lộ hết.
        // Nhóm trùng điểm vắt ngang mép nhóm sẽ được lộ cùng cụm (vd hạng 4-4-4 lộ cùng bước hạng 5-6).
        $revealed_from_bottom = array(
            'RANK65' => 2,
            'RANK43' => 4,
            'RANK12' => 4,
            'TWIST' => 4,
            'FINAL' => count($rows),
        )[$state['stage']] ?? 0;
        $threshold_total = null;
        if ($revealed_from_bottom > 0 && count($rows)) {
            $threshold_index = max(0, count($rows) - min($revealed_from_bottom, count($rows)));
            $threshold_total = (int) $rows[$threshold_index]['total'];
        }
        // Quy tắc trùng điểm: trước FINAL, đội hạng ≤ 2 không bao giờ lộ sớm kể cả khi cụm trùng
        // điểm của chúng chạm ngưỡng lộ — dành cho twist + chung kết (giữ bất ngờ top đầu).
        $protect_top = $state['stage'] !== 'FINAL';
        $top_two = array();
        $public_teams = array_map(static function(array $row) use ($threshold_total, $protect_top, &$top_two): array {
            if ((int) $row['rank'] <= 2) {
                $top_two[] = (int) $row['id'];
            }
            $is_revealed = $threshold_total !== null
                && (int) $row['total'] <= $threshold_total
                && (!$protect_top || (int) $row['rank'] >= 3);
            return array(
                'id' => (int) $row['id'],
                'number' => (int) $row['team_no'],
                'name' => $row['name'],
                'score' => $is_revealed ? (int) $row['total'] : null,
                'rank' => $is_revealed ? (int) $row['rank'] : null,
            );
        }, $rows);
        usort($public_teams, static fn(array $a, array $b): int => $a['number'] <=> $b['number']);
        $response = rest_ensure_response(array(
            'stage' => $state['stage'],
            'revision' => $state['revision'],
            'changedAt' => $state['changedAt'],
            'serverTime' => (int) round(microtime(true) * 1000),
            'teams' => $public_teams,
            'topTwo' => array_values($top_two),
            'scoresHidden' => MAC_Voting_DB::scores_hidden(),
        ));
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        return $response;
    }

    public static function vote_state(int $voter_id) {
        global $wpdb;
        MAC_Voting_DB::expire_open_round();
        $voters = MAC_Voting_DB::table('voters');
        $teams = MAC_Voting_DB::table('teams');
        $rounds = MAC_Voting_DB::table('rounds');
        $slots = MAC_Voting_DB::table('slots');
        $performances = MAC_Voting_DB::table('performances');
        $ballots = MAC_Voting_DB::table('ballots');
        $grants = MAC_Voting_DB::table('revote_grants');
        $voter = $wpdb->get_row($wpdb->prepare(
            "SELECT v.id,v.full_name,v.team_id,v.status,t.name AS team_name,t.team_no
             FROM $voters v JOIN $teams t ON t.id=v.team_id WHERE v.id=%d",
            $voter_id
        ), ARRAY_A);
        if (!$voter || $voter['status'] !== 'ACTIVE') {
            return new WP_Error('unauthorized', 'Phiên đăng nhập không còn hiệu lực.', array('status' => 401));
        }
        $base = array(
            'voter' => array('id' => (int) $voter['id'], 'fullName' => $voter['full_name'], 'teamName' => $voter['team_name'], 'teamNumber' => (int) $voter['team_no']),
            'round' => null,
            'performances' => array(),
        );
        $revote = $wpdb->get_row($wpdb->prepare(
            "SELECT g.id AS grant_id,s.position,s.round_id,r.opened_at,r.closes_at,p.id AS performance_id,p.team_id,p.title,t.name AS team_name,t.team_no
             FROM $grants g JOIN $performances p ON p.id=g.performance_id JOIN $slots s ON s.performance_id=p.id
             JOIN $rounds r ON r.id=s.round_id JOIN $teams t ON t.id=p.team_id
             WHERE g.voter_id=%d AND g.unused_key='UNUSED'
             ORDER BY g.created_at,g.id LIMIT 1",
            $voter_id
        ), ARRAY_A);
        if ($revote) {
            $own = (int) $revote['team_id'] === (int) $voter['team_id'];
            $base['round'] = array('id' => (int) $revote['round_id'], 'openedAt' => $revote['opened_at'], 'closesAt' => $revote['closes_at'], 'isRevote' => true);
            $base['performances'] = array(array(
                'id' => (int) $revote['performance_id'], 'position' => (int) $revote['position'], 'title' => $revote['title'],
                'teamName' => $revote['team_name'], 'teamNumber' => (int) $revote['team_no'], 'isOwnTeam' => $own,
                'hasVoted' => false, 'canVote' => !$own, 'isRevote' => true,
            ));
            $base['status'] = $own ? 'DONE' : 'OPEN';
            return $base;
        }
        $round = $wpdb->get_row("SELECT id,opened_at,closes_at FROM $rounds WHERE status='OPEN' LIMIT 1", ARRAY_A);
        if (!$round) {
            return array_merge($base, array('status' => 'WAITING'));
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.position,p.id AS performance_id,p.team_id,p.title,t.name AS team_name,t.team_no,
                EXISTS(SELECT 1 FROM $ballots b WHERE b.voter_id=%d AND b.performance_id=p.id AND b.status='VALID') AS has_voted,
                EXISTS(SELECT 1 FROM $ballots b2 WHERE b2.voter_id=%d AND b2.performance_id=p.id) AS has_history,
                EXISTS(SELECT 1 FROM $grants g WHERE g.voter_id=%d AND g.performance_id=p.id AND g.unused_key='UNUSED') AS can_revote
             FROM $slots s JOIN $performances p ON p.id=s.performance_id JOIN $teams t ON t.id=p.team_id
             WHERE s.round_id=%d ORDER BY s.position",
            $voter_id, $voter_id, $voter_id, (int) $round['id']
        ), ARRAY_A);
        $items = array();
        $has_open = false;
        foreach ($rows ?: array() as $row) {
            $own = (int) $row['team_id'] === (int) $voter['team_id'];
            $blocked_revoke = (bool) $row['has_history'] && !(bool) $row['has_voted'] && !(bool) $row['can_revote'];
            $can_vote = !$own && !(bool) $row['has_voted'] && !$blocked_revoke;
            $has_open = $has_open || $can_vote;
            $items[] = array(
                'id' => (int) $row['performance_id'], 'position' => (int) $row['position'], 'title' => $row['title'],
                'teamName' => $row['team_name'], 'teamNumber' => (int) $row['team_no'], 'isOwnTeam' => $own,
                'hasVoted' => (bool) $row['has_voted'], 'canVote' => $can_vote,
            );
        }
        $base['round'] = array('id' => (int) $round['id'], 'openedAt' => $round['opened_at'], 'closesAt' => $round['closes_at']);
        $base['performances'] = $items;
        $base['status'] = $has_open ? 'OPEN' : 'DONE';
        return $base;
    }

    public static function submit(WP_REST_Request $request) {
        if (!MAC_Voting_DB::is_voting_enabled()) {
            return new WP_Error('voting_disabled', 'Chương trình chưa mở.', array('status' => 403));
        }
        global $wpdb;
        MAC_Voting_DB::expire_open_round();
        $voter_id = (int) MAC_Voting_Auth::voter_id();
        $ballots_param = $request->get_param('ballots');
        $entries = array();
        if (is_array($ballots_param) && count($ballots_param)) {
            foreach ($ballots_param as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $entries[] = array(
                    'performance_id' => absint(isset($entry['performanceId']) ? $entry['performanceId'] : 0),
                    'request_id' => sanitize_text_field((string) (isset($entry['requestId']) ? $entry['requestId'] : '')),
                    'scores' => isset($entry['scores']) && is_array($entry['scores']) ? $entry['scores'] : array(),
                );
            }
        }
        if (!count($entries)) {
            $entries[] = array(
                'performance_id' => absint($request->get_param('performanceId')),
                'request_id' => sanitize_text_field((string) $request->get_param('requestId')),
                'scores' => $request->get_param('scores'),
            );
        }
        $allowed_scores = array(10, 20, 30, 40, 50);
        foreach ($entries as $entry) {
            $style = is_array($entry['scores']) ? (int) ($entry['scores']['styleScore'] ?? 0) : 0;
            $staging = is_array($entry['scores']) ? (int) ($entry['scores']['stagingScore'] ?? 0) : 0;
            $teamwork = is_array($entry['scores']) ? (int) ($entry['scores']['teamworkScore'] ?? 0) : 0;
            if (!$entry['performance_id'] || !wp_is_uuid($entry['request_id']) || !in_array($style, $allowed_scores, true) || !in_array($staging, $allowed_scores, true) || !in_array($teamwork, $allowed_scores, true)) {
                return new WP_Error('invalid_ballot', 'Vui lòng chấm đủ 3 tiêu chí.', array('status' => 400));
            }
        }
        $ballots = MAC_Voting_DB::table('ballots');
        foreach ($entries as $entry) {
            if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $ballots WHERE request_id=%s", $entry['request_id']))) {
                return rest_ensure_response(array('ok' => true, 'duplicate' => true, 'state' => self::vote_state($voter_id)));
            }
        }
        $voters = MAC_Voting_DB::table('voters');
        $performances = MAC_Voting_DB::table('performances');
        $slots = MAC_Voting_DB::table('slots');
        $rounds = MAC_Voting_DB::table('rounds');
        $grants = MAC_Voting_DB::table('revote_grants');
        $voter = $wpdb->get_row($wpdb->prepare("SELECT status, team_id FROM $voters WHERE id=%d", $voter_id), ARRAY_A);
        if (!$voter || $voter['status'] !== 'ACTIVE') {
            return new WP_Error('forbidden', 'Tài khoản không hợp lệ.', array('status' => 403));
        }
        $voter_team = (int) $voter['team_id'];
        $targets = array();
        foreach ($entries as $entry) {
            $target = $wpdb->get_row($wpdb->prepare(
                "SELECT p.team_id,s.round_id,r.status AS round_status
                 FROM $performances p JOIN $slots s ON s.performance_id=p.id JOIN $rounds r ON r.id=s.round_id
                 WHERE p.id=%d",
                $entry['performance_id']
            ), ARRAY_A);
            if (!$target) {
                return new WP_Error('forbidden', 'Tiết mục không hợp lệ.', array('status' => 403));
            }
            if ((int) $target['team_id'] === $voter_team) {
                return new WP_Error('own_team', 'Bạn không thể chấm tiết mục của team mình.', array('status' => 403));
            }
            if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $ballots WHERE voter_id=%d AND performance_id=%d AND status='VALID'", $voter_id, $entry['performance_id']))) {
                return new WP_Error('already_voted', 'Bạn đã chấm tiết mục này rồi.', array('status' => 409));
            }
            $history_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ballots WHERE voter_id=%d AND performance_id=%d", $voter_id, $entry['performance_id']));
            $grant_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $grants WHERE voter_id=%d AND performance_id=%d AND unused_key='UNUSED' LIMIT 1", $voter_id, $entry['performance_id']));
            if ($target['round_status'] !== 'OPEN' && !$grant_id) {
                return new WP_Error('round_closed', 'Lượt chấm điểm này đã đóng.', array('status' => 409));
            }
            if ($history_count > 0 && !$grant_id) {
                return new WP_Error('revote_blocked', 'Phiếu trước đã bị hủy và chưa được cấp quyền vote lại.', array('status' => 403));
            }
            $targets[] = array_merge($entry, array('round_id' => (int) $target['round_id'], 'round_status' => $target['round_status'], 'grant_id' => $grant_id));
        }
        $open_round_id = 0;
        foreach ($targets as $target) {
            if ($target['round_status'] === 'OPEN') {
                $open_round_id = $target['round_id'];
                break;
            }
        }
        if ($open_round_id) {
            $required = array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT p.id FROM $performances p JOIN $slots s ON s.performance_id=p.id
                 WHERE s.round_id=%d AND p.team_id <> %d AND NOT EXISTS(SELECT 1 FROM $ballots b WHERE b.voter_id=%d AND b.performance_id=p.id AND b.status='VALID')",
                $open_round_id, $voter_team, $voter_id
            )));
            $submitted = array();
            foreach ($targets as $target) {
                if ($target['round_status'] === 'OPEN') {
                    $submitted[] = (int) $target['performance_id'];
                }
            }
            sort($required);
            sort($submitted);
            if ($required !== $submitted) {
                return new WP_Error('partial_vote', 'Quy định mới: phải chấm đủ cả hai tiết mục trong lượt, hoặc không chấm.', array('status' => 400));
            }
        }

        $wpdb->query('START TRANSACTION');
        foreach ($targets as $target) {
            $style = (int) $target['scores']['styleScore'];
            $staging = (int) $target['scores']['stagingScore'];
            $teamwork = (int) $target['scores']['teamworkScore'];
            $inserted = $wpdb->insert($ballots, array(
                'request_id' => $target['request_id'], 'voter_id' => $voter_id, 'performance_id' => $target['performance_id'],
                'round_id' => $target['round_id'], 'style_score' => $style, 'staging_score' => $staging,
                'teamwork_score' => $teamwork, 'total_score' => $style + $staging + $teamwork,
                'status' => 'VALID', 'active_key' => 'VALID', 'created_at' => MAC_Voting_DB::utc_now(),
            ), array('%s','%d','%d','%d','%d','%d','%d','%d','%s','%s','%s'));
            if (!$inserted) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('duplicate_vote', 'Phiếu đã tồn tại hoặc không thể ghi nhận.', array('status' => 409));
            }
            $ballot_id = (int) $wpdb->insert_id;
            if ($target['grant_id']) {
                $wpdb->update($grants, array('unused_key' => null, 'used_at' => MAC_Voting_DB::utc_now()), array('id' => $target['grant_id']), array('%s','%s'), array('%d'));
            }
            MAC_Voting_DB::audit('VOTER', (string) $voter_id, 'BALLOT_SUBMITTED', 'ballot', (string) $ballot_id, array('performanceId' => $target['performance_id']));
        }
        $wpdb->query('COMMIT');
        return rest_ensure_response(array('ok' => true, 'state' => self::vote_state($voter_id)));
    }
}
