<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap ltr-wrap">
  <h1>🧭 Điều khiển quay thưởng</h1>
  <p class="ltr-sub">Bấm <strong>"Dò la bàn"</strong> mỗi khi có người thắng lô tô. Màn hình LED sẽ tự động chạy hiệu ứng la bàn dò hướng, thuyền đi tìm kho báu và mở phần quà — bạn không cần thao tác gì thêm trên màn hình đó.</p>

  <div id="ltr-message" class="ltr-msg" style="display:none;"></div>

  <div class="ltr-panel ltr-panel-main">
    <button id="ltr-btn-draw" class="ltr-btn ltr-btn-gold ltr-btn-large">🧭 DÒ LA BÀN</button>

    <div class="ltr-secondary-actions">
      <button id="ltr-btn-ready" class="ltr-btn ltr-btn-ghost">✅ Sẵn sàng lượt tiếp theo</button>
      <button id="ltr-btn-undo" class="ltr-btn ltr-btn-ghost">↩ Hoàn tác lượt gần nhất</button>
    </div>
  </div>

  <div class="ltr-columns">
    <div class="ltr-panel">
      <h2>💰 Kho tàng còn lại</h2>
      <div id="ltr-prize-summary"><p class="ltr-empty">Đang tải...</p></div>
      <p class="ltr-hint"><a href="<?php echo esc_url(admin_url('admin.php?page=ltr-prizes')); ?>">Quản lý phần quà →</a></p>
    </div>
    <div class="ltr-panel">
      <h2>📜 Lịch sử quay</h2>
      <div id="ltr-history-list"><p class="ltr-empty">Đang tải...</p></div>
    </div>
  </div>

  <div class="ltr-panel">
    <p class="ltr-hint">📺 Chưa mở màn hình LED? <a href="<?php echo esc_url(admin_url('admin.php?page=ltr-display-info')); ?>">Lấy link màn hình tại đây →</a></p>
  </div>
</div>
