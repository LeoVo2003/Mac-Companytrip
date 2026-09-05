(function () {
  "use strict";

  var ROOT = window.LTR_DATA.root;
  var KEY = window.LTR_DATA.key || '';
  var HARBOR = { x: 6, y: 88 };
  var SPOT_POS = [
    { x: 18, y: 26 }, { x: 64, y: 16 }, { x: 84, y: 46 },
    { x: 58, y: 66 }, { x: 22, y: 70 }, { x: 40, y: 42 }
  ];
  var ROUTE_IDS = ['ltr-route-0', 'ltr-route-1', 'ltr-route-2', 'ltr-route-3', 'ltr-route-4', 'ltr-route-5'];

  var stage = document.getElementById('ltr-stage');
  var mapEl = document.getElementById('ltr-map');
  var shipEl = document.getElementById('ltr-ship');
  var spotsContainer = document.getElementById('ltr-spots');
  var idleCaption = document.getElementById('ltr-idle-caption');

  var compassOverlay = document.getElementById('ltr-compass-overlay');
  var compassNeedle = document.getElementById('ltr-compass-needle');

  var revealEl = document.getElementById('ltr-reveal');
  var bigChestEl = document.getElementById('ltr-big-chest');
  var prizePopEl = document.getElementById('ltr-prize-pop');
  var revealImage = document.getElementById('ltr-reveal-image');
  var revealPrize = document.getElementById('ltr-reveal-prize');

  var lastEventId = null;
  var currentSeq = 0;      // Tăng mỗi lần có thay đổi trạng thái lớn để huỷ animation cũ còn dang dở.
  var pendingTimeouts = [];
  var spotEls = [];
  var remainingTotal = 0;
  var requesting = false;  // Chặn bấm liên tục khi đang gửi lệnh draw lên server.

  function clearPending() {
    pendingTimeouts.forEach(function (id) { clearTimeout(id); });
    pendingTimeouts = [];
  }
  function after(ms, seq, fn) {
    var id = setTimeout(function () {
      if (seq !== currentSeq) return; // Đã có lệnh reset/draw mới hơn, huỷ bước này.
      fn();
    }, ms);
    pendingTimeouts.push(id);
  }

  function buildSpots() {
    spotsContainer.innerHTML = '';
    spotEls = SPOT_POS.map(function () {
      var el = document.createElement('div');
      el.className = 'ltr-spot';
      var rot = (Math.random() * 18 - 9).toFixed(1);
      el.innerHTML =
        '<div class="ltr-ring"></div>' +
        '<div class="ltr-x" style="--x-rot:' + rot + 'deg"><svg viewBox="0 0 40 40"><path d="M8 8 L32 32 M32 8 L8 32" stroke="#7a2e2e" stroke-width="5" stroke-linecap="round"/></svg></div>';
      spotsContainer.appendChild(el);
      return el;
    });
    SPOT_POS.forEach(function (pos, i) {
      spotEls[i].style.left = pos.x + '%';
      spotEls[i].style.top = pos.y + '%';
    });
  }

  function resetSpots() {
    spotEls.forEach(function (el) { el.classList.remove('ltr-arrived'); });
    mapEl.querySelectorAll('.ltr-sparkle').forEach(function (s) { s.remove(); });
  }

  function placeShip(x, y) {
    shipEl.style.left = x + '%';
    shipEl.style.top = y + '%';
  }
  function setShipRotation(deg) {
    shipEl.style.setProperty('--ship-rot', deg + 'deg');
  }

  function easeInOutQuad(t) { return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t; }

  function bearingToTarget(idx) {
    var pathEl = document.getElementById(ROUTE_IDS[idx]);
    if (!pathEl) return 0;
    var len = pathEl.getTotalLength();
    var p0 = pathEl.getPointAtLength(0);
    var p1 = pathEl.getPointAtLength(Math.min(len, len * 0.12));
    return Math.atan2(p1.y - p0.y, p1.x - p0.x) * 180 / Math.PI;
  }

  function spawnSparkle(xPct, yPct) {
    var s = document.createElement('div');
    s.className = 'ltr-sparkle';
    s.style.left = (xPct + (Math.random() - 0.5) * 1.4) + '%';
    s.style.top = (yPct + (Math.random() - 0.5) * 1.4) + '%';
    mapEl.appendChild(s);
    setTimeout(function () { s.remove(); }, 850);
  }

  function sailAlongPath(idx, durationMs, seq, onDone) {
    var pathEl = document.getElementById(ROUTE_IDS[idx]);
    if (!pathEl) { if (onDone) onDone(); return; }
    var len = pathEl.getTotalLength();
    var t0 = null;
    var frameCount = 0;

    shipEl.classList.add('ltr-sailing');

    function frame(ts) {
      if (seq !== currentSeq) return; // Bị huỷ giữa chừng bởi reset/draw mới.
      if (t0 === null) t0 = ts;
      var raw = Math.min(1, (ts - t0) / durationMs);
      var t = easeInOutQuad(raw);

      var p = pathEl.getPointAtLength(t * len);
      placeShip(p.x, p.y);

      var pAhead = pathEl.getPointAtLength(Math.min(len, (t + 0.015) * len));
      var angle = Math.atan2(pAhead.y - p.y, pAhead.x - p.x) * 180 / Math.PI;
      setShipRotation(angle);

      frameCount++;
      if (frameCount % 3 === 0) spawnSparkle(p.x, p.y);

      if (raw < 1) {
        requestAnimationFrame(frame);
      } else {
        shipEl.classList.remove('ltr-sailing');
        if (seq === currentSeq && typeof onDone === 'function') onDone();
      }
    }
    requestAnimationFrame(frame);
  }

  function burstCoins(container, count, spread) {
    for (var i = 0; i < count; i++) {
      var coin = document.createElement('div');
      coin.className = 'ltr-coin';
      var angle = Math.random() * Math.PI * 2;
      var dist = spread * (0.5 + Math.random() * 0.7);
      coin.style.setProperty('--dx', Math.cos(angle) * dist + 'px');
      coin.style.setProperty('--dy', (Math.sin(angle) * dist - spread * 0.3) + 'px');
      coin.style.animationDelay = (Math.random() * 0.18) + 's';
      container.appendChild(coin);
      (function (c) { setTimeout(function () { c.remove(); }, 1300); })(coin);
    }
  }

  function resetRevealDom() {
    revealEl.classList.remove('ltr-show');
    bigChestEl.classList.remove('ltr-open');
    prizePopEl.classList.remove('ltr-visible');
    revealImage.removeAttribute('src');
    var coins = revealEl.querySelectorAll('.ltr-coin');
    coins.forEach(function (c) { c.remove(); });
  }

  function hideCompass() {
    compassOverlay.classList.remove('ltr-active');
  }

  /* ---------- Điều phối toàn bộ trình tự khi có lệnh "draw" ---------- */
  function handleDraw(evt) {
    currentSeq++;
    var seq = currentSeq;
    clearPending();

    resetRevealDom();
    resetSpots();
    hideCompass();
    stage.classList.add('ltr-busy');

    var idx = Math.max(0, Math.min(SPOT_POS.length - 1, evt.spot | 0));
    var spotEl = spotEls[idx];

    // Bước 1: La bàn xoay dò hướng.
    var bearing = bearingToTarget(idx);
    var spins = 3 + Math.floor(Math.random() * 2); // 3-4 vòng trước khi dừng
    compassNeedle.style.setProperty('--needle-rot', (spins * 360 + bearing) + 'deg');
    compassOverlay.classList.add('ltr-active');

    after(1700, seq, function () {
      hideCompass();

      // Bước 2: thuyền chạy dọc theo đường đã vẽ trên bản đồ.
      sailAlongPath(idx, 2600, seq, function () {
        spotEl.classList.add('ltr-arrived');

        after(500, seq, function () {
          // Bước 3: popup lớn xuất hiện, rương to mở ra.
          revealEl.classList.add('ltr-show');

          after(500, seq, function () {
            bigChestEl.classList.add('ltr-open');
            burstCoins(bigChestEl, 26, 130);

            after(750, seq, function () {
              // Bước 4: phần thưởng nổi lên ngay phía trên rương đã mở.
              revealPrize.textContent = (evt.prize && evt.prize.name) ? evt.prize.name : '';
              if (evt.prize && evt.prize.image_url) {
                revealImage.src = evt.prize.image_url;
              }
              prizePopEl.classList.add('ltr-visible');
              stage.classList.remove('ltr-busy');
              updateIdleCaption();
            });
          });
        });
      });
    });
  }

  /* ---------- Reset: huỷ mọi animation dang dở và đưa màn hình về trạng thái chờ ---------- */
  function handleReset() {
    currentSeq++;
    clearPending();

    hideCompass();
    resetRevealDom();
    resetSpots();

    shipEl.classList.remove('ltr-sailing');
    setShipRotation(0);
    placeShip(HARBOR.x, HARBOR.y);

    stage.classList.remove('ltr-busy');
    updateIdleCaption();
  }

  function updateIdleCaption() {
    if (stage.classList.contains('ltr-busy')) return;
    if (remainingTotal > 0) {
      idleCaption.textContent = 'Chạm vào bản đồ để dò la bàn tìm kho báu';
      mapEl.classList.add('ltr-clickable');
    } else {
      idleCaption.textContent = 'Đã hết phần thưởng trong kho tàng';
      mapEl.classList.remove('ltr-clickable');
    }
  }

  function poll() {
    fetch(ROOT + 'state?_=' + Date.now(), {
      cache: 'no-store',
      headers: { 'Cache-Control': 'no-cache' }
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.event) return;

        if (data.prizes) {
          remainingTotal = data.prizes.reduce(function (sum, p) { return sum + (p.remaining | 0); }, 0);
          updateIdleCaption();
        }

        if (lastEventId === null) {
          lastEventId = data.event.event_id;
          return; // Đồng bộ lần đầu, không phát lại hiệu ứng cho sự kiện cũ.
        }
        if (data.event.event_id !== lastEventId) {
          lastEventId = data.event.event_id;
          if (data.event.type === 'draw') {
            handleDraw(data.event);
          } else {
            handleReset();
          }
        }
      })
      .catch(function () { /* lỗi mạng tạm thời, thử lại ở lần poll sau */ });
  }

  /* ---------- Bấm trực tiếp lên bản đồ để dò la bàn (không cần trang điều khiển riêng) ---------- */
  function performDraw() {
    if (requesting || stage.classList.contains('ltr-busy') || revealEl.classList.contains('ltr-show')) return;
    if (remainingTotal <= 0) return;

    requesting = true;
    fetch(ROOT + 'draw?_=' + Date.now(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ key: KEY })
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        requesting = false;
        // Nếu API trả sự kiện ngay, chạy luôn cho nhanh (không cần đợi vòng poll kế tiếp).
        if (res && res.event && res.event.event_id !== lastEventId) {
          lastEventId = res.event.event_id;
          handleDraw(res.event);
        }
      })
      .catch(function () { requesting = false; });
  }

  mapEl.addEventListener('click', function () {
    performDraw();
  });

  /* ---------- Đóng popup ngay tại chỗ (không phụ thuộc việc đồng bộ server) ----------
     Chạm vào popup phần thưởng, hoặc nhấn phím Cách/Enter/Esc, để đóng ngay lập tức
     và tiếp tục lượt sau — không cần load lại trang. */
  revealEl.addEventListener('click', function (e) {
    e.stopPropagation();
    if (revealEl.classList.contains('ltr-show')) {
      handleReset();
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.key === ' ' || e.key === 'Enter') {
      if (revealEl.classList.contains('ltr-show')) {
        handleReset();
      } else {
        performDraw();
      }
    }
  });

  buildSpots();
  placeShip(HARBOR.x, HARBOR.y);
  poll();
  setInterval(poll, 1200);
})();
