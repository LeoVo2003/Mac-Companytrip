<?php if (!defined('ABSPATH')) exit; ?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Màn hình Lô Tô Kho Báu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Pirata+One&family=Special+Elite&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url($css_url); ?>?v=<?php echo esc_attr(LTR_VERSION); ?>">
</head>
<body>
  <div class="ltr-stage" id="ltr-stage">

    <!-- ===== BẢN ĐỒ ===== -->
    <div class="ltr-map" id="ltr-map">
      <div class="ltr-parchment"></div>
      <div class="ltr-fold-sheen"></div>
      <svg class="ltr-route-svg" viewBox="0 0 100 100" preserveAspectRatio="none">
        <path id="ltr-route-0" class="ltr-route-line" d="M6,88 Q35,55 18,26" />
        <path id="ltr-route-1" class="ltr-route-line" d="M6,88 Q45,60 64,16" />
        <path id="ltr-route-2" class="ltr-route-line" d="M6,88 Q55,70 84,46" />
        <path id="ltr-route-3" class="ltr-route-line" d="M6,88 Q40,75 58,66" />
        <path id="ltr-route-4" class="ltr-route-line" d="M6,88 Q15,80 22,70" />
        <path id="ltr-route-5" class="ltr-route-line" d="M6,88 Q30,65 40,42" />
      </svg>
      <svg class="ltr-compass-deco" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="42" fill="none" stroke="#6E4626" stroke-width="1.4" opacity=".7"/>
        <circle cx="50" cy="50" r="34" fill="none" stroke="#6E4626" stroke-width="1" opacity=".5"/>
        <g stroke="#6E4626" stroke-width="1" opacity=".55">
          <line x1="50" y1="8" x2="50" y2="92"/>
          <line x1="8" y1="50" x2="92" y2="50"/>
          <line x1="17" y1="17" x2="83" y2="83"/>
          <line x1="83" y1="17" x2="17" y2="83"/>
        </g>
        <path d="M50 8 L57 46 L50 50 L43 46 Z" fill="#6E4626" opacity=".8"/>
        <path d="M50 92 L57 54 L50 50 L43 54 Z" fill="#6E4626" opacity=".5"/>
        <path d="M8 50 L46 43 L50 50 L46 57 Z" fill="#6E4626" opacity=".5"/>
        <path d="M92 50 L54 43 L50 50 L54 57 Z" fill="#6E4626" opacity=".5"/>
        <circle cx="50" cy="50" r="3.5" fill="#6E4626"/>
        <text x="50" y="20" text-anchor="middle" font-size="9" fill="#6E4626" font-family="'Special Elite',monospace">B</text>
      </svg>

      <!-- chim hải âu trang trí, bay chầm chậm cho bản đồ có sức sống -->
      <svg class="ltr-gull ltr-gull-1" viewBox="0 0 40 16"><path d="M2 10 Q10 0 18 10 Q26 0 34 10" fill="none" stroke="#4a3420" stroke-width="1.6" stroke-linecap="round" opacity=".55"/></svg>
      <svg class="ltr-gull ltr-gull-2" viewBox="0 0 40 16"><path d="M2 10 Q10 0 18 10 Q26 0 34 10" fill="none" stroke="#4a3420" stroke-width="1.4" stroke-linecap="round" opacity=".4"/></svg>

      <div class="ltr-harbor" id="ltr-harbor">
        <svg viewBox="0 0 100 100" class="ltr-harbor-svg">
          <path d="M50 84 a15 6.5 0 1 0 0.1 0" fill="none" stroke="#8a6a3c" stroke-width="2.6" opacity=".5"/>
          <path d="M50 84 a9.5 4.2 0 1 0 0.1 0" fill="none" stroke="#8a6a3c" stroke-width="2.2" opacity=".6"/>
          <g transform="translate(50,42)">
            <circle cx="0" cy="-23" r="7.5" fill="none" stroke="#C79A3D" stroke-width="5"/>
            <rect x="-3" y="-16" width="6" height="36" rx="3" fill="#C79A3D"/>
            <rect x="-15" y="-3" width="30" height="5.5" rx="2.7" fill="#C79A3D"/>
            <path d="M -23 15 Q 0 36 23 15" stroke="#C79A3D" stroke-width="5.5" fill="none" stroke-linecap="round"/>
            <path d="M -23 15 l -6.5 -8.5 M 23 15 l 6.5 -8.5" stroke="#C79A3D" stroke-width="5.5" fill="none" stroke-linecap="round"/>
          </g>
        </svg>
      </div>

      <div id="ltr-spots"></div>

      <!-- ===== THUYỀN (phong cách khắc nét trên bản đồ cổ) ===== -->
      <div class="ltr-ship" id="ltr-ship">
        <div class="ltr-ship-shadow"></div>
        <div class="ltr-ship-wake"></div>
        <div class="ltr-ship-inner">
          <svg viewBox="0 0 220 170" class="ltr-ship-svg">
            <!-- vệt sóng dưới mũi thuyền -->
            <path d="M6 138 Q30 132 46 138 Q64 144 80 138" fill="none" stroke="#3a2210" stroke-width="2" opacity=".4" stroke-linecap="round"/>

            <!-- thân thuyền: khối đặc, viền vàng mảnh kiểu bản khắc -->
            <path d="M14 128 C14 146 60 156 110 156 C160 156 206 146 206 128
                     C198 122 178 120 158 123 C132 127 88 127 62 123
                     C42 120 22 122 14 128 Z"
                  fill="#3a2210" stroke="#C79A3D" stroke-width="2"/>
            <path d="M30 128 Q110 140 190 128" fill="none" stroke="#C79A3D" stroke-width="1.4" opacity=".6"/>

            <!-- cột buồm sau (thấp hơn) -->
            <line x1="158" y1="128" x2="158" y2="66" stroke="#2c1a0c" stroke-width="3" stroke-linecap="round"/>
            <path d="M158 72 Q136 84 140 116 Q158 108 158 72 Z" fill="#F3E8CF" stroke="#3a2210" stroke-width="2" class="ltr-sail ltr-sail-jib"/>
            <path d="M144 84 Q150 96 146 110" fill="none" stroke="#3a2210" stroke-width="1" opacity=".35"/>
            <path d="M158 60 L176 66 L158 72 Z" fill="#8A3A32" stroke="#3a2210" stroke-width="1" class="ltr-flag"/>

            <!-- cột buồm chính -->
            <line x1="92" y1="128" x2="92" y2="18" stroke="#2c1a0c" stroke-width="4" stroke-linecap="round"/>
            <line x1="62" y1="40" x2="122" y2="40" stroke="#2c1a0c" stroke-width="2.5" stroke-linecap="round"/>
            <path d="M92 26 Q52 48 58 108 Q92 96 92 26 Z" fill="#FBF2DC" stroke="#3a2210" stroke-width="2" class="ltr-sail ltr-sail-main"/>
            <path d="M72 44 Q66 70 70 100 M82 36 Q76 68 80 104" fill="none" stroke="#3a2210" stroke-width="1" opacity=".3"/>
            <path d="M92 16 L118 8 L92 40 Z" fill="#8A3A32" stroke="#3a2210" stroke-width="1" class="ltr-flag ltr-flag-main"/>

            <!-- mũi thuyền vươn ra (bowsprit) -->
            <line x1="14" y1="126" x2="-10" y2="112" stroke="#2c1a0c" stroke-width="3" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

      <div class="ltr-idle-caption" id="ltr-idle-caption">Đang chờ MC dò la bàn tìm kho báu...</div>
    </div>
    <!-- ===== LA BÀN DÒ HƯỚNG ===== -->
    <div class="ltr-compass-overlay" id="ltr-compass-overlay">
      <div class="ltr-compass-widget">
        <svg viewBox="0 0 200 200">
          <circle cx="100" cy="100" r="92" fill="rgba(234,217,174,.12)" stroke="#F3CE72" stroke-width="3"/>
          <circle cx="100" cy="100" r="72" fill="none" stroke="#F3CE72" stroke-width="1" opacity=".5"/>
          <g stroke="#F3CE72" stroke-width="1.5" opacity=".6">
            <line x1="100" y1="10" x2="100" y2="30"/>
            <line x1="100" y1="170" x2="100" y2="190"/>
            <line x1="10" y1="100" x2="30" y2="100"/>
            <line x1="170" y1="100" x2="190" y2="100"/>
          </g>
          <text x="100" y="24" text-anchor="middle" font-size="14" fill="#F3CE72" font-family="'Special Elite',monospace">B</text>
          <g id="ltr-compass-needle" class="ltr-needle">
            <polygon points="100,26 90,100 100,100" fill="#8A3A32"/>
            <polygon points="100,174 110,100 100,100" fill="#EAD9AE"/>
          </g>
          <circle cx="100" cy="100" r="7" fill="#F3CE72" stroke="#4a2c14" stroke-width="2"/>
        </svg>
      </div>
      <div class="ltr-compass-text">Đang dò la bàn...</div>
    </div>

    <!-- ===== POPUP MỞ RƯƠNG + PHẦN THƯỞNG ===== -->
    <div class="ltr-reveal" id="ltr-reveal">
      <div class="ltr-reveal-stage">

        <div class="ltr-big-chest" id="ltr-big-chest">
          <div class="ltr-rays"></div>
          <div class="ltr-glow"></div>
          <svg viewBox="0 0 200 170" class="ltr-chest-svg">
            <defs>
              <linearGradient id="ltrChestBody" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#b17e46"/>
                <stop offset="1" stop-color="#5a3618"/>
              </linearGradient>
              <linearGradient id="ltrChestLid" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#c99a5c"/>
                <stop offset="1" stop-color="#7a4a24"/>
              </linearGradient>
              <linearGradient id="ltrGold" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#FCE7A6"/>
                <stop offset="1" stop-color="#C79A3D"/>
              </linearGradient>
            </defs>

            <!-- thân rương (cố định) -->
            <g class="ltr-chest-body-g">
              <path d="M20,80 L20,148 Q20,162 34,162 L166,162 Q180,162 180,148 L180,80 Z"
                    fill="url(#ltrChestBody)" stroke="#2c1a0c" stroke-width="3.5"/>
              <path d="M22,96 L178,96 M22,116 L178,116 M22,136 L178,136" stroke="#3a2210" stroke-width="1" opacity=".25"/>
              <rect x="20" y="140" width="160" height="11" fill="url(#ltrGold)"/>
              <rect x="48" y="80" width="15" height="82" fill="url(#ltrGold)"/>
              <rect x="137" y="80" width="15" height="82" fill="url(#ltrGold)"/>
              <rect x="86" y="72" width="28" height="34" rx="5" fill="url(#ltrGold)" stroke="#2c1a0c" stroke-width="2.5"/>
              <circle cx="100" cy="90" r="4.5" fill="#2c1a0c"/>
              <rect x="98" y="90" width="4" height="10" fill="#2c1a0c"/>
            </g>

            <!-- nắp rương vòm (bung ra khi mở) -->
            <g class="ltr-chest-lid-g">
              <path d="M20,80 Q20,14 100,14 Q180,14 180,80 Z"
                    fill="url(#ltrChestLid)" stroke="#2c1a0c" stroke-width="3.5"/>
              <path d="M30,66 Q100,50 170,66" fill="none" stroke="#3a2210" stroke-width="1.2" opacity=".3"/>
              <rect x="48" y="14" width="15" height="66" fill="url(#ltrGold)"/>
              <rect x="137" y="14" width="15" height="66" fill="url(#ltrGold)"/>
              <rect x="86" y="14" width="28" height="20" rx="4" fill="url(#ltrGold)"/>
            </g>
          </svg>
        </div>

        <div class="ltr-prize-pop" id="ltr-prize-pop">
          <svg class="ltr-corner ltr-corner-tl" viewBox="0 0 60 60"><path d="M4 4 Q4 30 30 30" fill="none" stroke="#C79A3D" stroke-width="2"/><circle cx="30" cy="30" r="2.6" fill="#C79A3D"/></svg>
          <svg class="ltr-corner ltr-corner-tr" viewBox="0 0 60 60"><path d="M4 4 Q4 30 30 30" fill="none" stroke="#C79A3D" stroke-width="2"/><circle cx="30" cy="30" r="2.6" fill="#C79A3D"/></svg>
          <svg class="ltr-corner ltr-corner-bl" viewBox="0 0 60 60"><path d="M4 4 Q4 30 30 30" fill="none" stroke="#C79A3D" stroke-width="2"/><circle cx="30" cy="30" r="2.6" fill="#C79A3D"/></svg>
          <svg class="ltr-corner ltr-corner-br" viewBox="0 0 60 60"><path d="M4 4 Q4 30 30 30" fill="none" stroke="#C79A3D" stroke-width="2"/><circle cx="30" cy="30" r="2.6" fill="#C79A3D"/></svg>

          <div class="ltr-reveal-label">
            <svg class="ltr-seal" viewBox="0 0 40 40"><circle cx="20" cy="20" r="17" fill="none" stroke="#8A3A32" stroke-width="2"/><path d="M20 10v20M13 15l7-5 7 5M14 24h12" stroke="#8A3A32" stroke-width="1.6" fill="none" stroke-linecap="round"/></svg>
            <span>Phần thưởng của bạn</span>
            <svg class="ltr-seal" viewBox="0 0 40 40"><circle cx="20" cy="20" r="17" fill="none" stroke="#8A3A32" stroke-width="2"/><path d="M20 10v20M13 15l7-5 7 5M14 24h12" stroke="#8A3A32" stroke-width="1.6" fill="none" stroke-linecap="round"/></svg>
          </div>
          <div class="ltr-reveal-image-wrap">
            <div class="ltr-image-glow"></div>
            <img id="ltr-reveal-image" class="ltr-reveal-image" src="" alt="">
          </div>
          <div class="ltr-reveal-prize" id="ltr-reveal-prize">—</div>
        </div>

      </div>
      <div class="ltr-dismiss-hint">Chạm màn hình để tiếp tục</div>
    </div>

  </div>

<script>
  window.LTR_DATA = { root: <?php echo wp_json_encode($rest_root); ?>, key: <?php echo wp_json_encode($key); ?> };
</script>
<script src="<?php echo esc_url($js_url); ?>?v=<?php echo esc_attr(LTR_VERSION); ?>"></script>
</body>
</html>
