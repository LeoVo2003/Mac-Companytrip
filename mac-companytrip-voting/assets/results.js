(() => {
  const root = document.getElementById("mac-results-app");
  if (!root) return;

  const endpoint = root.dataset.endpoint;
  const logo = root.dataset.logo;
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
  const clamp = (minimum, value, maximum) => Math.min(maximum, Math.max(minimum, value));
  const formatTotal = (score) => Number(score).toLocaleString("vi-VN");
  // Thang 10 ô: mỗi ô = 10% chiều cao cột. Quán quân chạm ~82% (8,2 ô) cho vừa khung.
  const CELL = 10;
  const LADDER_LEVELS = {
    RANK65: { 6: 3, 5: 3 },
    RANK43: { 6: 4, 5: 4, 4: 4, 3: 5 },
    RANK12: { 6: 4, 5: 4, 4: 4, 3: 5, 2: 6, 1: 6 },
    TWIST: { 6: 4, 5: 4, 4: 4, 3: 5, 2: 6, 1: 6 },
    FINAL: { 6: 4, 5: 4, 4: 4, 3: 5, 2: 6, 1: 8.2 },
  };
  // Badge hạng xuất hiện trễ một nhịp: hạng lộ ra ở bước này thì bước kế tiếp mới gắn badge,
  // riêng FINAL gắn đủ badge để khép màn. Thứ tự bước và bước lộ từng cặp hạng:
  const STAGE_ORDER = { RANK65: 1, RANK43: 2, RANK12: 3, TWIST: 4, FINAL: 5 };
  const RANK_REVEALED_AT = { 6: 1, 5: 1, 4: 2, 3: 2, 2: 5, 1: 5 };
  const badgeFor = (stage, rank) => stage === "FINAL" || (STAGE_ORDER[stage] ?? 0) > (RANK_REVEALED_AT[rank] ?? 9);

  let state = null;
  let rosterSignature = "";
  let animationTimer = 0;
  let frameId = 0;
  let pollTimer = 0;
  let failedPolls = 0;
  let pyroRevision = -1;
  let pyroStop = null;

  function shell(teams) {
    root.innerHTML = `<div class="mr-shell" data-stage="idle"><canvas class="mr-pyro" aria-hidden="true"></canvas><div class="mr-seascape" aria-hidden="true"><i class="mr-sun"></i><i class="mr-wake"></i></div><div class="mr-compass" aria-hidden="true"><svg viewBox="0 0 400 400" role="presentation"><circle cx="200" cy="200" r="176"/><circle cx="200" cy="200" r="154"/><circle cx="200" cy="200" r="118"/><path class="mr-compass-cross" d="M200 24v352M24 200h352M76 76l248 248M324 76 76 324"/><path class="mr-compass-rose" d="M200 48l22 130 130 22-130 22-22 130-22-130-130-22 130-22z"/><path class="mr-compass-minor" d="M200 84l12 104 104 12-104 12-12 104-12-104-104-12 104-12z"/><g class="mr-compass-labels"><text x="200" y="18">N</text><text x="386" y="206">E</text><text x="200" y="397">S</text><text x="14" y="206">W</text><text x="327" y="67">NE</text><text x="337" y="345">SE</text><text x="63" y="345">SW</text><text x="62" y="67">NW</text></g></svg><i class="mr-needle"></i></div><header class="mr-header"><div class="mr-brand-lockup"><img src="${esc(logo)}" alt="MAC Marketing"></div><div class="mr-event"><span>COMPANY TRIP - One Direction</span><strong>TỔNG KẾT COMPANY TRIP</strong></div><div class="mr-connection" role="status"><i></i><span>Đang đồng bộ</span></div></header><main><div class="mr-heading" aria-live="polite"><p>KẾT QUẢ CHUNG CUỘC</p><h1>Khoảnh khắc đang đến gần</h1><span></span></div><section class="mr-chart" aria-label="Biểu đồ tổng điểm của 6 đội">${teams.map((team) => `<article class="mr-team" data-team-id="${team.id}" role="group" aria-label="Team số ${team.number} ${esc(team.name)}"><div class="mr-score"><span>—</span><small>ĐIỂM</small></div><div class="mr-column"><div class="mr-rank" hidden></div><div class="mr-bar"><span></span><i></i></div><div class="mr-base"></div></div><div class="mr-team-name"><strong>${esc(team.name)}</strong></div></article>`).join("")}</section></main><footer class="mr-footer"><span class="mr-stage-copy">Sẵn sàng công bố</span><div><i></i><span>LIVE RESULT</span></div></footer></div>`;
  }

  function setConnection(connected) {
    const connection = root.querySelector(".mr-connection");
    if (!connection) return;
    connection.classList.toggle("is-offline", !connected);
    connection.querySelector("span").textContent = connected ? "Đang đồng bộ" : "Mất kết nối · đang thử lại";
  }

  function setHeading(kicker, title, description, footer) {
    const heading = root.querySelector(".mr-heading");
    if (!heading) return;
    heading.querySelector("p").textContent = kicker;
    heading.querySelector("h1").textContent = title;
    heading.querySelector("span").textContent = description;
    root.querySelector(".mr-stage-copy").textContent = footer;
  }

  function teamElement(id) {
    return root.querySelector(`[data-team-id="${String(id).replace(/"/g, "")}"]`);
  }

  function clearTeamState(element) {
    element.classList.remove("is-featured", "is-muted", "is-revealed", "is-finalist", "is-champion", "is-runner-up", "is-third");
    const rank = element.querySelector(".mr-rank");
    rank.hidden = true;
    rank.textContent = "";
  }

  function setBar(element, level, scoreText) {
    element.style.setProperty("--bar-level", `${clamp(8, level, 100)}%`);
    element.querySelector(".mr-score span").textContent = scoreText;
  }

  // Admin có thể ẩn điểm trên màn chiếu: che số bằng ••• cho tới khi mở lại.
  function displayScore(text) {
    return state?.scoresHidden ? "•••" : text;
  }

  function stopStageAnimation() {
    window.clearInterval(animationTimer);
    animationTimer = 0;
    if (frameId) cancelAnimationFrame(frameId);
    frameId = 0;
    if (pyroStop) pyroStop();
    pyroStop = null;
  }

  function renderIdle() {
    setHeading("KẾT QUẢ CHUNG CUỘC", "Khoảnh khắc đang đến gần", "", "Sẵn sàng công bố");
    state.teams.forEach((team, index) => {
      const element = teamElement(team.id);
      clearTeamState(element);
      setBar(element, 13 + (index % 2) * 2, "—");
    });
  }

  // Step 1: tung điểm nhưng lượn sóng nhẹ nhàng, không giật. Cột dâng cao dần để có đà trước khi lộ hạng.
  function renderRolling() {
    setHeading("TỔNG ĐIỂM ĐANG CHUYỂN ĐỘNG", "Ai sẽ chạm đỉnh?", "6 đội · 4 chặng đường · 1 ngôi vương duy nhất", "Đang tung điểm trực tiếp");
    const waves = state.teams.map((team, index) => ({
      id: team.id,
      base: 26 + (index % 3) * 3,
      amplitude: 6.5 + (index % 2) * 2.5,
      period: 3200 + index * 380,
      phase: index * 0.9,
      value: 430 + index * 25,
      target: 520 + index * 30,
    }));
    const drift = (wave) => {
      wave.value += (wave.target - wave.value) * 0.012;
      if (Math.abs(wave.target - wave.value) < 4) wave.target = 430 + Math.random() * 320;
      return String(Math.round(wave.value));
    };
    if (reducedMotion.matches) {
      const gentle = () => waves.forEach((wave) => {
        const element = teamElement(wave.id);
        if (element) setBar(element, wave.base, displayScore(drift(wave)));
      });
      gentle();
      animationTimer = window.setInterval(gentle, 1000);
      return;
    }
    const start = performance.now();
    const step = (now) => {
      const elapsed = now - start;
      waves.forEach((wave) => {
        const element = teamElement(wave.id);
        if (!element) return;
        clearTeamState(element);
        const level = wave.base + Math.sin((elapsed / wave.period) * Math.PI * 2 + wave.phase) * wave.amplitude;
        setBar(element, level, displayScore(drift(wave)));
      });
      frameId = requestAnimationFrame(step);
    };
    frameId = requestAnimationFrame(step);
  }

  function rankLabel(rank) {
    if (Number(rank) === 1) return "QUÁN QUÂN";
    if (Number(rank) === 2) return "HẠNG NHÌ";
    if (Number(rank) === 3) return "HẠNG BA";
    return `HẠNG ${rank}`;
  }

  function podiumClass(rank) {
    if (Number(rank) === 1) return "is-champion";
    if (Number(rank) === 2) return "is-runner-up";
    if (Number(rank) === 3) return "is-third";
    return "";
  }

  function revealedAtRank(rank) {
    return state.teams.filter((team) => Number(team.rank) === rank && team.score !== null);
  }

  function teamNames(teams) {
    return teams.map((team) => team.name).join(" · ");
  }

  // Step 5: hai đội dẫn đầu lên xuống đối pha liên tục để giữ cú twist.
  function startTwist() {
    if (reducedMotion.matches) return;
    const start = performance.now();
    const step = (now) => {
      const seconds = (now - start) / 1000;
      (state.topTwo || []).forEach((id, index) => {
        const element = teamElement(id);
        if (!element) return;
        const level = 60 + Math.sin(seconds * 1.6 + index * Math.PI) * 11;
        element.style.setProperty("--bar-level", `${level}%`);
      });
      frameId = requestAnimationFrame(step);
    };
    frameId = requestAnimationFrame(step);
  }

  function renderFinal() {
    const champions = revealedAtRank(1);
    const champion = champions[0];
    const name = champion ? champion.name : "Kết quả chung cuộc";
    const scoreLine = champion && !state.scoresHidden ? ` · ${formatTotal(champion.score)} điểm` : "";
    const description = champion ? `CHÚC MỪNG ${name.toUpperCase()}${scoreLine} · Nhà vô địch Company Trip` : "Đã hoàn tất công bố";
    setHeading("QUÁN QUÂN COMPANY TRIP", name, description, "Kết quả chung cuộc");
    if (pyroRevision !== state.revision && !reducedMotion.matches) {
      pyroRevision = state.revision;
      pyroStop = startPyro();
    }
  }

  // Step 2-6: thang ô cố định theo thứ hạng; CSS tự lo hiệu ứng trồi lên mượt mà.
  // Chỉ render lại đội nào đổi trạng thái để hạng đã lộ đứng yên (không nhấp nháy lại
  // màu, không pop lại badge) khi hạng mới bước lên.
  function renderLadder(stage) {
    state.teams.forEach((team) => {
      const element = teamElement(team.id);
      const classes = [];
      let level;
      let scoreText;
      let badgeText = "";
      if (team.rank !== null) {
        const cells = LADDER_LEVELS[stage][team.rank] ?? 4;
        classes.push("is-revealed");
        const podium = podiumClass(team.rank);
        if (podium) classes.push("is-finalist", podium);
        level = cells * CELL;
        scoreText = displayScore(formatTotal(team.score));
        if (badgeFor(stage, team.rank)) badgeText = rankLabel(team.rank);
      } else if (stage === "RANK12" || stage === "TWIST") {
        // Top 2 đã leo lên 6 ô nhưng giấu hạng + điểm thật để giữ cú twist.
        level = 6 * CELL;
        scoreText = "•••";
      } else {
        classes.push("is-muted");
        level = 14;
        scoreText = "•••";
      }
      const snapshot = `${classes.join(" ")}|${level}|${scoreText}|${badgeText}`;
      if (element.dataset.snapshot === snapshot) return;
      element.dataset.snapshot = snapshot;
      clearTeamState(element);
      classes.forEach((name) => element.classList.add(name));
      setBar(element, level, scoreText);
      const rank = element.querySelector(".mr-rank");
      rank.hidden = !badgeText;
      rank.textContent = badgeText;
    });

    if (stage === "RANK65") {
      const teams = revealedAtRank(5).concat(revealedAtRank(6));
      setHeading("HẠNG 6 & HẠNG 5", "Những cái tên đầu tiên lộ diện", teams.length ? `Xin chúc mừng ${teamNames(teams)}` : "Kết quả đang được chốt", "Tín hiệu 1 · Đã chốt");
      return;
    }
    if (stage === "RANK43") {
      const teams = revealedAtRank(3).concat(revealedAtRank(4));
      setHeading("HẠNG 4 & HẠNG 3", "Top giữa đã gọi tên ai?", teams.length ? `Xin chúc mừng ${teamNames(teams)}` : "Kết quả đang được chốt", "Tín hiệu 2 · Đã chốt");
      return;
    }
    if (stage === "RANK12") {
      setHeading("HẠNG 2 & HẠNG 1", "Hai cái tên cuối cùng bước lên", "Cả hai cùng chạm mốc 6 ô · Ngôi vương chỉ có một", "Tín hiệu 3 · Đã chốt");
      return;
    }
    if (stage === "TWIST") {
      setHeading("KHOẢNH KHẮC QUYẾT ĐỊNH", "Ai sẽ chạm tay vào cúp?", "Hai đội dẫn đầu bám đuổi nhau từng điểm một", "Căng thẳng tột độ");
      startTwist();
      return;
    }
    renderFinal();
  }

  function applyStage(nextState, force = false) {
    const signature = nextState.teams.map((team) => `${team.id}:${team.number}:${team.name}`).join("|");
    if (signature !== rosterSignature) {
      rosterSignature = signature;
      shell(nextState.teams);
      force = true;
    }
    const changed = force || !state || nextState.revision !== state.revision || nextState.stage !== state.stage || !!nextState.scoresHidden !== !!state.scoresHidden;
    state = nextState;
    setConnection(true);
    if (!changed) return;
    stopStageAnimation();
    root.querySelector(".mr-shell").dataset.stage = state.stage.toLowerCase();
    if (state.stage === "ROLLING") renderRolling();
    else if (["RANK65", "RANK43", "RANK12", "TWIST", "FINAL"].includes(state.stage)) renderLadder(state.stage);
    else renderIdle();
  }

  async function poll() {
    try {
      const response = await fetch(`${endpoint}${endpoint.includes("?") ? "&" : "?"}_=${Date.now()}`, { credentials: "same-origin", cache: "no-store" });
      if (!response.ok) throw new Error("Không tải được trạng thái công bố.");
      const nextState = await response.json();
      if (!Array.isArray(nextState.teams) || !nextState.teams.length) throw new Error("Chưa có đội trong bảng tổng điểm.");
      failedPolls = 0;
      applyStage(nextState);
    } catch (error) {
      failedPolls += 1;
      if (!state) root.innerHTML = `<div class="mr-error" role="alert"><img src="${esc(logo)}" alt="MAC Marketing"><strong>Chưa kết nối được màn hình kết quả</strong><p>${esc(error.message)}</p><button type="button">Thử lại</button></div>`;
      if (failedPolls >= 2) setConnection(false);
      root.querySelector(".mr-error button")?.addEventListener("click", poll, { once: true });
    }
  }

  function startPyro() {
    const canvas = root.querySelector(".mr-pyro");
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
