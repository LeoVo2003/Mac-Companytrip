<?php if (!defined('ABSPATH')) exit; ?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Màn hình Lô Tô Kho Báu — Hải trình săn kho báu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800;900&family=Be+Vietnam+Pro:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url($css_url); ?>?v=<?php echo esc_attr(LTR_VERSION); ?>">
</head>
<body>
  <div class="ltr-stage" id="ltr-stage" data-state="idle">

    <!-- ===== NỀN BIỂN ĐIỆN ẢNH ===== -->
    <div class="ltr-ambient-ocean" aria-hidden="true"></div>

    <!-- ===== BẢNG HẢI TRÌNH (VOYAGE BOARD) ===== -->
    <main class="ltr-voyage-board" id="ltr-map">
      <div class="ltr-map-surface" aria-hidden="true"></div>
      <div class="ltr-map-grid" aria-hidden="true"></div>
      <div class="ltr-map-vignette" aria-hidden="true"></div>

      <!-- Dấu ấn thương hiệu ở góc -->
      <header class="ltr-map-brand" aria-hidden="true">
        <svg class="ltr-brand-seal" viewBox="0 0 48 48">
          <circle cx="24" cy="24" r="21" fill="none" stroke="currentColor" stroke-width="2" opacity=".8"/>
          <circle cx="24" cy="24" r="16" fill="none" stroke="currentColor" stroke-width="1" opacity=".5"/>
          <path d="M24 9 L28 22 L24 24 L20 22 Z M24 39 L28 26 L24 24 L20 26 Z M9 24 L22 20 L24 24 L22 28 Z M39 24 L26 20 L24 24 L26 28 Z" fill="currentColor" opacity=".85"/>
          <circle cx="24" cy="24" r="2.6" fill="currentColor"/>
        </svg>
        <span class="ltr-brand-text">HẢI TRÌNH SĂN KHO BÁU</span>
      </header>

      <!-- Badge số phần thưởng còn lại (JS cập nhật từ /state) -->
      <div class="ltr-badge" aria-hidden="true">
        <svg class="ltr-badge-icon" viewBox="0 0 32 32">
          <path d="M4,14 L4,26 Q4,28 6,28 L26,28 Q28,28 28,26 L28,14 Z" fill="currentColor" opacity=".85"/>
          <path d="M4,14 Q4,5 16,5 Q28,5 28,14 Z" fill="currentColor"/>
          <rect x="13" y="11" width="6" height="9" rx="1.5" fill="#f8ecd0"/>
          <rect x="10" y="5" width="3" height="23" fill="#f8ecd0" opacity=".7"/>
          <rect x="19" y="5" width="3" height="23" fill="#f8ecd0" opacity=".7"/>
        </svg>
        <span class="ltr-badge-text" id="ltr-badge-remaining">—</span>
      </div>

      <!-- La bàn hoa thị mờ ở trung tâm (watermark) -->
      <svg class="ltr-compass-rose" viewBox="0 0 200 200" aria-hidden="true">
        <circle cx="100" cy="100" r="86" fill="none" stroke="currentColor" stroke-width="1" opacity=".5"/>
        <circle cx="100" cy="100" r="66" fill="none" stroke="currentColor" stroke-width="1" opacity=".35"/>
        <circle cx="100" cy="100" r="40" fill="none" stroke="currentColor" stroke-width="1" opacity=".28"/>
        <g stroke="currentColor" stroke-width="1" opacity=".3">
          <line x1="100" y1="10" x2="100" y2="190"/>
          <line x1="10" y1="100" x2="190" y2="100"/>
          <line x1="34" y1="34" x2="166" y2="166"/>
          <line x1="166" y1="34" x2="34" y2="166"/>
        </g>
        <path d="M100 16 L112 92 L100 100 L88 92 Z" fill="currentColor" opacity=".55"/>
        <path d="M100 184 L112 108 L100 100 L88 108 Z" fill="currentColor" opacity=".3"/>
        <path d="M16 100 L92 88 L100 100 L92 112 Z" fill="currentColor" opacity=".3"/>
        <path d="M184 100 L108 88 L100 100 L108 112 Z" fill="currentColor" opacity=".3"/>
      </svg>

      <!-- 6 tuyến hải trình (nét mực mờ khi chờ, chỉ tuyến được chọn mới nổi bật) -->
      <svg class="ltr-route-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
        <path id="ltr-route-dyn" class="ltr-route-line" d="" />
      </svg>

      <!-- Vài nét trang trí: vệt sóng khắc, vầng mây mờ -->
      <div class="ltr-map-decorations" aria-hidden="true">
        <svg class="ltr-wave-mark ltr-wave-1" viewBox="0 0 60 16"><path d="M2 10 Q10 2 18 8 Q26 14 34 8 Q42 2 50 8" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
        <svg class="ltr-wave-mark ltr-wave-2" viewBox="0 0 60 16"><path d="M2 10 Q10 2 18 8 Q26 14 34 8 Q42 2 50 8" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
        <svg class="ltr-wave-mark ltr-wave-3" viewBox="0 0 60 16"><path d="M2 10 Q10 2 18 8 Q26 14 34 8 Q42 2 50 8" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
        <div class="ltr-fog ltr-fog-1"></div>
        <div class="ltr-fog ltr-fog-2"></div>
      </div>

      <!-- Bến cảng (điểm xuất phát) -->
      <div class="ltr-harbor" id="ltr-harbor">
        <svg viewBox="0 0 100 100" class="ltr-harbor-svg" aria-hidden="true">
          <path d="M50 82 a17 7 0 1 0 0.1 0" fill="none" stroke="currentColor" stroke-width="2.4" opacity=".35"/>
          <path d="M50 82 a10 4.4 0 1 0 0.1 0" fill="none" stroke="currentColor" stroke-width="2" opacity=".45"/>
          <g transform="translate(50,44)">
            <circle cx="0" cy="-24" r="8" fill="none" stroke="currentColor" stroke-width="5"/>
            <rect x="-3.2" y="-17" width="6.4" height="38" rx="3.2" fill="currentColor"/>
            <rect x="-16" y="-3" width="32" height="6" rx="3" fill="currentColor"/>
            <path d="M -24 16 Q 0 38 24 16" stroke="currentColor" stroke-width="6" fill="none" stroke-linecap="round"/>
            <path d="M -24 16 l -7 -9 M 24 16 l 7 -9" stroke="currentColor" stroke-width="6" fill="none" stroke-linecap="round"/>
          </g>
        </svg>
        <span class="ltr-harbor-label">CẢNG</span>
      </div>

      <!-- Lớp 6 đảo kho báu (JS dựng) -->
      <div id="ltr-spots" class="ltr-treasure-layer"></div>

      <!-- ===== THUYỀN: mover (translate+rotate) tách khỏi body (bob/roll/wake) ===== -->
      <div class="ltr-ship-mover" id="ltr-ship">
        <div class="ltr-ship-body">
          <svg viewBox="0 0 220 170" class="ltr-ship-svg" aria-hidden="true">
            <defs>
              <linearGradient id="ltrHullGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#8a5a2e"/>
                <stop offset="0.55" stop-color="#5c3417"/>
                <stop offset="1" stop-color="#2c1a0c"/>
              </linearGradient>
              <linearGradient id="ltrSailGrad" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="#FBF2DC"/>
                <stop offset="1" stop-color="#E4CFA0"/>
              </linearGradient>
              <linearGradient id="ltrDeckGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#c99a5c"/>
                <stop offset="1" stop-color="#8a6238"/>
              </linearGradient>
            </defs>

            <!-- Vệt sóng sau lái (nhìn từ trên xuống) -->
            <path d="M26 66 Q10 74 4 85 Q10 96 26 104" fill="none" stroke="#dfe9ec" stroke-width="2" opacity=".3" stroke-linecap="round"/>
            <path d="M30 74 Q18 80 14 85 Q18 90 30 96" fill="none" stroke="#dfe9ec" stroke-width="1.6" opacity=".22" stroke-linecap="round"/>

            <!-- Thân tàu nhìn từ trên xuống: mũi nhọn hướng +X -->
            <path d="M204 85 C176 58 128 48 84 50 C56 51 34 62 30 85 C34 108 56 119 84 120 C128 122 176 112 204 85 Z"
                  fill="url(#ltrHullGrad)" stroke="#1c1006" stroke-width="2.6"/>
            <!-- Boong tàu -->
            <path d="M190 85 C166 64 124 56 88 58 C64 59 46 68 42 85 C46 102 64 111 88 112 C124 114 166 106 190 85 Z"
                  fill="url(#ltrDeckGrad)" stroke="#3a2210" stroke-width="1.6"/>
            <!-- Vân ván boong -->
            <path d="M50 85 L186 85" fill="none" stroke="#3a2210" stroke-width="1.4" opacity=".5"/>
            <path d="M56 71 Q120 65 176 75" fill="none" stroke="#3a2210" stroke-width="1.2" opacity=".35"/>
            <path d="M56 99 Q120 105 176 95" fill="none" stroke="#3a2210" stroke-width="1.2" opacity=".35"/>

            <!-- Buồng lái ở đuôi -->
            <rect x="46" y="70" width="26" height="30" rx="5" fill="url(#ltrDeckGrad)" stroke="#2c1a0c" stroke-width="2.2"/>
            <rect x="52" y="76" width="6" height="7" rx="1.5" fill="#1c1006" opacity=".65"/>
            <rect x="61" y="76" width="6" height="7" rx="1.5" fill="#1c1006" opacity=".65"/>

            <!-- Cột buồm trước + cánh buồm nhìn từ trên -->
            <ellipse cx="98" cy="85" rx="12" ry="40" fill="url(#ltrSailGrad)" stroke="#3a2210" stroke-width="2" class="ltr-sail ltr-sail-jib"/>
            <path d="M98 49 Q104 85 98 121" fill="none" stroke="#3a2210" stroke-width="1" opacity=".3"/>
            <circle cx="98" cy="85" r="4.4" fill="#2c1a0c"/>

            <!-- Cột buồm chính + cánh buồm lớn -->
            <ellipse cx="140" cy="85" rx="14" ry="52" fill="url(#ltrSailGrad)" stroke="#3a2210" stroke-width="2.2" class="ltr-sail ltr-sail-main"/>
            <path d="M140 37 Q148 85 140 133" fill="none" stroke="#3a2210" stroke-width="1" opacity=".3"/>
            <circle cx="140" cy="85" r="5" fill="#2c1a0c"/>

            <!-- Cờ mũi -->
            <path d="M204 85 L219 78 L206 92 Z" fill="#1c1006" stroke="#0a0603" stroke-width="1" class="ltr-flag ltr-flag-main"/>
            <!-- Bọt sóng mũi -->
            <path d="M206 70 Q216 78 219 85 Q216 92 206 100" fill="none" stroke="#dfe9ec" stroke-width="2.4" opacity=".6" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

      <div class="ltr-idle-cta" id="ltr-idle-caption">CHẠM VÀO BẢN ĐỒ ĐỂ BẮT ĐẦU HÀNH TRÌNH</div>
    </main>

    <div class="ltr-marquee-strip" aria-hidden="true">
      <span class="ltr-marquee-text" id="ltr-marquee"></span>
    </div>

    <!-- Marquee lịch sử trúng thưởng (ẩn khi không có data) -->
    <div class="ltr-marquee-strip" aria-hidden="true">
      <span class="ltr-marquee-text" id="ltr-marquee"></span>
    </div>

    <!-- ===== LA BÀN DÒ HƯỚNG (overlay) ===== -->
    <div class="ltr-compass-overlay" id="ltr-compass-overlay" aria-hidden="true">
      <div class="ltr-compass-widget">
        <svg viewBox="0 0 200 200">
          <circle cx="100" cy="100" r="94" fill="rgba(10,26,36,.35)" stroke="#e3bf6a" stroke-width="4"/>
          <circle cx="100" cy="100" r="80" fill="#f0e2bd" stroke="#b98f3c" stroke-width="2"/>
          <circle cx="100" cy="100" r="60" fill="none" stroke="#8a6a3c" stroke-width="1" opacity=".5"/>
          <g stroke="#6e4626" stroke-width="1.4" opacity=".55">
            <line x1="100" y1="24" x2="100" y2="40"/><line x1="100" y1="160" x2="100" y2="176"/>
            <line x1="24" y1="100" x2="40" y2="100"/><line x1="160" y1="100" x2="176" y2="100"/>
          </g>
          <text x="100" y="38" text-anchor="middle" font-size="16" fill="#6e4626" font-family="'Playfair Display',serif">B</text>
          <g id="ltr-compass-needle" class="ltr-needle">
            <polygon points="100,30 91,100 100,104 109,100" fill="#c0392b" stroke="#5a1f18" stroke-width="1.2"/>
            <polygon points="100,170 109,100 100,96 91,100" fill="#ffffff" stroke="#6e6e6e" stroke-width="1.2"/>
          </g>
          <circle cx="100" cy="100" r="8" fill="#e3bf6a" stroke="#4a2c14" stroke-width="2"/>
        </svg>
      </div>
      <div class="ltr-compass-text">ĐANG XÁC ĐỊNH HẢI TRÌNH…</div>
    </div>

    <!-- ===== REVEAL: rương mở + thẻ phần thưởng ===== -->
    <div class="ltr-reveal" id="ltr-reveal" aria-hidden="true">
      <div class="ltr-reveal-backdrop"></div>
      <div class="ltr-reveal-stage">
        <div class="ltr-chest" id="ltr-chest">
          <div class="ltr-chest-rays" aria-hidden="true"></div>
          <div class="ltr-chest-glow" aria-hidden="true"></div>
          <svg viewBox="0 0 200 170" class="ltr-chest-svg" aria-hidden="true">
            <defs>
              <linearGradient id="ltrChestBody" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#b17e46"/><stop offset="1" stop-color="#542f13"/>
              </linearGradient>
              <linearGradient id="ltrChestLid" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#cd9d5e"/><stop offset="1" stop-color="#77471f"/>
              </linearGradient>
              <linearGradient id="ltrGold2" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#fdeab0"/><stop offset="1" stop-color="#c79a3d"/>
              </linearGradient>
            </defs>
            <g class="ltr-chest-body-g">
              <path d="M20,80 L20,148 Q20,162 34,162 L166,162 Q180,162 180,148 L180,80 Z" fill="url(#ltrChestBody)" stroke="#2c1a0c" stroke-width="3.5"/>
              <path d="M22,98 L178,98 M22,118 L178,118 M22,138 L178,138" stroke="#3a2210" stroke-width="1" opacity=".25"/>
              <rect x="20" y="140" width="160" height="11" fill="url(#ltrGold2)"/>
              <rect x="48" y="80" width="15" height="82" fill="url(#ltrGold2)"/>
              <rect x="137" y="80" width="15" height="82" fill="url(#ltrGold2)"/>
              <rect x="86" y="72" width="28" height="34" rx="5" fill="url(#ltrGold2)" stroke="#2c1a0c" stroke-width="2.5"/>
              <circle cx="100" cy="90" r="4.5" fill="#2c1a0c"/><rect x="98" y="90" width="4" height="10" fill="#2c1a0c"/>
            </g>
            <g class="ltr-chest-lid-g">
              <path d="M20,80 Q20,14 100,14 Q180,14 180,80 Z" fill="url(#ltrChestLid)" stroke="#2c1a0c" stroke-width="3.5"/>
              <path d="M30,66 Q100,50 170,66" fill="none" stroke="#3a2210" stroke-width="1.2" opacity=".3"/>
              <rect x="48" y="14" width="15" height="66" fill="url(#ltrGold2)"/>
              <rect x="137" y="14" width="15" height="66" fill="url(#ltrGold2)"/>
              <rect x="86" y="14" width="28" height="20" rx="4" fill="url(#ltrGold2)"/>
            </g>
          </svg>
          <div class="ltr-particles" id="ltr-particles" aria-hidden="true"></div>
        </div>

        <div class="ltr-prize-card" id="ltr-prize-card">
          <div class="ltr-prize-label">KHO BÁU ĐÃ ĐƯỢC TÌM THẤY</div>
          <div class="ltr-prize-image-wrap">
            <img id="ltr-reveal-image" class="ltr-prize-image" alt="">
            <svg class="ltr-prize-placeholder" id="ltr-prize-placeholder" viewBox="0 0 120 100" aria-hidden="true">
              <path d="M18,42 L18,86 Q18,92 24,92 L96,92 Q102,92 102,86 L102,42 Z" fill="#b17e46" stroke="#5a3618" stroke-width="3"/>
              <path d="M18,42 Q18,14 60,14 Q102,14 102,42 Z" fill="#cd9d5e" stroke="#5a3618" stroke-width="3"/>
              <rect x="52" y="36" width="16" height="22" rx="3" fill="#fdeab0" stroke="#5a3618" stroke-width="2.5"/>
              <rect x="46" y="14" width="9" height="78" fill="#fdeab0" opacity=".85"/>
              <rect x="65" y="14" width="9" height="78" fill="#fdeab0" opacity=".85"/>
            </svg>
          </div>
          <div class="ltr-prize-name" id="ltr-reveal-prize">—</div>
        </div>
      </div>
      <div class="ltr-dismiss-hint">Chạm màn hình / Space / Enter để tiếp tục</div>
    </div>

    <!-- Toast lỗi nhỏ, tự ẩn -->
    <div class="ltr-toast" id="ltr-toast" role="status" aria-live="polite"></div>

  </div>

<script>
  window.LTR_DATA = { root: <?php echo wp_json_encode($rest_root); ?>, key: <?php echo wp_json_encode($key); ?> };
</script>
<script src="<?php echo esc_url($js_url); ?>?v=<?php echo esc_attr(LTR_VERSION); ?>"></script>
</body>
</html>
