(() => {
  const root = document.getElementById("mac-voting-admin");
  if (!root || !window.MACVotingAdmin) return;
  let data = null;
  const canWrite = () => window.MACVotingAdmin.role === "super";
  let tab = "overview";
  let importFeedback = null;
  let loadingOverview = false;
  let peopleTeam = "all";
  let peopleQuery = "";
  let awardCategoryId = "";
  let awardTeamId = "";
  let awardCustom = "";
  let overviewTab = "chart";
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
  const ajax = async (action, values = {}, file = null) => {
    const body = new FormData(); body.append("action", action); body.append("nonce", window.MACVotingAdmin.nonce);
    Object.entries(values).forEach(([key, value]) => body.append(key, value)); if (file) body.append("file", file);
    const response = await fetch(window.MACVotingAdmin.ajaxUrl, { method: "POST", credentials: "same-origin", body });
    const responseText = await response.text();
    let result;
    try { result = JSON.parse(responseText); }
    catch { throw new Error("Máy chủ trả về dữ liệu không hợp lệ. Hãy kiểm tra PHP log hoặc tắt hiển thị warning trên trang."); }
    if (!response.ok || !result.success) {
      const error = new Error(result.data?.message || "Thao tác thất bại.");
      error.details = result.data || {};
      error.status = response.status;
      throw error;
    }
    return result.data;
  };
  const notify = (message, error = false) => { const old = root.querySelector(".ma-alert"); if (old) old.remove(); const box = document.createElement("div"); box.className = "ma-alert" + (error ? " error" : ""); box.setAttribute("role", error ? "alert" : "status"); box.setAttribute("aria-live", error ? "assertive" : "polite"); box.textContent = message; root.prepend(box); setTimeout(() => box.remove(), 4500); };
  const normalizeHeader = (value) => String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/đ/g, "d")
    .replace(/Đ/g, "D")
    .trim()
    .toLowerCase()
    .replace(/\s+/g, " ");
  const formatBytes = (bytes) => bytes < 1024 ? `${bytes} B` : bytes < 1048576 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1048576).toFixed(1)} MB`;

  function parseCsv(text) {
    const firstLine = text.replace(/^\uFEFF/, "").split(/\r?\n/, 1)[0] || "";
    const delimiter = (firstLine.match(/;/g) || []).length > (firstLine.match(/,/g) || []).length ? ";" : ",";
    const rows = []; let row = []; let cell = ""; let quoted = false;
    const source = text.replace(/^\uFEFF/, "");
    for (let index = 0; index < source.length; index += 1) {
      const char = source[index];
      if (char === '"') {
        if (quoted && source[index + 1] === '"') { cell += '"'; index += 1; }
        else quoted = !quoted;
      } else if (char === delimiter && !quoted) { row.push(cell); cell = ""; }
      else if ((char === "\n" || char === "\r") && !quoted) {
        if (char === "\r" && source[index + 1] === "\n") index += 1;
        row.push(cell); if (row.some((value) => value.trim() !== "")) rows.push(row); row = []; cell = "";
      } else cell += char;
    }
    row.push(cell); if (row.some((value) => value.trim() !== "")) rows.push(row);
    return { delimiter, rows };
  }

  async function previewImport(file) {
    if (!/\.csv$/i.test(file.name)) { notify("Chỉ chấp nhận file CSV.", true); return; }
    if (file.size > 5 * 1024 * 1024) { notify("File CSV không được lớn hơn 5 MB.", true); return; }
    const parsed = parseCsv(await file.text());
    const headers = parsed.rows[0] || [];
    const normalized = headers.map(normalizeHeader);
    const required = [
      ["Họ tên", ["ho ten", "ten", "full name"]],
      ["Team", ["team", "doi"]],
      ["Email", ["email", "mail", "email cong ty"]],
    ];
    const missing = required.filter(([, aliases]) => !aliases.some((name) => normalized.includes(name))).map(([label]) => label);
    const previewRows = parsed.rows.slice(1);
    const nameIndex = normalized.findIndex((value) => ["ho ten", "ten", "full name"].includes(value));
    const teamIndex = normalized.findIndex((value) => ["team", "doi"].includes(value));
    const emailIndex = normalized.findIndex((value) => ["email", "mail", "email cong ty"].includes(value));
    const validTeams = (data?.teams || []).map((team) => normalizeHeader(team.name));
    const seenEmails = new Map();
    const rowErrors = missing.length ? [] : previewRows.flatMap((values, index) => {
      const issues = [];
      const name = String(values[nameIndex] || "").trim();
      const team = normalizeHeader(values[teamIndex] || "").replace(/^#?\s*\d+\s*/, "").trim();
      const emailValue = String(values[emailIndex] || "").trim().toLowerCase();
      const email = emailValue && !emailValue.includes("@") ? `${emailValue}@macusaone.com` : emailValue;
      if (!name) issues.push("thiếu họ tên");
      if (!validTeams.includes(team)) issues.push("team không hợp lệ");
      if (!/^[^\s@]+@macusaone\.com$/i.test(email)) issues.push("email phải thuộc @macusaone.com");
      if (email && seenEmails.has(email)) issues.push(`email trùng dòng ${seenEmails.get(email)}`);
      else if (email) seenEmails.set(email, index + 2);
      return issues.length ? [`Dòng ${index + 2}: ${issues.join(", ")}`] : [];
    });
    let serverValidation = null;
    let serverError = "";
    if (!missing.length && !rowErrors.length && previewRows.length > 0) {
      const checking = document.createElement("div");
      checking.className = "ma-import-checking";
      checking.setAttribute("role", "status");
      checking.innerHTML = `<span></span><strong>Đang kiểm tra ${previewRows.length} dòng trên server…</strong>`;
      root.append(checking);
      try { serverValidation = await ajax("mac_vote_import", { dryRun: "1" }, file); }
      catch (err) { serverError = err.message; }
      finally { checking.remove(); }
    }
    const canImport = !missing.length && !rowErrors.length && !serverError && previewRows.length > 0;
    const modal = document.createElement("div");
    modal.className = "ma-modal";
    const validationMessage = missing.length
      ? `<div class="ma-import-warning"><strong>Thiếu cột bắt buộc: ${esc(missing.join(", "))}</strong><span>Hãy sửa file rồi chọn lại.</span></div>`
      : rowErrors.length
        ? `<div class="ma-import-warning"><strong>${rowErrors.length} dòng chưa hợp lệ</strong><span>${rowErrors.slice(0, 10).map(esc).join("<br>")}${rowErrors.length > 10 ? `<br>…và ${rowErrors.length - 10} lỗi khác` : ""}</span></div>`
        : serverError
          ? `<div class="ma-import-warning"><strong>Server chưa chấp nhận file</strong><span>${esc(serverError)}</span></div>`
          : `<div class="ma-import-ready"><strong>✓ ${previewRows.length} dòng hợp lệ</strong><span>${esc(serverValidation?.message || `Toàn bộ ${previewRows.length} người đã được kiểm tra và chưa ghi vào database.`)}</span></div>`;
    modal.innerHTML = `<div class="ma-modal-card" role="dialog" aria-modal="true" aria-labelledby="ma-import-title"><div class="ma-modal-head"><div><small>XEM TRƯỚC DỮ LIỆU</small><h2 id="ma-import-title">Xác nhận import CSV</h2></div><button type="button" id="ma-import-close" aria-label="Đóng">×</button></div><div class="ma-file-summary"><span>CSV</span><div><strong>${esc(file.name)}</strong><small>${formatBytes(file.size)} · ${previewRows.length} dòng dữ liệu · dấu phân cách ${parsed.delimiter === ";" ? "chấm phẩy" : "phẩy"}</small></div></div>${validationMessage}<div class="ma-preview-count">Đang hiển thị đủ <strong>${previewRows.length}/${previewRows.length}</strong> người</div><div class="ma-preview-table"><table><thead><tr><th>#</th>${headers.slice(0, 8).map((value) => `<th>${esc(value)}</th>`).join("")}</tr></thead><tbody>${previewRows.map((values, rowIndex) => `<tr><td>${rowIndex + 1}</td>${headers.slice(0, 8).map((_, index) => `<td>${esc(values[index] || "")}</td>`).join("")}</tr>`).join("") || `<tr><td colspan="${Math.max(headers.length + 1, 2)}">Không có dòng dữ liệu.</td></tr>`}</tbody></table></div><div class="ma-modal-actions"><button type="button" id="ma-import-cancel">Hủy</button><button type="button" id="ma-import-confirm" class="ma-primary" ${canImport ? "" : "disabled"}>Xác nhận import ${previewRows.length} người</button></div></div>`;
    root.append(modal);
    const previousFocus = document.activeElement;
    const close = () => { document.removeEventListener("keydown", handleKeydown); modal.remove(); previousFocus?.focus?.(); };
    const handleKeydown = (event) => { if (event.key === "Escape") close(); };
    document.addEventListener("keydown", handleKeydown);
    modal.addEventListener("click", (event) => { if (event.target === modal) close(); });
    modal.querySelector("#ma-import-close").addEventListener("click", close);
    modal.querySelector("#ma-import-cancel").addEventListener("click", close);
    modal.querySelector("#ma-import-close").focus();
    modal.querySelector("#ma-import-confirm").addEventListener("click", async (event) => {
      const button = event.currentTarget; button.disabled = true; button.textContent = "Đang import...";
      try {
        const result = await ajax("mac_vote_import", {}, file);
        data = result.overview;
        importFeedback = {
          message: result.message,
          fileName: file.name,
          at: new Date().toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" }),
          staffAccounts: result.staffAccounts || [],
        };
        close(); tab = "data"; render(); notify(result.message);
      } catch (err) { button.disabled = false; button.textContent = "Thử import lại"; notify(err.message, true); }
    });
  }

  function showRoundConflictModal(targetRoundId, activeRoundId) {
    if (root.querySelector(".ma-round-conflict-modal")) return;
    const modal = document.createElement("div");
    modal.className = "ma-modal";
    modal.innerHTML = `<div class="ma-modal-card ma-round-conflict-modal" role="alertdialog" aria-modal="true" aria-labelledby="ma-round-conflict-title" aria-describedby="ma-round-conflict-description"><div class="ma-modal-head"><div><small>CẦN ĐÓNG LƯỢT HIỆN TẠI</small><h2 id="ma-round-conflict-title">Chưa thể mở lượt ${esc(targetRoundId)}</h2></div><button type="button" data-conflict-close aria-label="Đóng">×</button></div><div class="ma-round-conflict-warning"><span aria-hidden="true">!</span><div><strong>Lượt ${esc(activeRoundId)} vẫn đang mở vote</strong><p id="ma-round-conflict-description">Mỗi thời điểm chỉ được mở một lượt. Hãy đóng lượt ${esc(activeRoundId)} trước, sau đó mới mở lượt ${esc(targetRoundId)}.</p></div></div><div class="ma-round-conflict-flow" aria-hidden="true"><span class="is-open"><i></i>Lượt ${esc(activeRoundId)} · Đang mở</span><b>→</b><span><i></i>Lượt ${esc(targetRoundId)} · Đang chờ</span></div><div class="ma-modal-actions"><button type="button" data-conflict-close>Để sau</button><button type="button" class="ma-primary" id="ma-go-active-round">Đến nút đóng lượt ${esc(activeRoundId)}</button></div></div>`;
    root.append(modal);
    const previousFocus = document.activeElement;
    const focusable = Array.from(modal.querySelectorAll("button:not([disabled])"));
    const close = (restoreFocus = true) => {
      document.removeEventListener("keydown", handleKeydown);
      modal.remove();
      if (restoreFocus) previousFocus?.focus?.();
    };
    const handleKeydown = (event) => {
      if (event.key === "Escape") { close(); return; }
      if (event.key !== "Tab" || focusable.length < 2) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    };
    document.addEventListener("keydown", handleKeydown);
    modal.addEventListener("click", (event) => { if (event.target === modal) close(); });
    modal.querySelectorAll("[data-conflict-close]").forEach((button) => button.addEventListener("click", () => close()));
    modal.querySelector("#ma-go-active-round").addEventListener("click", () => {
      close(false);
      const closeRoundButton = Array.from(root.querySelectorAll('[data-round][data-op="close"]'))
        .find((candidate) => String(candidate.dataset.round) === String(activeRoundId));
      if (!closeRoundButton) { load(true); return; }
      closeRoundButton.scrollIntoView({ behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth", block: "center" });
      closeRoundButton.focus({ preventScroll: true });
    });
    modal.querySelector("#ma-go-active-round").focus();
  }

  function showResetModal() {
    const modal = document.createElement("div");
    modal.className = "ma-modal";
    modal.innerHTML = `<div class="ma-modal-card ma-reset-modal" role="dialog" aria-modal="true" aria-labelledby="ma-reset-title" aria-describedby="ma-reset-description"><div class="ma-modal-head"><div><small>VÙNG NGUY HIỂM</small><h2 id="ma-reset-title">Đặt lại toàn bộ phiên?</h2></div><button type="button" id="ma-reset-close" aria-label="Đóng">×</button></div><div id="ma-reset-description" class="ma-reset-warning"><span>!</span><div><strong>Thao tác này không thể hoàn tác</strong><p>Hệ thống sẽ xóa phiếu, quyền vote lại, dữ liệu check-in và tắt cổng văn nghệ.</p></div></div><ul class="ma-reset-list"><li><strong>Xóa:</strong> phiếu, quyền vote lại, check-in, điểm mốc, điểm hạng mục và lịch sử cộng điểm.</li><li><strong>Đặt lại:</strong> 3 lượt văn nghệ và 4 mốc check-in về DRAFT. Cổng văn nghệ về TẮT.</li><li><strong>Giữ nguyên:</strong> nhân sự, email, QR, team và lịch biểu diễn.</li></ul><label class="ma-reset-confirm" for="ma-reset-input"><span>Nhập <code>RESET</code> để xác nhận</span><input id="ma-reset-input" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="RESET"></label><div class="ma-modal-actions"><button type="button" id="ma-reset-cancel">Hủy</button><button type="button" id="ma-reset-confirm-button" class="ma-danger-button" disabled>Xóa dữ liệu sự kiện</button></div></div>`;
    root.append(modal);
    const previousFocus = document.activeElement;
    const input = modal.querySelector("#ma-reset-input");
    const confirmButton = modal.querySelector("#ma-reset-confirm-button");
    let resetting = false;
    const close = () => {
      if (resetting) return;
      document.removeEventListener("keydown", handleKeydown);
      modal.remove();
      previousFocus?.focus?.();
    };
    const handleKeydown = (event) => { if (event.key === "Escape") close(); };
    document.addEventListener("keydown", handleKeydown);
    modal.addEventListener("click", (event) => { if (event.target === modal) close(); });
    modal.querySelector("#ma-reset-close").addEventListener("click", close);
    modal.querySelector("#ma-reset-cancel").addEventListener("click", close);
    input.addEventListener("input", () => {
      input.value = input.value.toUpperCase().replace(/[^A-Z]/g, "").slice(0, 5);
      confirmButton.disabled = input.value !== "RESET";
    });
    confirmButton.addEventListener("click", async () => {
      if (input.value !== "RESET" || resetting) return;
      resetting = true;
      input.disabled = true;
      confirmButton.disabled = true;
      confirmButton.classList.add("is-loading");
      confirmButton.textContent = "Đang đặt lại…";
      try {
        const result = await ajax("mac_vote_reset_event", { confirmation: input.value });
        data = result.overview;
        resetting = false;
        close();
        render();
        notify(result.message);
      } catch (err) {
        resetting = false;
        input.disabled = false;
        confirmButton.disabled = false;
        confirmButton.classList.remove("is-loading");
        confirmButton.textContent = "Thử đặt lại lần nữa";
        notify(err.message, true);
      }
    });
    input.focus();
  }

  const topActions = () => `<div class="ma-top-actions"><button id="ma-refresh">↻ Tải lại dữ liệu</button>${canWrite() ? `<button id="ma-reset-event" class="ma-reset-trigger">Đặt lại sự kiện</button>` : ""}</div>`;

  function teamManager() {
    const teams = data.teams || [];
    return `<section class="ma-panel ma-team-manager"><header><div><small>QUẢN LÝ TEAM</small><h2>Danh sách team linh hoạt</h2><p>Đổi tên trực tiếp hoặc thêm team mới. Chương trình vẫn có 6 slot; có thể chuyển nhân sự bằng cách import lại CSV rồi chọn team biểu diễn trong lịch.</p></div><form id="ma-team-add"><label for="ma-new-team-name">Tên team mới</label><div><input id="ma-new-team-name" name="name" type="text" minlength="2" maxlength="100" placeholder="Ví dụ: Sóng Biển" required><button class="ma-primary" type="submit">+ Thêm team</button></div></form></header><div class="ma-team-list">${teams.map((team) => {
      const scheduled = Number(team.is_scheduled) > 0;
      const voters = Number(team.voter_count);
      const ballots = Number(team.ballot_count);
      const blockedReason = scheduled ? "Team đang nằm trong lịch biểu diễn" : voters ? `Team còn ${voters} nhân sự` : ballots ? "Team còn dữ liệu phiếu" : "";
      return `<article data-team-row="${team.id}"><span class="ma-team-number">#${team.team_no}</span><div class="ma-team-edit"><label for="ma-team-${team.id}">Tên team</label><input id="ma-team-${team.id}" type="text" minlength="2" maxlength="100" value="${esc(team.name)}" data-original="${esc(team.name)}"></div><div class="ma-team-meta"><span>${voters} nhân sự</span><span class="${scheduled ? "scheduled" : "unscheduled"}">${scheduled ? "Đã xếp lịch" : "Chưa xếp lịch"}</span></div><div class="ma-team-actions"><button type="button" data-team-save="${team.id}">Lưu tên</button><button type="button" class="danger" data-team-delete="${team.id}" data-team-name="${esc(team.name)}" ${blockedReason ? `disabled title="${esc(blockedReason)}"` : ""}>Xóa</button></div></article>`;
    }).join("")}</div></section>`;
  }

  async function load(announce = false) {
    if (loadingOverview) return;
    loadingOverview = true;
    const refreshButton = root.querySelector("#ma-refresh");
    if (refreshButton) { refreshButton.disabled = true; refreshButton.classList.add("is-loading"); refreshButton.textContent = "Đang tải lại…"; }
    try {
      data = await ajax("mac_vote_overview");
      render();
      if (announce) notify("Dữ liệu dashboard đã được cập nhật.");
    } catch (err) {
      if (data) {
        if (announce) notify(err.message, true);
        if (refreshButton) { refreshButton.disabled = false; refreshButton.classList.remove("is-loading"); refreshButton.textContent = "↻ Tải lại dữ liệu"; }
      } else {
        root.innerHTML = `<div class="ma-load-error" role="alert"><strong>Không tải được dashboard</strong><p>${esc(err.message)}</p><button id="ma-load-retry">Thử lại</button></div>`;
        root.querySelector("#ma-load-retry")?.addEventListener("click", () => load(true));
      }
    } finally { loadingOverview = false; }
  }
  const statusLabel = (status) => status === "OPEN" ? "Đang mở vote" : status === "CLOSED" ? "Đã đóng" : "Chưa bắt đầu";
  const revealStageLabel = (stage) => ({ IDLE: "Đang chờ", ROLLING: "Đang tung điểm", DECOY: "Đã chốt cú lừa", THIRD: "Đã công bố hạng ba", SECOND: "Đã công bố hạng nhì", FINAL: "Đã công bố quán quân" }[stage] || "Đang chờ");
  function sidebar() { return `<aside class="ma-side"><div class="ma-brand"><img src="${esc(window.MACVotingAdmin.logo)}"><div><strong>Company Trip</strong>${canWrite() ? "" : `<small>Admin · chỉ xem</small>`}</div></div><nav><button data-tab="overview" class="${tab === "overview" ? "active" : ""}">◉ Tổng quan</button><button data-tab="checkin" class="${tab === "checkin" ? "active" : ""}">▣ Check-in</button><button data-tab="art" class="${tab === "art" ? "active" : ""}">♪ Văn nghệ</button><button data-tab="data" class="${tab === "data" ? "active" : ""}">▦ Nhân sự & QR</button></nav><div class="ma-side-links"><a href="${esc(window.MACVotingAdmin.checkinUrl)}">↗ Máy quét BTC</a>${canWrite() ? `<a href="${esc(window.MACVotingAdmin.resultsUrl)}" target="_blank" rel="noopener">↗ Màn hình kết quả</a><a href="${esc(window.MACVotingAdmin.voteUrl)}" target="_blank" rel="noopener">↗ Mở trang vote</a>` : ""}</div><a class="ma-side-logout" href="${esc(window.MACVotingAdmin.logoutUrl)}">Đăng xuất</a></aside>`; }
  function votingGate() {
    const on = !!data.votingEnabled;
    return `<section class="ma-panel ma-gate ${on ? "is-on" : "is-off"}"><header><div><small>VĂN NGHỆ</small><h2>${on ? "Cổng đang bật" : "Cổng đang tắt"}</h2><p>${on ? "QR cá nhân và login username đã được mở." : "Public không thể đăng nhập hoặc chấm điểm, kể cả khi còn cookie cũ."}</p></div><span class="ma-gate-status">${on ? "ĐANG BẬT" : "TẮT"}</span></header>${canWrite() ? `<div style="padding:16px 20px 20px"><button type="button" id="ma-gate" class="${on ? "danger" : "ma-primary"}">${on ? "Tắt cổng văn nghệ" : "Bật cổng chấm điểm"}</button></div>` : ""}</section>`;
  }
  const checkpointStatus = (status) => status === "OPEN" ? "Đang mở" : status === "CLOSED" ? "Đã chốt" : "Chưa mở";
  function checkinView() {
    const boards = data.checkinBoard || [];
    const staff = data.staff || [];
    const users = data.assignableUsers || [];
    const teams = data.teams || [];
    const scanner = `<section class="ma-panel ma-scanner-cta"><header><div><small>MÁY QUÉT</small><h2>Check-in bằng QR</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Mở trang quét để điểm danh team được gán.</p></div><a class="ma-primary" href="${esc(window.MACVotingAdmin.checkinUrl)}">Mở máy quét BTC</a></header></section>`;
    const checkpointCards = (data.checkpoints || []).map((item) => `<article class="ma-check-card ${item.status.toLowerCase()}"><small>MỐC ${item.id} · ${checkpointStatus(item.status)}</small><h2>${esc(item.name)}</h2><p>${esc(item.description || "")}</p>${canWrite() ? `<div class="ma-check-actions">${item.status === "DRAFT" ? `<button type="button" class="ma-primary" data-checkpoint="${item.id}" data-op="open">Mở mốc</button>` : item.status === "OPEN" ? `<button type="button" class="danger" data-checkpoint="${item.id}" data-op="close">Đóng & chốt</button>` : `<button type="button" data-checkpoint="${item.id}" data-op="reopen">Mở lại</button>`}</div>` : ""}</article>`).join("");
    const progress = `<section class="ma-panel"><header><div><small>TIẾN ĐỘ</small><h2>Team đã đủ / chưa đủ</h2></div>${canWrite() ? `<a class="ma-primary" href="${esc(window.MACVotingAdmin.checkinExportUrl)}">↓ Xuất CSV check-in</a>` : ""}</header>${boards.map((board) => `<div class="ma-board-table" style="margin:12px 16px 16px"><table><thead><tr><th>Mốc ${board.checkpoint.id} · ${esc(board.checkpoint.name)}</th><th>Tiến độ</th><th>Hoàn thành</th><th>Hạng</th><th>Điểm</th></tr></thead><tbody>${board.teams.map((team) => `<tr><td><strong>#${team.teamNumber} ${esc(team.teamName)}</strong></td><td>${team.checkedIn}/${team.eligible}</td><td>${team.completedAt || "—"}</td><td>${team.temporaryRank || "—"}</td><td>${team.temporaryPoints || 0}</td></tr>`).join("")}</tbody></table></div>`).join("")}</section>`;
    const staffPanel = canWrite() ? `<section class="ma-panel"><header><div><small>BTC</small><h2>Tài khoản máy quét</h2></div></header><form class="ma-staff-form" id="ma-staff-form"><label>Chọn tài khoản WordPress<select id="ma-staff-user">${users.map((user) => `<option value="${user.id}">${esc(user.name)} · ${esc(user.email)}</option>`).join("")}</select></label><div class="ma-staff-teams">${teams.map((team) => `<label><input type="checkbox" name="teamIds" value="${team.id}"> #${team.team_no} ${esc(team.name)}</label>`).join("")}</div><button type="submit" class="ma-primary">Lưu quyền check-in</button><p style="margin:0;color:#667085;font-size:13px">Admin quét được mọi team. BTC thường chỉ được gán 1-2 team.</p></form><div class="ma-board-table" style="margin:0 16px 16px"><table><thead><tr><th>BTC</th><th>Team được gán</th></tr></thead><tbody>${staff.map((item) => `<tr><td><strong>${esc(item.name)}</strong><small>${esc(item.email)}${item.isAdmin ? " · Admin" : ""}</small></td><td>${item.isAdmin ? "Tất cả team" : (item.teamIds || []).map((id) => { const team = teams.find((row) => String(row.id) === String(id)); return team ? `#${team.team_no} ${team.name}` : id; }).join(", ") || "Chưa gán"}</td></tr>`).join("") || `<tr><td colspan="2">Chưa có tài khoản BTC.</td></tr>`}</tbody></table></div></section>` : "";
    return `<header class="ma-top"><div><small>CHECK-IN</small><h1>4 mốc Company Trip</h1></div>${topActions()}</header>${scanner}<div class="ma-check-grid">${checkpointCards}</div>${progress}${staffPanel}`;
  }
  function pointsView() {
    const board = data.totalBoard || { categories: [], teams: [], history: [] };
    const categories = board.categories || [];
    const ranked = board.teams || [];
    const teams = ranked.slice().sort((a, b) => a.teamNumber - b.teamNumber);
    const history = board.history || [];
    const fmt = (value) => Number(value) > 0 ? `+${value}` : String(value);
    const presets = [-30, -20, -10, 10, 20, 30, 40, 50];
    if (!categories.some((category) => String(category.id) === String(awardCategoryId))) {
      awardCategoryId = categories[0] ? String(categories[0].id) : "";
    }
    if (!teams.some((team) => String(team.teamId) === String(awardTeamId))) {
      awardTeamId = "";
    }
    const selectedTeam = teams.find((team) => String(team.teamId) === String(awardTeamId));
    const currentPoints = selectedTeam
      ? Number((selectedTeam.cells || []).find((entry) => String(entry.categoryId) === String(awardCategoryId))?.points || 0)
      : 0;
    const canScore = Boolean(awardCategoryId && awardTeamId);
    const teamScore = (team) => Number((team.cells || []).find((entry) => String(entry.categoryId) === String(awardCategoryId))?.points || 0);
    const selectedCategory = categories.find((category) => String(category.id) === String(awardCategoryId));
    const maxAbs = Math.max(1, ...ranked.map((team) => Math.abs(Number(team.total) || 0)));
    const checkinLedger = data.teamPoints || [];
    const checkpointCols = (data.checkpoints || []).slice().sort((a, b) => a.id - b.id);
    const tabs = [
      ["chart", "Tổng điểm"],
      ["award", "Điểm hạng mục"],
      ["history", "Lịch sử"],
    ];
    const nav = `<nav class="ma-subnav" aria-label="Tổng quan">${tabs.map(([id, label]) => `<button type="button" data-overview-tab="${id}" class="${overviewTab === id ? "active" : ""}">${label}</button>`).join("")}</nav>`;
    const chart = `<div class="ma-overview-stack"><section class="ma-panel ma-chart-panel"><header><div><small>TỔNG ĐIỂM</small><h2>6 đội · Check-in + hạng mục</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Cột đã gồm điểm check-in đã chốt và điểm hạng mục. Điểm văn nghệ (phiếu) chưa cộng vào đây.</p></div></header><div class="ma-chart" style="--ma-chart-max:${maxAbs}">${ranked.map((team) => { const total = Number(team.total) || 0; const height = Math.max(6, Math.round((Math.abs(total) / maxAbs) * 100)); return `<figure class="ma-chart-col ${total < 0 ? "is-neg" : ""}"><strong>${fmt(total)}</strong><div class="ma-chart-track" aria-hidden="true"><span style="height:${height}%"></span></div><figcaption><b>#${team.teamNumber} ${esc(team.teamName)}</b><small>Check-in ${fmt(team.checkin)} · Hạng mục ${fmt(team.categories)}</small></figcaption></figure>`; }).join("")}</div></section><section class="ma-panel"><header><div><small>CHECK-IN</small><h2>Sổ điểm 4 mốc</h2></div></header><div class="ma-board-table" style="margin:0"><table><thead><tr><th>Team</th>${checkpointCols.map((item) => `<th>${esc(item.name || ("Mốc " + item.id))}</th>`).join("")}<th>Tổng check-in</th></tr></thead><tbody>${checkinLedger.map((row) => `<tr><td><strong>#${row.teamNumber} ${esc(row.teamName)}</strong></td>${row.checkpoints.map((value) => `<td>${fmt(value)}</td>`).join("")}<td><strong>${fmt(row.total)}</strong></td></tr>`).join("") || `<tr><td colspan="${checkpointCols.length + 2}">Chưa có điểm check-in đã chốt.</td></tr>`}</tbody></table></div></section></div>`;
    const award = `<section class="ma-panel ma-award"><div class="ma-award-step"><div class="ma-award-step-head"><h2>1. Hạng mục</h2><div class="ma-award-cat-actions"><button type="button" id="ma-cat-add" aria-label="Thêm hạng mục">+</button><button type="button" id="ma-cat-edit" aria-label="Sửa hạng mục" ${awardCategoryId ? "" : "disabled"}>✎</button><button type="button" id="ma-cat-delete" class="is-danger" aria-label="Xóa hạng mục" ${awardCategoryId ? "" : "disabled"}>×</button></div></div>${categories.length ? `<select id="ma-award-category">${categories.map((category) => `<option value="${category.id}" ${String(category.id) === String(awardCategoryId) ? "selected" : ""}>${esc(category.name)}</option>`).join("")}</select>` : `<p class="ma-cat-empty">Chưa có hạng mục. Bấm + để thêm việc cần chấm.</p>`}</div><div class="ma-award-step"><h2>2. Team</h2><div class="ma-award-teams">${teams.map((team) => { const score = teamScore(team); const selected = String(team.teamId) === String(awardTeamId); return `<button type="button" data-award-team="${team.teamId}" class="${selected ? "is-selected" : ""}" ${awardCategoryId ? "" : "disabled"} aria-pressed="${selected}"><strong>${esc(team.teamName)}</strong>${score ? `<small>${fmt(score)}</small>` : ""}</button>`; }).join("")}</div></div><div class="ma-award-step"><h2>3. Điểm</h2><div class="ma-award-presets">${presets.map((value) => `<button type="button" data-award-points="${value}" class="${canScore && currentPoints === value ? "is-selected" : ""} ${value < 0 ? "is-minus" : ""}" ${canScore ? "" : "disabled"} aria-pressed="${canScore && currentPoints === value}">${fmt(value)}</button>`).join("")}</div><form id="ma-award-custom" class="ma-award-custom"><label class="sr-only" for="ma-award-custom-input">Điểm khác</label><input id="ma-award-custom-input" type="number" min="-100" max="100" step="1" placeholder="Điểm khác, vd: 25 hoặc -10" value="${esc(awardCustom)}" ${canScore ? "" : "disabled"}><button type="submit" ${canScore ? "" : "disabled"}>Chọn</button></form>${canScore && currentPoints !== 0 && !presets.includes(currentPoints) ? `<p class="ma-award-current">Điểm hiện tại của ${esc(selectedTeam.teamName)} · ${esc(selectedCategory?.name || "")}: <strong>${fmt(currentPoints)}</strong></p>` : ""}</div></section>`;
    const historyRows = history.length
      ? history.map((item) => `<tr class="is-${item.kind}"><td>${esc(item.at)}</td><td>${esc(item.actor)}</td><td>${item.teamNumber ? `#${item.teamNumber} ` : ""}${esc(item.teamName)}</td><td>${esc(item.source)}${item.note ? ` · ${esc(item.note)}` : ""}</td><td><strong>${item.kind === "clear" ? "Xóa điểm" : fmt(item.points)}</strong></td></tr>`).join("")
      : `<tr><td colspan="5">Chưa có lịch sử cộng điểm.</td></tr>`;
    const historyPanel = `<section class="ma-panel"><header><div><small>AUDIT</small><h2>Ai cộng điểm · lúc nào</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Gồm hạng mục admin chấm và điểm check-in khi đóng mốc.</p></div></header><div class="ma-table ma-history-table"><table><thead><tr><th>Thời gian</th><th>Người cộng</th><th>Team</th><th>Nguồn</th><th>Điểm</th></tr></thead><tbody>${historyRows}</tbody></table></div></section>`;
    const awardRead = `<section class="ma-panel"><header><div><small>HẠNG MỤC</small><h2>Điểm từng đội</h2></div></header><div class="ma-board-table" style="margin:0"><table><thead><tr><th>Team</th>${categories.map((category) => `<th>${esc(category.name)}</th>`).join("")}<th>Tổng</th></tr></thead><tbody>${teams.map((team) => `<tr><td><strong>#${team.teamNumber} ${esc(team.teamName)}</strong></td>${(team.cells || []).map((cell) => `<td>${fmt(cell.points)}</td>`).join("")}<td><strong>${fmt(team.categories)}</strong></td></tr>`).join("") || `<tr><td colspan="${Math.max(categories.length + 2, 2)}">Chưa có điểm hạng mục.</td></tr>`}</tbody></table></div></section>`;
    const body = overviewTab === "award" ? (canWrite() ? award : awardRead) : overviewTab === "history" ? historyPanel : chart;
    return `<header class="ma-top"><div><small>TỔNG QUAN</small><h1>Tổng điểm 6 team</h1></div>${topActions()}</header>${nav}${body}`;
  }
  function live() {
    const lockedTeams = new Set(data.rounds.filter((round) => round.status !== "DRAFT").flatMap((round) => round.slots.map((slot) => String(slot.team_id))));
    return `<div class="ma-art-live">${votingGate()}<div class="ma-stats"><article><span>Người được vote</span><strong>${data.stats.activeVoters}</strong></article><article><span>Phiếu hợp lệ</span><strong>${data.stats.validBallots}</strong></article><article><span>Phiếu đã hủy</span><strong>${data.stats.revokedBallots}</strong></article></div><div class="ma-grid"><section class="ma-panel"><header><div><small>LỊCH BIỂU DIỄN</small><h2>3 lượt · 6 tiết mục</h2></div><b>LIVE CONTROL</b></header><div class="ma-rounds">${data.rounds.map((round) => `<article class="ma-round ${round.status.toLowerCase()}"><div class="ma-round-head"><span><small>LƯỢT ${round.id}</small><strong>${statusLabel(round.status)}</strong></span>${canWrite() ? (round.status === "DRAFT" ? `<button data-round="${round.id}" data-op="open">▶ Mở vote</button>` : round.status === "OPEN" ? `<button class="danger" data-round="${round.id}" data-op="close">■ Đóng lượt</button>` : `<button class="reopen" data-round="${round.id}" data-op="reopen">↻ Mở lại</button>`) : ""}</div><div class="ma-slots">${round.slots.map((slot) => `<div><small>TIẾT MỤC ${slot.position}</small><strong>#${slot.team_no} ${esc(slot.team_name)}</strong>${round.status === "DRAFT" && canWrite() ? `<select data-slot="${slot.id}">${data.performances.map((performance) => `<option value="${performance.id}" ${String(performance.id) === String(slot.performance_id) ? "selected" : ""} ${lockedTeams.has(String(performance.team_id)) ? "disabled" : ""}>#${performance.team_no} ${esc(performance.team_name)}</option>`).join("")}</select>` : `<em>⌕ ${round.status === "DRAFT" ? "Lịch biểu diễn" : "Đã khóa lịch"}</em>`}</div>`).join("")}</div></article>`).join("")}</div></section><section class="ma-panel ma-results"><header><div><small>ĐIỂM TRỰC TIẾP</small><h2>Kết quả tạm tính</h2></div></header>${data.results.map((result, index) => `<div class="ma-result"><i>${result.average_score === null ? "—" : index + 1}</i><span><strong>#${result.team_no} ${esc(result.team_name)}</strong><small>${result.voter_count} phiếu hợp lệ</small></span><b>${result.average_score === null ? "Chưa vote" : Number(result.average_score).toFixed(2)}</b></div>`).join("")}<p>Điểm đầy đủ chỉ hiển thị cho admin cho tới tín hiệu công bố cuối.</p></section></div></div>`;
  }
  function revealView() {
    const stage = data.reveal?.stage || "IDLE";
    let previousScore = null; let currentRank = 0;
    const resultRows = data.results.map((result, index) => {
      if (result.average_score !== null && (previousScore === null || Number(previousScore) !== Number(result.average_score))) currentRank = index + 1;
      previousScore = result.average_score;
      return `<div class="ma-reveal-result"><i>${result.average_score === null ? "—" : currentRank}</i><span><strong>#${result.team_no} ${esc(result.team_name)}</strong><small>${result.voter_count} phiếu hợp lệ</small></span><b>${result.average_score === null ? "—" : Number(result.average_score).toFixed(2)}</b></div>`;
    }).join("");
    return `<section class="ma-art-block"><header class="ma-art-block-head"><div><small>CÔNG BỐ</small><h2>Công bố kết quả</h2></div><a class="ma-screen-link" href="${esc(window.MACVotingAdmin.resultsUrl)}" target="_blank" rel="noopener">↗ Màn hình trình chiếu</a></header><div class="ma-reveal-grid"><section class="ma-panel ma-reveal-control"><header><div><small>LIVE REVEAL</small><h2>Tín hiệu MC</h2></div><span class="ma-reveal-status ${stage.toLowerCase()}"><i></i>${revealStageLabel(stage)}</span></header><div class="ma-reveal-intro"><strong>Một cú lừa · Ba lần chốt giải</strong><p>Ba đội thấp điểm nhất hiện điểm thật trước. Sau đó công bố hạng 3 → hạng 2 → quán quân.</p></div><div class="ma-reveal-actions"><button type="button" class="ma-reveal-start" data-reveal-stage="ROLLING" ${stage === "IDLE" ? "" : "disabled"}><span>00</span><strong>Mở màn · Tung điểm</strong><small>6 cột chạy điểm</small></button><button type="button" data-reveal-stage="DECOY" ${stage === "ROLLING" ? "" : "disabled"}><span>01</span><strong>Chốt cú lừa</strong><small>Điểm thật ba đội cuối</small></button><button type="button" data-reveal-stage="THIRD" ${stage === "DECOY" ? "" : "disabled"}><span>02</span><strong>Công bố hạng 3</strong><small>Mở đội thứ ba</small></button><button type="button" data-reveal-stage="SECOND" ${stage === "THIRD" ? "" : "disabled"}><span>03</span><strong>Công bố hạng 2</strong><small>Mở đội thứ hai</small></button><button type="button" class="ma-reveal-final" data-reveal-stage="FINAL" ${stage === "SECOND" ? "" : "disabled"}><span>04</span><strong>Công bố quán quân</strong><small>Mở vị trí cao nhất</small></button></div><div class="ma-reveal-footer"><p><i></i>Màn hình trình chiếu tự đồng bộ trong khoảng 1 giây.</p><button type="button" data-reveal-stage="IDLE" ${stage === "IDLE" ? "disabled" : ""}>↻ Đặt lại</button></div></section><aside class="ma-panel ma-reveal-scoreboard"><header><div><small>CHỈ ADMIN</small><h2>Điểm thật</h2></div></header><div>${resultRows}</div></aside></div></section>`;
  }
  function artView() {
    return `<header class="ma-top"><div><small>VĂN NGHỆ</small><h1>Chấm điểm tiết mục</h1></div>${topActions()}</header><div class="ma-art-stack">${live()}${revealView()}${ballots()}</div>`;
  }
  function ballots() { return `<section class="ma-art-block ma-art-ballots"><header class="ma-art-block-head"><div><small>PHIẾU</small><h2>${canWrite() ? "Quản lý phiếu" : "Phiếu đã chấm"}</h2></div></header><div class="ma-table"><table><thead><tr><th>Người chấm</th><th>Team chấm</th><th>Tiết mục</th><th>Điểm</th><th>Trạng thái</th>${canWrite() ? "<th>Thao tác</th>" : ""}</tr></thead><tbody>${data.ballots.map((ballot) => `<tr class="${ballot.status === "REVOKED" ? "revoked" : ""}"><td><strong>${esc(ballot.full_name)}</strong><small>${esc(ballot.created_at)}</small></td><td>${esc(ballot.voter_team)}</td><td>${esc(ballot.performance_team)}</td><td><details class="ma-ballot-detail"><summary><strong>${ballot.total_score}</strong><span>Xem chi tiết</span></summary><div><p><span>Phong cách & thần thái</span><b>${ballot.style_score}</b></p><p><span>Dàn dựng & sáng tạo</span><b>${ballot.staging_score}</b></p><p><span>Đồng đội & bản sắc</span><b>${ballot.teamwork_score}</b></p></div></details></td><td><span class="badge ${ballot.status.toLowerCase()}">${ballot.status === "VALID" ? "Hợp lệ" : "Đã hủy"}</span></td>${canWrite() ? `<td>${ballot.status === "VALID" ? `<button data-ballot="${ballot.id}" data-op="revoke">Hủy phiếu</button>` : !Number(ballot.has_revote_grant) ? `<button data-ballot="${ballot.id}" data-op="revote">Cho vote lại</button>` : "Đã cấp vote lại"}</td>` : ""}</tr>`).join("")}</tbody></table></div></section>`; }
  function personnelQr() {
    const voters = (data.voters || []).filter((row) => {
      if (peopleTeam !== "all" && String(row.team_id) !== String(peopleTeam)) return false;
      const haystack = `${row.full_name} ${row.email || ""}`.toLowerCase();
      if (peopleQuery && haystack.indexOf(peopleQuery.toLowerCase()) === -1) return false;
      return true;
    });
    return `<section class="ma-panel" style="margin-top:16px"><header><div><small>QR CÁ NHÂN</small><h2>${canWrite() ? "Gửi QR qua email" : "Danh sách nhân sự"}</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">${canWrite() ? "Mỗi người một QR. Dùng cho check-in và login văn nghệ." : "Chỉ xem danh sách. Super admin mới gửi hoặc tạo lại QR."}</p></div>${canWrite() ? `<button type="button" class="ma-primary" id="ma-send-filtered">Gửi QR cho danh sách đang lọc</button>` : ""}</header><div style="padding:16px 20px 20px"><div class="ma-people-filter"><select id="ma-people-team"><option value="all">Tất cả team</option>${(data.teams || []).map((team) => `<option value="${team.id}" ${String(peopleTeam) === String(team.id) ? "selected" : ""}>#${team.team_no} ${esc(team.name)}</option>`).join("")}</select><input id="ma-people-query" type="search" placeholder="Tìm tên hoặc email" value="${esc(peopleQuery)}"></div><div class="ma-people-table"><table><thead><tr><th>Họ tên</th><th>Email</th><th>Team</th><th>Trạng thái</th>${canWrite() ? "<th></th>" : ""}</tr></thead><tbody>${voters.map((row) => `<tr><td><strong>${esc(row.full_name)}</strong></td><td>${esc(row.email || "—")}</td><td>#${row.team_no} ${esc(row.team_name)}</td><td>${row.status === "ACTIVE" ? "Hoạt động" : "Ngưng"}</td>${canWrite() ? `<td class="ma-people-actions"><button type="button" data-qr-view="${row.id}">Xem & gửi</button><button type="button" data-qr-regen="${row.id}">Tạo lại QR</button></td>` : ""}</tr>`).join("") || `<tr><td colspan="${canWrite() ? 5 : 4}">Không có nhân sự khớp bộ lọc.</td></tr>`}</tbody></table></div></div></section>`;
  }
  function dataView() { return `<header class="ma-top"><div><small>NHÂN SỰ</small><h1>Nhân sự & QR</h1></div></header>${window.MACVotingAdmin.permalinkWarning ? `<div class="ma-permalink-warning"><strong>URL đẹp chưa được bật</strong><span>Website đang dùng link dạng <code>?page_id=...</code>. Chọn cấu trúc “Tên bài viết” rồi lưu để dùng đường dẫn /cham-diem-van-nghe/.</span><a href="${esc(window.MACVotingAdmin.permalinkSettingsUrl)}">Mở cài đặt Permalink →</a></div>` : ""}${Number(data.stats.missingEmailVoters) ? `<div class="ma-permalink-warning"><strong>${data.stats.missingEmailVoters} nhân sự chưa có email</strong><span>Những người này chưa thể đăng nhập bằng username. Hãy import lại CSV có cột Email để mapping vào dữ liệu cũ.</span><a href="${esc(window.MACVotingAdmin.templateUrl)}">Tải CSV mẫu mới →</a></div>` : ""}${importFeedback ? `<div class="ma-import-success"><span>✓</span><div><strong>${esc(importFeedback.message)}</strong><small>${esc(importFeedback.fileName)} · ${esc(importFeedback.at)} · Tổng hiện có ${data.stats.activeVoters} người được vote</small></div></div>${(importFeedback.staffAccounts || []).length ? `<div class="ma-import-success ma-staff-passwords"><span>!</span><div><strong>Mật khẩu tài khoản BTC — chỉ hiện một lần</strong><ul>${importFeedback.staffAccounts.map((item) => `<li><b>${esc(item.email)}</b> · ${esc(item.password)}</li>`).join("")}</ul></div></div>` : ""}` : ""}${canWrite() ? `<div class="ma-data"><section class="ma-panel"><span class="ma-icon">⇧</span><small>IMPORT CSV</small><h2>Danh sách nhân sự</h2><p>Cột bắt buộc: Họ tên, Team, Email. Cột tùy chọn: Vai trò (BTC/ADMIN) và Mật khẩu để tạo tài khoản dashboard. Email có thể là địa chỉ đầy đủ hoặc chỉ username trước @macusaone.com.</p><label class="ma-primary">Chọn & xem trước CSV<input id="ma-import" type="file" accept=".csv,text/csv"></label><a href="${esc(window.MACVotingAdmin.templateUrl)}">↓ Tải file mẫu</a></section><section class="ma-panel"><span class="ma-icon">▦</span><small>SAO LƯU & ĐỐI SOÁT</small><h2>Xuất dữ liệu</h2><p>Gồm bảng điểm và chi tiết toàn bộ phiếu hợp lệ/đã hủy.</p><a class="ma-primary" href="${esc(window.MACVotingAdmin.exportUrl)}">↓ Xuất CSV kết quả</a></section></div>` : ""}${personnelQr()}`; }
  function render() {
    root.classList.toggle("is-readonly", !canWrite());
    root.innerHTML = `<div class="ma-layout">${sidebar()}<main class="ma-content">${tab === "overview" ? pointsView() : tab === "checkin" ? checkinView() : tab === "art" ? artView() : dataView()}</main></div>`;
    if (tab === "data") {
      root.querySelector(".ma-content > .ma-top")?.insertAdjacentHTML("beforeend", topActions());
      if (canWrite()) root.querySelector(".ma-content")?.insertAdjacentHTML("beforeend", teamManager());
    }
    if (tab === "art") {
      let previousScore = null; let currentRank = 0;
      root.querySelectorAll(".ma-result i").forEach((element, index) => {
        const score = data.results[index].average_score;
        if (score === null) { element.textContent = "—"; return; }
        if (previousScore === null || Number(previousScore) !== Number(score)) currentRank = index + 1;
        previousScore = score; element.textContent = String(currentRank);
      });
    }
    root.querySelectorAll("[data-tab]").forEach((button) => button.addEventListener("click", () => { tab = button.dataset.tab; render(); }));
    root.querySelectorAll("[data-overview-tab]").forEach((button) => button.addEventListener("click", () => { overviewTab = button.dataset.overviewTab; render(); }));
    root.querySelector("#ma-refresh")?.addEventListener("click", () => load(true));
    if (canWrite()) {
    root.querySelector("#ma-reset-event")?.addEventListener("click", showResetModal);
    root.querySelectorAll("[data-reveal-stage]").forEach((button) => button.addEventListener("click", async () => {
      const stage = button.dataset.revealStage;
      const originalLabel = button.innerHTML;
      button.disabled = true;
      button.classList.add("is-loading");
      if (stage !== "IDLE") button.innerHTML = `<strong>Đang gửi tín hiệu…</strong><small>Giữ nguyên màn hình trình chiếu</small>`;
      try {
        const result = await ajax("mac_vote_reveal", { stage });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) {
        button.disabled = false;
        button.classList.remove("is-loading");
        button.innerHTML = originalLabel;
        notify(err.message, true);
      }
    }));
    root.querySelectorAll("[data-round]").forEach((button) => button.addEventListener("click", async () => {
      const operation = button.dataset.op;
      const activeRound = data.rounds.find((round) => round.status === "OPEN" && String(round.id) !== String(button.dataset.round));
      if ((operation === "open" || operation === "reopen") && activeRound) {
        showRoundConflictModal(button.dataset.round, activeRound.id);
        return;
      }
      const messages = {
        open: `Mở lượt ${button.dataset.round} để nhận phiếu?`,
        close: `Đóng lượt ${button.dataset.round}? Người chưa chấm sẽ tạm thời không gửi được phiếu.`,
        reopen: `Mở lại lượt ${button.dataset.round}? Phiếu cũ được giữ nguyên và chỉ người chưa chấm mới được tiếp tục.`,
      };
      if (!confirm(messages[operation])) return;
      const originalLabel = button.textContent;
      button.disabled = true;
      button.textContent = operation === "reopen" ? "Đang mở lại…" : "Đang cập nhật…";
      try {
        data = await ajax("mac_vote_round", { roundId: button.dataset.round, operation });
        render();
        notify(operation === "reopen" ? `Đã mở lại lượt ${button.dataset.round}.` : "Đã cập nhật lượt.");
      } catch (err) {
        button.disabled = false;
        button.textContent = originalLabel;
        if (err.details?.code === "round_already_open" && err.details?.openRoundId) {
          showRoundConflictModal(button.dataset.round, err.details.openRoundId);
          return;
        }
        notify(err.message, true);
        load();
      }
    }));
    root.querySelectorAll("select[data-slot]").forEach((select) => select.addEventListener("change", async () => { try { data = await ajax("mac_vote_swap", { slotId: select.dataset.slot, performanceId: select.value }); render(); notify("Đã đổi vị trí team."); } catch (err) { notify(err.message, true); load(); } }));
    root.querySelectorAll("[data-ballot]").forEach((button) => button.addEventListener("click", async () => { const reason = prompt(button.dataset.op === "revoke" ? "Lý do hủy phiếu:" : "Lý do cho phép vote lại:"); if (!reason) return; try { data = await ajax("mac_vote_ballot", { ballotId: button.dataset.ballot, operation: button.dataset.op, reason }); render(); notify("Đã cập nhật phiếu."); } catch (err) { notify(err.message, true); } }));
    root.querySelector("#ma-import")?.addEventListener("change", async (event) => { const file = event.target.files[0]; event.target.value = ""; if (file) await previewImport(file); });
    root.querySelector("#ma-team-add")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const input = form.querySelector("input[name='name']");
      const button = form.querySelector("button[type='submit']");
      const name = input.value.trim();
      if (name.length < 2) { notify("Tên team phải có ít nhất 2 ký tự.", true); input.focus(); return; }
      button.disabled = true; button.textContent = "Đang thêm…";
      try { const result = await ajax("mac_vote_team", { operation: "add", name }); data = result.overview; render(); notify(result.message); }
      catch (err) { button.disabled = false; button.textContent = "+ Thêm team"; notify(err.message, true); }
    });
    root.querySelectorAll("[data-team-save]").forEach((button) => button.addEventListener("click", async () => {
      const row = button.closest("[data-team-row]");
      const input = row.querySelector("input");
      const name = input.value.trim();
      if (name.length < 2) { notify("Tên team phải có ít nhất 2 ký tự.", true); input.focus(); return; }
      if (name === input.dataset.original) { notify("Tên team chưa thay đổi."); return; }
      button.disabled = true; button.textContent = "Đang lưu…";
      try { const result = await ajax("mac_vote_team", { operation: "rename", teamId: button.dataset.teamSave, name }); data = result.overview; render(); notify(result.message); }
      catch (err) { button.disabled = false; button.textContent = "Lưu tên"; notify(err.message, true); }
    }));
    root.querySelectorAll("[data-team-delete]").forEach((button) => button.addEventListener("click", async () => {
      if (!confirm(`Xóa team “${button.dataset.teamName}”? Team đã xóa không thể khôi phục.`)) return;
      button.disabled = true; button.textContent = "Đang xóa…";
      try { const result = await ajax("mac_vote_team", { operation: "delete", teamId: button.dataset.teamDelete }); data = result.overview; render(); notify(result.message); }
      catch (err) { button.disabled = false; button.textContent = "Xóa"; notify(err.message, true); }
    }));
    root.querySelector("#ma-gate")?.addEventListener("click", async () => {
      const next = !data.votingEnabled;
      if (!confirm(next ? "Bật cổng văn nghệ? Nhân viên sẽ login được bằng QR và username." : "Tắt cổng văn nghệ? Public sẽ không vào được trang chấm điểm.")) return;
      try {
        const result = await ajax("mac_vote_gate", { enabled: next ? "1" : "0" });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    });
    root.querySelectorAll("[data-checkpoint]").forEach((button) => button.addEventListener("click", async () => {
      const operation = button.dataset.op;
      const messages = {
        open: `Mở mốc ${button.dataset.checkpoint}? Chỉ một mốc được mở tại một thời điểm.`,
        close: `Đóng & chốt mốc ${button.dataset.checkpoint}? Điểm hạng sẽ được ghi vào sổ.`,
        reopen: `Mở lại mốc ${button.dataset.checkpoint}?`,
      };
      if (!confirm(messages[operation])) return;
      try {
        data = await ajax("mac_vote_checkpoint", { checkpointId: button.dataset.checkpoint, operation });
        render();
        notify("Đã cập nhật mốc check-in.");
      } catch (err) { notify(err.message, true); }
    }));
    root.querySelector("#ma-staff-form")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const userId = event.currentTarget.querySelector("#ma-staff-user").value;
      const teamIds = JSON.stringify(Array.from(event.currentTarget.querySelectorAll("input[name='teamIds']:checked")).map((input) => Number(input.value)));
      try {
        const result = await ajax("mac_vote_staff", { userId, teamIds });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    });
    }
    root.querySelector("#ma-people-team")?.addEventListener("change", (event) => { peopleTeam = event.currentTarget.value; render(); });
    root.querySelector("#ma-people-query")?.addEventListener("input", (event) => { peopleQuery = event.currentTarget.value; });
    root.querySelector("#ma-people-query")?.addEventListener("keydown", (event) => { if (event.key === "Enter") { peopleQuery = event.currentTarget.value; render(); } });
    if (!canWrite()) return;
    const qrPng = async (url) => window.MACQRCode.toDataURL(url, { width: 512, margin: 2, color: { dark: "#111827", light: "#ffffff" } });
    const sendQr = async (voter) => {
      if (!voter.email) throw new Error("Nhân sự này chưa có email.");
      const png = await qrPng(voter.qrUrl);
      return ajax("mac_vote_qr", { voterId: voter.id, operation: "email", png });
    };
    const showQrModal = (voter) => {
      const modal = document.createElement("div");
      modal.className = "ma-modal";
      modal.innerHTML = `<div class="ma-modal-card" role="dialog" aria-modal="true"><div class="ma-modal-head"><div><small>QR CÁ NHÂN</small><h2>${esc(voter.full_name)}</h2></div><button type="button" data-close aria-label="Đóng">×</button></div><div class="ma-qr-modal-preview"><canvas width="240" height="240"></canvas><strong>#${voter.team_no} ${esc(voter.team_name)}</strong><small>${esc(voter.email || "")}</small></div><div class="ma-modal-actions"><button type="button" data-close>Đóng</button><button type="button" class="ma-primary" data-send>Gửi email QR</button></div></div>`;
      root.append(modal);
      const canvas = modal.querySelector("canvas");
      if (canvas && window.MACQRCode) window.MACQRCode.toCanvas(canvas, voter.qrUrl, { width: 240, margin: 2, color: { dark: "#111827", light: "#ffffff" } });
      const close = () => modal.remove();
      modal.addEventListener("click", (event) => { if (event.target === modal) close(); });
      modal.querySelectorAll("[data-close]").forEach((button) => button.addEventListener("click", close));
      modal.querySelector("[data-send]").addEventListener("click", async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        button.textContent = "Đang gửi…";
        try { const result = await sendQr(voter); close(); notify(result.message); }
        catch (err) { button.disabled = false; button.textContent = "Gửi email QR"; notify(err.message, true); }
      });
    };
    root.querySelectorAll("[data-qr-view]").forEach((button) => button.addEventListener("click", () => {
      const voter = (data.voters || []).find((row) => String(row.id) === String(button.dataset.qrView));
      if (voter) showQrModal(voter);
    }));
    root.querySelectorAll("[data-qr-regen]").forEach((button) => button.addEventListener("click", async () => {
      if (!confirm("Tạo lại QR? QR cũ sẽ mất hiệu lực và cần gửi email mới.")) return;
      try {
        const result = await ajax("mac_vote_qr", { voterId: button.dataset.qrRegen, operation: "regenerate" });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    }));
    root.querySelector("#ma-send-filtered")?.addEventListener("click", async () => {
      const voters = (data.voters || []).filter((row) => {
        if (row.status !== "ACTIVE" || !row.email) return false;
        if (peopleTeam !== "all" && String(row.team_id) !== String(peopleTeam)) return false;
        return true;
      });
      if (!voters.length) { notify("Không có người ACTIVE có email trong bộ lọc.", true); return; }
      if (!confirm(`Gửi QR tới ${voters.length} người?`)) return;
      const button = root.querySelector("#ma-send-filtered");
      button.disabled = true;
      let sent = 0;
      for (const voter of voters) {
        button.textContent = `Đang gửi ${sent + 1}/${voters.length}…`;
        try { await sendQr(voter); sent += 1; }
        catch (err) { notify(`${voter.full_name}: ${err.message}`, true); }
      }
      button.disabled = false;
      button.textContent = "Gửi QR cho danh sách đang lọc";
      notify(`Đã gửi ${sent}/${voters.length} email QR.`);
    });
    root.querySelector("#ma-award-category")?.addEventListener("change", (event) => {
      awardCategoryId = event.currentTarget.value;
      render();
    });
    root.querySelectorAll("[data-award-team]").forEach((button) => button.addEventListener("click", () => {
      awardTeamId = button.dataset.awardTeam;
      render();
    }));
    const saveAward = async (points) => {
      if (!awardCategoryId || !awardTeamId) return;
      try {
        const result = await ajax("mac_vote_points", {
          operation: "award",
          categoryId: awardCategoryId,
          teamId: awardTeamId,
          points,
        });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) {
        notify(err.message, true);
      }
    };
    root.querySelectorAll("[data-award-points]").forEach((button) => button.addEventListener("click", async () => {
      const value = Number(button.dataset.awardPoints);
      const selectedTeam = (data.totalBoard?.teams || []).find((team) => String(team.teamId) === String(awardTeamId));
      const current = Number((selectedTeam?.cells || []).find((entry) => String(entry.categoryId) === String(awardCategoryId))?.points || 0);
      button.disabled = true;
      await saveAward(current === value ? 0 : value);
    }));
    root.querySelector("#ma-award-custom-input")?.addEventListener("input", (event) => {
      awardCustom = event.currentTarget.value;
    });
    root.querySelector("#ma-award-custom")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const raw = String(awardCustom || "").trim();
      const value = Number(raw);
      if (!raw || !Number.isInteger(value)) {
        notify("Nhập điểm nguyên, ví dụ 25 hoặc -10.", true);
        return;
      }
      if (value < -100 || value > 100 || value === 0) {
        notify("Điểm nhập tay từ -100 đến 100, khác 0. Bấm lại ô đang chọn để xóa điểm.", true);
        return;
      }
      const button = event.currentTarget.querySelector("button[type='submit']");
      button.disabled = true;
      await saveAward(value);
    });
    root.querySelector("#ma-cat-add")?.addEventListener("click", async () => {
      const name = prompt("Tên hạng mục mới", "");
      if (name === null) return;
      const trimmed = name.trim();
      if (trimmed.length < 2) { notify("Tên hạng mục phải có ít nhất 2 ký tự.", true); return; }
      try {
        const result = await ajax("mac_vote_points", { operation: "add", name: trimmed });
        if (result.categoryId) awardCategoryId = String(result.categoryId);
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    });
    root.querySelector("#ma-cat-edit")?.addEventListener("click", async () => {
      const current = (data.totalBoard?.categories || []).find((category) => String(category.id) === String(awardCategoryId));
      if (!current) return;
      const name = prompt("Đổi tên hạng mục", current.name);
      if (name === null) return;
      const trimmed = name.trim();
      if (trimmed.length < 2) { notify("Tên hạng mục phải có ít nhất 2 ký tự.", true); return; }
      try {
        const result = await ajax("mac_vote_points", { operation: "rename", categoryId: awardCategoryId, name: trimmed });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    });
    root.querySelector("#ma-cat-delete")?.addEventListener("click", async () => {
      const current = (data.totalBoard?.categories || []).find((category) => String(category.id) === String(awardCategoryId));
      if (!current) return;
      if (!confirm(`Xóa hạng mục “${current.name}”? Điểm đã cộng/trừ của hạng mục này cũng sẽ mất.`)) return;
      try {
        const result = await ajax("mac_vote_points", { operation: "delete", categoryId: awardCategoryId });
        awardCategoryId = "";
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    });
  }
  load(); setInterval(() => { if ((tab === "overview" || tab === "checkin" || tab === "art") && !document.hidden && !root.querySelector(".ma-modal") && !root.querySelector("#ma-award-custom-input:focus")) load(false); }, 5000);
})();
