(() => {
  "use strict";

  let state = window.AUCTION_INITIAL_STATE || {};
  const preferredView = String(window.AO_VIEW || "auto").toLowerCase();
  const syncMode = Boolean(window.AO_SYNC) || preferredView === "auto" || preferredView === "";
  let teamId = window.AO_TEAM_ID ? Number(window.AO_TEAM_ID) : null;
  let flashTimer = null;
  let lastEventKey = null;

  const el = (id) => document.getElementById(id);
  const show = (node, on) => {
    if (!node) return;
    node.hidden = !on;
  };

  function fmt(n) {
    return "₹ " + Number(n || 0).toLocaleString("en-IN");
  }

  /** Compact purse for all-teams cards (e.g. ₹ 2.1L). */
  function fmtCompact(n) {
    const v = Number(n || 0);
    if (v >= 10000000) {
      const cr = v / 10000000;
      return `₹ ${cr % 1 === 0 ? cr.toFixed(0) : cr.toFixed(1)}Cr`;
    }
    if (v >= 100000) {
      const lakh = v / 100000;
      return `₹ ${lakh % 1 === 0 ? lakh.toFixed(0) : lakh.toFixed(1)}L`;
    }
    if (v >= 1000) return `₹ ${Math.round(v / 1000)}K`;
    return fmt(v);
  }

  function teamsColumnCount(n) {
    if (n <= 4) return 2;
    if (n <= 9) return 3;
    if (n <= 16) return 4;
    return 5;
  }

  function leagueName(s) {
    const custom = s?.rules?.league_title;
    if (custom) return String(custom).toUpperCase();
    return String(s.tournamentName || s.auctionName || "PLAYER AUCTION").toUpperCase();
  }

  function shortLeague(s) {
    const custom = s?.rules?.league_title;
    if (custom) return String(custom).toUpperCase().slice(0, 18);
    const name = String(s.auctionName || s.tournamentName || "TAM2").toUpperCase();
    return name.length > 18 ? name.slice(0, 16) + "…" : name;
  }

  function yearLine(s) {
    return `PLAYER AUCTION ${s.year || new Date().getFullYear()}`;
  }

  const DEFAULT_AVATAR = "/assets/graphics/defaultplayeravatar.png";
  const DEFAULT_TEAM = "/assets/graphics/default_team.png";

  function setPhoto(imgId, phId, url) {
    const img = el(imgId);
    const ph = el(phId);
    if (!img) return;
    img.src = url || DEFAULT_AVATAR;
    img.hidden = false;
    if (ph) ph.hidden = true;
  }

  function setTeamLogo(containerId, logoUrl, name) {
    const box = el(containerId);
    if (!box) return;
    const src = logoUrl || DEFAULT_TEAM;
    box.innerHTML = `<img src="${src}" alt="${(name || "Team").replace(/"/g, "")}" />`;
  }

  function roleLabel(p) {
    return (p?.primaryRole || p?.role || "PLAYER").toUpperCase();
  }

  function styleLine(p) {
    return (p?.styleLine || p?.battingStyle || "").toUpperCase() || "—";
  }

  function roleLine(p) {
    const role = roleLabel(p);
    let style = String(p?.styleLine || p?.battingStyle || "").trim().toUpperCase();
    style = style.replace(/\s*BAT(TING)?$/i, "").trim();
    if (!style || style === "—") return role;
    if (role.includes(style) || style.includes(role)) return style.length >= role.length ? style : role;
    if (role === "BATTER") return `${style} BATTER`;
    return `${style} ${role}`.replace(/\s+/g, " ").trim();
  }

  function playerCode(p) {
    const code = p?.registrationCode || "";
    const num = code.split("-").pop() || code || "—";
    return `PLAYER # ${num}`;
  }

  function playerHash(p) {
    const code = p?.registrationCode || "";
    const num = String(code.split("-").pop() || code || "00000").replace(/^#/, "");
    return `#${num}`;
  }

  /* ── View switching ── */
  function resolveSyncedView(s) {
    const v = String(s.broadcastView || "player").toLowerCase();
    if (s.broadcastTeamId) teamId = Number(s.broadcastTeamId);
    if (v === "teams") return "teams";
    if (v === "team") return "team";
    // Camera overlay stays on split so the video hole remains open,
    // but renderSplit will clear chrome when no player is on block.
    if (v === "split") return "split";
    if (v === "idle") return "idle";
    return s.currentPlayer ? "player" : "idle";
  }

  function activeBaseView(s) {
    if (syncMode) return resolveSyncedView(s);
    if (preferredView === "teams") return "teams";
    if (preferredView === "team") return "team";
    // Locked camera URL: keep split (transparent hole), idle chrome when no player
    if (preferredView === "split") return "split";
    if (s.currentPlayer && (s.status === "live" || s.status === "paused" || s.status === "published")) {
      return "player";
    }
    return "idle";
  }

  function showView(name) {
    ["player", "split", "team", "teams", "idle"].forEach((v) => {
      show(el(`view-${v}`), v === name);
    });
    const isCam = name === "split";
    const isTeams = name === "teams";
    const isPlayer = name === "player";
    document.body.classList.toggle("ao-camera-mode", isCam);
    document.body.classList.toggle("ao-teams-mode", isTeams);
    document.body.classList.toggle("ao-player-mode", isPlayer);
    if (!isCam) document.body.classList.remove("ao-camera-idle");
    const bg = document.querySelector(".ao-stage-bg");
    if (bg) bg.hidden = isCam || isPlayer;
  }

  let timerKey = null;
  let timerInterval = null;
  let lastBidKey = null;
  let lastPlayerId = null;
  let lastCamPlayerId = null;
  let shownView = null;
  let viewAnimLock = false;
  let playerAnimLock = false;
  let queuedRender = null;
  let queuedPlayerState = null;
  const TEAM_ACCENTS = ["#f97316", "#2563eb", "#a855f7", "#ef4444", "#14b8a6", "#eab308"];

  function bump(node, cls, ms = 600) {
    if (!node) return;
    node.classList.remove(cls);
    void node.offsetWidth;
    node.classList.add(cls);
    setTimeout(() => node.classList.remove(cls), ms);
  }

  function durationMs(value, fallback = 850) {
    if (!value) return fallback;
    const raw = String(value);
    if (raw.endsWith("ms")) return Number(raw.slice(0, -2)) || fallback;
    if (raw.endsWith("s")) return Number(raw.slice(0, -1)) * 1000 || fallback;
    return Number(raw) || fallback;
  }

  function clearAnimate(node) {
    if (!node) return;
    [...node.classList]
      .filter((c) => c === "animate__animated" || c.startsWith("animate__"))
      .forEach((c) => node.classList.remove(c));
    node.style.removeProperty("--animate-delay");
    node.style.removeProperty("--animate-duration");
  }

  function playAnimate(node, name, opts = {}) {
    return new Promise((resolve) => {
      if (!node || !name) {
        resolve();
        return;
      }
      const next = `animate__${name}`;
      const others = [...node.classList].filter(
        (c) => c.startsWith("animate__") && c !== "animate__animated" && c !== next
      );
      others.forEach((c) => node.classList.remove(c));
      node.classList.remove("animate__faster");
      if (node.classList.contains(next)) {
        node.classList.remove(next);
        void node.offsetWidth;
      }
      const delay = Number(opts.delay || 0);
      if (delay) node.style.setProperty("--animate-delay", `${delay}s`);
      else node.style.removeProperty("--animate-delay");
      if (opts.duration) node.style.setProperty("--animate-duration", opts.duration);
      else node.style.removeProperty("--animate-duration");
      node.classList.add("animate__animated", next);
      if (opts.faster) node.classList.add("animate__faster");
      let settled = false;
      const finish = (ev) => {
        if (ev && ev.target !== node) return;
        if (settled) return;
        settled = true;
        node.removeEventListener("animationend", finish);
        if (!opts.hold) {
          node.classList.remove(next, "animate__faster", "animate__animated");
          node.style.removeProperty("--animate-delay");
          node.style.removeProperty("--animate-duration");
        }
        resolve();
      };
      node.addEventListener("animationend", finish);
      setTimeout(finish, delay * 1000 + durationMs(opts.duration, opts.faster ? 500 : 850) + 120);
    });
  }

  function playMany(list) {
    return Promise.all((list || []).map((item) => playAnimate(item.node, item.name, item)));
  }

  function playerPieces() {
    return {
      top: document.querySelector(".ao-view-player .ao-fs-top"),
      spine: el("ap-spine"),
      portrait: document.querySelector(".ao-view-player .ao-wp-photo"),
      identity: document.querySelector(".ao-view-player .ao-fs-identity"),
      price: document.querySelector(".ao-view-player .ao-fs-pricebar"),
      foot: document.querySelector(".ao-view-player .ao-fs-foot"),
    };
  }

  function triggerPlayerEnter() {
    const p = playerPieces();
    return playMany([
      { node: p.top, name: "fadeInDown" },
      { node: p.spine, name: "fadeIn", delay: 0.05 },
      { node: p.portrait, name: "zoomIn", delay: 0.08 },
      { node: p.identity, name: "fadeInLeft", delay: 0.12 },
      { node: p.price, name: "slideInUp", delay: 0.18 },
      { node: p.foot, name: "fadeInUp", delay: 0.22 },
    ]);
  }

  function playPlayerExit() {
    const p = playerPieces();
    const stack = el("ap-bid-stack");
    return playMany([
      { node: stack, name: "fadeOutRight", hold: true },
      { node: p.price, name: "slideOutDown", hold: true },
      { node: p.identity, name: "fadeOutLeft", delay: 0.04, hold: true },
      { node: p.portrait, name: "zoomOut", delay: 0.05, hold: true },
      { node: p.top, name: "fadeOutUp", delay: 0.06, hold: true },
      { node: p.foot, name: "fadeOut", delay: 0.06, hold: true },
      { node: p.spine, name: "fadeOut", delay: 0.06, hold: true },
    ]);
  }

  function triggerCameraEnter() {
    return playMany([
      { node: document.querySelector(".ao-cam-rail"), name: "fadeInLeft" },
      { node: document.querySelector(".ao-cam-portrait"), name: "zoomIn", delay: 0.08 },
      { node: document.querySelector(".ao-cam-identity"), name: "fadeInUp", delay: 0.12 },
      { node: document.querySelector(".ao-cam-bottom"), name: "slideInUp", delay: 0.16 },
    ]);
  }

  function resetBidStack() {
    const stack = el("ap-bid-stack");
    if (!stack) return;
    stack.hidden = true;
    stack.innerHTML = "";
    stack.dataset.newestKey = "";
    stack.dataset.bidSig = "";
    clearAnimate(stack);
  }

  function recentBids(s, extra = {}) {
    const hist = [...(s.bidHistory || [])];
    const amount = Number(extra.amount || s.currentBid || 0);
    const teamName = extra.teamName || s.highestBidder || "";
    const teamId = extra.teamId || s.highestBidderId;
    if (amount > 0) {
      const exists = hist.some(
        (b) =>
          Number(b.amount) === amount &&
          (String(b.teamId || "") === String(teamId || "") ||
            String(b.teamName || "") === String(teamName))
      );
      if (!exists) {
        hist.unshift({
          teamId,
          teamName,
          teamLogo: extra.teamLogo || s.highestBidderLogo,
          amount,
        });
      }
    }
    return hist.slice(0, 5);
  }

  function renderBidStack(s, extra = {}) {
    const stack = el("ap-bid-stack");
    if (!stack) return;
    const hist = recentBids(s, extra);
    if (!hist.length) {
      stack.hidden = true;
      stack.innerHTML = "";
      stack.dataset.newestKey = "";
      stack.dataset.bidSig = "";
      return;
    }

    const newestKey = `${hist[0].teamId || hist[0].teamName || ""}:${hist[0].amount || 0}`;
    const sig = bidSignature(hist);
    if (sig === stack.dataset.bidSig && stack.childElementCount) return;

    const isNewBid = Boolean(newestKey && newestKey !== stack.dataset.newestKey);
    stack.dataset.bidSig = sig;
    stack.dataset.newestKey = newestKey;
    stack.hidden = false;
    stack.innerHTML = hist
      .map((b, i) => {
        const lead = i === 0;
        const slide = isNewBid && lead ? "animate__animated animate__bounceInRight animate__faster" : "";
        const name = String(b.teamName || "TEAM").toUpperCase();
        return `<article class="ao-bid-toast ${lead ? "is-lead" : "is-past"} ${slide}">
          <div class="ao-bid-toast-kicker">${lead ? "CURRENT BID" : "PREVIOUS BID"}</div>
          <div class="ao-bid-toast-row">
            <div class="ao-team-logo ao-bid-toast-logo"><img src="${b.teamLogo || DEFAULT_TEAM}" alt="" /></div>
            <div class="ao-bid-toast-meta">
              <strong>${name}</strong>
              <span>${fmt(b.amount)}</span>
            </div>
          </div>
        </article>`;
      })
      .join("");
  }

  function triggerBidAnimation(s, extra = {}) {
    const amount = Number(extra.amount ?? s.currentBid ?? 0);
    if (amount < 1 && !(s.bidHistory || []).length) return;
    renderBidStack(s, extra);
    playAnimate(el("ap-bid"), "pulse", { duration: "0.6s", faster: true });
    playAnimate(el("as-bid"), "pulse", { duration: "0.6s", faster: true });
  }

  function splitName(p) {
    if (p?.firstName || p?.lastName) {
      return { first: p.firstName || "—", last: p.lastName || "" };
    }
    const parts = String(p?.playerName || "—").trim().split(/\s+/);
    if (parts.length === 1) return { first: parts[0].toUpperCase(), last: "" };
    const last = parts.pop();
    return { first: parts.join(" ").toUpperCase(), last: last.toUpperCase() };
  }

  function auctionStatusText(s) {
    if (s.status === "paused") return "PAUSED";
    if (s.status === "completed") return "COMPLETED";
    if (!s.currentPlayer) return "WAITING";
    if (Number(s.currentBid) > 0) return "BIDDING OPEN";
    return "BIDDING OPEN";
  }

  function renderBidHistory(s) {
    renderBidHistoryInto("ap-bid-history", s);
    renderBidHistoryInto("as-bid-history", s);
  }

  function bidSignature(hist) {
    return (hist || []).map((b) => `${b.teamId || ""}:${b.amount || 0}`).join("|");
  }

  function renderBidHistoryInto(wrapId, s) {
    const wrap = el(wrapId);
    if (!wrap) return;
    const hist = (s.bidHistory || []).slice(0, 4);
    const sig = bidSignature(hist);
    const prevSig = wrap.dataset.bidSig || "";
    if (sig === prevSig && wrap.childElementCount) return;

    const newestKey = hist[0] ? `${hist[0].teamId || ""}:${hist[0].amount || 0}` : "";
    const isNewBid = Boolean(newestKey && newestKey !== wrap.dataset.newestKey);

    wrap.dataset.bidSig = sig;
    wrap.dataset.newestKey = newestKey;

    if (!hist.length) {
      wrap.innerHTML = `<div class="ao-fs-bidchip" style="opacity:.5"><div class="ao-fs-bidchip-meta"><div class="ao-fs-bidchip-name">NO BIDS YET</div><div class="ao-fs-bidchip-amt">—</div></div></div>`;
      return;
    }
    const leadId = s.highestBidderId;
    wrap.innerHTML = hist
      .map((b, i) => {
        const lead = Number(b.teamId) === Number(leadId) && i === 0;
        const color = TEAM_ACCENTS[i % TEAM_ACCENTS.length];
        const slide = isNewBid && i === 0 ? "is-bid-slide" : "";
        return `<div class="ao-fs-bidchip ${lead ? "is-lead" : ""} ${slide}" style="--chip-i:${i}">
          <span class="ao-fs-bidchip-accent" style="background:${color}"></span>
          <div class="ao-team-logo ao-team-logo-sm"><img src="${b.teamLogo || DEFAULT_TEAM}" alt="" /></div>
          <div class="ao-fs-bidchip-meta">
            <div class="ao-fs-bidchip-name">${(b.teamName || "—").toUpperCase()}</div>
            <div class="ao-fs-bidchip-amt">${fmt(b.amount)}</div>
          </div>
          ${lead ? '<span class="ao-fs-bidchip-arrow">▲</span>' : ""}
        </div>`;
      })
      .join("");
  }

  function updateTimer(s) {
    const total = Number(s.bidTimerSeconds || 30);
    const started = s.bidTimerStartedAt ? new Date(s.bidTimerStartedAt).getTime() : Date.now();
    const key = `${s.currentPlayer?.auctionPlayerId || 0}:${s.currentBid}:${s.bidTimerStartedAt || ""}`;

    const tick = () => {
      const elapsed = Math.floor((Date.now() - started) / 1000);
      const left = Math.max(0, total - elapsed);
      ["ap-timer", "as-timer"].forEach((id) => {
        const timerEl = el(id);
        if (timerEl) timerEl.textContent = String(left);
      });
      ["ap-timer-ring", "as-timer-ring"].forEach((id) => {
        const ring = el(id);
        if (!ring) return;
        const circ = 2 * Math.PI * 42;
        const pct = total > 0 ? left / total : 0;
        ring.style.strokeDasharray = String(circ);
        ring.style.strokeDashoffset = String(circ * (1 - pct));
      });
    };

    if (key !== timerKey) {
      timerKey = key;
      clearInterval(timerInterval);
      tick();
      timerInterval = setInterval(tick, 250);
    } else {
      tick();
    }
  }

  /* ── Player full-screen card ── */
  function paintPlayerCard(s) {
    const p = s.currentPlayer;
    if (!p) return;
    const league = leagueName(s);
    const names = splitName(p);
    const stats = p.statistics || {};

    if (el("ap-league")) el("ap-league").textContent = league;
    if (el("ap-league-sub")) el("ap-league-sub").textContent = "PLAYER AUCTION";
    if (el("ap-year-badge")) el("ap-year-badge").textContent = String(s.year || new Date().getFullYear());
    if (el("ap-footer")) {
      el("ap-footer").textContent = `${league} ${s.year || new Date().getFullYear()}`;
    }

    el("ap-firstname").textContent = names.first;
    el("ap-lastname").textContent = names.last || "—";
    if (el("ap-spine")) {
      const spine = String(names.last || names.first || "PLAYER").toUpperCase();
      el("ap-spine").textContent = spine.split("").join("\n");
    }
    if (el("ap-role")) el("ap-role").textContent = roleLabel(p);
    if (el("ap-style")) el("ap-style").textContent = styleLine(p);
    if (el("ap-roleline")) el("ap-roleline").textContent = roleLine(p);
    el("ap-id").textContent = playerHash(p);

    el("ap-age").textContent = p.age != null ? String(p.age) : "—";
    el("ap-runs").textContent = stats.runs != null && stats.runs !== "" ? String(stats.runs) : "—";
    el("ap-matches").textContent = stats.matches != null && stats.matches !== "" ? String(stats.matches) : "—";
    el("ap-sr").textContent = stats.strikeRate != null && stats.strikeRate !== "" ? String(stats.strikeRate) : "—";

    el("ap-base").textContent = fmt(p.basePrice);
    el("ap-bid").textContent = fmt(s.currentBid > 0 ? s.currentBid : p.basePrice);
    el("ap-bidder").textContent = (s.highestBidder || "—").toUpperCase();
    el("ap-status-text").textContent = auctionStatusText(s);

    setPhoto("ap-photo", null, p.playerPhoto);
    setTeamLogo("ap-team-logo", s.highestBidderLogo, s.highestBidder);
    renderBidHistory(s);
    renderBidStack(s);
    updateTimer(s);

    const playerId = p.auctionPlayerId;
    const bidKey = `${playerId}:${Number(s.currentBid || 0)}:${s.highestBidderId || ""}`;
    if (lastBidKey != null && bidKey !== lastBidKey && Number(s.currentBid || 0) > 0) {
      triggerBidAnimation(s);
    }
    lastBidKey = bidKey;
  }

  function renderPlayerCard(s) {
    const p = s.currentPlayer;
    if (!p) return;
    const playerId = p.auctionPlayerId;

    if (playerAnimLock) {
      queuedPlayerState = s;
      return;
    }

    if (lastPlayerId && playerId !== lastPlayerId) {
      playerAnimLock = true;
      playPlayerExit().then(() => {
        lastPlayerId = playerId;
        lastBidKey = `${playerId}:0`;
        resetBidStack();
        const next = queuedPlayerState || s;
        queuedPlayerState = null;
        playerAnimLock = false;
        paintPlayerCard(next);
        triggerPlayerEnter();
        if (queuedPlayerState) renderPlayerCard(queuedPlayerState);
      });
      return;
    }

    if (playerId !== lastPlayerId) {
      lastPlayerId = playerId;
      lastBidKey = `${playerId}:0`;
      resetBidStack();
      paintPlayerCard(s);
      triggerPlayerEnter();
      return;
    }

    paintPlayerCard(s);
  }

  /* ── Camera overlay (left rail + transparent video hole) ── */
  function renderSplit(s) {
    const cam = document.querySelector(".ao-cam");
    const league = leagueName(s);
    if (el("as-league")) el("as-league").textContent = `— ${shortLeague(s)} —`;
    if (el("as-year")) el("as-year").textContent = String(s.year || new Date().getFullYear());
    if (el("as-footer")) el("as-footer").textContent = `${league} · PLAYER AUCTION`;

    const p = s.currentPlayer;
    // No player on block → fully idle / clear. Keep camera hole empty (transparent).
    if (!p) {
      document.body.classList.add("ao-camera-idle");
      cam?.classList.add("is-idle");
      if (el("as-status-text")) el("as-status-text").textContent = "WAITING";
      renderBidHistoryInto("as-bid-history", s);
      updateTimer(s);
      lastCamPlayerId = null;
      return;
    }

    document.body.classList.remove("ao-camera-idle");
    cam?.classList.remove("is-idle");

    const names = splitName(p);
    const stats = p.statistics || {};

    if (el("as-firstname")) el("as-firstname").textContent = names.first;
    if (el("as-lastname")) el("as-lastname").textContent = names.last || "—";
    if (el("as-role")) el("as-role").textContent = roleLabel(p);
    if (el("as-style")) el("as-style").textContent = styleLine(p);
    if (el("as-id")) el("as-id").textContent = playerCode(p);

    if (el("as-age")) el("as-age").textContent = p.age != null ? String(p.age) : "—";
    if (el("as-runs")) el("as-runs").textContent = stats.runs != null && stats.runs !== "" ? String(stats.runs) : "—";
    if (el("as-matches")) el("as-matches").textContent = stats.matches != null && stats.matches !== "" ? String(stats.matches) : "—";
    if (el("as-sr")) el("as-sr").textContent = stats.strikeRate != null && stats.strikeRate !== "" ? String(stats.strikeRate) : "—";

    if (el("as-base")) el("as-base").textContent = fmt(p.basePrice);
    if (el("as-bid")) el("as-bid").textContent = fmt(s.currentBid > 0 ? s.currentBid : p.basePrice);
    if (el("as-bidder")) el("as-bidder").textContent = (s.highestBidder || "—").toUpperCase();
    if (el("as-status-text")) el("as-status-text").textContent = auctionStatusText(s);

    setPhoto("as-photo", null, p.playerPhoto);
    setTeamLogo("as-team-logo", s.highestBidderLogo, s.highestBidder);
    renderBidHistoryInto("as-bid-history", s);
    updateTimer(s);

    const playerId = p.auctionPlayerId;
    if (playerId !== lastCamPlayerId) {
      lastCamPlayerId = playerId;
      triggerCameraEnter();
    }
  }

  /* ── Team display ── */
  function pickTeam(s) {
    const teams = s.teams || [];
    if (!teams.length) return null;
    const wanted = s.broadcastTeamId || teamId;
    if (wanted) {
      const found = teams.find((t) => Number(t.id) === Number(wanted));
      if (found) return found;
    }
    if (s.highestBidderId) {
      const hb = teams.find((t) => Number(t.id) === Number(s.highestBidderId));
      if (hb) return hb;
    }
    return teams[0];
  }

  function renderTeam(s) {
    const t = pickTeam(s);
    if (!t) return;
    const nameEl = el("at-name");
    if (nameEl) nameEl.textContent = (t.name || "—").toUpperCase();
    setTeamLogo("at-logo", t.logo, t.name);
    renderSquadGrid(t);
  }

  function renderSquadGrid(t) {
    const grid = el("at-grid");
    if (!grid) return;
    const squad = t.squad || [];
    grid.style.gridTemplateColumns = "";
    grid.style.gridTemplateRows = "";
    if (!squad.length) {
      grid.innerHTML = `<div class="ao-pcard-none">NO PLAYERS YET</div>`;
      return;
    }
    grid.innerHTML = squad
      .map((p) => {
        const name = (p.playerName || "—").toUpperCase();
        const src = p.playerPhoto || DEFAULT_AVATAR;
        return `<article class="ao-pcard">
          <div class="ao-pcard-art">
            <img class="ao-pcard-frame" src="/assets/graphics/playercard_background-graphics.png" alt="" />
            <div class="ao-pcard-player">
              <img class="ao-pcard-img" src="${src}" alt="" />
            </div>
          </div>
          <div class="ao-pcard-note">
            <div class="ao-pcard-name">${name}</div>
            <div class="ao-pcard-meta">
              <span class="ao-pcard-role">${roleLabel(p)}</span>
              <span class="ao-pcard-price">${fmt(p.soldPrice)}</span>
            </div>
          </div>
        </article>`;
      })
      .join("");
  }

  /* ── SOLD / UNSOLD flash ── */
  function splitPlayerName(full) {
    const parts = String(full || "—").trim().split(/\s+/).filter(Boolean);
    if (parts.length <= 1) return { first: "", last: (parts[0] || "—").toUpperCase() };
    return {
      first: parts.slice(0, -1).join(" ").toUpperCase(),
      last: parts[parts.length - 1].toUpperCase(),
    };
  }

  function hideFlashAnimated(node, exitName = "zoomOut") {
    if (!node || node.hidden) return;
    node.classList.add("is-exiting");
    const stage = node.querySelector(".ao-fx-stage");
    clearTimeout(node._exitTimer);
    playAnimate(stage, exitName, { duration: "0.75s", hold: true }).then(() => {
      show(node, false);
      node.classList.remove("is-exiting");
      delete node.dataset.from;
      clearAnimate(stage);
    });
  }

  function openFlash(node, enterName, extras = {}) {
    const other = node.id === "flash-sold" ? el("flash-unsold") : el("flash-sold");
    hideFlashAnimated(other, "fadeOut");
    clearTimeout(flashTimer);
    node.classList.remove("is-exiting");
    show(node, true);
    const stage = node.querySelector(".ao-fx-stage");
    const stamp = node.querySelector(".ao-fx-stamp");
    const price = node.querySelector(".ao-fx-price");
    const book = node.querySelector(".ao-fx-book");
    [stage, book, ...node.querySelectorAll(".ao-fx-face, .ao-fx-stamp em, .ao-fx-kicker, .ao-fx-rule, .ao-fx-identity, .ao-fx-price, .ao-fx-team, .ao-fx-sub, .ao-fx-player img")].forEach((piece) => {
      if (!piece) return;
      piece.style.animation = "none";
      void piece.offsetWidth;
      piece.style.animation = "";
    });
    playAnimate(stage, enterName, { duration: "0.95s" });
    if (stamp && extras.stamp) {
      playAnimate(stamp, extras.stamp, { delay: 0.28, duration: "0.9s" });
    }
    if (price && extras.price && !price.hidden) {
      playAnimate(price, extras.price, { delay: 0.42, duration: "0.8s" });
    }
    flashTimer = setTimeout(() => hideFlashAnimated(node, extras.exit || "zoomOut"), 5500);
  }

  function showSold(payload) {
    const names = splitPlayerName(payload.playerName);
    const first = el("sold-firstname");
    if (first) first.textContent = names.first;
    el("sold-name").textContent = names.last;
    el("sold-team").textContent = (payload.soldTeamName || payload.highestBidder || "—").toUpperCase();
    el("sold-price").textContent = fmt(payload.soldPrice || payload.currentBid);
    setPhoto("sold-photo", null, payload.playerPhoto);
    setTeamLogo("sold-team-logo", payload.teamLogo || payload.highestBidderLogo, payload.soldTeamName);
    openFlash(el("flash-sold"), "zoomIn", { stamp: "tada", price: "pulse", exit: "zoomOut" });
  }

  function showUnsold(payload) {
    const names = splitPlayerName(payload.playerName);
    const first = el("unsold-firstname");
    if (first) first.textContent = names.first;
    el("unsold-name").textContent = names.last;
    const price = el("unsold-price");
    if (price) price.textContent = fmt(payload.basePrice);
    setPhoto("unsold-photo", null, payload.playerPhoto);
    openFlash(el("flash-unsold"), "backInDown", { stamp: "headShake", exit: "fadeOutDown" });
  }

  function maybeFlashFromState(s) {
    const ev = s.lastEvent;
    if (!ev || !ev.type || !ev.at) return;
    const key = `${ev.type}:${ev.at}`;
    if (key === lastEventKey) return;
    const age = Date.now() - new Date(ev.at).getTime();
    if (age > 8000) return;
    lastEventKey = key;
    if (ev.type === "SOLD") showSold(ev.payload || {});
    if (ev.type === "UNSOLD") showUnsold(ev.payload || {});
  }

  /* ── Main render ── */
  function paintActiveView(s, view, entering = false) {
    if (view === "player") renderPlayerCard(s);
    if (view === "split") renderSplit(s);
    if (view === "team") renderTeam(s);
    if (view === "teams") renderAllTeams(s);
    if (view === "idle" && entering) {
      playAnimate(document.querySelector(".ao-idle-card"), "zoomIn", { duration: "0.9s" });
    }
  }

  function finishViewChange(s, view) {
    const entering = shownView !== view;
    showView(view);
    shownView = view;
    paintActiveView(s, view, entering);
    maybeFlashFromState(s);
  }

  function render(s) {
    state = s;
    if (el("ai-league")) el("ai-league").textContent = leagueName(s);

    const view = activeBaseView(s);

    if (viewAnimLock) {
      queuedRender = s;
      return;
    }

    if (shownView === "player" && view !== "player") {
      viewAnimLock = true;
      playPlayerExit().then(() => {
        lastPlayerId = null;
        Object.values(playerPieces()).forEach(clearAnimate);
        clearAnimate(el("ap-bid-stack"));
        viewAnimLock = false;
        const next = queuedRender || s;
        queuedRender = null;
        finishViewChange(next, activeBaseView(next));
      });
      maybeFlashFromState(s);
      return;
    }

    finishViewChange(s, view);
  }

  function renderAllTeams(s) {
    const league = el("ats-league");
    if (league) league.textContent = leagueName(s);
    const grid = el("ats-grid");
    if (!grid) return;
    const teams = s.teams || [];
    const cols = teamsColumnCount(teams.length);
    grid.style.setProperty("--ats-cols", String(cols));
    grid.dataset.count = String(teams.length);
    grid.innerHTML = teams
      .map((t, i) => {
        const logoSrc = t.logo || DEFAULT_TEAM;
        const logo = `<img src="${logoSrc}" alt="${(t.name || "Team").replace(/"/g, "")}" />`;
        const joined = t.squadCount || 0;
        const accent = TEAM_ACCENTS[i % TEAM_ACCENTS.length];
        const name = (t.name || "").toUpperCase();
        return `
        <article class="ao-teams-card" style="--accent:${accent}">
          <div class="ao-teams-logo">${logo}</div>
          <div class="ao-teams-info">
            <h3 class="ao-teams-name">${name}</h3>
            <p class="ao-teams-players">${joined} PLAYERS</p>
            <p class="ao-teams-balance"><strong>${fmtCompact(t.remaining)}</strong> LEFT</p>
          </div>
        </article>`;
      })
      .join("");
  }

  function onAnimation(type, payload) {
    if (type === "SOLD") {
      lastEventKey = `SOLD:${Date.now()}`;
      showSold(payload || {});
    }
    if (type === "UNSOLD") {
      lastEventKey = `UNSOLD:${Date.now()}`;
      showUnsold(payload || {});
    }
    if (type === "BID_PLACED") {
      const amount = Number(payload?.amount || payload?.currentBid || state.currentBid || 0);
      if (amount > 0) {
        triggerBidAnimation(state, {
          amount,
          teamName: payload?.teamName || payload?.highestBidder || state.highestBidder,
          teamLogo: payload?.teamLogo || state.highestBidderLogo,
        });
      }
    }
  }

  /* Cycle teams with ← → when on team view */
  window.addEventListener("keydown", (e) => {
    if (preferredView !== "team") return;
    const teams = state.teams || [];
    if (!teams.length) return;
    const idx = Math.max(0, teams.findIndex((t) => Number(t.id) === Number(teamId || teams[0].id)));
    if (e.key === "ArrowRight") {
      const next = teams[(idx + 1) % teams.length];
      teamId = next.id;
      render(state);
    }
    if (e.key === "ArrowLeft") {
      const prev = teams[(idx - 1 + teams.length) % teams.length];
      teamId = prev.id;
      render(state);
    }
  });

  const auctionId = state.auctionId;
  const realtime = new CricketRealtime({
    room: window.AUCTION_ROOM,
    hydrateUrl: auctionId ? `/api/auctions/${auctionId}/state` : null,
    pollWhenLiveMs: 800,
    pollWhenOfflineMs: 400,
    onState: (s) => render(s),
    onAnimation: (type, payload) => onAnimation(type, payload),
  });
  realtime.start();
  render(state);
})();
