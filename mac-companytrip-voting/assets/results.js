(() => {
  const root = document.getElementById("mac-results-app");
  if (!root) return;

  const endpoint = root.dataset.endpoint;
  const logo = root.dataset.logo;
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
  const clamp = (minimum, value, maximum) => Math.min(maximum, Math.max(minimum, value));
  const formatTotal = (score) => Number(score).toLocaleString("vi-VN");
  // Thang 10 ô: mỗi ô = 10% chiều cao cột. Độ cao theo kịch bản MC: lộ 6-5 = 80% → lộ top 4:
  // 4-5-6 = 50% + hạng 3 = 80% → twist: hạng 3 về 50%, 4-5-6 về 30%, top 2 dao động 70→90%
  // → quán quân 85%, hạng nhì 60%.
  const CELL = 10;
  const LADDER_LEVELS = {
    RANK65: { 6: 8, 5: 8 },
    TEASE43: { 6: 8, 5: 8 },
    RANK43: { 6: 5, 5: 5, 4: 5, 3: 8 },
    RANK12: { 6: 3, 5: 3, 4: 3, 3: 5, 2: 6, 1: 6 },
    TWIST: { 6: 3, 5: 3, 4: 3, 3: 5, 2: 6, 1: 6 },
    FINAL: { 6: 3, 5: 3, 4: 3, 3: 5, 2: 6, 1: 8.5 },
  };
  // Badge: hạng 6-5 lộ ở bước 1 nhưng bước 2 mới gắn badge; hạng 4-3 gắn badge ngay khi lộ
  // (bước 2 gắn đủ 3-4-5-6); hạng 2-1 chỉ gắn badge ở FINAL để giữ cú twist.
  const STAGE_ORDER = { RANK65: 1, TEASE43: 1, RANK43: 2, RANK12: 3, TWIST: 3, FINAL: 4 };
  const BADGE_FROM = { 6: 2, 5: 2, 4: 2, 3: 2, 2: 4, 1: 4 };
  const badgeFor = (stage, rank) => (STAGE_ORDER[stage] ?? 0) >= (BADGE_FROM[rank] ?? 9);

  let state = null;
  let rosterSignature = "";
  // Tập các đội đã lộ hạng — dùng để tính nhóm MỚI lộ cho dòng "Xin chúc mừng".
  const revealedIds = new Set();
  // Nhóm đang được phóng to chữ số điểm (điểm tăng trưởng): nhóm mới lộ to nhất, nhóm cũ nhỏ lại.
  let heroIds = new Set();
  let animationTimer = 0;
  let frameId = 0;
  let pollTimer = 0;
  let failedPolls = 0;
  let pyroRevision = -1;
  let pyroStop = null;
  let pyroTimer = 0;

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
    element.classList.remove("is-featured", "is-muted", "is-revealed", "is-finalist", "is-champion", "is-runner-up", "is-third", "is-score-hero");
    element.querySelector(".mr-bar").style.transitionDuration = "";
    const rank = element.querySelector(".mr-rank");
    rank.hidden = true;
    rank.textContent = "";
  }

  function setBar(element, level, scoreText) {
    // level dạng số = % chiều cao cột; dạng chuỗi (vd "112px") = mức cố định cho cột chưa lộ.
    const barLevel = typeof level === "number" ? `${clamp(8, level, 100)}%` : level;
    element.style.setProperty("--bar-level", barLevel);
    element.querySelector(".mr-score span").textContent = scoreText;
  }

  // Admin có thể ẩn điểm trên màn chiếu: che số bằng ••• cho tới khi mở lại.
  function displayScore(text) {
    return state?.scoresHidden ? "•••" : text;
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
    revealedIds.clear();
    heroIds = new Set();
    setHeading("KẾT QUẢ CHUNG CUỘC", "Khoảnh khắc đang đến gần", "", "Sẵn sàng công bố");
    state.teams.forEach((team, index) => {
      const element = teamElement(team.id);
      clearTeamState(element);
      setBar(element, 13 + (index % 2) * 2, "—");
    });
  }

  // Step 1: tung điểm nhưng lượn sóng nhẹ nhàng, không giật. Cột dâng cao dần để có đà trước khi lộ hạng.
  function renderRolling() {
    revealedIds.clear();
    heroIds = new Set();
    setHeading("TỔNG ĐIỂM ĐANG CHUYỂN ĐỘNG", "Ai sẽ chạm đỉnh?", "6 đội · 4 chặng đường · 1 ngôi vương duy nhất", "Đang tung điểm trực tiếp");
    const waves = state.teams.map((team, index) => {
      const column = teamElement(team.id)?.querySelector(".mr-column");
      // Vạch xuất phát 122px quy đổi sang % theo chiều cao cột thật để kéo mượt, không giật.
      const startLevel = column ? clamp(6, (122 / Math.max(1, column.clientHeight)) * 100, 34) : 14;
      return {
        id: team.id,
        start: startLevel,
        base: 26 + (index % 3) * 3,
        amplitude: 6.5 + (index % 2) * 2.5,
        period: 3200 + index * 380,
        phase: index * 0.9,
        value: 430 + index * 25,
        target: 520 + index * 30,
      };
    });
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
      // 1,4s đầu kéo đều từ vạch 122px lên cao rồi mới để sóng lượn — tránh cú giật bật cao ngay khung đầu.
      const ease = 1 - Math.pow(1 - Math.min(1, elapsed / 1400), 3);
      waves.forEach((wave) => {
        const element = teamElement(wave.id);
        if (!element) return;
        clearTeamState(element);
        const waveLevel = wave.base + Math.sin((elapsed / wave.period) * Math.PI * 2 + wave.phase) * wave.amplitude;
        const level = wave.start + (waveLevel - wave.start) * ease;
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

  // Tiêu đề động theo hạng thực lộ — trùng điểm có thể cho "HẠNG 5 ×2", "HẠNG 4 ×3" thay vì 6-5 cứng.
  function rankHeadline(teams) {
    const counts = {};
    teams.forEach((team) => { counts[team.rank] = (counts[team.rank] || 0) + 1; });
    return Object.entries(counts)
      .map(([rank, count]) => ({ rank: Number(rank), count }))
      .sort((a, b) => b.rank - a.rank)
      .map((entry) => entry.count > 1 ? `HẠNG ${entry.rank} ×${entry.count}` : `HẠNG ${entry.rank}`)
      .join(" & ");
  }

  // Step twist: nhóm dẫn đầu dao động đối pha quanh 80% (70→90) để giữ cú twist.
  function startTwist() {
    if (reducedMotion.matches) return;
    // Cột top 2 đã leo mượt 900ms lên 80% trước đó; khi dao động mới chuyển sang bám nhanh 120ms.
    (state.topTwo || []).forEach((id) => {
      const bar = teamElement(id)?.querySelector(".mr-bar");
      if (bar) bar.style.transitionDuration = "120ms";
    });
    const start = performance.now();
    const step = (now) => {
      const seconds = (now - start) / 1000;
      (state.topTwo || []).forEach((id, index) => {
        const element = teamElement(id);
        if (!element) return;
        const level = 80 + Math.sin(seconds * 1.6 + index * Math.PI) * 10;
        element.style.setProperty("--bar-level", `${level}%`);
      });
      frameId = requestAnimationFrame(step);
    };
    frameId = requestAnimationFrame(step);
  }

  function renderFinal() {
    // Trùng điểm có thể cho nhiều đồng quán quân: xướng đủ mọi đội hạng 1.
    const champions = revealedAtRank(1);
    const names = champions.map((team) => team.name);
    const title = names.length ? names.join(" · ") : "Kết quả chung cuộc";
    const scoreLine = champions.length === 1 && !state.scoresHidden ? ` · ${formatTotal(champions[0].score)} điểm` : "";
    const description = names.length ? `CHÚC MỪNG ${names.join(" · ").toUpperCase()}${scoreLine} · Nhà vô địch Company Trip` : "Đã hoàn tất công bố";
    setHeading("QUÁN QUÂN COMPANY TRIP", title, description, "Kết quả chung cuộc");
    if (pyroRevision !== state.revision && !reducedMotion.matches) {
      pyroRevision = state.revision;
      // Pháo hoa chờ thêm 3s để cột quán quân leo trọn lên đỉnh và MC xướng tên xong mới bắn.
      pyroTimer = window.setTimeout(() => { pyroStop = startPyro(); }, 3000);
    }
  }

  // Step 2-6: thang ô cố định theo thứ hạng; CSS tự lo hiệu ứng trồi lên mượt mà.
  // Chỉ render lại đội nào đổi trạng thái để hạng đã lộ đứng yên (không nhấp nháy lại
  // màu, không pop lại badge) khi hạng mới bước lên.
  function renderLadder(stage) {
    // Nhóm MỚI lộ ở nhịp này (đội lộ rồi không xướng lại); trùng điểm có thể lộ nhiều hơn 2 đội.
    const newlyRevealed = state.teams.filter((team) => team.rank !== null && !revealedIds.has(team.id)).sort((a, b) => b.rank - a.rank);
    // Điểm tăng trưởng: nhóm mới lộ nhận chữ số điểm to nhất, nhóm lộ trước tự nhỏ lại.
    // FINAL: chỉ quán quân giữ chữ to, các hạng còn lại thu nhỏ về mức thường.
    if (stage === "FINAL") {
      heroIds = new Set(state.teams.filter((team) => Number(team.rank) === 1).map((team) => team.id));
    } else if (newlyRevealed.length) {
      heroIds = new Set(newlyRevealed.map((team) => team.id));
    }
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
        if (heroIds.has(team.id)) classes.push("is-score-hero");
        level = cells * CELL;
        scoreText = displayScore(formatTotal(team.score));
        if (badgeFor(stage, team.rank)) badgeText = rankLabel(team.rank);
      } else if (stage === "RANK12" || stage === "TWIST") {
        // Top 2 đã leo lên mốc 80% nhưng giấu hạng + điểm thật để giữ cú twist.
        level = 8 * CELL;
        scoreText = "•••";
      } else {
        // Chưa lộ: cột về vạch xuất phát 122px để nhóm được lộ nổi bật hẳn.
        classes.push("is-muted");
        level = "122px";
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

    state.teams.forEach((team) => { if (team.rank !== null) revealedIds.add(team.id); });
    const celebrate = newlyRevealed.length ? `Xin chúc mừng ${teamNames(newlyRevealed)}` : "Kết quả đang được chốt";

    if (stage === "RANK65") {
      setHeading(rankHeadline(newlyRevealed) || "KẾT QUẢ ĐANG CHỐT", "Những cái tên đầu tiên lộ diện", celebrate, "Tín hiệu 1 · Đã chốt");
      return;
    }
    if (stage === "TEASE43") {
      setHeading("TOP 4 ĐANG ĐẾN GẦN", "Ai sẽ bước tiếp?", "Nhấn thêm một nhịp nữa để lộ diện", "Tín hiệu 2 · Nhá hàng");
      return;
    }
    if (stage === "RANK43") {
      setHeading(rankHeadline(newlyRevealed) || "KẾT QUẢ ĐANG CHỐT", "Top giữa đã gọi tên ai?", celebrate, "Tín hiệu 2 · Đã chốt");
      return;
    }
    if (stage === "RANK12") {
      setHeading("HẠNG 2 & HẠNG 1", "Hai cái tên cuối cùng bước lên", "Cả hai cùng chạm mốc 6 ô · Ngôi vương chỉ có một", "Tín hiệu 3 · Đã chốt");
      return;
    }
    if (stage === "TWIST") {
      // Trùng điểm top đầu có thể cho 3-4 cột cùng bám đuổi; copy tự điều chỉnh theo số cột.
      const leaderCount = (state.topTwo || []).length;
      const twistCopy = leaderCount > 2 ? `${leaderCount} đội dẫn đầu đang bám đuổi nhau từng điểm một` : "Hai đội dẫn đầu bám đuổi nhau từng điểm một";
      setHeading("KHOẢNH KHẮC QUYẾT ĐỊNH", "Ai sẽ chạm tay vào cúp?", twistCopy, "Căng thẳng tột độ");
      // Bước twist gộp: top 2 leo mượt lên 6 ô trước, ~1,1s sau mới bắt đầu dao động bám đuổi.
      animationTimer = window.setTimeout(startTwist, 1100);
      return;
    }
    renderFinal();
  }

  function applyStage(nextState, force = false) {
    const signature = nextState.teams.map((team) => `${team.id}:${team.number}:${team.name}`).join("|");
    if (signature !== rosterSignature) {
      rosterSignature = signature;
      revealedIds.clear();
      heroIds = new Set();
      shell(nextState.teams);
      force = true;
    }
    const changed = force || !state || nextState.revision !== state.revision || nextState.stage !== state.stage || !!nextState.scoresHidden !== !!state.scoresHidden;
    state = nextState;
    // Ẩn điểm = giấu hẳn khối số (display:none) thay vì che bằng •••.
    root.classList.toggle("is-scores-hidden", !!state.scoresHidden);
    setConnection(true);
    if (!changed) return;
    stopStageAnimation();
    root.querySelector(".mr-shell").dataset.stage = state.stage.toLowerCase();
    if (state.stage === "ROLLING") renderRolling();
    else if (["RANK65", "TEASE43", "RANK43", "RANK12", "TWIST", "FINAL"].includes(state.stage)) renderLadder(state.stage);
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
