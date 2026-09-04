<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Module phân xe + điểm danh trên xe, chạy SONG SONG và ĐỘC LẬP với engine check-in.
 * - Chỉ Trạm 1 (checkpoint id 1) mới auto-assign xe khi quét QR, theo xe đang BOARDING trên server.
 * - Roll-call (điểm danh trên xe) không ghi vào bảng checkins và không ảnh hưởng điểm.
 */
final class MAC_Bus {
    public const ROLE = 'mac_bus_guide';
    public const CAP_ROLLCALL = 'mac_bus_rollcall';
    public const BUS_META = 'mac_bus_id';
    public const FIRST_CHECKPOINT_ID = 1;
    public const BUS_COUNT = 5;

    public static function register_roles(): void {
        $role = get_role(self::ROLE);
        if (!$role) {
            add_role(self::ROLE, 'MAC HDV Xe (Vietravel)', array(
                'read' => true,
                MAC_Checkin::CAP => true,
                self::CAP_ROLLCALL => true,
            ));
            return;
        }
        $role->add_cap(MAC_Checkin::CAP);
        $role->add_cap(self::CAP_ROLLCALL);
    }

    public static function is_guide(): bool {
        return current_user_can(self::CAP_ROLLCALL) && !MAC_Checkin::is_super();
    }

    public static function guide_bus_id(int $user_id): int {
        return (int) get_user_meta($user_id, self::BUS_META, true);
    }

    public static function can_manage(): bool {
        return MAC_Checkin::is_super();
    }

    public static function can_rollcall(int $bus_id): bool {
        if ($bus_id <= 0) {
            return false;
        }
        if (MAC_Checkin::is_super()) {
            return true;
        }
        // HDV chỉ điểm danh đúng xe mình; BTC (có quyền quét) được kiểm soát cùng HDV mọi xe.
        if (self::is_guide()) {
            return self::guide_bus_id(get_current_user_id()) === $bus_id;
        }
        return current_user_can(MAC_Checkin::CAP);
    }

