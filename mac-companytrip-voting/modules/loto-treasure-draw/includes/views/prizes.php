<?php
if (!defined('ABSPATH')) exit;
$prizes = LTR_Prizes::get_prizes();
?>
<div class="wrap ltr-wrap">
  <h1>💰 Kho tàng phần thưởng</h1>

  <?php if (isset($_GET['added'])) : ?><div class="notice notice-success is-dismissible"><p>Đã thêm phần thưởng.</p></div><?php endif; ?>
  <?php if (isset($_GET['deleted'])) : ?><div class="notice notice-success is-dismissible"><p>Đã xóa phần thưởng.</p></div><?php endif; ?>
  <?php if (isset($_GET['reset'])) : ?><div class="notice notice-success is-dismissible"><p>Đã reset toàn bộ dữ liệu.</p></div><?php endif; ?>

  <div class="ltr-panel">
    <h2>+ Thêm phần thưởng mới</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('ltr_add_prize'); ?>
      <input type="hidden" name="action" value="ltr_add_prize">

      <label>Tên phần thưởng</label>
      <input type="text" name="prize_name" class="ltr-input" placeholder="VD: Nồi cơm điện Sharp 1.8L" required>

      <label>Số lượng</label>
      <input type="number" name="prize_qty" class="ltr-input" value="1" min="1" required>

      <label>Hình ảnh phần thưởng</label><br>
      <img class="ltr-image-preview" src="" alt="" style="display:none;max-width:120px;border-radius:8px;margin-bottom:8px;">
      <input type="hidden" name="prize_image_id" class="ltr-image-id" value="0">
      <br>
      <button type="button" class="button ltr-choose-image">Chọn hình ảnh</button>

      <p><button type="submit" class="ltr-btn ltr-btn-gold">Thêm phần thưởng</button></p>
    </form>
  </div>

  <div class="ltr-panel">
    <h2>Danh sách phần thưởng</h2>
    <table class="widefat ltr-table">
      <thead>
        <tr><th style="width:70px;">Hình</th><th>Tên</th><th style="width:110px;">Còn lại</th><th style="width:70px;"></th></tr>
      </thead>
      <tbody>
      <?php foreach ($prizes as $p) : ?>
        <tr>
          <td>
            <?php if (!empty($p['image_url'])) : ?>
              <img src="<?php echo esc_url($p['image_url']); ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px;">
            <?php endif; ?>
          </td>
          <td><?php echo esc_html($p['name']); ?></td>
          <td><?php echo (int) $p['remaining']; ?> / <?php echo (int) $p['total']; ?></td>
          <td>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Xóa phần thưởng này?');">
              <?php wp_nonce_field('ltr_delete_prize'); ?>
              <input type="hidden" name="action" value="ltr_delete_prize">
              <input type="hidden" name="prize_id" value="<?php echo esc_attr($p['id']); ?>">
              <button type="submit" class="button-link-delete">Xóa</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($prizes)) : ?>
        <tr><td colspan="4">Chưa có phần thưởng nào. Thêm phần thưởng đầu tiên ở form phía trên.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="ltr-panel">
    <h2>⚠ Khu vực nguy hiểm</h2>
    <p class="ltr-hint">Dùng khi bắt đầu một chương trình mới hoàn toàn. Thao tác này xóa toàn bộ phần thưởng và lịch sử quay, không thể hoàn tác.</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Xóa TOÀN BỘ phần thưởng và lịch sử quay? Không thể hoàn tác.');">
      <?php wp_nonce_field('ltr_reset_all'); ?>
      <input type="hidden" name="action" value="ltr_reset_all">
      <button type="submit" class="ltr-btn ltr-btn-danger">🗑 Xóa toàn bộ dữ liệu</button>
    </form>
  </div>
</div>
