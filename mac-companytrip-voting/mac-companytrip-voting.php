<?php
/**
 * Plugin Name: MAC Company Trip Voting
 * Plugin URI: https://macmarketing.vn/
 * Description: Hệ thống chấm điểm văn nghệ Company Trip, có quản lý team linh hoạt, khóa vote team mình, chống phiếu trùng và audit log.
 * Version: 1.8.3
 * Author: MAC Marketing
 * Text Domain: mac-companytrip-voting
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MAC_VOTING_VERSION', '1.8.3');
define('MAC_VOTING_FILE', __FILE__);
define('MAC_VOTING_DIR', plugin_dir_path(__FILE__));
define('MAC_VOTING_URL', plugin_dir_url(__FILE__));

if (!defined('MAC_VOTING_GITHUB_REPO')) {
    define('MAC_VOTING_GITHUB_REPO', 'LeoVo2003/Mac-Companytrip');
}

require_once MAC_VOTING_DIR . 'includes/class-mac-voting-db.php';
require_once MAC_VOTING_DIR . 'includes/class-mac-voting-auth.php';
require_once MAC_VOTING_DIR . 'includes/class-mac-voting-qr.php';
require_once MAC_VOTING_DIR . 'includes/class-mac-checkin.php';
require_once MAC_VOTING_DIR . 'includes/class-mac-points.php';
require_once MAC_VOTING_DIR . 'includes/class-mac-games.php';
require_once MAC_VOTING_DIR . 'includes/class-mac-voting-rest.php';
require_once MAC_VOTING_DIR . 'includes/class-mac-checkin-rest.php';
require_once MAC_VOTING_DIR . 'includes/class-mac-voting-public.php';
require_once MAC_VOTING_DIR . 'includes/class-mac-checkin-public.php';
require_once MAC_VOTING_DIR . 'includes/class-mac-voting-admin.php';
require_once MAC_VOTING_DIR . 'includes/class-mac-admin-public.php';
require_once MAC_VOTING_DIR . 'includes/class-mac-admin-rest.php';
require_once MAC_VOTING_DIR . 'includes/class-mac-voting-updater.php';

register_activation_hook(__FILE__, array('MAC_Voting_DB', 'activate'));

function mac_voting_bootstrap(): void {
    add_action('init', 'mac_voting_maybe_upgrade', 5);
    MAC_Checkin::register_roles();
    MAC_Voting_REST::init();
    MAC_Checkin_REST::init();
    MAC_Voting_Public::init();
    MAC_Checkin_Public::init();
    MAC_Admin_REST::init();
    MAC_Admin_Public::init();
    MAC_Voting_Updater::init();
    if (is_admin()) {
        MAC_Voting_Admin::init();
    }
}
add_action('plugins_loaded', 'mac_voting_bootstrap');

function mac_voting_maybe_upgrade(): void {
    MAC_Voting_DB::maybe_upgrade();
}