    /** BTC/Hoa tiêu chỉ được tự pick mình (người team 7) vào xe — không đụng nhân viên QR. */
    public static function voter_is_staff(int $voter_id): bool {
        global $wpdb;
        $team_no = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT t.team_no FROM ' . MAC_Voting_DB::table('voters') . ' v JOIN ' . MAC_Voting_DB::table('teams') . ' t ON t.id=v.team_id WHERE v.id=%d',
            $voter_id
        ));
        return MAC_Voting_DB::is_staff_team_no($team_no);
    }

    public static function buses(): array {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . MAC_Voting_DB::table('buses') . ' ORDER BY sort_order', ARRAY_A) ?: array();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['sort_order'] = (int) $row['sort_order'];
            $row['capacity'] = (int) $row['capacity'];
        }
        unset($row);
        return $rows;
    }

    public static function boarding_bus(): ?array {
        foreach (self::buses() as $bus) {
            if ($bus['status'] === 'BOARDING') {
                return $bus;
            }
        }
        return null;
    }

    /** Chỉ bật phân xe khi Trạm 1 đang mở và đợt phân xe chưa hoàn tất (chưa chốt đủ 5 xe). */
    public static function assignment_enabled(): bool {
        $active = MAC_Checkin::active_checkpoint();
        if (!$active || (int) $active['id'] !== self::FIRST_CHECKPOINT_ID) {
            return false;
        }
        $buses = self::buses();
        if (!$buses) {
            return false;
        }
        foreach ($buses as $bus) {
            if ($bus['status'] !== 'CLOSED') {
                return true;
            }
        }
        return false;
    }

    public static function bus_counts(int $bus_id): array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(CASE WHEN member_type='EMPLOYEE' THEN 1 ELSE 0 END) AS employees,
                SUM(CASE WHEN member_type='COMPANION' THEN 1 ELSE 0 END) AS companions,
                SUM(CASE WHEN member_type<>'EMPLOYEE' THEN 1 ELSE 0 END) AS staff
             FROM " . MAC_Voting_DB::table('bus_members') . ' WHERE bus_id=%d',
            $bus_id
        ), ARRAY_A);
        $employees = (int) ($row['employees'] ?? 0);
        $companions = (int) ($row['companions'] ?? 0);
        $staff = max(0, (int) ($row['staff'] ?? 0) - $companions);
        return array('employees' => $employees, 'companions' => $companions, 'staff' => $staff, 'total' => $employees + $companions + $staff);
    }

    /**
     * Nhịp tự động của đợt phân xe: xe nào (BOARDING hoặc WAITING) chạm sức chứa tối đa
     * sẽ tự chốt; nếu không còn xe nào nhận người thì mở xe WAITING đầu tiên.
     * Super Admin vẫn đóng/mở từng xe thủ công bằng close_bus/open_bus khi cần.
     */
    public static function sync_boarding(): void {
        if (!self::assignment_enabled()) {
            return;
        }
        global $wpdb;
        $now = MAC_Voting_DB::utc_now();
        foreach (self::buses() as $bus) {
            if ($bus['status'] === 'CLOSED') {
                continue;
            }
            $capacity = max(1, $bus['capacity']);
            if (self::bus_counts($bus['id'])['total'] >= $capacity) {
                $wpdb->update(
                    MAC_Voting_DB::table('buses'),
                    array('status' => 'CLOSED', 'closed_at' => $now, 'updated_at' => $now),
                    array('id' => $bus['id']),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
                MAC_Voting_DB::audit('SYSTEM', (string) get_current_user_id(), 'BUS_AUTO_CLOSED', 'bus', (string) $bus['id'], array('busName' => $bus['name'], 'capacity' => $capacity));
            }
        }
        if (!self::boarding_bus()) {
            self::open_first_waiting();
        }
    }

    /** Mở xe WAITING đầu tiên — thay nút "Mở Xe 1" cũ: lượt quét/headline dashboard tự kích hoạt. */
    private static function open_first_waiting(): void {
        global $wpdb;
        foreach (self::buses() as $bus) {
            if ($bus['status'] !== 'WAITING') {
                continue;
            }
            $wpdb->update(
                MAC_Voting_DB::table('buses'),
                array('status' => 'BOARDING', 'opened_at' => MAC_Voting_DB::utc_now(), 'updated_at' => MAC_Voting_DB::utc_now()),
                array('id' => $bus['id']),
                array('%s', '%s', '%s'),
                array('%d')
            );
            MAC_Voting_DB::audit('SYSTEM', (string) get_current_user_id(), 'BUS_AUTO_OPENED', 'bus', (string) $bus['id'], array('busName' => $bus['name']));
            return;
        }
    }

    /** Super Admin chốt sớm một xe (kể cả chưa đầy); xe kế trong hàng WAITING sẽ tự mở. */
    public static function close_bus(int $bus_id) {
        global $wpdb;
        $bus = null;
        foreach (self::buses() as $row) {
            if ((int) $row['id'] === $bus_id) {
                $bus = $row;
            }
        }
        if (!$bus) {
            return new WP_Error('not_found', 'Xe không tồn tại.', array('status' => 404));
        }
        if ($bus['status'] === 'CLOSED') {
            return self::admin_state();
        }
        $now = MAC_Voting_DB::utc_now();
        $wpdb->update(
            MAC_Voting_DB::table('buses'),
            array('status' => 'CLOSED', 'closed_at' => $now, 'updated_at' => $now),
            array('id' => $bus_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'BUS_CLOSED_MANUAL', 'bus', (string) $bus_id, array('busName' => $bus['name']));
        return self::admin_state();
    }

    /**
     * Super Admin mở xe thủ công: xe WAITING thành BOARDING (xe đang BOARDING khác trả về
     * hàng WAITING); xe CLOSED trở lại hàng WAITING để chờ mở.
     */
    public static function open_bus(int $bus_id) {
        global $wpdb;
        $bus = null;
        foreach (self::buses() as $row) {
            if ((int) $row['id'] === $bus_id) {
                $bus = $row;
            }
        }
        if (!$bus) {
            return new WP_Error('not_found', 'Xe không tồn tại.', array('status' => 404));
        }
        $now = MAC_Voting_DB::utc_now();
        if ($bus['status'] === 'WAITING') {
            $current = self::boarding_bus();
            $wpdb->query('START TRANSACTION');
            if ($current && (int) $current['id'] !== $bus_id) {
                $wpdb->update(
                    MAC_Voting_DB::table('buses'),
                    array('status' => 'WAITING', 'updated_at' => $now),
                    array('id' => (int) $current['id']),
                    array('%s', '%s'),
                    array('%d')
                );
            }
            $opened = $wpdb->update(
                MAC_Voting_DB::table('buses'),
                array('status' => 'BOARDING', 'opened_at' => $now, 'updated_at' => $now),
                array('id' => $bus_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            if ($opened === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', 'Không mở được xe.', array('status' => 500));
            }
            $wpdb->query('COMMIT');
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'BUS_OPENED_MANUAL', 'bus', (string) $bus_id, array(
                'busName' => $bus['name'],
                'swappedFrom' => $current && (int) $current['id'] !== $bus_id ? $current['name'] : null,
            ));
            return self::admin_state();
        }
        if ($bus['status'] === 'CLOSED') {
            $wpdb->update(
                MAC_Voting_DB::table('buses'),
                array('status' => 'WAITING', 'opened_at' => null, 'closed_at' => null, 'updated_at' => $now),
                array('id' => $bus_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
            MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'BUS_REOPENED', 'bus', (string) $bus_id, array('busName' => $bus['name']));
            return self::admin_state();
        }
        return self::admin_state();
    }

    /** Super Admin đặt sức chứa tối đa từng xe; chạm ngưỡng là xe tự chốt theo sync_boarding. */
    public static function save_capacity(int $bus_id, int $capacity) {
        global $wpdb;
        $capacity = max(1, min(500, $capacity));
        $bus = null;
        foreach (self::buses() as $row) {
            if ((int) $row['id'] === $bus_id) {
                $bus = $row;
            }
        }
        if (!$bus) {
            return new WP_Error('not_found', 'Xe không tồn tại.', array('status' => 404));
        }
        $wpdb->update(
            MAC_Voting_DB::table('buses'),
            array('capacity' => $capacity, 'updated_at' => MAC_Voting_DB::utc_now()),
            array('id' => $bus_id),
            array('%d', '%s'),
            array('%d')
        );
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'BUS_CAPACITY_SET', 'bus', (string) $bus_id, array('busName' => $bus['name'], 'capacity' => $capacity));
        return self::admin_state();
    }

    public static function voter_assignment(int $voter_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT m.*, b.name AS bus_name, b.sort_order AS bus_no
             FROM ' . MAC_Voting_DB::table('bus_members') . ' m
             JOIN ' . MAC_Voting_DB::table('buses') . " b ON b.id=m.bus_id
             WHERE m.voter_id=%d",
            $voter_id
        ), ARRAY_A);
        if (!$row) {
            return null;
        }
        $party = self::party_voters($voter_id);
        return array(
            'assigned' => true,
            'busId' => (int) $row['bus_id'],
            'busName' => (string) $row['bus_name'],
            'busNo' => (int) $row['bus_no'],
            'source' => (string) $row['assigned_source'],
            'partySize' => count($party),
            'companions' => array_values(array_map(static function(array $member): string { return (string) $member['full_name']; }, array_filter($party, static function(array $member) use ($voter_id): bool { return (int) $member['id'] !== $voter_id; }))),
        );
    }

    /** Người có QR và toàn bộ người đi kèm được xem là một nhóm không tách xe. */
    public static function party_voters(int $voter_id): array {
        global $wpdb;
        $voters = MAC_Voting_DB::table('voters');
        $primary_id = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(primary_voter_id,id) FROM $voters WHERE id=%d", $voter_id));
        if (!$primary_id) return array();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id,full_name,status,primary_voter_id,bus_rider FROM $voters WHERE id=%d OR (primary_voter_id=%d AND status='COMPANION') ORDER BY CASE WHEN id=%d THEN 0 ELSE 1 END,import_order,id",
            $primary_id,
            $primary_id,
            $primary_id
        ), ARRAY_A) ?: array();
    }

    /** Server tự gán xe đang BOARDING khi check-in Trạm 1; không bao giờ làm hỏng lượt check-in. */
    public static function auto_assign(int $voter_id): ?array {
        if (!self::assignment_enabled()) {
            return null;
        }
        $existing = self::voter_assignment($voter_id);
        if ($existing) {
            return $existing;
        }
        $party = self::party_voters($voter_id);
        if (!$party) return array('assigned' => false, 'reason' => 'VOTER_NOT_FOUND');
        // self_rider_override: người đánh dấu tự túc (Đi xe = Không) mà vẫn quét QR Trạm 1 = đổi ý đi xe:
        // nhận đúng người quét lên xe (1 chỗ), không kéo theo người nhà tự túc không quét.
        $scanned = null;
        foreach ($party as $member) {
            if ((int) $member['id'] === $voter_id) { $scanned = $member; break; }
        }
        if ($scanned && (int) ($scanned['bus_rider'] ?? 1) === 0) {
            $party = array($scanned);
        }
        $party_size = count($party);
        // Đồng bộ trước khi chọn xe: xe đầy đã chốt tự chuyển sang xe kế đang chờ.
        self::sync_boarding();
        $bus = self::boarding_bus();
        if (!$bus) {
            return array('assigned' => false, 'reason' => 'NO_BUS_BOARDING');
        }
        global $wpdb;
        $now = MAC_Voting_DB::utc_now();
        // Tìm xe đầu tiên (từ xe đang nhận trở đi) chứa TRỌN nhóm — nhóm không bao giờ bị tách.
        // Các xe lướt qua vì thiếu chỗ sẽ chốt; nếu không xe nào đủ chỗ thì giữ nguyên trạng thái
        // (chỗ lẻ còn lại vẫn dành cho người quét riêng) và báo lỗi rõ ràng.
        $target = null;
        $skipped = array();
        foreach (self::buses() as $candidate) {
            if ($candidate['status'] === 'CLOSED' || (int) $candidate['sort_order'] < (int) $bus['sort_order']) {
                continue;
            }
            $current_total = self::bus_counts((int) $candidate['id'])['total'];
            if ($current_total + $party_size <= (int) $candidate['capacity']) {
                $target = $candidate;
                break;
            }
            $skipped[] = $candidate;
        }
        if (!$target) {
            return array('assigned' => false, 'reason' => 'NO_ROOM_FOR_PARTY', 'partySize' => $party_size);
        }
        foreach ($skipped as $skip) {
            $remaining = max(0, (int) $skip['capacity'] - self::bus_counts((int) $skip['id'])['total']);
            $wpdb->update(
                MAC_Voting_DB::table('buses'),
                array('status' => 'CLOSED', 'closed_at' => $now, 'updated_at' => $now),
                array('id' => (int) $skip['id']),
                array('%s', '%s', '%s'),
                array('%d')
            );
            MAC_Voting_DB::audit('SYSTEM', (string) get_current_user_id(), 'BUS_CLOSED_FOR_PARTY', 'bus', (string) $skip['id'], array('partySize' => $party_size, 'remainingSeats' => $remaining));
        }
        if ($target['status'] !== 'BOARDING') {
            $wpdb->update(
                MAC_Voting_DB::table('buses'),
                array('status' => 'BOARDING', 'opened_at' => $now, 'updated_at' => $now),
                array('id' => (int) $target['id']),
                array('%s', '%s', '%s'),
                array('%d')
            );
        }
        $bus = $target;
        $wpdb->query('START TRANSACTION');
        $added = 0;
        foreach ($party as $member) {
            $inserted = $wpdb->insert(
                MAC_Voting_DB::table('bus_members'),
                array(
                    'bus_id' => (int) $bus['id'],
                    'voter_id' => (int) $member['id'],
                    'member_type' => (int) $member['id'] === $voter_id ? 'EMPLOYEE' : 'COMPANION',
                    'manual_name' => null,
                    'assigned_source' => 'CHECKIN',
                    'assigned_by' => get_current_user_id(),
                    'assigned_at' => $now,
                    'updated_at' => $now,
                ),
                array('%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s')
            );
            if ($inserted) $added++;
        }
        if ($added !== $party_size) {
            $wpdb->query('ROLLBACK');
            return self::voter_assignment($voter_id) ?: array('assigned' => false, 'reason' => 'DB_ERROR');
        }
        $wpdb->query('COMMIT');
        $companion_names = array_values(array_map(static function(array $member): string { return MAC_Voting_DB::title_case((string) $member['full_name']); }, array_slice($party, 1)));
        MAC_Voting_DB::audit('SYSTEM', (string) get_current_user_id(), 'BUS_PARTY_ASSIGNED', 'voter', (string) $voter_id, array('busId' => (int) $bus['id'], 'busName' => $bus['name'], 'partySize' => $party_size, 'companions' => $companion_names));
        // Đủ sức chứa thì chốt xe này và mở xe kế cho người tiếp theo.
        self::sync_boarding();
        return array(
            'assigned' => true,
            'busId' => (int) $bus['id'],
            'busName' => (string) $bus['name'],
            'busNo' => (int) $bus['sort_order'],
            'source' => 'CHECKIN',
            'partySize' => $party_size,
            'companions' => $companion_names,
        );
    }

    /** Gán thủ công (người chưa phân xe, hoặc BTC/Hoa tiêu có voter record). */
    public static function assign_voter(int $voter_id, int $bus_id, string $source = 'MANUAL') {
        global $wpdb;
        $bus = null;
        foreach (self::buses() as $row) {
            if ((int) $row['id'] === $bus_id) {
                $bus = $row;
            }
        }
        if (!$bus) {
            return new WP_Error('not_found', 'Xe không tồn tại.', array('status' => 404));
        }
        $voter = $wpdb->get_row($wpdb->prepare(
            'SELECT v.id,v.full_name,t.team_no FROM ' . MAC_Voting_DB::table('voters') . ' v JOIN ' . MAC_Voting_DB::table('teams') . ' t ON t.id=v.team_id WHERE v.id=%d',
            $voter_id
        ), ARRAY_A);
        if (!$voter) {
            return new WP_Error('voter_not_found', 'Không tìm thấy nhân sự.', array('status' => 404));
        }
        $type = MAC_Voting_DB::is_staff_team_no((int) $voter['team_no']) ? 'STAFF' : 'EMPLOYEE';
        $now = MAC_Voting_DB::utc_now();
        $existing = $wpdb->get_var($wpdb->prepare('SELECT bus_id FROM ' . MAC_Voting_DB::table('bus_members') . ' WHERE voter_id=%d', $voter_id));
        if ($existing !== null) {
            if ((int) $existing === $bus_id) {
                return self::admin_state();
            }
            return self::move_voter($voter_id, $bus_id);
        }
        $party = $type === 'STAFF' ? array($voter) : self::party_voters($voter_id);
        $wpdb->query('START TRANSACTION');
        foreach ($party as $member) {
            $member_type = $type === 'STAFF' ? 'STAFF' : ((int) $member['id'] === $voter_id ? 'EMPLOYEE' : 'COMPANION');
            $saved = $wpdb->insert(
                MAC_Voting_DB::table('bus_members'),
                array(
                    'bus_id' => $bus_id,
                    'voter_id' => (int) $member['id'],
                    'member_type' => $member_type,
                    'manual_name' => null,
                    'assigned_source' => $source,
                    'assigned_by' => get_current_user_id(),
                    'assigned_at' => $now,
                    'updated_at' => $now,
                ),
                array('%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s')
            );
            if (!$saved) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', 'Không thể gán trọn nhóm vào xe.', array('status' => 500));
            }
        }
        $wpdb->query('COMMIT');
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), $type === 'STAFF' ? 'BUS_MEMBER_MANUAL_ADDED' : 'BUS_PARTY_ASSIGNED', 'voter', (string) $voter_id, array('busId' => $bus_id, 'busName' => $bus['name'], 'partySize' => count($party)));
        return self::admin_state();
    }

    public static function add_manual_person(int $bus_id, string $name) {
        global $wpdb;
        $name = mb_substr(trim(wp_strip_all_tags($name)), 0, 190, 'UTF-8');
        if ($name === '') {
            return new WP_Error('name_required', 'Cần nhập họ tên.', array('status' => 400));
        }
        $bus = null;
        foreach (self::buses() as $row) {
            if ((int) $row['id'] === $bus_id) {
                $bus = $row;
            }
        }
        if (!$bus) {
            return new WP_Error('not_found', 'Xe không tồn tại.', array('status' => 404));
        }
        $now = MAC_Voting_DB::utc_now();
        $wpdb->insert(
            MAC_Voting_DB::table('bus_members'),
            array(
                'bus_id' => $bus_id,
                'voter_id' => null,
                'member_type' => 'MANUAL',
                'manual_name' => $name,
                'assigned_source' => 'MANUAL',
                'assigned_by' => get_current_user_id(),
                'assigned_at' => $now,
                'updated_at' => $now,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'BUS_MEMBER_MANUAL_ADDED', 'bus', (string) $bus_id, array('manualName' => $name));
        return self::admin_state();
    }

    /** Chuyển xe theo member id — dùng cho cả người thêm thủ công (không có voter_id). */
    public static function move_member_by_id(int $member_id, int $to_bus_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MAC_Voting_DB::table('bus_members') . ' WHERE id=%d', $member_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Không còn thành viên này.', array('status' => 404));
        }
        if ($row['voter_id'] !== null) {
            return self::move_voter((int) $row['voter_id'], $to_bus_id);
        }
        if ((int) $row['bus_id'] === $to_bus_id) {
            return self::admin_state();
        }
        $to = null;
        $from_name = '';
        foreach (self::buses() as $bus) {
            if ((int) $bus['id'] === $to_bus_id) {
                $to = $bus;
            }
            if ((int) $bus['id'] === (int) $row['bus_id']) {
                $from_name = (string) $bus['name'];
            }
        }
        if (!$to) {
            return new WP_Error('not_found', 'Xe đích không tồn tại.', array('status' => 404));
        }
        $now = MAC_Voting_DB::utc_now();
        $wpdb->update(
            MAC_Voting_DB::table('bus_members'),
            array('bus_id' => $to_bus_id, 'assigned_source' => 'MOVED', 'assigned_by' => get_current_user_id(), 'updated_at' => $now),
            array('id' => $member_id),
            array('%d', '%s', '%d', '%s'),
            array('%d')
        );
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'BUS_MEMBER_MOVED', 'bus_member', (string) $member_id, array(
            'fromBus' => $from_name,
            'toBus' => $to['name'],
            'voterId' => $row['voter_id'] !== null ? (int) $row['voter_id'] : null,
            'manualName' => $row['manual_name'],
        ));
        return self::admin_state();
    }

    /** Danh sách voter_id đã nằm trong một xe — để ẩn khỏi các danh sách chọn thêm ở xe khác. */
    public static function assigned_voter_ids(): array {
        global $wpdb;
        $ids = $wpdb->get_col('SELECT voter_id FROM ' . MAC_Voting_DB::table('bus_members') . ' WHERE voter_id IS NOT NULL');
        return array_map('intval', $ids ?: array());
    }

    /** Super Admin: khởi tạo lại toàn bộ đợt phân xe (về WAITING, xóa member + roll-call). */
    public static function reset_assignment() {
        global $wpdb;
        $now = MAC_Voting_DB::utc_now();
        $wpdb->query('START TRANSACTION');
        $wpdb->query($wpdb->prepare('UPDATE ' . MAC_Voting_DB::table('buses') . " SET status='WAITING', opened_at=NULL, closed_at=NULL, updated_at=%s", $now));
        $wpdb->query('DELETE FROM ' . MAC_Voting_DB::table('bus_rollcall_marks'));
        $wpdb->query('DELETE FROM ' . MAC_Voting_DB::table('bus_rollcalls'));
        $wpdb->query('DELETE FROM ' . MAC_Voting_DB::table('bus_members'));
        $wpdb->query('COMMIT');
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'BUS_RESET', 'bus', null, array());
        return self::admin_state();
    }

    public static function move_voter(int $voter_id, int $to_bus_id) {
        global $wpdb;
        $from = self::voter_assignment($voter_id);
        if (!$from) {
            return new WP_Error('not_assigned', 'Người này chưa ở xe nào.', array('status' => 409));
        }
        if ((int) $from['busId'] === $to_bus_id) {
            return self::admin_state();
        }
        $to = null;
        foreach (self::buses() as $row) {
            if ((int) $row['id'] === $to_bus_id) {
                $to = $row;
            }
        }
        if (!$to) {
            return new WP_Error('not_found', 'Xe đích không tồn tại.', array('status' => 404));
        }
        $now = MAC_Voting_DB::utc_now();
        $party_ids = array_map('intval', array_column(self::party_voters($voter_id), 'id'));
        if (!$party_ids) $party_ids = array($voter_id);
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . MAC_Voting_DB::table('bus_members') . ' SET bus_id=%d,assigned_source=%s,assigned_by=%d,updated_at=%s WHERE voter_id IN (' . implode(',', $party_ids) . ')',
            $to_bus_id,
            'MOVED',
            get_current_user_id(),
            $now
        ));
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'BUS_MEMBER_MOVED', 'voter', (string) $voter_id, array(
            'fromBus' => $from['busName'],
            'toBus' => $to['name'],
            'partySize' => count($party_ids),
        ));
        return self::admin_state();
    }

    public static function remove_member(int $member_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MAC_Voting_DB::table('bus_members') . ' WHERE id=%d', $member_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Không còn thành viên này.', array('status' => 404));
        }
        $removed_count = 1;
        if ($row['voter_id'] !== null) {
            $party_ids = array_map('intval', array_column(self::party_voters((int) $row['voter_id']), 'id'));
            if ($party_ids) {
                $removed_count = (int) $wpdb->query('DELETE FROM ' . MAC_Voting_DB::table('bus_members') . ' WHERE voter_id IN (' . implode(',', $party_ids) . ')');
            } else {
                $wpdb->delete(MAC_Voting_DB::table('bus_members'), array('id' => $member_id), array('%d'));
            }
        } else {
            $wpdb->delete(MAC_Voting_DB::table('bus_members'), array('id' => $member_id), array('%d'));
        }
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'BUS_MEMBER_REMOVED', 'bus_member', (string) $member_id, array(
            'busId' => (int) $row['bus_id'],
            'voterId' => $row['voter_id'] !== null ? (int) $row['voter_id'] : null,
            'manualName' => $row['manual_name'],
            'removedCount' => $removed_count,
        ));
        return self::admin_state();
    }

    public static function manifest(int $bus_id): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT m.id,m.voter_id,m.member_type,m.manual_name,m.assigned_source,v.full_name,v.birth_year,v.gender,v.citizen_id,v.phone,v.room_type,v.room_no,v.room_group,v.email,v.note,v.primary_voter_id,v.import_order,t.team_no,t.name AS team_name
             FROM ' . MAC_Voting_DB::table('bus_members') . ' m
             LEFT JOIN ' . MAC_Voting_DB::table('voters') . ' v ON v.id=m.voter_id
             LEFT JOIN ' . MAC_Voting_DB::table('teams') . ' t ON t.id=v.team_id
             WHERE m.bus_id=%d
             ORDER BY m.member_type DESC, v.full_name, m.manual_name',
            $bus_id
        ), ARRAY_A) ?: array();
        $items = array();
        foreach ($rows as $row) {
            $items[] = array(
                'id' => (int) $row['id'],
                'voterId' => $row['voter_id'] !== null ? (int) $row['voter_id'] : null,
                'name' => MAC_Voting_DB::title_case((string) ($row['full_name'] ?? $row['manual_name'])),
                'memberType' => (string) $row['member_type'],
                'source' => (string) $row['assigned_source'],
                'teamNo' => $row['team_no'] !== null ? (int) $row['team_no'] : null,
                'teamName' => $row['team_name'],
                'birthYear' => (string) ($row['birth_year'] ?? ''),
                'gender' => (string) ($row['gender'] ?? ''),
                'citizenId' => (string) ($row['citizen_id'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'roomType' => (string) ($row['room_type'] ?? ''),
                'roomNo' => (string) ($row['room_no'] ?? ''),
                'roomGroup' => (string) ($row['room_group'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'note' => (string) ($row['note'] ?? ''),
                'primaryVoterId' => $row['primary_voter_id'] !== null ? (int) $row['primary_voter_id'] : null,
                'importOrder' => $row['import_order'] !== null ? (int) $row['import_order'] : null,
            );
        }
        return $items;
    }

    /** Người đã check-in Trạm 1 nhưng chưa có xe — để Super Admin gán thủ công. */
    public static function unassigned(): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT DISTINCT v.id,v.full_name,t.team_no,t.name AS team_name
             FROM ' . MAC_Voting_DB::table('checkins') . ' c
             JOIN ' . MAC_Voting_DB::table('voters') . ' v ON v.id=c.voter_id
             JOIN ' . MAC_Voting_DB::table('teams') . ' t ON t.id=v.team_id
             LEFT JOIN ' . MAC_Voting_DB::table('bus_members') . ' m ON m.voter_id=v.id
             WHERE c.checkpoint_id=%d AND m.id IS NULL AND v.bus_rider=1
             ORDER BY v.full_name',
            self::FIRST_CHECKPOINT_ID
        ), ARRAY_A) ?: array();
        return array_map(static function(array $row): array {
            return array(
                'voterId' => (int) $row['id'],
                'name' => MAC_Voting_DB::title_case((string) $row['full_name']),
                'teamNo' => (int) $row['team_no'],
                'teamName' => (string) $row['team_name'],
            );
        }, $rows);
    }

    /** Người tự túc (Đi xe = Không) chưa nằm xe nào — Super Admin gán tay sau khi chốt phân xe Trạm 1. */
    public static function self_arrange_list(): array {
        global $wpdb;
        $voters = MAC_Voting_DB::table('voters');
        $teams = MAC_Voting_DB::table('teams');
        $assigned = array_flip(self::assigned_voter_ids());
        $rows = $wpdb->get_results("SELECT v.id,v.full_name,v.status,v.primary_voter_id,t.team_no,t.name AS team_name
            FROM $voters v JOIN $teams t ON t.id=v.team_id
            WHERE v.bus_rider=0 AND v.status IN ('ACTIVE','COMPANION')
            ORDER BY v.import_order,v.id", ARRAY_A) ?: array();
        $in_list = array();
        foreach ($rows as $r) $in_list[(int) $r['id']] = true;
        $comp_counts = array();
        foreach ($wpdb->get_results("SELECT primary_voter_id AS pid, COUNT(*) AS c FROM $voters WHERE status='COMPANION' AND bus_rider=0 AND primary_voter_id IS NOT NULL GROUP BY primary_voter_id", ARRAY_A) ?: array() as $c) {
            $comp_counts[(int) $c['pid']] = (int) $c['c'];
        }
        $items = array();
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if (isset($assigned[$id])) continue;
            $primary_id = (int) ($r['primary_voter_id'] ?? 0);
            // Người đi kèm mà người chính cũng đang chờ gán: ẩn vì gán người chính là kéo cả nhóm.
            if ((string) $r['status'] === 'COMPANION' && $primary_id && isset($in_list[$primary_id]) && !isset($assigned[$primary_id])) {
                continue;
            }
            $items[] = array(
                'voterId' => $id,
                'name' => MAC_Voting_DB::title_case((string) $r['full_name']),
                'teamNo' => (int) $r['team_no'],
                'teamName' => (string) $r['team_name'],
                'companions' => (string) $r['status'] === 'COMPANION' ? 0 : (int) ($comp_counts[$id] ?? 0),
            );
        }
        return $items;
    }

    public static function guides(): array {
        $users = get_users(array('role' => self::ROLE, 'orderby' => 'display_name'));
        $items = array();
        foreach ($users as $user) {
            $items[] = array(
                'id' => (int) $user->ID,
                'name' => $user->display_name,
                'login' => $user->user_login,
                'busId' => self::guide_bus_id((int) $user->ID),
            );
        }
        return $items;
    }

    public static function save_guide(string $name, string $login, string $password, int $bus_id) {
        $login = sanitize_user($login, true);
        if ($login === '') {
            return new WP_Error('invalid_login', 'Username HDV không hợp lệ.', array('status' => 400));
        }
        if ($bus_id < 1 || $bus_id > self::BUS_COUNT) {
            return new WP_Error('invalid_bus', 'Xe phụ trách không hợp lệ.', array('status' => 400));
        }
        // Login dashboard đi theo email công ty: hdv.xe1 => hdv.xe1@macusaone.com.
        $email = MAC_Voting_DB::normalize_company_email($login . '@' . MAC_Voting_DB::COMPANY_EMAIL_DOMAIN);
        $pass = trim($password) !== '' ? trim($password) : MAC_Voting_DB::DEFAULT_STAFF_PASSWORD;
        $user = get_user_by('login', $login);
        if (!$user && $email) {
            $user = get_user_by('email', $email);
        }
        if ($user) {
            if (!in_array(self::ROLE, (array) $user->roles, true)) {
                $user->add_role(self::ROLE);
            }
            wp_set_password($pass, $user->ID);
            if ($email && strcasecmp((string) $user->user_email, $email) !== 0 && !get_user_by('email', $email)) {
                wp_update_user(array('ID' => (int) $user->ID, 'user_email' => $email));
            }
            $old_bus = self::guide_bus_id((int) $user->ID);
            update_user_meta($user->ID, self::BUS_META, $bus_id);
            if ($old_bus !== $bus_id) {
                MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'BUS_GUIDE_BUS_CHANGED', 'user', (string) $user->ID, array('fromBus' => $old_bus, 'toBus' => $bus_id));
            }
            return array('created' => false, 'login' => $login, 'password' => $pass, 'busId' => $bus_id, 'name' => $name !== '' ? $name : $user->display_name);
        }
        $display = $name !== '' ? MAC_Voting_DB::title_case($name) : ('HDV Xe ' . $bus_id);
        $user_id = wp_insert_user(array(
            'user_login' => $login,
            'user_email' => $email ?: ($login . '@' . MAC_Voting_DB::COMPANY_EMAIL_DOMAIN),
            'user_pass' => $pass,
            'display_name' => $display,
            'role' => self::ROLE,
        ));
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        update_user_meta((int) $user_id, self::BUS_META, $bus_id);
        MAC_Voting_DB::audit('ADMIN', (string) get_current_user_id(), 'BUS_GUIDE_CREATED', 'user', (string) $user_id, array('login' => $login, 'busId' => $bus_id));
        return array('created' => true, 'login' => $login, 'password' => $pass, 'busId' => $bus_id, 'name' => $display);
    }

    /* ------------------------------ Roll-call ------------------------------ */

    public static function current_rollcall(int $bus_id): ?array {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . MAC_Voting_DB::table('bus_rollcalls') . ' WHERE bus_id=%d ORDER BY sequence_no DESC LIMIT 1',
            $bus_id
        ), ARRAY_A);
    }

    public static function new_rollcall(int $bus_id) {
        global $wpdb;
        if (!self::can_rollcall($bus_id)) {
            return new WP_Error('forbidden', 'Bạn không có quyền điểm danh xe này.', array('status' => 403));
        }
        $last = self::current_rollcall($bus_id);
        $sequence = $last ? (int) $last['sequence_no'] + 1 : 1;
        $wpdb->insert(
            MAC_Voting_DB::table('bus_rollcalls'),
            array(
                'bus_id' => $bus_id,
                'sequence_no' => $sequence,
                'created_by' => get_current_user_id(),
                'created_at' => MAC_Voting_DB::utc_now(),
            ),
            array('%d', '%d', '%d', '%s')
        );
        MAC_Voting_DB::audit('STAFF', (string) get_current_user_id(), 'BUS_ROLLCALL_CREATED', 'bus', (string) $bus_id, array('sequence' => $sequence));
        return self::rollcall_state($bus_id);
    }

    public static function toggle_mark(int $bus_id, int $member_id, bool $present) {
        global $wpdb;
        if (!self::can_rollcall($bus_id)) {
            return new WP_Error('forbidden', 'Bạn không có quyền điểm danh xe này.', array('status' => 403));
        }
        $rollcall = self::current_rollcall($bus_id);
        if (!$rollcall) {
            $state = self::new_rollcall($bus_id);
            if (is_wp_error($state)) {
                return $state;
            }
            $rollcall = self::current_rollcall($bus_id);
        }
        $member = $wpdb->get_row($wpdb->prepare('SELECT id FROM ' . MAC_Voting_DB::table('bus_members') . ' WHERE id=%d AND bus_id=%d', $member_id, $bus_id), ARRAY_A);
        if (!$member) {
            return new WP_Error('not_found', 'Thành viên không thuộc xe này.', array('status' => 404));
        }
        $now = MAC_Voting_DB::utc_now();
        $wpdb->query($wpdb->prepare(
            'INSERT INTO ' . MAC_Voting_DB::table('bus_rollcall_marks') . ' (rollcall_id,bus_member_id,present,marked_by,marked_at,updated_at)
             VALUES (%d,%d,%d,%d,%s,%s)
             ON DUPLICATE KEY UPDATE present=VALUES(present),marked_by=VALUES(marked_by),marked_at=VALUES(marked_at),updated_at=VALUES(updated_at)',
            (int) $rollcall['id'],
            $member_id,
            $present ? 1 : 0,
            get_current_user_id(),
            $now,
            $now
        ));
        return self::rollcall_state($bus_id);
    }

    public static function rollcall_state(int $bus_id): array {
        global $wpdb;
        $bus = null;
        foreach (self::buses() as $row) {
            if ((int) $row['id'] === $bus_id) {
                $bus = $row;
            }
        }
        $manifest = self::manifest($bus_id);
        $rollcall = self::current_rollcall($bus_id);
        $marks = array();
        if ($rollcall) {
            $rows = $wpdb->get_results($wpdb->prepare(
                'SELECT bus_member_id,present FROM ' . MAC_Voting_DB::table('bus_rollcall_marks') . ' WHERE rollcall_id=%d',
                (int) $rollcall['id']
            ), ARRAY_A) ?: array();
            foreach ($rows as $row) {
                $marks[(int) $row['bus_member_id']] = (int) $row['present'] === 1;
            }
        }
        $present_count = 0;
        foreach ($manifest as &$member) {
            $member['present'] = $marks[$member['id']] ?? false;
            if ($member['present']) {
                $present_count++;
            }
        }
        unset($member);
        $history_rows = $wpdb->get_results($wpdb->prepare(
            'SELECT r.id,r.sequence_no,r.created_at,
                (SELECT COUNT(*) FROM ' . MAC_Voting_DB::table('bus_rollcall_marks') . " m WHERE m.rollcall_id=r.id AND m.present=1) AS present_count
             FROM " . MAC_Voting_DB::table('bus_rollcalls') . ' r
             WHERE r.bus_id=%d
             ORDER BY r.sequence_no DESC',
            $bus_id
        ), ARRAY_A) ?: array();
        $history = array_map(static function(array $row): array {
            return array(
                'sequence' => (int) $row['sequence_no'],
                'createdAt' => MAC_Voting_DB::hanoi_time((string) $row['created_at']),
                'presentCount' => (int) $row['present_count'],
            );
        }, $history_rows);
        return array(
            'busId' => $bus_id,
            'busName' => $bus ? (string) $bus['name'] : ('Xe ' . $bus_id),
            'members' => $manifest,
            'presentCount' => $present_count,
            'totalCount' => count($manifest),
            'currentSequence' => $rollcall ? (int) $rollcall['sequence_no'] : 0,
            'history' => $history,
        );
    }

    public static function rollcall_summary(int $bus_id): array {
        global $wpdb;
        $rollcall = self::current_rollcall($bus_id);
        $marks = array();
        if ($rollcall) {
            $rows = $wpdb->get_results($wpdb->prepare(
                'SELECT bus_member_id,present FROM ' . MAC_Voting_DB::table('bus_rollcall_marks') . ' WHERE rollcall_id=%d',
                (int) $rollcall['id']
            ), ARRAY_A) ?: array();
            foreach ($rows as $row) {
                $marks[(int) $row['bus_member_id']] = (int) $row['present'] === 1;
            }
        }
        $history_rows = $wpdb->get_results($wpdb->prepare(
            'SELECT r.sequence_no,r.created_at,
                (SELECT COUNT(*) FROM ' . MAC_Voting_DB::table('bus_rollcall_marks') . " m WHERE m.rollcall_id=r.id AND m.present=1) AS present_count
             FROM " . MAC_Voting_DB::table('bus_rollcalls') . ' r
             WHERE r.bus_id=%d
             ORDER BY r.sequence_no DESC',
            $bus_id
        ), ARRAY_A) ?: array();
        return array(
            'sequence' => $rollcall ? (int) $rollcall['sequence_no'] : 0,
            'presentCount' => count(array_filter($marks)),
            'marks' => $marks,
            'history' => array_map(static function(array $row): array {
                return array(
                    'sequence' => (int) $row['sequence_no'],
                    'createdAt' => MAC_Voting_DB::hanoi_time((string) $row['created_at']),
                    'presentCount' => (int) $row['present_count'],
                );
            }, $history_rows),
        );
    }

    public static function admin_state(): array {
        // Mọi payload phân xe đều đi qua sync: xe đầy tự chốt, thiếu xe nhận thì tự mở.
        self::sync_boarding();
        $buses = array();
        foreach (self::buses() as $bus) {
            $counts = self::bus_counts((int) $bus['id']);
            $buses[] = array(
                'id' => (int) $bus['id'],
                'name' => (string) $bus['name'],
                'sortOrder' => (int) $bus['sort_order'],
                'status' => (string) $bus['status'],
                'capacity' => (int) $bus['capacity'],
                'employees' => $counts['employees'],
                'companions' => $counts['companions'],
                'staff' => $counts['staff'],
                'total' => $counts['total'],
                'manifest' => self::manifest((int) $bus['id']),
                'rollcall' => self::rollcall_summary((int) $bus['id']),
            );
        }
        $boarding = self::boarding_bus();
        return array(
            'enabled' => self::assignment_enabled(),
            'buses' => $buses,
            'boardingBusId' => $boarding ? (int) $boarding['id'] : null,
            'unassigned' => self::unassigned(),
            'selfArrange' => self::self_arrange_list(),
            'guides' => self::guides(),
        );
    }
}
