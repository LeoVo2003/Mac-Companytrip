<?php
/**
 * Trang màn chiếu standalone cho /ket-qua-tong/ — "Race to the Crown".
 *
 * Không gọi wp_head/wp_footer nên header/footer/CSS/JS của theme không can thiệp;
 * chỉ nạp final.css + final.js để màn công bố tổng chạy toàn màn hình.
 */

if (!defined('ABSPATH')) {
    exit;
}

$mac_favicon = esc_url(MAC_VOTING_URL . 'assets/favicon_mac_one.png');
$mac_logo = esc_url(MAC_VOTING_URL . 'assets/mac-marketing-logo.png');
$mac_ver = MAC_VOTING_VERSION;
$mac_url = MAC_VOTING_URL;
$mac_config = array(
    'endpoint' => rest_url('mac-voting/v1/final-reveal'),
    'logo' => MAC_VOTING_URL . 'assets/mac-marketing-logo.png',
);
nocache_headers();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Kết Quả Chung Cuộc — MAC Marketing</title>
<link rel="icon" href="<?php echo $mac_favicon; ?>">
<link rel="stylesheet" href="<?php echo esc_url($mac_url . 'assets/final.css'); ?>?v=<?php echo esc_attr($mac_ver); ?>">
</head>
<body class="mac-final-page mac-final-standalone">
<div id="mac-final-app" class="mac-final-app" data-logo="<?php echo $mac_logo; ?>">
    <div class="mf-loading" role="status">
        <img src="<?php echo $mac_logo; ?>" alt="MAC Marketing">
        <span>Đang kết nối màn hình công bố tổng…</span>
    </div>
    <noscript>Bạn cần bật JavaScript để xem kết quả.</noscript>
</div>
<script>window.MACFinal = <?php echo wp_json_encode($mac_config); ?>;</script>
<script src="<?php echo esc_url($mac_url . 'assets/final.js'); ?>?v=<?php echo esc_attr($mac_ver); ?>"></script>
</body>
</html>
