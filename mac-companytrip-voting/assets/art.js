(() => {
  const root = document.getElementById("mac-art-app");
  if (!root) return;

  const endpoint = root.dataset.endpoint;
  const logo = root.dataset.logo;
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
  const clamp = (minimum, value, maximum) => Math.min(maximum, Math.max(minimum, value));
  const formatScore = (score) => Number(score).toLocaleString("vi-VN", { maximumFractionDigits: 2 });

  // Tiến độ đường đua (%) theo hạng ở từng bước — vị trí theo kịch bản, không theo điểm thật.
  const RACE_X = {
    RANK65: { 6: 30, 5: 38 },
    RANK43: { 6: 30, 5: 38, 4: 50, 3: 62 },
    THIRD: { 6: 30, 5: 38, 4: 50, 3: 62 },
    TWIST: { 6: 30, 5: 38, 4: 50, 3: 62 },
    SECOND: { 6: 30, 5: 38, 4: 50, 3: 62, 2: 90 },
    FINAL: { 6: 30, 5: 38, 4: 50, 3: 62, 2: 90, 1: 100 },
  };
  const MUTED_X = 14;

  let state = null;
  let rosterSignature = "";
  let animationTimer = 0;
  let frameId = 0;
  let pollTimer = 0;
  let failedPolls = 0;
  let pyroRevision = -1;
  let pyroStop = null;
  let pyroTimer = 0;

  function boatSvg(number) {
    return `<svg viewBox="0 0 120 84" aria-hidden="true"><path class="ar-sail" d="M64 4 L64 52 L18 52 Z"/><rect x="62" y="2" width="3" height="54" rx="1.5" fill="rgba(255,227,173,.65)"/><path class="ar-hull" d="M6 56 L114 56 L96 78 L26 78 Z"/><text class="ar-num" x="60" y="72">${number}</text></svg>`;
  }

  function shell(teams) {
    root.innerHTML = `<div class="ar-shell" data-stage="idle"><canvas class="ar-pyro" aria-hidden="true"></canvas><svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs><linearGradient id="ar-hull-grad" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5a3b1f"/><stop offset="1" stop-color="#24180d"/></linearGradient><linearGradient id="ar-sail-grad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e8c17a"/><stop offset="1" stop-color="#b8823f"/></linearGradient><linearGradient id="ar-sail-gold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff2bd"/><stop offset=".5" stop-color="#ffcf7d"/><stop offset="1" stop-color="#b8823f"/></linearGradient><linearGradient id="ar-sail-silver" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e4e9ee"/><stop offset="1" stop-color="#5c6672"/></linearGradient><linearGradient id="ar-sail-copper" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e69a68"/><stop offset="1" stop-color="#5a3b1f"/></linearGradient></defs></svg><header class="ar-header"><div class="ar-brand"><img src="${esc(logo)}" alt="MAC Marketing"></div><div class="ar-event"><span>COMPANY TRIP - One Direction</span><strong>ĐUA THUYỀN VĂN NGHỆ</strong></div><div class="ar-connection" role="status"><i></i><span>Đang đồng bộ</span></div></header><main><div class="ar-heading" aria-live="polite"><p>VĂN NGHỆ · COMPANY TRIP</p><h1>Đường đua sẵn sàng</h1><span></span></div><section class="ar-sea" aria-label="Đường đua thuyền của 6 đội"><i class="ar-finish" aria-hidden="true"></i>${teams.map((team) => `<div class="ar-lane" data-team-id="${team.id}" role="group" aria-label="Team số ${team.number} ${esc(team.name)}"><span class="ar-team-name">${esc(team.name)}</span><div class="ar-boat" style="--boat-x:2%"><span class="ar-score">—</span>${boatSvg(team.number)}<span class="ar-wake"></span><span class="ar-rank" hidden></span></div></div>`).join("")}</section></main></div>`;
  }

  function setConnection(connected) {
    const connection = root.querySelector(".ar-connection");
    if (!connection) return;
    connection.classList.toggle("is-offline", !connected);
    connection.querySelector("span").textContent = connected ? "Đang đồng bộ" : "Mất kết nối · đang thử lại";
  }

  function setHeading(kicker, title, description) {
    const heading = root.querySelector(".ar-heading");
    if (!heading) return;
    heading.querySelector("p").textContent = kicker;
    heading.querySelector("h1").textContent = title;
    heading.querySelector("span").textContent = description;
  }

  function laneElement(id) {
    return root.querySelector(`[data-team-id="${String(id).replace(/"/g, "")}"]`);
  }

  function setBoat(lane, x, scoreText, badgeText, classes) {
    const boat = lane.querySelector(".ar-boat");
    boat.style.setProperty("--boat-x", `${clamp(1, x, 100)}%`);
    lane.querySelector(".ar-score").textContent = scoreText;
    const rank = lane.querySelector(".ar-rank");
    rank.hidden = !badgeText;
    rank.textContent = badgeText;
    lane.classList.remove("is-revealed", "is-muted", "is-featured", "is-champion", "is-runner-up", "is-third");
    classes.forEach((name) => lane.classList.add(name));
  }

  function rankLabel(rank) {
    if (Number(rank) === 1) return "QUÁN QUÂN";
    if (Number(rank) === 2) return "HẠNG NHÌ";
    if (Number(rank) === 3) return "HẠNG BA";
    return "KHUYẾN KHÍCH";
  }

  function podiumClass(rank) {
    if (Number(rank) === 1) return "is-champion";
    if (Number(rank) === 2) return "is-runner-up";
    if (Number(rank) === 3) return "is-third";
    return "";
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

  function teamNames(teams) {
    return teams.map((team) => team.name).join(" · ");
  }

  function renderIdle() {
    setHeading("VĂN NGHỆ · COMPANY TRIP", "Đường đua sẵn sàng", "");
    state.teams.forEach((team) => {
      const lane = laneElement(team.id);
      if (lane) setBoat(lane, 2, "—", "", []);
    });
  }

  // Xuất phát: 6 thuyền nhấp nhô tiến 8-22%, điểm tung nhẹ.
  function renderRolling() {
    setHeading("XUẤT PHÁT", "Sáu thuyền ra khơi", "Gió đã lên, đường đua mở rộng");
    const waves = state.teams.map((team, index) => ({
      id: team.id,
      base: 12 + index * 1.6,
      amplitude: 3.5 + (index % 2) * 1.5,
      period: 3000 + index * 340,
      phase: index * 0.9,
      value: 60 + index * 12,
      target: 80 + index * 15,
    }));
    const drift = (wave) => {
      wave.value += (wave.target - wave.value) * 0.012;
      if (Math.abs(wave.target - wave.value) < 3) wave.target = 60 + Math.random() * 90;
      return String(Math.round(wave.value * 10) / 10);
    };
    const step = (now) => {
      const t = now;
      waves.forEach((wave) => {
        const lane = laneElement(wave.id);
        if (!lane) return;
        const x = wave.base + Math.sin((t / wave.period) * Math.PI * 2 + wave.phase) * wave.amplitude;
        setBoat(lane, x, drift(wave), "", []);
      });
      frameId = requestAnimationFrame(step);
    };
    if (reducedMotion.matches) {
      const gentle = () => waves.forEach((wave) => {
        const lane = laneElement(wave.id);
        if (lane) setBoat(lane, wave.base, drift(wave), "", []);
      });
      gentle();
      animationTimer = window.setInterval(gentle, 1000);
      return;
    }
    frameId = requestAnimationFrame(step);
  }

  // Cú lừa: 3 thuyền cuối bảng thật vươn lên dẫn đầu 78-86%.
  function renderDecoy() {
    setHeading("AI ĐANG DẪN ĐẦU?", "Ba thuyền bứt phá ngoạn mục", "Cả đường đua nín thở theo dõi");
    state.teams.forEach((team, index) => {
      const lane = laneElement(team.id);
      if (!lane) return;
      if (team.featured) {
        setBoat(lane, 78 + index * 2.5, formatScore(team.score), "", ["is-featured"]);
      } else {
        setBoat(lane, 18 + index * 2, "•••", "", ["is-muted"]);
      }
    });
  }

  function renderLadder(stage) {
    const revealedNow = state.teams.filter((team) => team.rank !== null);
    state.teams.forEach((team, index) => {
      const lane = laneElement(team.id);
      if (!lane) return;
      if (team.rank !== null) {
        const classes = ["is-revealed"];
        const podium = podiumClass(team.rank);
        if (podium) classes.push(podium);
        const badge = stage === "TWIST" || stage === "RANK65" || stage === "RANK43" || stage === "THIRD" || stage === "SECOND" || stage === "FINAL" ? rankLabel(team.rank) : "";
        setBoat(lane, RACE_X[stage]?.[team.rank] ?? MUTED_X, formatScore(team.score), badge, classes);
      } else if (stage === "TWIST") {
        // Top 2 giấu hạng + điểm, sẽ dao động 72-88% ở vòng lặp twist.
        setBoat(lane, 76, "•••", "", []);
      } else {
        setBoat(lane, MUTED_X + index, "•••", "", ["is-muted"]);
      }
    });

    if (stage === "RANK65") {
      const teams = revealedNow.filter((team) => team.rank >= 5).sort((a, b) => b.rank - a.rank);
      setHeading("HẠNG 6 & HẠNG 5", "Ba thuyền lừa rơi xuống", teams.length ? `Xin chúc mừng ${teamNames(teams)}` : "");
      return;
    }
    if (stage === "RANK43" || stage === "THIRD") {
      const teams = revealedNow.filter((team) => team.rank === 3 || team.rank === 4).sort((a, b) => b.rank - a.rank);
      setHeading("HẠNG 4 & HẠNG 3", "Đường đua dần rõ", teams.length ? `Xin chúc mừng ${teamNames(teams)}` : "");
      return;
    }
    if (stage === "SECOND") {
      const teams = revealedNow.filter((team) => team.rank === 2);
      setHeading("HẠNG NHÌ", "Thuyền bạc đã gọi tên", teams.length ? `Xin chúc mừng ${teamNames(teams)}` : "");
      return;
    }
    if (stage === "TWIST") {
      setHeading("KHOẢNH KHẮC QUYẾT ĐỊNH", "Hai thuyền bám đuổi từng sóng", "Chỉ một thuyền chạm đích đầu tiên");
      animationTimer = window.setTimeout(startTwist, 1100);
      return;
    }
    renderFinal();
  }

  // Top 2 bám đuổi đối pha 72-88% + tung điểm.
  function startTwist() {
    if (reducedMotion.matches) return;
    const ids = (state.topTwo && state.topTwo.length ? state.topTwo : state.teams.filter((team) => team.rank !== null && team.rank <= 2).map((team) => team.id));
    const waves = ids.map((id, index) => ({ id, phase: index * Math.PI, value: 90 + index * 20, target: 110 + index * 25 }));
    const start = performance.now();
    const step = (now) => {
      const seconds = (now - start) / 1000;
      waves.forEach((wave) => {
        const lane = laneElement(wave.id);
        if (!lane) return;
        const x = 80 + Math.sin(seconds * 1.5 + wave.phase) * 8;
        wave.value += (wave.target - wave.value) * 0.02;
        if (Math.abs(wave.target - wave.value) < 5) wave.target = 90 + Math.random() * 60;
        setBoat(lane, x, String(Math.round(wave.value * 10) / 10), "", []);
      });
      frameId = requestAnimationFrame(step);
    };
    frameId = requestAnimationFrame(step);
  }

  function renderFinal() {
    const champions = state.teams.filter((team) => Number(team.rank) === 1);
    const names = champions.map((team) => team.name);
    const title = names.length ? names.join(" · ") : "Kết quả chung cuộc";
    const scoreLine = champions.length === 1 ? ` · ${formatScore(champions[0].score)} điểm TB` : "";
    setHeading("QUÁN QUÂN VĂN NGHỆ", title, names.length ? `CHÚC MỪNG ${names.join(" · ").toUpperCase()}${scoreLine} · Nhà vô địch văn nghệ Company Trip` : "Đã hoàn tất công bố");
    if (pyroRevision !== state.revision && !reducedMotion.matches) {
      pyroRevision = state.revision;
      // Pháo hoa chờ 3s để thuyền quán quân băng vạch đích trọn vẹn rồi mới bắn.
      pyroTimer = window.setTimeout(() => { pyroStop = startPyro(); }, 3000);
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
    else if (state.stage === "DECOY") renderDecoy();
    else if (["RANK65", "RANK43", "THIRD", "SECOND", "TWIST", "FINAL"].includes(state.stage)) renderLadder(state.stage);
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
    let frame = 0;
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
      frame = requestAnimationFrame(draw);
    };
    resize();
    window.addEventListener("resize", resize);
    frame = requestAnimationFrame(draw);
    return () => {
      running = false;
      cancelAnimationFrame(frame);
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
