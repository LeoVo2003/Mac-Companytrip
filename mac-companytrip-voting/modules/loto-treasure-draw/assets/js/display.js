(function () {
  "use strict";

  /* ======================================================================
     Lô Tô Kho Báu — bộ điều khiển màn hình LED "Treasure Voyage" v2.0.0
     ----------------------------------------------------------------------
     - State machine rõ ràng (IDLE / COMPASS / ROUTE_LOCK / SAILING /
       ARRIVAL / REVEAL) để không bao giờ chạy chồng animation.
     - Di chuyển thuyền bằng TỌA ĐỘ PIXEL THẬT (SVGPoint + getScreenCTM)
       nên mũi thuyền luôn đúng hướng trên màn 16:9 bị kéo giãn.
     - Mover (translate3d + rotate) tách khỏi body (bob/roll/wake).
     - Route draw-on, la bàn dò hướng, rương mở, thẻ phần thưởng.
     - GIỮ NGUYÊN contract REST ltr/v1 (state/draw) và event
       { event_id, type, spot, prize:{name,image_url} }.
     ====================================================================== */

  var DATA = window.LTR_DATA || {};
  var ROOT = DATA.root;
  var KEY = DATA.key || '';

  /* ---------- Hằng số bố cục (hệ toạ độ chuẩn hoá 0..100) ---------- */
  var TREASURE_SPOTS = [
    { id: 0, x: 20, y: 22 },
    { id: 1, x: 48, y: 15 },
    { id: 2, x: 78, y: 24 },
    { id: 3, x: 83, y: 59 },
    { id: 4, x: 62, y: 78 },
    { id: 5, x: 31, y: 68 }
  ];
  var HARBOR = { x: 10, y: 84 };
  var ROUTE_IDS = ['ltr-route-0', 'ltr-route-1', 'ltr-route-2', 'ltr-route-3', 'ltr-route-4', 'ltr-route-5'];

  var SHIP_HEADING_OFFSET = 0;    // Mũi thuyền đã hướng +X trong SVG → offset 0.
  var BOW_OFFSET_RATIO = 0.46;    // Mũi tàu cách tâm ~0.46 chiều dài (viewBox 260, mũi ~x248).
  var SHIP_SPEED = 260;           // px/s — dùng suy ra duration theo độ dài route.
  var POLL_MS = 1200;

  var DISPLAY_STATE = {
    IDLE: 'idle', COMPASS: 'compass', ROUTE_LOCK: 'route-lock',
    SAILING: 'sailing', ARRIVAL: 'arrival', REVEAL: 'reveal'
  };

  // 3 dáng đảo (deterministic theo index — KHÔNG random để route không đổi mỗi lần load).
  var ISLAND_SHAPES = [
    {
      land: 'M50,20 C67,17 83,29 84,47 C85,66 70,81 51,80 C31,79 17,65 19,46 C21,30 34,23 50,20 Z',
      contour: 'M50,11 C72,7 92,25 92,48 C92,72 71,90 49,89 C26,88 8,69 10,45 C12,24 31,14 50,11 Z'
    },
    {
      land: 'M46,22 C64,16 84,26 86,44 C88,63 71,79 52,80 C33,81 18,68 18,49 C18,34 31,27 46,22 Z',
      contour: 'M45,12 C68,5 92,20 94,44 C96,69 73,89 50,89 C27,89 8,71 9,47 C10,26 27,17 45,12 Z'
    },
    {
      land: 'M52,21 C70,18 85,31 83,49 C81,68 65,80 47,78 C29,76 17,62 21,44 C24,30 37,24 52,21 Z',
      contour: 'M53,11 C76,7 94,26 91,50 C88,74 67,90 45,87 C23,84 7,64 12,42 C16,23 34,14 53,11 Z'
    }
  ];

  /* ---------- Tham chiếu DOM (khớp display.php) ---------- */
  var stage = document.getElementById('ltr-stage');
  var mapEl = document.getElementById('ltr-map');
  var shipEl = document.getElementById('ltr-ship');
  var spotsContainer = document.getElementById('ltr-spots');
  var idleCaption = document.getElementById('ltr-idle-caption');
  var harborEl = document.getElementById('ltr-harbor');

  var routeSvg = mapEl ? mapEl.querySelector('.ltr-route-svg') : null;
  var routeEls = ROUTE_IDS.map(function (id) { return document.getElementById(id); });

  var compassOverlay = document.getElementById('ltr-compass-overlay');
  var compassNeedle = document.getElementById('ltr-compass-needle');

  var revealEl = document.getElementById('ltr-reveal');
  var particlesEl = document.getElementById('ltr-particles');
  var revealImage = document.getElementById('ltr-reveal-image');
  var revealPrize = document.getElementById('ltr-reveal-prize');
  var imageWrap = revealEl ? revealEl.querySelector('.ltr-prize-image-wrap') : null;
  var toastEl = document.getElementById('ltr-toast');

  /* ---------- Trạng thái runtime ---------- */
  var currentState = DISPLAY_STATE.IDLE;
  var currentSeq = 0;            // Tăng mỗi lần đổi sequence lớn → huỷ callback/rAF cũ.
  var pendingTimeouts = [];
  var activeAnims = [];          // WAAPI đang chạy (kim la bàn, route draw-on).
  var sailRafId = null;
  var spotEls = [];
  var remainingTotal = 0;
  var requesting = false;        // Chặn spam draw.
  var lastEventId = null;
  var hasPolled = false;
  var shipHeading = 0;

  // Wake foam pool (cố định, tái sử dụng — không rò rỉ DOM).
  var wakeLayer = null;
  var foamPool = [];
  var foamIndex = 0;
  var lastFoamTime = 0;
  var foamInterval = 100;

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
      if (seq !== currentSeq) return;   // Đã có reset/draw mới → huỷ bước này.
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

  // Huỷ MỌI thứ đang chạy: timeout, rAF, WAAPI, bọt nước. Không tự đổi visual.
  function clearSequence() {
    currentSeq++;
    pendingTimeouts.forEach(function (id) { clearTimeout(id); });
    pendingTimeouts = [];
    if (sailRafId) { cancelAnimationFrame(sailRafId); sailRafId = null; }
    activeAnims.forEach(function (a) { try { a.cancel(); } catch (e) {} });
    activeAnims = [];
    if (shipEl) shipEl.classList.remove('is-sailing');
    cancelFoam();
  }

  /* ======================================================================
     Geometry — quy đổi toạ độ user SVG → pixel thật trong bản đồ
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

  // Độ dài route theo PIXEL (SVG bị kéo giãn 16:9 nên độ dài user ≠ pixel).
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

  // Quy đổi "khoảng dừng px" ở CUỐI route sang đơn vị user của path.
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
     Layout — dựng 6 đảo kho báu + kiểm tra chồng lấn
     ====================================================================== */
  function buildTreasureSpots() {
    if (!spotsContainer) return;
    spotsContainer.innerHTML = '';
    spotEls = TREASURE_SPOTS.map(function (spot, i) {
      var shape = ISLAND_SHAPES[i % ISLAND_SHAPES.length];
      var rot = ((i * 41) % 70) - 35;   // xoay đảo deterministic cho tự nhiên
      var el = document.createElement('div');
      el.className = 'ltr-spot';
      el.style.left = spot.x + '%';
      el.style.top = spot.y + '%';
      el.style.setProperty('--i', String(i));
      el.innerHTML =
        '<div class="ltr-spot-halo"></div>' +
        '<svg class="ltr-spot-island" viewBox="0 0 100 100" aria-hidden="true" style="transform:rotate(' + rot + 'deg)">' +
          '<path class="ltr-island-contour" d="' + shape.contour + '"/>' +
          '<path class="ltr-island-land" d="' + shape.land + '"/>' +
        '</svg>' +
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

  // Kiểm tra chồng lấn bằng rect thật; chỉ THU NHỎ marker (không dịch node để
  // route luôn khớp tâm đảo). Bỏ qua compass-rose vì đó là watermark mờ nằm SAU
  // các đảo (z-index thấp hơn) — không phải vật cản thị giác.
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
      // Đang sailing: frame kế tiếp tự dùng CTM mới (không cần can thiệp).
      // Đang idle: snap thuyền về đúng cảng theo kích thước mới.
      if (currentState === DISPLAY_STATE.IDLE) resetShipToHarbor();
    }, 160);
  }

  /* ======================================================================
     Route
     ====================================================================== */
  function prepareSelectedRoute(idx) {
    if (routeSvg) routeSvg.classList.add('has-selection');
    routeEls.forEach(function (el, i) {
      if (el) el.classList.toggle('is-selected', i === idx);
    });
  }

  function animateRouteDraw(idx) {
    var pathEl = routeEls[idx];
    if (!pathEl) return;
    var len = pathEl.getTotalLength();
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
    routeEls.forEach(function (el) {
      if (!el) return;
      el.classList.remove('is-selected');
      if (el.getAnimations) el.getAnimations().forEach(function (a) { a.cancel(); });
      el.style.strokeDasharray = '';
      el.style.strokeDashoffset = '';
    });
  }

  /* ======================================================================
     Thuyền
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
    // Cho thuyền hướng từ cảng vào giữa biển — tự nhiên, không phụ thuộc resolution.
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

  function sailAlongPath(idx, seq, onArrive) {
    var pathEl = routeEls[idx];
    if (!pathEl || !routeSvg) { if (onArrive) onArrive(); return; }

    setDisplayState(DISPLAY_STATE.SAILING);
    shipEl.classList.add('is-sailing');

    var userLen = pathEl.getTotalLength();
    var pixelLen = computePixelLength(pathEl, userLen);
    var shipLenPx = shipEl.offsetWidth || 120;
    var spotRadiusPx = (spotEls[idx] ? spotEls[idx].offsetWidth : 60) / 2;
    // Dừng SAU tâm đảo một khoảng để MŨI thuyền không đè lên marker.
    var stopPx = BOW_OFFSET_RATIO * shipLenPx + 0.95 * spotRadiusPx;
    var stopUser = endPxToUser(pathEl, userLen, stopPx);
    var travelUserLen = Math.max(userLen * 0.25, userLen - stopUser);
    var lookAheadUser = Math.max(userLen * 0.035, 2.5);
    var duration = reducedMotion() ? 900 : clamp(pixelLen / SHIP_SPEED * 1000, 2600, 4200);

    var t0 = null;
    lastFoamTime = 0;
    foamInterval = rand(80, 120);

    function frame(ts) {
      if (seq !== currentSeq) { sailRafId = null; return; }   // bị reset/draw mới huỷ
      if (t0 === null) t0 = ts;
      var rawT = Math.min(1, (ts - t0) / duration);
      var u = smootherstep(rawT) * travelUserLen;

      var ctm = routeSvg.getScreenCTM();
      var rect = getMapRect();
      if (!ctm) { sailRafId = requestAnimationFrame(frame); return; }

      var p = pathEl.getPointAtLength(u);
      var px = svgUserPointToLocalPx(p.x, p.y, ctm, rect);
      // Điểm nhìn trước (cho phép vượt travelUserLen, tiến về phía đảo) → heading.
      var pA = pathEl.getPointAtLength(Math.min(userLen, u + lookAheadUser));
      var pxA = svgUserPointToLocalPx(pA.x, pA.y, ctm, rect);

      var targetHeading = Math.atan2(pxA.y - px.y, pxA.x - px.x) * 180 / Math.PI;
      shipHeading = lerpAngle(shipHeading, targetHeading, reducedMotion() ? 0.5 : 0.18);
      setShipTransformPx(px.x, px.y, shipHeading);

      if (!reducedMotion()) spawnFoam(ts, px, shipHeading, shipLenPx);

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
    // Góc từ cảng → đảo (pixel-space). Kim đỏ mặc định chỉ lên (-Y) nên cộng 90°.
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
     Treasure reveal
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
    var n = 10 + Math.floor(Math.random() * 7);   // 10–16 hạt
    for (var i = 0; i < n; i++) {
      var p = document.createElement('div');
      p.className = 'ltr-particle';
      var ang = Math.random() * Math.PI * 2;
      var dist = rand(40, 130);
      p.style.setProperty('--dx', (Math.cos(ang) * dist).toFixed(1) + 'px');
      p.style.setProperty('--dy', (Math.sin(ang) * dist - 34).toFixed(1) + 'px');
      p.style.animationDelay = (Math.random() * 0.18).toFixed(2) + 's';
      particlesEl.appendChild(p);
    }
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
      if (!reducedMotion()) burstParticles();
    });
    after(1200 * k, seq, function () {
      revealEl.classList.add('is-card');
      setDisplayState(DISPLAY_STATE.REVEAL);   // mở khoá input để đóng lượt
    });
  }

  function hideTreasureReveal() {
    if (revealEl) revealEl.classList.remove('is-active', 'is-focus', 'is-chest', 'is-open', 'is-card');
    if (particlesEl) particlesEl.innerHTML = '';
    if (revealImage) { revealImage.onerror = null; revealImage.removeAttribute('src'); }
    if (imageWrap) imageWrap.classList.remove('no-image');
  }

  /* ======================================================================
     Treasure node states
     ====================================================================== */
  function resetSpots() {
    spotEls.forEach(function (el) { el.classList.remove('is-target', 'is-dimmed', 'is-arrived'); });
  }
  function applyTargetDimming(idx) {
    spotEls.forEach(function (el, i) {
      if (i === idx) { el.classList.add('is-target'); el.classList.remove('is-dimmed'); }
      else { el.classList.add('is-dimmed'); el.classList.remove('is-target'); }
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
      animateRouteDraw(idx);

      after(reducedMotion() ? 120 : rand(250, 350), seq, function () {
        sailAlongPath(idx, seq, function () {
          setDisplayState(DISPLAY_STATE.ARRIVAL);
          var spot = spotEls[idx];
          if (spot) { spot.classList.remove('is-target'); spot.classList.add('is-arrived'); }
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
     CTA + toast
     ====================================================================== */
  function updateIdleCaption() {
    if (!idleCaption) return;
    if (!hasPolled) { syncClickable(); return; }   // giữ text mặc định tới lần poll đầu
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
     Đồng bộ server (poll) + tap-to-draw — GIỮ nguyên contract ltr/v1
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
        if (lastEventId === null) { lastEventId = data.event.event_id; return; } // đồng bộ lần đầu
        if (data.event.event_id !== lastEventId) {
          lastEventId = data.event.event_id;
          if (data.event.type === 'draw') handleDraw(data.event);
          else handleReset();   // 'ready' / 'undo' / 'reset' → huỷ + về idle an toàn
        }
      })
      .catch(function () { /* lỗi mạng tạm thời — giữ nguyên UI, thử lại lần poll sau */ });
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
     Input: IDLE → draw; REVEAL → đóng; các state giữa → khoá
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

  // Webfont (Special Elite) làm đổi kích thước chữ CTA → kiểm tra lại chồng lấn
  // và snap thuyền về cảng một lần nữa sau khi font sẵn sàng.
  if (document.fonts && document.fonts.ready && document.fonts.ready.then) {
    document.fonts.ready.then(function () {
      validateTreasureLayout();
      if (currentState === DISPLAY_STATE.IDLE) resetShipToHarbor();
    });
  }
})();
