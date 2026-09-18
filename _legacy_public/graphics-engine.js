/**
 * Broadcast Graphics Engine — modular overlay system for OBS.
 * GPU-friendly transforms, enter/hold/exit lifecycle, team theming.
 */

(function (global) {
  "use strict";

  const DEFAULTS = {
    primaryColor: "#1a8cff",
    secondaryColor: "#0a1628",
    accentColor: "#38bdf8",
  };

  const TIMING = {
    fast: 300,
    normal: 600,
    slow: 900,
    hold: 1200,
    exit: 500,
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
      this.node = null;
      this.timer = null;
    }

    mount(parent) {
      this.node = this.render();
      if (this.node) parent.appendChild(this.node);
      requestAnimationFrame(() => this.node?.classList.add("gfx-enter"));
      return this;
    }

    scheduleRemove(ms) {
      clearTimeout(this.timer);
      this.timer = setTimeout(() => this.dismiss(), ms);
    }

    dismiss() {
      if (!this.node) return;
      this.node.classList.remove("gfx-enter");
      this.node.classList.add("gfx-exit");
      const n = this.node;
      setTimeout(() => {
        n.remove();
        this.engine.onGraphicRemoved(this.id);
      }, TIMING.exit);
    }

    render() {
      return el("div", `gfx ${this.id}`);
    }
  }

  /* ── Event Graphics ── */

  class RunEventGraphic extends BaseGraphic {
    constructor(engine, type, data) {
      super(engine, type);
      this.type = type;
      this.data = data;
    }

    render() {
      const isSix = this.type === "six";
      const label = isSix ? "SIX" : "FOUR";
      const player = safeStr(this.data.player);
      const colors = this.data.teamColors || DEFAULTS;
      const root = el("div", `gfx gfx-run gfx-${this.type}`);
      root.style.setProperty("--gfx-accent", colors.primary);
      root.innerHTML = `
        <div class="gfx-run-panels">
          <div class="gfx-run-panel gfx-run-panel-a"></div>
          <div class="gfx-run-panel gfx-run-panel-b"></div>
        </div>
        <div class="gfx-run-burst"></div>
        <div class="gfx-run-content">
          <div class="gfx-run-title">${label}</div>
          ${player ? `<div class="gfx-run-player">${player}</div>` : ""}
          <div class="gfx-run-sub">${isSix ? "6 RUNS" : "4 RUNS"}</div>
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
      const root = el("div", "gfx gfx-wicket");
      root.innerHTML = `
        <div class="gfx-wicket-flash"></div>
        <div class="gfx-wicket-sweep"></div>
        <div class="gfx-wicket-content">
          <div class="gfx-wicket-label">WICKET</div>
          <div class="gfx-wicket-out">OUT</div>
          ${player ? `<div class="gfx-wicket-player">${player}</div>` : ""}
          <div class="gfx-wicket-dismissal">${dismissal}</div>
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
      const root = el("div", "gfx gfx-toss");
      root.innerHTML = `
        <div class="gfx-toss-logos">
          <div class="gfx-toss-team gfx-toss-team-a">
            ${d.teamALogo ? `<img src="${d.teamALogo}" alt="" onerror="this.style.display='none'">` : `<span class="gfx-toss-initial">${safeStr(d.teamAName).slice(0,2)}</span>`}
          </div>
          <div class="gfx-toss-team gfx-toss-team-b">
            ${d.teamBLogo ? `<img src="${d.teamBLogo}" alt="" onerror="this.style.display='none'">` : `<span class="gfx-toss-initial">${safeStr(d.teamBName).slice(0,2)}</span>`}
          </div>
        </div>
        <div class="gfx-toss-center">
          <div class="gfx-toss-heading">TOSS</div>
          <div class="gfx-toss-winner">${safeStr(d.winnerName)}</div>
          <div class="gfx-toss-decision">WON THE TOSS</div>
          <div class="gfx-toss-choice">${safeStr(d.decision)}</div>
          ${d.matchNo ? `<div class="gfx-toss-meta">MATCH ${d.matchNo}</div>` : ""}
          ${d.tournament ? `<div class="gfx-toss-tournament">${d.tournament}</div>` : ""}
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
      const root = el("div", "gfx gfx-need");
      root.style.setProperty("--gfx-accent", d.primaryColor || DEFAULTS.primaryColor);
      root.innerHTML = `
        <div class="gfx-need-inner">
          <div class="gfx-need-label">NEED</div>
          <div class="gfx-need-runs">${d.runs ?? "—"}</div>
          <div class="gfx-need-label">RUNS</div>
          <div class="gfx-need-from">FROM <span>${d.balls ?? "—"}</span> BALLS</div>
          <div class="gfx-need-team">${safeStr(d.teamName)}</div>
          <div class="gfx-need-sub">TO WIN</div>
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
      const root = el("div", "gfx gfx-innings");
      root.innerHTML = `
        <div class="gfx-innings-inner">
          <div class="gfx-innings-title">INNINGS BREAK</div>
          <div class="gfx-innings-team">${safeStr(d.teamName)}</div>
          <div class="gfx-innings-score">${d.score ?? 0} / ${d.wickets ?? 0}</div>
          <div class="gfx-innings-overs">${safeStr(d.overs)} OVERS</div>
          ${d.target != null ? `<div class="gfx-innings-target">TARGET <span>${d.target}</span></div>` : ""}
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
      const root = el("div", "gfx gfx-winner");
      root.style.setProperty("--gfx-accent", d.primaryColor || DEFAULTS.accentColor);
      root.innerHTML = `
        <div class="gfx-winner-rays"></div>
        <div class="gfx-winner-inner">
          <div class="gfx-winner-complete">MATCH COMPLETE</div>
          ${d.logo ? `<img class="gfx-winner-logo" src="${d.logo}" alt="" onerror="this.style.display='none'">` : ""}
          <div class="gfx-winner-team">${safeStr(d.teamName)}</div>
          <div class="gfx-winner-result">${safeStr(d.result)}</div>
          ${d.tournament ? `<div class="gfx-winner-tournament">${d.tournament}</div>` : ""}
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
      const root = el("div", "gfx gfx-partnership");
      root.innerHTML = `
        <div class="gfx-partnership-inner">
          <div class="gfx-partnership-title">PARTNERSHIP</div>
          <div class="gfx-partnership-players">
            <span class="gfx-partnership-p1">${safeStr(d.player1)}</span>
            <span class="gfx-partnership-amp">&</span>
            <span class="gfx-partnership-p2">${safeStr(d.player2)}</span>
          </div>
          <div class="gfx-partnership-runs">${d.runs ?? "—"} RUNS</div>
          <div class="gfx-partnership-balls">${d.balls ?? "—"} BALLS</div>
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
      const isBowler = d.role === "bowler";
      const root = el("div", "gfx gfx-player-card");
      root.style.setProperty("--gfx-accent", d.primaryColor || DEFAULTS.primaryColor);
      let stats = "";
      if (isBowler) {
        stats = `
          <div class="gfx-pc-stat"><span>OVERS</span><strong>${safeStr(d.overs, "—")}</strong></div>
          <div class="gfx-pc-stat"><span>RUNS</span><strong>${d.runs ?? "—"}</strong></div>
          <div class="gfx-pc-stat"><span>WKTS</span><strong>${d.wickets ?? "—"}</strong></div>
          <div class="gfx-pc-stat"><span>ECON</span><strong>${safeStr(d.economy, "—")}</strong></div>`;
      } else {
        stats = `
          <div class="gfx-pc-stat"><span>RUNS</span><strong>${d.runs ?? "—"}</strong></div>
          <div class="gfx-pc-stat"><span>BALLS</span><strong>${d.balls ?? "—"}</strong></div>
          <div class="gfx-pc-stat"><span>SR</span><strong>${safeStr(d.strikeRate, "—")}</strong></div>`;
      }
      root.innerHTML = `
        <div class="gfx-pc-inner">
          <div class="gfx-pc-team">${safeStr(d.teamName)}</div>
          <div class="gfx-pc-name">${safeStr(d.playerName)}</div>
          <div class="gfx-pc-stats">${stats}</div>
        </div>
      `;
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
      const players = (d.players || []).filter(Boolean);
      const root = el("div", "gfx gfx-lineup");
      root.style.setProperty("--gfx-accent", d.primaryColor || DEFAULTS.primaryColor);
      const list = players.map((p, i) =>
        `<div class="gfx-lineup-player" style="animation-delay:${i * 80}ms">${p}</div>`
      ).join("");
      root.innerHTML = `
        <div class="gfx-lineup-inner">
          ${d.logo ? `<img class="gfx-lineup-logo" src="${d.logo}" alt="" onerror="this.style.display='none'">` : ""}
          <div class="gfx-lineup-team">${safeStr(d.teamName)}</div>
          <div class="gfx-lineup-list">${list}</div>
        </div>
      `;
      return root;
    }
  }

  class MatchSummaryGraphic extends BaseGraphic {
    constructor(engine, data) {
      super(engine, "match_summary");
      this.data = data;
    }

    render() {
      const d = this.data;
      const root = el("div", "gfx gfx-summary");
      root.innerHTML = `
        <div class="gfx-summary-inner">
          <div class="gfx-summary-title">MATCH SUMMARY</div>
          <div class="gfx-summary-teams">
            <div class="gfx-summary-team">
              <div class="gfx-summary-name">${safeStr(d.teamAName)}</div>
              <div class="gfx-summary-score">${d.teamAScore ?? 0} / ${d.teamAWickets ?? 0}</div>
              <div class="gfx-summary-overs">${safeStr(d.teamAOvers)} OVERS</div>
            </div>
            <div class="gfx-summary-vs">VS</div>
            <div class="gfx-summary-team">
              <div class="gfx-summary-name">${safeStr(d.teamBName)}</div>
              <div class="gfx-summary-score">${d.teamBScore ?? 0} / ${d.teamBWickets ?? 0}</div>
              <div class="gfx-summary-overs">${safeStr(d.teamBOvers)} OVERS</div>
            </div>
          </div>
          ${d.result ? `<div class="gfx-summary-result">${d.result}</div>` : ""}
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
      const root = el("div", `gfx gfx-message gfx-${this.id}`);
      const text = safeStr(this.data.text, this.id.toUpperCase());
      root.innerHTML = `
        <div class="gfx-message-inner">
          <div class="gfx-message-text">${text}</div>
          ${this.data.sub ? `<div class="gfx-message-sub">${this.data.sub}</div>` : ""}
        </div>
      `;
      return root;
    }
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
        <div class="sb" id="scoreboard">
          <div class="sb-accent"></div>
          <div class="sb-body">
            <div class="sb-brand">
              <div class="sb-logo-wrap">
                <img class="sb-logo" id="sb-logo" alt="" style="display:none">
                <div class="sb-logo-fallback" id="sb-logo-fb"></div>
              </div>
              <div class="sb-team-block">
                <div class="sb-team-name" id="sb-team-name">TEAM</div>
                <div class="sb-match-meta" id="sb-match-meta"></div>
              </div>
            </div>
            <div class="sb-score-block">
              <div class="sb-score-main">
                <span class="sb-score" id="sb-score">0</span>
                <span class="sb-wickets">/<span id="sb-wickets">0</span></span>
              </div>
              <div class="sb-overs" id="sb-overs">0.0 OV</div>
            </div>
            <div class="sb-players">
              <div class="sb-batter" id="sb-bat1"><span class="sb-strike"></span><span class="sb-bat-name"></span><span class="sb-bat-fig"></span></div>
              <div class="sb-batter" id="sb-bat2"><span class="sb-strike"></span><span class="sb-bat-name"></span><span class="sb-bat-fig"></span></div>
              <div class="sb-bowler" id="sb-bowler"><span class="sb-bowl-label">BOWLER</span><span class="sb-bowl-name"></span><span class="sb-bowl-fig"></span></div>
            </div>
            <div class="sb-rates">
              <div class="sb-rate" id="sb-crr">CRR —</div>
              <div class="sb-rate sb-rate-rrr" id="sb-rrr" style="display:none">RRR —</div>
              <div class="sb-target" id="sb-target" style="display:none"></div>
            </div>
            <div class="sb-this-over">
              <div class="sb-this-over-label">THIS OVER</div>
              <div class="sb-balls" id="sb-balls"></div>
            </div>
          </div>
          <div class="sb-pp" id="sb-pp" style="display:none">POWERPLAY</div>
        </div>
      `;
      this.els = {
        logo: this.root.querySelector("#sb-logo"),
        logoFb: this.root.querySelector("#sb-logo-fb"),
        teamName: this.root.querySelector("#sb-team-name"),
        matchMeta: this.root.querySelector("#sb-match-meta"),
        score: this.root.querySelector("#sb-score"),
        wickets: this.root.querySelector("#sb-wickets"),
        overs: this.root.querySelector("#sb-overs"),
        bat1: this.root.querySelector("#sb-bat1"),
        bat2: this.root.querySelector("#sb-bat2"),
        bowler: this.root.querySelector("#sb-bowler"),
        crr: this.root.querySelector("#sb-crr"),
        rrr: this.root.querySelector("#sb-rrr"),
        target: this.root.querySelector("#sb-target"),
        balls: this.root.querySelector("#sb-balls"),
        pp: this.root.querySelector("#sb-pp"),
        accent: this.root.querySelector(".sb-accent"),
        sb: this.root.querySelector("#scoreboard"),
      };
    }

    setBatterRow(rowEl, parsed) {
      const strike = rowEl.querySelector(".sb-strike");
      const name = rowEl.querySelector(".sb-bat-name");
      const fig = rowEl.querySelector(".sb-bat-fig");
      name.textContent = parsed.name || "—";
      if (parsed.runs) {
        fig.textContent = parsed.balls ? `${parsed.runs} (${parsed.balls})` : parsed.runs;
      } else {
        fig.textContent = "";
      }
      rowEl.classList.toggle("on-strike", parsed.onStrike);
      strike.textContent = parsed.onStrike ? "●" : "";
    }

    renderThisOver(balls) {
      const container = this.els.balls;
      container.innerHTML = "";
      const list = Array.isArray(balls) ? balls : [];
      list.forEach((b) => {
        const ball = el("span", `sb-ball sb-ball-${ballClass(b)}`);
        ball.textContent = ballDisplay(b);
        container.appendChild(ball);
      });
    }

    update(state) {
      const batting = state.battingTeam === "A" ? state.teamA : state.teamB;
      const bowling = state.battingTeam === "A" ? state.teamB : state.teamA;
      const colors = teamColors(batting, state.battingTeam === "A" ? "#e63946" : "#1d8cf8");

      this.els.accent.style.background = `linear-gradient(180deg, ${colors.primary}, transparent)`;
      this.els.sb.style.setProperty("--sb-accent", colors.primary);
      this.els.teamName.textContent = teamShortName(batting);
      this.els.matchMeta.textContent = `MATCH ${safeStr(state.matchNo, "1")} · ${safeStr(state.matchTitle, "LIVE")}`.toUpperCase();

      const logo = safeStr(batting.logo);
      if (logo) {
        this.els.logo.src = logo;
        this.els.logo.style.display = "";
        this.els.logoFb.style.display = "none";
        this.els.logo.onerror = () => {
          this.els.logo.style.display = "none";
          this.els.logoFb.style.display = "flex";
          this.els.logoFb.textContent = teamShortName(batting).slice(0, 2);
        };
      } else {
        this.els.logo.style.display = "none";
        this.els.logoFb.style.display = "flex";
        this.els.logoFb.textContent = teamShortName(batting).slice(0, 2);
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
        this.els.wickets.parentElement.classList.add("wicket-flash");
        setTimeout(() => this.els.wickets.parentElement.classList.remove("wicket-flash"), 400);
      } else {
        this.els.wickets.textContent = wickets;
      }
      this.prevScore = score;
      this.prevWickets = wickets;

      this.els.overs.textContent = `${safeStr(batting.overs, "0.0")} OV`;

      this.setBatterRow(this.els.bat1, parseBatsmanLine(batting.batsman1));
      this.setBatterRow(this.els.bat2, parseBatsmanLine(batting.batsman2));

      const bowl = parseBowlerLine(bowling.bowler);
      this.els.bowler.querySelector(".sb-bowl-name").textContent = bowl.name || "—";
      this.els.bowler.querySelector(".sb-bowl-fig").textContent = bowl.figures;

      this.els.crr.textContent = `CRR ${calcCRR(score, batting.overs)}`;

      if (state.target != null && state.battingTeam === "B") {
        const needed = state.target - score;
        const ballsRem = ballsRemaining(batting.overs, 20);
        const rrr = calcRRR(score, state.target, batting.overs, 20);
        this.els.rrr.style.display = rrr ? "" : "none";
        if (rrr) this.els.rrr.textContent = `RRR ${rrr}`;
        this.els.target.style.display = "";
        this.els.target.textContent = `TGT ${state.target} · NEED ${Math.max(0, needed)} (${ballsRem})`;
      } else {
        this.els.rrr.style.display = "none";
        this.els.target.style.display = "none";
      }

      this.renderThisOver(state.thisOver);
      this.els.pp.style.display = state.powerplay ? "" : "none";
    }

    setVisible(v) {
      this.els.sb.classList.toggle("sb-hidden", !v);
    }
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
      this.scoreboard = new MainScoreboard(this.scoreboardRoot);
      this.scaleCanvas();
      window.addEventListener("resize", () => this.scaleCanvas());
    }

    scaleCanvas() {
      const w = window.innerWidth;
      const h = window.innerHeight;
      const scale = Math.min(w / 1920, h / 1080);
      this.canvas.style.transform = `scale(${scale})`;
      this.canvas.style.transformOrigin = "top left";
    }

    applyTeamTheme(team) {
      const colors = teamColors(team);
      document.documentElement.style.setProperty("--broadcast-accent", colors.primary);
    }

    updateState(state) {
      this.state = state;
      const mode = state.displayMode || "scoreboard";
      const showSb = state.visible !== false && mode === "scoreboard";
      this.scoreboard.setVisible(showSb);
      if (showSb) {
        const batting = state.battingTeam === "A" ? state.teamA : state.teamB;
        this.applyTeamTheme(batting);
        this.scoreboard.update(state);
      }
    }

    onGraphicRemoved(id) {
      this.activeGraphics.delete(id);
    }

    hideScoreboardTemporarily() {
      this.scoreboard.setVisible(false);
    }

    showScoreboard() {
      if (this.state?.visible !== false) {
        this.scoreboard.setVisible(true);
      }
    }

    show(type, data = {}, options = {}) {
      const normalized = type.toLowerCase().replace(/-/g, "_");
      const duration = options.duration || this.defaultDuration(normalized);
      const layer = this.isFullscreen(normalized) ? this.fullscreenLayer : this.eventLayer;

      if (["four", "six", "wicket", "out"].includes(normalized)) {
        this.hideScoreboardTemporarily();
      }

      let graphic;
      switch (normalized) {
        case "four":
        case "six":
          graphic = new RunEventGraphic(this, normalized, data);
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
        case "team_lineup":
          graphic = new TeamLineupGraphic(this, data);
          break;
        case "match_summary":
          graphic = new MatchSummaryGraphic(this, data);
          break;
        case "milestone":
        case "custom":
        case "sponsor":
        case "message":
          graphic = new CustomMessageGraphic(this, normalized, data);
          break;
        default:
          graphic = new CustomMessageGraphic(this, normalized, { text: normalized.toUpperCase(), ...data });
      }

      const key = `${normalized}-${Date.now()}`;
      this.activeGraphics.set(key, graphic);
      graphic.mount(layer);
      graphic.scheduleRemove(duration);
      setTimeout(() => this.showScoreboard(), duration - 200);
      return graphic;
    }

    defaultDuration(type) {
      const map = {
        four: 2100,
        six: 2300,
        wicket: 2500,
        out: 2200,
        toss: 4500,
        need_to_win: 3500,
        innings_break: 4000,
        winner: 5000,
        partnership: 3200,
        player_card: 3000,
        team_lineup: 4500,
        match_summary: 5000,
        milestone: 2200,
        custom: 2200,
        sponsor: 3000,
        message: 2800,
      };
      return map[type] || 2500;
    }

    isFullscreen(type) {
      return ["toss", "need_to_win", "innings_break", "winner", "match_summary", "team_lineup"].includes(type);
    }

    buildPayloadFromState(type) {
      const s = this.state;
      if (!s) return {};
      const batting = s.battingTeam === "A" ? s.teamA : s.teamB;
      const colors = teamColors(batting);

      switch (type) {
        case "need_to_win": {
          const needed = (s.target ?? 0) - (batting.score ?? 0);
          return {
            runs: Math.max(0, needed),
            balls: ballsRemaining(batting.overs, 20),
            teamName: batting.name,
            primaryColor: colors.primary,
          };
        }
        case "innings_break":
          return {
            teamName: batting.name,
            score: batting.score,
            wickets: batting.wickets,
            overs: batting.overs,
            target: s.target,
          };
        case "match_summary":
          return {
            teamAName: s.teamA.name,
            teamBName: s.teamB.name,
            teamAScore: s.teamA.score,
            teamAWickets: s.teamA.wickets,
            teamAOvers: s.teamA.overs,
            teamBScore: s.teamB.score,
            teamBWickets: s.teamB.wickets,
            teamBOvers: s.teamB.overs,
            result: s.result,
          };
        case "winner": {
          const result = safeStr(s.result);
          let winnerName = batting.name;
          let logo = batting.logo;
          let primaryColor = colors.primary;
          if (result.toLowerCase().includes(s.teamA.name.toLowerCase())) {
            winnerName = s.teamA.name;
            logo = s.teamA.logo;
            primaryColor = teamColors(s.teamA).primary;
          } else if (result.toLowerCase().includes(s.teamB.name.toLowerCase())) {
            winnerName = s.teamB.name;
            logo = s.teamB.logo;
            primaryColor = teamColors(s.teamB).primary;
          }
          return { teamName: winnerName, result, logo, primaryColor, tournament: s.matchTitle };
        }
        default:
          return {};
      }
    }

    playAnimation(animation, payload) {
      const type = animation.toLowerCase();
      const merged = { ...this.buildPayloadFromState(type), ...(payload || {}) };

      if (type === "four" || type === "six") {
        const batting = this.state?.battingTeam === "A" ? this.state.teamA : this.state?.teamB;
        merged.teamColors = teamColors(batting || {});
        if (!merged.player) {
          const b1 = parseBatsmanLine(batting?.batsman1);
          merged.player = b1.onStrike ? b1.name : parseBatsmanLine(batting?.batsman2).name;
        }
      }

      if ((type === "wicket" || type === "out") && !merged.player) {
        const batting = this.state?.battingTeam === "A" ? this.state.teamA : this.state?.teamB;
        const b1 = parseBatsmanLine(batting?.batsman1);
        merged.player = b1.onStrike ? b1.name : parseBatsmanLine(batting?.batsman2).name;
        merged.dismissal = merged.dismissal || "OUT";
      }

      if (type === "milestone" || type === "custom") {
        merged.text = payload?.text || (type === "milestone" ? "MILESTONE" : "WOW!");
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
