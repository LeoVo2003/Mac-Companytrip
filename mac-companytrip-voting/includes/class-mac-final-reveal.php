<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Màn công bố TỔNG ĐIỂM chung cuộc — "Race to the Crown".
 *
 * State machine tuyến tính (hỗ trợ đồng hạng theo nhóm hạng):
 * READY → BUILD_CHECKIN → BUILD_GAME → BUILD_THIDUA → BUILD_VANNGHE → LOCKED
 * → BOTTOM → TOP3 → BRONZE → DUEL → RUNNER_UP → CHAMPION → BOARD
 *
 * Snapshot điểm đóng băng lúc BẮT ĐẦU; reset mới tạo snapshot mới.
 * Điều khiển qua admin-ajax (mac_final_reveal, op next/back/reset, super admin);
 * màn chiếu đọc public qua REST GET mac-voting/v1/final-reveal.
 */
final class MAC_Final_Reveal {
    private const NS = 'mac-voting/v1';

    public const STATES = array(
        'READY', 'BUILD_CHECKIN', 'BUILD_GAME', 'BUILD_THIDUA', 'BUILD_VANNGHE',
        'LOCKED', 'BOTTOM', 'TOP3', 'BRONZE', 'DUEL', 'RUNNER_UP', 'CHAMPION', 'BOARD',
    );

    public static function init(): void {
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('wp_ajax_mac_final_reveal', array(__CLASS__, 'ajax_control'));
        add_shortcode('mac_companytrip_final', array(__CLASS__, 'shortcode'));
        add_filter('template_include', array(__CLASS__, 'standalone_template'), 99);
        add_filter('body_class', array(__CLASS__, 'body_class'));
        add_filter('show_admin_bar', array(__CLASS__, 'hide_admin_bar'));
    }

    /**
     * /ket-qua-tong/ tự render toàn trang bằng template riêng (dark cinematic),
     * bỏ qua header/footer/CSS của theme để màn chiếu toàn màn hình.
     */
    public static function standalone_template($template) {
        if (self::is_final_page()) {
            return MAC_VOTING_DIR . 'includes/template-final-page.php';
        }
        return $template;
    }

    public static function is_final_page(): bool {
        global $post;
        $page_id = (int) get_option('mac_voting_final_page_id');
        return ($page_id && is_page($page_id))
            || ($post instanceof WP_Post && has_shortcode($post->post_content, 'mac_companytrip_final'));
    }

    public static function body_class(array $classes): array {
        if (self::is_final_page()) {
            $classes[] = 'mac-final-page';
        }
        return $classes;
    }

    public static function hide_admin_bar($show) {
        if (self::is_final_page()) {
            return false;
        }
        return $show;
    }

