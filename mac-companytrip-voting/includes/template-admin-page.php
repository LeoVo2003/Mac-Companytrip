<?php
/**
 * Trang dashboard standalone cho /company-trip-admin/.
 *
 * Không gọi wp_head/wp_footer nên header/footer/CSS/JS của theme không can thiệp;
 * chỉ nạp đúng bộ asset của plugin để giao diện khớp 100% với dashboard.
 */

if (!defined('ABSPATH')) {
    exit;
}

$mac_logo = esc_url(MAC_VOTING_URL . 'assets/mac-marketing-logo.png');
$mac_ver = MAC_VOTING_VERSION;
$mac_url = MAC_VOTING_URL;
$mac_can_access = is_user_logged_in() && MAC_Voting_Admin::can_access_dashboard();
nocache_headers();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Company Trip Dashboard — MAC Marketing</title>
<link rel="icon" href="<?php echo $mac_logo; ?>">
<link rel="stylesheet" href="<?php echo esc_url($mac_url . 'assets/admin.css'); ?>?v=<?php echo esc_attr($mac_ver); ?>">
<link rel="stylesheet" href="<?php echo esc_url($mac_url . 'assets/admin-qr.css'); ?>?v=<?php echo esc_attr($mac_ver); ?>">
<link rel="stylesheet" href="<?php echo esc_url($mac_url . 'assets/ui-refinements.css'); ?>?v=<?php echo esc_attr($mac_ver); ?>">
</head>
<body class="mac-admin-public-page mac-admin-standalone">
<?php if ($mac_can_access) : ?>
<div id="mac-voting-admin" class="mac-admin-app">
    <div class="mac-admin-loading">Đang tải dashboard...</div>
</div>
<script>window.MACVotingAdmin = <?php echo wp_json_encode(MAC_Voting_Admin::script_config()); ?>;</script>
<script src="<?php echo esc_url($mac_url . 'assets/qrcode.bundle.js'); ?>?v=<?php echo esc_attr($mac_ver); ?>"></script>
<script src="<?php echo esc_url($mac_url . 'assets/admin.js'); ?>?v=<?php echo esc_attr($mac_ver); ?>"></script>
<?php elseif (is_user_logged_in()) : ?>
<div class="ma-login">
    <img src="<?php echo $mac_logo; ?>" alt="MAC Marketing">
    <h1>Không có quyền</h1>
    <p>Tài khoản này không vào được dashboard Company Trip.</p>
    <a class="ma-primary" href="<?php echo esc_url(wp_logout_url(MAC_Voting_DB::admin_page_url())); ?>">Đăng xuất</a>
</div>
<?php else : ?>
<?php echo MAC_Admin_Public::login_form_markup($mac_logo); ?>
<script src="<?php echo esc_url($mac_url . 'assets/admin-login.js'); ?>?v=<?php echo esc_attr($mac_ver); ?>"></script>
<?php endif; ?>
</body>
</html>
