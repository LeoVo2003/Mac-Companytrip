(() => {
  const root = document.getElementById("mac-art-race-app");
  if (!root) return;

  const endpoint = root.dataset.endpoint;
  const logo = root.dataset.logo;
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
  const clamp = (minimum, value, maximum) => Math.min(maximum, Math.max(minimum, value));
  const fmtScore = (score) => Number(score).toLocaleString("vi-VN", { maximumFractionDigits: 1 });

  // Vị trí thuyền (% đường đua) theo hạng từng bước; DECOY dùng cụm featured riêng.
  const RACE_POSITIONS = {
    THIRD: { 3: 88, 4: 60, 5: 45, 6: 32 },
    SECOND: { 2: 94, 3: 88, 4: 60, 5: 45, 6: 32 },
    FINAL: { 1: 100, 2: 94, 3: 88, 4: 60, 5: 45, 6: 32 },
  };
  const badgeFor = (stage, rank) => {
    const at = { 3: "THIRD", 2: "SECOND", 1: "FINAL", 4: "FINAL", 5: "FINAL", 6: "FINAL" }[rank];
    const order = { THIRD: 1, SECOND: 2, FINAL: 3 };
    return at && order[stage] >= order[at] ? rankLabel(rank) : "";
  };
  function rankLabel(rank) {
    if (Number(rank) === 1) return "QUÁN QUÂN";
    if (Number(rank) === 2) return "HẠNG NHÌ";
    if (Number(rank) === 3) return "HẠNG BA";
    return "KHUYẾN KHÍCH";
  }

  let state = null;
  let rosterSignature = "";
  let animationTimer = 0;
  let frameId = 0;
  let pollTimer = 0;
  let failedPolls = 0;
  let pyroRevision = -1;
  let pyroStop = null;
  let pyroTimer = 0;

  const BOAT_SVG = `<svg viewBox="0 0 64 44" aria-hidden="true"><path d="M6 30h52l-9 10H15z"/><path d="M31 6v24"/><path d="M31 6c11 5 16 13 18 24H31z"/><path d="M31 12c-8 4-12 10-13 18h13z"/></svg>`;

  function shell(teams) {
    root.innerHTML = `<div class="ar-shell" data-stage="idle"><canvas class="ar-pyro" aria-hidden="true"></canvas><div class="ar-sea" aria-hidden="true"><i class="ar-moon"></i></div><header class="ar-header"><div class="ar-brand"><img src="${esc(logo)}" alt="MAC Marketing"></div><div class="ar-event"><span>COMPANY TRIP - One Direction</span><strong>ĐUA THUYỀN VĂN NGHỆ</strong></div><div class="ar-connection" role="status"><i></i><span>Đang đồng bộ</span></div></header><main><div class="ar-heading" aria-live="polite"><p>KẾT QUẢ VĂN NGHỆ</p><h1>Khoảnh khắc đang đến gần</h1><span></span></div><section class="ar-track" aria-label="Đường đua thuyền của 6 đội"><div class="ar-finish" aria-hidden="true"><i></i><b>VỀ BẾN</b></div>${teams.map((team) => `<article class="ar-lane" data-team-id="${team.id}" role="group" aria-label="Team số ${team.number} ${esc(team.name)}"><div class="ar-lane-head"><strong>#${team.number} ${esc(team.name)}</strong><div class="ar-rank" hidden></div></div><div class="ar-water"><div class="ar-boat">${BOAT_SVG}<i class="ar-wake"></i></div></div><div class="ar-score"><span>—</span><small>ĐIỂM TB</small></div></article>`).join("")}</section></main><footer class="ar-footer"><span class="ar-stage-copy">Sẵn sàng công bố</span><div><i></i><span>LIVE RACE</span></div></footer></div>`;
  }

  function setConnection(connected) {
    const connection = root.querySelector(".ar-connection");
    if (!connection) return;
    connection.classList.toggle("is-offline", !connected);
    connection.querySelector("span").textContent = connected ? "Đang đồng bộ" : "Mất kết nối · đang thử lại";
  }

  function setHeading(kicker, title, description, footer) {
    const heading = root.querySelector(".ar-heading");
    if (!heading) return;
    heading.querySelector("p").textContent = kicker;
    heading.querySelector("h1").textContent = title;
    heading.querySelector("span").textContent = description;
    root.querySelector(".ar-stage-copy").textContent = footer;
  }

  function laneElement(id) {
    return root.querySelector(`[data-team-id="${String(id).replace(/"/g, "")}"]`);
  }

  function setLane(element, position, scoreText, badgeText, extraClass) {
    element.style.setProperty("--pos", `${clamp(2, position, 100)}%`);
    element.querySelector(".ar-score span").textContent = scoreText;
    const rank = element.querySelector(".ar-rank");
    rank.hidden = !badgeText;
    rank.textContent = badgeText;
    element.classList.toggle("is-champion", extraClass === "is-champion");
    element.classList.toggle("is-decoy", extraClass === "is-decoy");
  }

  function stopStageAnimation() {
    window.clearInterval(animationTimer);
    animationTimer = 0;
    window.clearTimeout(pyroTimer);
    pyroTimer = 0;
    if (frameId) cancelAnimationFrame(frameId);
    frameId = 0;
    if (pyroStop) pyroStop();
    pyroStop = null;
  }

  function renderIdle() {
    setHeading("KẾT QUẢ VĂN NGHỆ", "Khoảnh khắc đang đến gần", "", "Sẵn sàng công bố");
    state.teams.forEach((team) => setLane(laneElement(team.id), 4, "—", "", ""));
  }

  // Mở màn: 6 thuyền nhấp nhô tiến dần, số phiếu chạy giả để giữ hồi hộp.
  function renderRolling() {
    setHeading("ĐUA THUYỀN VĂN NGHỆ", "Sáu mái chèo khuấy động đêm gala", "Lượt phiếu đang dồn về bến", "Đang tung điểm trực tiếp");
    const waves = state.teams.map((team, index) => ({
      id: team.id,
      base: 14 + (index % 3) * 4,
      amplitude: 5 + (index % 2) * 3,
      period: 3000 + index * 360,
      phase: index * 0.8,
      value: 90 + index * 12,
      target: 120 + index * 15,
    }));
    const drift = (wave) => {
      wave.value += (wave.target - wave.value) * 0.012;
      if (Math.abs(wave.target - wave.value) < 3) wave.target = 90 + Math.random() * 90;
      return String(Math.round(wave.value));
    };
    if (reducedMotion.matches) {
      const gentle = () => waves.forEach((wave) => {
        const element = laneElement(wave.id);
        if (element) setLane(element, wave.base, drift(wave), "", "");
      });
      gentle();
      animationTimer = window.setInterval(gentle, 1000);
      return;
    }
    const start = performance.now();
    const step = (now) => {
      const elapsed = now - start;
      waves.forEach((wave) => {
        const element = laneElement(wave.id);
        if (!element) return;
        const position = wave.base + Math.sin((elapsed / wave.period) * Math.PI * 2 + wave.phase) * wave.amplitude;
        setLane(element, position, drift(wave), "", "");
      });
      frameId = requestAnimationFrame(step);
    };
    frameId = requestAnimationFrame(step);
  }

  function renderRace(stage) {
    const positions = RACE_POSITIONS[stage] || {};
    const featured = state.teams.filter((team) => team.featured);
    const decoyPositions = [82, 76, 70];
    state.teams.forEach((team, index) => {
      const element = laneElement(team.id);
      let position;
      let scoreText = "—";
      let badgeText = "";
      let extra = "";
      if (stage === "DECOY") {
        const featuredIndex = featured.findIndex((entry) => entry.id === team.id);
        if (featuredIndex >= 0) {
          position = decoyPositions[featuredIndex] ?? 70;
          scoreText = team.score !== null ? fmtScore(team.score) : "—";
          extra = "is-decoy";
        } else {
          position = 16 + (index % 3) * 4;
        }
      } else if (team.rank !== null) {
        position = positions[team.rank] ?? 30;
        scoreText = team.score !== null ? fmtScore(team.score) : "—";
        badgeText = badgeFor(stage, team.rank);
        if (Number(team.rank) === 1) extra = "is-champion";
      } else {
        // Hạng 1-2 chưa lộ ở THIRD/SECOND: thuyền vẫn bám giữa đường đua.
        position = 24 + (index % 2) * 5;
      }
      setLane(element, position, scoreText, badgeText, extra);
    });

    if (stage === "DECOY") {
      setHeading("CÚ LỪA", "Ba thuyền bứt phá sớm!", "Nhưng chưa chắc đây đã là thứ tự thật…", "Tín hiệu 1 · Cú lừa");
      return;
    }
    if (stage === "THIRD") {
      const team = state.teams.find((entry) => Number(entry.rank) === 3);
      setHeading("HẠNG BA", team ? team.name : "Đang chốt", team && team.score !== null ? `Xin chúc mừng ${team.name} · ${fmtScore(team.score)} điểm` : "Kết quả đang được chốt", "Tín hiệu 2 · Đã chốt");
      return;
    }
    if (stage === "SECOND") {
      const team = state.teams.find((entry) => Number(entry.rank) === 2);
      setHeading("HẠNG NHÌ", team ? team.name : "Đang chốt", team && team.score !== null ? `Xin chúc mừng ${team.name} · ${fmtScore(team.score)} điểm` : "Kết quả đang được chốt", "Tín hiệu 3 · Đã chốt");
      return;
    }
    renderFinal();
  }

  function renderFinal() {
    const champions = state.teams.filter((team) => Number(team.rank) === 1);
    const names = champions.map((team) => team.name);
    const title = names.length ? names.join(" · ") : "Kết quả chung cuộc";
    const scoreLine = champions.length === 1 && champions[0].score !== null ? ` · ${fmtScore(champions[0].score)} điểm` : "";
    const description = names.length ? `CHÚC MỪNG ${names.join(" · ").toUpperCase()}${scoreLine} · Nhà vô địch văn nghệ Company Trip` : "Đã hoàn tất công bố";
    setHeading("QUÁN QUÂN VĂN NGHỆ", title, description, "Kết quả chung cuộc");
    if (pyroRevision !== state.revision && !reducedMotion.matches) {
      pyroRevision = state.revision;
      // Đợi thuyền quán quân chạm bến xong mới bắn pháo hoa.
      pyroTimer = window.setTimeout(() => { pyroStop = startPyro(); }, 1500);
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
    stopStageAnimation();
    root.querySelector(".ar-shell").dataset.stage = state.stage.toLowerCase();
    if (state.stage === "ROLLING") renderRolling();
    else if (["DECOY", "THIRD", "SECOND", "FINAL"].includes(state.stage)) renderRace(state.stage);
    else renderIdle();
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
      if (!state) root.innerHTML = `<div class="ar-error" role="alert"><img src="${esc(logo)}" alt="MAC Marketing"><strong>Chưa kết nối được màn đua thuyền</strong><p>${esc(error.message)}</p><button type="button">Thử lại</button></div>`;
      if (failedPolls >= 2) setConnection(false);
      root.querySelector(".ar-error button")?.addEventListener("click", poll, { once: true });
    }
  }

  function startPyro() {
    const canvas = root.querySelector(".ar-pyro");
    const context = canvas.getContext("2d");
    const particles = [];
    const colors = ["#ffe9ad", "#e8c17a", "#b8823f", "#ffcf7d", "#fff6e0", "#ff8a4c", "#ff5d3a"];
    let running = true;
    const started = performance.now();
    let lastFountain = 0;
    let lastBurst = 0;
    const resize = () => {
      const ratio = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.round(innerWidth * ratio);
      canvas.height = Math.round(innerHeight * ratio);
      canvas.style.width = `${innerWidth}px`;
      canvas.style.height = `${innerHeight}px`;
      context.setTransform(ratio, 0, 0, ratio, 0, 0);
    };
    const particle = (x, y, velocityX, velocityY, color, size, life, gravity = 0.09) => particles.push({ x, y, velocityX, velocityY, color, size, life, maxLife: life, gravity, rotation: Math.random() * Math.PI });
    const fountain = (x) => {
      for (let index = 0; index < 26; index += 1) particle(x + (Math.random() - 0.5) * 22, innerHeight - 18, (Math.random() - 0.5) * 4.4, -8 - Math.random() * 8.5, colors[index % colors.length], 2 + Math.random() * 3.4, 75 + Math.random() * 45, 0.13);
    };
    const burst = (x, y) => {
      for (let index = 0; index < 132; index += 1) {
        const angle = (Math.PI * 2 * index) / 132 + Math.random() * 0.08;
        const speed = 3 + Math.random() * 8.4;
        particle(x, y, Math.cos(angle) * speed, Math.sin(angle) * speed, colors[index % colors.length], 1.6 + Math.random() * 2.8, 80 + Math.random() * 55, 0.045);
      }
    };
    const draw = (now) => {
      if (!running) return;
      context.clearRect(0, 0, innerWidth, innerHeight);
      if (now - lastFountain > 80 && now - started < 8200) {
        [0.1, 0.3, 0.5, 0.7, 0.9].forEach((position) => fountain(innerWidth * position));
        lastFountain = now;
      }
      if (now - lastBurst > 460 && now - started < 9400) {
        burst(innerWidth * (0.14 + Math.random() * 0.72), innerHeight * (0.14 + Math.random() * 0.38));
        lastBurst = now;
      }
      for (let index = particles.length - 1; index >= 0; index -= 1) {
        const item = particles[index];
        item.x += item.velocityX;
        item.y += item.velocityY;
        item.velocityY += item.gravity;
        item.velocityX *= 0.992;
        item.life -= 1;
        item.rotation += 0.08;
        if (item.life <= 0) { particles.splice(index, 1); continue; }
        context.globalAlpha = clamp(0, item.life / Math.min(30, item.maxLife), 1);
        context.fillStyle = item.color;
        context.save();
        context.translate(item.x, item.y);
        context.rotate(item.rotation);
        context.fillRect(-item.size / 2, -item.size / 2, item.size, item.size * 1.9);
        context.restore();
      }
      context.globalAlpha = 1;
      if (now - started > 12500 && !particles.length) { running = false; context.clearRect(0, 0, innerWidth, innerHeight); return; }
      requestAnimationFrame(draw);
    };
    resize();
    window.addEventListener("resize", resize);
    requestAnimationFrame(draw);
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
  window.addEventListener("beforeunload", () => { window.clearInterval(pollTimer); stopStageAnimation(); });
})();
