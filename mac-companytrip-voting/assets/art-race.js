(() => {
  const root = document.getElementById("mac-art-race-app");
  if (!root) return;

  const endpoint = root.dataset.endpoint;
  const logo = root.dataset.logo;
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
  const fmtScore = (score) => Number(score).toLocaleString("vi-VN", { maximumFractionDigits: 1 });
  const rankLabel = (rank) => ({ 1: "QUÁN QUÂN", 2: "HẠNG NHÌ", 3: "HẠNG BA" }[Number(rank)] || `HẠNG ${rank}`);

  let state = null;
  let rosterSignature = "";
  let pollTimer = 0;
  let failedPolls = 0;
  let pyroRevision = -1;
  let pyroStop = null;
  let pyroTimer = 0;
  let spotlightStartTimer = 0;
  let spotlightTimer = 0;
  let spotlightIndex = -1;

  function shell(teams) {
    root.innerHTML = `<div class="ar-shell" data-stage="idle">
      <canvas class="ar-pyro" aria-hidden="true"></canvas>
      <div class="ar-stage-world" aria-hidden="true"><i class="ar-haze"></i><i class="ar-runway"></i><i class="ar-edge ar-edge-left"></i><i class="ar-edge ar-edge-right"></i></div>
      <div class="ar-curtain" aria-hidden="true"><i class="ar-curtain-left"></i><i class="ar-curtain-right"></i><span></span></div>
      <header class="ar-header">
        <div class="ar-brand"><img src="${esc(logo)}" alt="MAC Marketing"></div>
        <div class="ar-event"><span>COMPANY TRIP · KẾT QUẢ VĂN NGHỆ</span><strong>ONE DIRECTION</strong></div>
        <div class="ar-connection" role="status"><i></i><span>Đang đồng bộ</span></div>
      </header>
      <main>
        <div class="ar-title" aria-live="polite">
          <p>ONE DIRECTION</p>
          <h1>THE SPOTLIGHT</h1>
          <span>Chỉ một hướng · chỉ một khoảnh khắc</span>
        </div>
        <section class="ar-podiums" aria-label="Sáu vị trí công bố kết quả văn nghệ">
          ${teams.map((team, index) => `<article class="ar-team is-pending" style="--slot:${index}" data-team-id="${team.id}" role="group" aria-label="Đội số ${team.number} ${esc(team.name)}">
            <div class="ar-beam" aria-hidden="true"></div>
            <div class="ar-team-copy">
              <span class="ar-team-rank">CHỜ CÔNG BỐ</span>
              <strong>${esc(team.name)}</strong>
              <b>—</b>
              <small>ĐIỂM TRUNG BÌNH</small>
            </div>
            <div class="ar-marker">${esc(team.name)}</div>
            <div class="ar-podium" aria-hidden="true"><i></i><span></span></div>
          </article>`).join("")}
        </section>
      </main>
      <footer class="ar-footer"><span class="ar-stage-copy">Sẵn sàng công bố</span><div><i></i><span>LIVE · THE SPOTLIGHT</span></div></footer>
    </div>`;
  }

  function setConnection(connected) {
    const connection = root.querySelector(".ar-connection");
    if (!connection) return;
    connection.classList.toggle("is-offline", !connected);
    connection.querySelector("span").textContent = connected ? "Đang đồng bộ" : "Mất kết nối · đang thử lại";
  }

  function setTitle(kicker, title, description, footer) {
    const heading = root.querySelector(".ar-title");
    if (!heading) return;
    heading.querySelector("p").textContent = kicker;
    heading.querySelector("h1").textContent = title;
    heading.querySelector("span").textContent = description;
    root.querySelector(".ar-stage-copy").textContent = footer;
  }

  function teamElement(id) {
    return root.querySelector(`[data-team-id="${String(id).replace(/"/g, "")}"]`);
  }

  function resetEffects() {
    window.clearTimeout(pyroTimer);
    window.clearTimeout(spotlightStartTimer);
    window.clearInterval(spotlightTimer);
    pyroTimer = 0;
    spotlightStartTimer = 0;
    spotlightTimer = 0;
    spotlightIndex = -1;
    if (pyroStop) pyroStop();
    pyroStop = null;
  }

  function renderIdle() {
    setTitle("ONE DIRECTION", "THE SPOTLIGHT", "Chỉ một hướng · chỉ một khoảnh khắc", "Sẵn sàng công bố");
    state.teams.forEach((team) => {
      const element = teamElement(team.id);
      element.className = "ar-team is-pending";
      element.querySelector(".ar-team-rank").textContent = "CHỜ CÔNG BỐ";
      element.querySelector(".ar-team-copy b").textContent = "—";
    });
  }

  function renderRolling() {
    setTitle("ONE DIRECTION", "THE SPOTLIGHT", "Sân khấu đang mở · spotlight sẽ bắt đầu sau 5 giây", "Đang mở màn");
    state.teams.forEach((team) => {
      const element = teamElement(team.id);
      element.className = "ar-team is-pending";
      element.querySelector(".ar-team-rank").textContent = "ĐANG CHỜ";
      element.querySelector(".ar-team-copy b").textContent = "—";
    });
    const elapsed = Math.max(0, Number(state.serverTime || Date.now()) - Number(state.changedAt || 0));
    spotlightStartTimer = window.setTimeout(startSpotlightSearch, Math.max(0, 5000 - elapsed));
  }

  function startSpotlightSearch() {
    if (!state || state.stage !== "ROLLING") return;
    const teams = state.teams || [];
    if (!teams.length) return;
    setTitle("SPOTLIGHT ĐANG TÌM KIẾM", "Ai sẽ được gọi tên?", "Ánh sáng đang di chuyển giữa sáu đội", "Chờ tín hiệu công bố hạng 6");
    if (reducedMotion.matches) return;
    const jump = () => {
      root.querySelectorAll(".ar-team.is-searching").forEach((element) => element.classList.remove("is-searching"));
      let next = Math.floor(Math.random() * teams.length);
      if (teams.length > 1 && next === spotlightIndex) next = (next + 1 + Math.floor(Math.random() * (teams.length - 1))) % teams.length;
      spotlightIndex = next;
      teamElement(teams[next].id)?.classList.add("is-searching");
    };
    jump();
    spotlightTimer = window.setInterval(jump, 620);
  }

  function renderReveal() {
    const current = state.teams.find((team) => team.current) || null;
    state.teams.forEach((team) => {
      const element = teamElement(team.id);
      const revealed = Boolean(team.revealed || team.rank !== null);
      element.className = `ar-team ${revealed ? "is-revealed" : "is-pending"}${team.current ? " is-current" : ""}${Number(team.rank) === 1 ? " is-champion" : ""}`;
      element.querySelector(".ar-team-rank").textContent = revealed ? rankLabel(team.rank) : "CHỜ CÔNG BỐ";
      element.querySelector(".ar-team-copy b").textContent = revealed && team.score !== null ? fmtScore(team.score) : "—";
    });

    if (!current) {
      setTitle("KẾT QUẢ VĂN NGHỆ", "Đang chốt kết quả", "Spotlight sẽ khóa vào đội tiếp theo", "Chờ tín hiệu MC");
      return;
    }

    const champion = Number(current.rank) === 1;
    setTitle(
      champion ? "QUÁN QUÂN VĂN NGHỆ" : rankLabel(current.rank),
      current.name,
      current.score !== null ? `${fmtScore(current.score)} điểm · Spotlight thuộc về ${current.name}` : "Kết quả đã được công bố",
      champion ? "Kết quả chung cuộc" : `Đã công bố ${rankLabel(current.rank).toLowerCase()}`
    );

    if (champion && pyroRevision !== state.revision && !reducedMotion.matches) {
      pyroRevision = state.revision;
      pyroTimer = window.setTimeout(() => { pyroStop = startPyro(); }, 850);
    }
  }

  function applyStage(nextState, force = false) {
    const signature = nextState.teams.map((team) => `${team.id}:${team.number}:${team.name}`).join("|");
    if (signature !== rosterSignature) {
      rosterSignature = signature;
      shell(nextState.teams);
      force = true;
    }
    const changed = force || !state || nextState.revision !== state.revision || nextState.stage !== state.stage;
    state = nextState;
    setConnection(true);
    if (!changed) return;
    resetEffects();
    root.querySelector(".ar-shell").dataset.stage = state.stage.toLowerCase();
    if (state.stage === "IDLE") renderIdle();
    else if (state.stage === "ROLLING") renderRolling();
    else renderReveal();
  }

  async function poll() {
    try {
      const response = await fetch(`${endpoint}${endpoint.includes("?") ? "&" : "?"}_=${Date.now()}`, { credentials: "same-origin", cache: "no-store" });
      if (!response.ok) throw new Error("Không tải được trạng thái công bố.");
      const nextState = await response.json();
      if (!Array.isArray(nextState.teams) || !nextState.teams.length) throw new Error("Chưa có đội trong bảng văn nghệ.");
      failedPolls = 0;
      applyStage(nextState);
    } catch (error) {
      failedPolls += 1;
      if (!state) root.innerHTML = `<div class="ar-error" role="alert"><img src="${esc(logo)}" alt="MAC Marketing"><strong>Chưa kết nối được The Spotlight</strong><p>${esc(error.message)}</p><button type="button">Thử lại</button></div>`;
      if (failedPolls >= 2) setConnection(false);
      root.querySelector(".ar-error button")?.addEventListener("click", poll, { once: true });
    }
  }

  function startPyro() {
    const canvas = root.querySelector(".ar-pyro");
    const context = canvas.getContext("2d");
    const particles = [];
    const colors = ["#fff4ce", "#efc878", "#ff6a2c", "#e31e24", "#ffffff"];
    let running = true;
    const started = performance.now();
    const resize = () => {
      const ratio = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.round(innerWidth * ratio);
      canvas.height = Math.round(innerHeight * ratio);
      canvas.style.width = `${innerWidth}px`;
      canvas.style.height = `${innerHeight}px`;
      context.setTransform(ratio, 0, 0, ratio, 0, 0);
    };
    const burst = (x, y) => {
      for (let index = 0; index < 110; index += 1) {
        const angle = (Math.PI * 2 * index) / 110 + Math.random() * 0.08;
        const speed = 2.5 + Math.random() * 7;
        particles.push({ x, y, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed, color: colors[index % colors.length], life: 70 + Math.random() * 50, size: 1.5 + Math.random() * 3 });
      }
    };
    resize();
    burst(innerWidth * 0.25, innerHeight * 0.34);
    burst(innerWidth * 0.75, innerHeight * 0.3);
    window.setTimeout(() => running && burst(innerWidth * 0.5, innerHeight * 0.2), 650);
    const draw = () => {
      if (!running) return;
      context.clearRect(0, 0, innerWidth, innerHeight);
      for (let index = particles.length - 1; index >= 0; index -= 1) {
        const particle = particles[index];
        particle.x += particle.vx;
        particle.y += particle.vy;
        particle.vy += 0.065;
        particle.vx *= 0.993;
        particle.life -= 1;
        if (particle.life <= 0) { particles.splice(index, 1); continue; }
        context.globalAlpha = Math.min(1, particle.life / 24);
        context.fillStyle = particle.color;
        context.fillRect(particle.x, particle.y, particle.size, particle.size * 1.8);
      }
      context.globalAlpha = 1;
      if (performance.now() - started > 9000 && !particles.length) return;
      requestAnimationFrame(draw);
    };
    requestAnimationFrame(draw);
    window.addEventListener("resize", resize);
    return () => {
      running = false;
      window.removeEventListener("resize", resize);
      context.clearRect(0, 0, innerWidth, innerHeight);
    };
  }

  reducedMotion.addEventListener?.("change", () => state && applyStage(state, true));
  document.addEventListener("visibilitychange", () => { if (!document.hidden) poll(); });
  poll();
  pollTimer = window.setInterval(() => { if (!document.hidden) poll(); }, 900);
  window.addEventListener("beforeunload", () => { window.clearInterval(pollTimer); resetEffects(); });
})();
