(function () {
  "use strict";

  var root = LTR_DATA.root;
  var nonce = LTR_DATA.nonce;

  function apiGet(path) {
    return fetch(root + path, { headers: { 'X-WP-Nonce': nonce } }).then(function (r) { return r.json(); });
  }
  function apiPost(path, body) {
    return fetch(root + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
      body: JSON.stringify(body || {})
    }).then(function (r) { return r.json(); });
  }

  var drawBtn = document.getElementById('ltr-btn-draw');
  var readyBtn = document.getElementById('ltr-btn-ready');
  var undoBtn = document.getElementById('ltr-btn-undo');
  var prizeList = document.getElementById('ltr-prize-summary');
  var historyList = document.getElementById('ltr-history-list');
  var msgBox = document.getElementById('ltr-message');

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s === undefined || s === null ? '' : s;
    return d.innerHTML;
  }

  function showMsg(text, isError) {
    msgBox.textContent = text;
    msgBox.className = 'ltr-msg ' + (isError ? 'ltr-msg-error' : 'ltr-msg-ok');
    msgBox.style.display = 'block';
    setTimeout(function () { msgBox.style.display = 'none'; }, 3500);
  }

  function renderPrizes(prizes) {
    prizeList.innerHTML = '';
    var totalRemaining = 0;
    prizes.forEach(function (p) {
      totalRemaining += p.remaining;
      var row = document.createElement('div');
      row.className = 'ltr-prize-row';
      row.innerHTML = '<span>' + escapeHtml(p.name) + '</span><span class="ltr-badge">' + p.remaining + '/' + p.total + '</span>';
      prizeList.appendChild(row);
    });
    if (!prizes.length) {
      prizeList.innerHTML = '<p class="ltr-empty">Chưa có phần thưởng nào. Hãy thêm ở trang Kho tàng.</p>';
    }
    drawBtn.disabled = totalRemaining <= 0;
    drawBtn.textContent = totalRemaining <= 0 ? '⚓ Đã hết quà trong kho tàng' : '🧭 DÒ LA BÀN';
  }

  function renderHistory(history) {
    historyList.innerHTML = '';
    if (!history || !history.length) {
      historyList.innerHTML = '<p class="ltr-empty">Chưa có lượt quay nào.</p>';
      return;
    }
    history.slice().reverse().slice(0, 30).forEach(function (h) {
      var item = document.createElement('div');
      item.className = 'ltr-hist-item';
      item.innerHTML = '<strong>' + escapeHtml(h.prize_name) + '</strong>';
      historyList.appendChild(item);
    });
  }

  function refresh() {
    return apiGet('state').then(function (data) {
      renderPrizes(data.prizes || []);
      renderHistory(data.history || []);
    });
  }

  drawBtn.addEventListener('click', function () {
    drawBtn.disabled = true;
    apiPost('draw', {}).then(function (res) {
      if (res && res.message) {
        showMsg(res.message, true);
      } else {
        showMsg('Đã tìm thấy phần thưởng! Xem màn hình LED.');
      }
      refresh();
    });
  });

  readyBtn.addEventListener('click', function () {
    apiPost('ready', {}).then(function () {
      showMsg('Màn hình LED đã sẵn sàng cho lượt tiếp theo.');
    });
  });

  undoBtn.addEventListener('click', function () {
    if (!confirm('Hoàn tác lượt quay gần nhất?')) return;
    apiPost('undo', {}).then(function (res) {
      if (res && res.message) showMsg(res.message, true);
      else showMsg('Đã hoàn tác lượt quay gần nhất.');
      refresh();
    });
  });

  refresh();
})();
