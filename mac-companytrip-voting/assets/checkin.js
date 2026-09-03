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
  const backLink = () => `<a class="mc-back" href="${esc(config.dashboard)}">← Quay lại dashboard</a>`;
  let bootstrap = null;
  let openTeamId = null;
  let windowMinutes = 15;
  let flash = null;
  let scanning = false;
  let lastToken = "";
  let lastScanAt = 0;
  let videoStream = null;
  let raf = 0;
  let pollTimer = 0;

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
      render();
      if (bootstrap.activeCheckpoint) await startCamera();
      startPolling();
    } catch (err) {
      root.innerHTML = `<main class="mc-shell"><section class="mc-card"><p class="mc-kicker">CHECK-IN</p><h1>Không mở được camera quét</h1><p class="mc-meta">${esc(err.message)}</p></section></main>`;
    }
  }

  // Đồng bộ trạng thái xe / tiến độ cho nhiều line quét mà không cần reload.
  function startPolling() {
    if (pollTimer) window.clearInterval(pollTimer);
    pollTimer = window.setInterval(async () => {
      try {
        const next = await request("checkin/bootstrap");
        const hadCheckpoint = !!bootstrap?.activeCheckpoint;
        bootstrap = next;
        windowMinutes = Number(bootstrap.windowMinutes) > 0 ? Number(bootstrap.windowMinutes) : 15;
        if (!hadCheckpoint && bootstrap.activeCheckpoint) {
          render();
          await startCamera();
        } else {
          updateLive();
        }
      } catch {
        // Giữ nguyên màn hình khi mạng lỗi thoáng qua.
      }
    }, 2500);
  }

  function busChip() {
    if (!bootstrap?.busAssignmentEnabled) return "";
    const bus = bootstrap.activeBus;
    if (!bus) return `<div class="mc-bus-chip idle"><span>PHÂN XE</span><strong>Chưa có xe mở — check-in vẫn ghi nhận</strong></div>`;
    return `<div class="mc-bus-chip live"><span>ĐANG PHÂN</span><strong>${esc(bus.name)} · ${bus.totalCount ?? bus.employeeCount} người</strong></div>`;
  }

  function memberRows(team) {
    const rows = [
      ...(team.missingMembers || []).map((member) => ({ ...member, state: "missing" })),
      ...(team.checkedInMembers || []).map((member) => ({ ...member, state: "done" })),
      ...(team.exemptedMembers || []).map((member) => ({ ...member, state: "exempt" })),
    ];
    const label = { missing: "Chưa quét", done: "✓ Đã quét", exempt: "Miễn" };
    return rows.map((member) => `<li class="${member.state}"><span>${esc(member.fullName)}</span><small>${label[member.state]}</small></li>`).join("") || `<li class="missing"><span>Chưa có thành viên</span><small></small></li>`;
  }

  function accordion() {
    const teams = bootstrap?.allowedTeams || [];
    return `<section class="mc-card mc-list-card"><p class="mc-kicker">DANH SÁCH CHECK-IN</p><ul class="mc-accordion">${teams.map((team) => {
      const open = String(team.teamId) === String(openTeamId);
      return `<li class="${open ? "open" : ""}"><button type="button" class="mc-acc-head" data-acc-team="${team.teamId}" aria-expanded="${open}"><span class="mc-acc-name">#${team.teamNumber} ${esc(team.teamName)}</span><strong>${team.checkedIn}/${team.eligible}</strong></button>${open ? `<div class="mc-acc-body"><p class="mc-acc-sub">CHƯA CHECK-IN · ${team.missingMembers?.length || 0}</p><ul class="mc-members">${memberRows(team)}</ul></div>` : ""}</li>`;
    }).join("") || `<li><p class="mc-meta">Chưa có trạm mở.</p></li>`}</ul></section>`;
  }

  function render() {
    const checkpoint = bootstrap?.activeCheckpoint;
    if (!checkpoint) {
      stopCamera();
      root.innerHTML = `<main class="mc-shell"><header class="mc-top"><img src="${esc(config.logo)}" alt="MAC Marketing">${backLink()}</header><section class="mc-card mc-empty"><p class="mc-kicker">CHECK-IN</p><h1>Chưa có trạm check-in nào đang mở</h1><p class="mc-meta">Đợi admin mở một trạm check-in rồi tải lại trang.</p></section></main>`;
      return;
    }
    root.innerHTML = `<main class="mc-shell"><header class="mc-top"><img src="${esc(config.logo)}" alt="MAC Marketing">${backLink()}</header><section class="mc-card"><p class="mc-kicker">CHECK-IN · TRẠM ${checkpoint.id}</p><h1>${esc(checkpoint.name)}</h1><p class="mc-meta">Quét QR mọi team — hệ thống tự nhận diện đội.</p>${busChip()}<div class="mc-flash-slot">${flash ? `<div class="mc-flash ${flash.type}" role="status">${esc(flash.text)}</div>` : ""}</div><div class="mc-camera"><video id="mc-video" playsinline muted autoplay></video><canvas id="mc-canvas"></canvas><div class="mc-frame" aria-hidden="true"></div></div></section>${accordion()}</main>`;
    root.querySelectorAll("[data-acc-team]").forEach((button) => button.addEventListener("click", () => {
      const id = button.dataset.accTeam;
      openTeamId = String(openTeamId) === String(id) ? null : id;
      refreshAccordion();
    }));
  }

  function refreshAccordion() {
    const slot = root.querySelector(".mc-list-card");
    if (!slot) return;
    slot.outerHTML = accordion();
    root.querySelectorAll("[data-acc-team]").forEach((button) => button.addEventListener("click", () => {
      const id = button.dataset.accTeam;
      openTeamId = String(openTeamId) === String(id) ? null : id;
      refreshAccordion();
    }));
  }

  // Cập nhật chip xe + số liệu accordion sau mỗi poll/scan mà không đụng camera.
  function updateLive() {
    const chipSlot = root.querySelector(".mc-bus-chip");
    const chipHtml = busChip();
    if (chipSlot && chipHtml) chipSlot.outerHTML = chipHtml;
    else if (!chipSlot && chipHtml) root.querySelector(".mc-meta")?.insertAdjacentHTML("afterend", chipHtml);
    refreshAccordion();
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
    } catch {
      showFlash("err", "Không mở được camera. Hãy cấp quyền camera cho trình duyệt.");
    }
  }

  function applyTeam(progress) {
    if (!bootstrap?.allowedTeams) return;
    bootstrap.allowedTeams = bootstrap.allowedTeams.map((team) => String(team.teamId) === String(progress.teamId) ? progress : team);
    updateLive();
  }

  function showFlash(type, text) {
    flash = { type, text };
    const slot = root.querySelector(".mc-flash-slot");
    if (slot) slot.innerHTML = `<div class="mc-flash ${type}" role="status">${esc(text)}</div>`;
  }

  async function handleToken(raw) {
    let token = String(raw || "").trim();
    const marker = "/company-trip/q/";
    const markerAt = token.lastIndexOf(marker);
    if (markerAt !== -1) token = token.slice(markerAt + marker.length).split(/[?#]/)[0].trim();
    if (!token || (token === lastToken && Date.now() - lastScanAt < 2500)) return;
    lastToken = token;
    lastScanAt = Date.now();
    const checkpoint = bootstrap?.activeCheckpoint;
    if (!checkpoint) return;
    try {
      const result = await request("checkin/scan", { method: "POST", body: JSON.stringify({ checkpointId: checkpoint.id, token }) });
      applyTeam(result.teamProgress);
      const bus = result.busAssignment;
      const partyLine = Number(bus?.partySize || 0) > 1 ? ` · nhóm ${bus.partySize} người` : "";
      const busLine = bus?.assigned ? ` → ${bus.busName}${partyLine}` : bootstrap?.busAssignmentEnabled ? (bus?.reason === "NO_ROOM_FOR_PARTY" ? ` · KHÔNG ĐỦ CHỖ CHO NHÓM ${bus.partySize} NGƯỜI` : " · CHƯA PHÂN XE — gặp Điều phối") : "";
      showFlash("ok", `✓ ${result.voter.fullName} · #${result.voter.teamNumber} ${result.voter.teamName} · ${result.teamProgress.checkedIn}/${result.teamProgress.eligible}${busLine}`);
    } catch (err) {
      const extra = err.payload || {};
      if (extra.teamProgress) applyTeam(extra.teamProgress);
      const locked = extra.code === "WINDOW_LOCKED" || extra.code === "window_locked";
      const dup = extra.code === "ALREADY_CHECKED_IN" || extra.code === "already_checked_in";
      const type = locked ? "err" : dup ? "warn" : "err";
      const bus = extra.busAssignment;
      const busLine = bus?.assigned ? ` · ${bus.busName}` : "";
      showFlash(type, (locked ? `Cửa sổ ${windowMinutes} phút đã hết. ${err.message}` : err.message) + busLine);
    }
  }

  window.addEventListener("pagehide", () => { stopCamera(); window.clearInterval(pollTimer); });
  load();
})();
