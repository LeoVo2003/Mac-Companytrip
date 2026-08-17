<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Points {
    public const SOURCE = 'CATEGORY';

    public static function categories(): array {
        global $wpdb;
        $table = MAC_Voting_DB::table('point_categories');
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
            return new WP_Error('invalid', 'Tên hạng mục phải có ít nhất 2 ký tự.', array('status' => 400));
        }
        $table = MAC_Voting_DB::table('point_categories');
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE name=%s", $name));
        if ($exists) {
            return new WP_Error('duplicate', 'Hạng mục này đã tồn tại.', array('status' => 409));
        }
        $max = (int) $wpdb->get_var("SELECT MAX(sort_order) FROM $table");
        $inserted = $wpdb->insert($table, array(
            'name' => $name,
            'points' => 0,
            'sort_order' => $max + 1,
            'created_at' => MAC_Voting_DB::utc_now(),
        ), array('%s', '%d', '%d', '%s'));
        if (!$inserted) {
            return new WP_Error('db_error', 'Không thêm được hạng mục.', array('status' => 500));
        }
        $id = (int) $wpdb->insert_id;
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'POINT_CATEGORY_ADDED', 'point_category', (string) $id, array(
            'name' => $name,
        ));
        return $id;
    }

    public static function rename_category(int $category_id, string $name) {
        global $wpdb;
        $name = sanitize_text_field($name);
        if (mb_strlen($name) < 2) {
            return new WP_Error('invalid', 'Tên hạng mục phải có ít nhất 2 ký tự.', array('status' => 400));
        }
        $table = MAC_Voting_DB::table('point_categories');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $category_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Hạng mục không tồn tại.', array('status' => 404));
        }
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE name=%s AND id<>%d", $name, $category_id));
        if ($exists) {
            return new WP_Error('duplicate', 'Hạng mục này đã tồn tại.', array('status' => 409));
        }
        $wpdb->update($table, array('name' => $name), array('id' => $category_id), array('%s'), array('%d'));
        $points = MAC_Voting_DB::table('team_points');
        $wpdb->update($points, array('note' => $name), array(
            'source_type' => self::SOURCE,
            'source_id' => (string) $category_id,
        ), array('%s'), array('%s', '%s'));
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'POINT_CATEGORY_RENAMED', 'point_category', (string) $category_id, array(
            'from' => $row['name'],
            'to' => $name,
        ));
        return true;
    }

    public static function delete_category(int $category_id) {
        global $wpdb;
        $table = MAC_Voting_DB::table('point_categories');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $category_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Hạng mục không tồn tại.', array('status' => 404));
        }
        $points = MAC_Voting_DB::table('team_points');
        $wpdb->delete($points, array('source_type' => self::SOURCE, 'source_id' => (string) $category_id), array('%s', '%s'));
        $wpdb->delete($table, array('id' => $category_id), array('%d'));
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'POINT_CATEGORY_DELETED', 'point_category', (string) $category_id, array(
            'name' => $row['name'],
        ));
        return true;
    }

    public static function award(int $category_id, int $team_id, int $points) {
        global $wpdb;
        if ($points < -100 || $points > 100) {
            return new WP_Error('invalid', 'Điểm phải từ -100 đến 100.', array('status' => 400));
        }
        $categories = MAC_Voting_DB::table('point_categories');
        $teams = MAC_Voting_DB::table('teams');
        $category = $wpdb->get_row($wpdb->prepare("SELECT * FROM $categories WHERE id=%d", $category_id), ARRAY_A);
        if (!$category) {
            return new WP_Error('not_found', 'Hạng mục không tồn tại.', array('status' => 404));
        }
        $team = $wpdb->get_row($wpdb->prepare("SELECT id,name FROM $teams WHERE id=%d", $team_id), ARRAY_A);
        if (!$team) {
            return new WP_Error('not_found', 'Team không tồn tại.', array('status' => 404));
        }
        $ledger = MAC_Voting_DB::table('team_points');
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id,points FROM $ledger WHERE source_type=%s AND source_id=%s AND team_id=%d",
            self::SOURCE,
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

    public static function dashboard(): array {
        global $wpdb;
        $teams_table = MAC_Voting_DB::table('teams');
        $points_table = MAC_Voting_DB::table('team_points');
        $teams = $wpdb->get_results("SELECT id,team_no,name FROM $teams_table ORDER BY team_no", ARRAY_A) ?: array();
        $ledger = $wpdb->get_results("SELECT team_id,source_type,source_id,points FROM $points_table", ARRAY_A) ?: array();
        $by_team = array();
        foreach ($ledger as $row) {
            $team_id = (int) $row['team_id'];
            if (!isset($by_team[$team_id])) {
                $by_team[$team_id] = array('CHECKIN' => 0, 'CATEGORY' => array());
            }
            if ($row['source_type'] === 'CHECKIN') {
                $by_team[$team_id]['CHECKIN'] += (int) $row['points'];
            } elseif ($row['source_type'] === self::SOURCE) {
                $by_team[$team_id]['CATEGORY'][$row['source_id']] = (int) $row['points'];
            }
        }
        $categories = self::categories();
        $board = array();
        foreach ($teams as $team) {
            $team_id = (int) $team['id'];
            $awards = $by_team[$team_id]['CATEGORY'] ?? array();
            $checkin = (int) ($by_team[$team_id]['CHECKIN'] ?? 0);
            $category_total = 0;
            $cells = array();
            foreach ($categories as $category) {
                $current = $awards[(string) $category['id']] ?? 0;
                $category_total += $current;
                $state = $current > 0 ? 'plus' : ($current < 0 ? 'minus' : 'none');
                $cells[] = array(
                    'categoryId' => $category['id'],
                    'points' => $current,
                    'state' => $state,
                );
            }
            $board[] = array(
                'teamId' => $team_id,
                'teamNumber' => (int) $team['team_no'],
                'teamName' => $team['name'],
                'checkin' => $checkin,
                'categories' => $category_total,
                'total' => $checkin + $category_total,
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
            'categories' => $categories,
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
            "SELECT * FROM $audit WHERE action IN (%s,%s,%s) ORDER BY id DESC LIMIT 300",
            'TEAM_POINTS_AWARDED',
            'TEAM_POINTS_CLEARED',
            'CHECKPOINT_POINTS_FINALIZED'
        ), ARRAY_A) ?: array();
        $items = array();
        foreach ($rows as $row) {
            $details = json_decode($row['details_json'], true);
            if (!is_array($details)) {
                $details = array();
            }
            $actor = self::actor_label($row['actor_id']);
            $at = get_date_from_gmt($row['created_at'], 'H:i d/m/Y');
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
            $team_id = (int) $row['entity_id'];
            $team = $team_map[$team_id] ?? null;
            $kind = $row['action'] === 'TEAM_POINTS_CLEARED' ? 'clear' : 'award';
            $items[] = array(
                'at' => $at,
                'actor' => $actor,
                'teamName' => $details['teamName'] ?? ($team ? $team['name'] : ''),
                'teamNumber' => $team ? (int) $team['team_no'] : 0,
                'source' => 'Hạng mục',
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
        $wpdb->query($wpdb->prepare("DELETE FROM $points WHERE source_type=%s", self::SOURCE));
    }

    public static function seed_categories(): void {
        global $wpdb;
        $table = MAC_Voting_DB::table('point_categories');
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
