<?php
// Dữ liệu sự kiện được giữ lại khi gỡ plugin để tránh mất phiếu ngoài ý muốn.
// Chỉ xóa bảng khi BTC chủ động xử lý trong database sau khi đã backup.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
