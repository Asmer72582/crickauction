/**
 * Broadcast Overlay Engine — modular panels + theme skinning.
 * Consumes normalized /api/overlay/match/{id} payload only.
 */
(function (global) {
  "use strict";

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function el(tag, cls, html) {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (html != null) n.innerHTML = html;
    return n;
  }

  function safe(v, fb) {
    if (v == null || v === "undefined" || Number.isNaN(v)) return fb ?? "";
    return v;
  }

  function animateNumber(node, from, to, ms) {
    if (!node) return;
    from = Number(from) || 0;
    to = Number(to) || 0;
    if (from === to) {
      node.textContent = to;
      return;
    }
    const start = performance.now();
    const diff = to - from;
    function frame(now) {
      const t = Math.min(1, (now - start) / (ms || 380));
      const e = 1 - Math.pow(1 - t, 3);
      node.textContent = Math.round(from + diff * e);
      if (t < 1) requestAnimationFrame(frame);
      else {
        node.textContent = to;
        node.classList.add("bo-flash");
        setTimeout(() => node.classList.remove("bo-flash"), 280);
      }
    }
    requestAnimationFrame(frame);
  }

  /* ── Panels ── */

  const Panels = {
    score(data) {
      const t = data.battingTeam || {};
      const chase = data.chase || {};
      return `
        <div class="bo-panel bo-score">
          <div class="bo-score-row">
            ${t.logo ? `<img class="bo-logo" src="${t.logo}" alt="" onerror="this.style.display='none'">` : `<div class="bo-logo-fb">${(t.shortName || "TM").slice(0, 2)}</div>`}
            <div class="bo-team">
              <div class="bo-team-name">${safe(t.shortName || t.name, "TEAM")}</div>
              <div class="bo-team-full">${safe(t.name)}</div>
            </div>
            <div class="bo-score-main">
              <span class="bo-runs" data-anim-score>${safe(t.runs, 0)}</span>
              <span class="bo-wkts">/<span data-anim-wkts>${safe(t.wickets, 0)}</span></span>
              <div class="bo-overs">${safe(t.overs, "0.0")} OV</div>
            </div>
            <div class="bo-rates">
              <div>CRR ${safe(t.crr, "0.00")}</div>
              ${chase.rrr != null ? `<div class="bo-rrr">RRR ${chase.rrr}</div>` : ""}
              ${chase.target != null ? `<div class="bo-tgt">TGT ${chase.target}</div>` : ""}
            </div>
          </div>
          ${chase.need != null ? `<div class="bo-chase">NEED ${chase.need} FROM ${chase.ballsLeft ?? "—"} BALLS</div>` : ""}
        </div>`;
    },

    batsmen(data) {
      const list = data.batsmen || [];
      const rows = list.map((b) => `
        <div class="bo-bat-row ${b.striker ? "striker" : ""}">
          <span class="bo-strike">${b.striker ? "▶" : ""}</span>
          <span class="bo-bat-name">${safe(b.name, "—")}</span>
          <span class="bo-bat-fig">${safe(b.runs, 0)} (${safe(b.balls, 0)})</span>
          <span class="bo-bat-bd">${safe(b.fours, 0)}×4 · ${safe(b.sixes, 0)}×6</span>
        </div>`).join("") || `<div class="bo-empty">No batsmen set</div>`;
      return `<div class="bo-panel bo-batsmen"><div class="bo-label">BATSMEN</div>${rows}</div>`;
    },

    bowler(data) {
      const b = data.bowler;
      if (!b) return `<div class="bo-panel bo-bowler"><div class="bo-label">BOWLER</div><div class="bo-empty">—</div></div>`;
      return `
        <div class="bo-panel bo-bowler">
          <div class="bo-label">BOWLER</div>
          <div class="bo-bowl-name">${safe(b.name)}</div>
          <div class="bo-bowl-fig">${safe(b.figures, `${b.overs} - ${b.maidens} - ${b.runs} - ${b.wickets}`)}</div>
        </div>`;
    },

    last_ball(data) {
      const lb = data.lastBall;
      const label = lb?.label || "—";
      const type = (lb?.type || "").toLowerCase().replace(/\s+/g, "-");
      return `
        <div class="bo-panel bo-last-ball bo-lb-${type}">
          <div class="bo-label">LAST BALL</div>
          <div class="bo-lb-value" data-last-ball>${label}</div>
        </div>`;
    },

    last_over(data) {
      const balls = data.thisOver || [];
      const chips = balls.length
        ? balls.map((s) => {
            const c = String(s).toUpperCase();
            let cls = "run";
            if (c === "4") cls = "four";
            else if (c === "6") cls = "six";
            else if (c === "W") cls = "wicket";
            else if (c === "WD" || c === "NB") cls = "extra";
            else if (c === "•" || c === "0" || c === ".") cls = "dot";
            return `<span class="bo-chip bo-chip-${cls}">${c === "." ? "•" : c}</span>`;
          }).join("")
        : `<span class="bo-chip bo-chip-empty">—</span>`;
      return `<div class="bo-panel bo-last-over"><div class="bo-label">THIS OVER</div><div class="bo-chips">${chips}</div></div>`;
    },

    partnership(data) {
      const p = data.partnership || { runs: 0, balls: 0 };
      return `
        <div class="bo-panel bo-partnership">
          <div class="bo-label">PARTNERSHIP</div>
          <div class="bo-big">${safe(p.runs, 0)} <span>RUNS</span></div>
          <div class="bo-sub">${safe(p.balls, 0)} BALLS</div>
        </div>`;
    },

    fow(data) {
      const list = data.fallOfWickets || [];
      const rows = list.length
        ? list.map((f) => `<div class="bo-fow-row">${safe(f.label, (f.score != null ? f.score + "/" + f.wicket : "Wicket " + f.wicket))}</div>`).join("")
        : `<div class="bo-empty">No wickets yet</div>`;
      return `<div class="bo-panel bo-fow"><div class="bo-label">FALL OF WICKETS</div>${rows}</div>`;
    },

    match_info(data) {
      const m = data.match || {};
      const a = data.battingTeam || {};
      const b = data.bowlingTeam || {};
      return `
        <div class="bo-panel bo-match-info">
          <div class="bo-label">${safe(m.title, "MATCH").toUpperCase()}</div>
          <div class="bo-vs">${safe(a.name)} <span>VS</span> ${safe(b.name)}</div>
          ${m.result ? `<div class="bo-result">${safe(m.result)}</div>` : `<div class="bo-sub">MATCH ${safe(m.matchNo)} · INNINGS ${safe(m.innings)}</div>`}
        </div>`;
    },

    player_card(data) {
      const bat = (data.batsmen || []).find((b) => b.striker) || (data.batsmen || [])[0];
      if (!bat) {
        return `<div class="bo-panel bo-player-card"><div class="bo-empty">No player</div></div>`;
      }
      return `
        <div class="bo-panel bo-player-card">
          <div class="bo-pc-avatar">${(bat.name || "?").slice(0, 1).toUpperCase()}</div>
          <div class="bo-pc-name">${safe(bat.name)}</div>
          <div class="bo-pc-score">${safe(bat.runs, 0)} <span>(${safe(bat.balls, 0)})</span></div>
          <div class="bo-pc-bd"><span>${safe(bat.fours, 0)} FOUR</span><span>${safe(bat.sixes, 0)} SIX</span></div>
        </div>`;
    },

    full(data) {
      return `
        <div class="bo-full">
          ${Panels.score(data)}
          <div class="bo-full-row">
            ${Panels.batsmen(data)}
            ${Panels.bowler(data)}
            ${Panels.last_over(data)}
          </div>
          ${data.chase?.need != null ? "" : ""}
        </div>`;
    },
  };

  class BroadcastOverlayEngine {
    constructor(opts) {
      this.root = opts.root;
      this.canvas = opts.canvas;
      this.matchId = opts.matchId;
      this.token = opts.token;
      this.themeId = opts.themeId || "modern";
      this.panel = opts.panel || "full";
      this.version = 0;
      this.lastEventId = null;
      this.prevRuns = null;
      this.prevWkts = null;
      this.prevLastBall = null;
      this.scale();
      window.addEventListener("resize", () => this.scale());
    }

    scale() {
      const w = window.innerWidth;
      const h = window.innerHeight;
      const s = Math.min(w / 1920, h / 1080);
      this.canvas.style.transform = `scale(${s})`;
      this.canvas.style.transformOrigin = "top left";
    }

    applyTheme(theme) {
      const c = theme?.colors || {};
      const root = document.documentElement;
      root.style.setProperty("--bo-primary", c.primary || "#0ea5e9");
      root.style.setProperty("--bo-accent", c.accent || "#38bdf8");
      root.style.setProperty("--bo-text", c.text || "#f8fafc");
      root.style.setProperty("--bo-panel", c.panel || "rgba(15,23,42,0.92)");
      root.style.setProperty("--bo-danger", c.danger || "#ef4444");
      this.canvas.dataset.theme = theme?.id || this.themeId;
      const pos = theme?.scorePosition || { x: 80, y: 820 };
      this.root.style.setProperty("--bo-x", pos.x + "px");
      this.root.style.setProperty("--bo-y", pos.y + "px");
    }

    render(data) {
      const panel = data.broadcast?.panel || this.panel;
      const theme = data.broadcast?.theme;
      if (theme) this.applyTheme(theme);

      const visible = data.match?.visible !== false;
      this.root.classList.toggle("bo-hidden", !visible);

      const branding = data.branding || {};
      const renderer = Panels[panel] || Panels.full;
      this.root.innerHTML = `
        <div class="bo-stack">
          ${branding.sponsorText || branding.poweredBy ? `<div class="bo-brand">${safe(branding.sponsorText || branding.poweredBy)}</div>` : ""}
          ${renderer(data)}
        </div>`;

      // Number animations
      const runsEl = $("[data-anim-score]", this.root);
      const wktsEl = $("[data-anim-wkts]", this.root);
      const runs = data.battingTeam?.runs ?? 0;
      const wkts = data.battingTeam?.wickets ?? 0;
      if (this.prevRuns != null && runsEl) animateNumber(runsEl, this.prevRuns, runs);
      else if (runsEl) runsEl.textContent = runs;
      if (this.prevWkts != null && wktsEl) animateNumber(wktsEl, this.prevWkts, wkts, 300);
      else if (wktsEl) wktsEl.textContent = wkts;
      this.prevRuns = runs;
      this.prevWkts = wkts;

      // Last ball emphasis
      const lb = data.lastBall?.label;
      if (lb && lb !== this.prevLastBall) {
        const node = $("[data-last-ball]", this.root);
        if (node) {
          node.classList.add("bo-lb-pop");
          setTimeout(() => node.classList.remove("bo-lb-pop"), 700);
        }
        this.prevLastBall = lb;
      }
    }

    playEvent(ev) {
      if (!ev?.animation) return;
      const layer = el("div", `bo-event bo-event-${ev.animation}`);
      const label = (ev.payload?.text || ev.animation || "").toUpperCase();
      layer.innerHTML = `<div class="bo-event-text">${label}</div>`;
      this.canvas.appendChild(layer);
      requestAnimationFrame(() => layer.classList.add("show"));
      setTimeout(() => {
        layer.classList.remove("show");
        layer.classList.add("hide");
        setTimeout(() => layer.remove(), 400);
      }, 1800);
    }

    async tick() {
      try {
        const params = new URLSearchParams({
          token: this.token,
          theme: this.themeId,
          panel: this.panel,
        });
        if (this.lastEventId) params.set("last_event", this.lastEventId);
        const res = await fetch(`/api/overlay/match/${this.matchId}/poll?${params}`, {
          headers: { Accept: "application/json", "X-Overlay-Token": this.token },
        });
        if (!res.ok) return;
        const json = await res.json();
        if (!this.lastEventId && json.latest_event_id) {
          this.lastEventId = json.latest_event_id;
        }
        if (json.version !== this.version || !this.version) {
          this.version = json.version;
          this.render(json.data);
        }
        (json.events || []).forEach((ev) => {
          if (!ev?.id) return;
          this.lastEventId = ev.id;
          this.playEvent(ev);
        });
      } catch (_) {
        // stay quiet for OBS
      }
    }

    start() {
      this.tick();
      this.timer = setInterval(() => this.tick(), 400);
    }
  }

  global.BroadcastOverlayEngine = BroadcastOverlayEngine;
  global.BroadcastPanels = Panels;
})(window);
