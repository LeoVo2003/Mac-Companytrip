<?php
if (!defined('ABSPATH')) exit;
$url = add_query_arg('ltr_display', '1', home_url('/'));
?>
<div class="wrap ltr-wrap">
  <h1>📺 Màn hình LED</h1>
  <div class="ltr-panel">
    <p>Mở đường link dưới đây trên trình duyệt của máy tính đang chiếu lên màn hình LED / máy chiếu, sau đó bật chế độ toàn màn hình (phím <strong>F11</strong>):</p>
    <input type="text" readonly value="<?php echo esc_url($url); ?>" class="ltr-input" onclick="this.select();">
    <p><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" class="ltr-btn ltr-btn-gold">Mở màn hình LED trong tab mới →</a></p>
    <p class="ltr-hint">Giờ bạn có thể điều khiển trực tiếp ngay trên màn hình này: <strong>chạm/click vào bản đồ để dò la bàn</strong> tìm phần thưởng, và <strong>chạm vào popup phần thưởng để đóng</strong> lại, tiếp tục lượt sau. Không cần mở thêm trang Điều khiển nữa (trang đó vẫn dùng được nếu muốn điều khiển từ điện thoại khác).</p>
  </div>
</div>
