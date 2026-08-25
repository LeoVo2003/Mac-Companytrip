(() => {
  const root = document.getElementById("mac-art-race-app");
  if (!root) return;

  const endpoint = root.dataset.endpoint;
  const logo = root.dataset.logo;
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
  const fmtScore = (score) => Number(score).toLocaleString("vi-VN", { maximumFractionDigits: 1 });
  const rankLabel = (rank) => ({ 1: "QUÁN QUÂN", 2: "HẠNG NHÌ", 3: "HẠNG BA" }[Number(rank)] || `HẠNG ${rank}`);
  const teamNameClass = (name) => {
    const length = Array.from(String(name || "").trim()).length;
    return length > 24 ? " is-name-xlong" : length > 16 ? " is-name-long" : "";
  };
  const dustMarkup = () => `<div class="ar-dust" aria-hidden="true">${Array.from({ length: 30 }, (_, index) => {
    const x = 16 + ((index * 37) % 69);
    const y = 8 + ((index * 53) % 83);
    const size = 1 + (index % 4) * 0.55;
    const delay = -((index % 11) * 0.17);
    const duration = 1.8 + (index % 7) * 0.19;
    return `<i style="--dust-x:${x}%;--dust-y:${y}%;--dust-size:${size}px;--dust-delay:${delay}s;--dust-duration:${duration}s"></i>`;
  }).join("")}</div>`;

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
  let searchActive = false;
  const decelTimers = [];
  let shakeTimer = 0;

  function shell(teams) {
    root.innerHTML = `<div class="ar-shell" data-stage="idle">
      <canvas class="ar-pyro" aria-hidden="true"></canvas>
      <div class="ar-stage-world" aria-hidden="true"><i class="ar-rays"></i><i class="ar-haze"></i><i class="ar-runway"></i></div>
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
          <div class="ar-spot" data-spot="0"></div>
          ${teams.map((team, index) => `<article class="ar-team is-pending${teamNameClass(team.name)}" style="--slot:${index}" data-team-id="${team.id}" role="group" aria-label="Đội số ${team.number} ${esc(team.name)}">
            <div class="ar-beam" aria-hidden="true"></div>
            ${dustMarkup()}
            <div class="ar-team-copy">
              <span class="ar-team-rank">CHỜ CÔNG BỐ</span>
              <strong>${esc(team.name)}</strong>
              <b>—</b>
              <small>ĐIỂM TRUNG BÌNH</small>
            </div>
            <div class="ar-podium" aria-hidden="true"><em>${esc(team.name)}</em><span></span></div>
            <i class="ar-floor-ring" aria-hidden="true"></i>
          </article>`).join("")}
        </section>
      </main>
      <i class="ar-vignette" aria-hidden="true"></i>
      <div class="ar-offline-overlay" role="alert" aria-live="assertive" aria-hidden="true">
        <i aria-hidden="true"></i>
        <strong>Tạm mất kết nối</strong>
        <p>Đang tự động kết nối lại với bàn điều khiển. Kết quả trên màn hình vẫn được giữ nguyên.</p>
      </div>
      <footer class="ar-footer"><span class="ar-stage-copy">Sẵn sàng công bố</span><div><i></i><span>LIVE · THE SPOTLIGHT</span></div></footer>
    </div>`;
  }

  function setConnection(connected, showOverlay = false) {
    const connection = root.querySelector(".ar-connection");
    if (!connection) return;
    connection.classList.toggle("is-offline", !connected);
    connection.querySelector("span").textContent = connected ? "Đang đồng bộ" : "Mất kết nối · đang thử lại";
    const shellElement = root.querySelector(".ar-shell");
    const overlay = root.querySelector(".ar-offline-overlay");
    const overlayVisible = !connected && showOverlay;
    shellElement?.classList.toggle("has-connection-alert", overlayVisible);
    overlay?.setAttribute("aria-hidden", overlayVisible ? "false" : "true");
  }

  function setTitle(kicker, title, description, footer) {
    const heading = root.querySelector(".ar-title");
    if (!heading) return;
    heading.classList.remove("is-soft");
    heading.querySelector("p").textContent = kicker;
    heading.querySelector("h1").textContent = title;
    heading.querySelector("span").textContent = description;
    root.querySelector(".ar-stage-copy").textContent = footer;
    // Chữ bay vào theo kiểu wipe từng khối, so le nhau.
    heading.classList.remove("ar-swap");
    void heading.offsetWidth;
    heading.classList.add("ar-swap");
  }

  function teamElement(id) {
    return root.querySelector(`[data-team-id="${String(id).replace(/"/g, "")}"]`);
  }

  /* Chùm spotlight toàn cục trượt ngang qua các bục thay vì bật tắt rời rạc. */
  function aimSpots(targets, duration, visible = true) {
    const host = root.querySelector(".ar-podiums");
    const spots = root.querySelectorAll(".ar-spot");
    if (!host || !spots.length) return;
    spots.forEach((spot, index) => {
      const target = targets[index];
      if (!target) {
        spot.style.opacity = "0";
        return;
      }
      const a = target.getBoundingClientRect();
      const b = host.getBoundingClientRect();
      const x = a.left + a.width / 2 - b.left - spot.offsetWidth / 2;
      spot.style.transitionDuration = `${duration}ms, ${Math.min(260, duration)}ms`;
      spot.style.opacity = visible ? "1" : "0";
      spot.style.transform = `translateX(${x}px)`;
    });
  }

  /* Nhóm đội chưa lộ — dùng cho vòng tìm kiếm sau mỗi lần công bố. */
  function searchPool() {
    return (state?.teams || [])
      .filter((team) => !(team.revealed || team.rank !== null))
      .map((team) => teamElement(team.id))
      .filter(Boolean);
  }

  /* Đếm chạy số từ 0 lên điểm thật trong ~0.8s khi lộ diện. */
  function countUp(element, value) {
    if (!element) return;
    if (reducedMotion.matches || value === null || value === undefined) {
      element.textContent = value === null || value === undefined ? "—" : fmtScore(value);
      return;
    }
    const target = Number(value);
    const start = performance.now();
    const tick = (now) => {
      const t = Math.min(1, (now - start) / 800);
      const eased = 1 - Math.pow(1 - t, 3);
      element.textContent = t < 1 ? fmtScore(Math.round(target * eased * 10) / 10) : fmtScore(target);
      if (t < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }

  function shakeStage() {
    const shellEl = root.querySelector(".ar-shell");
    if (!shellEl || reducedMotion.matches) return;
    shellEl.classList.remove("ar-shake");
    void shellEl.offsetWidth;
    shellEl.classList.add("ar-shake");
    window.clearTimeout(shakeTimer);
    shakeTimer = window.setTimeout(() => shellEl.classList.remove("ar-shake"), 420);
  }

  function resetEffects() {
    window.clearTimeout(pyroTimer);
    window.clearTimeout(spotlightStartTimer);
    window.clearInterval(spotlightTimer);
    window.clearTimeout(shakeTimer);
    decelTimers.forEach((timer) => window.clearTimeout(timer));
    decelTimers.length = 0;
    pyroTimer = 0;
    spotlightStartTimer = 0;
    spotlightTimer = 0;
    spotlightIndex = -1;
    searchActive = false;
    root.querySelector(".ar-shell")?.classList.remove("is-decel", "ar-shake", "is-search-active");
    aimSpots([]);
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
    if (!state || state.stage === "IDLE" || state.stage === "FINAL") return;
    if (!searchPool().length) return;
    searchActive = true;
    if (state.stage === "ROLLING") {
      setTitle("ÁNH SÁNG ĐANG GỌI TÊN", "Ai sẽ bước vào tâm điểm?", "Một khoảnh khắc · một cái tên được chọn", "Chờ tín hiệu công bố hạng 6");
    } else {
      setTitle("ÁNH SÁNG TIẾP TỤC GỌI TÊN", "Tâm điểm tiếp theo thuộc về ai?", "Spotlight đang lướt qua những đội chưa lộ diện", "Chờ tín hiệu MC");
    }
    root.querySelector(".ar-title")?.classList.add("is-soft");
    if (reducedMotion.matches) return;
    root.querySelector(".ar-shell")?.classList.add("is-search-active");
    const jump = () => {
      root.querySelectorAll(".ar-team.is-searching").forEach((element) => element.classList.remove("is-searching"));
      const pool = searchPool();
      if (!pool.length) { aimSpots([], 300); return; }
      // Chỉ một luồng sáng duy nhất lướt qua từng đội khi tìm kiếm.
      const picked = [pool.splice(Math.floor(Math.random() * pool.length), 1)[0]];
      picked.forEach((element) => element.classList.add("is-searching"));
      aimSpots(picked, 300);
    };
    jump();
    spotlightTimer = window.setInterval(jump, 620);
  }

  /* Pha hãm dần kiểu vòng quay số: nhảy thưa dần rồi mới khóa vào đội đúng. */
  function lockSpotlight(currentEl, onLock) {
    window.clearInterval(spotlightTimer);
    spotlightTimer = 0;
    searchActive = false;
    if (reducedMotion.matches || !currentEl) {
      root.querySelectorAll(".ar-team.is-searching").forEach((element) => element.classList.remove("is-searching"));
      root.querySelector(".ar-shell")?.classList.remove("is-search-active");
      aimSpots([]);
      onLock();
      return;
    }
    const shellEl = root.querySelector(".ar-shell");
    shellEl.classList.add("is-decel");
    const others = searchPool().filter((el) => el !== currentEl);
    const steps = [380, 620, 900];
    let acc = 0;
    steps.forEach((delay) => {
      const pick = others.length ? others[Math.floor(Math.random() * others.length)] : currentEl;
      decelTimers.push(window.setTimeout(() => {
        root.querySelectorAll(".ar-team.is-searching").forEach((element) => element.classList.remove("is-searching"));
        pick.classList.add("is-searching");
        aimSpots([pick], delay + 140);
      }, acc));
      acc += delay;
    });
    decelTimers.push(window.setTimeout(() => {
      root.querySelectorAll(".ar-team.is-searching").forEach((element) => element.classList.remove("is-searching"));
      aimSpots([currentEl], 1000);
    }, acc));
    decelTimers.push(window.setTimeout(() => {
      shellEl.classList.remove("is-decel", "is-search-active");
      aimSpots([]);
      onLock();
    }, acc + 1050));
  }

  function renderReveal() {
    const current = state.teams.find((team) => team.current) || null;
    const currentEl = current ? teamElement(current.id) : null;
    const champion = current && Number(current.rank) === 1;
    const needDecel = searchActive && currentEl && !reducedMotion.matches;

    state.teams.forEach((team) => {
      const element = teamElement(team.id);
      const revealed = Boolean(team.revealed || team.rank !== null);
      element.className = `ar-team ${revealed ? "is-revealed" : "is-pending"}${team.current ? " is-current" : ""}${Number(team.rank) === 1 ? " is-champion" : ""}`;
      element.querySelector(".ar-team-rank").textContent = revealed ? rankLabel(team.rank) : "CHỜ CÔNG BỐ";
      const scoreEl = element.querySelector(".ar-team-copy b");
      if (team.current) {
        scoreEl.textContent = "0";
      } else {
        scoreEl.textContent = revealed && team.score !== null ? fmtScore(team.score) : "—";
      }
    });

    if (!current) {
      setTitle("KẾT QUẢ VĂN NGHỆ", "Đang chốt kết quả", "Spotlight sẽ khóa vào đội tiếp theo", "Chờ tín hiệu MC");
      return;
    }

    setTitle(
      champion ? "QUÁN QUÂN VĂN NGHỆ" : rankLabel(current.rank),
      current.name,
      current.score !== null ? `${fmtScore(current.score)} điểm · Spotlight thuộc về ${current.name}` : "Kết quả đã được công bố",
      champion ? "Kết quả chung cuộc" : `Đã công bố ${rankLabel(current.rank).toLowerCase()}`
    );

    const onLock = () => {
      countUp(currentEl?.querySelector(".ar-team-copy b"), current.score);
      if (champion) shakeStage();
      // Giữ spotlight khóa 3 giây cho MC xướng tên rồi tiếp tục tìm kiếm các đội chưa lộ.
      if (!champion && searchPool().length && !reducedMotion.matches) {
        spotlightStartTimer = window.setTimeout(startSpotlightSearch, 3000);
      }
    };
    if (needDecel) {
      lockSpotlight(currentEl, onLock);
    } else {
      root.querySelectorAll(".ar-team.is-searching").forEach((element) => element.classList.remove("is-searching"));
      aimSpots([]);
      onLock();
    }

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
    root.classList.toggle("is-light", !!state.lightTheme);
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
      if (failedPolls >= 2) setConnection(false, failedPolls >= 6);
      root.querySelector(".ar-error button")?.addEventListener("click", poll, { once: true });
    }
  }

  /* Pháo hoa: confetti vuông + tia lửa lấp lánh + dải ruy băng xoay rơi, palette đỏ-cam-vàng-trắng. */
  function startPyro() {
    const canvas = root.querySelector(".ar-pyro");
    const context = canvas.getContext("2d");
    const particles = [];
    const colors = ["#fff4ce", "#efc878", "#ff6a2c", "#e31e24", "#ffffff"];
    let running = true;
    const started = performance.now();
    let lastRibbon = 0;
    const resize = () => {
      const ratio = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.round(innerWidth * ratio);
      canvas.height = Math.round(innerHeight * ratio);
      canvas.style.width = `${innerWidth}px`;
      canvas.style.height = `${innerHeight}px`;
      context.setTransform(ratio, 0, 0, ratio, 0, 0);
    };
    const burst = (x, y) => {
      for (let index = 0; index < 90; index += 1) {
        const angle = (Math.PI * 2 * index) / 90 + Math.random() * 0.08;
        const speed = 2.5 + Math.random() * 7;
        particles.push({ kind: index % 4 === 0 ? "spark" : "rect", x, y, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed, color: colors[index % colors.length], life: 70 + Math.random() * 50, size: 1.5 + Math.random() * 3, tw: Math.random() * Math.PI * 2, rot: Math.random() * Math.PI, vr: (Math.random() - 0.5) * 0.24 });
      }
    };
    resize();
    burst(innerWidth * 0.25, innerHeight * 0.34);
    burst(innerWidth * 0.75, innerHeight * 0.3);
    window.setTimeout(() => running && burst(innerWidth * 0.5, innerHeight * 0.2), 650);
    const draw = (now) => {
      if (!running) return;
      context.clearRect(0, 0, innerWidth, innerHeight);
      if (now - lastRibbon > 130 && now - started < 6500) {
        lastRibbon = now;
        particles.push({ kind: "ribbon", x: Math.random() * innerWidth, y: -24, vx: 0, vy: 1.1 + Math.random() * 1.6, color: colors[Math.floor(Math.random() * colors.length)], life: 320, size: 2 + Math.random() * 2, tw: Math.random() * Math.PI * 2, rot: Math.random() * Math.PI, vr: (Math.random() - 0.5) * 0.18 });
      }
      for (let index = particles.length - 1; index >= 0; index -= 1) {
        const p = particles[index];
        p.life -= 1;
        if (p.life <= 0 || p.y > innerHeight + 30) { particles.splice(index, 1); continue; }
        if (p.kind === "ribbon") {
          p.tw += 0.05;
          p.x += Math.sin(p.tw) * 1.3;
          p.y += p.vy;
          p.rot += p.vr;
          context.globalAlpha = Math.min(1, p.life / 60);
          context.fillStyle = p.color;
          context.save();
          context.translate(p.x, p.y);
          context.rotate(p.rot);
          context.fillRect(-p.size / 2, -p.size * 6, p.size, p.size * 12);
          context.restore();
          continue;
        }
        p.x += p.vx;
        p.y += p.vy;
        p.vy += p.kind === "spark" ? 0.045 : 0.065;
        p.vx *= 0.993;
        p.rot += p.vr;
        if (p.kind === "spark") {
          p.tw += 0.3;
          context.globalAlpha = Math.min(1, p.life / 24) * (0.55 + 0.45 * Math.sin(p.tw));
          context.fillStyle = p.color;
          context.beginPath();
          context.arc(p.x, p.y, p.size * 0.7, 0, Math.PI * 2);
          context.fill();
        } else {
          context.globalAlpha = Math.min(1, p.life / 24);
          context.fillStyle = p.color;
          context.save();
          context.translate(p.x, p.y);
          context.rotate(p.rot);
          context.fillRect(-p.size / 2, -p.size * 0.9, p.size, p.size * 1.8);
          context.restore();
        }
      }
      context.globalAlpha = 1;
      if (now - started > 9000 && !particles.length) return;
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
