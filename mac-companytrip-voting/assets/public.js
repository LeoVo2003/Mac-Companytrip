(() => {
  const root = document.getElementById("mac-voting-app");
  if (!root) return;
  const config = window.MACVoting || {
    restUrl: root.dataset.restUrl,
    nonce: root.dataset.nonce,
    logo: root.dataset.logo,
  };
  const api = config.restUrl;
  let state = null;
  let selectedPerformanceId = null;
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
  const request = async (path, options = {}) => {
    const response = await fetch(api + path, { credentials: "same-origin", headers: { "Content-Type": "application/json", "X-WP-Nonce": config.nonce, ...(options.headers || {}) }, ...options });
    const contentType = response.headers.get("content-type") || "";
    if (!contentType.includes("application/json")) throw new Error("Máy chủ không trả về dữ liệu hợp lệ.");
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || "Có lỗi hệ thống. Vui lòng thử lại.");
    return data;
  };
  const brand = () => `<div class="mv-brand"><img src="${esc(config.logo)}" alt="MAC Marketing"><div><small>COMPANY TRIP 2026</small><strong>Chấm Điểm Văn Nghệ</strong></div></div>`;
  const shell = (body, cls = "") => `<main class="mv-shell ${cls}"><div class="mv-grid"></div><section class="mv-card">${body}</section><p class="mv-footer">MAC MARKETING · COMPANY TRIP 2026</p></main>`;
  const icons = {
    mail: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>`,
  };

  function confirmDialog({ title, kicker, body, confirmLabel }) {
    return new Promise((resolve) => {
      const modal = document.createElement("div");
      modal.className = "mv-modal";
      modal.innerHTML = `<div class="mv-modal-card" role="dialog" aria-modal="true" aria-labelledby="mv-modal-title"><p class="mv-kicker">${kicker}</p><h2 id="mv-modal-title">${title}</h2>${body}<div class="mv-modal-actions"><button type="button" class="mv-button mv-button--secondary mv-modal-cancel">Hủy</button><button type="button" class="mv-button mv-button--primary mv-modal-confirm" data-confirm>${confirmLabel}</button></div></div>`;
      const finish = (value) => {
        document.removeEventListener("keydown", onKey);
        modal.remove();
        resolve(value);
      };
      const onKey = (event) => { if (event.key === "Escape") finish(false); };
      modal.addEventListener("click", (event) => { if (event.target === modal) finish(false); });
      modal.querySelector(".mv-modal-cancel").addEventListener("click", () => finish(false));
      modal.querySelector("[data-confirm]").addEventListener("click", () => finish(true));
      document.addEventListener("keydown", onKey);
      root.appendChild(modal);
      modal.querySelector("[data-confirm]").focus();
    });
  }

  function lockedView() {
    root.innerHTML = shell(`${brand()}<div class="mv-heading mv-locked"><p class="mv-kicker">MAC COMPANY TRIP</p><h1>Chương trình chưa mở</h1><p>Cổng chấm điểm văn nghệ sẽ mở khi ban tổ chức thông báo. Bạn không cần làm gì lúc này.</p></div>`);
    setTimeout(refresh, 12000 + Math.floor(Math.random() * 4000));
  }

  function loginView() {
    root.innerHTML = shell(`${brand()}<div class="mv-heading"><p class="mv-kicker">ĐĂNG NHẬP DỰ PHÒNG</p><h1>Xin chào,<br>bạn là ai?</h1><p>Nhập username email công ty. Bạn cũng có thể mở QR cá nhân trong email để đăng nhập nhanh.</p></div><form id="mv-login" class="mv-form"><label for="mv-email">Username</label><div class="mv-input mv-email-input"><span aria-hidden="true">${icons.mail}</span><input id="mv-email" type="text" inputmode="email" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="64" aria-describedby="mv-email-domain mv-email-hint" placeholder="ten.nguoidung"><b id="mv-email-domain">@macusaone.com</b></div><p id="mv-email-hint" class="mv-login-hint">Chỉ cần nhập phần đứng trước dấu @.</p><p id="mv-error" class="mv-error" role="alert" hidden></p><button type="submit" class="mv-button mv-button--primary mv-button--block mv-login-submit">Tiếp tục <span aria-hidden="true">→</span></button></form><div class="mv-trust"><span>✓ QR cá nhân là cách vào chính</span><span>▣ Username chỉ khi QR lỗi</span></div>`);
    const emailInput = root.querySelector("#mv-email");
    emailInput.addEventListener("input", () => {
      const value = emailInput.value.trim().toLowerCase();
      if (value.endsWith("@macusaone.com")) emailInput.value = value.slice(0, -"@macusaone.com".length);
    });
    root.querySelector("#mv-login").addEventListener("submit", async (event) => {
      event.preventDefault(); const error = root.querySelector("#mv-error"); const button = event.currentTarget.querySelector("button[type='submit']"); error.hidden = true;
      const username = emailInput.value.trim().toLowerCase();
      if (!/^[a-z0-9._%+-]+$/.test(username)) { error.textContent = "Vui lòng nhập đúng username email công ty."; error.hidden = false; emailInput.focus(); return; }
      button.disabled = true; button.textContent = "Đang xác minh…";
      try { const data = await request("login", { method: "POST", body: JSON.stringify({ username }) }); confirmView(data.voter, data.state); }
      catch (err) { button.disabled = false; button.innerHTML = `Tiếp tục <span aria-hidden="true">→</span>`; error.textContent = err.message; error.hidden = false; }
    });
  }

  function confirmView(voter, nextState) {
    root.innerHTML = shell(`${brand()}<div class="mv-confirm-icon">✓</div><p class="mv-kicker mv-centered">XÁC NHẬN</p><h1 class="mv-confirm-name">${esc(voter.fullName)}</h1><div class="mv-team-pill">#${voter.teamNumber} ${esc(voter.teamName)}</div><button type="button" id="mv-confirm" class="mv-button mv-button--primary mv-button--block mv-confirm-submit">Đúng, là tôi →</button><button type="button" id="mv-wrong" class="mv-button mv-button--ghost mv-confirm-wrong">Không phải tôi</button>`, "mv-confirm");
    root.querySelector("#mv-confirm").addEventListener("click", () => { state = nextState; renderState(); });
    root.querySelector("#mv-wrong").addEventListener("click", logout);
  }

  async function logout() { try { await request("logout", { method: "POST", body: "{}" }); } catch {} state = null; loginView(); }
  function header() { return `<header class="mv-vote-header"><img src="${esc(config.logo)}" alt="MAC Marketing"><button type="button" id="mv-logout" class="mv-button mv-button--ghost mv-logout-button">Thoát</button></header>`; }

  function renderState() {
    if (!state) return loginView();
    if (state.status === "WAITING") {
      root.innerHTML = `<main class="mv-vote-shell">${header()}<section class="mv-status"><div class="mv-pulse">◷</div><p class="mv-kicker">ĐÃ ĐĂNG NHẬP</p><h1>Chờ mở vote</h1><p>Trang tự cập nhật khi bắt đầu.</p><div class="mv-identity"><span>${esc(state.voter.fullName)}</span><strong>#${state.voter.teamNumber} ${esc(state.voter.teamName)}</strong></div></section></main>`;
      root.querySelector("#mv-logout").addEventListener("click", logout);
      setTimeout(refresh, 8000 + Math.floor(Math.random() * 4000)); return;
    }
    const eligible = state.performances.filter((item) => !item.isOwnTeam);
    const completed = eligible.filter((item) => item.hasVoted).length;
    const available = eligible.filter((item) => item.canVote);
    const progress = state.round.isRevote ? 100 : Math.max(15, ((completed + .35) / Math.max(eligible.length, 1)) * 100);
    const progressHead = `<div class="mv-progress-head"><div><span>${state.round.isRevote ? "VOTE LẠI" : `LƯỢT ${state.round.id}/3`}</span><strong>${completed}/${eligible.length} đã gửi</strong></div><i><b style="width:${progress}%"></b></i></div>`;
    if (state.status === "DONE" || !available.length) {
      selectedPerformanceId = null;
      root.innerHTML = `<main class="mv-vote-shell">${header()}<section class="mv-status"><div class="mv-done">✓</div><p class="mv-kicker">HOÀN TẤT</p><h1>Cảm ơn bạn!</h1><div class="mv-complete">✓ ${completed}/${eligible.length} tiết mục đã chấm</div></section></main>`;
      root.querySelector("#mv-logout").addEventListener("click", logout);
      setTimeout(refresh, 8000 + Math.floor(Math.random() * 4000));
      return;
    }
    const active = available.find((item) => String(item.id) === String(selectedPerformanceId));
    if (!active) {
      selectedPerformanceId = null;
      root.innerHTML = `<main class="mv-vote-shell">${header()}${progressHead}<section class="mv-team-picker"><div class="mv-picker-head"><p class="mv-kicker">CHỌN TIẾT MỤC</p></div><div class="mv-team-tabs">${eligible.map((item) => item.hasVoted ? `<article class="mv-team-tab is-complete"><span>#${item.teamNumber}</span><strong>${esc(item.teamName)}</strong><small>Đã gửi</small></article>` : `<button type="button" class="mv-team-tab" data-performance-id="${item.id}" ${item.canVote ? "" : "disabled"}><span>#${item.teamNumber}</span><strong>${esc(item.teamName)}</strong><small>${item.canVote ? "Chấm điểm →" : "Chưa mở"}</small></button>`).join("")}</div></section></main>`;
      root.querySelector("#mv-logout").addEventListener("click", logout);
      root.querySelectorAll("[data-performance-id]").forEach((button) => button.addEventListener("click", () => { selectedPerformanceId = button.dataset.performanceId; renderState(); }));
      return;
    }
    const criteria = [["styleScore", "Phong Cách & Thần Thái Biểu Diễn"], ["stagingScore", "Dàn Dựng & Sáng Tạo"], ["teamworkScore", "Tinh Thần Đồng Đội & Bản Sắc Doanh Nghiệp"]];
    root.innerHTML = `<main class="mv-vote-shell">${header()}${progressHead}<div class="mv-vote-back"><button type="button" id="mv-back" class="mv-button mv-button--ghost">← Chọn đội</button></div><section class="mv-performance"><div>#${active.teamNumber}</div><article><p>${active.isRevote ? "CHẤM LẠI" : "ĐANG CHẤM"}</p><h1>${esc(active.teamName)}</h1></article></section><section class="mv-score-card"><p class="mv-kicker">PHIẾU CHẤM</p><h2>Chọn số sao</h2><form id="mv-ballot">${criteria.map((criterion, index) => `<fieldset data-star-field="${criterion[0]}"><legend><b>0${index + 1}</b><span>${criterion[1]}</span><output class="mv-star-output" aria-live="polite">—</output></legend><div class="mv-star-rating" role="radiogroup" aria-label="${criterion[1]}">${[10,20,30,40,50].map((score, star) => `<label class="mv-star" data-star="${star + 1}" title="${star + 1} sao · ${score} điểm"><input type="radio" name="${criterion[0]}" value="${score}" aria-label="${star + 1} sao, ${score} điểm"><i aria-hidden="true">★</i></label>`).join("")}</div></fieldset>`).join("")}<p id="mv-error" class="mv-error" role="alert" hidden></p><button type="submit" class="mv-button mv-button--primary mv-button--block mv-score-submit">Gửi phiếu →</button><small class="mv-note">Không thể sửa sau khi gửi</small></form></section></main>`;
    root.querySelector("#mv-logout").addEventListener("click", logout);
    root.querySelector("#mv-back").addEventListener("click", () => { selectedPerformanceId = null; renderState(); });
    const ballotForm = root.querySelector("#mv-ballot");
    const updateStars = (fieldset, previewStars = null) => {
      const score = Number(fieldset.querySelector("input:checked")?.value || 0);
      const shownStars = previewStars === null ? score / 10 : previewStars;
      const rating = fieldset.querySelector(".mv-star-rating");
      fieldset.querySelectorAll(".mv-star").forEach((star) => {
        const starNumber = Number(star.dataset.star);
        star.classList.toggle("is-active", starNumber <= score / 10);
        star.classList.toggle("is-preview", previewStars !== null && starNumber <= previewStars);
      });
      rating.classList.toggle("is-previewing", previewStars !== null);
      fieldset.querySelector(".mv-star-output").textContent = shownStars ? `${shownStars * 10} điểm` : "—";
    };
    ballotForm.addEventListener("change", (event) => { if (event.target.matches(".mv-star input")) updateStars(event.target.closest("fieldset")); });
    ballotForm.addEventListener("pointerover", (event) => {
      const star = event.target.closest(".mv-star");
      if (star && ballotForm.contains(star)) updateStars(star.closest("fieldset"), Number(star.dataset.star));
    });
    ballotForm.querySelectorAll(".mv-star-rating").forEach((rating) => rating.addEventListener("pointerleave", () => updateStars(rating.closest("fieldset"))));
    ballotForm.addEventListener("submit", async (event) => {
      event.preventDefault(); const form = new FormData(event.currentTarget); const error = root.querySelector("#mv-error"); const button = event.currentTarget.querySelector(".mv-score-submit"); error.hidden = true;
      const scores = { styleScore: Number(form.get("styleScore")), stagingScore: Number(form.get("stagingScore")), teamworkScore: Number(form.get("teamworkScore")) };
      if (!scores.styleScore || !scores.stagingScore || !scores.teamworkScore) { error.textContent = "Chọn đủ 3 tiêu chí."; error.hidden = false; return; }
      const confirmed = await confirmDialog({
        kicker: "XÁC NHẬN",
        title: "Gửi phiếu?",
        confirmLabel: "Gửi phiếu →",
        body: `<div class="mv-modal-team"><strong>#${active.teamNumber} ${esc(active.teamName)}</strong></div><ul class="mv-modal-scores">${criteria.map((criterion) => `<li><span>${criterion[1]}</span><strong>${scores[criterion[0]] / 10} ★ · ${scores[criterion[0]]}đ</strong></li>`).join("")}<li class="mv-modal-total"><span>Tổng</span><strong>${scores.styleScore + scores.stagingScore + scores.teamworkScore}đ</strong></li></ul><p class="mv-modal-warn">Không thể sửa sau khi gửi.</p>`,
      });
      if (!confirmed) { button.focus(); return; }
      button.disabled = true; button.textContent = "Đang gửi…";
      try { const data = await request("submit", { method: "POST", body: JSON.stringify({ performanceId: active.id, requestId: crypto.randomUUID(), scores }) }); state = data.state; selectedPerformanceId = null; renderState(); }
      catch (err) { button.disabled = false; button.textContent = "Gửi phiếu →"; error.textContent = err.message; error.hidden = false; }
    });
  }

  function errorView(message) {
    root.innerHTML = shell(`${brand()}<div class="mv-system-error"><div class="mv-error-icon">!</div><p class="mv-kicker">CHƯA KẾT NỐI ĐƯỢC</p><h1>Không thể tải hệ thống</h1><p>${esc(message)}</p><button type="button" id="mv-retry" class="mv-button mv-button--primary mv-button--block mv-retry-button">Thử tải lại</button></div>`);
    root.querySelector("#mv-retry").addEventListener("click", refresh);
  }
  async function refresh() {
    try {
      if (!api) throw new Error("Trang vote đang thiếu cấu hình kết nối.");
      const data = await request("bootstrap", { headers: {} });
      if (data.enabled === false) { state = null; lockedView(); return; }
      if (data.authenticated) {
        state = data.state;
        const params = new URLSearchParams(window.location.search);
        if (params.get("from") === "qr" && state?.voter) {
          params.delete("from");
          const next = `${window.location.pathname}${params.toString() ? `?${params}` : ""}${window.location.hash}`;
          window.history.replaceState({}, "", next);
          confirmView(state.voter, state);
          return;
        }
        renderState();
      } else loginView();
    } catch (err) {
      errorView(err.message || "Vui lòng thử lại hoặc báo cho ban tổ chức.");
    }
  }
  refresh();
})();
