(() => {
  const root = document.getElementById("mac-voting-admin");
  if (!root || !window.MACVotingAdmin) return;
  let data = null;
  const canWrite = () => window.MACVotingAdmin.role === "super";
  let tab = "overview";
  let importFeedback = null;
  let personFeedback = null;
  let loadingOverview = false;
  let peopleTeam = "all";
  let peopleQuery = "";
  let exemptQuery = "";
  let awardCategoryId = "";
  let awardTeamId = "";
  let overviewTab = "chart";
  let busManifestId = 0;
  let busQuery = "";
  let busAnyQuery = "";
  let busFilter = "all";
  let busSearch = "";
  const remainingSeconds = (closesAt) => Math.max(0, Math.ceil((new Date(String(closesAt).replace(" ", "T") + "Z").getTime() - Date.now()) / 1000));
  const formatRemainingTime = (closesAt) => {
    const seconds = remainingSeconds(closesAt);
    return `${String(Math.floor(seconds / 60)).padStart(2, "0")}:${String(seconds % 60).padStart(2, "0")}`;
  };
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
  const ladder = [50, 40, 30, 20, 10, 0];
  const fmt = (value) => Number(value) > 0 ? `+${value}` : String(value);
  const trim1 = (value) => { const n = Math.round(Number(value) * 10) / 10; return Number.isInteger(n) ? String(n) : n.toFixed(1); };
  const gamesMatrix = (games, gameBoard, teams) => `<div class="ma-board-table"><table><thead><tr><th>Team</th>${games.map((game) => `<th>${esc(game.name)}</th>`).join("")}<th>Tổng</th></tr></thead><tbody>${teams.map((team) => { const gameRow = gameBoard.find((row) => String(row.teamId) === String(team.teamId)); return `<tr><td><strong>#${team.teamNumber} ${esc(team.teamName)}</strong></td>${games.map((game) => { const cell = (gameRow?.cells || []).find((entry) => entry.gameId === game.id) || { rank: 0, points: 0 }; return `<td>${cell.rank >= 1 ? `<span class="ma-rank-chip rank-${cell.rank}">Hạng ${cell.rank}</span> <strong>${cell.points}đ</strong>` : "—"}</td>`; }).join("")}<td><strong>${gameRow?.total || 0}đ</strong></td></tr>`; }).join("") || `<tr><td colspan="${games.length + 2}">Chưa xếp hạng game.</td></tr>`}</tbody></table></div>`;
  const rankOfPoints = (points) => { const index = ladder.indexOf(Number(points)); return index >= 0 ? index + 1 : 0; };
  const categoryStatus = (category) => category?.isComplete
    ? { label: `✓ ${category.scoredTeams} tham gia · ${category.nonParticipatingTeams ?? Math.max(0, category.teamCount - category.scoredTeams)} không tham gia`, cls: "is-complete" }
    : { label: "Chưa có đội tham gia", cls: "is-progress" };
  const thiduaMatrix = (categories, teams) => `<div class="ma-board-table"><table><thead><tr><th>Team</th>${categories.map((category) => { const status = categoryStatus(category); return `<th>${esc(category.name)}<small class="ma-thidua-status ${status.cls}">${status.label}</small></th>`; }).join("")}<th>Điểm Thi đua</th></tr></thead><tbody>${teams.map((team) => `<tr><td><strong>#${team.teamNumber} ${esc(team.teamName)}</strong></td>${(team.cells || []).map((cell) => `<td class="${cell.hasScore ? "" : "ma-thidua-unscored"}">${cell.hasScore ? fmt(cell.points) : `0<small class="ma-cell-sub">Không tham gia</small>`}</td>`).join("")}<td><strong>${fmt(team.thidua)}/50</strong><small class="ma-thidua-score-meta">${team.thiduaCompletedRounds || 0} hạng mục được tính</small></td></tr>`).join("") || `<tr><td colspan="${Math.max(categories.length + 2, 2)}">Chưa có điểm thi đua.</td></tr>`}</tbody></table></div>`;
  // Optimistic update dùng CÙNG logic backend: chỉ trung bình các hạng mục hoàn tất, ROUND, kẹp 0-50.
  const refreshCategoryMeta = (categoryId, teams) => {
    const category = (data.totalBoard?.categories || []).find((entry) => String(entry.id) === String(categoryId));
    if (!category) return;
    const values = [];
    teams.forEach((team) => {
      const cell = (team.cells || []).find((entry) => String(entry.categoryId) === String(categoryId));
      if (cell?.hasScore) values.push(Number(cell.points) || 0);
    });
    category.scoredTeams = values.length;
    category.teamCount = teams.length;
    category.nonParticipatingTeams = Math.max(0, teams.length - values.length);
    category.isComplete = values.length > 0;
  };
  const recomputeThidua = (teams) => {
    const all = data.totalBoard?.categories || [];
    const completed = all.filter((category) => category.isComplete);
    teams.forEach((team) => {
      const raw = completed.reduce((sum, category) => {
        const cell = (team.cells || []).find((entry) => String(entry.categoryId) === String(category.id));
        return sum + (Number(cell?.points) || 0);
      }, 0);
      team.thiduaRawTotal = raw;
      team.thiduaCompletedRounds = completed.length;
      team.thiduaTotalRounds = all.length;
      team.thidua = completed.length ? Math.max(0, Math.min(50, Math.round(raw / completed.length))) : 0;
      team.total = (Number(team.checkin) || 0) + (Number(team.games) || 0) + (Number(team.vote) || 0) + team.thidua;
    });
    const sorted = teams.slice().sort((a, b) => (b.total - a.total) || (a.teamNumber - b.teamNumber));
    let rank = 0; let previous = null;
    sorted.forEach((team, index) => { if (previous === null || previous !== team.total) rank = index + 1; team.rank = rank; previous = team.total; });
  };
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
  const applyPointsPayload = (result) => {
    const board = result?.totalBoard || result?.overview?.totalBoard;
    if (board) data.totalBoard = board;
  };
  const applyCheckpointPayload = (result) => {
    if (Array.isArray(result?.checkpoints)) data.checkpoints = result.checkpoints;
    if (Array.isArray(result?.checkinBoard)) data.checkinBoard = result.checkinBoard;
    if (Array.isArray(result?.teamPoints)) data.teamPoints = result.teamPoints;
    if (result?.totalBoard) data.totalBoard = result.totalBoard;
    data.stats = { ...(data.stats || {}), openCheckpointId: Number(result?.openCheckpointId || 0) };
  };
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
      if (!/^[^\s@]+@(macusaone\.com|yesoffice\.vn|macmarketing\.vn)$/i.test(email)) issues.push("email phải thuộc @macusaone.com, @yesoffice.vn hoặc @macmarketing.vn");
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
    modal.innerHTML = `<div class="ma-modal-card ma-reset-modal" role="dialog" aria-modal="true" aria-labelledby="ma-reset-title" aria-describedby="ma-reset-description"><div class="ma-modal-head"><div><small>VÙNG NGUY HIỂM</small><h2 id="ma-reset-title">Đặt lại toàn bộ phiên?</h2></div><button type="button" id="ma-reset-close" aria-label="Đóng">×</button></div><div id="ma-reset-description" class="ma-reset-warning"><span>!</span><div><strong>Thao tác này không thể hoàn tác</strong><p>Hệ thống sẽ xóa phiếu, quyền vote lại, dữ liệu check-in và tắt cổng văn nghệ.</p></div></div><ul class="ma-reset-list"><li><strong>Xóa:</strong> phiếu, quyền vote lại, check-in, điểm mốc, xếp hạng trò chơi, điểm thi đua và lịch sử cộng điểm.</li><li><strong>Đặt lại:</strong> 3 lượt văn nghệ và 4 mốc check-in về DRAFT. Cổng văn nghệ về TẮT.</li><li><strong>Giữ nguyên:</strong> nhân sự, email, QR, team và lịch biểu diễn.</li></ul><label class="ma-reset-confirm" for="ma-reset-input"><span>Nhập <code>RESET</code> để xác nhận</span><input id="ma-reset-input" type="text" autocomplete="off" spellcheck="false" placeholder="RESET"></label><div class="ma-modal-actions"><button type="button" id="ma-reset-cancel">Hủy</button><button type="button" id="ma-reset-confirm-button" class="ma-danger-button" disabled>Xóa dữ liệu sự kiện</button></div></div>`;
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
    // Không ghi đè input.value khi đang gõ: làm vậy sẽ vỡ composition của bộ gõ tiếng Việt.
    let composing = false;
    const confirmValue = () => input.value.toUpperCase().replace(/[^A-Z]/g, "").slice(0, 5);
    const syncConfirm = () => { confirmButton.disabled = confirmValue() !== "RESET"; };
    input.addEventListener("compositionstart", () => { composing = true; });
    input.addEventListener("compositionend", () => { composing = false; syncConfirm(); });
    input.addEventListener("input", () => { if (!composing) syncConfirm(); });
    confirmButton.addEventListener("click", async () => {
      if (confirmValue() !== "RESET" || resetting) return;
      resetting = true;
      input.disabled = true;
      confirmButton.disabled = true;
      confirmButton.classList.add("is-loading");
      confirmButton.textContent = "Đang đặt lại…";
      try {
        const result = await ajax("mac_vote_reset_event", { confirmation: confirmValue() });
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

  function confirmDialog({ title, message, confirmLabel = "Xác nhận", danger = false }) {
    return new Promise((resolve) => {
      const modal = document.createElement("div");
      modal.className = "ma-modal";
      modal.innerHTML = `<div class="ma-modal-card ma-confirm-card" role="dialog" aria-modal="true"><div class="ma-modal-head"><div><small>XÁC NHẬN</small><h2>${esc(title)}</h2></div><button type="button" data-close aria-label="Đóng">×</button></div><p class="ma-confirm-message">${esc(message)}</p><div class="ma-modal-actions"><button type="button" data-close>Hủy</button><button type="button" class="${danger ? "ma-danger-button" : "ma-primary"}" data-confirm>${esc(confirmLabel)}</button></div></div>`;
      root.append(modal);
      let settled = false;
      const done = (value) => {
        if (settled) return;
        settled = true;
        document.removeEventListener("keydown", onKey);
        modal.remove();
        resolve(value);
      };
      const onKey = (event) => { if (event.key === "Escape") done(false); };
      document.addEventListener("keydown", onKey);
      modal.addEventListener("click", (event) => { if (event.target === modal) done(false); });
      modal.querySelectorAll("[data-close]").forEach((button) => button.addEventListener("click", () => done(false)));
      modal.querySelector("[data-confirm]").addEventListener("click", () => done(true));
      modal.querySelector("[data-confirm]").focus();
    });
  }
  function promptDialog({ title, label, initial = "", confirmLabel = "Lưu lại", placeholder = "" }) {
    return new Promise((resolve) => {
      const modal = document.createElement("div");
      modal.className = "ma-modal";
      modal.innerHTML = `<div class="ma-modal-card ma-confirm-card" role="dialog" aria-modal="true"><div class="ma-modal-head"><div><small>NHẬP LIỆU</small><h2>${esc(title)}</h2></div></div><label class="ma-prompt-label">${esc(label)}<input type="text" maxlength="120" value="${esc(initial)}" placeholder="${esc(placeholder)}"></label><div class="ma-modal-actions"><button type="button" data-close>Hủy</button><button type="button" class="ma-primary" data-confirm>${esc(confirmLabel)}</button></div></div>`;
      root.append(modal);
      const input = modal.querySelector("input");
      let settled = false;
      const done = (value) => {
        if (settled) return;
        settled = true;
        document.removeEventListener("keydown", onKey);
        modal.remove();
        resolve(value);
      };
      const onKey = (event) => {
        if (event.key === "Escape") done(null);
        if (event.key === "Enter") { event.preventDefault(); done(input.value); }
      };
      document.addEventListener("keydown", onKey);
      modal.addEventListener("click", (event) => { if (event.target === modal) done(null); });
      modal.querySelectorAll("[data-close]").forEach((button) => button.addEventListener("click", () => done(null)));
      modal.querySelector("[data-confirm]").addEventListener("click", () => done(input.value));
      input.focus();
      input.select();
    });
  }

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
  const revealStageLabel = (stage) => ({ IDLE: "Đang chờ", ROLLING: "Spotlight đang tìm kiếm", SIXTH: "Đã công bố hạng 6", FIFTH: "Đã công bố hạng 5", FOURTH: "Đã công bố hạng 4", THIRD: "Đã công bố hạng 3", SECOND: "Đã công bố hạng 2", FINAL: "Đã công bố quán quân" }[stage] || "Đang chờ");
    const totalRevealStageLabel = (stage) => ({ IDLE: "Đang chờ", ROLLING: "Đang tung điểm", RANK65: "Đã lộ diện hạng 6-5", TEASE43: "Đang nhá hàng top 4", RANK43: "Đã lộ diện hạng 4-3", RANK12: "Top 2 đã bước lên", TWIST: "3 đội đang cùng tung điểm", REVEAL3: "Đã lộ diện hạng ba", FINAL: "Đã công bố quán quân" }[stage] || "Đang chờ");
  function sidebar() {
    const role = window.MACVotingAdmin.role;
    const nav = role === "guide"
      ? `<button data-tab="checkin" class="${tab === "checkin" ? "active" : ""}">▣ Check-in</button><button data-tab="mybus" class="${tab === "mybus" ? "active" : ""}">▧ Xe của tôi</button>`
      : `<button data-tab="overview" class="${tab === "overview" ? "active" : ""}">◉ Tổng quan</button><button data-tab="checkin" class="${tab === "checkin" ? "active" : ""}">▣ Check-in</button>${role !== "guide" ? `<button data-tab="bus" class="${tab === "bus" ? "active" : ""}">▤ Phân xe</button>` : ""}<button data-tab="games" class="${tab === "games" ? "active" : ""}">◈ Trò chơi lớn</button><button data-tab="art" class="${tab === "art" ? "active" : ""}">♪ Văn nghệ</button><button data-tab="thidua" class="${tab === "thidua" ? "active" : ""}">★ Thi đua</button><button data-tab="data" class="${tab === "data" ? "active" : ""}">▦ Nhân sự & QR</button>`;
    const links = role === "guide"
      ? `<a href="${esc(window.MACVotingAdmin.checkinUrl)}">↗ Quét QR check-in</a>`
      : `<a href="${esc(window.MACVotingAdmin.checkinUrl)}">↗ Quét QR check-in</a>${canWrite() ? `<a href="${esc(window.MACVotingAdmin.resultsUrl)}" target="_blank" rel="noopener">↗ Màn hình kết quả</a><a href="${esc(window.MACVotingAdmin.voteUrl)}" target="_blank" rel="noopener">↗ Mở trang vote</a><button type="button" id="ma-seed-demo" class="ma-side-secret" title="Chỉ super admin · diễn tập màn hình công bố">⚓ Áp dữ liệu demo</button>` : ""}`;
    return `<aside class="ma-side"><div class="ma-brand"><img src="${esc(window.MACVotingAdmin.logo)}"><div><strong>Company Trip</strong>${role === "guide" ? `<small>HDV · xe của tôi</small>` : canWrite() ? "" : `<small>Admin · chỉ xem</small>`}</div></div><nav>${nav}</nav><div class="ma-side-links">${links}</div><a class="ma-side-logout" href="${esc(window.MACVotingAdmin.logoutUrl)}">Đăng xuất</a></aside>`;
  }
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
    const openCheckpoint = (data.checkpoints || []).find((item) => item.status === "OPEN") || null;
    const scanner = `<section class="ma-panel ma-scanner-cta"><header><div><small>CHECK-IN</small><h2>Quét QR check-in</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Mở camera để quét mã QR và ghi nhận check-in cho team.</p></div><a class="ma-primary" href="${esc(window.MACVotingAdmin.checkinUrl)}">Mở trang quét</a></header></section>`;
    const checkpointCards = (data.checkpoints || []).map((item) => `<article class="ma-check-card ${item.status.toLowerCase()}"><small>TRẠM ${item.id} · ${checkpointStatus(item.status)}</small><h2>${esc(item.name)}</h2><p>${esc(item.description || "")}</p><div class="ma-check-actions"><span class="ma-check-fixed">Tối đa 150đ/trạm</span>${canWrite() ? (item.status === "OPEN" ? `<button type="button" class="danger" data-checkpoint="${item.id}" data-op="close">Đóng & chốt</button>` : `${item.status === "DRAFT" ? `<button type="button" class="ma-primary" data-checkpoint="${item.id}" data-op="open">Mở trạm</button>` : `<button type="button" data-checkpoint="${item.id}" data-op="reopen">Mở lại</button>`}<label class="ma-duration"><span>tự đóng sau</span><input type="number" min="1" max="120" value="15" data-checkpoint-duration="${item.id}"><span>phút</span></label>`) : ""}</div></article>`).join("");
    const progressCheckpoints = boards.map((board) => board.checkpoint);
    const progressTeams = boards[0]?.teams || [];
    const progress = `<section class="ma-panel"><header><div><small>TIẾN ĐỘ</small><h2>Điểm theo tỷ lệ có mặt</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Mỗi team có cửa sổ bằng đúng số phút của trạm kể từ lượt quét đầu tiên; hết giờ cửa sổ khóa.</p></div>${canWrite() ? `<a class="ma-primary" href="${esc(window.MACVotingAdmin.checkinExportUrl)}">↓ Xuất CSV check-in</a>` : ""}</header><div class="ma-board-table"><table><thead><tr><th>Đội</th>${progressCheckpoints.map((item) => `<th>Trạm ${item.id}<small>${esc(item.name)}</small></th>`).join("")}</tr></thead><tbody>${progressTeams.map((team) => `<tr><td><strong>#${team.teamNumber} ${esc(team.teamName)}</strong></td>${boards.map((board) => { const cell = board.teams.find((row) => String(row.teamId) === String(team.teamId)); return `<td class="ma-progress-cell">${cell ? `<strong>${cell.checkedIn}/${cell.eligible}</strong><small>${cell.temporaryPoints || 0}đ</small>` : "—"}</td>`; }).join("")}</tr>`).join("") || `<tr><td colspan="${progressCheckpoints.length + 1}">Chưa có trạm nào.</td></tr>`}</tbody></table></div></section>`;
    const guides = data.buses?.guides || [];
    const accountRows = [
      ...staff.map((item) => `<tr><td><div class="ma-staff-cell"><strong>${esc(item.name)}</strong><small>${esc(item.email)}</small></div></td><td>${item.isAdmin ? "Super Admin" : "BTC / Hoa tiêu"}</td></tr>`),
      ...guides.map((item) => `<tr><td><div class="ma-staff-cell"><strong>${esc(item.name)}</strong><small>${esc(item.login)}</small></div></td><td>HDV Xe ${item.busId}</td></tr>`),
    ].join("");
    const staffPanel = canWrite() ? `<section class="ma-panel"><header><div><small>SCANNER</small><h2>Tài khoản được phép quét QR</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Mọi tài khoản dưới đây quét được toàn bộ 6 team — không còn giới hạn theo team. Tài khoản HDV tạo trong tab Phân xe.</p></div></header><div class="ma-board-table ma-no-sticky"><table><thead><tr><th>Tài khoản</th><th>Loại</th></tr></thead><tbody>${accountRows || `<tr><td colspan="2">Chưa có tài khoản.</td></tr>`}</tbody></table></div></section>` : "";
    const openExemptions = openCheckpoint ? (data.exemptions?.[openCheckpoint.id] || []) : [];
    const exemptCandidates = (data.voters || []).filter((row) => row.status === "ACTIVE" && !openExemptions.some((item) => String(item.voterId) === String(row.id)));
    const normalizedExemptQuery = normalizeHeader(exemptQuery);
    const visibleCandidates = normalizedExemptQuery ? exemptCandidates.filter((row) => normalizeHeader(`${row.full_name} ${row.email || ""} ${row.team_name || ""}`).includes(normalizedExemptQuery)) : exemptCandidates;
    const exemptionPanel = canWrite() && openCheckpoint ? `<section class="ma-panel ma-exemptions"><header><div><small>MIỄN CHECK-IN</small><h2>Trạm ${openCheckpoint.id} · ${esc(openCheckpoint.name)}</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Người được miễn không tính vào mẫu số và biến khỏi danh sách "CÒN THIẾU" của các team.</p></div></header><form class="ma-exempt-form" id="ma-exempt-form"><label for="ma-exempt-search">Tìm nhanh<input id="ma-exempt-search" type="search" placeholder="Gõ tên để lọc trong ${exemptCandidates.length} người…" value="${esc(exemptQuery)}" autocomplete="off"></label><label for="ma-exempt-voter">Chọn người<select id="ma-exempt-voter">${visibleCandidates.map((row) => `<option value="${row.id}">${esc(row.full_name)} · #${row.team_no} ${esc(row.team_name)}</option>`).join("") || `<option value="">${normalizedExemptQuery ? "Không tìm thấy ai khớp" : "Không còn ai để miễn"}</option>`}</select></label><label for="ma-exempt-reason">Lý do<input id="ma-exempt-reason" type="text" maxlength="500" placeholder="Ví dụ: được BTC duyệt cho đến muộn" required></label><button type="submit" class="ma-primary" ${exemptCandidates.length ? "" : "disabled"}>Miễn check-in</button></form><div class="ma-board-table"><table><thead><tr><th>Người được miễn</th><th>Lý do</th><th></th></tr></thead><tbody>${openExemptions.map((item) => `<tr><td><strong>${esc(item.fullName)}</strong></td><td>${esc(item.reason || "")}</td><td><button type="button" data-exempt-clear="${item.voterId}" data-exempt-name="${esc(item.fullName)}">Bỏ miễn</button></td></tr>`).join("") || `<tr><td colspan="3">Chưa có ai được miễn ở trạm này.</td></tr>`}</tbody></table></div></section>` : "";
    return `<header class="ma-top"><div><small>CHECK-IN</small><h1>4 trạm Company Trip</h1></div>${topActions()}</header>${scanner}<div class="ma-check-grid">${checkpointCards}</div>${progress}${exemptionPanel}${staffPanel}`;
  }
  function busView() {
    const state = data.buses || { buses: [], unassigned: [], guides: [] };
    const buses = state.buses || [];
    if (!busManifestId && buses.length) busManifestId = (buses.find((b) => b.status === "BOARDING") || buses[0]).id;
    const boarding = buses.find((b) => b.status === "BOARDING") || null;
    const busStatusLabel = (s) => s === "BOARDING" ? "ĐANG XẾP" : s === "CLOSED" ? "ĐÃ CHỐT" : "CHỜ";
    const control = `<section class="ma-panel ma-bus-control"><header><div><small>PHÂN XE · TRẠM 1</small><h2>${boarding ? `Đang xếp ${esc(boarding.name)}` : "Chưa mở xe nào"}</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Mở/đóng xe chỉ diễn ra trong đợt phân xe Trạm 1. QR quét vào tự rơi vào xe đang mở — server quyết định, không theo browser.</p></div><span class="ma-reveal-status ${boarding ? "rolling" : "idle"}"><i></i>${state.enabled ? (boarding ? "ĐANG PHÂN XE" : "CHỜ MỞ XE 1") : "CHƯA BẬT / ĐÃ HOÀN TẤT"}</span></header>${canWrite() ? `<div class="ma-reveal-actions">${boarding ? `<button type="button" class="ma-reveal-start" data-bus-advance="${boarding.id}"><span>${boarding.sortOrder}</span><strong>Chốt ${esc(boarding.name)} → mở xe ${boarding.sortOrder + 1}</strong><small>${boarding.employees} NV QR · ${boarding.staff} BTC/Hoa tiêu</small></button>` : `<button type="button" class="ma-reveal-start" id="ma-bus-open" ${state.enabled ? "" : "disabled"}><span>01</span><strong>Mở Xe 1</strong><small>Bắt đầu nhận người từ QR Trạm 1</small></button>`}</div><div class="ma-bus-extra"><button type="button" id="ma-bus-reset" class="ma-bus-ghost-danger">⟲ Reset phân xe</button><small>Xóa toàn bộ phân xe + lượt điểm danh, đưa 5 xe về CHỜ. Chỉ Super Admin.</small></div>` : `<p class="ma-bus-note">Chỉ Super Admin mới mở/chốt xe. BTC · Hoa tiêu xem tiến độ và tự pick mình vào xe ở manifest bên dưới.</p>`}<div class="ma-board-table ma-no-sticky"><table><thead><tr><th>Xe</th><th>NV QR</th><th>BTC</th><th>Tổng</th><th>Trạng thái</th></tr></thead><tbody>${buses.map((b) => `<tr class="${b.id === busManifestId ? "is-selected" : ""}"><td><button type="button" data-bus-manifest="${b.id}"><strong>${esc(b.name)}</strong></button></td><td>${b.employees}</td><td>${b.staff}</td><td><strong>${b.total}</strong></td><td>${busStatusLabel(b.status)}</td></tr>`).join("") || `<tr><td colspan="5">Chưa có dữ liệu xe.</td></tr>`}</tbody></table></div></section>`;
    const manifestBus = buses.find((b) => b.id === busManifestId) || null;
    const busStaff = !!window.MACVotingAdmin.busStaff;
    const roll = manifestBus?.rollcall || { sequence: 0, presentCount: 0, marks: {}, history: [] };
    const staffPool = (data.voters || []).filter((v) => Number(v.team_no) === 7 && v.status === "ACTIVE");
    const assignedIds = new Set();
    buses.forEach((b) => (b.manifest || []).forEach((m) => { if (m.voterId) assignedIds.add(Number(m.voterId)); }));
    const staffPick = staffPool.filter((v) => !assignedIds.has(Number(v.id)));
    const pickHtml = staffPick.map((v) => `<label class="ma-pick-item"><input type="checkbox" name="staffIds" value="${v.id}"> ${esc(v.full_name)}</label>`).join("") || `<p class="ma-pick-empty">Không còn BTC/Hoa tiêu chờ thêm.</p>`;
    const anyQueryNorm = busAnyQuery.trim().toLowerCase();
    const anyPool = canWrite() ? (data.voters || []).filter((v) => v.status === "ACTIVE" && !assignedIds.has(Number(v.id)) && (!anyQueryNorm || `${v.full_name} ${v.team_name || ""}`.toLowerCase().includes(anyQueryNorm))).slice(0, 40) : [];
    const q = busQuery.trim().toLowerCase();
    const members = ((manifestBus?.manifest) || []).filter((m) => !q || `${m.name} ${m.teamName || ""}`.toLowerCase().includes(q));
    const memberRows = members.map((m) => `<tr><td><strong>${esc(m.name)}</strong></td><td>${m.teamNo ? `#${m.teamNo} ${esc(m.teamName)}` : "—"}</td><td>${m.memberType === "EMPLOYEE" ? "NV QR" : m.memberType === "STAFF" ? "BTC/Hoa tiêu" : "Thủ công"}</td><td class="ma-bus-actions">${busStaff ? `<button type="button" class="ma-roll-mini ${roll.marks[m.id] ? "is-present" : ""}" data-rollcall-toggle="${m.id}" data-bus="${busManifestId}" data-present="${roll.marks[m.id] ? "" : "1"}" aria-pressed="${!!roll.marks[m.id]}" title="Điểm danh">${roll.marks[m.id] ? "✓" : "○"}</button>` : ""}${canWrite() ? `<select data-bus-move-member="${m.id}" aria-label="Chuyển xe ${esc(m.name)}"><option value="">Chuyển xe…</option>${buses.filter((b) => b.id !== busManifestId).map((b) => `<option value="${b.id}">${esc(b.name)}</option>`).join("")}</select><button type="button" class="danger ma-bus-remove" data-bus-remove="${m.id}" data-bus-remove-name="${esc(m.name)}" aria-label="Xóa ${esc(m.name)} khỏi xe">×</button>` : ""}</td></tr>`).join("") || `<tr><td colspan="4" class="ma-cell-empty">${q ? "Không ai khớp tìm kiếm." : "Xe chưa có ai."}</td></tr>`;
    const manifest = manifestBus ? `<section class="ma-panel ma-bus-manifest"><div class="ma-bus-tabs" role="tablist" aria-label="Chọn xe">${buses.map((b) => `<button type="button" role="tab" data-bus-manifest="${b.id}" class="${b.id === busManifestId ? "active" : ""}" aria-selected="${b.id === busManifestId}"><strong>${esc(b.name)}</strong><small>${b.total}</small></button>`).join("")}</div><header><div><small>MANIFEST · LƯỢT ${roll.sequence || "—"}</small><h2>${esc(manifestBus.name)} · ${roll.presentCount}/${manifestBus.total} có mặt</h2></div><div class="ma-bus-tools"><input id="ma-bus-query" type="search" placeholder="Tìm tên / team" value="${esc(busQuery)}"><button type="button" id="ma-bus-csv" class="ma-bus-ghost">↓ Xuất CSV</button>${busStaff ? `<button type="button" class="ma-primary" id="ma-rollcall-new" data-bus="${busManifestId}">ĐIỂM DANH LƯỢT MỚI</button>` : ""}</div></header><div class="ma-board-table ma-no-sticky"><table><thead><tr><th>Họ tên</th><th>Team</th><th>Loại</th><th></th></tr></thead><tbody>${memberRows}</tbody></table></div>${busStaff && roll.history.length ? `<div class="ma-bus-history"><small>LỊCH SỬ LƯỢT ĐIỂM DANH</small><ul>${roll.history.map((h) => `<li>Lượt ${h.sequence} · ${esc(h.createdAt)} · ${h.presentCount}/${manifestBus.total}</li>`).join("")}</ul></div>` : ""}${busStaff ? `<form class="ma-bus-add" id="ma-bus-add-form"><div class="ma-bus-add-head"><span>Thêm BTC/Hoa tiêu vào ${esc(manifestBus.name)}</span><small>Chọn nhiều người cùng lúc rồi bấm thêm — người đã ở xe khác sẽ không xuất hiện</small></div><div class="ma-bus-pick">${pickHtml}</div>${canWrite() ? `<div class="ma-bus-add-head"><span>Thêm nhân sự bất kỳ</span><small>Tìm tên, tick rồi thêm; mỗi người chỉ ở một xe</small></div><input id="ma-bus-any-query" type="search" placeholder="Tìm tên để thêm thủ công…" value="${esc(busAnyQuery)}"><div class="ma-bus-pick">${anyPool.map((v) => `<label class="ma-pick-item"><input type="checkbox" name="anyIds" value="${v.id}"> ${esc(v.full_name)} <small>#${v.team_no}</small></label>`).join("") || `<p class="ma-pick-empty">Gõ tên để tìm người chưa ở xe nào.</p>`}</div><input id="ma-bus-manual" type="text" placeholder="Hoặc gõ tên người ngoài (tùy chọn)">` : ""}<button type="submit" class="ma-primary">+ Thêm vào xe</button></form>` : ""}</section>` : "";
    const unassignedRows = (state.unassigned || []).map((u) => `<tr><td><strong>${esc(u.name)}</strong></td><td>#${u.teamNo} ${esc(u.teamName)}</td><td><select data-unassigned-assign="${u.voterId}"><option value="">Gán vào xe…</option>${buses.map((b) => `<option value="${b.id}">${esc(b.name)}</option>`).join("")}</select></td></tr>`).join("") || `<tr><td colspan="3" class="ma-cell-empty">Không còn ai chờ phân xe.</td></tr>`;
    const unassigned = `<section class="ma-panel"><header><div><small>CHƯA PHÂN XE · ${(state.unassigned || []).length}</small><h2>Check-in lúc chưa có xe mở / đến trễ</h2></div></header><div class="ma-board-table ma-no-sticky"><table><thead><tr><th>Họ tên</th><th>Team</th><th></th></tr></thead><tbody>${unassignedRows}</tbody></table></div></section>`;
    const guideRows = (state.guides || []).map((g) => `<tr><td><strong>${esc(g.name)}</strong></td><td>${esc(g.login)}</td><td>Xe ${g.busId}</td></tr>`).join("") || `<tr><td colspan="3">Chưa có tài khoản HDV.</td></tr>`;
    const guides = `<section class="ma-panel"><header><div><small>HDV VIETRAVEL</small><h2>5 tài khoản điểm danh trên xe</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">HDV chỉ thấy Check-in + Xe của tôi; quét QR được mọi team nhưng chỉ điểm danh xe mình.</p></div></header><form class="ma-guide-form" id="ma-guide-form"><label>Tên hiển thị<input name="name" type="text" placeholder="VD: Anh Tuấn Vietravel" required></label><label>Username<input name="login" type="text" placeholder="hdv.xe1" required autocapitalize="none"></label><label>Mật khẩu<input name="password" type="text" placeholder="Để trống = Mac-123"></label><label>Xe phụ trách<select name="busId">${[1, 2, 3, 4, 5].map((n) => `<option value="${n}">Xe ${n}</option>`).join("")}</select></label><button type="submit" class="ma-primary">Lưu tài khoản HDV</button></form><div class="ma-board-table ma-no-sticky"><table><thead><tr><th>HDV</th><th>Đăng nhập</th><th>Xe</th></tr></thead><tbody>${guideRows}</tbody></table></div></section>`;
    return `<header class="ma-top"><div><small>PHÂN XE</small><h1>Điều phối Xe 1–5 · Trạm 1</h1></div>${topActions()}</header>${control}${manifest}${canWrite() ? unassigned + guides : ""}`;
  }
  function myBusView() {
    const bus = data.myBus;
    if (!bus) return `<header class="ma-top"><div><small>XE CỦA TÔI</small><h1>Chưa được gán xe</h1></div></header><section class="ma-panel"><p>Nhờ Super Admin gán xe phụ trách cho tài khoản HDV này.</p></section>`;
    const q = busSearch.trim().toLowerCase();
    const filtered = (bus.members || []).filter((m) => !q || m.name.toLowerCase().includes(q));
    const visible = busFilter === "missing" ? filtered.filter((m) => !m.present) : filtered;
    const employees = visible.filter((m) => m.memberType === "EMPLOYEE");
    const others = visible.filter((m) => m.memberType !== "EMPLOYEE");
    const row = (m) => `<li><button type="button" class="ma-roll-row ${m.present ? "is-present" : ""}" data-rollcall-toggle="${m.id}" data-present="${m.present ? "" : "1"}" aria-pressed="${m.present}"><span>${m.present ? "✓" : "○"} ${esc(m.name)}</span><small>${m.teamNo ? `#${m.teamNo}` : m.memberType === "STAFF" ? "BTC" : ""}</small></button></li>`;
    return `<header class="ma-top"><div><small>XE CỦA TÔI</small><h1>${esc(bus.busName)} · Điểm danh trên xe</h1></div>${topActions()}</header><section class="ma-panel ma-bus-mine"><header><div><small>LƯỢT ${bus.currentSequence || 1}</small><h2>${bus.presentCount} / ${bus.totalCount} đã có mặt</h2></div><button type="button" class="ma-primary" id="ma-rollcall-new">ĐIỂM DANH LƯỢT MỚI</button></header><div class="ma-bus-tools"><input id="ma-bus-search" type="search" placeholder="Tìm tên…" value="${esc(busSearch)}"><div class="ma-bus-filters"><button type="button" class="${busFilter === "all" ? "active" : ""}" data-bus-filter="all">Tất cả</button><button type="button" class="${busFilter === "missing" ? "active" : ""}" data-bus-filter="missing">Chưa có mặt</button></div></div><ul class="ma-roll-list">${employees.map(row).join("") || `<li><p style="margin:0;color:#667085">Không có ai khớp bộ lọc.</p></li>`}</ul>${others.length ? `<p class="ma-roll-divider">BTC / HOA TIÊU / THỦ CÔNG</p><ul class="ma-roll-list">${others.map(row).join("")}</ul>` : ""}<div class="ma-bus-history"><small>LỊCH SỬ LƯỢT ĐIỂM DANH</small><ul>${(bus.history || []).map((h) => `<li>Lượt ${h.sequence} · ${esc(h.createdAt)} · ${h.presentCount}/${bus.totalCount}</li>`).join("") || `<li>Chưa có lượt nào.</li>`}</ul></div></section>`;
  }
  function pointsView() {
    const board = data.totalBoard || { categories: [], teams: [], history: [] };
    const categories = board.categories || [];
    const ranked = board.teams || [];
    const teams = ranked.slice().sort((a, b) => a.teamNumber - b.teamNumber);
    const history = board.history || [];
    const games = data.games?.list || [];
    const gameBoard = data.games?.board || [];
    if (overviewTab !== "chart" && overviewTab !== "reveal" && overviewTab !== "history") overviewTab = "chart";
    const maxAbs = Math.max(1, ...ranked.map((team) => Math.abs(Number(team.total) || 0)));
    const checkinLedger = data.teamPoints || [];
    const checkpointCols = (data.checkpoints || []).slice().sort((a, b) => a.id - b.id);
    const tabs = [
      ["chart", "Tổng điểm"],
      ["reveal", "Công bố"],
      ["history", "Lịch sử"],
    ];
    const nav = `<nav class="ma-subnav" aria-label="Tổng quan">${tabs.map(([id, label]) => `<button type="button" data-overview-tab="${id}" class="${overviewTab === id ? "active" : ""}">${label}</button>`).join("")}</nav>`;
    const chartPanel = `<section class="ma-panel ma-chart-panel"><header><div><small>TỔNG ĐIỂM</small><h2>6 đội · 4 mặt trận bứt phá</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Cột gồm check-in đã chốt, xếp hạng trò chơi, văn nghệ quy đổi và thi đua.</p></div></header><div class="ma-chart" style="--ma-chart-max:${maxAbs}">${ranked.map((team) => { const total = Number(team.total) || 0; const height = Math.max(6, Math.round((Math.abs(total) / maxAbs) * 100)); return `<figure class="ma-chart-col ${total < 0 ? "is-neg" : ""}" aria-label="#${team.teamNumber} ${esc(team.teamName)}"><strong>${fmt(total)}</strong><div class="ma-chart-track" aria-hidden="true"><span style="height:${height}%"></span></div><figcaption><b>#${team.teamNumber} ${esc(team.teamName)}</b></figcaption></figure>`; }).join("")}</div></section>`;
    const scoreboard = `<section class="ma-panel"><header><div><small>BẢNG ĐIỂM</small><h2>4 mặt trận · Check-in + Trò chơi + Văn nghệ + Thi đua</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Check-in tối đa 600đ, trò chơi tối đa 150đ, văn nghệ tối đa 200đ, thi đua tối đa 50đ · 600 + 150 + 200 + 50 = 1.000đ.</p></div></header><div class="ma-board-table ma-pin-2"><table><thead><tr><th>Hạng</th><th>Team</th><th>Check-in</th><th>Trò chơi</th><th>Văn nghệ</th><th>Thi đua</th><th>Tổng</th></tr></thead><tbody>${ranked.map((team) => `<tr><td><strong>${team.rank}</strong></td><td><strong>#${team.teamNumber} ${esc(team.teamName)}</strong></td><td>${fmt(team.checkin)}</td><td>${fmt(team.games)}</td><td>${fmt(team.vote)}${team.voteAverage !== null ? `<small class="ma-cell-sub">TB ${trim1(team.voteAverage)}/150</small>` : ""}</td><td>${fmt(team.thidua)}/50<small class="ma-cell-sub">${team.thiduaCompletedRounds ?? 0} hạng mục hoàn tất</small></td><td><strong>${fmt(team.total)}</strong></td></tr>`).join("")}</tbody></table></div></section>`;
    const checkinPanel = `<section class="ma-panel"><header><div><small>CHECK-IN</small><h2>Sổ điểm 4 trạm</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Điểm đã chốt khi đóng trạm, tối đa 150đ/trạm.</p></div></header><div class="ma-board-table"><table><thead><tr><th>Team</th>${checkpointCols.map((item) => `<th>Trạm ${item.id}<small>${esc(item.name)}</small></th>`).join("")}<th>Tổng check-in</th></tr></thead><tbody>${checkinLedger.map((row) => `<tr><td><strong>#${row.teamNumber} ${esc(row.teamName)}</strong></td>${row.checkpoints.map((value) => `<td>${fmt(value)}</td>`).join("")}<td><strong>${fmt(row.total)}</strong></td></tr>`).join("") || `<tr><td colspan="${checkpointCols.length + 2}">Chưa có điểm check-in đã chốt.</td></tr>`}</tbody></table></div></section>`;
    const gamesSummary = `<section class="ma-panel"><header><div><small>TRÒ CHƠI LỚN</small><h2>Điểm 3 game</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Thang hạng 1 – 6: 50 · 40 · 30 · 20 · 10 · 0đ. Xếp hạng trong tab Trò chơi lớn.</p></div></header>${gamesMatrix(games, gameBoard, teams)}</section>`;
    const votePanel = `<section class="ma-panel"><header><div><small>VĂN NGHỆ</small><h2>Kết quả bình chọn</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Điểm = ROUND(TB phiếu ÷ 150 × 200), tối đa 200đ, cập nhật trực tiếp khi có phiếu.</p></div></header><div class="ma-board-table"><table><thead><tr><th>Team</th><th>TB phiếu</th><th>Số phiếu hợp lệ</th><th>Điểm văn nghệ</th></tr></thead><tbody>${ranked.map((team) => `<tr><td><strong>#${team.teamNumber} ${esc(team.teamName)}</strong></td><td>${team.voteAverage === null ? "Chưa vote" : `${trim1(team.voteAverage)}/150`}</td><td>${team.voteBallots || 0}</td><td><strong>${fmt(team.vote)}</strong></td></tr>`).join("")}</tbody></table></div></section>`;
    const thiduaPanel = `<section class="ma-panel"><header><div><small>THI ĐUA</small><h2>Điểm thi đua · tối đa 50đ</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Điểm chính thức là trung bình các hạng mục đã hoàn tất. Chấm trong tab Thi đua.</p></div></header>${thiduaMatrix(categories, teams)}</section>`;
    const chart = `<div class="ma-overview-stack">${chartPanel}${scoreboard}${checkinPanel}${gamesSummary}${votePanel}${thiduaPanel}</div>`;
    const historyRows = history.length
      ? history.map((item) => `<tr class="is-${item.kind}"><td>${esc(item.at)}</td><td>${esc(item.actor)}</td><td>${item.teamNumber ? `#${item.teamNumber} ` : ""}${esc(item.teamName)}</td><td>${esc(item.source)}${item.note ? ` · ${esc(item.note)}` : ""}</td><td><strong>${item.kind === "clear" ? "Xóa điểm" : item.source === "Thi đua" && item.rank ? `Hạng ${item.rank} · ${item.points}đ` : fmt(item.points)}</strong></td></tr>`).join("")
      : `<tr><td colspan="5">Chưa có lịch sử cộng điểm.</td></tr>`;
    const historyPanel = `<section class="ma-panel"><header><div><small>AUDIT</small><h2>Ai cộng điểm · lúc nào</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Gồm thi đua, xếp hạng trò chơi và điểm check-in khi đóng trạm.</p></div></header><div class="ma-table ma-history-table"><table><thead><tr><th>Thời gian</th><th>Người cộng</th><th>Team</th><th>Nguồn</th><th>Điểm</th></tr></thead><tbody>${historyRows}</tbody></table></div></section>`;
    const body = overviewTab === "history" ? historyPanel : overviewTab === "reveal" ? totalRevealView() : chart;
    return `<header class="ma-top"><div><small>TỔNG QUAN</small><h1>Tổng điểm 6 team</h1></div>${topActions()}</header>${nav}${body}`;
  }
  function gamesView() {
    const games = data.games?.list || [];
    const gameBoard = data.games?.board || [];
    const teams = (data.totalBoard?.teams || []).slice().sort((a, b) => a.teamNumber - b.teamNumber);
    const ladderChips = `<div class="ma-ladder">${ladder.map((value, index) => `<span class="ma-ladder-chip rank-${index + 1}">Hạng ${index + 1} · ${value}đ</span>`).join("")}</div>`;
    const intro = `<section class="ma-panel"><header><div><small>THANG ĐIỂM</small><h2>Hạng 1 – 6 · từ 50đ về 0đ</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Chọn hạng từng team ở mỗi game; điểm ghi ngay vào sổ tổng. Chưa xếp hoặc hạng 6 = 0đ.</p></div></header>${ladderChips}</section>`;
    const totals = `<section class="ma-panel"><header><div><small>TỔNG GAME</small><h2>Ma trận điểm 3 game</h2></div></header>${gamesMatrix(games, gameBoard, teams)}</section>`;
    const cards = `<div class="ma-game-grid">${games.map((game, index) => `<article class="ma-panel ma-game-card"><header><div><small>GAME ${index + 1}</small><h2>${esc(game.name)}</h2></div><span class="ma-game-max">tối đa 50đ</span></header><div class="ma-game-rows">${teams.map((team) => { const gameRow = gameBoard.find((row) => String(row.teamId) === String(team.teamId)); const cell = (gameRow?.cells || []).find((entry) => entry.gameId === game.id) || { rank: 0, points: 0 }; return `<div class="ma-game-row"><span class="ma-game-team">#${team.teamNumber} <b>${esc(team.teamName)}</b></span><select data-game-rank data-game="${game.id}" data-team="${team.teamId}" ${canWrite() ? "" : "disabled"}>${[0, 1, 2, 3, 4, 5, 6].map((rank) => `<option value="${rank}" ${cell.rank === rank ? "selected" : ""}>${rank === 0 ? "Chưa xếp" : `Hạng ${rank} · ${ladder[rank - 1]}đ`}</option>`).join("")}</select><b class="ma-game-points ${cell.rank >= 1 ? "rank-" + cell.rank : ""}">${cell.points}đ</b></div>`; }).join("")}</div></article>`).join("")}</div>`;
    return `<header class="ma-top"><div><small>TRÒ CHƠI LỚN</small><h1>Xếp hạng 3 game</h1></div>${topActions()}</header>${intro}${totals}${cards}`;
  }
  function thiduaView() {
    const board = data.totalBoard || { categories: [], teams: [] };
    const categories = board.categories || [];
    const teams = (board.teams || []).slice().sort((a, b) => a.teamNumber - b.teamNumber);
    if (!categories.some((category) => String(category.id) === String(awardCategoryId))) {
      awardCategoryId = categories[0] ? String(categories[0].id) : "";
    }
    if (!teams.some((team) => String(team.teamId) === String(awardTeamId))) {
      awardTeamId = "";
    }
    const selectedCategory = categories.find((category) => String(category.id) === String(awardCategoryId));
    const catStatus = categoryStatus(selectedCategory);
    const selectedTeam = teams.find((team) => String(team.teamId) === String(awardTeamId));
    const selectedCell = selectedTeam ? (selectedTeam.cells || []).find((entry) => String(entry.categoryId) === String(awardCategoryId)) : null;
    const currentPoints = selectedCell?.hasScore ? Number(selectedCell.points) : null;
    const canScore = Boolean(awardCategoryId && awardTeamId);
    const info = `<section class="ma-panel ma-thidua-summary"><header><div><small>THI ĐUA · 5%</small><h2>Tối đa 50 điểm</h2></div><span class="ma-thidua-formula">Điểm chính thức = ROUND(tổng hạng mục được tính ÷ số hạng mục được tính)</span></header><p class="ma-thidua-summary-copy">Mỗi hạng mục xếp hạng 50 · 40 · 30 · 20 · 10 · 0. Khi có ít nhất một team được chọn điểm, hạng mục được tính ngay; team chưa chọn được xem là không tham gia và nhận 0đ.</p></section>`;
    const award = `${info}<section class="ma-panel ma-award"><div class="ma-award-step"><div class="ma-award-step-head"><h2>1. Hạng mục thi đua</h2><div class="ma-award-cat-actions"><button type="button" id="ma-cat-add" aria-label="Thêm hạng mục thi đua">+</button><button type="button" id="ma-cat-edit" aria-label="Sửa hạng mục thi đua" ${awardCategoryId ? "" : "disabled"}>✎</button><button type="button" id="ma-cat-delete" class="is-danger" aria-label="Xóa hạng mục thi đua" ${awardCategoryId ? "" : "disabled"}>×</button></div></div>${categories.length ? `<div class="ma-award-cat-row"><select id="ma-award-category">${categories.map((category) => `<option value="${category.id}" ${String(category.id) === String(awardCategoryId) ? "selected" : ""}>${esc(category.name)}</option>`).join("")}</select>${selectedCategory ? `<span class="ma-thidua-status ${catStatus.cls} ma-thidua-cat-chip">${catStatus.label}${selectedCategory.isComplete ? " · Được tính vào điểm Thi đua" : " · Chưa tính"}</span>` : ""}</div>` : `<p class="ma-cat-empty">Chưa có hạng mục thi đua. Bấm + để thêm.</p>`}</div><div class="ma-award-step"><h2>2. Team</h2><div class="ma-award-teams">${teams.map((team) => { const cell = (team.cells || []).find((entry) => String(entry.categoryId) === String(awardCategoryId)); const selected = String(team.teamId) === String(awardTeamId); return `<button type="button" data-award-team="${team.teamId}" class="${selected ? "is-selected" : ""}" ${awardCategoryId ? "" : "disabled"} aria-pressed="${selected}"><strong>${esc(team.teamName)}</strong><small class="${cell?.hasScore ? "" : "ma-thidua-unscored"}">${cell?.hasScore ? `Hạng ${rankOfPoints(cell.points)} · ${cell.points}đ` : "Không tham gia · 0đ"}</small></button>`; }).join("")}</div></div><div class="ma-award-step"><h2>3. Điểm theo hạng</h2><p style="margin:0 0 8px;color:#667085;font-size:13px">Chọn hạng cho team tham gia; bấm lại ô đang chọn để đưa team về Không tham gia · 0đ.</p><div class="ma-award-presets">${ladder.map((value, index) => `<button type="button" data-award-points="${value}" class="${canScore && currentPoints === value ? "is-selected" : ""} ${value === 0 ? "is-zero" : ""}" ${canScore ? "" : "disabled"} aria-pressed="${canScore && currentPoints === value}">Hạng ${index + 1} · ${value}đ</button>`).join("")}</div></div></section>`;
    const read = `${info}<section class="ma-panel"><header><div><small>THI ĐUA</small><h2>Điểm từng đội</h2><p style="margin:6px 0 0;color:#667085;font-size:13px">Điểm chính thức là trung bình các hạng mục có đội tham gia, tối đa 50đ. Team không tham gia nhận 0đ. Chỉ super admin mới chấm được.</p></div></header>${thiduaMatrix(categories, teams)}</section>`;
    return `<header class="ma-top"><div><small>THI ĐUA</small><h1>Chấm điểm thi đua</h1></div>${topActions()}</header>${canWrite() ? award : read}`;
  }
  function live() {
    const lockedTeams = new Set(data.rounds.filter((round) => round.status !== "DRAFT").flatMap((round) => round.slots.map((slot) => String(slot.team_id))));
    return `<div class="ma-art-live">${votingGate()}<div class="ma-stats"><article><span>Người được vote</span><strong>${data.stats.activeVoters}</strong></article><article><span>Phiếu hợp lệ</span><strong>${data.stats.validBallots}</strong></article><article><span>Phiếu đã hủy</span><strong>${data.stats.revokedBallots}</strong></article></div><div class="ma-grid"><section class="ma-panel"><header><div><small>LỊCH BIỂU DIỄN</small><h2>3 lượt · 6 tiết mục</h2></div><b>LIVE CONTROL</b></header><div class="ma-rounds">${data.rounds.map((round) => `<article class="ma-round ${round.status.toLowerCase()}"><div class="ma-round-head"><span><small>LƯỢT ${round.id}</small><strong>${statusLabel(round.status)}</strong></span><div class="ma-round-controls">${canWrite() ? (round.status === "DRAFT" ? `<label class="ma-duration"><span>tự đóng sau</span><input type="number" min="1" max="120" value="5" data-round-duration="${round.id}"><span>phút</span></label><button data-round="${round.id}" data-op="open">▶ Mở vote</button>` : round.status === "OPEN" ? `<button class="danger" data-round="${round.id}" data-op="close">■ Đóng lượt</button>` : `<label class="ma-duration"><span>tự đóng sau</span><input type="number" min="1" max="120" value="5" data-round-duration="${round.id}"><span>phút</span></label><button class="reopen" data-round="${round.id}" data-op="reopen">↻ Mở lại</button>`) : ""}</div></div><div class="ma-slots">${round.slots.map((slot) => `<div><small>TIẾT MỤC ${slot.position}</small><strong>#${slot.team_no} ${esc(slot.team_name)}</strong>${round.status === "DRAFT" && canWrite() ? `<select data-slot="${slot.id}">${data.performances.map((performance) => `<option value="${performance.id}" ${String(performance.id) === String(slot.performance_id) ? "selected" : ""} ${lockedTeams.has(String(performance.team_id)) ? "disabled" : ""}>#${performance.team_no} ${esc(performance.team_name)}</option>`).join("")}</select>` : `<em>⌕ ${round.status === "DRAFT" ? "Lịch biểu diễn" : "Đã khóa lịch"}</em>`}</div>`).join("")}</div></article>`).join("")}</div></section><section class="ma-panel ma-results"><header><div><small>ĐIỂM TRỰC TIẾP</small><h2>Kết quả tạm tính</h2></div></header>${data.results.map((result, index) => `<div class="ma-result"><i>${result.average_score === null ? "—" : index + 1}</i><span><strong>#${result.team_no} ${esc(result.team_name)}</strong><small>${result.voter_count} phiếu hợp lệ</small></span><b>${result.average_score === null ? "Chưa vote" : trim1(result.average_score)}</b></div>`).join("")}<p>Điểm đầy đủ chỉ hiển thị cho admin cho tới tín hiệu công bố cuối.</p></section></div></div>`;
  }
  function revealView() {
    const stage = data.reveal?.stage || "IDLE";
    const lightTheme = !!data.artLightTheme;
    const revealPlan = data.artRevealPlan || {};
    const nextStage = revealPlan.nextStage || null;
    const rankCounts = revealPlan.rankCounts || {};
    const revealRankButton = (targetStage, rank, step, label, extraClass = "") => {
      const count = Number(rankCounts[rank] || 0);
      const skipped = count === 0;
      const detail = skipped
        ? `Bỏ qua · không có hạng ${rank} do đồng điểm`
        : count > 1
          ? `${count} đội đồng hạng · công bố cùng lúc`
          : "Mở đúng một đội";
      return `<button type="button" class="${extraClass}${skipped ? " is-skipped" : ""}" data-reveal-stage="${targetStage}" ${nextStage === targetStage ? "" : "disabled"}><span>${step}</span><strong>${label}</strong><small>${detail}</small></button>`;
    };
    let previousScore = null; let currentRank = 0;
    const resultRows = data.results.map((result, index) => {
      if (result.average_score !== null && (previousScore === null || Number(previousScore) !== Number(result.average_score))) currentRank = index + 1;
      previousScore = result.average_score;
      return `<div class="ma-reveal-result"><i>${result.average_score === null ? "—" : currentRank}</i><span><strong>#${result.team_no} ${esc(result.team_name)}</strong><small>${result.voter_count} phiếu hợp lệ</small></span><b>${result.average_score === null ? "—" : trim1(result.average_score)}</b></div>`;
    }).join("");
    return `<section class="ma-art-block"><header class="ma-art-block-head"><div><small>CÔNG BỐ VĂN NGHỆ</small><h2>One Direction · The Spotlight</h2></div><a class="ma-screen-link" href="${esc(window.MACVotingAdmin.artResultsUrl)}" target="_blank" rel="noopener">↗ Màn hình trình chiếu</a></header><div class="ma-reveal-grid"><section class="ma-panel ma-reveal-control"><header><div><small>LIVE REVEAL</small><h2>Tín hiệu MC · theo nhóm hạng thực tế</h2></div><span class="ma-reveal-status ${stage.toLowerCase()}"><i></i>${revealStageLabel(stage)}</span></header><div class="ma-reveal-intro"><strong>Spotlight từ cuối bảng lên quán quân</strong><p>Đội đồng điểm được công bố cùng lúc; hạng bị khuyết sẽ tự làm mờ và bỏ qua.</p></div><div class="ma-reveal-actions"><button type="button" class="ma-reveal-start" data-reveal-stage="ROLLING" ${nextStage === "ROLLING" ? "" : "disabled"}><span>00</span><strong>Mở màn · The Spotlight</strong><small>Spotlight bắt đầu tìm kiếm</small></button>${revealRankButton("SIXTH", 6, "01", "Công bố hạng 6")}${revealRankButton("FIFTH", 5, "02", "Công bố hạng 5")}${revealRankButton("FOURTH", 4, "03", "Công bố hạng 4")}${revealRankButton("THIRD", 3, "04", "Công bố hạng 3")}${revealRankButton("SECOND", 2, "05", "Công bố hạng 2")}${revealRankButton("FINAL", 1, "06", "Công bố quán quân", "ma-reveal-final")}</div><div class="ma-reveal-footer"><p><i></i>Màn hình trình chiếu tự đồng bộ trong khoảng 1 giây.</p><div class="ma-reveal-footer-actions"><button type="button" id="ma-art-theme" class="${lightTheme ? "is-warn" : ""}">${lightTheme ? "◎ Về tone biển đêm" : "◎ Bật tone biển sáng"}</button><button type="button" data-reveal-stage="IDLE" ${stage === "IDLE" ? "disabled" : ""}>↻ Đặt lại</button></div></div></section><aside class="ma-panel ma-reveal-scoreboard"><header><div><small>CHỈ ADMIN</small><h2>Điểm thật</h2></div></header><div>${resultRows}</div></aside></div></section>`;
  }
  function artView() {
    return `<header class="ma-top"><div><small>VĂN NGHỆ</small><h1>Chấm điểm tiết mục</h1></div>${topActions()}</header><div class="ma-art-stack">${live()}${revealView()}${ballots()}</div>`;
  }
  function totalRevealView() {
    const stage = data.totalReveal?.stage || "IDLE";
    const scoresHidden = !!data.totalScoresHidden;
    const tieWarnings = data.totalTieWarnings || [];
    const ranked = data.totalBoard?.teams || [];
    const rankCounts = ranked.reduce((acc, team) => { acc[team.rank] = (acc[team.rank] || 0) + 1; return acc; }, {});
    const rows = ranked.map((team) => `<div class="ma-reveal-result"><i>${team.rank}</i><span><strong>${esc(team.teamName)}</strong><small>Check-in ${fmt(team.checkin)} · Game ${fmt(team.games)} · Văn nghệ ${fmt(team.vote)} · Thi đua ${fmt(team.thidua)}</small></span><b>${fmt(team.total)}${rankCounts[team.rank] > 1 ? `<small style="color:#b54708;font-size:11px;font-weight:700;margin-left:6px">đồng hạng</small>` : ""}</b></div>`).join("") || `<div class="ma-reveal-result"><span><strong>Chưa có dữ liệu tổng điểm.</strong></span></div>`;
    return `<section class="ma-art-block"><header class="ma-art-block-head"><div><small>TỔNG KẾT COMPANY TRIP</small><h2>Bàn điều khiển công bố</h2></div><a class="ma-screen-link" href="${esc(window.MACVotingAdmin.resultsUrl)}" target="_blank" rel="noopener">↗ Màn hình trình chiếu</a></header><div class="ma-reveal-grid"><section class="ma-panel ma-reveal-control"><header><div><small>TÍN HIỆU TỔNG KẾT</small><h2>Các bước công bố tổng kết</h2></div><span class="ma-reveal-status ${stage.toLowerCase()}"><i></i>${totalRevealStageLabel(stage)}</span></header><div class="ma-reveal-intro"><strong>Công bố tổng kết</strong><p>Công bố kết quả chung cuộc · 6 đội · 4 mặt trận.</p></div>${tieWarnings.length ? `<div class="ma-reveal-warn" role="alert"><span>!</span><div><strong>Trùng điểm được phát hiện</strong><ul>${tieWarnings.map((warning) => `<li>${esc(warning)}</li>`).join("")}</ul></div></div>` : ""}<div class="ma-reveal-actions"><button type="button" class="ma-reveal-start" data-total-reveal-stage="ROLLING" ${stage === "IDLE" ? "" : "disabled"}><span>00</span><strong>Mở màn · Tung điểm</strong><small>Kéo mượt từ vạch 122px</small></button><button type="button" data-total-reveal-stage="RANK65" ${stage === "ROLLING" ? "" : "disabled"}><span>01</span><strong>Lộ diện hạng 6 & 5</strong><small>80% · badge khuyến khích</small></button><button type="button" data-total-reveal-stage="${stage === "TEASE43" ? "RANK43" : "TEASE43"}" ${stage === "RANK65" || stage === "TEASE43" ? "" : "disabled"}><span>02</span><strong>${stage === "TEASE43" ? "Hiện top 4" : "Nhá hàng top 4"}</strong><small>${stage === "TEASE43" ? "Chỉ lộ hạng 4-5-6 · badge khuyến khích" : "Nhấn lần 1: nhấp nháy · lần 2: lộ diện"}</small></button><button type="button" data-total-reveal-stage="TWIST" ${stage === "RANK43" || stage === "RANK12" ? "" : "disabled"}><span>03</span><strong>Tạo cú twist</strong><small>3 đội dẫn đầu cùng tung điểm</small></button><button type="button" data-total-reveal-stage="REVEAL3" ${stage === "TWIST" ? "" : "disabled"}><span>04</span><strong>Hiện top 3</strong><small>Hạng ba lộ diện · 1-2 tung tiếp</small></button><button type="button" class="ma-reveal-final" data-total-reveal-stage="FINAL" ${stage === "REVEAL3" ? "" : "disabled"}><span>05</span><strong>Công bố quán quân</strong><small>Nhất 85% · Nhì 60% + pháo hoa</small></button></div><div class="ma-reveal-footer"><p><i></i>Màn hình trình chiếu tự đồng bộ trong khoảng 1 giây.</p><div class="ma-reveal-footer-actions"><button type="button" id="ma-toggle-scores" class="${scoresHidden ? "is-warn" : ""}">${scoresHidden ? "◎ Hiện điểm trên màn chiếu" : "◉ Ẩn điểm trên màn chiếu"}</button><button type="button" data-total-reveal-stage="IDLE" ${stage === "IDLE" ? "disabled" : ""}>↻ Đặt lại</button></div></div></section><aside class="ma-panel ma-reveal-scoreboard"><header><div><small>CHỈ ADMIN</small><h2>Tổng điểm thật</h2></div></header><div>${rows}</div></aside></div></section>`;
  }
  function ballots() { return `<section class="ma-art-block ma-art-ballots"><header class="ma-art-block-head"><div><small>PHIẾU</small><h2>${canWrite() ? "Quản lý phiếu" : "Phiếu đã chấm"}</h2></div></header><div class="ma-table"><table><thead><tr><th>Người chấm</th><th>Team chấm</th><th>Tiết mục</th><th>Điểm</th><th>Trạng thái</th>${canWrite() ? "<th>Thao tác</th>" : ""}</tr></thead><tbody>${data.ballots.map((ballot) => `<tr class="${ballot.status === "REVOKED" ? "revoked" : ""}"><td><strong>${esc(ballot.full_name)}</strong><small>${esc(ballot.created_at)}</small></td><td>${esc(ballot.voter_team)}</td><td>${esc(ballot.performance_team)}</td><td><details class="ma-ballot-detail"><summary><strong>${ballot.total_score}</strong><span>Xem chi tiết</span></summary><div><p><span>Phong cách & thần thái</span><b>${ballot.style_score}</b></p><p><span>Dàn dựng & sáng tạo</span><b>${ballot.staging_score}</b></p><p><span>Đồng đội & bản sắc</span><b>${ballot.teamwork_score}</b></p></div></details></td><td><span class="badge ${ballot.status.toLowerCase()}">${ballot.status === "VALID" ? "Hợp lệ" : "Đã hủy"}</span></td>${canWrite() ? `<td>${ballot.status === "VALID" ? `<button data-ballot="${ballot.id}" data-op="revoke">Hủy phiếu</button>` : !Number(ballot.has_revote_grant) ? `<button data-ballot="${ballot.id}" data-op="revote">Cho vote lại</button>` : "Đã cấp vote lại"}</td>` : ""}</tr>`).join("")}</tbody></table></div></section>`; }
  function personnelQr() {
    const voters = (data.voters || []).filter((row) => {
      if (peopleTeam !== "all" && String(row.team_id) !== String(peopleTeam)) return false;
      const haystack = `${row.full_name} ${row.email || ""}`.toLowerCase();
      if (peopleQuery && haystack.indexOf(peopleQuery.toLowerCase()) === -1) return false;
      return true;
    });
    const actions = canWrite() ? `<div class="ma-panel-actions"><button type="button" class="ma-primary" id="ma-person-add">+ Thêm người</button><button type="button" class="ma-primary" id="ma-send-filtered">Gửi QR cho danh sách đang lọc</button></div>` : "";
    const rows = voters.map((row) => `<tr><td class="ma-person-name"><strong>${esc(row.full_name)}</strong></td><td class="ma-person-email">${esc(row.email || "—")}</td><td class="ma-person-team">#${row.team_no} ${esc(row.team_name)}</td><td class="ma-person-status"><span>${row.status === "ACTIVE" ? "Hoạt động" : "Ngưng"}</span></td>${canWrite() ? `<td class="ma-people-actions"><button type="button" data-qr-view="${row.id}">Xem & gửi</button><button type="button" data-qr-regen="${row.id}">Tạo lại QR</button>${row.email ? `<button type="button" data-role-grant="${row.id}">Cấp quyền</button>` : ""}</td>` : ""}</tr>`).join("");
    return `<section class="ma-panel ma-people-panel"><header><div class="ma-people-header-copy"><small>QR CÁ NHÂN</small><h2>${canWrite() ? "Gửi QR qua email" : "Danh sách nhân sự"}</h2><p>${canWrite() ? "Mỗi người một QR. Dùng cho check-in và login văn nghệ." : "Chỉ xem danh sách. Super admin mới gửi hoặc tạo lại QR."}</p>${actions}</div></header><div class="ma-people-body"><div class="ma-people-filter"><select id="ma-people-team"><option value="all">Tất cả team</option>${(data.teams || []).map((team) => `<option value="${team.id}" ${String(peopleTeam) === String(team.id) ? "selected" : ""}>#${team.team_no} ${esc(team.name)}</option>`).join("")}</select><input id="ma-people-query" type="search" placeholder="Tìm tên hoặc email" value="${esc(peopleQuery)}"></div><div class="ma-people-table"><table><thead><tr><th>Họ tên</th><th>Email</th><th>Team</th><th>Trạng thái</th>${canWrite() ? "<th></th>" : ""}</tr></thead><tbody>${rows || `<tr class="ma-people-empty"><td colspan="${canWrite() ? 5 : 4}">Không có nhân sự khớp bộ lọc.</td></tr>`}</tbody></table></div></div></section>`;
  }
  function dataView() { return `<header class="ma-top"><div><small>NHÂN SỰ</small><h1>Nhân sự & QR</h1></div></header>${window.MACVotingAdmin.permalinkWarning ? `<div class="ma-permalink-warning"><strong>URL đẹp chưa được bật</strong><span>Website đang dùng link dạng <code>?page_id=...</code>. Chọn cấu trúc “Tên bài viết” rồi lưu để dùng đường dẫn /cham-diem-van-nghe/.</span><a href="${esc(window.MACVotingAdmin.permalinkSettingsUrl)}">Mở cài đặt Permalink →</a></div>` : ""}${Number(data.stats.missingEmailVoters) ? `<div class="ma-permalink-warning"><strong>${data.stats.missingEmailVoters} nhân sự chưa có email</strong><span>Những người này chưa thể đăng nhập bằng username. Hãy import lại CSV có cột Email để mapping vào dữ liệu cũ.</span><a href="${esc(window.MACVotingAdmin.templateUrl)}">Tải CSV mẫu mới →</a></div>` : ""}${importFeedback ? `<div class="ma-import-success"><span>✓</span><div><strong>${esc(importFeedback.message)}</strong><small>${esc(importFeedback.fileName)} · ${esc(importFeedback.at)} · Tổng hiện có ${data.stats.activeVoters} người được vote</small></div></div>${(importFeedback.staffAccounts || []).length ? `<div class="ma-import-success ma-staff-passwords"><span>!</span><div><strong>Mật khẩu tài khoản BTC — chỉ hiện một lần</strong><ul>${importFeedback.staffAccounts.map((item) => `<li><b>${esc(item.email)}</b> · ${esc(item.password)}</li>`).join("")}</ul></div></div>` : ""}` : ""}${personFeedback ? `<div class="ma-import-success ma-staff-passwords"><span>!</span><div><strong>Tài khoản ${personFeedback.kind === "super" ? "Super admin" : "BTC"} của ${esc(personFeedback.name)} — chỉ hiện một lần</strong><ul><li>Đăng nhập: <b>${esc(personFeedback.login)}</b> · Mật khẩu: <b>${esc(personFeedback.password)}</b></li></ul><small>Dùng tài khoản này đăng nhập trang Máy quét BTC. Muốn đổi team được quét thì vào tab Check-in → Tài khoản máy quét.</small></div></div>` : ""}${canWrite() ? `<div class="ma-data"><section class="ma-panel"><span class="ma-icon">⇧</span><small>IMPORT CSV</small><h2>Danh sách nhân sự</h2><p>Cột bắt buộc: Họ tên, Team, Email. Cột tùy chọn: Vai trò (BTC/Super admin) và Mật khẩu để tạo tài khoản dashboard. Email chấp nhận @macusaone.com, @yesoffice.vn hoặc @macmarketing.vn; username không có @ mặc định thành @macusaone.com.</p><label class="ma-primary">Chọn & xem trước CSV<input id="ma-import" type="file" accept=".csv,text/csv"></label><a href="${esc(window.MACVotingAdmin.templateUrl)}">↓ Tải file mẫu</a></section><section class="ma-panel"><span class="ma-icon">▦</span><small>SAO LƯU & ĐỐI SOÁT</small><h2>Xuất dữ liệu</h2><p>Gồm bảng điểm và chi tiết toàn bộ phiếu hợp lệ/đã hủy.</p><a class="ma-primary" href="${esc(window.MACVotingAdmin.exportUrl)}">↓ Xuất CSV kết quả</a></section></div>` : ""}${personnelQr()}`; }
  function render() {
    if (window.MACVotingAdmin.role === "guide" && tab !== "checkin" && tab !== "mybus") tab = "checkin";
    root.classList.toggle("is-readonly", !canWrite());
    root.innerHTML = `<div class="ma-layout">${sidebar()}<main class="ma-content">${tab === "overview" ? pointsView() : tab === "checkin" ? checkinView() : tab === "games" ? gamesView() : tab === "thidua" ? thiduaView() : tab === "art" ? artView() : tab === "bus" ? busView() : tab === "mybus" ? myBusView() : dataView()}</main></div>`;
    const sideNav = root.querySelector(".ma-side nav");
    const activeTabButton = sideNav?.querySelector("button.active");
    // Mobile: thanh tab cuộn ngang trong .ma-side (không phải nav) — sau mỗi lần
    // render phải kéo tab đang chọn vào giữa tầm nhìn, nếu không nó giật về vị trí đầu.
    if (activeTabButton) {
      const scroller = [sideNav, root.querySelector(".ma-side")].find((element) => element && element.scrollWidth > element.clientWidth);
      if (scroller) {
        const scrollerRect = scroller.getBoundingClientRect();
        const buttonRect = activeTabButton.getBoundingClientRect();
        scroller.scrollTo({ left: scroller.scrollLeft + (buttonRect.left - scrollerRect.left) - (scroller.clientWidth - buttonRect.width) / 2 });
      }
    }
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
    root.querySelector("#ma-seed-demo")?.addEventListener("click", async () => {
      if (!(await confirmDialog({ title: "Dữ liệu demo", message: "Áp bộ dữ liệu diễn tập (48 nhân sự ảo, 240 phiếu, điểm check-in · trò chơi · thi đua)? Toàn bộ phiếu và điểm chấm hiện tại sẽ bị thay thế bởi bộ demo.", confirmLabel: "Áp dữ liệu demo" }))) return;
      try {
        const result = await ajax("mac_vote_seed_demo");
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    });
    root.querySelector("#ma-art-theme")?.addEventListener("click", async (event) => {
      const button = event.currentTarget;
      const light = !data.artLightTheme;
      button.disabled = true;
      try {
        const result = await ajax("mac_vote_toggle_art_theme", { light: light ? 1 : "" });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) {
        button.disabled = false;
        notify(err.message, true);
      }
    });
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
    root.querySelectorAll("[data-total-reveal-stage]").forEach((button) => button.addEventListener("click", async () => {
      const stage = button.dataset.totalRevealStage;
      const originalLabel = button.innerHTML;
      button.disabled = true;
      button.classList.add("is-loading");
      if (stage !== "IDLE") button.innerHTML = `<strong>Đang gửi tín hiệu…</strong><small>Giữ nguyên màn hình trình chiếu</small>`;
      try {
        const result = await ajax("mac_vote_reveal_total", { stage });
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
    root.querySelector("#ma-toggle-scores")?.addEventListener("click", async (event) => {
      const button = event.currentTarget;
      const hide = !data.totalScoresHidden;
      button.disabled = true;
      button.classList.add("is-loading");
      try {
        const result = await ajax("mac_vote_toggle_scores", { hidden: hide ? 1 : "" });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) {
        button.disabled = false;
        button.classList.remove("is-loading");
        notify(err.message, true);
      }
    });
    root.querySelectorAll("[data-round]").forEach((button) => button.addEventListener("click", async () => {
      const operation = button.dataset.op;
      if ((operation === "open" || operation === "reopen") && !data.votingEnabled) {
        notify("Cổng văn nghệ đang tắt. Bật cổng ở khối VĂN NGHỆ trước rồi mới mở vote được.", true);
        return;
      }
      let duration = null;
      if (operation !== "close") {
        duration = Number(root.querySelector(`[data-round-duration="${button.dataset.round}"]`)?.value);
        if (!Number.isInteger(duration) || duration < 1 || duration > 120) { notify("Thời gian tự đóng phải từ 1 đến 120 phút.", true); return; }
      }
      const activeRound = data.rounds.find((round) => round.status === "OPEN" && String(round.id) !== String(button.dataset.round));
      if ((operation === "open" || operation === "reopen") && activeRound) {
        showRoundConflictModal(button.dataset.round, activeRound.id);
        return;
      }
      const messages = {
        open: `Mở lượt ${button.dataset.round} để nhận phiếu? Lượt sẽ tự đóng sau ${duration} phút.`,
        close: `Đóng lượt ${button.dataset.round}? Người chưa chấm sẽ tạm thời không gửi được phiếu.`,
        reopen: `Mở lại lượt ${button.dataset.round}? Phiếu cũ được giữ nguyên, chỉ người chưa chấm mới được tiếp tục và lượt tự đóng sau ${duration} phút.`,
      };
      const roundLabels = { open: "Mở vote", close: "Đóng lượt", reopen: "Mở lại" };
      if (!(await confirmDialog({ title: `Lượt ${button.dataset.round}`, message: messages[operation], confirmLabel: roundLabels[operation], danger: operation === "close" }))) return;
      const originalLabel = button.textContent;
      button.disabled = true;
      button.textContent = operation === "reopen" ? "Đang mở lại…" : "Đang cập nhật…";
      try {
        data = await ajax("mac_vote_round", { roundId: button.dataset.round, operation, durationMinutes: duration || "" });
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
    root.querySelectorAll("[data-ballot]").forEach((button) => button.addEventListener("click", async () => { const reason = await promptDialog({ title: button.dataset.op === "revoke" ? "Hủy phiếu" : "Cho vote lại", label: "Lý do", confirmLabel: "Xác nhận" }); if (reason === null || !reason.trim()) return; try { data = await ajax("mac_vote_ballot", { ballotId: button.dataset.ballot, operation: button.dataset.op, reason: reason.trim() }); render(); notify("Đã cập nhật phiếu."); } catch (err) { notify(err.message, true); } }));
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
      if (!(await confirmDialog({ title: "Xóa team", message: `Xóa team “${button.dataset.teamName}”? Team đã xóa không thể khôi phục.`, confirmLabel: "Xóa team", danger: true }))) return;
      button.disabled = true; button.textContent = "Đang xóa…";
      try { const result = await ajax("mac_vote_team", { operation: "delete", teamId: button.dataset.teamDelete }); data = result.overview; render(); notify(result.message); }
      catch (err) { button.disabled = false; button.textContent = "Xóa"; notify(err.message, true); }
    }));
    root.querySelector("#ma-gate")?.addEventListener("click", async () => {
      const next = !data.votingEnabled;
      if (!(await confirmDialog({ title: "Cổng văn nghệ", message: next ? "Bật cổng văn nghệ? Nhân viên sẽ login được bằng QR và username." : "Tắt cổng văn nghệ? Public sẽ không vào được trang chấm điểm.", confirmLabel: next ? "Bật cổng" : "Tắt cổng", danger: !next }))) return;
      const previous = !!data.votingEnabled;
      data.votingEnabled = next;
      render();
      try {
        const result = await ajax("mac_vote_gate", { enabled: next ? "1" : "0" });
        data.votingEnabled = !!result.votingEnabled;
        render();
        notify(result.message);
      } catch (err) {
        data.votingEnabled = previous;
        render();
        notify(err.message, true);
      }
    });
    root.querySelectorAll("[data-checkpoint]").forEach((button) => button.addEventListener("click", async () => {
      const operation = button.dataset.op;
      let duration = null;
      if (["open", "reopen"].includes(operation)) {
        duration = Number(root.querySelector(`[data-checkpoint-duration="${button.dataset.checkpoint}"]`)?.value);
        if (!Number.isInteger(duration) || duration < 1 || duration > 120) { notify("Thời gian tự đóng phải từ 1 đến 120 phút.", true); return; }
      }
      const messages = {
        open: `Mở trạm ${button.dataset.checkpoint}? Chỉ một trạm được mở tại một thời điểm và cửa sổ check-in tự khóa sau ${duration} phút.`,
        close: `Đóng & chốt trạm ${button.dataset.checkpoint}? Điểm hạng sẽ được ghi vào sổ.`,
        reopen: `Mở lại trạm ${button.dataset.checkpoint}? Cửa sổ check-in tự khóa sau ${duration} phút.`,
      };
      const checkpointLabels = { open: "Mở trạm", close: "Đóng & chốt", reopen: "Mở lại" };
      if (!(await confirmDialog({ title: `Trạm ${button.dataset.checkpoint}`, message: messages[operation], confirmLabel: checkpointLabels[operation], danger: operation === "close" }))) return;
      const originalLabel = button.textContent;
      button.disabled = true;
      button.classList.add("is-loading");
      button.textContent = operation === "reopen" ? "Đang mở lại…" : operation === "close" ? "Đang chốt…" : "Đang mở…";
      try {
        const result = await ajax("mac_vote_checkpoint", { checkpointId: button.dataset.checkpoint, operation, durationMinutes: duration || "" });
        applyCheckpointPayload(result);
        render();
        notify(result.message || "Đã cập nhật trạm check-in.");
      } catch (err) {
        button.disabled = false;
        button.classList.remove("is-loading");
        button.textContent = originalLabel;
        notify(err.message, true);
      }
    }));
    root.querySelectorAll("[data-game-rank]").forEach((select) => select.addEventListener("change", async () => {
      const rank = Number(select.value);
      const boardRow = (data.games?.board || []).find((row) => String(row.teamId) === String(select.dataset.team));
      const cell = (boardRow?.cells || []).find((entry) => String(entry.gameId) === String(select.dataset.game));
      if (boardRow && cell) {
        cell.rank = rank;
        cell.points = rank >= 1 ? ladder[rank - 1] : 0;
        boardRow.total = (boardRow.cells || []).reduce((sum, item) => sum + (Number(item.points) || 0), 0);
        render();
      }
      try {
        const result = await ajax("mac_vote_games", { operation: "rank", gameId: select.dataset.game, teamId: select.dataset.team, rank: select.value });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); load(); }
    }));
    root.querySelector("#ma-exempt-form")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const openCheckpoint = (data.checkpoints || []).find((item) => item.status === "OPEN");
      if (!openCheckpoint) { notify("Chưa có trạm check-in nào đang mở.", true); return; }
      const form = event.currentTarget;
      const voterId = form.querySelector("#ma-exempt-voter").value;
      const reason = form.querySelector("#ma-exempt-reason").value.trim();
      if (!voterId) { notify("Không còn ai để miễn ở trạm này.", true); return; }
      if (!reason) { notify("Nhập lý do miễn check-in.", true); return; }
      const button = form.querySelector("button[type='submit']");
      button.disabled = true; button.textContent = "Đang lưu…";
      try {
        const result = await ajax("mac_vote_exemption", { operation: "set", checkpointId: openCheckpoint?.id || "", voterId, reason });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { button.disabled = false; button.textContent = "Miễn check-in"; notify(err.message, true); }
    });
    root.querySelector("#ma-exempt-search")?.addEventListener("input", (event) => {
      exemptQuery = event.currentTarget.value;
      const select = root.querySelector("#ma-exempt-voter");
      if (!select) return;
      const openCheckpoint = (data.checkpoints || []).find((item) => item.status === "OPEN");
      const openExemptions = openCheckpoint ? (data.exemptions?.[openCheckpoint.id] || []) : [];
      const query = normalizeHeader(event.currentTarget.value);
      const filtered = (data.voters || []).filter((row) => row.status === "ACTIVE"
        && !openExemptions.some((item) => String(item.voterId) === String(row.id))
        && (!query || normalizeHeader(`${row.full_name} ${row.email || ""} ${row.team_name || ""}`).includes(query)));
      select.innerHTML = filtered.map((row) => `<option value="${row.id}">${esc(row.full_name)} · #${row.team_no} ${esc(row.team_name)}</option>`).join("") || `<option value="">Không tìm thấy ai khớp</option>`;
    });
    root.querySelectorAll("[data-exempt-clear]").forEach((button) => button.addEventListener("click", async () => {
      const openCheckpoint = (data.checkpoints || []).find((item) => item.status === "OPEN");
      if (!openCheckpoint) { notify("Chưa có trạm check-in nào đang mở.", true); return; }
      if (!(await confirmDialog({ title: "Bỏ miễn check-in", message: `Bỏ miễn check-in của ${button.dataset.exemptName || "người này"} ở trạm đang mở?`, confirmLabel: "Bỏ miễn", danger: true }))) return;
      try {
        const result = await ajax("mac_vote_exemption", { operation: "clear", checkpointId: openCheckpoint?.id || "", voterId: button.dataset.exemptClear });
        data = result.overview;
        render();
        notify(result.message);
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
    root.querySelector("#ma-bus-open")?.addEventListener("click", async (event) => {
      event.currentTarget.disabled = true;
      try {
        const result = await ajax("mac_vote_bus_open");
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); render(); }
    });
    root.querySelectorAll("[data-bus-advance]").forEach((button) => button.addEventListener("click", async () => {
      try {
        const result = await ajax("mac_vote_bus_advance", { busId: button.dataset.busAdvance });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    }));
    root.querySelectorAll("[data-bus-manifest]").forEach((button) => button.addEventListener("click", () => { busManifestId = Number(button.dataset.busManifest); render(); }));
    root.querySelector("#ma-bus-query")?.addEventListener("input", (event) => { busQuery = event.currentTarget.value; });
    root.querySelector("#ma-bus-query")?.addEventListener("keydown", (event) => { if (event.key === "Enter") { busQuery = event.currentTarget.value; render(); } });
    root.querySelectorAll("[data-bus-move-member]").forEach((select) => select.addEventListener("change", async () => {
      if (!select.value) return;
      try {
        const result = await ajax("mac_vote_bus_move", { memberId: select.dataset.busMoveMember, toBus: select.value });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    }));
    root.querySelector("#ma-bus-reset")?.addEventListener("click", async () => {
      const ok = await confirmDialog({ title: "Reset đợt phân xe?", message: "Toàn bộ danh sách xe, người đã gán và lượt điểm danh trên xe sẽ bị xóa, 5 xe về trạng thái CHỜ. Check-in và điểm thi đấu không bị ảnh hưởng.", confirmLabel: "Reset phân xe", danger: true });
      if (!ok) return;
      try {
        const result = await ajax("mac_vote_bus_reset");
        data = result.overview;
        busManifestId = 0;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    });
    root.querySelector("#ma-bus-csv")?.addEventListener("click", () => {
      const bus = ((data.buses || {}).buses || []).find((b) => b.id === busManifestId);
      if (!bus) return;
      const rows = [["Họ tên", "Team", "Loại", "Nguồn"], ...(bus.manifest || []).map((m) => [m.name, m.teamNo ? `#${m.teamNo} ${m.teamName || ""}` : "", m.memberType === "EMPLOYEE" ? "NV QR" : m.memberType === "STAFF" ? "BTC/Hoa tiêu" : "Thủ công", m.source])];
      const csv = "\uFEFF" + rows.map((r) => r.map((c) => `"${String(c ?? "").replace(/"/g, '""')}"`).join(",")).join("\n");
      const link = document.createElement("a");
      link.href = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
      link.download = `manifest-${String(bus.name).replace(/\s+/g, "-").toLowerCase()}.csv`;
      link.click();
      URL.revokeObjectURL(link.href);
      notify(`Đã xuất CSV ${bus.name}.`);
    });
    root.querySelector("#ma-bus-any-query")?.addEventListener("input", (event) => { busAnyQuery = event.currentTarget.value; });
    root.querySelector("#ma-bus-any-query")?.addEventListener("keydown", (event) => { if (event.key === "Enter") { busAnyQuery = event.currentTarget.value; render(); } });
    root.querySelectorAll("[data-bus-remove]").forEach((button) => button.addEventListener("click", async () => {
      try {
        const result = await ajax("mac_vote_bus_remove", { memberId: button.dataset.busRemove });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    }));
    root.querySelectorAll("[data-unassigned-assign]").forEach((select) => select.addEventListener("change", async () => {
      if (!select.value) return;
      try {
        const result = await ajax("mac_vote_bus_assign", { voterId: select.dataset.unassignedAssign, toBus: select.value });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    }));
    root.querySelector("#ma-bus-add-form")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const manual = form.querySelector("#ma-bus-manual")?.value.trim() || "";
      const picked = Array.from(form.querySelectorAll("input[name='staffIds']:checked")).map((input) => input.value);
      const anyPicked = Array.from(form.querySelectorAll("input[name='anyIds']:checked")).map((input) => input.value);
      if (!picked.length && !anyPicked.length && !manual) {
        notify("Chọn ít nhất một người hoặc gõ tên.", true);
        return;
      }
      try {
        let added = 0;
        for (const voterId of [...picked, ...anyPicked]) {
          await ajax("mac_vote_bus_assign", { voterId, toBus: busManifestId });
          added += 1;
        }
        let result = null;
        if (manual) {
          result = await ajax("mac_vote_bus_add_manual", { busId: busManifestId, manualName: manual });
          added += 1;
        }
        if (!result) {
          const refreshed = await ajax("mac_vote_overview");
          data = refreshed;
        } else {
          data = result.overview;
        }
        render();
        notify(`Đã thêm ${added} người vào xe.`);
      } catch (err) { notify(err.message, true); }
    });
    root.querySelector("#ma-guide-form")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      try {
        const result = await ajax("mac_vote_guide_save", { name: form.name.value, login: form.login.value, password: form.password.value, busId: form.busId.value });
        data = result.overview;
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    });
    const rollcall = async (values) => {
      try {
        const result = await ajax("mac_vote_rollcall", values);
        if (result.overview) data = result.overview;
        if (result.myBus) data.myBus = result.myBus;
        render();
      } catch (err) { notify(err.message, true); }
    };
    root.querySelectorAll("[data-rollcall-toggle]").forEach((button) => button.addEventListener("click", () => rollcall({ busId: button.dataset.bus || data.myBus?.busId, operation: "toggle", memberId: button.dataset.rollcallToggle, present: button.dataset.present })));
    root.querySelector("#ma-rollcall-new")?.addEventListener("click", (event) => rollcall({ busId: event.currentTarget.dataset.bus || data.myBus?.busId, operation: "new_round" }));
    root.querySelectorAll("[data-bus-filter]").forEach((button) => button.addEventListener("click", () => { busFilter = button.dataset.busFilter; render(); }));
    root.querySelector("#ma-bus-search")?.addEventListener("input", (event) => { busSearch = event.currentTarget.value; });
    root.querySelector("#ma-bus-search")?.addEventListener("keydown", (event) => { if (event.key === "Enter") { busSearch = event.currentTarget.value; render(); } });
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
    const slugEmail = (name) => {
      const ascii = name.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/đ/g, "d");
      const local = ascii.split(/[^a-z0-9]+/).filter(Boolean).join(".");
      return local ? `${local}@macusaone.com` : "";
    };
    const bindModalClose = (modal) => {
      const onKey = (event) => { if (event.key === "Escape") close(); };
      const close = () => { document.removeEventListener("keydown", onKey); modal.remove(); };
      modal.addEventListener("click", (event) => { if (event.target === modal) close(); });
      modal.querySelectorAll("[data-close]").forEach((button) => button.addEventListener("click", close));
      document.addEventListener("keydown", onKey);
      return close;
    };
    const showPersonModal = () => {
      const teams = (data.teams || []).slice().sort((a, b) => a.team_no - b.team_no);
      const modal = document.createElement("div");
      modal.className = "ma-modal";
      modal.innerHTML = `<div class="ma-modal-card ma-person-modal" role="dialog" aria-modal="true"><div class="ma-modal-head"><div><small>THÊM NHÂN SỰ</small><h2>Thêm người vào danh sách</h2></div><button type="button" data-close aria-label="Đóng">×</button></div><form class="ma-person-form"><label class="ma-span-2">Họ tên<input name="fullName" maxlength="190" required placeholder="Nguyễn Văn A"></label><label class="ma-span-2">Email<input name="email" type="email" placeholder="ten.nguoidung@macusaone.com"><small class="ma-field-hint">Tự sinh từ họ tên (@macusaone.com ảo) — sửa lại hoặc xóa trắng nếu cần.</small></label><label>Team<select name="teamId">${teams.map((team) => `<option value="${team.id}">#${team.team_no} ${esc(team.name)}</option>`).join("")}</select></label><label>Vai trò<select name="role"><option value="" selected>Nhân sự thường</option><option value="btc">BTC · máy quét</option><option value="super">Super admin</option></select></label><label class="ma-span-2">Mật khẩu (tùy chọn)<input name="password" autocomplete="new-password" placeholder="Để trống = tự tạo · chỉ dùng khi chọn vai trò"></label><div class="ma-modal-actions"><button type="button" data-close>Hủy</button><button type="submit" class="ma-primary">+ Thêm người</button></div></form></div>`;
      root.append(modal);
      const close = bindModalClose(modal);
      const nameInput = modal.querySelector("input[name='fullName']");
      const emailInput = modal.querySelector("input[name='email']");
      let emailDirty = false;
      emailInput.addEventListener("input", () => { emailDirty = emailInput.value.trim() !== ""; });
      nameInput.addEventListener("input", () => { if (!emailDirty) emailInput.value = slugEmail(nameInput.value); });
      modal.querySelector(".ma-person-form").addEventListener("submit", async (event) => {
        event.preventDefault();
        const button = event.currentTarget.querySelector("button[type='submit']");
        button.disabled = true;
        button.textContent = "Đang thêm…";
        try {
          const result = await ajax("mac_vote_person", {
            operation: "add",
            name: nameInput.value.trim(),
            teamId: modal.querySelector("select[name='teamId']").value,
            email: emailInput.value.trim(),
            role: modal.querySelector("select[name='role']").value,
            password: modal.querySelector("input[name='password']").value,
          });
          close();
          data = result.overview;
          personFeedback = result.account || null;
          render();
          notify(result.message);
        } catch (err) { button.disabled = false; button.textContent = "+ Thêm người"; notify(err.message, true); }
      });
      nameInput.focus();
    };
    const showGrantModal = (voter) => {
      const modal = document.createElement("div");
      modal.className = "ma-modal";
      modal.innerHTML = `<div class="ma-modal-card ma-person-modal" role="dialog" aria-modal="true"><div class="ma-modal-head"><div><small>CẤP QUYỀN</small><h2>Tài khoản Quét QR check-in</h2></div><button type="button" data-close aria-label="Đóng">×</button></div><div class="ma-grant-summary"><strong>${esc(voter.full_name)}</strong><small>${esc(voter.email)} · #${voter.team_no} ${esc(voter.team_name)}</small></div><form class="ma-person-form"><label>Vai trò<select name="role"><option value="btc" selected>BTC · Quét QR check-in</option><option value="super">Super admin · toàn quyền</option></select></label><label>Mật khẩu (tùy chọn)<input name="password" autocomplete="new-password" placeholder="Để trống = tự tạo"></label><div class="ma-modal-actions"><button type="button" data-close>Hủy</button><button type="submit" class="ma-primary">Cấp quyền</button></div></form></div>`;
      root.append(modal);
      const close = bindModalClose(modal);
      modal.querySelector(".ma-person-form").addEventListener("submit", async (event) => {
        event.preventDefault();
        const button = event.currentTarget.querySelector("button[type='submit']");
        button.disabled = true;
        button.textContent = "Đang cấp…";
        try {
          const result = await ajax("mac_vote_person", {
            operation: "grant",
            voterId: voter.id,
            role: modal.querySelector("select[name='role']").value,
            password: modal.querySelector("input[name='password']").value,
          });
          close();
          data = result.overview;
          personFeedback = result.account || null;
          render();
          notify(result.message);
        } catch (err) { button.disabled = false; button.textContent = "Cấp quyền"; notify(err.message, true); }
      });
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
    root.querySelector("#ma-person-add")?.addEventListener("click", showPersonModal);
    root.querySelectorAll("[data-role-grant]").forEach((button) => button.addEventListener("click", () => {
      const voter = (data.voters || []).find((row) => String(row.id) === String(button.dataset.roleGrant));
      if (voter) showGrantModal(voter);
    }));
    root.querySelectorAll("[data-qr-regen]").forEach((button) => button.addEventListener("click", async () => {
      if (!(await confirmDialog({ title: "Tạo lại QR", message: "Tạo lại QR? QR cũ sẽ mất hiệu lực và cần gửi email mới.", confirmLabel: "Tạo lại QR", danger: true }))) return;
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
      if (!(await confirmDialog({ title: "Gửi email QR", message: `Gửi QR tới ${voters.length} người?`, confirmLabel: "Gửi email" }))) return;
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
    const applyLocalAward = (points) => {
      const team = (data.totalBoard?.teams || []).find((row) => String(row.teamId) === String(awardTeamId));
      const cell = (team?.cells || []).find((entry) => String(entry.categoryId) === String(awardCategoryId));
      if (!team || !cell) return;
      cell.hasScore = true;
      cell.points = points;
      cell.state = points > 0 ? "plus" : "zero";
      refreshCategoryMeta(awardCategoryId, data.totalBoard.teams);
      recomputeThidua(data.totalBoard.teams);
    };
    const applyLocalClear = () => {
      const team = (data.totalBoard?.teams || []).find((row) => String(row.teamId) === String(awardTeamId));
      const cell = (team?.cells || []).find((entry) => String(entry.categoryId) === String(awardCategoryId));
      if (!team || !cell) return;
      cell.hasScore = false;
      cell.points = 0;
      cell.state = "none";
      refreshCategoryMeta(awardCategoryId, data.totalBoard.teams);
      recomputeThidua(data.totalBoard.teams);
    };
    const saveAward = async (points) => {
      if (!awardCategoryId || !awardTeamId) return;
      applyLocalAward(points);
      render();
      try {
        const result = await ajax("mac_vote_points", {
          operation: "award",
          categoryId: awardCategoryId,
          teamId: awardTeamId,
          points,
        });
        applyPointsPayload(result);
        render();
        notify(result.message);
      } catch (err) {
        notify(err.message, true);
        load();
      }
    };
    const saveClear = async () => {
      if (!awardCategoryId || !awardTeamId) return;
      applyLocalClear();
      render();
      try {
        const result = await ajax("mac_vote_points", {
          operation: "clear",
          categoryId: awardCategoryId,
          teamId: awardTeamId,
        });
        applyPointsPayload(result);
        render();
        notify(result.message);
      } catch (err) {
        notify(err.message, true);
        load();
      }
    };
    root.querySelectorAll("[data-award-points]").forEach((button) => button.addEventListener("click", async () => {
      const value = Number(button.dataset.awardPoints);
      const selectedTeam = (data.totalBoard?.teams || []).find((team) => String(team.teamId) === String(awardTeamId));
      const cell = (selectedTeam?.cells || []).find((entry) => String(entry.categoryId) === String(awardCategoryId));
      button.disabled = true;
      // Bấm lại ô đang chọn = xóa kết quả đã chấm (operation clear riêng, không nhầm với set 0).
      if (cell?.hasScore && Number(cell.points) === value) await saveClear();
      else await saveAward(value);
    }));
    root.querySelector("#ma-cat-add")?.addEventListener("click", async (event) => {
      const trigger = event.currentTarget;
      const name = await promptDialog({ title: "Thêm hạng mục thi đua", label: "Tên hạng mục thi đua", confirmLabel: "Thêm hạng mục", placeholder: "Ví dụ: Trang phục đẹp" });
      if (name === null) return;
      const trimmed = name.trim();
      if (trimmed.length < 2) { notify("Tên hạng mục thi đua phải có ít nhất 2 ký tự.", true); return; }
      trigger.disabled = true;
      trigger.classList.add("is-loading");
      trigger.textContent = "…";
      try {
        const result = await ajax("mac_vote_points", { operation: "add", name: trimmed });
        if (result.categoryId) awardCategoryId = String(result.categoryId);
        applyPointsPayload(result);
        render();
        notify(result.message);
      } catch (err) {
        trigger.disabled = false;
        trigger.classList.remove("is-loading");
        trigger.textContent = "+";
        notify(err.message, true);
      }
    });
    root.querySelector("#ma-cat-edit")?.addEventListener("click", async () => {
      const current = (data.totalBoard?.categories || []).find((category) => String(category.id) === String(awardCategoryId));
      if (!current) return;
      const name = await promptDialog({ title: "Đổi tên hạng mục thi đua", label: "Tên hạng mục thi đua", initial: current.name, confirmLabel: "Lưu lại" });
      if (name === null) return;
      const trimmed = name.trim();
      if (trimmed.length < 2) { notify("Tên hạng mục thi đua phải có ít nhất 2 ký tự.", true); return; }
      try {
        const result = await ajax("mac_vote_points", { operation: "rename", categoryId: awardCategoryId, name: trimmed });
        applyPointsPayload(result);
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    });
    root.querySelector("#ma-cat-delete")?.addEventListener("click", async () => {
      const current = (data.totalBoard?.categories || []).find((category) => String(category.id) === String(awardCategoryId));
      if (!current) return;
      if (!(await confirmDialog({ title: "Xóa hạng mục thi đua", message: `Xóa hạng mục thi đua “${current.name}”? Điểm đã cộng của hạng mục này cũng sẽ mất.`, confirmLabel: "Xóa hạng mục", danger: true }))) return;
      try {
        const result = await ajax("mac_vote_points", { operation: "delete", categoryId: awardCategoryId });
        awardCategoryId = "";
        applyPointsPayload(result);
        render();
        notify(result.message);
      } catch (err) { notify(err.message, true); }
    });
  }
  // Dashboard chỉ tải lại khi bấm nút hoặc sau thao tác — không tự reload định kỳ để khỏi giật bảng/mất dữ liệu đang gõ.
  load();
  setInterval(() => {
    root.querySelectorAll("[data-window-closes]").forEach((badge) => {
      const seconds = remainingSeconds(badge.dataset.windowCloses);
      if (seconds <= 0) {
        badge.classList.remove("live");
        badge.classList.add("locked");
        badge.textContent = "Đã khóa";
      } else {
        badge.textContent = `Còn ${formatRemainingTime(badge.dataset.windowCloses)}`;
      }
    });
  }, 1000);
})();
