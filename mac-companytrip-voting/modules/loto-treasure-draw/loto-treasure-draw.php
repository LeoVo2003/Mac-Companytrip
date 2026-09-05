<?php
/**
 * Module: Lô Tô Kho Báu - Lucky Draw (bundled inside MAC Company Trip Voting).
 * Loaded by the parent plugin bootstrap; NOT a standalone WordPress plugin.
 * Plugin URI: https://example.com
 * Description: Plugin bốc thăm trúng thưởng chủ đề hải trình / cướp biển dành cho chương trình lô tô - team building. MC nhập tên người thắng lô tô rồi bấm "Dò la bàn"; màn hình LED tự động chạy hiệu ứng thuyền đi trên bản đồ tới rương kho báu và mở ra hình ảnh phần quà.
 * Version: 1.0.0
 * Author: 
 * Text Domain: loto-treasure-draw
 */

if (!defined('ABSPATH')) {
    exit; // Không truy cập trực tiếp.
}

define('LTR_VERSION', '2.3.0');
define('LTR_PLUGIN_FILE', __FILE__);
define('LTR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LTR_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once LTR_PLUGIN_DIR . 'includes/class-ltr-prizes.php';
require_once LTR_PLUGIN_DIR . 'includes/class-ltr-rest.php';
require_once LTR_PLUGIN_DIR . 'includes/class-ltr-admin.php';
require_once LTR_PLUGIN_DIR . 'includes/class-ltr-display.php';

// Activation is registered by the parent plugin file (mac-companytrip-voting.php),
// because register_activation_hook only fires for the real activated plugin bootstrap.

add_action('plugins_loaded', function () {
    LTR_Admin::init();
    LTR_REST::init();
    LTR_Display::init();
});
