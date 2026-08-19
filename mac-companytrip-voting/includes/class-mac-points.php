<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Points {
    public const SOURCE = 'THIDUA';
    private const LEGACY_SOURCE = 'CATEGORY';
    public const VOTE_MAX_SCORE = 150;
    public const VOTE_MAX_POINTS = 200;

    public static function categories(): array {
        global $wpdb;
        $table = MAC_Voting_DB::table('thidua_rounds');
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order,id", ARRAY_A) ?: array();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['points'] = (int) $row['points'];
            $row['sort_order'] = (int) $row['sort_order'];
        }
        unset($row);
        return $rows;
    }

    public static function add_category(string $name) {
        global $wpdb;
        $name = sanitize_text_field($name);
        if (mb_strlen($name) < 2) {
            return new WP_Error('invalid', 'Tên lần thi đua phải có ít nhất 2 ký tự.', array('status' => 400));
        }
        $table = MAC_Voting_DB::table('thidua_rounds');
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE name=%s", $name));
        if ($exists) {
            return new WP_Error('duplicate', 'Lần thi đua này đã tồn tại.', array('status' => 409));
        }
        $max = (int) $wpdb->get_var("SELECT MAX(sort_order) FROM $table");
        $inserted = $wpdb->insert($table, array(
            'name' => $name,
            'points' => 0,
            'sort_order' => $max + 1,
            'created_at' => MAC_Voting_DB::utc_now(),
        ), array('%s', '%d', '%d', '%s'));
        if (!$inserted) {
            return new WP_Error('db_error', 'Không thêm được lần thi đua.', array('status' => 500));
        }
        $id = (int) $wpdb->insert_id;
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'POINT_CATEGORY_ADDED', 'thidua_round', (string) $id, array(
            'name' => $name,
        ));
        return $id;
    }

    public static function rename_category(int $category_id, string $name) {
        global $wpdb;
        $name = sanitize_text_field($name);
        if (mb_strlen($name) < 2) {
            return new WP_Error('invalid', 'Tên lần thi đua phải có ít nhất 2 ký tự.', array('status' => 400));
        }
        $table = MAC_Voting_DB::table('thidua_rounds');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $category_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Lần thi đua không tồn tại.', array('status' => 404));
        }
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE name=%s AND id<>%d", $name, $category_id));
        if ($exists) {
            return new WP_Error('duplicate', 'Lần thi đua này đã tồn tại.', array('status' => 409));
        }
        $wpdb->update($table, array('name' => $name), array('id' => $category_id), array('%s'), array('%d'));
        $points = MAC_Voting_DB::table('team_points');
        foreach (array(self::SOURCE, self::LEGACY_SOURCE) as $source_type) {
            $wpdb->update($points, array('note' => $name), array(
                'source_type' => $source_type,
                'source_id' => (string) $category_id,
            ), array('%s'), array('%s', '%s'));
        }
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'POINT_CATEGORY_RENAMED', 'thidua_round', (string) $category_id, array(
            'from' => $row['name'],
            'to' => $name,
        ));
        return true;
    }

    public static function delete_category(int $category_id) {
        global $wpdb;
        $table = MAC_Voting_DB::table('thidua_rounds');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $category_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Lần thi đua không tồn tại.', array('status' => 404));
        }
        $points = MAC_Voting_DB::table('team_points');
        foreach (array(self::SOURCE, self::LEGACY_SOURCE) as $source_type) {
            $wpdb->delete($points, array('source_type' => $source_type, 'source_id' => (string) $category_id), array('%s', '%s'));
        }
        $wpdb->delete($table, array('id' => $category_id), array('%d'));
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'POINT_CATEGORY_DELETED', 'thidua_round', (string) $category_id, array(
            'name' => $row['name'],
        ));
        return true;
    }

    public static function award(int $category_id, int $team_id, int $points) {
        global $wpdb;
        if (!in_array($points, MAC_Voting_DB::RANK_LADDER, true)) {
            return new WP_Error('invalid', 'Điểm thi đua phải là 50, 40, 30, 20, 10 hoặc 0 theo hạng.', array('status' => 400));
        }
        $categories = MAC_Voting_DB::table('thidua_rounds');
        $teams = MAC_Voting_DB::table('teams');
        $category = $wpdb->get_row($wpdb->prepare("SELECT * FROM $categories WHERE id=%d", $category_id), ARRAY_A);
        if (!$category) {
            return new WP_Error('not_found', 'Lần thi đua không tồn tại.', array('status' => 404));
        }
        $team = $wpdb->get_row($wpdb->prepare("SELECT id,name,team_no FROM $teams WHERE id=%d", $team_id), ARRAY_A);
        if (!$team) {
            return new WP_Error('not_found', 'Team không tồn tại.', array('status' => 404));
        }
        if (MAC_Voting_DB::is_staff_team_no((int) $team['team_no'])) {
            return new WP_Error('staff_team', 'Team Hoa tiêu chỉ dành cho BTC, không chấm điểm thi đua.', array('status' => 400));
        }
        $ledger = MAC_Voting_DB::table('team_points');
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id,points FROM $ledger WHERE source_type IN (%s,%s) AND source_id=%s AND team_id=%d",
            self::SOURCE,
            self::LEGACY_SOURCE,
            (string) $category_id,
            $team_id
        ), ARRAY_A);
        $now = MAC_Voting_DB::utc_now();
        if ($points === 0) {
            if ($existing) {
                $wpdb->delete($ledger, array('id' => (int) $existing['id']), array('%d'));
                MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'TEAM_POINTS_CLEARED', 'team', (string) $team_id, array(
                    'categoryId' => $category_id,
                    'category' => $category['name'],
                    'teamName' => $team['name'],
                    'previous' => (int) $existing['points'],
                ));
            }
            return true;
        }
        $payload = array(
            'team_id' => $team_id,
            'source_type' => self::SOURCE,
            'source_id' => (string) $category_id,
            'points' => $points,
            'note' => $category['name'],
            'created_by' => get_current_user_id(),
            'updated_at' => $now,
        );
        if ($existing) {
            $wpdb->update($ledger, $payload, array('id' => (int) $existing['id']));
        } else {
            $payload['created_at'] = $now;
            $wpdb->insert($ledger, $payload);
        }
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'TEAM_POINTS_AWARDED', 'team', (string) $team_id, array(
            'categoryId' => $category_id,
            'category' => $category['name'],
            'teamName' => $team['name'],
            'points' => $points,
            'previous' => $existing ? (int) $existing['points'] : 0,
        ));
        return true;
    }

    public static function vote_averages(): array {
        global $wpdb;
        $ballots = MAC_Voting_DB::table('ballots');
        $performances = MAC_Voting_DB::table('performances');
        $rows = $wpdb->get_results(
            "SELECT p.team_id, AVG(b.total_score) AS avg_score, COUNT(b.id) AS ballot_count
             FROM $performances p
             LEFT JOIN $ballots b ON b.performance_id=p.id AND b.status='VALID'
             GROUP BY p.team_id",
            ARRAY_A
        ) ?: array();
        $map = array();
        foreach ($rows as $row) {
            $team_id = (int) $row['team_id'];
            $ballot_count = (int) $row['ballot_count'];
            if ($ballot_count === 0) {
                $map[$team_id] = array('average' => null, 'ballots' => 0, 'points' => 0);
                continue;
            }
            $average = (float) $row['avg_score'];
            $map[$team_id] = array(
                'average' => round($average, 2),
                'ballots' => $ballot_count,
                'points' => (int) round(($average / self::VOTE_MAX_SCORE) * self::VOTE_MAX_POINTS),
            );
        }
        return $map;
    }

    public static function dashboard(): array {
        global $wpdb;
        $teams_table = MAC_Voting_DB::table('teams');
        $points_table = MAC_Voting_DB::table('team_points');
        $teams = $wpdb->get_results($wpdb->prepare(
            "SELECT id,team_no,name FROM $teams_table WHERE team_no<>%d ORDER BY team_no",
            MAC_Voting_DB::STAFF_TEAM_NO
        ), ARRAY_A) ?: array();
        $ledger = $wpdb->get_results("SELECT team_id,source_type,source_id,points FROM $points_table", ARRAY_A) ?: array();
        $by_team = array();
        foreach ($ledger as $row) {
            $team_id = (int) $row['team_id'];
            if (!isset($by_team[$team_id])) {
                $by_team[$team_id] = array('CHECKIN' => 0, 'THIDUA' => array());
            }
            if ($row['source_type'] === 'CHECKIN') {
                $by_team[$team_id]['CHECKIN'] += (int) $row['points'];
            } elseif ($row['source_type'] === self::SOURCE || $row['source_type'] === self::LEGACY_SOURCE) {
                $by_team[$team_id]['THIDUA'][$row['source_id']] = (int) $row['points'];
            }
        }
        $rounds = self::categories();
        $games_board = MAC_Games::board();
        $games_map = array();
        foreach ($games_board as $game_row) {
            $games_map[$game_row['teamId']] = $game_row;
        }
        $vote_map = self::vote_averages();
        $board = array();
        foreach ($teams as $team) {
            $team_id = (int) $team['id'];
            $awards = $by_team[$team_id]['THIDUA'] ?? array();
            $checkin = (int) ($by_team[$team_id]['CHECKIN'] ?? 0);
            $thidua_total = 0;
            $cells = array();
            foreach ($rounds as $round) {
                $current = $awards[(string) $round['id']] ?? 0;
                $thidua_total += $current;
                $cells[] = array(
                    'categoryId' => $round['id'],
                    'points' => $current,
                    'state' => $current > 0 ? 'plus' : 'none',
                );
            }
            $games_total = (int) ($games_map[$team_id]['total'] ?? 0);
            $vote = $vote_map[$team_id] ?? array('average' => null, 'ballots' => 0, 'points' => 0);
            $board[] = array(
                'teamId' => $team_id,
                'teamNumber' => (int) $team['team_no'],
                'teamName' => $team['name'],
                'checkin' => $checkin,
                'games' => $games_total,
                'vote' => (int) $vote['points'],
                'voteAverage' => $vote['average'],
                'voteBallots' => (int) $vote['ballots'],
                'thidua' => $thidua_total,
                'total' => $checkin + $games_total + (int) $vote['points'] + $thidua_total,
                'cells' => $cells,
            );
        }
        usort($board, static function(array $a, array $b): int {
            if ($a['total'] !== $b['total']) {
                return $b['total'] <=> $a['total'];
            }
            return $a['teamNumber'] <=> $b['teamNumber'];
        });
        $rank = 0;
        $previous = null;
        foreach ($board as $index => &$row) {
            if ($previous === null || $previous !== $row['total']) {
                $rank = $index + 1;
            }
            $row['rank'] = $rank;
            $previous = $row['total'];
        }
        unset($row);
        return array(
            'categories' => $rounds,
            'teams' => $board,
            'history' => self::history(),
        );
    }

    public static function history(): array {
        global $wpdb;
        $audit = MAC_Voting_DB::table('audit');
        $teams = MAC_Voting_DB::table('teams');
        $team_rows = $wpdb->get_results("SELECT id,team_no,name FROM $teams", ARRAY_A) ?: array();
        $team_map = array();
        foreach ($team_rows as $team) {
            $team_map[(int) $team['id']] = $team;
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $audit WHERE action IN (%s,%s,%s,%s) ORDER BY id DESC LIMIT 300",
            'TEAM_POINTS_AWARDED',
            'TEAM_POINTS_CLEARED',
            'CHECKPOINT_POINTS_FINALIZED',
            'GAME_RANK_SET'
        ), ARRAY_A) ?: array();
        $items = array();
        foreach ($rows as $row) {
            $details = json_decode($row['details_json'], true);
            if (!is_array($details)) {
                $details = array();
            }
            $actor = self::actor_label($row['actor_id']);
            $at = MAC_Voting_DB::hanoi_time($row['created_at']);
            if ($row['action'] === 'CHECKPOINT_POINTS_FINALIZED') {
                $checkpoint_name = $details['checkpointName'] ?? ('Mốc ' . $row['entity_id']);
                $awards = isset($details['awards']) && is_array($details['awards']) ? $details['awards'] : array();
                if ($awards) {
                    foreach ($awards as $award) {
                        $points = (int) ($award['points'] ?? 0);
                        if ($points === 0) {
                            continue;
                        }
                        $items[] = array(
                            'at' => $at,
                            'actor' => $actor,
                            'teamName' => $award['teamName'] ?? '',
                            'teamNumber' => (int) ($award['teamNumber'] ?? 0),
                            'source' => 'Check-in',
                            'note' => $checkpoint_name,
                            'points' => $points,
                            'kind' => 'checkin',
                        );
                    }
                } else {
                    $items[] = array(
                        'at' => $at,
                        'actor' => $actor,
                        'teamName' => '6 team',
                        'teamNumber' => 0,
                        'source' => 'Check-in',
                        'note' => $checkpoint_name,
                        'points' => 0,
                        'kind' => 'checkin',
                    );
                }
                continue;
            }
            if ($row['action'] === 'GAME_RANK_SET') {
                $team_id = (int) $row['entity_id'];
                $team = $team_map[$team_id] ?? null;
                $game_rank = (int) ($details['rank'] ?? 0);
                $items[] = array(
                    'at' => $at,
                    'actor' => $actor,
                    'teamName' => $details['teamName'] ?? ($team ? $team['name'] : ''),
                    'teamNumber' => $team ? (int) $team['team_no'] : 0,
                    'source' => 'Trò chơi',
                    'note' => ($details['game'] ?? '') . ($game_rank > 0 ? ' · Hạng ' . $game_rank : ''),
                    'points' => (int) ($details['points'] ?? 0),
                    'kind' => $game_rank > 0 ? 'award' : 'clear',
                );
                continue;
            }
            $team_id = (int) $row['entity_id'];
            $team = $team_map[$team_id] ?? null;
            $kind = $row['action'] === 'TEAM_POINTS_CLEARED' ? 'clear' : 'award';
            $items[] = array(
                'at' => $at,
                'actor' => $actor,
                'teamName' => $details['teamName'] ?? ($team ? $team['name'] : ''),
                'teamNumber' => $team ? (int) $team['team_no'] : 0,
                'source' => 'Thi đua',
                'note' => $details['category'] ?? '',
                'points' => $kind === 'clear' ? 0 : (int) ($details['points'] ?? 0),
                'kind' => $kind,
            );
        }
        return $items;
    }

    private static function actor_label($actor_id): string {
        $id = absint($actor_id);
        if (!$id) {
            return 'Hệ thống';
        }
        $user = get_userdata($id);
        return $user ? $user->display_name : ('User #' . $id);
    }

    public static function reset_awards(): void {
        global $wpdb;
        $points = MAC_Voting_DB::table('team_points');
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $points WHERE source_type IN (%s,%s)",
            self::SOURCE,
            self::LEGACY_SOURCE
        ));
    }

    public static function reset_history(): void {
        global $wpdb;
        $audit = MAC_Voting_DB::table('audit');
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $audit WHERE action IN (%s,%s,%s,%s)",
            'TEAM_POINTS_AWARDED',
            'TEAM_POINTS_CLEARED',
            'CHECKPOINT_POINTS_FINALIZED',
            'GAME_RANK_SET'
        ));
    }

    public static function seed_categories(): void {
        global $wpdb;
        $table = MAC_Voting_DB::table('thidua_rounds');
        $items = array(
            array('Gửi thông tin đội', 10, 1),
            array('Gửi demo văn nghệ', 10, 2),
        );
        $now = MAC_Voting_DB::utc_now();
        foreach ($items as $item) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table (name,points,sort_order,created_at) VALUES (%s,%d,%d,%s)",
                $item[0],
                $item[1],
                $item[2],
                $now
            ));
        }
    }
}
