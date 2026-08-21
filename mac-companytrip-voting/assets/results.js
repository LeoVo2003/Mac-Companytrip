(() => {
  const root = document.getElementById("mac-results-app");
  if (!root) return;

  const endpoint = root.dataset.endpoint;
  const logo = root.dataset.logo;
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
  const clamp = (minimum, value, maximum) => Math.min(maximum, Math.max(minimum, value));
  const scoreLevel = (score) => clamp(12, 12 + ((Number(score) - 30) / 120) * 86, 98);
  const formatScore = (score) => Number(score).toLocaleString("vi-VN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  let state = null;
  let rosterSignature = "";
  let animationTimer = 0;
  let pollTimer = 0;
  let failedPolls = 0;
  let pyroRevision = -1;
  let pyroStop = null;

  function shell(teams) {
    root.innerHTML = `<div class="mr-shell" data-stage="idle"><canvas class="mr-pyro" aria-hidden="true"></canvas><div class="mr-seascape" aria-hidden="true"><i class="mr-sun"></i><i class="mr-wake"></i></div><div class="mr-compass" aria-hidden="true"><svg viewBox="0 0 400 400" role="presentation"><circle cx="200" cy="200" r="176"/><circle cx="200" cy="200" r="154"/><circle cx="200" cy="200" r="118"/><path class="mr-compass-cross" d="M200 24v352M24 200h352M76 76l248 248M324 76 76 324"/><path class="mr-compass-rose" d="M200 48l22 130 130 22-130 22-22 130-22-130-130-22 130-22z"/><path class="mr-compass-minor" d="M200 84l12 104 104 12-104 12-12 104-12-104-104-12 104-12z"/><g class="mr-compass-labels"><text x="200" y="18">N</text><text x="386" y="206">E</text><text x="200" y="397">S</text><text x="14" y="206">W</text><text x="327" y="67">NE</text><text x="337" y="345">SE</text><text x="63" y="345">SW</text><text x="62" y="67">NW</text></g></svg><i class="mr-needle"></i></div><header class="mr-header"><div class="mr-brand-lockup"><img src="${esc(logo)}" alt="MAC Marketing"></div><div class="mr-event"><span>COMPANY TRIP - One Direction</span><strong>KẾT QUẢ VĂN NGHỆ</strong></div><div class="mr-connection" role="status"><i></i><span>Đang đồng bộ</span></div></header><main><div class="mr-heading" aria-live="polite"><p>KẾT QUẢ VĂN NGHỆ</p><h1>Khoảnh khắc đang đến gần</h1><span></span></div><section class="mr-chart" aria-label="Biểu đồ điểm của 6 đội">${teams.map((team) => `<article class="mr-team" data-team-id="${team.id}" role="group" aria-label="Team số ${team.number} ${esc(team.name)}"><div class="mr-score"><span>—</span><small>ĐIỂM</small></div><div class="mr-column"><div class="mr-rank" hidden></div><div class="mr-bar"><span></span><i></i></div><div class="mr-base"></div></div><div class="mr-team-name"><b>#${team.number}</b><strong>${esc(team.name)}</strong></div></article>`).join("")}</section></main><footer class="mr-footer"><span class="mr-stage-copy">Sẵn sàng công bố</span><div><i></i><span>LIVE RESULT</span></div></footer></div>`;
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

  function stopStageAnimation() {
    window.clearInterval(animationTimer);
    animationTimer = 0;
    if (pyroStop) pyroStop();
    pyroStop = null;
  }

  function renderIdle() {
    setHeading("KẾT QUẢ VĂN NGHỆ", "Khoảnh khắc đang đến gần", "", "Sẵn sàng công bố");
    state.teams.forEach((team, index) => {
      const element = teamElement(team.id);
      clearTeamState(element);
      setBar(element, 13 + (index % 2) * 2, "—");
    });
  }

  function rollingFrame() {
    state.teams.forEach((team, index) => {
      const element = teamElement(team.id);
      clearTeamState(element);
      const score = 54 + Math.random() * 95;
      setBar(element, scoreLevel(score), index % 2 ? score.toFixed(1) : String(Math.round(score)));
    });
  }

  function renderRolling() {
    setHeading("ĐIỂM SỐ ĐANG CHUYỂN ĐỘNG", "Ai sẽ chạm đỉnh?", "Mọi vị trí vẫn đang thay đổi", "Đang tung điểm trực tiếp");
    rollingFrame();
    animationTimer = window.setInterval(rollingFrame, reducedMotion.matches ? 700 : 110);
  }

  function renderDecoy() {
    setHeading("NHỮNG CÁI TÊN ĐANG BỨT PHÁ", "Top đầu đã lộ diện?", "Ba vị trí đang vươn lên mạnh mẽ", "Tín hiệu 1 · Đã chốt");
    state.teams.forEach((team, index) => {
      const element = teamElement(team.id);
      clearTeamState(element);
      if (team.featured) {
        const hasScore = team.score !== null && Number.isFinite(Number(team.score));
        element.classList.add("is-featured");
        setBar(element, hasScore ? scoreLevel(team.score) : 12, hasScore ? formatScore(team.score) : "—");
      } else {
        element.classList.add("is-muted");
        setBar(element, 28 + index * 2, "•••");
      }
    });
  }

  function rankLabel(rank) {
    if (Number(rank) === 1) return "QUÁN QUÂN";
    if (Number(rank) === 2) return "HẠNG NHÌ";
    if (Number(rank) === 3) return "HẠNG BA";
    return `HẠNG ${rank}`;
  }

  function revealTeam(team, podiumClass = "") {
    const element = teamElement(team.id);
    element.classList.remove("is-muted");
    element.classList.add("is-revealed");
    if (podiumClass) element.classList.add("is-finalist", podiumClass);
    setBar(element, scoreLevel(team.score), formatScore(team.score));
    const rank = element.querySelector(".mr-rank");
    rank.hidden = false;
    rank.textContent = rankLabel(team.rank);
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

  function renderPodium(stage) {
    state.teams.forEach((team) => {
      const element = teamElement(team.id);
      clearTeamState(element);
      element.classList.add("is-muted");
      setBar(element, 15, "•••");
    });
    state.teams.filter((team) => team.score !== null && team.rank !== null).forEach((team) => revealTeam(team, podiumClass(team.rank)));

    if (stage === "THIRD") {
      const teams = revealedAtRank(3);
      setHeading("TOP 3", teams.length ? "Vị trí thứ ba" : "Không có hạng ba riêng", teams.length ? `Xin chúc mừng ${teamNames(teams)}` : "Kết quả đồng hạng làm thay đổi thứ tự", "Hạng ba đã được công bố");
      return;
    }
    if (stage === "SECOND") {
      const teams = revealedAtRank(2);
      setHeading("TOP 2", teams.length ? "Vị trí thứ hai" : "Không có hạng nhì riêng", teams.length ? `Xin chúc mừng ${teamNames(teams)}` : "Kết quả đồng hạng làm thay đổi thứ tự", "Chỉ còn ngôi vị cao nhất");
      return;
    }

    const champions = revealedAtRank(1);
    const championScore = champions[0]?.score;
    setHeading("QUÁN QUÂN ĐÊM VĂN NGHỆ", champions.length ? teamNames(champions) : "Kết quả chung cuộc", championScore === undefined ? "Đã hoàn tất công bố" : `${formatScore(championScore)} điểm · Một màn trình diễn bùng nổ`, "Kết quả chung cuộc");
    if (pyroRevision !== state.revision && !reducedMotion.matches) {
      pyroRevision = state.revision;
      pyroStop = startPyro();
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
    root.querySelector(".mr-shell").dataset.stage = state.stage.toLowerCase();
    if (state.stage === "ROLLING") renderRolling();
    else if (state.stage === "DECOY") renderDecoy();
    else if (["THIRD", "SECOND", "FINAL"].includes(state.stage)) renderPodium(state.stage);
    else renderIdle();
  }

  async function poll() {
    try {
      const response = await fetch(`${endpoint}${endpoint.includes("?") ? "&" : "?"}_=${Date.now()}`, { credentials: "same-origin", cache: "no-store" });
      if (!response.ok) throw new Error("Không tải được trạng thái công bố.");
      const nextState = await response.json();
      if (!Array.isArray(nextState.teams) || !nextState.teams.length) throw new Error("Chưa có đội thi trong lịch biểu diễn.");
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
    const colors = ["#ffe9ad", "#e8c17a", "#b8823f", "#ffcf7d", "#fff6e0"];
    let frame = 0;
    let running = true;
    let started = performance.now();
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
      for (let index = 0; index < 15; index += 1) particle(x + (Math.random() - 0.5) * 18, innerHeight - 22, (Math.random() - 0.5) * 3.2, -7 - Math.random() * 7, colors[index % colors.length], 2 + Math.random() * 3, 65 + Math.random() * 35, 0.13);
    };
    const burst = (x, y) => {
      for (let index = 0; index < 78; index += 1) {
        const angle = (Math.PI * 2 * index) / 78 + Math.random() * 0.08;
        const speed = 2.6 + Math.random() * 7.2;
        particle(x, y, Math.cos(angle) * speed, Math.sin(angle) * speed, colors[index % colors.length], 1.5 + Math.random() * 2.5, 70 + Math.random() * 45, 0.045);
      }
    };
    const draw = (now) => {
      if (!running) return;
      context.clearRect(0, 0, innerWidth, innerHeight);
      if (now - lastFountain > 95 && now - started < 5200) {
        [0.22, 0.5, 0.78].forEach((position) => fountain(innerWidth * position));
        lastFountain = now;
      }
      if (now - lastBurst > 720 && now - started < 6200) {
        burst(innerWidth * (0.18 + Math.random() * 0.64), innerHeight * (0.17 + Math.random() * 0.34));
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
        context.globalAlpha = clamp(0, item.life / Math.min(28, item.maxLife), 1);
        context.fillStyle = item.color;
        context.save();
        context.translate(item.x, item.y);
        context.rotate(item.rotation);
        context.fillRect(-item.size / 2, -item.size / 2, item.size, item.size * 1.9);
        context.restore();
      }
      context.globalAlpha = 1;
      if (now - started > 7600 && !particles.length) { running = false; context.clearRect(0, 0, innerWidth, innerHeight); return; }
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
