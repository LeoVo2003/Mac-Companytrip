<?php
if (!defined('ABSPATH')) exit;

class LTR_Prizes {

    const OPT_PRIZES    = 'ltr_prizes';
    const OPT_HISTORY   = 'ltr_history';
    const OPT_EVENT     = 'ltr_current_event';
    const OPT_EVENT_SEQ = 'ltr_event_seq';
    const OPT_TOKEN     = 'ltr_display_token';

    public static function activate() {
        if (get_option(self::OPT_PRIZES, null) === null) {
            add_option(self::OPT_PRIZES, []);
        }
        if (get_option(self::OPT_HISTORY, null) === null) {
            add_option(self::OPT_HISTORY, []);
        }
        if (get_option(self::OPT_EVENT_SEQ, null) === null) {
            add_option(self::OPT_EVENT_SEQ, 0);
        }
        if (get_option(self::OPT_EVENT, null) === null) {
            add_option(self::OPT_EVENT, [
                'event_id' => 0,
                'type'     => 'idle',
                'spot'     => 0,
                'prize'    => null,
                'time'     => time(),
            ]);
        }
        if (get_option(self::OPT_TOKEN, null) === null) {
            add_option(self::OPT_TOKEN, wp_generate_password(20, false));
        }
    }

    public static function get_display_token() {
        $token = get_option(self::OPT_TOKEN, '');
        if (!$token) {
            $token = wp_generate_password(20, false);
            update_option(self::OPT_TOKEN, $token);
        }
        return $token;
    }

    public static function get_prizes() {
        $prizes = get_option(self::OPT_PRIZES, []);
        return is_array($prizes) ? $prizes : [];
    }

    public static function save_prizes($prizes) {
        update_option(self::OPT_PRIZES, $prizes);
    }

    public static function get_history() {
        $h = get_option(self::OPT_HISTORY, []);
        return is_array($h) ? $h : [];
    }

    public static function save_history($h) {
        update_option(self::OPT_HISTORY, $h);
    }

    public static function get_current_event() {
        $event = get_option(self::OPT_EVENT, null);
        return is_array($event) ? $event : [
            'event_id' => 0, 'type' => 'idle', 'spot' => 0,
        ];
    }

    public static function next_event_id() {
        $seq = (int) get_option(self::OPT_EVENT_SEQ, 0);
        $seq++;
        update_option(self::OPT_EVENT_SEQ, $seq);
        return $seq;
    }

    public static function set_current_event($event) {
        update_option(self::OPT_EVENT, $event);
    }

    public static function add_prize($name, $image_id, $qty) {
        $prizes   = self::get_prizes();
        $image_id = (int) $image_id;
        $prizes[] = [
            'id'        => 'p' . uniqid(),
            'name'      => sanitize_text_field($name),
            'image_id'  => $image_id,
            'image_url' => $image_id ? (string) wp_get_attachment_image_url($image_id, 'large') : '',
            'total'     => max(1, (int) $qty),
            'remaining' => max(1, (int) $qty),
        ];
        self::save_prizes($prizes);
    }

    public static function delete_prize($id) {
        $prizes = array_values(array_filter(self::get_prizes(), function ($p) use ($id) {
            return $p['id'] !== $id;
        }));
        self::save_prizes($prizes);
    }

    public static function reset_all() {
        self::save_prizes([]);
        self::save_history([]);
        self::set_current_event([
            'event_id' => self::next_event_id(),
            'type'     => 'reset',
            'spot'     => 0,
            'prize'    => null,
            'time'     => time(),
        ]);
    }

    public static function remaining_prizes() {
        return array_values(array_filter(self::get_prizes(), function ($p) {
            return isset($p['remaining']) && $p['remaining'] > 0;
        }));
    }

    /**
     * Randomly pick one remaining prize, decrement stock, log history and
     * publish a "draw" event for the LED display to pick up.
     */
    public static function draw() {
        $pool = self::remaining_prizes();
        if (empty($pool)) {
            return new WP_Error('no_prizes', 'Không còn phần thưởng nào trong kho tàng.');
        }

        $chosen = $pool[array_rand($pool)];

        $prizes = self::get_prizes();
        foreach ($prizes as &$p) {
            if ($p['id'] === $chosen['id']) {
                $p['remaining'] = max(0, (int) $p['remaining'] - 1);
                break;
            }
        }
        unset($p);
        self::save_prizes($prizes);

        $spot = function_exists('wp_rand') ? wp_rand(0, 5) : mt_rand(0, 5);

        $entry = [
            'id'         => 'h' . uniqid(),
            'prize_id'   => $chosen['id'],
            'prize_name' => $chosen['name'],
            'image_url'  => $chosen['image_url'],
            'time'       => time(),
        ];
        $history   = self::get_history();
        $history[] = $entry;
        self::save_history($history);

        $event = [
            'event_id' => self::next_event_id(),
            'type'     => 'draw',
            'spot'     => $spot,
            'prize'    => [
                'name'      => $chosen['name'],
                'image_url' => $chosen['image_url'],
            ],
            'time'     => time(),
        ];
        self::set_current_event($event);

        return $event;
    }

    public static function undo_last() {
        $history = self::get_history();
        if (empty($history)) {
            return new WP_Error('empty_history', 'Chưa có lượt quay nào để hoàn tác.');
        }
        $last = array_pop($history);
        self::save_history($history);

        $prizes = self::get_prizes();
        foreach ($prizes as &$p) {
            if ($p['id'] === $last['prize_id']) {
                $p['remaining'] = min((int) $p['total'], (int) $p['remaining'] + 1);
                break;
            }
        }
        unset($p);
        self::save_prizes($prizes);

        self::set_current_event([
            'event_id' => self::next_event_id(),
            'type'     => 'reset',
            'spot'     => 0,
            'prize'    => null,
            'time'     => time(),
        ]);

        return true;
    }

    public static function ready_next() {
        self::set_current_event([
            'event_id' => self::next_event_id(),
            'type'     => 'reset',
            'spot'     => 0,
            'prize'    => null,
            'time'     => time(),
        ]);
    }
}
