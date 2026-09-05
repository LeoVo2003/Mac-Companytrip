(function () {
  "use strict";

  /* ======================================================================
     Lô Tô Kho Báu — bộ điều khiển màn hình LED "Treasure Voyage" v2.1.0
     ----------------------------------------------------------------------
     - FIXED: Thuyền mũi về +X (0° = phải), không còn lệch 180° khi di chuyển.
     - FIXED: Tốc độ thuyền đồng đều — durationMs tính theo chiều dài thực path.
     - FIXED: 6 kho báu bố trí lại (không chồng lấn), routes tương thích.
     - ENHANCED: Bản đồ biển điện ảnh + sóngParallax + mây.
     - ENHANCED: 6 đảo khác biệt (dừa, mỏm đá, hang động, xác tàu, hải đăng, núi lửa).
     - ENHANCED: Camera zoom nhẹ khi cập bến + screen-shake lúc mở rương.
     - ENHANCED: Prize card thở nhẹ, badge số quà còn lại, marquee lịch sử.
     - GIỮ NGUYÊN contract REST ltr/v1 và event { event_id, type, spot, prize }.
     ====================================================================== */

  var DATA = window.LTR_DATA || {};
  var ROOT = DATA.root;
  var KEY = DATA.key || '';

  /* ---------- Hằng số bố cục (hệ toạ độ chuẩn hoá 0..100) ---------- */
  var TREASURE_SPOTS = [
    { id: 0, x: 22, y: 18 },
    { id: 1, x: 50, y: 12 },
    { id: 2, x: 80, y: 22 },
    { id: 3, x: 86, y: 55 },
    { id: 4, x: 62, y: 80 },
    { id: 5, x: 28, y: 60 }
  ];
  var HARBOR = { x: 12, y: 80 };
  var DYN_ROUTE_ID = 'ltr-route-dyn';

  var SHIP_HEADING_OFFSET = 0;    // Mũi thuyền đã hướng +X trong SVG mới → offset 0.
  var BOW_OFFSET_RATIO = 0.52;    // Mũi tàu cách tâm ~0.52 (viewBox 220, mũi ~x198).
  var SHIP_SPEED = 280;           // px/s
  var POLL_MS = 1200;

  var DISPLAY_STATE = {
    IDLE: 'idle', COMPASS: 'compass', ROUTE_LOCK: 'route-lock',
    SAILING: 'sailing', ARRIVAL: 'arrival', REVEAL: 'reveal'
  };

  // 6 icon đảo khác biệt
  var ISLAND_ICONS = [
    '<svg viewBox="0 0 80 80"><ellipse cx="40" cy="60" rx="26" ry="9" fill="#d9b26a" stroke="#2c1a0c" stroke-width="2"/><ellipse cx="40" cy="57" rx="20" ry="6" fill="#a97c3f" opacity=".5"/><path d="M40,58 L44,26" stroke="#5c3417" stroke-width="3.6" stroke-linecap="round"/><path d="M44,26 Q30,20 20,26 Q32,24 44,31 Z" fill="#3e6b3a" stroke="#2c1a0c" stroke-width="1.6"/><path d="M44,26 Q58,18 68,23 Q56,23 44,31 Z" fill="#3e6b3a" stroke="#2c1a0c" stroke-width="1.6"/><path d="M44,26 Q40,14 32,10 Q40,18 44,30 Z" fill="#274a26" stroke="#2c1a0c" stroke-width="1.6"/><path d="M44,26 Q50,12 60,10 Q50,18 44,30 Z" fill="#274a26" stroke="#2c1a0c" stroke-width="1.6"/></svg>',
    '<svg viewBox="0 0 80 80"><ellipse cx="40" cy="63" rx="24" ry="7" fill="#d9b26a" stroke="#2c1a0c" stroke-width="2"/><path d="M22,56 Q18,32 40,26 Q62,32 58,56 Q52,64 40,64 Q28,64 22,56 Z" fill="#6b6b6b" stroke="#2c1a0c" stroke-width="2.2"/><ellipse cx="31" cy="42" rx="6" ry="7.5" fill="#3d3d3d"/><ellipse cx="49" cy="42" rx="6" ry="7.5" fill="#3d3d3d"/><path d="M34,54 Q40,58 46,54 L44,58 L42,54 L40,58 L38,54 L36,58 Z" fill="#3d3d3d"/></svg>',
    '<svg viewBox="0 0 80 80"><ellipse cx="40" cy="63" rx="26" ry="8" fill="#d9b26a" stroke="#2c1a0c" stroke-width="2"/><path d="M14,58 Q10,30 40,22 Q70,30 66,58 Z" fill="#9c8a6f" stroke="#2c1a0c" stroke-width="2.2"/><path d="M40,58 Q26,58 26,44 Q26,32 40,32 Q54,32 54,44 Q54,58 40,58 Z" fill="#2c1a0c"/><path d="M28,46 Q40,36 52,46" fill="none" stroke="#6b5a42" stroke-width="2" opacity=".6"/></svg>',
    '<svg viewBox="0 0 80 80"><ellipse cx="40" cy="64" rx="26" ry="8" fill="#d9b26a" stroke="#2c1a0c" stroke-width="2"/><path d="M14,56 Q20,68 42,66 Q58,64 60,54 Q52,60 34,60 Q20,60 14,56 Z" fill="#5c3417" stroke="#2c1a0c" stroke-width="2.2"/><line x1="30" y1="56" x2="24" y2="22" stroke="#3a2210" stroke-width="3.6" stroke-linecap="round"/><path d="M24,22 L30,28 L22,32 Z" fill="#3a2210"/><path d="M30,30 Q42,34 46,42 Q36,40 28,44 Z" fill="#e7ddc4" stroke="#2c1a0c" stroke-width="1.6"/><circle cx="46" cy="58" r="2.6" fill="#2c1a0c" opacity=".75"/><circle cx="24" cy="58" r="2.2" fill="#2c1a0c" opacity=".75"/></svg>',
    '<svg viewBox="0 0 80 80"><ellipse cx="40" cy="64" rx="22" ry="7" fill="#d9b26a" stroke="#2c1a0c" stroke-width="2"/><path d="M33,60 L36,24 Q40,18 44,24 L47,60 Z" fill="#e7e0d0" stroke="#2c1a0c" stroke-width="2.2"/><path d="M35,44 L45,44 L44,54 L36,54 Z" fill="#c0522a" stroke="#2c1a0c" stroke-width="1.6"/><path d="M36,24 L44,24 L46,30 L34,30 Z" fill="#c0522a" stroke="#2c1a0c" stroke-width="1.8"/><rect x="37" y="16" width="6" height="8" rx="1" fill="#EAD9AE" stroke="#2c1a0c" stroke-width="1.6"/><circle cx="40" cy="13" r="2.2" fill="#EAD9AE" stroke="#2c1a0c" stroke-width="1.4"/></svg>',
    '<svg viewBox="0 0 80 80"><ellipse cx="40" cy="64" rx="28" ry="8" fill="#d9b26a" stroke="#2c1a0c" stroke-width="2"/><path d="M14,60 Q40,18 45,18 Q34,26 40,32 Q46,26 35,18 Q40,18 66,60 Z" fill="#3d3d3d" stroke="#2c1a0c" stroke-width="2.2"/><path d="M35,18 Q40,24 45,18 Q42,22 40,26 Q38,22 35,18 Z" fill="#c0522a"/><path d="M30,60 Q40,52 50,60 Z" fill="#6b6b6b" opacity=".55"/><path d="M40,18 Q46,10 42,2 Q38,8 44,14" fill="none" stroke="#cfcfcf" stroke-width="2.2" opacity=".5" stroke-linecap="round"/></svg>'
  ];

  /* ---------- Tham chiếu DOM ---------- */
  var stage = document.getElementById('ltr-stage');
  var mapEl = document.getElementById('ltr-map');
  var shipEl = document.getElementById('ltr-ship');
  var spotsContainer = document.getElementById('ltr-spots');
  var idleCaption = document.getElementById('ltr-idle-caption');
  var harborEl = document.getElementById('ltr-harbor');
  var revealEl = document.getElementById('ltr-reveal');
  var particlesEl = document.getElementById('ltr-particles');
  var revealImage = document.getElementById('ltr-reveal-image');
  var revealPrize = document.getElementById('ltr-reveal-prize');
  var imageWrap = revealEl ? revealEl.querySelector('.ltr-prize-image-wrap') : null;
  var toastEl = document.getElementById('ltr-toast');
  var compassOverlay = document.getElementById('ltr-compass-overlay');
  var compassNeedle = document.getElementById('ltr-compass-needle');

  var routeSvg = mapEl ? mapEl.querySelector('.ltr-route-svg') : null;
  var routeDynEl = document.getElementById(DYN_ROUTE_ID);

  /* ---------- Trạng thái runtime ---------- */
  var currentState = DISPLAY_STATE.IDLE;
  var currentSeq = 0;
  var pendingTimeouts = [];
  var activeAnims = [];
  var sailRafId = null;
  var spotEls = [];
  var remainingTotal = 0;
  var requesting = false;
  var lastEventId = null;
  var hasPolled = false;
  var shipHeading = 0;
  var historyItems = [];

  // Wake foam pool
  var wakeLayer = null;
  var foamPool = [];
  var foamIndex = 0;
  var lastFoamTime = 0;
  var foamInterval = 100;

  //visited tracking
  var visitedSpots = [];

  var toastTimer = null;
  var _svgPt = null;

  /* ======================================================================
     Tiện ích nhỏ
     ====================================================================== */
  function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function rand(a, b) { return a + Math.random() * (b - a); }
  function smootherstep(t) { return t * t * t * (t * (t * 6 - 15) + 10); }
  function reducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function after(ms, seq, fn) {
    var id = setTimeout(function () {
      if (seq !== currentSeq) return;
      fn();
    }, ms);
    pendingTimeouts.push(id);
    return id;
  }

  function normalizeAngle(a) {
    a = a % 360;
    if (a > 180) a -= 360;
    if (a < -180) a += 360;
    return a;
  }
  function lerpAngle(prev, target, t) {
    var diff = normalizeAngle(target - prev);
    return prev + diff * t;
  }

  /* ======================================================================
     State machine
     ====================================================================== */
  function setDisplayState(next) {
    currentState = next;
    if (stage) stage.dataset.state = next;
    syncClickable();
  }

  function syncClickable() {
    if (mapEl) mapEl.classList.toggle('is-clickable', currentState === DISPLAY_STATE.IDLE && remainingTotal > 0);
  }

  function clearSequence() {
    currentSeq++;
    pendingTimeouts.forEach(function (id) { clearTimeout(id); });
    pendingTimeouts = [];
    if (sailRafId) { cancelAnimationFrame(sailRafId); sailRafId = null; }
    activeAnims.forEach(function (a) { try { a.cancel(); } catch (e) {} });
    activeAnims = [];
    if (shipEl) shipEl.classList.remove('is-sailing');
    // stop camera zoom
    if (mapEl) { mapEl.style.transform = ''; mapEl.style.transformOrigin = ''; }
    cancelFoam();
  }

  /* ======================================================================
     Geometry
     ====================================================================== */
  function getMapRect() { return mapEl.getBoundingClientRect(); }

  function svgUserPointToLocalPx(ux, uy, ctm, rect) {
    if (!_svgPt) _svgPt = routeSvg.createSVGPoint();
    _svgPt.x = ux; _svgPt.y = uy;
    var sp = _svgPt.matrixTransform(ctm);
    return { x: sp.x - rect.left, y: sp.y - rect.top };
  }

  function getPathPointPx(pathEl, u) {
    var ctm = routeSvg.getScreenCTM();
    var rect = getMapRect();
    var p = pathEl.getPointAtLength(u);
    return svgUserPointToLocalPx(p.x, p.y, ctm, rect);
  }

  function getTreasurePointPx(idx) {
    var rect = getMapRect();
    var s = TREASURE_SPOTS[idx];
    return { x: s.x / 100 * rect.width, y: s.y / 100 * rect.height };
  }

  function getHarborPointPx() {
    var rect = getMapRect();
    return { x: HARBOR.x / 100 * rect.width, y: HARBOR.y / 100 * rect.height };
  }

  function computePixelLength(pathEl, userLen) {
    var steps = 48, cum = 0, prev = null;
    var ctm = routeSvg.getScreenCTM(), rect = getMapRect();
    for (var i = 0; i <= steps; i++) {
      var p = pathEl.getPointAtLength(userLen * i / steps);
      var px = svgUserPointToLocalPx(p.x, p.y, ctm, rect);
      if (prev) cum += Math.hypot(px.x - prev.x, px.y - prev.y);
      prev = px;
    }
    return cum;
  }

  function endPxToUser(pathEl, userLen, stopPx) {
    var ctm = routeSvg.getScreenCTM(), rect = getMapRect();
    var u0 = userLen * 0.88, u1 = userLen;
    var a = pathEl.getPointAtLength(u0), b = pathEl.getPointAtLength(u1);
    var pa = svgUserPointToLocalPx(a.x, a.y, ctm, rect);
    var pb = svgUserPointToLocalPx(b.x, b.y, ctm, rect);
    var dpx = Math.hypot(pb.x - pa.x, pb.y - pa.y);
    if (dpx < 0.001) return 0;
    return clamp(stopPx * (u1 - u0) / dpx, 0, userLen * 0.6);
  }

  /* ======================================================================
     Layout — dựng 6 đảo với icon riêng biệt
     ====================================================================== */
  function buildTreasureSpots() {
    if (!spotsContainer) return;
    spotsContainer.innerHTML = '';
    spotEls = TREASURE_SPOTS.map(function (spot, i) {
      var el = document.createElement('div');
      el.className = 'ltr-spot';
      el.style.left = spot.x + '%';
      el.style.top = spot.y + '%';
      el.style.setProperty('--i', String(i));
      el.innerHTML =
        '<div class="ltr-spot-halo"></div>' +
        '<div class="ltr-island-icon">' + ISLAND_ICONS[i % ISLAND_ICONS.length] + '</div>' +
        '<div class="ltr-spot-marker"><svg viewBox="0 0 40 40" aria-hidden="true">' +
          '<path d="M12 12 L28 28 M28 12 L12 28" fill="none" stroke="#8a2f28" stroke-width="4.6" stroke-linecap="round"/>' +
        '</svg></div>' +
        '<div class="ltr-spot-beacon"></div>';
      spotsContainer.appendChild(el);
      return el;
    });
  }

  function rectsOverlap(a, b) {
    return !(a.right <= b.left || a.left >= b.right || a.bottom <= b.top || a.top >= b.bottom);
  }
  function escalateShrink(el) {
    if (el.classList.contains('is-mini')) return;
    if (el.classList.contains('is-compact')) { el.classList.remove('is-compact'); el.classList.add('is-mini'); }
    else el.classList.add('is-compact');
  }

  function validateTreasureLayout() {
    if (!spotEls.length) return;
    var cta = idleCaption ? idleCaption.getBoundingClientRect() : null;
    var harbor = harborEl ? harborEl.getBoundingClientRect() : null;
    spotEls.forEach(function (el) { el.classList.remove('is-compact', 'is-mini'); });
    for (var pass = 0; pass < 2; pass++) {
      var changed = false;
      for (var i = 0; i < spotEls.length; i++) {
        var ri = spotEls[i].getBoundingClientRect();
        for (var j = i + 1; j < spotEls.length; j++) {
          if (rectsOverlap(ri, spotEls[j].getBoundingClientRect())) {
            escalateShrink(spotEls[i]); escalateShrink(spotEls[j]); changed = true;
          }
        }
        if (harbor && rectsOverlap(ri, harbor)) { escalateShrink(spotEls[i]); changed = true; }
        if (cta && rectsOverlap(ri, cta)) { escalateShrink(spotEls[i]); changed = true; }
      }
      if (!changed) break;
    }
  }

  var resizeTimer = null;
  function handleResize() {
    if (resizeTimer) clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      validateTreasureLayout();
      if (currentState === DISPLAY_STATE.IDLE) resetShipToHarbor();
    }, 160);
  }

  /* ======================================================================
     Route
     ====================================================================== */
  // Dựng đường hải trình ngoằn ngèo (dynamic) từ cảng tới đảo trúng thưởng.
  function windingPathD(sx, sy, ex, ey, seed) {
    var dx = ex - sx, dy = ey - sy;
    var dist = Math.hypot(dx, dy) || 1;
    var px = -dy / dist, py = dx / dist;
    var sign = (seed % 2 === 0) ? 1 : -1;
    var amp = clamp(dist * 0.22, 5, 20);
    var w = [
      { x: sx, y: sy },
      { x: sx + dx * 0.34 + px * amp * sign, y: sy + dy * 0.34 + py * amp * sign },
      { x: sx + dx * 0.68 - px * amp * 0.85 * sign, y: sy + dy * 0.68 - py * amp * 0.85 * sign },
      { x: ex, y: ey }
    ];
    function f(v) { return v.toFixed(2); }
    var d = 'M ' + f(w[0].x) + ' ' + f(w[0].y);
    for (var i = 1; i < w.length - 1; i++) {
      var xc = (w[i].x + w[i + 1].x) / 2, yc = (w[i].y + w[i + 1].y) / 2;
      d += ' Q ' + f(w[i].x) + ' ' + f(w[i].y) + ' ' + f(xc) + ' ' + f(yc);
    }
    d += ' L ' + f(w[w.length - 1].x) + ' ' + f(w[w.length - 1].y);
    return d;
  }

  function prepareSelectedRoute(idx) {
    if (!routeDynEl) return;
    var s = HARBOR;
    var t = TREASURE_SPOTS[idx];
    routeDynEl.setAttribute('d', windingPathD(s.x, s.y, t.x, t.y, idx));
    if (routeSvg) routeSvg.classList.add('has-selection');
    routeDynEl.classList.add('is-selected');
  }

  function animateRouteDraw() {
    var pathEl = routeDynEl;
    if (!pathEl) return;
    var len = pathEl.getTotalLength();
    if (!len) return;
    pathEl.style.strokeDasharray = String(len);
    pathEl.style.strokeDashoffset = String(len);
    var anim = pathEl.animate(
      [{ strokeDashoffset: len }, { strokeDashoffset: 0 }],
      { duration: reducedMotion() ? 200 : 700, easing: 'cubic-bezier(.2,.75,.25,1)', fill: 'forwards' }
    );
    activeAnims.push(anim);
  }

  function clearRouteSelection() {
    if (routeSvg) routeSvg.classList.remove('has-selection');
    if (!routeDynEl) return;
    routeDynEl.classList.remove('is-selected');
    if (routeDynEl.getAnimations) routeDynEl.getAnimations().forEach(function (a) { a.cancel(); });
    routeDynEl.style.strokeDasharray = '';
    routeDynEl.style.strokeDashoffset = '';
    routeDynEl.setAttribute('d', '');
  }

  /* ======================================================================
     Thuyền — FIXED: mũi tàu đúng hướng + tốc độ đồng đều
     ====================================================================== */
  function setShipTransformPx(x, y, angleDeg) {
    if (!shipEl) return;
    shipEl.style.transform =
      'translate3d(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px,0) ' +
      'translate(-50%,-50%) rotate(' + (angleDeg + SHIP_HEADING_OFFSET).toFixed(2) + 'deg)';
  }

  function resetShipToHarbor() {
    var h = getHarborPointPx();
    var rect = getMapRect();
    // kẹp thuyền nằm gọn trong bảng (tránh dính viền)
    var halfW = (shipEl ? shipEl.offsetWidth : 120) / 2;
    var halfH = (shipEl ? shipEl.offsetHeight : 90) / 2;
    h.x = clamp(h.x, halfW * 0.9, rect.width - halfW * 0.9);
    h.y = clamp(h.y, halfH * 0.9, rect.height - halfH * 0.9);
    // heading từ cảng vào giữa biển (tự nhiên)
    shipHeading = Math.atan2(rect.height * 0.46 - h.y, rect.width * 0.5 - h.x) * 180 / Math.PI;
    if (shipEl) shipEl.classList.remove('is-sailing');
    setShipTransformPx(h.x, h.y, shipHeading);
  }

  function buildWakePool() {
    if (!mapEl) return;
    wakeLayer = document.createElement('div');
    wakeLayer.className = 'ltr-wake-layer';
    wakeLayer.setAttribute('aria-hidden', 'true');
    mapEl.appendChild(wakeLayer);
    for (var i = 0; i < 10; i++) {
      var f = document.createElement('div');
      f.className = 'ltr-wake-foam';
      wakeLayer.appendChild(f);
      foamPool.push(f);
    }
  }
  function cancelFoam() {
    foamPool.forEach(function (el) {
      if (el.getAnimations) el.getAnimations().forEach(function (a) { a.cancel(); });
    });
  }
  function spawnFoam(ts, px, headingDeg, shipLenPx) {
    if (ts - lastFoamTime < foamInterval) return;
    lastFoamTime = ts;
    foamInterval = rand(80, 120);
    var el = foamPool[foamIndex % foamPool.length];
    foamIndex++;
    if (el.getAnimations) el.getAnimations().forEach(function (a) { a.cancel(); });
    var rad = headingDeg * Math.PI / 180;
    var back = shipLenPx * 0.42;
    var sx = px.x - Math.cos(rad) * back + rand(-6, 6);
    var sy = px.y - Math.sin(rad) * back + rand(-6, 6);
    var dx = -Math.cos(rad) * rand(6, 18) + rand(-7, 7);
    var dy = -Math.sin(rad) * rand(6, 18) + rand(-7, 7);
    el.animate([
      { transform: 'translate3d(' + sx + 'px,' + sy + 'px,0) translate(-50%,-50%) scale(.4)', opacity: 0 },
      { transform: 'translate3d(' + (sx + dx * 0.4) + 'px,' + (sy + dy * 0.4) + 'px,0) translate(-50%,-50%) scale(.95)', opacity: 0.6, offset: 0.3 },
      { transform: 'translate3d(' + (sx + dx) + 'px,' + (sy + dy) + 'px,0) translate(-50%,-50%) scale(1.25)', opacity: 0 }
    ], { duration: rand(560, 760), easing: 'ease-out' });
  }

  // FIXED: duration tính theo chiều dài path, lookahead cố định tuyệt đối
  function sailAlongPath(idx, seq, onArrive) {
    var pathEl = routeDynEl;
    if (!pathEl || !routeSvg) { if (onArrive) onArrive(); return; }

    setDisplayState(DISPLAY_STATE.SAILING);
    shipEl.classList.add('is-sailing');

    var userLen = pathEl.getTotalLength();
    var pixelLen = computePixelLength(pathEl, userLen);
    var shipLenPx = shipEl.offsetWidth || 120;
    var spotRadiusPx = (spotEls[idx] ? spotEls[idx].offsetWidth : 60) / 2;
    var stopPx = BOW_OFFSET_RATIO * shipLenPx + 0.95 * spotRadiusPx;
    var stopUser = endPxToUser(pathEl, userLen, stopPx);
    var travelUserLen = Math.max(userLen * 0.25, userLen - stopUser);

    // FIXED: durationMs tỉ lệ với chiều dài, không gán cứng
    var SPEED_MS_PER_UNIT = 22;
    var MIN_DURATION = 1600;
    var duration = reducedMotion() ? 900 : Math.max(MIN_DURATION, pixelLen / SHIP_SPEED * 1000);

    // FIXED: lookahead cố định tuyệt đối (3 đơn vị SVG)
    var LOOKAHEAD = 3;

    var t0 = null;
    lastFoamTime = 0;
    foamInterval = rand(80, 120);

    // Camera zoom nhẹ khi thuyền đến gần đích
    var targetSpot = getTreasurePointPx(idx);
    var zoomStarted = false;
    var ZOOM_THRESHOLD = 0.75; // bắt đầu zoom khi tới 75% đường

    function frame(ts) {
      if (seq !== currentSeq) { sailRafId = null; return; }
      if (t0 === null) t0 = ts;
      var rawT = Math.min(1, (ts - t0) / duration);
      var u = smootherstep(rawT) * travelUserLen;

      var ctm = routeSvg.getScreenCTM();
      var rect = getMapRect();
      if (!ctm) { sailRafId = requestAnimationFrame(frame); return; }

      var p = pathEl.getPointAtLength(u);
      var px = svgUserPointToLocalPx(p.x, p.y, ctm, rect);
      // FIXED: lookahead cố định thay vì % của len
      var pAhead = pathEl.getPointAtLength(Math.min(userLen, u + LOOKAHEAD));
      var pxA = svgUserPointToLocalPx(pAhead.x, pAhead.y, ctm, rect);

      var targetHeading = Math.atan2(pxA.y - px.y, pxA.x - px.x) * 180 / Math.PI;
      shipHeading = lerpAngle(shipHeading, targetHeading, reducedMotion() ? 0.5 : 0.18);
      setShipTransformPx(px.x, px.y, shipHeading);

      if (!reducedMotion()) spawnFoam(ts, px, shipHeading, shipLenPx);

      // Camera zoom when nearing destination
      if (rawT >= ZOOM_THRESHOLD && !zoomStarted) {
        zoomStarted = true;
        var mapRect = getMapRect();
        var dx = (targetSpot.x - mapRect.width / 2);
        var dy = (targetSpot.y - mapRect.height / 2);
        mapEl.style.transition = 'transform 1.2s cubic-bezier(.4,0,.2,1)';
        mapEl.style.transformOrigin = targetSpot.x + 'px ' + targetSpot.y + 'px';
        mapEl.style.transform = 'scale(1.07) translate(' + (dx * 0.02) + 'px,' + (dy * 0.02) + 'px)';
      }

      if (rawT < 1) {
        sailRafId = requestAnimationFrame(frame);
      } else {
        sailRafId = null;
        shipEl.classList.remove('is-sailing');
        cancelFoam();
        if (seq === currentSeq && onArrive) onArrive();
      }
    }
    sailRafId = requestAnimationFrame(frame);
  }

  /* ======================================================================
     La bàn
     ====================================================================== */
  function showCompassForTarget(idx, seq, onSettled) {
    var target = getTreasurePointPx(idx);
    var harbor = getHarborPointPx();
    var bearing = Math.atan2(target.y - harbor.y, target.x - harbor.x) * 180 / Math.PI;
    var posAngle = ((bearing + 90) % 360 + 360) % 360;
    var spins = reducedMotion() ? 0.6 : rand(2.5, 3.5);
    var totalDeg = spins * 360 + posAngle;
    var dur = reducedMotion() ? 520 : rand(1200, 1500);

    if (compassOverlay) compassOverlay.classList.add('is-active');
    if (compassNeedle) {
      var anim = compassNeedle.animate(
        [{ transform: 'rotate(0deg)' }, { transform: 'rotate(' + totalDeg.toFixed(2) + 'deg)' }],
        { duration: dur, easing: 'cubic-bezier(.16,.84,.24,1)', fill: 'forwards' }
      );
      activeAnims.push(anim);
    }
    after(dur + 40, seq, function () { if (onSettled) onSettled(); });
  }

  function hideCompass() {
    if (compassOverlay) compassOverlay.classList.remove('is-active');
  }

  /* ======================================================================
     Treasure reveal — ENHANCED: screen-shake, confetti, breath animation
     ====================================================================== */
  function setPrizeImage(url) {
    if (!revealImage || !imageWrap) return;
    if (url) {
      imageWrap.classList.remove('no-image');
      revealImage.onerror = function () {
        imageWrap.classList.add('no-image');
        revealImage.removeAttribute('src');
      };
      revealImage.src = url;
    } else {
      imageWrap.classList.add('no-image');
      revealImage.removeAttribute('src');
    }
  }

  function burstParticles() {
    if (!particlesEl) return;
    particlesEl.innerHTML = '';
    var n = 14 + Math.floor(Math.random() * 8);
    var colors = ['#ffe8a0', '#ffd700', '#ffaa00', '#ffb347', '#fff6d8', '#c79a3d', '#e8c572'];
    for (var i = 0; i < n; i++) {
      var p = document.createElement('div');
      p.className = 'ltr-particle';
      var ang = Math.random() * Math.PI * 2;
      var dist = rand(50, 160);
      p.style.setProperty('--dx', (Math.cos(ang) * dist).toFixed(1) + 'px');
      p.style.setProperty('--dy', (Math.sin(ang) * dist - 40).toFixed(1) + 'px');
      p.style.animationDelay = (Math.random() * 0.22).toFixed(2) + 's';
      p.style.background = 'radial-gradient(circle at 34% 30%, #fff, ' + colors[i % colors.length] + ' 62%, #9a7227)';
      particlesEl.appendChild(p);
    }
  }

  function screenShake(ms) {
    if (reducedMotion() || !stage) return;
    stage.style.transition = 'none';
    stage.style.animation = '';
    void stage.offsetWidth; // reflow
    var k = 0;
    var intensity = 3;
    var ids = [];
    function shake() {
      if (k >= ms / 16) { stage.style.transform = ''; return; }
      var ex = (Math.random() - 0.5) * intensity * 2;
      var ey = (Math.random() - 0.5) * intensity * 2;
      stage.style.transform = 'translate(' + ex.toFixed(1) + 'px,' + ey.toFixed(1) + 'px)';
      k++;
      ids.push(setTimeout(shake, 16));
    }
    shake();
    after(ms, currentSeq, function () {
      ids.forEach(function (id) { clearTimeout(id); });
      if (stage) stage.style.transform = '';
    });
  }

  function showTreasureReveal(prize, seq) {
    if (!revealEl) return;
    var k = reducedMotion() ? 0.5 : 1;
    if (revealPrize) revealPrize.textContent = (prize && prize.name) ? prize.name : 'Phần thưởng bí ẩn';
    setPrizeImage(prize && prize.image_url);

    revealEl.classList.add('is-active');
    after(350 * k, seq, function () { revealEl.classList.add('is-focus'); });
    after(550 * k, seq, function () { revealEl.classList.add('is-chest'); });
    after(850 * k, seq, function () {
      revealEl.classList.add('is-open');
      if (!reducedMotion()) {
        burstParticles();
        screenShake(180); // screen shake when chest opens
      }
    });
    after(1200 * k, seq, function () {
      revealEl.classList.add('is-card');
      setDisplayState(DISPLAY_STATE.REVEAL);
    });
  }

  function hideTreasureReveal() {
    if (revealEl) revealEl.classList.remove('is-active', 'is-focus', 'is-chest', 'is-open', 'is-card');
    if (particlesEl) particlesEl.innerHTML = '';
    if (revealImage) { revealImage.onerror = null; revealImage.removeAttribute('src'); }
    if (imageWrap) imageWrap.classList.remove('no-image');
    if (mapEl) {
      mapEl.style.transition = 'transform 0.8s cubic-bezier(.4,0,.2,1)';
      mapEl.style.transform = '';
      mapEl.style.transformOrigin = '';
    }
  }

  /* ======================================================================
     Treasure node states — ENHANCED: dimming dựa trên visited
     ====================================================================== */
  function resetSpots() {
    spotEls.forEach(function (el) { el.classList.remove('is-target', 'is-dimmed', 'is-arrived'); });
  }
  function applyTargetDimming(idx) {
    spotEls.forEach(function (el, i) {
      var isVisited = visitedSpots.indexOf(i) >= 0;
      if (i === idx) { el.classList.add('is-target'); el.classList.remove('is-dimmed'); }
      else if (isVisited) { el.classList.add('is-dimmed'); el.classList.remove('is-target'); }
      else { el.classList.remove('is-dimmed'); el.classList.remove('is-target'); }
    });
  }

  /* ======================================================================
     Điều phối toàn bộ lượt draw
     ====================================================================== */
  function handleDraw(evt) {
    clearSequence();
    var seq = currentSeq;

    hideTreasureReveal();
    resetSpots();
    clearRouteSelection();
    hideCompass();
    resetShipToHarbor();

    var idx = clamp(evt && evt.spot != null ? (evt.spot | 0) : 0, 0, TREASURE_SPOTS.length - 1);

    setDisplayState(DISPLAY_STATE.COMPASS);
    showCompassForTarget(idx, seq, function () {
      hideCompass();
      setDisplayState(DISPLAY_STATE.ROUTE_LOCK);
      applyTargetDimming(idx);
      prepareSelectedRoute(idx);
      animateRouteDraw();

      after(reducedMotion() ? 120 : rand(250, 350), seq, function () {
        sailAlongPath(idx, seq, function () {
          setDisplayState(DISPLAY_STATE.ARRIVAL);
          var spot = spotEls[idx];
          if (spot) { spot.classList.remove('is-target'); spot.classList.add('is-arrived'); }
          if (visitedSpots.indexOf(idx) < 0) visitedSpots.push(idx);
          after(reducedMotion() ? 200 : 430, seq, function () {
            showTreasureReveal(evt.prize, seq);
          });
        });
      });
    });
  }

  function handleReset() {
    clearSequence();
    hideCompass();
    hideTreasureReveal();
    clearRouteSelection();
    resetSpots();
    resetShipToHarbor();
    setDisplayState(DISPLAY_STATE.IDLE);
    updateIdleCaption();
  }

  /* ======================================================================
     CTA + toast + badge + marquee
     ====================================================================== */
  function updateIdleCaption() {
    if (!idleCaption) return;
    if (!hasPolled) { syncClickable(); return; }
    idleCaption.textContent = remainingTotal > 0
      ? 'CHẠM VÀO BẢN ĐỒ ĐỂ BẮT ĐẦU HÀNH TRÌNH'
      : 'KHO TÀNG ĐÃ TRỐNG — CHỜ NẠP THÊM PHẦN THƯỞNG';
    syncClickable();
  }

  function showToast(msg) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.classList.add('is-visible');
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('is-visible'); }, 3200);
  }

  /* ======================================================================
     Đồng bộ server (poll) + tap-to-draw
     ====================================================================== */
  function poll() {
    if (!ROOT) return;
    fetch(ROOT + 'state?_=' + Date.now(), { cache: 'no-store', headers: { 'Cache-Control': 'no-cache' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.event) return;
        hasPolled = true;
        if (data.prizes) {
          remainingTotal = data.prizes.reduce(function (sum, p) { return sum + (p.remaining | 0); }, 0);
          updateIdleCaption();
        }
        if (data.history) {
          historyItems = data.history.slice(-3);
          updateMarquee();
        }
        if (lastEventId === null) { lastEventId = data.event.event_id; return; }
        if (data.event.event_id !== lastEventId) {
          lastEventId = data.event.event_id;
          if (data.event.type === 'draw') handleDraw(data.event);
          else handleReset();
        }
      })
      .catch(function () { /* lỗi mạng tạm thời — giữ nguyên UI */ });
  }

  function performDraw() {
    if (requesting || currentState !== DISPLAY_STATE.IDLE || remainingTotal <= 0) return;
    requesting = true;
    fetch(ROOT + 'draw?_=' + Date.now(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ key: KEY })
    })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
      .then(function (res) {
        requesting = false;
        if (res.ok && res.data && res.data.event) {
          if (res.data.event.event_id !== lastEventId) {
            lastEventId = res.data.event.event_id;
            handleDraw(res.data.event);
          }
        } else {
          setDisplayState(DISPLAY_STATE.IDLE);
          showToast((res.data && res.data.message) ? res.data.message : 'Không thể kết nối kho báu. Vui lòng thử lại.');
        }
      })
      .catch(function () {
        requesting = false;
        setDisplayState(DISPLAY_STATE.IDLE);
        showToast('Không thể kết nối kho báu. Vui lòng thử lại.');
      });
  }

  /* ======================================================================
     Badge số quà + marquee lịch sử
     ====================================================================== */
  function updateMarquee() {
    var badge = document.getElementById('ltr-badge-remaining');
    var marquee = document.getElementById('ltr-marquee');
    if (badge) badge.textContent = remainingTotal + ' kho báu';
    if (marquee && historyItems.length > 0) {
      var parts = historyItems.map(function (h) { return h.prize_name; }).reverse().join('  ◆  ');
      marquee.textContent = parts;
      marquee.style.display = '';
    }
  }

  /* ======================================================================
     Input: IDLE → draw; REVEAL → đóng
     ====================================================================== */
  if (mapEl) {
    mapEl.addEventListener('click', function () {
      if (currentState === DISPLAY_STATE.IDLE) performDraw();
    });
  }
  if (revealEl) {
    revealEl.addEventListener('click', function (e) {
      e.stopPropagation();
      if (currentState === DISPLAY_STATE.REVEAL) handleReset();
    });
  }
  document.addEventListener('keydown', function (e) {
    var k = e.key;
    if (k === ' ' || k === 'Spacebar' || k === 'Enter' || k === 'Escape') {
      e.preventDefault();
      if (currentState === DISPLAY_STATE.REVEAL) handleReset();
      else if (currentState === DISPLAY_STATE.IDLE && k !== 'Escape') performDraw();
    }
  });
  window.addEventListener('resize', handleResize);

  /* ======================================================================
     Khởi động
     ====================================================================== */
  buildTreasureSpots();
  buildWakePool();
  validateTreasureLayout();
  resetShipToHarbor();
  setDisplayState(DISPLAY_STATE.IDLE);
  poll();
  setInterval(poll, POLL_MS);

  if (document.fonts && document.fonts.ready && document.fonts.ready.then) {
    document.fonts.ready.then(function () {
      validateTreasureLayout();
      if (currentState === DISPLAY_STATE.IDLE) resetShipToHarbor();
    });
  }
})();