    public static function shortcode(): string {
        wp_enqueue_style('mac-voting-final');
        wp_enqueue_script('mac-voting-final');
        $endpoint = esc_url(rest_url(self::NS . '/final-reveal'));
        $logo = esc_url(MAC_VOTING_URL . 'assets/mac-marketing-logo.png');
        ob_start();
        ?>
        <div id="mac-final-app" class="mac-final-app" data-endpoint="<?php echo $endpoint; ?>" data-logo="<?php echo $logo; ?>">
            <div class="mf-loading" role="status">
                <img src="<?php echo $logo; ?>" alt="MAC Marketing">
                <span>Đang kết nối màn hình công bố tổng…</span>
            </div>
            <noscript>Bạn cần bật JavaScript để xem kết quả.</noscript>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function routes(): void {
        register_rest_route(self::NS, '/final-reveal', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'rest_state'),
            'permission_callback' => '__return_true',
        ));
    }

    /** Public cho màn chiếu: chỉ đọc, không đổi state. */
    public static function rest_state() {
        $state = self::state();
        return rest_ensure_response(array(
            'state' => $state['state'],
            'revision' => $state['revision'],
            'changedAt' => $state['changedAt'],
            'serverTime' => time(),
            'snapshot' => self::snapshot(),
        ));
    }

    public static function state(): array {
        $state = get_option('mac_final_reveal_state', array());
        if (!is_array($state) || !in_array($state['state'] ?? '', self::STATES, true)) {
            $state = array();
        }
        return array(
            'state' => $state['state'] ?? 'READY',
            'revision' => (int) ($state['revision'] ?? 0),
            'changedAt' => (int) ($state['changedAt'] ?? 0),
        );
    }

    private static function set_state(string $next): array {
        $current = self::state();
        $state = array(
            'state' => $next,
            'revision' => $current['revision'] + 1,
            'changedAt' => time(),
        );
        update_option('mac_final_reveal_state', $state, false);
        return $state;
    }

    public static function snapshot(): ?array {
        $snapshot = get_option('mac_final_score_snapshot', null);
        return is_array($snapshot) && !empty($snapshot['teams']) && !empty($snapshot['groups']) ? $snapshot : null;
    }

    /**
     * Đóng băng bảng điểm + compute nhóm hạng (đồng hạng = cùng total).
     * Rank kiểu competition: 1,1,3 — nhóm hạng 2 thiếu thì DUEL xong lên thẳng CHAMPION.
     */
    public static function create_snapshot(): array {
        $board = MAC_Points::dashboard();
        $teams = array();
        foreach ($board['teams'] as $row) {
            $teams[] = array(
                'teamId' => (int) $row['teamId'],
                'teamNumber' => (int) $row['teamNumber'],
                'teamName' => (string) $row['teamName'],
                'checkin' => (int) $row['checkin'],
                'games' => (int) $row['games'],
                'vote' => (int) $row['vote'],
                'thidua' => (int) $row['thidua'],
                'total' => (int) $row['total'],
            );
        }
        usort($teams, static function (array $a, array $b): int {
            if ($a['total'] !== $b['total']) {
                return $b['total'] <=> $a['total'];
            }
            return $a['teamNumber'] <=> $b['teamNumber'];
        });
        $groups = array();
        $rank = 0;
        $previous = null;
        foreach ($teams as $index => $team) {
            if ($previous === null || $previous !== $team['total']) {
                $rank = $index + 1;
                $groups[] = array('rank' => $rank, 'total' => $team['total'], 'teams' => array());
            }
            $groups[count($groups) - 1]['teams'][] = $team;
            $previous = $team['total'];
        }
        $totals = array_map(static function (array $team): int {
            return $team['total'];
        }, $teams);
        $snapshot = array(
            'created_at' => time(),
            'teams' => $teams,
            'groups' => $groups,
            'displayMax' => max(1, (int) ceil(max($totals) * 1.08)),
            'displayMin' => min(0, min($totals)),
        );
        update_option('mac_final_score_snapshot', $snapshot, false);
        return $snapshot;
    }

    private static function has_rank_group(?array $snapshot, int $rank): bool {
        if (!$snapshot) {
            return false;
        }
        foreach ($snapshot['groups'] as $group) {
            if ((int) $group['rank'] === $rank) {
                return true;
            }
        }
        return false;
    }

    private static function has_bottom_groups(?array $snapshot): bool {
        if (!$snapshot) {
            return false;
        }
        foreach ($snapshot['groups'] as $group) {
            if ((int) $group['rank'] > 3) {
                return true;
            }
        }
        return false;
    }

    /** Transition tiến — tự skip phase không có nhóm hạng (đồng hạng). */
    public static function next_state(string $current, ?array $snapshot): ?string {
        switch ($current) {
            case 'READY': return 'BUILD_CHECKIN';
            case 'BUILD_CHECKIN': return 'BUILD_GAME';
            case 'BUILD_GAME': return 'BUILD_THIDUA';
            case 'BUILD_THIDUA': return 'BUILD_VANNGHE';
            case 'BUILD_VANNGHE': return 'LOCKED';
            case 'LOCKED': return self::has_bottom_groups($snapshot) ? 'BOTTOM' : 'TOP3';
            case 'BOTTOM': return 'TOP3';
            case 'TOP3': return self::has_rank_group($snapshot, 3) ? 'BRONZE' : 'DUEL';
            case 'BRONZE': return 'DUEL';
            case 'DUEL': return self::has_rank_group($snapshot, 2) ? 'RUNNER_UP' : 'CHAMPION';
            case 'RUNNER_UP': return 'CHAMPION';
            case 'CHAMPION': return 'BOARD';
            default: return null;
        }
    }

    /** Transition lùi — mirror của next để LÙI luôn về đúng ô trước đó. */
    public static function prev_state(string $current, ?array $snapshot): ?string {
        switch ($current) {
            case 'BUILD_CHECKIN': return 'READY';
            case 'BUILD_GAME': return 'BUILD_CHECKIN';
            case 'BUILD_THIDUA': return 'BUILD_GAME';
            case 'BUILD_VANNGHE': return 'BUILD_THIDUA';
            case 'LOCKED': return 'BUILD_VANNGHE';
            case 'BOTTOM': return 'LOCKED';
            case 'TOP3': return self::has_bottom_groups($snapshot) ? 'BOTTOM' : 'LOCKED';
            case 'BRONZE': return 'TOP3';
            case 'DUEL': return self::has_rank_group($snapshot, 3) ? 'BRONZE' : 'TOP3';
            case 'RUNNER_UP': return 'DUEL';
            case 'CHAMPION': return self::has_rank_group($snapshot, 2) ? 'RUNNER_UP' : 'DUEL';
            case 'BOARD': return 'CHAMPION';
            default: return null;
        }
    }

    /** Payload cho dashboard admin (tab Chung kết). */
    public static function payload(): array {
        $state = self::state();
        $snapshot = self::snapshot();
        return array(
            'state' => $state['state'],
            'revision' => $state['revision'],
            'changedAt' => $state['changedAt'],
            'snapshotCreatedAt' => $snapshot ? (int) $snapshot['created_at'] : null,
            'teamCount' => $snapshot ? count($snapshot['teams']) : 0,
            'screenUrl' => MAC_Voting_DB::final_page_url(),
            'next' => self::next_state($state['state'], $snapshot),
            'back' => self::prev_state($state['state'], $snapshot),
        );
    }

    public static function ajax_control(): void {
        check_ajax_referer('mac_voting_admin', 'nonce');
        if (!MAC_Checkin::is_super()) {
            wp_send_json_error(array('message' => 'Không có quyền điều khiển màn công bố tổng.'), 403);
        }
        $op = sanitize_key($_POST['op'] ?? '');
        $current = self::state();
        $snapshot = self::snapshot();
        $actor = (string) get_current_user_id();

        if ($op === 'next') {
            $started = false;
            if ($current['state'] === 'READY' && !$snapshot) {
                $snapshot = self::create_snapshot();
                $started = true;
            }
            $next = self::next_state($current['state'], $snapshot);
            if (!$next) {
                wp_send_json_error(array('message' => 'Kịch bản đã ở màn cuối (FINAL BOARD).'), 409);
            }
            $state = self::set_state($next);
            MAC_Voting_DB::audit('ADMIN', $actor, $started ? 'FINAL_REVEAL_STARTED' : 'FINAL_REVEAL_NEXT', 'final_reveal', (string) $state['revision'], array(
                'from' => $current['state'],
                'to' => $next,
                'snapshotCreatedAt' => (int) $snapshot['created_at'],
            ));
            wp_send_json_success(array('message' => self::message_for($next), 'finalReveal' => self::payload()));
        }

        if ($op === 'back') {
            $prev = self::prev_state($current['state'], $snapshot);
            if (!$prev) {
                wp_send_json_error(array('message' => 'Đang ở đầu kịch bản, không thể lùi.'), 409);
            }
            $state = self::set_state($prev);
            MAC_Voting_DB::audit('ADMIN', $actor, 'FINAL_REVEAL_BACK', 'final_reveal', (string) $state['revision'], array(
                'from' => $current['state'],
                'to' => $prev,
            ));
            wp_send_json_success(array('message' => 'Đã lùi về bước trước.', 'finalReveal' => self::payload()));
        }

        if ($op === 'reset') {
            self::reset();
            MAC_Voting_DB::audit('ADMIN', $actor, 'FINAL_REVEAL_RESET', 'final_reveal', (string) self::state()['revision'], array(
                'from' => $current['state'],
            ));
            wp_send_json_success(array('message' => 'Đã đặt lại màn công bố tổng. Snapshot cũ đã xóa.', 'finalReveal' => self::payload()));
        }

        wp_send_json_error(array('message' => 'Thao tác không hợp lệ.'), 400);
    }

    /** Xóa snapshot + đưa state về READY (dùng cho cả RESET sự kiện). */
    public static function reset(): void {
        delete_option('mac_final_score_snapshot');
        self::set_state('READY');
    }

    private static function message_for(string $state): string {
        $messages = array(
            'BUILD_CHECKIN' => 'Đã khóa snapshot. Màn chiếu đang mở điểm CHECK-IN.',
            'BUILD_GAME' => 'Mở điểm TRÒ CHƠI LỚN, bảng đua đang xếp lại.',
            'BUILD_THIDUA' => 'Mở điểm THI ĐUA, bảng đua đang xếp lại.',
            'BUILD_VANNGHE' => 'Mở điểm VĂN NGHỆ — trụ cột cuối cùng.',
            'LOCKED' => 'FINAL RANKING LOCKED — điểm đã khóa, chưa lộ hạng.',
            'BOTTOM' => 'Màn chiếu đang loại dần từ hạng thấp.',
            'TOP3' => 'Chỉ còn các đội dẫn đầu trên sân khấu.',
            'BRONZE' => 'Đã công bố hạng ba.',
            'DUEL' => 'FINAL DUEL — hai bên đang chạy số.',
            'RUNNER_UP' => 'Đã công bố á quân.',
            'CHAMPION' => 'Đã công bố nhà vô địch.',
            'BOARD' => 'FINAL BOARD — bảng tổng sắp chung cuộc.',
        );
        return $messages[$state] ?? 'Đã chuyển bước.';
    }
}
