(() => {
  const root = document.getElementById("mac-final-app");
  if (!root) return;

  const config = window.MACFinal || {};
  const endpoint = config.endpoint || root.dataset.endpoint;
  const logo = config.logo || root.dataset.logo || "";
  if (!endpoint) return;

  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  const esc = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
  const fmtNum = (value) => Number(value || 0).toLocaleString("vi-VN");

  // Trụ cột điểm mở dần theo từng trạng thái BUILD.
  const ROUND_KEYS = {
    BUILD_CHECKIN: ["checkin"],
    BUILD_GAME: ["checkin", "games"],
    BUILD_THIDUA: ["checkin", "games", "thidua"],
    BUILD_VANNGHE: ["checkin", "games", "thidua", "vote"],
  };
  const COLUMN_KEYS = ["checkin", "games", "thidua", "vote"];
  const BUILD_META = {
    BUILD_CHECKIN: { step: 1, title: "ĐIỂM CHECK-IN MỞ MÀN", description: "4 trạm Company Trip · tối đa 600 điểm" },
    BUILD_GAME: { step: 2, title: "TRÒ CHƠI LỚN VÀO CUỘC", description: "3 game · thang hạng 50 → 0 điểm" },
    BUILD_THIDUA: { step: 3, title: "THI ĐUA BỨT PHÁ", description: "Điểm ban giám khảo không giới hạn" },
    BUILD_VANNGHE: { step: 4, title: "VĂN NGHỆ — TRỤ CỘT CUỐI", description: "Lá phiếu của toàn công ty · tối đa 200 điểm" },
  };
  const STEP_MS = 1600;
  const COUNT_MS = 900;
  const ODO_MS = 3400;

  let payload = null;
  let lastRevision = -1;
  let failedPolls = 0;
  let pollTimer = 0;
  const lastRevByState = {};
  let timers = [];
  let rafIds = [];

  function clearLocalTimers() {
    timers.forEach((id) => window.clearTimeout(id));
    timers = [];
    rafIds.forEach((id) => window.cancelAnimationFrame(id));
    rafIds = [];
  }

  function shell() {
    root.innerHTML = `<div class="mf-shell" data-stage="ready"><header class="mf-header"><img src="${esc(logo)}" alt="MAC Marketing"><div class="mf-event"><span>COMPANY TRIP · AWARD NIGHT</span><strong>KẾT QUẢ CHUNG CUỘC</strong></div><div class="mf-connection" role="status"><i></i><span>Đang đồng bộ</span></div></header><div class="mf-heading" aria-live="polite"><p>MAC MARKETING</p><h1>RACE TO THE CROWN</h1><span>Màn công bố tổng điểm chung cuộc</span></div><div class="mf-main"><div class="mf-ready"><div class="mf-ready-crown" aria-hidden="true">♛</div><strong>Chờ tín hiệu từ ban tổ chức</strong><span>Điểm tổng 4 mặt trận sẽ được công bố ngay tại đây</span></div></div></div>`;
  }

  function setConnection(connected) {
    const connection = root.querySelector(".mf-connection");
    if (!connection) return;
    connection.classList.toggle("is-offline", !connected);
    connection.querySelector("span").textContent = connected ? "Đang đồng bộ" : "Mất kết nối · đang thử lại";
  }

  function setStage(stage) {
    root.querySelector(".mf-shell").dataset.stage = stage.toLowerCase();
  }

  function setHeading(kicker, title, description) {
    const heading = root.querySelector(".mf-heading");
    if (!heading) return;
    heading.querySelector("p").textContent = kicker;
    heading.querySelector("h1").textContent = title;
    heading.querySelector("span").textContent = description;
  }

  const main = () => root.querySelector(".mf-main");
  const teams = () => (payload && payload.snapshot ? payload.snapshot.teams : []);
  const groups = () => (payload && payload.snapshot ? payload.snapshot.groups : []);
  const groupOf = (teamId) => groups().find((group) => group.teams.some((team) => team.teamId === teamId)) || null;

  function countUp(element, to, duration) {
    if (reducedMotion.matches || duration <= 0) {
      element.textContent = fmtNum(to);
      return;
    }
    const start = performance.now();
    const step = (now) => {
      const progress = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - progress, 3);
      element.textContent = fmtNum(Math.round(to * eased));
      if (progress < 1) rafIds.push(window.requestAnimationFrame(step));
    };
    rafIds.push(window.requestAnimationFrame(step));
  }

  function flip(container, mutate) {
    if (reducedMotion.matches) {
      mutate();
      return;
    }
    const firstTop = new Map(Array.from(container.children).map((el) => [el, el.getBoundingClientRect().top]));
    mutate();
    Array.from(container.children).forEach((el) => {
      const before = firstTop.get(el);
      if (before === undefined) return;
      const delta = before - el.getBoundingClientRect().top;
      if (Math.abs(delta) < 1) return;
      el.style.transition = "none";
      el.style.transform = `translateY(${delta}px)`;
      window.requestAnimationFrame(() => {
        el.style.transition = "";
        el.style.transform = "";
      });
    });
  }

  function buildBoard() {
    const list = teams();
    main().innerHTML = `<div class="mf-board"><div class="mf-row-head"><span>ĐỘI</span><span>CHECK-IN</span><span>GAME</span><span>THI ĐUA</span><span>VĂN NGHỆ</span><span>TỔNG</span></div><div class="mf-rows">${list.map((team) => `<div class="mf-row" data-team-id="${team.teamId}"><div class="mf-cell mf-team"><span class="mf-rankno" hidden></span><span class="mf-team-num">#${team.teamNumber}</span><strong>${esc(team.teamName)}</strong><span class="mf-badge" hidden></span></div>${COLUMN_KEYS.map((key) => `<div class="mf-cell mf-col-${key}">—</div>`).join("")}<div class="mf-cell mf-total">—</div></div>`).join("")}</div><div class="mf-remain" hidden></div></div>`;
  }

  const rowOf = (teamId) => root.querySelector(`.mf-row[data-team-id="${teamId}"]`);

  function setCell(element, key, team, activeKeys) {
    const cell = element.querySelector(`.mf-col-${key}`);
    const active = activeKeys.includes(key);
    cell.classList.toggle("is-on", active);
    cell.classList.toggle("is-dim", !active);
    cell.textContent = active ? fmtNum(team[key]) : "—";
  }

  function orderByCumulative(activeKeys) {
    const container = root.querySelector(".mf-rows");
    flip(container, () => {
      teams()
        .slice()
        .sort((a, b) => {
          const sum = (team) => activeKeys.reduce((acc, key) => acc + Number(team[key] || 0), 0);
          return sum(b) - sum(a) || a.teamNumber - b.teamNumber;
        })
        .forEach((team) => container.append(rowOf(team.teamId)));
    });
  }

  function renderBuild(stage, animate) {
    const meta = BUILD_META[stage];
    const activeKeys = ROUND_KEYS[stage];
    setStage(stage);
    setHeading(`TRỤ CỘT ${meta.step}/4`, meta.title, meta.description);
    buildBoard();
    teams().forEach((team) => {
      const row = rowOf(team.teamId);
      COLUMN_KEYS.forEach((key) => setCell(row, key, team, activeKeys));
      const total = activeKeys.reduce((acc, key) => acc + Number(team[key] || 0), 0);
      const totalCell = row.querySelector(".mf-total");
      animate ? countUp(totalCell, total, COUNT_MS) : (totalCell.textContent = fmtNum(total));
    });
    orderByCumulative(activeKeys);
    rowOf(teams().slice().sort((a, b) => {
      const sum = (team) => activeKeys.reduce((acc, key) => acc + Number(team[key] || 0), 0);
      return sum(b) - sum(a) || a.teamNumber - b.teamNumber;
    })[0].teamId)?.classList.add("is-leader");
  }

  function renderFinalBoard(stage, onRow) {
    setStage(stage);
    buildBoard();
    const container = root.querySelector(".mf-rows");
    const ordered = teams().slice().sort((a, b) => a.total !== b.total ? b.total - a.total : a.teamNumber - b.teamNumber);
    ordered.forEach((team) => {
      const row = rowOf(team.teamId);
      COLUMN_KEYS.forEach((key) => setCell(row, key, team, COLUMN_KEYS));
      row.querySelector(".mf-total").textContent = fmtNum(team.total);
      onRow(row, team, groupOf(team.teamId));
      container.append(row);
    });
  }

  function setBadge(row, text, tone) {
    const badge = row.querySelector(".mf-badge");
    badge.hidden = false;
    badge.textContent = text;
    badge.className = `mf-badge ${tone}`;
  }

  function renderLocked(animate) {
    setStage("LOCKED");
    setHeading("FINAL RANKING", "ĐIỂM ĐÃ KHÓA", "Thứ hạng sẽ chỉ lộ khi MC ra hiệu");
    buildBoard();
    teams().forEach((team) => {
      const row = rowOf(team.teamId);
      COLUMN_KEYS.forEach((key) => setCell(row, key, team, COLUMN_KEYS));
      const totalCell = row.querySelector(".mf-total");
      animate ? countUp(totalCell, team.total, COUNT_MS) : (totalCell.textContent = fmtNum(team.total));
    });
    orderByCumulative(COLUMN_KEYS);
  }

  function setRemain(visibleCount) {
    const remain = root.querySelector(".mf-remain");
    if (!remain) return;
    remain.hidden = false;
    remain.textContent = `CHỈ CÒN ${visibleCount} ĐỘI`;
  }

  function renderBottom(animate) {
    const topGroups = groups().filter((group) => group.rank <= 3);
    const remainingTeams = topGroups.reduce((acc, group) => acc + group.teams.length, 0);
    renderFinalBoard("BOTTOM", (row, team) => {
      const group = groupOf(team.teamId);
      if (group && group.rank <= 3) row.classList.add("is-leader");
    });
    if (!animate) {
      groups().filter((group) => group.rank > 3).forEach((group) => group.teams.forEach((team) => rowOf(team.teamId)?.classList.add("is-out")));
      setRemain(remainingTeams);
      return;
    }
    const outGroups = groups().filter((group) => group.rank > 3).sort((a, b) => b.rank - a.rank);
    outGroups.forEach((group, index) => {
      timers.push(window.setTimeout(() => {
        group.teams.forEach((team) => {
          const row = rowOf(team.teamId);
          row?.classList.remove("is-leader");
          row?.classList.add("is-out");
        });
        setRemain(remainingTeams + outGroups.slice(index + 1).reduce((acc, item) => acc + item.teams.length, 0));
      }, index * STEP_MS));
    });
  }

  function renderTop3() {
    const topGroups = groups().filter((group) => group.rank <= 3);
    renderFinalBoard("TOP3", (row, team) => {
      const group = groupOf(team.teamId);
      if (!group || group.rank > 3) row.classList.add("is-out");
      else row.classList.add("is-leader");
    });
    setRemain(topGroups.reduce((acc, group) => acc + group.teams.length, 0));
  }

  function renderBronze() {
    renderFinalBoard("BRONZE", (row, team, group) => {
      if (!group || group.rank > 3) {
        row.classList.add("is-out");
      } else if (group.rank === 3) {
        row.classList.add("is-bronze");
        setBadge(row, "HẠNG 3", "bronze");
      }
    });
  }

  function renderDuel(animate) {
    setStage("DUEL");
    setHeading("FINAL DUEL", "CUỘC ĐUA CUỐI CÙNG", "Nhóm dẫn đầu chạy số trực tiếp — ai chạm đỉnh?");
    const rank1 = groups().find((group) => group.rank === 1);
    const rank2 = groups().find((group) => group.rank === 2);
    const lane = (group, tag, tone) => group ? `<div class="mf-lane ${tone}"><span class="mf-lane-tag">${tag}</span><div class="mf-lane-teams">${group.teams.map((team) => `<span>#${team.teamNumber} ${esc(team.teamName)}</span>`).join("")}</div><div class="mf-odo" data-odo="${group.total}">0</div></div>` : "";
    main().innerHTML = `<div class="mf-duel">${rank2 ? `${lane(rank2, "NHÓM BÁM ĐUỔI", "is-chasing")}<div class="mf-vs">VS</div>` : ""}${lane(rank1, "NHÓM DẪN ĐẦU", "is-leading")}</div>`;
    root.querySelectorAll(".mf-odo").forEach((element, index) => {
      const target = Number(element.dataset.odo) || 0;
      if (!animate) {
        element.textContent = fmtNum(target);
        return;
      }
      // Dừng ngay sát vạch: lệch 5-20 điểm tùy revision để lần chạy nào cũng khác.
      const stopShort = 5 + ((payload.revision + index * 7) % 16);
      const pauseAt = Math.max(0, target - stopShort);
      const start = performance.now();
      const step = (now) => {
        const progress = Math.min(1, (now - start) / ODO_MS);
        const eased = 1 - Math.pow(1 - progress, 3);
        element.textContent = fmtNum(Math.round(pauseAt * eased));
        if (progress < 1) rafIds.push(window.requestAnimationFrame(step));
      };
      rafIds.push(window.requestAnimationFrame(step));
    });
  }

  function renderRunnerUp() {
    renderFinalBoard("RUNNER_UP", (row, team, group) => {
      if (!group) return;
      if (group.rank === 2) {
        row.classList.add("is-silver");
        setBadge(row, "Á QUÂN", "silver");
      } else if (group.rank === 3) {
        row.classList.add("is-bronze");
        setBadge(row, "HẠNG 3", "bronze");
      } else if (group.rank > 3) {
        row.classList.add("is-out");
      }
    });
  }

  function renderChampion() {
    setStage("CHAMPION");
    setHeading("KHOẢNH KHẮC VINH QUANG", "QUÁN QUÂN", "Nhà vô địch Company Trip năm nay");
    buildBoard();
    const container = root.querySelector(".mf-rows");
    const ordered = teams().slice().sort((a, b) => a.total !== b.total ? b.total - a.total : a.teamNumber - b.teamNumber);
    ordered.forEach((team) => {
      const row = rowOf(team.teamId);
      const group = groupOf(team.teamId);
      COLUMN_KEYS.forEach((key) => setCell(row, key, team, COLUMN_KEYS));
      row.querySelector(".mf-total").textContent = fmtNum(team.total);
      if (!group || group.rank > 3) row.classList.add("is-out");
      else if (group.rank === 1) {
        row.classList.add("is-gold");
        setBadge(row, "QUÁN QUÂN", "gold");
      } else if (group.rank === 2) {
        row.classList.add("is-silver");
        setBadge(row, "Á QUÂN", "silver");
      } else if (group.rank === 3) {
        row.classList.add("is-bronze");
        setBadge(row, "HẠNG 3", "bronze");
      }
      container.append(row);
    });
  }

  function renderBoard() {
    renderFinalBoard("BOARD", (row, team, group) => {
      const rankNo = row.querySelector(".mf-rankno");
      rankNo.hidden = false;
      rankNo.textContent = group ? String(group.rank) : "—";
      if (group && group.rank === 1) {
        row.classList.add("is-gold");
        setBadge(row, "QUÁN QUÂN", "gold");
      } else if (group && group.rank === 2) {
        row.classList.add("is-silver");
        setBadge(row, "Á QUÂN", "silver");
      } else if (group && group.rank === 3) {
        row.classList.add("is-bronze");
        setBadge(row, "HẠNG 3", "bronze");
      }
    });
    setHeading("FINAL BOARD", "BẢNG TỔNG SẮP CHUNG CUỘC", "Cảm ơn tất cả các đội — hẹn mùa sau");
  }

  function renderReady() {
    setStage("READY");
    setHeading("MAC MARKETING", "RACE TO THE CROWN", "Màn công bố tổng điểm chung cuộc");
    main().innerHTML = `<div class="mf-ready"><div class="mf-ready-crown" aria-hidden="true">♛</div><strong>Chờ tín hiệu từ ban tổ chức</strong><span>Điểm tổng 4 mặt trận sẽ được công bố ngay tại đây</span></div>`;
  }

  function apply() {
    clearLocalTimers();
    const stage = payload.state;
    const snapshot = payload.snapshot;
    // Vào lại trạng thái quen (LÙI, F5, mở màn giữa chừng) thì render tĩnh, không replay.
    const animate = lastRevByState[stage] !== payload.revision;
    lastRevByState[stage] = payload.revision;
    if (stage === "READY" || !snapshot) {
      renderReady();
      return;
    }
    if (ROUND_KEYS[stage]) {
      renderBuild(stage, animate);
      return;
    }
    if (stage === "LOCKED") {
      renderLocked(animate);
      return;
    }
    if (stage === "BOTTOM") {
      setHeading("LOẠI DẦN", "AI PHẢI RỜI CUỘC ĐUA?", "Công bố lần lượt từ nhóm hạng thấp nhất");
      renderBottom(animate);
      return;
    }
    if (stage === "TOP3") {
      setHeading("SÂN KHẤU THU HẸP", "CHỈ CÒN TOP ĐẦU", "Những đội còn trụ lại trong cuộc đua vương miện");
      renderTop3();
      return;
    }
    if (stage === "BRONZE") {
      setHeading("CÔNG BỐ", "HẠNG BA CHUNG CUỘC", "Nỗ lực xứng đáng — một tràng pháo tay");
      renderBronze();
      return;
    }
    if (stage === "DUEL") {
      renderDuel(animate);
      return;
    }
    if (stage === "RUNNER_UP") {
      setHeading("CÔNG BỐ", "Á QUÂN CHUNG CUỘC", "Chỉ còn một cái tên cuối cùng");
      renderRunnerUp();
      return;
    }
    if (stage === "CHAMPION") {
      renderChampion();
      return;
    }
    if (stage === "BOARD") {
      renderBoard();
    }
  }

  async function poll() {
    if (document.hidden) return;
    try {
      const response = await fetch(`${endpoint}?_=${Date.now()}`, { cache: "no-store", credentials: "same-origin" });
      if (!response.ok) throw new Error("bad status");
      const body = await response.json();
      failedPolls = 0;
      setConnection(true);
      if (body.revision !== lastRevision) {
        lastRevision = body.revision;
        payload = body;
        apply();
      }
    } catch {
      failedPolls += 1;
      if (failedPolls >= 2) setConnection(false);
    }
  }

  shell();
  poll();
  pollTimer = window.setInterval(poll, 900);
  window.addEventListener("focus", poll);
  void pollTimer;
})();
