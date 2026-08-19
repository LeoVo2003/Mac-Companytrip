(() => {
  const root = document.getElementById("mac-checkin-app");
  if (!root) return;
  const config = {
    restUrl: root.dataset.restUrl,
    nonce: root.dataset.nonce,
    logo: root.dataset.logo,
    logout: root.dataset.logout,
    dashboard: root.dataset.dashboard || root.dataset.logout,
  };
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
  const remainingSeconds = (closesAt) => Math.max(0, Math.ceil((new Date(String(closesAt).replace(" ", "T") + "Z").getTime() - Date.now()) / 1000));
  const remainingClock = (closesAt) => {
    const seconds = remainingSeconds(closesAt);
    return `${String(Math.floor(seconds / 60)).padStart(2, "0")}:${String(seconds % 60).padStart(2, "0")}`;
  };
  const windowLabel = () => `CỬA SỔ ${windowMinutes}'`;
  const windowLockedText = () => `Cửa sổ ${windowMinutes} phút đã hết`;
  const backLink = () => `<a class="mc-back" href="${esc(config.dashboard)}">← Quay lại dashboard</a>`;
  let bootstrap = null;
  let selectedTeamId = null;
  let windowMinutes = 15;
  let flash = null;
  let scanning = false;
  let lastToken = "";
  let lastScanAt = 0;
  let videoStream = null;
  let raf = 0;

  const request = async (path, options = {}) => {
    const response = await fetch(config.restUrl + path, {
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-WP-Nonce": config.nonce, ...(options.headers || {}) },
      ...options,
    });
    const data = await response.json();
    if (!response.ok) {
      const error = new Error(data.message || "Không quét được QR.");
      error.payload = data.data || data;
      error.code = data.code || data.data?.code;
      throw error;
    }
    return data;
  };

  const stopCamera = () => {
    scanning = false;
    if (raf) cancelAnimationFrame(raf);
    raf = 0;
    if (videoStream) videoStream.getTracks().forEach((track) => track.stop());
    videoStream = null;
  };

  async function load() {
    try {
      bootstrap = await request("checkin/bootstrap");
      windowMinutes = Number(bootstrap.windowMinutes) > 0 ? Number(bootstrap.windowMinutes) : 15;
      if (!selectedTeamId && bootstrap.allowedTeams?.length) selectedTeamId = String(bootstrap.allowedTeams[0].teamId);
      if (selectedTeamId && !bootstrap.allowedTeams?.some((team) => String(team.teamId) === String(selectedTeamId))) {
        selectedTeamId = bootstrap.allowedTeams?.[0] ? String(bootstrap.allowedTeams[0].teamId) : null;
      }
      render();
      await startCamera();
    } catch (err) {
      root.innerHTML = `<main class="mc-shell"><section class="mc-card"><p class="mc-kicker">CHECK-IN</p><h1>Không mở được máy quét</h1><p class="mc-meta">${esc(err.message)}</p></section></main>`;
    }
  }

  function currentTeam() {
    return (bootstrap?.allowedTeams || []).find((team) => String(team.teamId) === String(selectedTeamId)) || bootstrap?.allowedTeams?.[0] || null;
  }

  function render() {
    const team = currentTeam();
    const checkpoint = bootstrap?.activeCheckpoint;
    if (!checkpoint) {
      stopCamera();
      root.innerHTML = `<main class="mc-shell"><header class="mc-top"><img src="${esc(config.logo)}" alt="MAC Marketing">${backLink()}</header><section class="mc-card mc-empty"><p class="mc-kicker">CHECK-IN</p><h1>Chưa có mốc đang mở</h1><p class="mc-meta">Đợi admin mở một checkpoint rồi tải lại trang.</p></section></main>`;
      return;
    }
    if (!team) {
      stopCamera();
      root.innerHTML = `<main class="mc-shell"><header class="mc-top"><img src="${esc(config.logo)}" alt="MAC Marketing">${backLink()}</header><section class="mc-card mc-empty"><p class="mc-kicker">CHECK-IN</p><h1>Chưa được gán team</h1><p class="mc-meta">Nhờ admin gán team cho tài khoản BTC này.</p></section></main>`;
      return;
    }
    const ratio = team.eligible ? Math.round((team.checkedIn / team.eligible) * 100) : 0;
    const flashHtml = flash ? `<div class="mc-flash ${flash.type}" role="status">${esc(flash.text)}</div>` : "";
    const windowHtml = team.windowLocked
      ? `<div class="mc-window locked"><span>ĐÃ KHÓA</span><strong>${windowLockedText()}</strong></div>`
      : team.windowClosesAt
        ? `<div class="mc-window live" data-window-closes="${esc(team.windowClosesAt)}"><span>${windowLabel()}</span><strong>Còn ${remainingClock(team.windowClosesAt)}</strong></div>`
        : `<div class="mc-window idle"><span>${windowLabel()}</span><strong>Mở ở lượt quét đầu tiên</strong></div>`;
    const doneHtml = team.completed ? `<div class="mc-done"><span>TEAM ĐÃ ĐỦ</span><strong>${team.checkedIn} / ${team.eligible}</strong><p>Hoàn thành ${esc(team.completedAt || "")}${team.temporaryRank ? ` · Hạng tạm thời #${team.temporaryRank}` : ""}</p></div>` : "";
    root.innerHTML = `<main class="mc-shell"><header class="mc-top"><img src="${esc(config.logo)}" alt="MAC Marketing">${backLink()}</header><section class="mc-card"><p class="mc-kicker">CHECK-IN — TEAM ${team.teamNumber}</p><h1>${esc(team.teamName)}</h1><p class="mc-meta">MỐC ${checkpoint.id} · ${esc(checkpoint.name)}</p>${windowHtml}${bootstrap.allowedTeams.length > 1 ? `<div class="mc-teams">${bootstrap.allowedTeams.map((item) => `<button type="button" class="${String(item.teamId) === String(team.teamId) ? "active" : ""}" data-team="${item.teamId}">#${item.teamNumber} ${esc(item.teamName)}<span>${item.checkedIn}/${item.eligible}</span></button>`).join("")}</div>` : ""}<div class="mc-progress"><strong>${team.checkedIn} / ${team.eligible}</strong><div class="mc-bar" aria-hidden="true"><b style="width:${ratio}%"></b></div></div>${flashHtml}${doneHtml}<div class="mc-camera"><video id="mc-video" playsinline muted autoplay></video><canvas id="mc-canvas"></canvas><div class="mc-frame" aria-hidden="true"></div></div><p class="mc-kicker">CÒN THIẾU</p><ul class="mc-missing">${team.missingMembers.length ? team.missingMembers.map((member) => `<li><span>${esc(member.fullName)}</span></li>`).join("") : `<li>Đã đủ người</li>`}</ul></section></main>`;
    root.querySelectorAll("[data-team]").forEach((button) => button.addEventListener("click", () => { selectedTeamId = button.dataset.team; flash = null; render(); startCamera(); }));
  }

  async function startCamera() {
    stopCamera();
    const video = root.querySelector("#mc-video");
    const canvas = root.querySelector("#mc-canvas");
    if (!video || !canvas || !window.jsQR) return;
    try {
      videoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: "environment" } }, audio: false });
      video.srcObject = videoStream;
      await video.play();
      scanning = true;
      const context = canvas.getContext("2d", { willReadFrequently: true });
      const tick = () => {
        if (!scanning) return;
        if (video.readyState >= 2) {
          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;
          context.drawImage(video, 0, 0, canvas.width, canvas.height);
          const image = context.getImageData(0, 0, canvas.width, canvas.height);
          const result = window.jsQR(image.data, image.width, image.height, { inversionAttempts: "dontInvert" });
          if (result?.data) handleToken(result.data);
        }
        raf = requestAnimationFrame(tick);
      };
      tick();
    } catch (err) {
      flash = { type: "err", text: "Không mở được camera. Hãy cấp quyền camera cho trình duyệt." };
      render();
    }
  }

  function applyTeam(progress) {
    bootstrap.allowedTeams = (bootstrap.allowedTeams || []).map((team) => String(team.teamId) === String(progress.teamId) ? progress : team);
    selectedTeamId = String(progress.teamId);
    const team = currentTeam();
    if (!team) return;
    const ratio = team.eligible ? Math.round((team.checkedIn / team.eligible) * 100) : 0;
    const strong = root.querySelector(".mc-progress strong");
    const bar = root.querySelector(".mc-bar b");
    const missing = root.querySelector(".mc-missing");
    if (strong) strong.textContent = `${team.checkedIn} / ${team.eligible}`;
    if (bar) bar.style.width = `${ratio}%`;
    if (missing) missing.innerHTML = team.missingMembers.length ? team.missingMembers.map((member) => `<li><span>${esc(member.fullName)}</span></li>`).join("") : `<li>Đã đủ người</li>`;
    const teamBtn = root.querySelector(`[data-team="${team.teamId}"] span`);
    if (teamBtn) teamBtn.textContent = `${team.checkedIn}/${team.eligible}`;
    const windowBox = root.querySelector(".mc-window");
    if (windowBox) {
      windowBox.className = `mc-window ${team.windowLocked ? "locked" : team.windowClosesAt ? "live" : "idle"}`;
      if (team.windowClosesAt) windowBox.dataset.windowCloses = team.windowClosesAt;
      else delete windowBox.dataset.windowCloses;
      const label = windowBox.querySelector("span");
      const value = windowBox.querySelector("strong");
      if (label && value) {
        if (team.windowLocked) { label.textContent = "ĐÃ KHÓA"; value.textContent = windowLockedText(); }
        else if (team.windowClosesAt) { label.textContent = windowLabel(); value.textContent = `Còn ${remainingClock(team.windowClosesAt)}`; }
        else { label.textContent = windowLabel(); value.textContent = "Mở ở lượt quét đầu tiên"; }
      }
    }
  }

  function showFlash(type, text) {
    flash = { type, text };
    let box = root.querySelector(".mc-flash");
    if (!box) {
      box = document.createElement("div");
      box.className = "mc-flash";
      box.setAttribute("role", "status");
      const camera = root.querySelector(".mc-camera");
      if (camera) camera.insertAdjacentElement("beforebegin", box);
      else return;
    }
    box.className = `mc-flash ${type}`;
    box.textContent = text;
  }

  async function handleToken(raw) {
    const token = String(raw || "").trim();
    if (!token || (token === lastToken && Date.now() - lastScanAt < 2500)) return;
    lastToken = token;
    lastScanAt = Date.now();
    const checkpoint = bootstrap?.activeCheckpoint;
    if (!checkpoint) return;
    try {
      const result = await request("checkin/scan", { method: "POST", body: JSON.stringify({ checkpointId: checkpoint.id, token }) });
      applyTeam(result.teamProgress);
      showFlash("ok", `✓ ${result.voter.fullName} · ${result.teamProgress.checkedIn}/${result.teamProgress.eligible}`);
    } catch (err) {
      const extra = err.payload || {};
      if (extra.teamProgress) applyTeam(extra.teamProgress);
      const locked = extra.code === "WINDOW_LOCKED" || extra.code === "window_locked";
      const type = locked ? "err" : extra.code === "ALREADY_CHECKED_IN" || extra.code === "already_checked_in" ? "warn" : "err";
      showFlash(type, locked ? `${windowLockedText()}. ${err.message}` : err.message);
    }
  }

  window.addEventListener("pagehide", stopCamera);
  setInterval(() => {
    root.querySelectorAll("[data-window-closes]").forEach((box) => {
      const seconds = remainingSeconds(box.dataset.windowCloses);
      const value = box.querySelector("strong");
      if (!value) return;
      if (seconds <= 0) {
        box.classList.remove("live");
        box.classList.add("locked");
        const label = box.querySelector("span");
        if (label) label.textContent = "ĐÃ KHÓA";
        value.textContent = windowLockedText();
        delete box.dataset.windowCloses;
      } else {
        value.textContent = `Còn ${remainingClock(box.dataset.windowCloses)}`;
      }
    });
  }, 1000);
  load();
})();
