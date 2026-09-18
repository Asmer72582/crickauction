/**
 * Broadcast Graphics Engine — modular overlay system for OBS.
 * GPU-friendly transforms, enter/hold/exit lifecycle, team theming.
 */

(function (global) {
  "use strict";

  const DEFAULTS = {
    primaryColor: "#FFA503",
    secondaryColor: "#002153",
    accentColor: "#00d4ff",
    defaultAvatar: "/assets/player-avatar-default.svg",
  };

  function defaultAvatarUrl(src) {
    const s = safeStr(src);
    return s || DEFAULTS.defaultAvatar;
  }

  function playerAvatarHtml(src, initials, cls = "sc-avatar") {
    const url = defaultAvatarUrl(src);
    const fb = safeStr(initials, "?").slice(0, 2).toUpperCase();
    return `<div class="${cls}"><img src="${url}" alt="" onerror="this.onerror=null;this.src='${DEFAULTS.defaultAvatar}'"><span class="sc-fb" aria-hidden="true">${fb}</span></div>`;
  }

  function brandFrom(data, state) {
    return {
      organizerLogo: safeStr(data?.organizerLogo || state?.organizerLogo),
      streamerLogo: safeStr(data?.streamerLogo || state?.streamerLogo),
      playerAvatar: safeStr(data?.playerAvatar || data?.avatar || state?.playerAvatar),
      teamALogo: safeStr(data?.teamALogo || state?.teamA?.logo),
      teamBLogo: safeStr(data?.teamBLogo || state?.teamB?.logo),
    };
  }

  function imgOrFallback(src, fallback, cls) {
    const fb = safeStr(fallback, "?").slice(0, 3).toUpperCase();
    if (src) {
      return `<div class="${cls}"><img src="${src}" alt="" onerror="this.style.display='none';this.parentNode.querySelector('.fb').style.display='flex'"><span class="fb" style="display:none">${fb}</span></div>`;
    }
    return `<div class="${cls}"><span class="fb">${fb}</span></div>`;
  }

  function brandRail(b, extra = "") {
    return `
      <div class="ipl-brand-rail">
        ${imgOrFallback(b.organizerLogo, "ORG", "ipl-logo ipl-org")}
        ${extra}
        ${imgOrFallback(b.streamerLogo, "LIVE", "ipl-logo ipl-stream")}
      </div>`;
  }

  function shine() {
    return `<div class="ipl-shine" aria-hidden="true"></div>`;
  }

  function sparks(n = 12) {
    return Array.from({ length: n }, (_, i) =>
      `<span class="ipl-spark" style="--i:${i};--a:${(i / n) * 360}deg"></span>`
    ).join("");
  }

  const TIMING = {
    fast: 400,
    normal: 900,
    slow: 1200,
    hold: 2200,
    enter: 1100,
    exit: 1100,
  };

  /* ── Utilities ── */

  function safeStr(v, fallback = "") {
    if (v == null || v === "undefined" || Number.isNaN(v)) return fallback;
    return String(v).trim();
  }

  function parseOvers(oversStr) {
    const parts = safeStr(oversStr, "0.0").split(".");
    const overs = parseInt(parts[0], 10) || 0;
    const balls = parseInt(parts[1], 10) || 0;
    return overs + balls / 6;
  }

  function calcCRR(score, oversStr) {
    const overs = parseOvers(oversStr);
    if (overs <= 0) return "0.00";
    return (score / overs).toFixed(2);
  }

  function calcRRR(chasing, target, oversStr, totalOvers) {
    const needed = target - chasing;
    const overs = parseOvers(oversStr);
    const remaining = Math.max(0, totalOvers - overs);
    if (needed <= 0 || remaining <= 0) return null;
    return (needed / remaining).toFixed(2);
  }

  function ballsRemaining(oversStr, totalOvers) {
    const overs = parseOvers(oversStr);
    const rem = Math.max(0, totalOvers - overs);
    return Math.ceil(rem * 6);
  }

  function parseBatsmanLine(line) {
    const t = safeStr(line);
    if (!t) return { name: "", runs: "", balls: "", onStrike: false };
    const strike = t.includes("*");
    const cleaned = t.replace(/\*/g, "").trim();
    const paren = cleaned.match(/^(.+?)\s+(\d+)\s*\((\d+)\)$/);
    if (paren) return { name: paren[1].trim(), runs: paren[2], balls: paren[3], onStrike: strike };
    const simple = cleaned.match(/^(.+?)\s+(\d+)\s+(\d+)$/);
    if (simple) return { name: simple[1].trim(), runs: simple[2], balls: simple[3], onStrike: strike };
    const compact = cleaned.match(/^(.+?)\s+(\d+)$/);
    if (compact) return { name: compact[1].trim(), runs: compact[2], balls: "", onStrike: strike };
    return { name: cleaned, runs: "", balls: "", onStrike: strike };
  }

  function parseBowlerLine(line) {
    const t = safeStr(line);
    if (!t) return { name: "", figures: "" };
    const m = t.match(/^(.+?)\s+([\d.-]+\s*\([\d.]+\))$/);
    if (m) return { name: m[1].trim(), figures: m[2].trim() };
    const m2 = t.match(/^(.+?)\s+([\d/\-]+.*)$/);
    if (m2) return { name: m2[1].trim(), figures: m2[2].trim() };
    return { name: t, figures: "" };
  }

  function teamShortName(team) {
    return safeStr(team.shortName) || safeStr(team.name).slice(0, 3).toUpperCase() || "TM";
  }

  function teamColors(team, fallbackHue) {
    const primary = safeStr(team.primaryColor) || fallbackHue || DEFAULTS.primaryColor;
    const secondary = safeStr(team.secondaryColor) || DEFAULTS.secondaryColor;
    return { primary, secondary };
  }

  function el(tag, className, html) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (html != null) node.innerHTML = html;
    return node;
  }

  function animateNumber(el, from, to, duration = 400) {
    if (!el || from === to) {
      if (el) el.textContent = to;
      return;
    }
    const start = performance.now();
    const diff = to - from;
    function frame(now) {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - t, 3);
      el.textContent = Math.round(from + diff * eased);
      if (t < 1) requestAnimationFrame(frame);
      else {
        el.textContent = to;
        el.classList.add("score-flash");
        setTimeout(() => el.classList.remove("score-flash"), 350);
      }
    }
    requestAnimationFrame(frame);
  }

  /* ── Base Graphic ── */

  class BaseGraphic {
    constructor(engine, id) {
      this.engine = engine;
      this.id = id;
      this.mapKey = null;
      this.node = null;
      this.timer = null;
    }

    mount(parent) {
      this.node = this.render();
      if (this.node) {
        this.node.classList.add("gfx-ready");
        this.applyStagger(this.node);
        parent.appendChild(this.node);
      }
      // Double rAF so the browser paints the initial state before enter starts
      requestAnimationFrame(() => {
        requestAnimationFrame(() => this.node?.classList.add("gfx-enter"));
      });
      return this;
    }

    applyStagger(root) {
      if (!root) return;
      const selectors = [
        ".mu-top-logo", ".mu-shield", ".mu-title", ".mu-team", ".mu-vs", ".mu-footer",
        ".tvv-title", ".tvv-match", ".tvv-box", ".tvv-names",
        ".ko-head", ".ko-titles", ".ko-format-tag", ".ko-stage", ".ko-fixture", ".ko-final-trophy", ".ko-final-caption", ".ko-foot",
        ".ms-brand", ".ms-pill", ".ms-block", ".ms-section-label", ".ms-cols",
        ".sq-brand", ".sq-title", ".sq-col", ".sq-team", ".sq-row",
        ".sc-head", ".sc-title", ".sc-card", ".sc-team-bar", ".sc-team-block",
        ".ts-team", ".ts-mid", ".ts-team-sub", ".ts-mid-sub", ".ts-winner", ".ts-pill", ".nw-bar",
        ".bd-card", ".bd-head", ".bd-sub", ".bd-tab",
        ".ps-head", ".ps-sub", ".ps-body", ".ps-runs",
        ".pc-avatar", ".pc-head", ".pc-labels", ".pc-values",
        ".is-head", ".is-sub", ".is-block", ".is-player", ".is-bd-col", ".is-mile", ".is-part",
        ".sum-org", ".sum-head", ".sum-cols", ".sum-row", ".sum-fow", ".sum-foot",
        ".ls-brand", ".ls-head", ".ls-card", ".ls-top", ".ls-cols", ".ls-section", ".ls-row", ".ls-note", ".ls-bottom",
        ".ib-card", ".ib-head", ".ib-sub", ".ib-body",
        ".tv-field-teams", ".tv-field-oval", ".tv-fielder",
        ".tv-bound-col", ".tv-bound-card > *",
        ".tv-arrow-head", ".tv-arrow-shaft", ".tv-arrow-tip",
        ".tv-flag", ".tv-shield > *", ".tv-lower-body > *",
        ".tv-xi-ribbon", ".tv-xi-row",
        ".bs-badge", ".bs-head", ".bs-row", ".bs-foot", ".bs-pill", ".bws-fow",
        ".bb-medal", ".bb-top", ".bb-bottom", ".bb-stat",
        ".tn-medal", ".tn-bar",
        ".tv-mega-logo", ".tv-mega", ".tv-mega-sub", ".tv-mega-player", ".tv-mega-kicker",
        ".gfx-message-text", ".gfx-message-sub",
        ".tv-trap", ".tv-hex-plate",
      ].join(",");
      const pieces = root.querySelectorAll(selectors);
      pieces.forEach((el, i) => {
        el.classList.add("gfx-piece");
        // Distinct gaps: 90–130ms between pieces so they feel one-by-one
        const gap = 90 + (i % 4) * 15;
        el.style.setProperty("--stagger", `${160 + i * gap}ms`);
        el.style.setProperty("--stagger-i", String(i));
      });
    }

    scheduleRemove(ms) {
      clearTimeout(this.timer);
      // Keep on screen for ms, then play the long exit
      this.timer = setTimeout(() => this.dismiss(), Math.max(ms, TIMING.enter + 800));
    }

    dismiss() {
      if (!this.node) return;
      clearTimeout(this.timer);
      this.node.classList.remove("gfx-enter", "gfx-ready");
      this.node.classList.add("gfx-exit");
      const n = this.node;
      const key = this.mapKey || this.id;
      const onDismissed = this.onDismissed;
      this.onDismissed = null;
      this.node = null;
      setTimeout(() => {
        n.remove();
        this.engine.onGraphicRemoved(key);
        if (typeof onDismissed === "function") {
          try { onDismissed(); } catch (_) { /* ignore */ }
        }
      }, TIMING.exit);
    }

    render() {
      return el("div", `gfx ${this.id}`);
    }
  }

  /* ── Event Graphics — old-school broadcast shapes ── */

  class RunEventGraphic extends BaseGraphic {
    constructor(engine, type, data) {
      super(engine, type);
      this.type = type;
      this.data = data;
    }

    render() {
      const isSix = this.type === "six";
      const label = isSix ? "SIX" : "FOUR";
      const ghostCount = isSix ? 6 : 4;
      const root = el("div", `gfx gfx-run gfx-${this.type} gfx-ss-boom ipl-fx tv-fx`);
      // Broadcast sting: black stage + swoosh ribbons + yellow outlined slam + floating outline ghosts
      const ghosts = Array.from({ length: ghostCount }, (_, i) =>
        `<span class="ss-ghost ss-g${i + 1}" aria-hidden="true">${label}</span>`
      ).join("");
      root.innerHTML = `
        <div class="ss-stage">
          <div class="ss-bg" aria-hidden="true"></div>
          <svg class="ss-ribbons" viewBox="0 0 1920 1080" preserveAspectRatio="none" aria-hidden="true">
            <path class="ss-ribbon ss-r1" fill="none" stroke-linecap="round" stroke-linejoin="round"
              d="M-120 80 C360 -40, 720 180, 960 420 C1180 640, 1480 860, 2100 1020"/>
            <path class="ss-ribbon ss-r2" fill="none" stroke-linecap="round" stroke-linejoin="round"
              d="M-140 220 C340 80, 700 280, 940 500 C1160 700, 1500 880, 2120 980"/>
            <path class="ss-ribbon ss-r3" fill="none" stroke-linecap="round" stroke-linejoin="round"
              d="M-160 360 C320 200, 680 360, 920 560 C1140 740, 1520 860, 2140 920"/>
          </svg>
          <div class="ss-ghosts" aria-hidden="true">${ghosts}</div>
          <div class="ss-word-wrap">
            <div class="ss-word" data-text="${label}">
              <span class="ss-word-rim" aria-hidden="true">${label}</span>
              <span class="ss-word-stroke" aria-hidden="true">${label}</span>
              <span class="ss-word-face">${label}</span>
            </div>
          </div>
        </div>
      `;
      return root;
    }
  }

  class WicketGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "wicket");
      this.data = data;
    }

    render() {
      const player = safeStr(this.data.player);
      const dismissal = safeStr(this.data.dismissal, "OUT");
      const isOutOnly = !!this.data.outOnly;
      const label = "OUT";
      const ghosts = Array.from({ length: 8 }, (_, i) =>
        `<span class="out-ghost out-g${i + 1}" aria-hidden="true">${label}</span>`
      ).join("");
      const root = el("div", `gfx gfx-wicket gfx-out-sting ipl-fx tv-fx ${isOutOnly ? "gfx-out-only" : ""}`);
      root.innerHTML = `
        <div class="out-stage">
          <div class="out-bg" aria-hidden="true"></div>
          <div class="out-flash" aria-hidden="true"></div>
          <div class="out-flash out-flash-b" aria-hidden="true"></div>
          <div class="out-rings" aria-hidden="true">
            <i class="out-ring out-ring-a"></i>
            <i class="out-ring out-ring-b"></i>
            <i class="out-ring out-ring-c"></i>
          </div>
          <svg class="out-cracks" viewBox="0 0 1920 1080" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <path class="out-crack" d="M960 540 L720 320 L640 280"/>
            <path class="out-crack" d="M960 540 L1180 300 L1280 240"/>
            <path class="out-crack" d="M960 540 L700 700 L620 780"/>
            <path class="out-crack" d="M960 540 L1220 720 L1320 800"/>
            <path class="out-crack" d="M960 540 L860 200"/>
            <path class="out-crack" d="M960 540 L1080 900"/>
          </svg>
          <svg class="out-ribbons" viewBox="0 0 1920 1080" preserveAspectRatio="none" aria-hidden="true">
            <path class="out-ribbon out-r1" fill="none" stroke-linecap="round"
              d="M-120 80 C360 -40, 720 180, 960 420 C1180 640, 1480 860, 2100 1020"/>
            <path class="out-ribbon out-r2" fill="none" stroke-linecap="round"
              d="M-140 220 C340 80, 700 280, 940 500 C1160 700, 1500 880, 2120 980"/>
            <path class="out-ribbon out-r3" fill="none" stroke-linecap="round"
              d="M-160 360 C320 200, 680 360, 920 560 C1140 740, 1520 860, 2140 920"/>
          </svg>
          <div class="out-ghosts" aria-hidden="true">${ghosts}</div>
          <div class="out-word-wrap">
            <div class="out-word" data-text="${label}">
              <span class="out-word-rim" aria-hidden="true">${label}</span>
              <span class="out-word-stroke" aria-hidden="true">${label}</span>
              <span class="out-word-face">${label}</span>
            </div>
            <div class="out-sub">${isOutOnly ? "BATTER" : "WICKET"}</div>
            <div class="out-player">
              <strong>${player ? player.toUpperCase() : "OUT"}</strong>
              <em>${dismissal.toUpperCase()}</em>
            </div>
          </div>
        </div>
      `;
      return root;
    }
  }

  class TossGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "toss");
      this.data = data;
    }

    render() {
      const d = this.data;
      const b = brandFrom(d, this.engine.state);
      const winner = safeStr(d.winnerName);
      const root = el("div", "gfx gfx-toss ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage tv-stage-wide">
          <div class="ts-row">
            <div class="ts-team ts-team-a">
              <div class="ts-team-head">TEAM</div>
              <div class="ts-team-sub"><strong>${safeStr(d.teamAName).toUpperCase()}</strong></div>
              <div class="ts-team-body">
                ${imgOrFallback(b.teamALogo || d.teamALogo, safeStr(d.teamAName).slice(0, 2), "ts-crest")}
              </div>
            </div>
            <div class="ts-mid">
              <div class="ts-mid-head"><span>TOSS</span></div>
              <div class="ts-mid-sub"><em class="ts-sub">${winner ? "WON THE TOSS" : "AWAITING"}</em></div>
              <div class="ts-mid-body">
                <strong class="ts-winner">${(winner || "Awaiting toss").toUpperCase()}</strong>
                ${d.decision ? `<span class="ts-pill">${safeStr(d.decision).toUpperCase()}</span>` : (winner ? `<span class="ts-pill">WON THE TOSS</span>` : `<span class="ts-pill">WAITING</span>`)}
                ${d.matchNo ? `<span class="ts-tiny">MATCH ${d.matchNo}${d.tournament ? " · " + safeStr(d.tournament) : ""}</span>` : ""}
              </div>
            </div>
            <div class="ts-team ts-team-b">
              <div class="ts-team-head">TEAM</div>
              <div class="ts-team-sub"><strong>${safeStr(d.teamBName).toUpperCase()}</strong></div>
              <div class="ts-team-body">
                ${imgOrFallback(b.teamBLogo || d.teamBLogo, safeStr(d.teamBName).slice(0, 2), "ts-crest")}
              </div>
            </div>
          </div>
        </div>
      `;
      return root;
    }
  }

  class NeedToWinGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "need_to_win");
      this.data = data;
    }

    render() {
      const d = this.data;
      const root = el("div", "gfx gfx-need ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage tv-stage-wide">
          <div class="nw-stack">
            <div class="nw-bar nw-orange nw-head">
              <em>NEED TO WIN</em>
            </div>
            <div class="nw-bar nw-blue">
              <span>NEED</span>
              <strong>${d.runs ?? "—"}</strong>
              <span>RUNS</span>
            </div>
            <div class="nw-bar nw-silver">
              <span>FROM</span>
              <strong>${d.balls ?? "—"}</strong>
              <span>BALLS</span>
            </div>
            <div class="nw-bar nw-blue nw-foot">
              <em>${safeStr(d.teamName).toUpperCase()}</em>
              <span>TO WIN</span>
            </div>
          </div>
        </div>
      `;
      return root;
    }
  }

  class InningsBreakGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "innings_break");
      this.data = data;
    }

    render() {
      const d = this.data;
      const b = brandFrom(d, this.engine.state);
      const root = el("div", "gfx gfx-innings ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage">
          <div class="ib-card">
            <div class="ib-head">INNINGS BREAK</div>
            <div class="ib-sub">${safeStr(d.teamName).toUpperCase()}</div>
            <div class="ib-body">
              <strong>${d.score ?? 0} / ${d.wickets ?? 0}</strong>
              <em>${safeStr(d.overs)} OVERS</em>
              ${d.target != null ? `<span class="ib-pill">TARGET ${d.target}</span>` : ""}
            </div>
          </div>
        </div>
      `;
      return root;
    }
  }

  class WinnerGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "winner");
      this.data = data;
    }

    render() {
      const d = this.data;
      const b = brandFrom(d, this.engine.state);
      const root = el("div", "gfx gfx-winner ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage">
          <div class="tv-brand-corners">
            ${imgOrFallback(b.organizerLogo, "ORG", "tv-badge")}
            ${imgOrFallback(b.streamerLogo, "LIVE", "tv-badge cyan")}
          </div>
          <div class="tv-shield">
            ${shine()}
            <span class="tv-burst-kicker">MATCH COMPLETE</span>
            ${imgOrFallback(d.logo || b.organizerLogo, safeStr(d.teamName).slice(0, 2), "tv-crest lg")}
            <strong>${safeStr(d.teamName).toUpperCase()}</strong>
            <span class="tv-pill">${safeStr(d.result).toUpperCase()}</span>
            ${d.tournament ? `<em class="tv-tiny">${safeStr(d.tournament)}</em>` : ""}
          </div>
        </div>
      `;
      return root;
    }
  }

  class PartnershipGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "partnership");
      this.data = data;
    }

    render() {
      const d = this.data;
      const p1 = safeStr(d.player1).toUpperCase() || "BATTER 1";
      const p2 = safeStr(d.player2).toUpperCase() || "BATTER 2";
      const root = el("div", "gfx gfx-partnership ipl-fx tv-fx");
      root.innerHTML = `
        <div class="ps-card">
          <div class="ps-head">PARTNERSHIP</div>
          <div class="ps-sub">${p1} &amp; ${p2}</div>
          <div class="ps-body">
            <strong class="ps-runs">${d.runs ?? 0}</strong>
            <em class="ps-balls">${d.balls ?? 0} BALLS</em>
          </div>
        </div>
      `;
      return root;
    }
  }

  class PlayerCardGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "player_card");
      this.data = data;
    }

    render() {
      const d = this.data;
      const b = brandFrom(d, this.engine.state);
      const isHistory = String(d.mode || "").toLowerCase() === "history";
      const isBowler = d.role === "bowler" || (isHistory && d.playingRole === "bowler");
      const name = safeStr(d.playerName || d.name, "PLAYER").toUpperCase();
      const team = safeStr(d.teamName, "").toUpperCase();
      const playingRole = safeStr(d.playingRole || (isBowler ? "bowler" : "batsman")).toUpperCase();
      const styleBits = [d.battingStyle, d.bowlingStyle].map((x) => safeStr(x)).filter(Boolean);
      const initials = name.replace(/[^A-Z]/g, "").slice(0, 2) || "PL";
      const avatar = safeStr(d.avatar || d.playerAvatar || b.playerAvatar);
      const runs = d.runs ?? 0;
      const balls = d.balls ?? 0;
      const fours = d.fours ?? 0;
      const sixes = d.sixes ?? 0;
      const sr = safeStr(d.strikeRate ?? d.sr, balls ? (((Number(runs) / Number(balls)) * 100) || 0).toFixed(1) : "0");

      let labels;
      let values;
      let liveHtml;
      if (isHistory) {
        labels = ["Matches", "Runs", "Wkts", "Avg", "Best"];
        values = [
          d.matches ?? 0,
          d.runs ?? 0,
          d.wickets ?? 0,
          safeStr(d.average || d.economy, "—"),
          safeStr(d.best, "—"),
        ];
        const tm = d.thisMatch;
        if (tm && (tm.balls > 0 || tm.runs > 0 || tm.wickets > 0)) {
          const tmBowl = tm.role === "bowler";
          liveHtml = tmBowl
            ? `<div class="pc-thismatch"><span>THIS MATCH</span><strong>${safeStr(tm.overs, "0.0")}-${tm.runs ?? 0}-${tm.wickets ?? 0}</strong></div>`
            : `<div class="pc-thismatch"><span>THIS MATCH</span><strong>${tm.runs ?? 0}</strong><em>(${tm.balls ?? 0})</em></div>`;
        } else {
          liveHtml = `<div class="pc-live" aria-label="Career"><strong>${d.matches ?? 0}</strong><em>M</em></div>`;
        }
      } else if (isBowler) {
        labels = ["Overs", "Runs", "Wkts", "Econ", "Dots"];
        values = [safeStr(d.overs, "0.0"), d.runs ?? 0, d.wickets ?? 0, safeStr(d.economy, "—"), d.dots ?? 0];
        liveHtml = `<div class="pc-live" aria-label="Current figures"><strong>${d.wickets ?? 0}</strong><em>/${d.runs ?? 0}</em></div>`;
      } else {
        labels = ["Runs", "Balls", "Fours", "Sixes", "SR"];
        values = [runs, balls, fours, sixes, sr];
        liveHtml = `<div class="pc-live" aria-label="Current score"><strong>${runs}</strong><em>${balls}</em></div>`;
      }

      const root = el("div", "gfx gfx-player-card ipl-fx tv-fx");
      root.innerHTML = `
        <div class="pc-card ${isHistory ? "pc-history" : "pc-live-mode"}">
          ${playerAvatarHtml(avatar, initials, "pc-avatar")}
          <div class="pc-head">
            <div class="pc-id">
              <strong class="pc-name">${name}</strong>
              ${team ? `<span class="pc-team">${team}</span>` : ""}
              <span class="pc-meta">${playingRole}${styleBits.length ? " · " + styleBits.join(" · ").toUpperCase() : ""}</span>
            </div>
            ${liveHtml}
          </div>
          <div class="pc-labels">
            ${labels.map((l) => `<span>${l}</span>`).join("")}
          </div>
          <div class="pc-values">
            ${values.map((v) => `<strong>${v}</strong>`).join("")}
          </div>
        </div>
      `;
      return root;
    }
  }

  class InstantShowGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "instant_show");
      this.data = data;
    }

    render() {
      const d = this.data;
      const players = Array.isArray(d.players) ? d.players : [];
      const playerRows = players.map((p) => {
        const role = safeStr(p.role).toUpperCase();
        const isBowl = role === "BOWLER";
        const stats = isBowl
          ? `<div class="is-stats">
              <span><i>O</i><b>${safeStr(p.overs || "0.0")}</b></span>
              <span><i>R</i><b>${p.runs ?? 0}</b></span>
              <span><i>W</i><b>${p.wickets ?? 0}</b></span>
              <span><i>Econ</i><b>${safeStr(p.econ ?? p.sr ?? "0.00")}</b></span>
              <span><i>Dots</i><b>${p.dots ?? 0}</b></span>
            </div>`
          : `<div class="is-stats">
              <span><i>R</i><b>${p.runs ?? 0}</b></span>
              <span><i>B</i><b>${p.balls ?? 0}</b></span>
              <span><i>4s</i><b>${p.fours ?? 0}</b></span>
              <span><i>6s</i><b>${p.sixes ?? 0}</b></span>
              <span><i>SR</i><b>${safeStr(p.sr ?? "0.0")}</b></span>
            </div>`;
        return `
          <div class="is-player">
            <div class="is-player-top">
              <strong>${safeStr(p.name).toUpperCase()}</strong>
              <em>${role}${p.team ? ` · ${safeStr(p.team).toUpperCase()}` : ""}</em>
            </div>
            ${stats}
          </div>`;
      }).join("") || `<div class="is-empty">No players yet</div>`;

      const milestones = Array.isArray(d.milestones) ? d.milestones : [];
      const mileRows = milestones.length
        ? milestones.map((m) => `
            <div class="is-mile">
              <strong>${m.runs ?? 50}</strong>
              <span>${safeStr(m.player).toUpperCase()}</span>
              <em>${safeStr(m.detail || "")}</em>
            </div>`).join("")
        : `<div class="is-empty">No 50 / 100 yet</div>`;

      const bdMatch = d.boundariesMatch || { fours: 0, sixes: 0 };
      const bdTour = d.boundariesTournament || bdMatch;
      const part = d.partnership || {};

      const root = el("div", "gfx gfx-instant-show ipl-fx tv-fx");
      root.innerHTML = `
        <div class="is-panel">
          <div class="is-head">INSTANT SHOW</div>
          <div class="is-sub">${safeStr(d.subtitle || "LIVE MATCH STATE").toUpperCase()}</div>
          <div class="is-body">
            <section class="is-block">
              <div class="is-block-h">PLAYER CARD</div>
              ${playerRows}
            </section>
            <section class="is-block">
              <div class="is-block-h">BOUNDARIES</div>
              <div class="is-bd">
                <div class="is-bd-head" aria-hidden="true"></div>
                <div class="is-bd-head">THIS MATCH</div>
                <div class="is-bd-head">TOURNAMENT</div>

                <div class="is-bd-label">FOURS <span>4s</span></div>
                <div class="is-bd-num" title="Fours this match">${bdMatch.fours ?? 0}</div>
                <div class="is-bd-num" title="Fours tournament">${bdTour.fours ?? 0}</div>

                <div class="is-bd-label">SIXES <span>6s</span></div>
                <div class="is-bd-num is-bd-six" title="Sixes this match">${bdMatch.sixes ?? 0}</div>
                <div class="is-bd-num is-bd-six" title="Sixes tournament">${bdTour.sixes ?? 0}</div>
              </div>
            </section>
            <section class="is-block">
              <div class="is-block-h">50 · 100 MILESTONES</div>
              ${mileRows}
            </section>
            <section class="is-block">
              <div class="is-block-h">PARTNERSHIP</div>
              <div class="is-part">
                <strong>${safeStr(part.player1 || "—").toUpperCase()}</strong>
                <span>&amp;</span>
                <strong>${safeStr(part.player2 || "—").toUpperCase()}</strong>
                <b>${part.runs ?? 0}</b>
                <em>(${part.balls ?? 0})</em>
              </div>
            </section>
          </div>
        </div>`;
      return root;
    }
  }

  class TeamLineupGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "team_lineup");
      this.data = data;
    }

    render() {
      const d = this.data;
      const b = brandFrom(d, this.engine.state);
      const avatars = (d.avatars || []).filter((a) => a && a.name);
      const entries = avatars.length
        ? avatars.map((a) => ({ name: a.name, avatar: a.avatar }))
        : (d.players || []).filter(Boolean).map((p) => ({ name: p, avatar: "" }));
      const list = (entries.length ? entries : [{ name: "Add squad in control", avatar: "" }]).slice(0, 11);
      const cards = list.map((p, i) => `
        <div class="sc-card" style="--i:${i}">
          <div class="sc-top">
            ${playerAvatarHtml(p.avatar || d.playerAvatar || b.playerAvatar, safeStr(p.name).slice(0, 2))}
          </div>
          <div class="sc-name">${safeStr(p.name).toUpperCase()}</div>
        </div>`).join("");
      const root = el("div", "gfx gfx-lineup ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage tv-stage-wide">
          <div class="sc-wrap">
            <div class="sc-head">
              <div class="sc-logo">${imgOrFallback(d.logo || b.organizerLogo, safeStr(d.teamName).slice(0, 2), "mu-logo-tile")}</div>
              <div class="sc-title">
                <strong>${safeStr(d.teamName).toUpperCase()}</strong>
                <em>Match No. ${safeStr(d.matchNo, "1")}${d.round ? `, ${safeStr(d.round)}` : ""} · PLAYING XI</em>
              </div>
            </div>
            <div class="sc-grid">${cards}</div>
          </div>
        </div>
      `;
      return root;
    }
  }

  class TeamVsTeamGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "team_vs_team");
      this.data = data;
    }
    render() {
      const d = this.data;
      const b = brandFrom(d, this.engine.state);
      const title = safeStr(d.tournament || d.matchTitle || "MATCH UP").toUpperCase();
      const root = el("div", "gfx gfx-team-vs ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage tv-stage-wide">
          <div class="tvv-wrap">
            <div class="tvv-title">
              <strong>${title}</strong>
            </div>
            <div class="tvv-match">Match No. ${safeStr(d.matchNo, "1")}</div>
            <div class="tvv-logos">
              <div class="tvv-box">
                ${imgOrFallback(d.teamALogo || b.teamALogo, safeStr(d.teamAName).slice(0, 2), "tvv-logo")}
              </div>
              <div class="tvv-box tvv-box-org">
                ${imgOrFallback(b.organizerLogo || b.streamerLogo, "ORG", "tvv-logo")}
              </div>
              <div class="tvv-box">
                ${imgOrFallback(d.teamBLogo || b.teamBLogo, safeStr(d.teamBName).slice(0, 2), "tvv-logo")}
              </div>
            </div>
            <div class="tvv-names">
              <strong class="tvv-name">${safeStr(d.teamAName).toUpperCase()}</strong>
              <span class="tvv-vs">VS</span>
              <strong class="tvv-name">${safeStr(d.teamBName).toUpperCase()}</strong>
            </div>
          </div>
        </div>`;
      return root;
    }
  }

  class KnockoutRoundGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "knockout_round");
      this.data = data;
    }

    drawLinks() {
      // Overlay shows blocks only — no bracket connector lines.
    }

    cricketTrophySvg() {
      return `
        <svg class="ko-ico ko-ico-trophy" viewBox="0 0 64 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <defs>
            <linearGradient id="koTrophyGold" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#ffe7a0"/>
              <stop offset="45%" stop-color="#E49402"/>
              <stop offset="100%" stop-color="#b36d00"/>
            </linearGradient>
          </defs>
          <path fill="url(#koTrophyGold)" d="M18 8h28v6c0 12-6 22-14 26-8-4-14-14-14-26V8z"/>
          <path fill="none" stroke="#ffe7a0" stroke-width="3" d="M18 14c-8 2-12 10-10 18 2 6 8 8 12 6"/>
          <path fill="none" stroke="#ffe7a0" stroke-width="3" d="M46 14c8 2 12 10 10 18-2 6-8 8-12 6"/>
          <rect x="28" y="40" width="8" height="14" rx="1" fill="url(#koTrophyGold)"/>
          <rect x="18" y="54" width="28" height="6" fill="url(#koTrophyGold)"/>
          <rect x="14" y="60" width="36" height="10" rx="1" fill="url(#koTrophyGold)"/>
          <circle cx="32" cy="22" r="5" fill="#002153" opacity="0.35"/>
          <path fill="#002153" opacity="0.45" d="M32 16l1.6 3.3 3.6.5-2.6 2.6.6 3.6L32 24.2 28.8 26.2l.6-3.6-2.6-2.6 3.6-.5z"/>
        </svg>`;
    }

    cricketBatSvg() {
      return `
        <svg class="ko-ico ko-ico-bat" viewBox="0 0 28 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect x="10" y="0" width="8" height="28" rx="2" fill="#5b3418"/>
          <rect x="9" y="26" width="10" height="8" rx="1" fill="#3d220f"/>
          <path fill="#d4a574" d="M6 34h16c2 0 4 2 4 5v40c0 4-3 7-7 7H9c-4 0-7-3-7-7V39c0-3 2-5 4-5z"/>
          <path fill="#c08d58" d="M8 42h12v34c0 2-1 3-3 3H11c-2 0-3-1-3-3V42z" opacity="0.45"/>
          <rect x="11" y="4" width="6" height="18" rx="1" fill="#7a4a24"/>
        </svg>`;
    }

    cricketBallSvg(uid = "a") {
      return `
        <svg class="ko-ico ko-ico-ball" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <defs>
            <radialGradient id="koBallShine-${uid}" cx="0.32" cy="0.28" r="0.7">
              <stop offset="0%" stop-color="#fff"/>
              <stop offset="55%" stop-color="#c62828" stop-opacity="0"/>
            </radialGradient>
          </defs>
          <circle cx="24" cy="24" r="20" fill="#c62828"/>
          <circle cx="24" cy="24" r="20" fill="url(#koBallShine-${uid})" opacity="0.35"/>
          <path d="M14 10c6 8 6 20 0 28" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>
          <path d="M34 10c-6 8-6 20 0 28" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>
          <path d="M15 14c2 1 3 2 3 3M15 20c2 1 3 2 3 3M15 26c2 1 3 2 3 3M15 32c2 1 3 2 3 3" fill="none" stroke="#fff" stroke-width="1.2"/>
          <path d="M33 14c-2 1-3 2-3 3M33 20c-2 1-3 2-3 3M33 26c-2 1-3 2-3 3M33 32c-2 1-3 2-3 3" fill="none" stroke="#fff" stroke-width="1.2"/>
        </svg>`;
    }

    finalArena(fixturesHtml) {
      return `
        <div class="ko-final-arena">
          <div class="ko-final-burst" aria-hidden="true"></div>
          <div class="ko-final-trophy gfx-piece">${this.cricketTrophySvg()}</div>
          <div class="ko-final-row">
            <div class="ko-final-deco ko-final-deco-l" aria-hidden="true">
              ${this.cricketBatSvg()}
              ${this.cricketBallSvg("l")}
            </div>
            <div class="ko-final-match">${fixturesHtml}</div>
            <div class="ko-final-deco ko-final-deco-r" aria-hidden="true">
              ${this.cricketBallSvg("r")}
              ${this.cricketBatSvg()}
            </div>
          </div>
          <div class="ko-final-caption gfx-piece">CHAMPIONSHIP MATCH</div>
        </div>`;
    }

    fixture(m, opts = {}) {
      if (!m) return "";
      const aRaw = safeStr(m.teamA);
      const bRaw = safeStr(m.teamB);
      const a = (aRaw || "TBD").toUpperCase();
      const b = (bRaw || "TBD").toUpperCase();
      const aIni = a.replace(/[^A-Z0-9]/g, "").slice(0, 2) || "T";
      const bIni = b.replace(/[^A-Z0-9]/g, "").slice(0, 2) || "T";
      const time = safeStr(m.time);
      const pendingA = !aRaw ? " is-pending" : "";
      const pendingB = !bRaw ? " is-pending" : "";
      const finalCls = opts.final || String(m.round).toUpperCase() === "F" ? " ko-fixture-final" : "";
      return `
        <div class="ko-fixture${finalCls} gfx-piece" data-slot="${safeStr(m.slot)}">
          <div class="ko-row${pendingA}">
            <span class="ko-ini">${aIni}</span>
            <span class="ko-team">${a}</span>
          </div>
          <div class="ko-row${pendingB}">
            <span class="ko-ini">${bIni}</span>
            <span class="ko-team">${b}</span>
          </div>
          ${time ? `<div class="ko-timebar"><em>TIME</em><span>${time}</span></div>` : ""}
        </div>`;
    }

    stage(heading, date, fixturesHtml, opts = {}) {
      const body = opts.final ? this.finalArena(fixturesHtml) : fixturesHtml;
      return `
        <section class="ko-stage gfx-piece${opts.final ? " ko-stage-final" : ""}">
          <div class="ko-stage-h">
            <span class="ko-stage-title">${heading}</span>
            ${date ? `<em>${safeStr(date)}</em>` : ""}
          </div>
          <div class="ko-stage-body">${body}</div>
        </section>`;
    }

    render() {
      const d = this.data;
      const b = brandFrom(d, this.engine.state);
      const format = [4, 8, 16].includes(Number(d.format)) ? Number(d.format) : 8;
      const matches = Array.isArray(d.matches) ? d.matches : [];
      const rd = d.roundDates || {};
      const title = safeStr(d.title || "KNOCKOUT ROUND").toUpperCase();
      const tourney = safeStr(d.tournament || d.matchTitle || "").toUpperCase();

      const catalog = [
        { round: "R64", label: "Round of 64", dateKey: "r64" },
        { round: "R32", label: "Round of 32", dateKey: "r32" },
        { round: "R16", label: "Round of 16", dateKey: "r16" },
        { round: "QF", label: "Quarter-finals", dateKey: "qf" },
        { round: "SF", label: "Semi-finals", dateKey: "sf" },
        { round: "F", label: "Final", dateKey: "final" },
      ];
      const byRound = {};
      matches.forEach((m) => {
        const r = String(m.round || "").toUpperCase();
        if (!r) return;
        (byRound[r] ||= []).push(m);
      });
      const stages = catalog.filter((c) => (byRound[c.round] || []).length);
      const stagesHtml = stages.map((st) => {
        const list = byRound[st.round] || [];
        const html = list.map((m) => this.fixture(m, { final: st.round === "F" })).join("");
        return this.stage(st.label, rd[st.dateKey], html, { final: st.round === "F" });
      }).join("");

      const root = el("div", `gfx gfx-knockout ipl-fx tv-fx ko-format-${format}`);
      root.innerHTML = `
        <div class="tv-stage tv-stage-wide">
          <div class="ko-board">
            <div class="ko-head gfx-piece">
              <div class="ko-brands">
                ${imgOrFallback(b.organizerLogo, "ORG", "tv-badge")}
                ${imgOrFallback(b.streamerLogo, "LIVE", "tv-badge cyan")}
              </div>
              <div class="ko-titles">
                <strong>${title}</strong>
                <em>${tourney || "TOURNAMENT BRACKET"}</em>
              </div>
              <div class="ko-format-tag">${d.custom ? "CUSTOM" : `${format} TEAMS`}</div>
            </div>
            <div class="ko-bracket">${stagesHtml}</div>
            <div class="ko-foot gfx-piece">PATH TO GLORY</div>
          </div>
        </div>`;
      return root;
    }
  }

  class BoundariesCounterGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "boundaries_counter");
      this.data = data;
    }
    render() {
      const d = this.data;
      const scope = String(d.scope || "tournament").toLowerCase() === "match" ? "match" : "tournament";
      const label = safeStr(d.label || (scope === "match" ? "THIS MATCH" : "TOURNAMENT")).toUpperCase();
      const fours = Number(d.fours ?? 0) || 0;
      const sixes = Number(d.sixes ?? 0) || 0;
      const fromFours = d.fromFours != null ? Number(d.fromFours) || 0 : fours;
      const fromSixes = d.fromSixes != null ? Number(d.fromSixes) || 0 : sixes;
      const bumpFour = d.bump === "four" || (fromFours !== fours);
      const bumpSix = d.bump === "six" || (fromSixes !== sixes);
      const root = el("div", "gfx gfx-boundaries bd-corner ipl-fx tv-fx");
      root.innerHTML = `
        <div class="bd-card bd-card-total bd-card-corner">
          <div class="bd-head"><span>${safeStr(d.title || "BOUNDARIES").toUpperCase()}</span></div>
          <div class="bd-sub bd-sub-single"><span>${label}</span></div>
          <div class="bd-body bd-body-total">
            <div class="bd-tab${bumpFour ? " bd-tab-bump" : ""}"><em>4s</em><strong class="bd-num" data-from="${fromFours}" data-to="${fours}">${fromFours}</strong></div>
            <div class="bd-tab${bumpSix ? " bd-tab-bump" : ""}"><em>6s</em><strong class="bd-num" data-from="${fromSixes}" data-to="${sixes}">${fromSixes}</strong></div>
          </div>
        </div>`;
      return root;
    }

    mount(parent) {
      super.mount(parent);
      requestAnimationFrame(() => {
        requestAnimationFrame(() => this.animateCounts());
      });
      return this;
    }

    animateCounts() {
      if (!this.node) return;
      this.node.querySelectorAll(".bd-num").forEach((el) => {
        const from = Number(el.getAttribute("data-from")) || 0;
        const to = Number(el.getAttribute("data-to")) || 0;
        if (from === to) {
          el.textContent = String(to);
          return;
        }
        const start = performance.now();
        const dur = 780;
        const step = (now) => {
          if (!this.node) return;
          const p = Math.min(1, (now - start) / dur);
          const eased = 1 - Math.pow(1 - p, 3);
          el.textContent = String(Math.round(from + (to - from) * eased));
          if (p < 1) {
            requestAnimationFrame(step);
          } else {
            el.textContent = String(to);
            el.classList.add("bd-num-pop");
          }
        };
        requestAnimationFrame(step);
      });
    }
  }

  class BlackboardGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "blackboard");
      this.data = data;
    }
    render() {
      const d = this.data;
      const root = el("div", "gfx gfx-blackboard ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage">
          <div class="tv-shield">
            ${shine()}
            <div class="tv-lower-brands" style="width:100%;margin-bottom:8px">
              ${imgOrFallback(this.data.organizerLogo || "", "ORG", "tv-badge-sm")}
              ${imgOrFallback(this.data.streamerLogo || "", "LIVE", "tv-badge-sm cyan")}
            </div>
            <span class="tv-burst-kicker">${safeStr(d.title || "NOTICE BOARD")}</span>
            <strong style="font-size:34px;white-space:pre-wrap;line-height:1.2">${safeStr(d.text || d.message || "—")}</strong>
          </div>
        </div>`;
      return root;
    }
  }

  class BothSquadsGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "both_squads");
      this.data = data;
    }
    render() {
      const d = this.data;
      const b = brandFrom(d, this.engine.state);
      const pack = (team) => {
        const players = (team?.players || []).filter(Boolean);
        const avatars = team?.avatars || [];
        const list = (players.length ? players : ["Add squad"]).slice(0, 11).map((name, i) => {
          const hit = avatars.find((a) => safeStr(a?.name).toLowerCase() === safeStr(name).toLowerCase());
          return { name, avatar: hit?.avatar || "" };
        });
        const cards = list.map((p, i) => `
          <div class="sc-card" style="--i:${i}">
            <div class="sc-top">
              ${playerAvatarHtml(p.avatar || d.playerAvatar || b.playerAvatar, safeStr(p.name).slice(0, 2))}
            </div>
            <div class="sc-name">${safeStr(p.name).toUpperCase()}</div>
          </div>`).join("");
        return `
          <div class="sc-team-block">
            <div class="sc-team-bar">${safeStr(team?.name).toUpperCase()}</div>
            <div class="sc-grid sc-grid-sm">${cards}</div>
          </div>`;
      };
      const root = el("div", "gfx gfx-both-squads ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage tv-stage-wide">
          <div class="sc-wrap sc-wrap-dual">
            <div class="sc-head">
              <div class="sc-logo">${imgOrFallback(b.organizerLogo || b.streamerLogo, "ORG", "mu-logo-tile")}</div>
              <div class="sc-title">
                <strong>TEAM SQUAD</strong>
                <em>Match No. ${safeStr(d.matchNo, "1")}</em>
              </div>
            </div>
            <div class="sc-dual">
              ${pack(d.teamA)}
              ${pack(d.teamB)}
            </div>
          </div>
        </div>`;
      return root;
    }
  }

  class FieldPositionGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "field_position");
      this.data = data;
    }

    /** Place labels toward the pitch so names stay inside the oval. */
    fielderDir(x, y) {
      const dx = x - 50;
      const dy = y - 50;
      if (Math.abs(dx) >= Math.abs(dy)) {
        return dx >= 0 ? "in-left" : "in-right";
      }
      return dy >= 0 ? "in-up" : "in-down";
    }

    render() {
      const d = this.data;
      const spots = (d.positions || []).filter((p) => p && (p.label || p.player || p.id));
      const map = spots.map((p) => {
        const name = safeStr(p.player).toUpperCase();
        const label = safeStr(p.label || p.id).toUpperCase();
        const x = Number(p.x);
        const y = Number(p.y);
        const px = Math.max(18, Math.min(82, Number.isFinite(x) ? x : 50));
        const py = Math.max(16, Math.min(84, Number.isFinite(y) ? y : 50));
        const side = this.fielderDir(px, py);
        const text = name || label || "—";
        return `
        <div class="tv-fielder tv-fielder-${side}" style="left:${px}%;top:${py}%">
          <i></i>
          <div class="tv-fielder-label">
            <strong class="tv-fielder-name">${text}</strong>
            ${name ? `<em class="tv-fielder-pos">${label}</em>` : ""}
          </div>
        </div>`;
      }).join("");
      const bowl = safeStr(d.bowlingTeam).toUpperCase();
      const bat = safeStr(d.battingTeam).toUpperCase();
      const root = el("div", "gfx gfx-field ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage tv-stage-wide">
          <div class="tv-field-frame tv-field-frame-solo">
            <div class="tv-field-teams" role="heading" aria-level="2">
              <strong>${bowl || "TEAM 1"}</strong>
              <span>fielding vs</span>
              <em>${bat || "TEAM 2"}</em>
            </div>
            <div class="tv-field-oval">
              <div class="tv-field-ring"></div>
              <div class="tv-field-pitch"></div>
              ${map}
            </div>
          </div>
        </div>`;
      return root;
    }
  }

  class MatchSummaryGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "match_summary");
      this.data = data;
    }

    /** Headings first, then data rows — never interleave. */
    applyStagger(root) {
      if (!root) return;
      const mark = (nodes, startMs, gapMs) => {
        nodes.forEach((el, i) => {
          el.classList.add("gfx-piece");
          el.style.setProperty("--stagger", `${startMs + i * gapMs}ms`);
          el.style.setProperty("--stagger-i", String(i));
          el.style.removeProperty("animation-delay");
        });
      };

      const brand = [...root.querySelectorAll(".ms-brand")];
      const title = [...root.querySelectorAll(".ms-pill-orange")];
      const teamHeads = [...root.querySelectorAll(".ms-pill-team")];
      const sectionLabels = [...root.querySelectorAll(".ms-section-label")];
      const colHeads = [...root.querySelectorAll(".ms-cols")];
      const rows = [...root.querySelectorAll(".ms-pill-silver")];
      const foot = [...root.querySelectorAll(".ms-pill-foot")];

      // Phase 1 — all headings
      mark(brand, 40, 0);
      mark(title, 120, 0);
      mark(teamHeads, 220, 70);
      mark(sectionLabels, 360, 55);
      mark(colHeads, 560, 45);
      // Phase 2 — player / bowler rows
      mark(rows, 780, 70);
      // Phase 3 — result footer
      mark(foot, 780 + Math.max(rows.length, 1) * 70 + 80, 0);
    }

    batRows(list) {
      const bats = (list || []).slice(0, 6);
      if (!bats.length) {
        return `<div class="ms-pill ms-pill-silver ms-pill-bat"><span class="ms-pill-name">—</span><span class="ms-pill-stat">No batsmen</span></div>`;
      }
      return bats.map((bat) => `
        <div class="ms-pill ms-pill-silver ms-pill-bat">
          <span class="ms-pill-name">${safeStr(bat?.name, "—").toUpperCase()}</span>
          <span class="ms-pill-how">${bat?.out ? safeStr(bat.dismissal || "out").toUpperCase() : "NOT OUT"}</span>
          <span class="ms-pill-stat">${bat?.runs ?? 0} (${bat?.balls ?? 0})</span>
        </div>
      `).join("");
    }

    bowlRows(list) {
      const bowls = (list || []).slice(0, 5);
      if (!bowls.length) {
        return `<div class="ms-pill ms-pill-silver ms-pill-bowl-row"><span class="ms-pill-name">—</span><span class="ms-pill-stat ms-span-bowl">No bowlers</span></div>`;
      }
      return bowls.map((bowl) => {
        const figures = bowl.figures
          || `${bowl.wickets ?? 0}-${bowl.runs ?? 0}`;
        const overs = bowl.overs != null ? safeStr(bowl.overs) : "0.0";
        const maidens = bowl.maidens ?? "—";
        const econ = bowl.econ != null ? safeStr(bowl.econ) : "—";
        return `
          <div class="ms-pill ms-pill-silver ms-pill-bowl-row">
            <span class="ms-pill-name">${safeStr(bowl?.name, "—").toUpperCase()}</span>
            <span class="ms-pill-stat">${overs}</span>
            <span class="ms-pill-stat">${maidens}</span>
            <span class="ms-pill-stat">${figures}</span>
            <span class="ms-pill-stat">${econ}</span>
          </div>
        `;
      }).join("");
    }

    teamBlock(team) {
      const t = team || {};
      const score = `${t.score ?? 0} / ${t.wickets ?? 0}`;
      return `
        <section class="ms-block">
          <div class="ms-pill ms-pill-team">
            <span class="ms-team-name">${safeStr(t.name, "TEAM").toUpperCase()}</span>
            <span class="ms-team-overs">OVER : ${safeStr(t.overs, "0.0")}</span>
            <span class="ms-team-score">${score}</span>
          </div>
          <div class="ms-section-label">BATTING</div>
          <div class="ms-cols ms-cols-bat">
            <span>Batsman</span><span>Out</span><span>R (B)</span>
          </div>
          <div class="ms-pills">
            ${this.batRows(t.batting)}
          </div>
          <div class="ms-section-label">BOWLING</div>
          <div class="ms-cols ms-cols-bowl">
            <span>Bowler</span><span>O</span><span>M</span><span>W-R</span><span>Econ</span>
          </div>
          <div class="ms-pills">
            ${this.bowlRows(t.bowling)}
          </div>
        </section>
      `;
    }

    render() {
      const d = this.data;
      const b = brandFrom(d, this.engine.state);
      const root = el("div", "gfx gfx-summary ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage tv-stage-wide">
          <div class="ms-stack">
            <div class="ms-brand">
              ${imgOrFallback(b.organizerLogo || b.streamerLogo, safeStr(d.logoText || d.tournament || "ORG").slice(0, 3), "mu-logo-tile")}
            </div>
            <div class="ms-pill ms-pill-orange">
              <strong>MATCH SUMMARY</strong>
              <em>Match No. ${safeStr(d.matchNo, "1")}${d.round ? `, ${safeStr(d.round)}` : ""}</em>
            </div>
            <div class="ms-dual">
              ${this.teamBlock(d.teamA)}
              ${this.teamBlock(d.teamB)}
            </div>
            <div class="ms-pill ms-pill-foot">
              ${safeStr(d.result, "Match in progress")}
            </div>
          </div>
        </div>
      `;
      return root;
    }
  }

  class CustomMessageGraphic extends BaseGraphic {
    constructor(engine, type, data) {
      super(engine, type);
      this.data = data;
    }

    render() {
      const raw = safeStr(this.data.text, this.id.toUpperCase());
      const isCommentator =
        this.id === "commentator"
        || /^commentator\s*:/i.test(raw)
        || !!this.data.commentator;
      const name = isCommentator
        ? safeStr(this.data.commentator || raw.replace(/^commentator\s*:/i, "").trim(), "COMMENTATOR")
        : raw;

      if (isCommentator) {
        const b = brandFrom(this.data, this.engine.state);
        const root = el("div", "gfx gfx-instant-show gfx-commentator-is ipl-fx tv-fx");
        root.innerHTML = `
          <div class="is-panel">
            <div class="is-head gfx-piece">COMMENTATOR</div>
            <div class="is-sub gfx-piece">ON AIR</div>
            <div class="is-body">
              <section class="is-block gfx-piece">
                <div class="is-block-h">NOW COMMENTATING</div>
                <div class="is-comm">
                  ${imgOrFallback(b.organizerLogo || b.streamerLogo, "MIC", "is-comm-logo")}
                  <div class="is-comm-meta">
                    <strong>${name.toUpperCase()}</strong>
                    <em>LIVE BROADCAST</em>
                  </div>
                </div>
              </section>
            </div>
          </div>
        `;
        return root;
      }

      const root = el("div", `gfx gfx-message gfx-${this.id} ipl-fx tv-fx`);
      root.innerHTML = `
        <div class="gfx-message-stage">
          <div class="gfx-message-frame gfx-piece">
            <div class="gfx-message-diamond gfx-piece" aria-hidden="true"></div>
            <div class="gfx-message-inner gfx-piece">
              <div class="gfx-message-text">${raw}</div>
              ${this.data.sub ? `<div class="gfx-message-sub">${this.data.sub}</div>` : ""}
            </div>
          </div>
        </div>
      `;
      return root;
    }
  }

  class MilestoneGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "milestone");
      this.data = data;
    }

    render() {
      const d = this.data;
      const runs = Number(d.runs) || 50;
      const label = runs >= 100 ? "CENTURY" : runs >= 50 ? "HALF CENTURY" : "MILESTONE";
      const b = brandFrom(d, this.engine.state);
      const root = el("div", "gfx gfx-milestone ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage tv-stage-mega">
          <div class="tv-mega-wrap">
            ${imgOrFallback(b.organizerLogo || b.streamerLogo, "LIVE", "tv-mega-logo")}
            <div class="tv-mega-kicker">${label}</div>
            <div class="tv-mega tv-mega-mile" data-text="${runs}">${runs}</div>
            <div class="tv-mega-sub">${safeStr(d.sub, "RUNS")}</div>
            <div class="tv-mega-player">
              <span>${safeStr(d.player, "BATTER").toUpperCase()}</span>
            </div>
          </div>
        </div>
      `;
      return root;
    }
  }

  /* ── Batting Summary ── */
  class BattingSummaryGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "batting_summary");
      this.data = data;
    }

    render() {
      const d = this.data;
      const b = brandFrom(d, this.engine.state);
      const rows = (d.players || []).map((p) => {
        const yet = !!p.yetToBat || String(p.dismissal || "").toLowerCase() === "yet to bat";
        const notOut = !yet && !p.out;
        const runs = yet ? "—" : (notOut ? `${p.runs ?? 0}*` : String(p.runs ?? 0));
        const balls = yet ? "—" : String(p.balls ?? 0);
        const how = yet ? "YET TO BAT" : (notOut ? "NOT OUT" : safeStr(p.dismissal || "out"));
        return `
          <div class="sum-row sum-row-bat ${yet ? "sum-yet" : (notOut ? "sum-notout" : "")}">
            <div class="sum-name">${safeStr(p.name).toUpperCase()}</div>
            <div class="sum-how">${how}</div>
            <strong class="sum-r">${runs}</strong>
            <em class="sum-b">${balls}</em>
          </div>`;
      }).join("") || `<div class="sum-row sum-row-bat"><div class="sum-name">—</div><div class="sum-how">No squad set</div><strong class="sum-r"></strong><em class="sum-b"></em></div>`;

      const extras = d.extras || {};
      const exTotal = (extras.wd || 0) + (extras.nb || 0) + (extras.b || 0) + (extras.lb || 0);
      const root = el("div", "gfx gfx-batting-summary ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage">
          <div class="sum-wrap">
            <div class="sum-org">${imgOrFallback(b.organizerLogo || d.teamLogo || b.streamerLogo, safeStr(d.badgeLeft || "ORG").slice(0, 3), "sum-org-logo")}</div>
            <header class="sum-head">
              <strong>${safeStr(d.teamName).toUpperCase()}</strong>
              <em>Match No. ${safeStr(d.matchNo, "1")}${d.round ? `, ${safeStr(d.round)}` : ""}, BATTING SUMMARY</em>
            </header>
            <div class="sum-cols sum-cols-bat">
              <span>Batter</span><span>Dismissal</span><span>R</span><span>B</span>
            </div>
            <div class="sum-list">${rows}</div>
            <footer class="sum-foot">
              <div class="sum-foot-silver">
                <span>Extras ${exTotal}</span>
                <span>Overs ${safeStr(d.overs, "0.0")}</span>
              </div>
              <div class="sum-foot-blue">${d.score ?? 0} - ${d.wickets ?? 0}</div>
            </footer>
          </div>
        </div>`;
      return root;
    }
  }

  /* ── Bowling Summary ── */
  class BowlingSummaryGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "bowling_summary");
      this.data = data;
    }

    render() {
      const d = this.data;
      const b = brandFrom(d, this.engine.state);
      const bowlRows = (d.bowlers || []).map((bw) => `
        <div class="sum-row sum-row-bowl">
          <div class="sum-name">${safeStr(bw.name).toUpperCase()}</div>
          <span class="sum-cell">${safeStr(bw.overs, "0.0")}</span>
          <span class="sum-cell">${bw.runs ?? 0}</span>
          <span class="sum-cell">${bw.wickets ?? 0}</span>
          <span class="sum-cell">${bw.dots ?? 0}</span>
          <span class="sum-cell">${safeStr(bw.econ, "—")}</span>
        </div>`).join("") || `
        <div class="sum-row sum-row-bowl">
          <div class="sum-name">—</div>
          <span class="sum-cell">—</span><span class="sum-cell">—</span>
          <span class="sum-cell">—</span><span class="sum-cell">—</span><span class="sum-cell">—</span>
        </div>`;

      const fow = d.fow || [];
      const fowNums = fow.map((_, i) => `<span>${i + 1}</span>`).join("") || "<span>—</span>";
      const fowScores = fow.map((x) => `<span>${x}</span>`).join("") || "<span>—</span>";

      const extras = d.extras || {};
      const exTotal = (extras.wd || 0) + (extras.nb || 0) + (extras.b || 0) + (extras.lb || 0);
      const root = el("div", "gfx gfx-bowling-summary ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage">
          <div class="sum-wrap">
            <div class="sum-org">${imgOrFallback(b.organizerLogo || d.teamLogo || b.streamerLogo, safeStr(d.badgeLeft || "ORG").slice(0, 3), "sum-org-logo")}</div>
            <header class="sum-head">
              <strong>${safeStr(d.teamName).toUpperCase()}</strong>
              <em>Match No. ${safeStr(d.matchNo, "1")}${d.round ? `, ${safeStr(d.round)}` : ""}, BOWLING SUMMARY</em>
            </header>
            <div class="sum-cols sum-cols-bowl">
              <span>Bowler</span><span>Overs</span><span>Runs</span><span>Wickets</span><span>Dots</span><span>Economy</span>
            </div>
            <div class="sum-list">${bowlRows}</div>
            <div class="sum-fow">
              <div class="sum-fow-label">FALL OF WICKETS</div>
              <div class="sum-fow-silver">
                <div class="sum-fow-nums">${fowNums}</div>
                <div class="sum-fow-scores">${fowScores}</div>
              </div>
            </div>
            <footer class="sum-foot">
              <div class="sum-foot-silver">
                <span>Extras ${exTotal}</span>
                <span>${safeStr(d.overs, "0.0")}</span>
              </div>
              <div class="sum-foot-blue">${d.score ?? 0} - ${d.wickets ?? 0}</div>
            </footer>
          </div>
        </div>`;
      return root;
    }
  }

  class LiveScorecardGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "live_scorecard");
      this.data = data;
    }

    render() {
      const d = this.data;
      const b = brandFrom(d, this.engine.state);
      const renderBatters = (items) => (items || []).map((p) => `
        <div class="ls-row">
          <span class="ls-player">${safeStr(p.name).toUpperCase()}</span>
          <span class="ls-dismissal">${safeStr(p.dismissal || (p.out ? "OUT" : "NOT OUT")).toUpperCase()}</span>
          <span class="ls-mini">${p.runs ?? 0}</span>
          <span class="ls-mini">${p.balls ?? 0}</span>
          <span class="ls-mini">${p.fours ?? 0}</span>
          <span class="ls-mini">${p.sixes ?? 0}</span>
          <span class="ls-mini">${safeStr(p.sr ?? "0.00")}</span>
        </div>
      `).join("") || `<div class="ls-row ls-empty"><span class="ls-player">—</span><span class="ls-dismissal">NO BATTERS</span><span class="ls-mini">-</span><span class="ls-mini">-</span><span class="ls-mini">-</span><span class="ls-mini">-</span><span class="ls-mini">-</span></div>`;
      const renderBowlers = (items) => (items || []).map((p) => `
        <div class="ls-row ls-row-bowl">
          <span class="ls-player">${safeStr(p.name).toUpperCase()}</span>
          <span class="ls-mini">${safeStr(p.overs || "0.0")}</span>
          <span class="ls-mini">${p.maidens ?? 0}</span>
          <span class="ls-mini">${p.runs ?? 0}</span>
          <span class="ls-mini">${p.wickets ?? 0}</span>
          <span class="ls-mini">${safeStr(p.econ || "0.00")}</span>
          <span class="ls-mini">${p.dots ?? 0}</span>
        </div>
      `).join("") || `<div class="ls-row ls-row-bowl ls-empty"><span class="ls-player">—</span><span class="ls-mini">-</span><span class="ls-mini">-</span><span class="ls-mini">-</span><span class="ls-mini">-</span><span class="ls-mini">-</span><span class="ls-mini">-</span></div>`;
      const renderTeam = (team, idx) => {
        const extras = team?.extras || {};
        const totalExtras = (extras.wd || 0) + (extras.nb || 0) + (extras.b || 0) + (extras.lb || 0) + (extras.pen || 0);
        const dnb = (team?.didNotBat || []).filter(Boolean).join(", ") || "—";
        return `
          <section class="ls-card" style="--ls-i:${idx}">
            <div class="ls-top">
              <strong>${safeStr(team?.name).toUpperCase()}</strong>
              <span>${team?.score ?? 0}/${team?.wickets ?? 0} (${safeStr(team?.overs || "0.0")} ov)</span>
            </div>
            <div class="ls-section">BATTING</div>
            <div class="ls-cols">
              <span>Batsmen</span><span>Out</span><span>R</span><span>B</span><span>4s</span><span>6s</span><span>SR</span>
            </div>
            ${renderBatters(team?.batting)}
            <div class="ls-note">Total <strong>${team?.score ?? 0}/${team?.wickets ?? 0}</strong> · Extras <strong>${totalExtras}</strong> (wd ${extras.wd || 0} nb ${extras.nb || 0} b ${extras.b || 0} lb ${extras.lb || 0})</div>
            <div class="ls-note">Did not bat <strong>${safeStr(dnb)}</strong></div>
            <div class="ls-note">Fall of wickets <strong>${(team?.fow || []).join(", ") || "—"}</strong></div>
            <div class="ls-section">BOWLING</div>
            <div class="ls-cols ls-cols-bowl">
              <span>Bowler</span><span>O</span><span>M</span><span>R</span><span>W</span><span>Econ</span><span>0s</span>
            </div>
            ${renderBowlers(team?.bowling)}
          </section>`;
      };

      // Prefer explicit teams list (batting-only). Fall back to dual payload, skipping empty sides.
      const teamHasBatted = (team) => {
        if (!team) return false;
        const bats = team.batting || [];
        if (bats.some((p) => (p.balls || 0) > 0 || (p.runs || 0) > 0 || p.out)) return true;
        const overs = parseFloat(String(team.overs || "0")) || 0;
        return overs > 0 || (team.score || 0) > 0 || (team.wickets || 0) > 0;
      };
      let teams = Array.isArray(d.teams) ? d.teams.filter(Boolean) : [];
      if (!teams.length) {
        const candidates = [d.teamA, d.teamB].filter(Boolean);
        const batted = candidates.filter(teamHasBatted);
        teams = batted.length ? batted : candidates.slice(0, 1);
      }
      const single = teams.length < 2;

      const root = el("div", "gfx gfx-live-scorecard ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tv-stage tv-stage-wide">
          <div class="ls-wrap${single ? " ls-wrap-single" : ""}">
            <div class="ls-brand">${imgOrFallback(b.organizerLogo || b.streamerLogo, "ORG", "mu-logo-tile")}</div>
            <div class="ls-head">
              <strong>${safeStr(d.title || d.tournament || (single ? "LIVE SCORECARD" : "MATCH SUMMARY")).toUpperCase()}</strong>
              <em>Match No. ${safeStr(d.matchNo || "1")} · ${single ? "CURRENT MATCH STATE" : "FULL SCORECARD"}</em>
            </div>
            <div class="ls-grid${single ? " ls-grid-single" : ""}">
              ${teams.map((t, i) => renderTeam(t, i)).join("")}
            </div>
            <div class="ls-bottom">${safeStr(d.result || "MATCH IN PROGRESS").toUpperCase()}</div>
          </div>
        </div>`;
      return root;
    }
  }

  /* ── Best Batsman / Player of the Match ── */
  class BestBatsmanGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "best_batsman");
      this.data = data;
    }

    render() {
      const d = this.data;
      const root = el("div", "gfx gfx-best-batsman ipl-fx tv-fx");
      root.innerHTML = `
        <div class="bb-wrap">
          <div class="bb-medal">
            <div class="bb-medal-inner">${safeStr(d.initials || d.playerName || "BB").slice(0, 2).toUpperCase()}</div>
          </div>
          <div class="bb-stack">
            <div class="bb-top">
              <div class="bb-left">
                <div class="bb-name">${safeStr(d.playerName, "PLAYER").toUpperCase()}</div>
                <div class="bb-team">${safeStr(d.teamName).toUpperCase()}</div>
              </div>
              <div class="bb-label">${safeStr(d.title, "BEST BATSMAN").toUpperCase()}</div>
            </div>
            <div class="bb-bottom">
              <div class="bb-stat"><span>Batting</span><strong>${d.runs ?? 0} (${d.balls ?? 0})</strong></div>
              <div class="bb-stat"><span>Bowling</span><strong>${safeStr(d.bowling, "—")}</strong></div>
              <div class="bb-stat"><span>Fours</span><strong>${d.fours ?? 0}</strong></div>
              <div class="bb-stat"><span>Sixes</span><strong>${d.sixes ?? 0}</strong></div>
              <div class="bb-stat"><span>SR</span><strong>${safeStr(d.sr, "—")}</strong></div>
              <div class="bb-stat"><span>Catches</span><strong>${d.catches ?? 0}</strong></div>
              <div class="bb-stat"><span>RunOuts</span><strong>${d.runouts ?? 0}</strong></div>
            </div>
          </div>
        </div>`;
      return root;
    }
  }

  /* ── Tournament Name banner ── */
  class TournamentNameGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "tournament_name");
      this.data = data;
    }

    render() {
      const d = this.data;
      const b = brandFrom(d, this.engine.state);
      const title = safeStr(d.title || d.tournament || "TOURNAMENT").toUpperCase();
      const initials = safeStr(d.initials || title, "CT").replace(/[^A-Z0-9]/gi, "").slice(0, 3).toUpperCase() || "CT";
      const root = el("div", "gfx gfx-tournament-name ipl-fx tv-fx");
      root.innerHTML = `
        <div class="tn-stage">
          <div class="tn-frame gfx-piece">
            <div class="tn-plate gfx-piece">
              <div class="tn-chevron tn-chevron-l" aria-hidden="true"></div>
              <div class="tn-chevron tn-chevron-r" aria-hidden="true"></div>
              <div class="tn-logo-slot gfx-piece">
                ${imgOrFallback(b.organizerLogo || b.streamerLogo, initials, "tn-org-logo")}
              </div>
              <div class="tn-kicker">OFFICIAL TOURNAMENT</div>
              <div class="tn-text">${title}</div>
              <div class="tn-underline" aria-hidden="true"></div>
            </div>
            <div class="tn-hex tn-hex-l gfx-piece" aria-hidden="true"></div>
            <div class="tn-hex tn-hex-r gfx-piece" aria-hidden="true"></div>
          </div>
        </div>`;
      return root;
    }
  }

  function calcSR(runs, balls) {
    const r = Number(runs) || 0;
    const b = Number(balls) || 0;
    if (b <= 0) return "—";
    return ((r / b) * 100).toFixed(1);
  }

  function calcEcon(runs, balls) {
    const overs = (Number(balls) || 0) / 6;
    if (overs <= 0) return "—";
    return ((Number(runs) || 0) / overs).toFixed(2);
  }

  function ballsToOversStr(balls) {
    const n = Number(balls) || 0;
    return `${Math.floor(n / 6)}.${n % 6}`;
  }

  function resolveBatter(team, which) {
    // Prefer structured batsmen from scoring engine
    if (Array.isArray(team.batsmen) && team.batsmen.length) {
      const striker = team.batsmen.find((b) => b.id === team.strikerId) ||
        team.batsmen.find((b) => b.onStrike && !b.out);
      const nonStriker = team.batsmen.find((b) => b.id === team.nonStrikerId) ||
        team.batsmen.find((b) => !b.out && b !== striker);
      const pick = which === 1 ? striker : nonStriker;
      if (pick) {
        return {
          name: pick.name || "",
          runs: pick.runs ?? 0,
          balls: pick.balls ?? 0,
          fours: pick.fours ?? 0,
          sixes: pick.sixes ?? 0,
          onStrike: which === 1 || !!pick.onStrike,
          sr: calcSR(pick.runs, pick.balls),
        };
      }
    }
    const parsed = parseBatsmanLine(which === 1 ? team.batsman1 : team.batsman2);
    return {
      name: parsed.name,
      runs: parsed.runs === "" ? 0 : Number(parsed.runs) || 0,
      balls: parsed.balls === "" ? 0 : Number(parsed.balls) || 0,
      fours: 0,
      sixes: 0,
      onStrike: which === 1 ? true : !!parsed.onStrike,
      sr: calcSR(parsed.runs, parsed.balls),
    };
  }

  function resolveBowler(bowlingTeam) {
    if (Array.isArray(bowlingTeam.bowlers) && bowlingTeam.currentBowlerId) {
      const b = bowlingTeam.bowlers.find((x) => x.id === bowlingTeam.currentBowlerId);
      if (b) {
        return {
          name: b.name || "",
          overs: ballsToOversStr(b.balls),
          maidens: b.maidens ?? 0,
          runs: b.runs ?? 0,
          wickets: b.wickets ?? 0,
          econ: calcEcon(b.runs, b.balls),
          figures: `${b.wickets ?? 0}/${b.runs ?? 0} (${ballsToOversStr(b.balls)})`,
        };
      }
    }
    const parsed = parseBowlerLine(bowlingTeam.bowler);
    return {
      name: parsed.name,
      overs: "",
      maidens: 0,
      runs: "",
      wickets: "",
      econ: "—",
      figures: parsed.figures,
    };
  }

  /* ── Main Scoreboard ── */

  class MainScoreboard {
    constructor(root) {
      this.root = root;
      this.prevScore = null;
      this.prevWickets = null;
      this.build();
    }

    build() {
      this.root.innerHTML = `
        <div class="sb sb-hub" id="scoreboard">
          <div class="hub-bar">
            <div class="hub-logo hub-logo-left">
              <img class="sb-logo" id="sb-logo" alt="" style="display:none">
              <div class="sb-logo-fallback" id="sb-logo-fb">CL</div>
            </div>

            <div class="hub-panel hub-batters">
              <div class="hub-batter" id="sb-bat1">
                <span class="sb-strike"></span>
                <span class="sb-bat-name">—</span>
                <span class="sb-bat-runs"></span>
                <span class="sb-bat-balls"></span>
              </div>
              <div class="hub-batter" id="sb-bat2">
                <span class="sb-strike"></span>
                <span class="sb-bat-name">—</span>
                <span class="sb-bat-runs"></span>
                <span class="sb-bat-balls"></span>
              </div>
            </div>

            <div class="hub-orange hub-orange-left">
              <div class="hub-vs" id="sb-vs-line"><span id="sb-team-a-short">—</span> vs <strong id="sb-team-b-short">—</strong></div>
              <div class="hub-rates">
                <div class="hub-crr" id="sb-crr">CRR : 0.00</div>
                <div class="hub-rrr" id="sb-rrr">RRR : —</div>
              </div>
            </div>

            <div class="hub-score-circle" id="hub-score-circle">
              <div class="hub-score-ring">
                <div class="hub-score-inner">
                  <div class="hub-score-line">
                    <span class="sb-score" id="sb-score">0</span>
                    <span class="hub-dash">-</span>
                    <span class="sb-wickets" id="sb-wickets">0</span>
                  </div>
                  <div class="sb-overs" id="sb-overs">0.0 (20 ov)</div>
                </div>
              </div>
            </div>

            <div class="hub-orange hub-orange-right">
              <div class="hub-look" id="sb-look">
                <em id="sb-look-label">TOURNAMENT</em>
                <strong id="sb-look-value">—</strong>
              </div>
            </div>

            <div class="hub-panel hub-bowl-panel">
              <div class="hub-bowler" id="sb-bowler">
                <span class="sb-bowl-name">—</span>
                <span class="sb-bowl-fig"></span>
                <span class="sb-bowl-overs" id="sb-bowl-overs"></span>
              </div>
              <div class="hub-action-look" id="sb-last-action">
                <em>LAST</em>
                <strong id="sb-last-action-text">—</strong>
              </div>
              <div class="hub-this-over" id="sb-balls"></div>
            </div>

            <div class="hub-logo hub-logo-right">
              <div class="sb-logo-fallback" id="sb-logo-right">LIVE</div>
            </div>
          </div>

          <div class="sb-chase" id="sb-chase" style="display:none"></div>
          <div class="sb-result" id="sb-result" style="display:none"></div>

          <!-- Kept for API/compat (hidden) -->
          <div class="hub-hidden" aria-hidden="true">
            <span id="sb-tournament"></span>
            <span id="sb-match-meta"></span>
            <span id="sb-team-name"></span>
            <span id="sb-team-full"></span>
            <span id="sb-bowl-team"></span>
            <span id="sb-other-team"></span>
            <span id="sb-other-score"></span>
            <span id="sb-bowl-meta"></span>
            <span id="sb-partnership"></span>
            <span id="sb-extras"></span>
            <span id="sb-toss"></span>
            <span id="sb-pp"></span>
          </div>
        </div>
      `;
      this.els = {
        logo: this.root.querySelector("#sb-logo"),
        logoFb: this.root.querySelector("#sb-logo-fb"),
        logoRight: this.root.querySelector("#sb-logo-right"),
        teamName: this.root.querySelector("#sb-team-name"),
        teamFull: this.root.querySelector("#sb-team-full"),
        teamAShort: this.root.querySelector("#sb-team-a-short"),
        teamBShort: this.root.querySelector("#sb-team-b-short"),
        tournament: this.root.querySelector("#sb-tournament"),
        matchMeta: this.root.querySelector("#sb-match-meta"),
        score: this.root.querySelector("#sb-score"),
        wickets: this.root.querySelector("#sb-wickets"),
        overs: this.root.querySelector("#sb-overs"),
        otherTeam: this.root.querySelector("#sb-other-team"),
        otherScore: this.root.querySelector("#sb-other-score"),
        bat1: this.root.querySelector("#sb-bat1"),
        bat2: this.root.querySelector("#sb-bat2"),
        bowler: this.root.querySelector("#sb-bowler"),
        bowlOvers: this.root.querySelector("#sb-bowl-overs"),
        bowlMeta: this.root.querySelector("#sb-bowl-meta"),
        toss: this.root.querySelector("#sb-toss"),
        crr: this.root.querySelector("#sb-crr"),
        rrr: this.root.querySelector("#sb-rrr"),
        bowlTeam: this.root.querySelector("#sb-bowl-team"),
        lastAction: this.root.querySelector("#sb-last-action"),
        lastActionText: this.root.querySelector("#sb-last-action-text"),
        partnership: this.root.querySelector("#sb-partnership"),
        chase: this.root.querySelector("#sb-chase"),
        result: this.root.querySelector("#sb-result"),
        balls: this.root.querySelector("#sb-balls"),
        extras: this.root.querySelector("#sb-extras"),
        pp: this.root.querySelector("#sb-pp"),
        look: this.root.querySelector("#sb-look"),
        lookLabel: this.root.querySelector("#sb-look-label"),
        lookValue: this.root.querySelector("#sb-look-value"),
        sb: this.root.querySelector("#scoreboard"),
      };
      this.lookSlides = [];
      this.lookIndex = 0;
      this.startLookRotate();
    }

    startLookRotate() {
      if (this.lookTimer) clearInterval(this.lookTimer);
      this.lookTimer = setInterval(() => this.advanceLook(), 3800);
    }

    buildLookSlides(state, batting, bowling, bat1, bat2, score) {
      const slides = [];
      const push = (label, value) => {
        const v = safeStr(value).trim();
        if (!v || v === "—") return;
        slides.push({ label: safeStr(label).toUpperCase(), value: v });
      };

      push("Tournament", safeStr(state.matchTitle, "Live Match").toUpperCase());
      push("Match", `No. ${safeStr(state.matchNo, "1")} · ${state.totalOvers || 20} OV`);

      const innings = Number(state.innings) || 1;
      const status = safeStr(state.matchStatus);
      if (status === "innings_break") {
        push("Status", "INNINGS BREAK");
      } else if (status === "completed") {
        push("Status", "MATCH COMPLETED");
      } else {
        push("Innings", innings >= 2 ? "2ND INNINGS" : "1ST INNINGS");
      }

      push("Batting", safeStr(batting.name).toUpperCase());
      push("Bowling", safeStr(bowling.name).toUpperCase());

      let tossText = "";
      const t = state.toss;
      if (t && typeof t === "object") {
        tossText = safeStr(t.text);
        if (!tossText && t.winner) {
          const dec = safeStr(t.decision, "bat");
          tossText = `${safeStr(t.winner)} elected to ${dec}`;
        }
      } else if (typeof t === "string") {
        tossText = safeStr(t);
      }
      if (tossText) push("Toss", tossText);

      const pRuns = (Number(bat1.runs) || 0) + (Number(bat2.runs) || 0);
      const pBalls = (Number(bat1.balls) || 0) + (Number(bat2.balls) || 0);
      if (bat1.name || bat2.name) {
        push("Partnership", `${pRuns} (${pBalls})`);
      }

      if (state.target != null && innings >= 2) {
        const needed = Math.max(0, Number(state.target) - Number(score || 0));
        push("Target", `${state.target} · Need ${needed}`);
      }

      if (state.powerplay) push("Phase", "POWERPLAY");

      const ex = batting.extras || {};
      const exTotal = (ex.wd || 0) + (ex.nb || 0) + (ex.b || 0) + (ex.lb || 0);
      if (exTotal > 0) {
        push("Extras", `${exTotal} (wd ${ex.wd || 0} nb ${ex.nb || 0})`);
      }

      if (safeStr(state.commentator)) {
        push("On Air", safeStr(state.commentator).toUpperCase());
      }

      if (safeStr(state.venue)) {
        push("Venue", safeStr(state.venue).toUpperCase());
      }

      if ((status === "completed" || status === "result") && safeStr(state.result).trim()) {
        push("Result", safeStr(state.result));
      }

      return slides.length ? slides : [{ label: "LIVE", value: "MATCH IN PROGRESS" }];
    }

    renderLookSlide(animate = true) {
      if (!this.els.lookLabel || !this.els.lookValue) return;
      const slides = this.lookSlides || [];
      if (!slides.length) return;
      if (this.lookIndex >= slides.length) this.lookIndex = 0;
      const slide = slides[this.lookIndex];
      const apply = () => {
        this.els.lookLabel.textContent = slide.label;
        this.els.lookValue.textContent = slide.value;
      };
      if (animate && this.els.look) {
        this.els.look.classList.add("is-swap");
        setTimeout(() => {
          apply();
          this.els.look.classList.remove("is-swap");
        }, 160);
      } else {
        apply();
      }
    }

    advanceLook() {
      if (!this.lookSlides?.length) return;
      this.lookIndex = (this.lookIndex + 1) % this.lookSlides.length;
      this.renderLookSlide(true);
    }

    refreshLook(state, batting, bowling, bat1, bat2, score) {
      const next = this.buildLookSlides(state, batting, bowling, bat1, bat2, score);
      const prevKey = (this.lookSlides || []).map((s) => `${s.label}|${s.value}`).join("||");
      const nextKey = next.map((s) => `${s.label}|${s.value}`).join("||");
      this.lookSlides = next;
      if (prevKey !== nextKey) {
        if (this.lookIndex >= next.length) this.lookIndex = 0;
        // Keep current topic if still present
        const cur = (prevKey && this.els.lookLabel)
          ? this.els.lookLabel.textContent
          : "";
        const sameIdx = next.findIndex((s) => s.label === cur);
        if (sameIdx >= 0) this.lookIndex = sameIdx;
        this.renderLookSlide(false);
      } else if (!this.els.lookValue?.textContent || this.els.lookValue.textContent === "—") {
        this.renderLookSlide(false);
      }
    }

    setBatterRow(rowEl, batter) {
      rowEl.querySelector(".sb-strike").textContent = batter.onStrike ? ">" : "";
      rowEl.querySelector(".sb-bat-name").textContent = batter.name || "—";
      rowEl.querySelector(".sb-bat-runs").textContent = batter.name ? String(batter.runs ?? 0) : "";
      rowEl.querySelector(".sb-bat-balls").textContent = batter.name ? String(batter.balls ?? 0) : "";
      rowEl.classList.toggle("on-strike", !!batter.onStrike && !!batter.name);
    }

    renderThisOver(balls) {
      const container = this.els.balls;
      container.innerHTML = "";
      const list = Array.isArray(balls) ? balls : [];
      if (!list.length) {
        container.innerHTML = '<span class="sb-ball sb-ball-empty">—</span>';
        return;
      }
      list.forEach((b) => {
        const ball = el("span", `sb-ball sb-ball-${ballClass(b)}`);
        ball.textContent = ballDisplay(b);
        container.appendChild(ball);
      });
    }

    update(state) {
      const totalOvers = state.totalOvers || 20;
      const batting = state.battingTeam === "A" ? state.teamA : state.teamB;
      const bowling = state.battingTeam === "A" ? state.teamB : state.teamA;
      const other = bowling;
      const colors = teamColors(batting, state.battingTeam === "A" ? "#e63946" : "#1d8cf8");

      this.els.sb.style.setProperty("--sb-accent", colors.primary);

      this.els.tournament.textContent = safeStr(state.matchTitle, "LIVE MATCH").toUpperCase();
      this.els.matchMeta.textContent = `MATCH ${safeStr(state.matchNo, "1")} · ${totalOvers} OV`;
      this.els.teamName.textContent = teamShortName(batting);
      this.els.teamFull.textContent = safeStr(batting.name).toUpperCase();
      if (this.els.bowlTeam) {
        this.els.bowlTeam.textContent = safeStr(bowling.name).toUpperCase();
      }

      const shortA = teamShortName(state.teamA);
      const shortB = teamShortName(state.teamB);
      if (this.els.teamAShort) this.els.teamAShort.textContent = shortA;
      if (this.els.teamBShort) {
        this.els.teamBShort.textContent = shortB;
        // Emphasize batting side like the reference (bold second code)
        this.els.teamAShort.style.fontWeight = state.battingTeam === "A" ? "800" : "500";
        this.els.teamBShort.style.fontWeight = state.battingTeam === "B" ? "800" : "500";
      }

      // End brands: streamer (left) + organiser (right), larger than the bar.
      const showLogos = state.showLogo !== false;
      const streamerLogo = safeStr(state.streamerLogo);
      const organizerLogo = safeStr(state.organizerLogo);
      const leftLogo = showLogos ? (streamerLogo || batting.logo || organizerLogo) : "";
      const rightLogo = showLogos ? (organizerLogo || streamerLogo || other.logo) : "";

      if (leftLogo) {
        this.els.logo.src = leftLogo;
        this.els.logo.style.display = "";
        this.els.logoFb.style.display = "none";
        this.els.logo.onerror = () => {
          this.els.logo.style.display = "none";
          this.els.logoFb.style.display = "flex";
          this.els.logoFb.textContent = streamerLogo ? "LIVE" : teamShortName(batting).slice(0, 2);
        };
      } else {
        this.els.logo.style.display = "none";
        this.els.logoFb.style.display = "flex";
        this.els.logoFb.textContent = "LIVE";
      }
      if (this.els.logoRight) {
        let rightImg = this.els.logoRight.parentElement?.querySelector("img.sb-logo-right-img");
        if (rightLogo) {
          if (!rightImg) {
            rightImg = document.createElement("img");
            rightImg.className = "sb-logo sb-logo-right-img";
            rightImg.alt = "";
            this.els.logoRight.parentElement?.prepend(rightImg);
          }
          rightImg.src = rightLogo;
          rightImg.style.display = "";
          this.els.logoRight.style.display = "none";
          rightImg.onerror = () => {
            rightImg.style.display = "none";
            this.els.logoRight.style.display = "flex";
            this.els.logoRight.textContent = safeStr(state.matchTitle, "ORG").slice(0, 8).toUpperCase();
          };
        } else {
          if (rightImg) rightImg.style.display = "none";
          this.els.logoRight.style.display = "flex";
          this.els.logoRight.textContent = safeStr(state.matchTitle, "ORG").slice(0, 8).toUpperCase();
        }
      }

      const score = batting.score ?? 0;
      const wickets = batting.wickets ?? 0;
      if (this.prevScore != null && this.prevScore !== score) {
        animateNumber(this.els.score, this.prevScore, score);
      } else {
        this.els.score.textContent = score;
      }
      if (this.prevWickets != null && this.prevWickets !== wickets) {
        animateNumber(this.els.wickets, this.prevWickets, wickets, 300);
        this.els.wickets.classList.add("wicket-flash");
        setTimeout(() => this.els.wickets.classList.remove("wicket-flash"), 400);
      } else {
        this.els.wickets.textContent = wickets;
      }
      this.prevScore = score;
      this.prevWickets = wickets;

      const oversNow = safeStr(batting.overs, "0.0");
      this.els.overs.textContent = `${oversNow} (${totalOvers} ov)`;

      this.els.otherTeam.textContent = teamShortName(other);
      this.els.otherScore.textContent = `${other.score ?? 0}/${other.wickets ?? 0}`;

      const bat1 = resolveBatter(batting, 1);
      const bat2 = resolveBatter(batting, 2);
      bat1.onStrike = true;
      bat2.onStrike = false;
      this.setBatterRow(this.els.bat1, bat1);
      this.setBatterRow(this.els.bat2, bat2);

      const bowl = resolveBowler(bowling);
      this.els.bowler.querySelector(".sb-bowl-name").textContent = bowl.name || "—";
      // Reference style: "0-18" then overs "1.2"
      const fig = bowl.wickets !== "" && bowl.runs !== ""
        ? `${bowl.wickets}-${bowl.runs}`
        : (bowl.figures || "").replace("/", "-").replace(/\s*\(.*\)/, "");
      this.els.bowler.querySelector(".sb-bowl-fig").textContent = bowl.name ? fig : "";
      if (this.els.bowlOvers) {
        this.els.bowlOvers.textContent = bowl.name && bowl.overs ? bowl.overs : "";
      }
      this.els.bowlMeta.textContent = bowl.name
        ? `ECON ${bowl.econ}${bowl.maidens ? ` · M ${bowl.maidens}` : ""}`
        : "";

      if (this.els.toss) {
        const t = state.toss;
        let tossText = "";
        if (t && typeof t === "object") {
          tossText = safeStr(t.text);
          if (!tossText && t.winner) {
            const dec = safeStr(t.decision, "bat");
            tossText = `'${safeStr(t.winner)}' won the toss and elected to ${dec}`;
          }
        } else if (typeof t === "string") {
          tossText = safeStr(t);
        }
        this.els.toss.textContent = tossText
          ? `Toss : ${tossText}`
          : `Toss : —`;
      }

      const crr = calcCRR(score, batting.overs);
      this.els.crr.textContent = `CRR : ${crr}`;

      const pRuns = (Number(bat1.runs) || 0) + (Number(bat2.runs) || 0);
      const pBalls = (Number(bat1.balls) || 0) + (Number(bat2.balls) || 0);
      this.els.partnership.textContent = (bat1.name || bat2.name)
        ? `P'SHIP ${pRuns} (${pBalls})`
        : "P'SHIP —";

      if (state.target != null && Number(state.innings) >= 2) {
        const needed = Math.max(0, state.target - score);
        const ballsRem = ballsRemaining(batting.overs, totalOvers);
        const rrr = calcRRR(score, state.target, batting.overs, totalOvers);
        if (this.els.rrr) {
          this.els.rrr.classList.toggle("is-idle", !rrr);
          this.els.rrr.textContent = rrr ? `RRR : ${rrr}` : "RRR : —";
        }
        this.els.chase.style.display = "";
        this.els.chase.textContent = `TARGET ${state.target}  ·  NEED ${needed} RUNS FROM ${ballsRem} BALLS`;
      } else {
        if (this.els.rrr) {
          this.els.rrr.classList.add("is-idle");
          this.els.rrr.textContent = "RRR : —";
        }
        this.els.chase.style.display = "none";
      }

      this.updateLastAction(state);

      const ex = batting.extras || {};
      const exTotal = (ex.wd || 0) + (ex.nb || 0) + (ex.b || 0) + (ex.lb || 0);
      this.els.extras.textContent = exTotal
        ? `EX ${exTotal} (wd ${ex.wd || 0} nb ${ex.nb || 0} b ${ex.b || 0} lb ${ex.lb || 0})`
        : "";

      this.renderThisOver(state.thisOver);
      if (this.els.pp) {
        this.els.pp.textContent = state.powerplay ? "POWERPLAY" : "";
        this.els.pp.style.display = state.powerplay ? "" : "none";
      }

      this.refreshLook(state, batting, bowling, bat1, bat2, score);

      if ((state.matchStatus || "") === "completed" && state.result && String(state.result).trim()) {
        this.els.result.style.display = "";
        this.els.result.textContent = `RESULT: ${state.result}`;
      } else {
        this.els.result.style.display = "none";
      }
    }

    updateLastAction(state) {
      if (!this.els.lastActionText) return;
      const le = state?.lastEvent;
      let label = "—";
      let kind = "idle";
      const type = String(le?.type || "").toLowerCase();
      if (type === "four" || (type === "boundary" && Number(le?.runs) === 4)) {
        label = "FOUR";
        kind = "four";
      } else if (type === "six" || (type === "boundary" && Number(le?.runs) === 6)) {
        label = "SIX";
        kind = "six";
      } else if (type === "wicket" || type === "out") {
        label = "WICKET";
        kind = "wicket";
      } else if (type === "wide") {
        label = `WIDE${le?.extra ? ` +${le.extra}` : ""}`;
        kind = "extra";
      } else if (type === "noball" || type === "no_ball") {
        label = `NO BALL${le?.runs ? ` +${le.runs}` : ""}`;
        kind = "extra";
      } else if (type === "bye") {
        label = `BYE ${le?.runs ?? ""}`.trim();
        kind = "extra";
      } else if (type === "legbye" || type === "leg_bye") {
        label = `LEG BYE ${le?.runs ?? ""}`.trim();
        kind = "extra";
      } else if (type === "run" || type === "runs" || type === "dot") {
        const runs = Number(le?.runs ?? 0);
        label = runs === 0 ? "DOT" : `${runs} RUN${runs === 1 ? "" : "S"}`;
        kind = runs === 0 ? "dot" : "run";
      } else if (Array.isArray(state?.thisOver) && state.thisOver.length) {
        const last = state.thisOver[state.thisOver.length - 1];
        const disp = ballDisplay(last);
        const cls = ballClass(last);
        if (cls === "four") { label = "FOUR"; kind = "four"; }
        else if (cls === "six") { label = "SIX"; kind = "six"; }
        else if (cls === "wicket") { label = "WICKET"; kind = "wicket"; }
        else if (cls === "extra") { label = String(disp || "EXTRA").toUpperCase(); kind = "extra"; }
        else if (cls === "dot" || disp === "•") { label = "DOT"; kind = "dot"; }
        else { label = `${disp} RUN${disp === "1" ? "" : "S"}`; kind = "run"; }
      }
      this.els.lastActionText.textContent = label;
      if (this.els.lastAction) {
        this.els.lastAction.dataset.kind = kind;
      }
    }

    setVisible(v) {
      this.els.sb.classList.toggle("sb-hidden", !v);
    }
  }

  /** Overlay 02 · Neon Prism — cyber lower-third strip (same data API as hub). */
  class PrismScoreboard extends MainScoreboard {
    build() {
      this.root.innerHTML = `
        <div class="sb sb-prism" id="scoreboard">
          <div class="prism-frame">
            <div class="prism-glow" aria-hidden="true"></div>
            <header class="prism-top">
              <div class="prism-logo-l">
                <img class="sb-logo" id="sb-logo" alt="" style="display:none">
                <div class="sb-logo-fallback" id="sb-logo-fb">▶</div>
              </div>
              <div class="prism-ticker" id="sb-look">
                <em id="sb-look-label">LIVE</em>
                <strong id="sb-look-value">—</strong>
              </div>
              <div class="prism-meta">
                <span id="sb-tournament"></span>
                <span id="sb-match-meta"></span>
              </div>
              <div class="prism-vs" id="sb-vs-line">
                <span id="sb-team-a-short">—</span><i>×</i><span id="sb-team-b-short">—</span>
              </div>
              <div class="prism-logo-r">
                <div class="sb-logo-fallback" id="sb-logo-right">LIVE</div>
              </div>
            </header>
            <div class="prism-grid">
              <section class="prism-col prism-col-score">
                <div class="prism-team-tag" id="sb-team-full">TEAM</div>
                <div class="prism-scoreline">
                  <span class="sb-score" id="sb-score">0</span>
                  <span class="prism-slash">/</span>
                  <span class="sb-wickets" id="sb-wickets">0</span>
                </div>
                <div class="sb-overs prism-overs" id="sb-overs">0.0 (20 ov)</div>
                <div class="prism-chase-row">
                  <span id="sb-other-team">—</span>
                  <span id="sb-other-score">0/0</span>
                </div>
              </section>
              <section class="prism-col prism-col-bat">
                <div class="prism-bat" id="sb-bat1">
                  <span class="sb-strike"></span>
                  <span class="sb-bat-name">—</span>
                  <span class="sb-bat-runs"></span>
                  <span class="sb-bat-balls"></span>
                </div>
                <div class="prism-bat" id="sb-bat2">
                  <span class="sb-strike"></span>
                  <span class="sb-bat-name">—</span>
                  <span class="sb-bat-runs"></span>
                  <span class="sb-bat-balls"></span>
                </div>
                <div class="prism-this-over" id="sb-balls"></div>
              </section>
              <section class="prism-col prism-col-bowl">
                <div class="prism-bowler" id="sb-bowler">
                  <span class="sb-bowl-name">—</span>
                  <span class="sb-bowl-fig"></span>
                  <span class="sb-bowl-overs" id="sb-bowl-overs"></span>
                </div>
                <div class="prism-rates">
                  <div id="sb-crr">CRR : 0.00</div>
                  <div id="sb-rrr" class="is-idle">RRR : —</div>
                </div>
                <div class="prism-last" id="sb-last-action">
                  <em>LAST</em>
                  <strong id="sb-last-action-text">—</strong>
                </div>
              </section>
            </div>
            <div class="sb-chase" id="sb-chase" style="display:none"></div>
            <div class="sb-result" id="sb-result" style="display:none"></div>
            <div class="hub-hidden" aria-hidden="true">
              <span id="sb-team-name"></span>
              <span id="sb-bowl-team"></span>
              <span id="sb-bowl-meta"></span>
              <span id="sb-partnership"></span>
              <span id="sb-extras"></span>
              <span id="sb-toss"></span>
              <span id="sb-pp"></span>
            </div>
          </div>
        </div>
      `;
      this.els = {
        logo: this.root.querySelector("#sb-logo"),
        logoFb: this.root.querySelector("#sb-logo-fb"),
        logoRight: this.root.querySelector("#sb-logo-right"),
        teamName: this.root.querySelector("#sb-team-name"),
        teamFull: this.root.querySelector("#sb-team-full"),
        teamAShort: this.root.querySelector("#sb-team-a-short"),
        teamBShort: this.root.querySelector("#sb-team-b-short"),
        tournament: this.root.querySelector("#sb-tournament"),
        matchMeta: this.root.querySelector("#sb-match-meta"),
        score: this.root.querySelector("#sb-score"),
        wickets: this.root.querySelector("#sb-wickets"),
        overs: this.root.querySelector("#sb-overs"),
        otherTeam: this.root.querySelector("#sb-other-team"),
        otherScore: this.root.querySelector("#sb-other-score"),
        bat1: this.root.querySelector("#sb-bat1"),
        bat2: this.root.querySelector("#sb-bat2"),
        bowler: this.root.querySelector("#sb-bowler"),
        bowlOvers: this.root.querySelector("#sb-bowl-overs"),
        bowlMeta: this.root.querySelector("#sb-bowl-meta"),
        toss: this.root.querySelector("#sb-toss"),
        crr: this.root.querySelector("#sb-crr"),
        rrr: this.root.querySelector("#sb-rrr"),
        bowlTeam: this.root.querySelector("#sb-bowl-team"),
        lastAction: this.root.querySelector("#sb-last-action"),
        lastActionText: this.root.querySelector("#sb-last-action-text"),
        partnership: this.root.querySelector("#sb-partnership"),
        chase: this.root.querySelector("#sb-chase"),
        result: this.root.querySelector("#sb-result"),
        balls: this.root.querySelector("#sb-balls"),
        extras: this.root.querySelector("#sb-extras"),
        pp: this.root.querySelector("#sb-pp"),
        look: this.root.querySelector("#sb-look"),
        lookLabel: this.root.querySelector("#sb-look-label"),
        lookValue: this.root.querySelector("#sb-look-value"),
        sb: this.root.querySelector("#scoreboard"),
      };
      this.lookSlides = [];
      this.lookIndex = 0;
      this.startLookRotate();
    }
  }

  function createScoreboard(root, pkg) {
    return pkg === "02" ? new PrismScoreboard(root) : new MainScoreboard(root);
  }

  function ballClass(b) {
    const s = safeStr(b).toLowerCase();
    if (s === "w" || s === "wk" || s === "wicket") return "wicket";
    if (s === "4" || s === "four") return "four";
    if (s === "6" || s === "six") return "six";
    if (s === "•" || s === "." || s === "0" || s === "dot") return "dot";
    if (s === "wd" || s === "nb" || s === "wide" || s === "noball") return "extra";
    return "run";
  }

  function ballDisplay(b) {
    const s = safeStr(b).toLowerCase();
    if (s === "w" || s === "wk" || s === "wicket") return "W";
    if (s === "four") return "4";
    if (s === "six") return "6";
    if (s === "dot" || s === ".") return "•";
    if (s === "wide") return "WD";
    if (s === "noball") return "NB";
    return safeStr(b, "•").toUpperCase();
  }

  /* ── Graphics Engine ── */

  class GraphicsEngine {
    constructor(options = {}) {
      this.canvas = options.canvas;
      this.eventLayer = options.eventLayer;
      this.fullscreenLayer = options.fullscreenLayer;
      this.scoreboardRoot = options.scoreboardRoot;
      this.state = null;
      this.activeGraphics = new Map();
      this.focusExclusive = false;
      this._restoreScoreboardTimer = null;
      this._bdPrev = null;
      this.package = options.package
        || window.CRICKET_THEME?.package
        || this.canvas?.dataset?.overlayPackage
        || "01";
      this.scoreboard = createScoreboard(this.scoreboardRoot, this.package);
      this.scaleCanvas();
      window.addEventListener("resize", () => this.scaleCanvas());
    }

    setPackage(pkg) {
      const next = pkg === "02" ? "02" : "01";
      if (this.package === next) return;
      const wasVisible = this.state?.visible !== false
        && (this.state?.displayMode || "scoreboard") === "scoreboard"
        && !this.focusExclusive;
      this.package = next;
      this.scoreboard = createScoreboard(this.scoreboardRoot, next);
      if (this.state) this.scoreboard.update(this.state);
      this.scoreboard.setVisible(wasVisible);
    }

    scaleCanvas() {
      if (!this.canvas) return;
      const w = window.innerWidth;
      const h = window.innerHeight;
      // Exact OBS Browser Source size → no CSS scale (sharpest)
      if (w === 1920 && h === 1080) {
        this.canvas.style.transform = "none";
        this.canvas.style.left = "0";
        this.canvas.style.top = "0";
        this.canvas.style.width = "1920px";
        this.canvas.style.height = "1080px";
        return;
      }
      // Avoid sub-pixel blur from fractional scales
      let scale = Math.min(w / 1920, h / 1080);
      if (Math.abs(scale - 1) < 0.002) scale = 1;
      scale = Math.round(scale * 1000) / 1000;
      this.canvas.style.transformOrigin = "center center";
      this.canvas.style.transform = `translate(-50%, -50%) scale(${scale})`;
      this.canvas.style.left = "50%";
      this.canvas.style.top = "50%";
      this.canvas.style.width = "1920px";
      this.canvas.style.height = "1080px";
    }

    applyTeamTheme(team) {
      const colors = teamColors(team);
      document.documentElement.style.setProperty("--broadcast-accent", colors.primary);
    }

    updateState(state) {
      this.state = state;
      const mode = state.displayMode || "scoreboard";
      const showSb = state.visible !== false && mode === "scoreboard" && !this.focusExclusive;
      this.scoreboard.setVisible(showSb);
      if (state.visible !== false && mode === "scoreboard") {
        const batting = state.battingTeam === "A" ? state.teamA : state.teamB;
        this.applyTeamTheme(batting);
        this.scoreboard.update(state);
      }
    }

    clearAllGraphics() {
      clearTimeout(this._restoreScoreboardTimer);
      this.activeGraphics.forEach((g) => {
        try {
          clearTimeout(g.timer);
          g.onDismissed = null;
          if (g.node) {
            g.node.remove();
            g.node = null;
          }
        } catch (_) { /* ignore */ }
      });
      this.activeGraphics.clear();
      if (this.eventLayer) this.eventLayer.innerHTML = "";
      if (this.fullscreenLayer) this.fullscreenLayer.innerHTML = "";
    }

    beginExclusiveFocus() {
      this.focusExclusive = true;
      clearTimeout(this._restoreScoreboardTimer);
      this.clearAllGraphics();
      this.hideScoreboardTemporarily();
      document.getElementById("graphics-root")?.classList.add("gfx-focus-mode");
    }

    endExclusiveFocus() {
      this.focusExclusive = false;
      document.getElementById("graphics-root")?.classList.remove("gfx-focus-mode");
      this.showScoreboard();
    }

    onGraphicRemoved(key) {
      this.activeGraphics.delete(key);
      if (this.activeGraphics.size === 0 && this.focusExclusive) {
        this.endExclusiveFocus();
      }
    }

    hideScoreboardTemporarily() {
      this.scoreboard.setVisible(false);
    }

    showScoreboard() {
      if (this.focusExclusive) return;
      if (this.state?.visible !== false && (this.state?.displayMode || "scoreboard") === "scoreboard") {
        this.scoreboard.setVisible(true);
      }
    }

    show(type, data = {}, options = {}) {
      const normalized = type.toLowerCase().replace(/-/g, "_");
      const asCommentator =
        normalized === "commentator"
        || (normalized === "message" && (/^commentator\s*:/i.test(String(data.text || "")) || data.commentator));
      const playType = asCommentator ? "commentator" : normalized;
      const duration = options.duration || this.defaultDuration(playType);
      const corner = options.exclusive === false || this.isCornerGraphic(playType);
      const layer = (!corner && this.isFullscreen(playType)) ? this.fullscreenLayer : this.eventLayer;

      if (!corner) {
        this.beginExclusiveFocus();
      } else {
        // Replace only matching HUD graphics; keep scoreboard visible.
        for (const [key, g] of [...this.activeGraphics.entries()]) {
          const same =
            (playType === "boundaries_counter" && (String(key).startsWith("boundaries_counter") || g?.id === "boundaries_counter"))
            || (playType === "player_card" && (String(key).startsWith("player_card") || g?.id === "player_card"))
            || (playType === "instant_show" && (String(key).startsWith("instant_show") || g?.id === "instant_show"))
            || (playType === "commentator" && (String(key).startsWith("commentator") || g?.id === "commentator" || g?.node?.classList?.contains("gfx-commentator-is")))
            || (playType === "partnership" && (String(key).startsWith("partnership") || g?.id === "partnership"));
          if (!same) continue;
          clearTimeout(g.timer);
          g.onDismissed = null;
          g.node?.remove();
          this.activeGraphics.delete(key);
        }
      }

      let graphic;
      switch (playType) {
        case "four":
        case "six":
          graphic = new RunEventGraphic(this, playType, data);
          break;
        case "wicket":
        case "out":
          graphic = new WicketGraphic(this, data);
          break;
        case "toss":
          graphic = new TossGraphic(this, data);
          break;
        case "need_to_win":
          graphic = new NeedToWinGraphic(this, data);
          break;
        case "innings_break":
          graphic = new InningsBreakGraphic(this, data);
          break;
        case "winner":
          graphic = new WinnerGraphic(this, data);
          break;
        case "partnership":
          graphic = new PartnershipGraphic(this, data);
          break;
        case "player_card":
          graphic = new PlayerCardGraphic(this, data);
          break;
        case "instant_show":
          graphic = new InstantShowGraphic(this, data);
          break;
        case "team_lineup":
          graphic = new TeamLineupGraphic(this, data);
          break;
        case "match_summary":
          graphic = new MatchSummaryGraphic(this, data);
          break;
        case "live_scorecard":
          graphic = new LiveScorecardGraphic(this, data);
          break;
        case "milestone":
          graphic = new MilestoneGraphic(this, data);
          break;
        case "batting_summary":
          graphic = new BattingSummaryGraphic(this, data);
          break;
        case "bowling_summary":
          graphic = new BowlingSummaryGraphic(this, data);
          break;
        case "best_batsman":
        case "player_of_match":
          graphic = new BestBatsmanGraphic(this, data);
          break;
        case "tournament_name":
          graphic = new TournamentNameGraphic(this, data);
          break;
        case "team_vs_team":
          graphic = new TeamVsTeamGraphic(this, data);
          break;
        case "knockout_round":
          graphic = new KnockoutRoundGraphic(this, data);
          break;
        case "boundaries_counter":
          graphic = new BoundariesCounterGraphic(this, data);
          break;
        case "blackboard":
          graphic = new BlackboardGraphic(this, data);
          break;
        case "both_squads":
          graphic = new BothSquadsGraphic(this, data);
          break;
        case "field_position":
          graphic = new FieldPositionGraphic(this, data);
          break;
        case "commentator":
          graphic = new CustomMessageGraphic(this, "commentator", {
            ...data,
            commentator: data.commentator || String(data.text || "").replace(/^commentator\s*:/i, "").trim(),
          });
          break;
        case "custom":
        case "sponsor":
        case "message":
          graphic = new CustomMessageGraphic(this, playType, data);
          break;
        default:
          graphic = new CustomMessageGraphic(this, playType, { text: playType.toUpperCase(), ...data });
      }

      const key = `${playType}-${Date.now()}`;
      graphic.mapKey = key;
      this.activeGraphics.set(key, graphic);

      if (playType === "four" || playType === "six") {
        graphic.onDismissed = () => this.queueBoundariesAfterBoundary(playType);
      }

      graphic.mount(layer);
      graphic.scheduleRemove(duration);
      return graphic;
    }

    defaultDuration(type) {
      const map = {
        six: 5200,
        four: 4800,
        wicket: 5800,
        out: 5600,
        toss: 7000,
        need_to_win: 6000,
        innings_break: 6500,
        winner: 7500,
        partnership: 5600,
        player_card: 5800,
        instant_show: 10000,
        team_lineup: 7500,
        match_summary: 14000,
        live_scorecard: 12000,
        batting_summary: 9500,
        bowling_summary: 9500,
        best_batsman: 7000,
        player_of_match: 7000,
        tournament_name: 6000,
        milestone: 4800,
        team_vs_team: 7000,
        knockout_round: 12000,
        boundaries_counter: 6000,
        blackboard: 6500,
        both_squads: 9000,
        field_position: 9500,
        custom: 4500,
        sponsor: 5000,
        message: 5000,
        commentator: 6500,
      };
      return map[type] || 5000;
    }

    isFullscreen(type) {
      return ["six", "four", "wicket", "out", "toss", "need_to_win", "innings_break", "winner", "match_summary", "live_scorecard", "team_lineup", "batting_summary", "bowling_summary", "team_vs_team", "knockout_round", "both_squads", "blackboard", "field_position", "tournament_name", "message", "custom", "sponsor"].includes(type);
    }

    isCornerGraphic(type) {
      return type === "boundaries_counter" || type === "player_card" || type === "instant_show" || type === "partnership" || type === "commentator";
    }

    matchBoundaryTotals(state = this.state) {
      const sumTeam = (team) => ({
        fours: (team?.batsmen || []).reduce((n, b) => n + (b.fours || 0), 0),
        sixes: (team?.batsmen || []).reduce((n, b) => n + (b.sixes || 0), 0),
      });
      const a = sumTeam(state?.teamA);
      const b = sumTeam(state?.teamB);
      return { fours: a.fours + b.fours, sixes: a.sixes + b.sixes };
    }

    buildBoundariesCornerPayload(extra = {}) {
      const s = this.state || {};
      const scope = String(extra.scope || s.boundariesScope || "match").toLowerCase() === "tournament"
        ? "tournament"
        : "match";
      const live = this.matchBoundaryTotals(s);
      const fours = extra.fours != null ? Number(extra.fours) || 0 : live.fours;
      const sixes = extra.sixes != null ? Number(extra.sixes) || 0 : live.sixes;
      const prev = this._bdPrev || {
        fours: Math.max(0, fours - (extra.bump === "four" ? 1 : 0)),
        sixes: Math.max(0, sixes - (extra.bump === "six" ? 1 : 0)),
      };
      return {
        title: "BOUNDARIES",
        scope,
        label: scope === "match" ? "THIS MATCH" : (extra.label || "TOURNAMENT"),
        organizerLogo: s.organizerLogo || "",
        streamerLogo: s.streamerLogo || "",
        ...extra,
        fours,
        sixes,
        fromFours: extra.fromFours != null ? Number(extra.fromFours) || 0 : prev.fours,
        fromSixes: extra.fromSixes != null ? Number(extra.fromSixes) || 0 : prev.sixes,
        bump: extra.bump || "",
      };
    }

    showBoundariesCorner(extra = {}) {
      const payload = this.buildBoundariesCornerPayload(extra);
      this._bdPrev = { fours: payload.fours, sixes: payload.sixes };
      return this.show("boundaries_counter", payload, {
        exclusive: false,
        duration: extra.duration || 4800,
      });
    }

    queueBoundariesAfterBoundary(kind) {
      this.showBoundariesCorner({
        scope: "match",
        bump: kind === "six" ? "six" : "four",
      });
    }

    buildPayloadFromState(type) {
      const s = this.state;
      if (!s) return {};
      const batting = s.battingTeam === "A" ? s.teamA : s.teamB;
      const bowling = s.battingTeam === "A" ? s.teamB : s.teamA;
      const colors = teamColors(batting, DEFAULTS.primaryColor);
      const bat1 = resolveBatter(batting, 1);
      const bat2 = resolveBatter(batting, 2);
      const bowl = resolveBowler(bowling);

      switch (type) {
        case "need_to_win": {
          const needed = (s.target ?? 0) - (batting.score ?? 0);
          return {
            runs: Math.max(0, needed),
            balls: ballsRemaining(batting.overs, s.totalOvers || 20),
            teamName: batting.name,
            primaryColor: DEFAULTS.primaryColor,
          };
        }
        case "innings_break": {
          const le = s.lastEvent && typeof s.lastEvent === "object" ? s.lastEvent : null;
          if (le?.type === "innings_break" && (le.teamName || le.score != null)) {
            return {
              teamName: le.teamName || bowling.name,
              score: le.score ?? bowling.score,
              wickets: le.wickets ?? bowling.wickets,
              overs: le.overs || bowling.overs,
              target: le.target ?? s.target,
              result: le.result || s.result || "",
            };
          }
          // After end_innings the finished side is bowling; otherwise show current batting.
          const finished = Number(s.innings) >= 2 ? bowling : batting;
          return {
            teamName: finished.name,
            score: finished.score,
            wickets: finished.wickets,
            overs: finished.overs,
            target: s.target,
            result: s.result || "",
          };
        }
        case "match_summary": {
          const packTeam = (team, bowlingSide) => {
            const batting = (team.batsmen || [])
              .slice()
              .map((b) => ({
                name: b.name,
                out: !!b.out,
                dismissal: b.out ? (b.dismissal || "out") : "not out",
                runs: b.runs ?? 0,
                balls: b.balls ?? 0,
              }));
            // Bowling against this team = the other side's bowlers.
            const bowling = (bowlingSide.bowlers || []).map((b) => ({
              name: b.name,
              figures: `${b.wickets ?? 0}-${b.runs ?? 0}`,
              overs: ballsToOversStr(b.balls),
              maidens: b.maidens ?? 0,
              runs: b.runs ?? 0,
              wickets: b.wickets ?? 0,
              econ: calcEcon(b.runs, b.balls),
            }));
            return {
              name: team.name,
              score: team.score ?? 0,
              wickets: team.wickets ?? 0,
              overs: team.overs || "0.0",
              batting,
              bowling,
            };
          };
          return {
            tournament: s.matchTitle,
            matchNo: s.matchNo,
            round: s.round || "",
            result: s.result || "Match in progress",
            teamA: packTeam(s.teamA, s.teamB),
            teamB: packTeam(s.teamB, s.teamA),
            // legacy fields
            teamAName: s.teamA.name,
            teamBName: s.teamB.name,
            teamAScore: s.teamA.score,
            teamAWickets: s.teamA.wickets,
            teamAOvers: s.teamA.overs,
            teamBScore: s.teamB.score,
            teamBWickets: s.teamB.wickets,
            teamBOvers: s.teamB.overs,
          };
        }
        case "live_scorecard": {
          const packTeam = (team, bowlingSide) => {
            const batting = (team?.batsmen || []).map((p) => ({
              name: p.name,
              out: !!p.out,
              dismissal: p.out ? (p.dismissal || "out") : "not out",
              runs: p.runs ?? 0,
              balls: p.balls ?? 0,
              fours: p.fours ?? 0,
              sixes: p.sixes ?? 0,
              sr: calcSR(p.runs, p.balls),
            }));
            const bowling = (bowlingSide?.bowlers || []).map((p) => ({
              name: p.name,
              overs: ballsToOversStr(p.balls),
              maidens: p.maidens ?? 0,
              runs: p.runs ?? 0,
              wickets: p.wickets ?? 0,
              econ: calcEcon(p.runs, p.balls),
              dots: p.dots ?? 0,
            }));
            const fow = (team?.batsmen || [])
              .filter((p) => p.out)
              .map((p, i) => `${i + 1}-${p.fowScore ?? team?.score ?? 0} (${p.name})`);
            const onCard = new Set(
              (team?.batsmen || []).map((p) => String(p.name || "").toLowerCase()).filter(Boolean)
            );
            const didNotBat = (team?.squad || [])
              .map((p) => (typeof p === "string" ? p : p?.name) || "")
              .filter((n) => n && !onCard.has(String(n).toLowerCase()));
            return {
              name: team?.name || "TEAM",
              score: team?.score ?? 0,
              wickets: team?.wickets ?? 0,
              overs: team?.overs || "0.0",
              extras: team?.extras || {},
              batting,
              bowling,
              fow,
              didNotBat,
            };
          };
          const completed = s.matchStatus === "completed";
          const battingSide = s.battingTeam === "B" ? s.teamB : s.teamA;
          const bowlingSide = s.battingTeam === "B" ? s.teamA : s.teamB;
          // Match end → both innings scorecards (control Match Summary look).
          const teams = completed
            ? [packTeam(s.teamA, s.teamB), packTeam(s.teamB, s.teamA)]
            : [packTeam(battingSide, bowlingSide)];
          return {
            title: completed
              ? (s.matchTitle || "Match Summary")
              : (s.matchTitle || "Live Scorecard"),
            tournament: s.matchTitle,
            matchNo: s.matchNo,
            result: completed
              ? (s.result || "Match completed")
              : (s.result || "Match in progress"),
            teams,
          };
        }
        case "winner": {
          const result = safeStr(s.result);
          let winnerName = batting.name;
          let logo = batting.logo;
          if (s.teamA?.name && result.toLowerCase().includes(String(s.teamA.name).toLowerCase())) {
            winnerName = s.teamA.name;
            logo = s.teamA.logo;
          } else if (s.teamB?.name && result.toLowerCase().includes(String(s.teamB.name).toLowerCase())) {
            winnerName = s.teamB.name;
            logo = s.teamB.logo;
          }
          return {
            teamName: winnerName,
            result: result || `${winnerName} WON`,
            logo,
            primaryColor: DEFAULTS.primaryColor,
            tournament: s.matchTitle,
          };
        }
        case "toss":
          return {
            teamAName: s.teamA?.name,
            teamBName: s.teamB?.name,
            teamALogo: s.teamA?.logo,
            teamBLogo: s.teamB?.logo,
            winnerName: s.toss?.winner || "",
            decision: s.toss?.decision === "bowl" ? "elected to bowl" : (s.toss?.winner ? "elected to bat" : ""),
            matchNo: s.matchNo,
            tournament: s.matchTitle,
            text: s.toss?.text || "",
          };
        case "partnership": {
          const runs = (Number(bat1.runs) || 0) + (Number(bat2.runs) || 0);
          const balls = (Number(bat1.balls) || 0) + (Number(bat2.balls) || 0);
          return {
            player1: bat1.name || "Batter 1",
            player2: bat2.name || "Batter 2",
            runs,
            balls,
          };
        }
        case "milestone": {
          const r = Number(bat1.runs) || 0;
          const milestone = r >= 100 ? 100 : 50;
          return {
            runs: milestone,
            player: bat1.name || "Batter",
            sub: `${bat1.runs || 0} (${bat1.balls || 0})`,
          };
        }
        case "player_card": {
          const role = String(this._pendingRole || "").toLowerCase();
          // Prefer a fully-built server/client payload when player was picked from squad.
          if (this._pendingPlayerCard && (this._pendingPlayerCard.playerName || this._pendingPlayerCard.playerId)) {
            return this._pendingPlayerCard;
          }
          const findSquad = (name, teamObj) => {
            const n = String(name || "").toLowerCase();
            return (teamObj?.squad || []).find((p) => String(p?.name || "").toLowerCase() === n) || null;
          };
          let player = bat1;
          let teamName = batting.name;
          let teamObj = batting;
          if (role === "nonstriker" || role === "non_striker") {
            player = bat2;
          } else if (role === "bowler") {
            player = bowl;
            teamName = bowling.name;
            teamObj = bowling;
            const sq = findSquad(player.name, teamObj);
            const avatar = sq?.avatar || player.avatar || this.state?.playerAvatar || "";
            return {
              mode: "live",
              role: "bowler",
              playingRole: sq?.role || "bowler",
              battingStyle: sq?.battingStyle || "",
              bowlingStyle: sq?.bowlingStyle || "",
              teamName,
              playerName: player.name || "Bowler",
              avatar,
              playerAvatar: avatar,
              runs: player.runs ?? 0,
              balls: player.balls ?? 0,
              overs: ballsToOversStr(player.balls),
              wickets: player.wickets ?? 0,
              economy: calcEcon(player.runs, player.balls),
              dots: player.dots ?? 0,
              fours: 0,
              sixes: 0,
              strikeRate: "—",
              matches: sq?.history?.matches ?? 0,
              average: sq?.history?.average || "",
              best: sq?.history?.best || "",
              history: sq?.history || null,
            };
          }
          const sq = findSquad(player.name, teamObj);
          const avatar = sq?.avatar || player.avatar || this.state?.playerAvatar || "";
          const sr = player.sr ?? calcSR(player.runs, player.balls);
          return {
            mode: "live",
            role: role || "striker",
            playingRole: sq?.role || "batsman",
            battingStyle: sq?.battingStyle || "",
            bowlingStyle: sq?.bowlingStyle || "",
            teamName,
            playerName: player.name || "Player",
            avatar,
            playerAvatar: avatar,
            runs: player.runs ?? 0,
            balls: player.balls ?? 0,
            fours: player.fours ?? 0,
            sixes: player.sixes ?? 0,
            strikeRate: sr,
            sr,
            matches: sq?.history?.matches ?? 0,
            average: sq?.history?.average || "",
            best: sq?.history?.best || "",
            history: sq?.history || null,
          };
        }
        case "instant_show": {
          const packBat = (p, role) => ({
            name: p?.name || "—",
            role,
            team: batting.name,
            runs: p?.runs ?? 0,
            balls: p?.balls ?? 0,
            fours: p?.fours ?? 0,
            sixes: p?.sixes ?? 0,
            sr: calcSR(p?.runs, p?.balls),
          });
          const livePlayers = [];
          if (bat1?.name) livePlayers.push(packBat(bat1, "STRIKER"));
          if (bat2?.name) livePlayers.push(packBat(bat2, "NON-STRIKER"));
          (batting.batsmen || []).forEach((p) => {
            if (!p?.name) return;
            const id = p.id;
            if (id && (id === batting.strikerId || id === batting.nonStrikerId)) return;
            if (livePlayers.some((x) => x.name.toLowerCase() === String(p.name).toLowerCase())) return;
            livePlayers.push({
              name: p.name,
              role: p.out ? "OUT" : "BATTED",
              team: batting.name,
              runs: p.runs ?? 0,
              balls: p.balls ?? 0,
              fours: p.fours ?? 0,
              sixes: p.sixes ?? 0,
              sr: calcSR(p.runs, p.balls),
            });
          });
          if (bowl?.name) {
            livePlayers.push({
              name: bowl.name,
              role: "BOWLER",
              team: bowling.name,
              runs: bowl.runs ?? 0,
              wickets: bowl.wickets ?? 0,
              overs: ballsToOversStr(bowl.balls),
              econ: calcEcon(bowl.runs, bowl.balls),
              dots: bowl.dots ?? 0,
            });
          }
          const milestones = (batting.batsmen || [])
            .filter((p) => (p.runs || 0) >= 50)
            .map((p) => ({
              runs: (p.runs || 0) >= 100 ? 100 : 50,
              player: p.name,
              detail: `${p.runs ?? 0} (${p.balls ?? 0})`,
            }))
            .sort((a, b) => b.runs - a.runs);
          const bd = this.matchBoundaryTotals(s);
          return {
            subtitle: `${safeStr(s.matchTitle || "LIVE")} · MATCH ${safeStr(s.matchNo || "1")}`,
            players: livePlayers,
            boundariesMatch: bd,
            boundariesTournament: bd,
            milestones,
            partnership: {
              player1: bat1.name || "Batter 1",
              player2: bat2.name || "Batter 2",
              runs: (Number(bat1.runs) || 0) + (Number(bat2.runs) || 0),
              balls: (Number(bat1.balls) || 0) + (Number(bat2.balls) || 0),
            },
          };
        }
        case "team_lineup":
          return this.buildTeamLineupPayload(null);
        case "batting_summary":
        case "bowling_summary":
          return this.buildInningsCardPayload(type, null);
        case "best_batsman":
        case "player_of_match": {
          const list = (batting.batsmen || []).slice().sort((a, b) => (b.runs || 0) - (a.runs || 0));
          const top = list[0] || {};
          return {
            playerName: top.name || bat1.name || "Player",
            teamName: batting.name,
            initials: (top.name || bat1.name || "BB").slice(0, 2),
            title: type === "player_of_match" ? "PLAYER OF THE MATCH" : "BEST BATSMAN",
            runs: top.runs ?? bat1.runs ?? 0,
            balls: top.balls ?? bat1.balls ?? 0,
            fours: top.fours ?? 0,
            sixes: top.sixes ?? 0,
            sr: calcSR(top.runs ?? bat1.runs, top.balls ?? bat1.balls),
            bowling: "—",
            catches: 0,
            runouts: 0,
          };
        }
        case "team_vs_team":
          return {
            teamAName: s.teamA?.name,
            teamBName: s.teamB?.name,
            teamALogo: s.teamA?.logo,
            teamBLogo: s.teamB?.logo,
            matchNo: s.matchNo,
            tournament: s.matchTitle,
            matchTitle: s.matchTitle,
          };
        case "knockout_round": {
          const ko = s.knockout || {};
          return {
            title: ko.title || "KNOCKOUT ROUND",
            format: [4, 8, 16].includes(Number(ko.format)) ? Number(ko.format) : 8,
            matches: Array.isArray(ko.matches) ? ko.matches : [],
            roundDates: ko.roundDates || {},
            tournament: s.matchTitle,
            matchTitle: s.matchTitle,
            matchNo: s.matchNo,
          };
        }
        case "boundaries_counter": {
          const sumTeam = (team) => ({
            fours: (team?.batsmen || []).reduce((n, b) => n + (b.fours || 0), 0),
            sixes: (team?.batsmen || []).reduce((n, b) => n + (b.sixes || 0), 0),
          });
          const a = sumTeam(s.teamA);
          const b = sumTeam(s.teamB);
          const scope = String(s.boundariesScope || "tournament").toLowerCase() === "match" ? "match" : "tournament";
          return {
            scope,
            label: scope === "match" ? "THIS MATCH" : "TOURNAMENT",
            title: "BOUNDARIES",
            fours: a.fours + b.fours,
            sixes: a.sixes + b.sixes,
            teamA: null,
            teamB: null,
          };
        }
        case "blackboard":
          return { text: s.customMessage || s.result || s.matchTitle || "Black Board", title: "NOTICE BOARD" };
        case "both_squads":
          return {
            matchNo: s.matchNo,
            teamA: {
              name: s.teamA?.name,
              logo: s.teamA?.logo,
              players: [...(s.teamA?.squad || []).map((p) => p.name), ...(s.teamA?.batsmen || []).map((b) => b.name)].filter(Boolean).slice(0, 11),
              avatars: (s.teamA?.squad || []).map((p) => ({ name: p.name, avatar: p.avatar || "" })),
            },
            teamB: {
              name: s.teamB?.name,
              logo: s.teamB?.logo,
              players: [...(s.teamB?.squad || []).map((p) => p.name), ...(s.teamB?.batsmen || []).map((b) => b.name)].filter(Boolean).slice(0, 11),
              avatars: (s.teamB?.squad || []).map((p) => ({ name: p.name, avatar: p.avatar || "" })),
            },
          };
        case "field_position": {
          const bowlSide = s.battingTeam === "B" ? s.teamA : s.teamB;
          const striker = (batting.batsmen || []).find((p) => p.id === batting.strikerId);
          const non = (batting.batsmen || []).find((p) => p.id === batting.nonStrikerId);
          const bowl = (bowlSide?.bowlers || []).find((p) => p.id === bowlSide?.currentBowlerId);
          return {
            positions: s.fieldPositions || [],
            zone: s.fieldZone || "Standard",
            battingTeam: batting.name,
            bowlingTeam: bowlSide?.name,
            bowler: bowl?.name || "",
            striker: striker?.name || "",
            nonStriker: non?.name || "",
            bowlingSquad: [...new Set([
              ...(bowlSide?.squad || []).map((p) => p.name),
              ...(bowlSide?.bowlers || []).map((p) => p.name),
            ].filter(Boolean))],
          };
        }
        case "tournament_name":
          return {
            title: s.matchTitle || "Tournament",
            tournament: s.matchTitle,
            initials: safeStr(s.matchTitle, "CT").slice(0, 2),
            organizerLogo: s.organizerLogo || "",
            streamerLogo: s.streamerLogo || "",
          };
        default:
          return {};
      }
    }

    buildTeamLineupPayload(teamKey) {
      const s = this.state;
      if (!s) return {};
      const key = teamKey === "B" || teamKey === "A"
        ? teamKey
        : ((s.battingTeam || "A") === "B" ? "B" : "A");
      const team = key === "B" ? s.teamB : s.teamA;
      const squad = team?.squad || [];
      const fromSquad = squad.map((p) => p.name).filter(Boolean);
      const fromBat = (team?.batsmen || []).map((b) => b.name).filter(Boolean);
      const names = (fromSquad.length ? fromSquad : fromBat).slice(0, 11);
      return {
        teamName: team?.name,
        logo: team?.logo,
        matchNo: s.matchNo,
        round: s.round || "",
        players: names,
        avatars: names.map((name) => {
          const hit = squad.find((p) => safeStr(p?.name).toLowerCase() === safeStr(name).toLowerCase());
          return { name, avatar: hit?.avatar || "" };
        }),
        primaryColor: DEFAULTS.primaryColor,
      };
    }

    buildInningsCardPayload(type, teamKey) {
      const s = this.state;
      if (!s) return {};
      const key = teamKey === "B" || teamKey === "A" ? teamKey : (s.battingTeam || "A");
      const batSide = key === "A" ? s.teamA : s.teamB;
      const other = key === "A" ? s.teamB : s.teamA;

      if (type === "batting_summary") {
        const batsmen = batSide.batsmen || [];
        const byName = new Map();
        batsmen.forEach((b) => {
          const key = safeStr(b.name).toLowerCase();
          if (key) byName.set(key, b);
        });

        const squadNames = (batSide.squad || [])
          .map((p) => safeStr(p?.name))
          .filter(Boolean)
          .slice(0, 11);

        const orderedNames = squadNames.length
          ? squadNames
          : batsmen.map((b) => safeStr(b.name)).filter(Boolean);

        // Include any batsmen missing from squad (came in without squad entry)
        batsmen.forEach((b) => {
          const n = safeStr(b.name);
          if (!n) return;
          if (!orderedNames.some((x) => x.toLowerCase() === n.toLowerCase())) {
            orderedNames.push(n);
          }
        });

        const players = orderedNames.slice(0, 11).map((name) => {
          const live = byName.get(name.toLowerCase());
          if (!live) {
            return {
              name,
              runs: 0,
              balls: 0,
              out: false,
              yetToBat: true,
              dismissal: "Yet to bat",
            };
          }
          return {
            name: live.name || name,
            runs: live.runs ?? 0,
            balls: live.balls ?? 0,
            out: !!live.out,
            yetToBat: false,
            dismissal: live.out ? (live.dismissal || "out") : "Not out",
          };
        });

        return {
          teamName: batSide.name,
          teamLogo: batSide.logo || "",
          matchNo: s.matchNo,
          round: s.round || "",
          overs: batSide.overs || "0.0",
          score: batSide.score ?? 0,
          wickets: batSide.wickets ?? 0,
          extras: batSide.extras || {},
          badgeLeft: teamShortName(batSide).slice(0, 2),
          badgeRight: teamShortName(other).slice(0, 2),
          organizerLogo: s.organizerLogo || "",
          streamerLogo: s.streamerLogo || "",
          players,
        };
      }

      // bowling_summary — bowlers for selected team vs opposition batting innings
      const bowlSide = key === "A" ? s.teamA : s.teamB;
      const oppBat = key === "A" ? s.teamB : s.teamA;
      const fow = (oppBat.batsmen || [])
        .filter((b) => b.out)
        .map((b, i) => b.fowScore != null ? b.fowScore : (i + 1) * Math.max(1, Math.floor((oppBat.score || 0) / Math.max(1, oppBat.wickets || 1))));

      return {
        teamName: bowlSide.name,
        teamLogo: bowlSide.logo || "",
        matchNo: s.matchNo,
        round: s.round || "",
        overs: oppBat.overs || "0.0",
        score: oppBat.score ?? 0,
        wickets: oppBat.wickets ?? 0,
        extras: oppBat.extras || {},
        badgeLeft: teamShortName(bowlSide).slice(0, 2),
        badgeRight: teamShortName(oppBat).slice(0, 2),
        organizerLogo: s.organizerLogo || "",
        streamerLogo: s.streamerLogo || "",
        fow,
        bowlers: (bowlSide.bowlers || []).map((b) => ({
          name: b.name,
          overs: ballsToOversStr(b.balls),
          runs: b.runs ?? 0,
          wickets: b.wickets ?? 0,
          dots: b.dots ?? 0,
          econ: calcEcon(b.runs, b.balls),
        })),
      };
    }

    playAnimation(animation, payload) {
      const type = animation.toLowerCase();
      this._pendingRole = payload?.role || "";
      this._pendingPlayerCard = (type === "player_card" && payload && (payload.playerId || payload.playerName || payload.mode === "history"))
        ? payload
        : null;
      let base = this.buildPayloadFromState(type);
      if ((type === "batting_summary" || type === "bowling_summary") && payload?.team) {
        base = this.buildInningsCardPayload(type, payload.team);
      }
      if (type === "team_lineup" && payload?.team) {
        base = this.buildTeamLineupPayload(payload.team);
      }
      const merged = {
        ...base,
        ...(payload || {}),
        organizerLogo: payload?.organizerLogo || this.state?.organizerLogo || base.organizerLogo || "",
        streamerLogo: payload?.streamerLogo || this.state?.streamerLogo || base.streamerLogo || "",
        playerAvatar: payload?.playerAvatar || payload?.avatar || this.state?.playerAvatar || base.playerAvatar || "",
        avatar: payload?.avatar || payload?.playerAvatar || base.avatar || this.state?.playerAvatar || "",
      };
      // Always rebuild full batting card from live state (squad + yet to bat)
      if (type === "batting_summary") {
        const card = this.buildInningsCardPayload(type, payload?.team || base.team || null);
        merged.players = card.players;
        merged.teamName = card.teamName || merged.teamName;
        merged.score = card.score;
        merged.wickets = card.wickets;
        merged.overs = card.overs;
        merged.extras = card.extras;
      }
      this._pendingRole = "";
      this._pendingPlayerCard = null;

      if (type === "four" || type === "six") {
        merged.teamColors = {
          primary: type === "six" ? DEFAULTS.accentColor : DEFAULTS.primaryColor,
          secondary: DEFAULTS.secondaryColor,
        };
        if (!merged.player) {
          const batting = this.state?.battingTeam === "A" ? this.state.teamA : this.state?.teamB;
          const b1 = resolveBatter(batting || {}, 1);
          merged.player = b1.name;
        }
      }

      if ((type === "wicket" || type === "out") && !merged.player) {
        const batting = this.state?.battingTeam === "A" ? this.state.teamA : this.state?.teamB;
        const b1 = resolveBatter(batting || {}, 1);
        merged.player = b1.name;
        merged.dismissal = merged.dismissal || "OUT";
      }
      if (type === "out") merged.outOnly = true;

      if (type === "custom") {
        merged.text = payload?.text || "WOW!";
      }

      if (type === "boundaries_counter") {
        this.showBoundariesCorner({
          ...merged,
          scope: payload?.scope || merged.scope || "tournament",
        });
        return;
      }

      this.show(type, merged);
    }
  }

  global.GraphicsEngine = GraphicsEngine;
  global.BroadcastUtils = {
    safeStr, parseBatsmanLine, parseBowlerLine, teamColors, teamShortName,
    calcCRR, calcRRR, ballsRemaining, ballClass, ballDisplay,
  };
})(window);
