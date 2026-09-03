<?php

if (!defined('ABSPATH')) {
    exit;
}

final class MAC_Games {
    public const SOURCE = 'GAME';

    public static function games(): array {
        global $wpdb;
        $table = MAC_Voting_DB::table('games');
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order,id", ARRAY_A) ?: array();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['sort_order'] = (int) $row['sort_order'];
        }
        unset($row);
        return $rows;
    }

    public static function set_rank(int $game_id, int $team_id, int $rank) {
        global $wpdb;
        if ($rank < 0 || $rank > 6) {
            return new WP_Error('invalid', 'Hạng phải từ 0 đến 6 (0 = chưa xếp hạng).', array('status' => 400));
        }
        $games = MAC_Voting_DB::table('games');
        $teams = MAC_Voting_DB::table('teams');
        $game = $wpdb->get_row($wpdb->prepare("SELECT * FROM $games WHERE id=%d", $game_id), ARRAY_A);
        if (!$game) {
            return new WP_Error('not_found', 'Trò chơi không tồn tại.', array('status' => 404));
        }
        $team = $wpdb->get_row($wpdb->prepare("SELECT id,name,team_no FROM $teams WHERE id=%d", $team_id), ARRAY_A);
        if (!$team) {
            return new WP_Error('not_found', 'Team không tồn tại.', array('status' => 404));
        }
        if (MAC_Voting_DB::is_staff_team_no((int) $team['team_no'])) {
            return new WP_Error('staff_team', 'Team Hoa tiêu chỉ dành cho BTC, không xếp hạng trò chơi.', array('status' => 400));
        }
        $points = $rank >= 1 ? (int) MAC_Voting_DB::RANK_LADDER[$rank - 1] : 0;
        $ledger = MAC_Voting_DB::table('team_points');
        $source_id = self::SOURCE . '_' . $game_id;
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id,points FROM $ledger WHERE source_type=%s AND source_id=%s AND team_id=%d",
            self::SOURCE,
            $source_id,
            $team_id
        ), ARRAY_A);
        $now = MAC_Voting_DB::utc_now();
        // v1.9.16: rank 0 (Chưa xếp) mới xóa record; Hạng 6 = 0đ phải giữ record explicit
        // để bảng không nhầm Hạng 6 thành "Chưa xếp".
        if ($rank === 0) {
            if ($existing) {
                $wpdb->delete($ledger, array('id' => (int) $existing['id']), array('%d'));
            }
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'GAME_RANK_SET', 'team', (string) $team_id, array(
                'gameId' => $game_id,
                'game' => $game['name'],
                'teamName' => $team['name'],
                'rank' => 0,
                'points' => 0,
                'previous' => $existing ? (int) $existing['points'] : 0,
            ));
            return true;
        }
        $payload = array(
            'team_id' => $team_id,
            'source_type' => self::SOURCE,
            'source_id' => $source_id,
            'points' => $points,
            'note' => $game['name'] . ' · Hạng ' . $rank,
            'created_by' => get_current_user_id(),
            'updated_at' => $now,
        );
        if ($existing) {
            $wpdb->update($ledger, $payload, array('id' => (int) $existing['id']));
        } else {
            $payload['created_at'] = $now;
            $wpdb->insert($ledger, $payload);
        }
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'GAME_RANK_SET', 'team', (string) $team_id, array(
            'gameId' => $game_id,
            'game' => $game['name'],
            'teamName' => $team['name'],
            'rank' => $rank,
            'points' => $points,
            'previous' => $existing ? (int) $existing['points'] : 0,
        ));
        return true;
    }

    public static function rank_from_points(int $points): int {
        $index = array_search($points, MAC_Voting_DB::RANK_LADDER, true);
        return $index === false ? 0 : $index + 1;
    }

    public static function board(): array {
        global $wpdb;
        $games = self::games();
        $teams = $wpdb->get_results($wpdb->prepare(
            'SELECT id,team_no,name FROM ' . MAC_Voting_DB::table('teams') . ' WHERE team_no<>%d ORDER BY team_no',
            MAC_Voting_DB::STAFF_TEAM_NO
        ), ARRAY_A) ?: array();
        $ledger = $wpdb->get_results($wpdb->prepare(
            "SELECT team_id,source_id,points FROM " . MAC_Voting_DB::table('team_points') . " WHERE source_type=%s",
            self::SOURCE
        ), ARRAY_A) ?: array();
        $map = array();
        foreach ($ledger as $row) {
            $map[(int) $row['team_id']][$row['source_id']] = (int) $row['points'];
        }
        $board = array();
        foreach ($teams as $team) {
            $team_id = (int) $team['id'];
            $cells = array();
            $total = 0;
            foreach ($games as $game) {
                $source_id = self::SOURCE . '_' . $game['id'];
                $has_rank = isset($map[$team_id][$source_id]);
                $points = $has_rank ? (int) $map[$team_id][$source_id] : 0;
                $total += $points;
                $cells[] = array(
                    'gameId' => $game['id'],
                    'points' => $points,
                    'rank' => $has_rank ? self::rank_from_points($points) : 0,
                );
            }
            $board[] = array(
                'teamId' => $team_id,
                'teamNumber' => (int) $team['team_no'],
                'teamName' => $team['name'],
                'cells' => $cells,
                'total' => $total,
            );
        }
        return $board;
    }

    /** Xóa hạng/điểm của 3 game, giữ nguyên danh sách game để chấm lại nhanh. */
    public static function reset_ranks(): bool {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM " . MAC_Voting_DB::table('team_points') . " WHERE source_type=%s",
            self::SOURCE
        )) !== false;
    }
}
