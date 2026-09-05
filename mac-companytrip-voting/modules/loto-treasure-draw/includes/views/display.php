<?php if (!defined('ABSPATH')) exit; ?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Màn hình Lô Tô Kho Báu — Hải trình săn kho báu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pirata+One&family=Special+Elite&display=swap" rel="stylesheet">
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
        <path id="ltr-route-0" class="ltr-route-line" d="M10,84 C4,58 8,36 20,22" />
        <path id="ltr-route-1" class="ltr-route-line" d="M10,84 C18,52 30,26 48,15" />
        <path id="ltr-route-2" class="ltr-route-line" d="M10,84 C30,50 54,24 78,24" />
        <path id="ltr-route-3" class="ltr-route-line" d="M10,84 C44,90 76,82 83,59" />
        <path id="ltr-route-4" class="ltr-route-line" d="M10,84 C26,85 46,83 62,78" />
        <path id="ltr-route-5" class="ltr-route-line" d="M10,84 C16,80 24,74 31,68" />
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
          <svg viewBox="0 0 260 150" class="ltr-ship-svg" aria-hidden="true">
            <defs>
              <linearGradient id="ltrHull" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#6b3f22"/>
                <stop offset=".5" stop-color="#4a2714"/>
                <stop offset="1" stop-color="#2c160a"/>
              </linearGradient>
              <linearGradient id="ltrDeck" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#b07c44"/>
                <stop offset="1" stop-color="#7c4f27"/>
              </linearGradient>
              <linearGradient id="ltrSail" x1="1" y1="0" x2="0" y2="0">
                <stop offset="0" stop-color="#fbf3df"/>
                <stop offset="1" stop-color="#d8c6a2"/>
              </linearGradient>
              <radialGradient id="ltrShipShadow" cx=".5" cy=".5" r=".5">
                <stop offset="0" stop-color="rgba(4,16,22,.42)"/>
                <stop offset="1" stop-color="rgba(4,16,22,0)"/>
              </radialGradient>
            </defs>

            <!-- 1. bóng nước -->
            <ellipse cx="132" cy="82" rx="116" ry="50" fill="url(#ltrShipShadow)"/>

            <!-- 2. bọt sóng phía lái (-X) -->
            <g class="ltr-ship-wake">
              <path d="M46,75 C28,66 14,68 2,60" fill="none" stroke="#dff0f4" stroke-width="4" stroke-linecap="round" opacity=".55"/>
              <path d="M46,75 C28,84 14,82 2,90" fill="none" stroke="#dff0f4" stroke-width="4" stroke-linecap="round" opacity=".55"/>
              <path d="M50,75 C36,70 24,72 12,75 C24,78 36,80 50,75 Z" fill="#eaf6f8" opacity=".4"/>
            </g>

            <!-- 3. thân tàu -->
            <path d="M44,75 C44,50 80,38 132,37 C186,36 228,54 248,75 C228,96 186,114 132,113 C80,112 44,100 44,75 Z"
                  fill="url(#ltrHull)" stroke="#c9a24a" stroke-width="2.5"/>
            <!-- 4. viền mạn -->
            <path d="M57,75 C57,56 88,47 132,46 C180,45 216,60 233,75 C216,90 180,105 132,104 C88,103 57,94 57,75 Z"
                  fill="none" stroke="#c9a24a" stroke-width="1.2" opacity=".5"/>
            <!-- 5. boong -->
            <path d="M67,75 C67,60 94,52 132,51 C174,50 206,62 221,75 C206,88 174,100 132,99 C94,98 67,90 67,75 Z"
                  fill="url(#ltrDeck)" stroke="#33200f" stroke-width="1.4"/>
            <g stroke="#33200f" stroke-width=".8" opacity=".22" fill="none">
              <path d="M80,64 C106,59 160,59 205,67"/>
              <path d="M76,75 C106,71 166,71 213,75"/>
              <path d="M80,86 C106,91 160,91 205,83"/>
            </g>

            <!-- 6+7. cột buồm & buồm (buồm phồng về phía lái) -->
            <path d="M84,50 C66,62 66,88 84,100 L84,50 Z" fill="url(#ltrSail)" stroke="#8a6a3c" stroke-width="1.2"/>
            <line x1="84" y1="47" x2="84" y2="103" stroke="#2c1a0c" stroke-width="2.4" stroke-linecap="round"/>
            <path d="M132,39 C107,56 107,94 132,111 L132,39 Z" fill="url(#ltrSail)" stroke="#8a6a3c" stroke-width="1.4"/>
            <line x1="132" y1="36" x2="132" y2="114" stroke="#2c1a0c" stroke-width="3" stroke-linecap="round"/>
            <path d="M182,50 C164,62 164,88 182,100 L182,50 Z" fill="url(#ltrSail)" stroke="#8a6a3c" stroke-width="1.2"/>
            <line x1="182" y1="47" x2="182" y2="103" stroke="#2c1a0c" stroke-width="2.4" stroke-linecap="round"/>
            <g stroke="#b6a17c" stroke-width=".8" opacity=".45" fill="none">
              <path d="M120,52 C114,66 114,84 120,98"/>
              <path d="M171,58 C166,68 166,82 171,92"/>
            </g>

            <!-- 9. cờ đuôi tàu -->
            <path d="M46,75 C36,69 28,71 20,66 C27,73 27,77 20,84 C28,79 36,81 46,75 Z" fill="#8a2f28" stroke="#591d18" stroke-width="1"/>

            <!-- mũi tàu (+X) -->
            <line x1="246" y1="75" x2="258" y2="75" stroke="#2c1a0c" stroke-width="2.6" stroke-linecap="round"/>
            <circle cx="248" cy="75" r="2.6" fill="#e0b657"/>

            <!-- 10. highlight -->
            <path d="M98,52 C122,46 168,46 205,56" fill="none" stroke="#ffffff" stroke-width="1.6" opacity=".13" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

      <div class="ltr-idle-cta" id="ltr-idle-caption">CHẠM VÀO BẢN ĐỒ ĐỂ BẮT ĐẦU HÀNH TRÌNH</div>
    </main>

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
          <text x="100" y="38" text-anchor="middle" font-size="16" fill="#6e4626" font-family="'Special Elite',monospace">B</text>
          <g id="ltr-compass-needle" class="ltr-needle">
            <polygon points="100,30 91,100 100,104 109,100" fill="#8a2f28"/>
            <polygon points="100,170 109,100 100,96 91,100" fill="#f3e6c8"/>
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
