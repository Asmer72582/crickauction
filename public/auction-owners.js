(() => {
  "use strict";

  let state = window.AUCTION_INITIAL_STATE || {};
  const auctionId = state.auctionId;
  const storageKey = `ao-owner-team:${auctionId || 0}`;
  const DEFAULT_TEAM = "/assets/graphics/default_team.png";
  const DEFAULT_AVATAR = "/assets/graphics/defaultplayeravatar.png";

  const el = (id) => document.getElementById(id);
  let teamId = Number(localStorage.getItem(storageKey) || 0) || null;
  let tab = "overview";

  function fmt(n) {
    return "₹" + Number(n || 0).toLocaleString("en-IN");
  }

  function pickTeam(s) {
    const teams = s.teams || [];
    if (!teams.length) return null;
    return teams.find((t) => Number(t.id) === Number(teamId)) || null;
  }

  function setTab(next) {
    tab = next;
    document.querySelectorAll(".own-tab").forEach((btn) => {
      btn.classList.toggle("is-on", btn.dataset.tab === tab);
    });
    document.querySelectorAll(".own-panel").forEach((panel) => {
      const on = panel.dataset.panel === tab;
      panel.hidden = !on;
      panel.classList.toggle("is-on", on);
    });
  }

  function showPicker(on) {
    el("own-picker").hidden = !on;
    el("own-desk").hidden = on;
    el("own-switch").hidden = on;
    el("own-title").textContent = on ? "Select your team" : (pickTeam(state)?.name || "Your team");
  }

  function renderPicker(s) {
    const grid = el("own-picker-grid");
    if (!grid) return;
    const teams = s.teams || [];
    if (!teams.length) {
      grid.innerHTML = `<div class="own-empty">No teams in this auction yet.</div>`;
      return;
    }
    grid.innerHTML = teams
      .map((t) => `
        <button type="button" class="own-pick" data-pick-team="${t.id}">
          <img src="${t.logo || DEFAULT_TEAM}" alt="" />
          <div>
            <strong>${t.name || "—"}</strong>
            <span>${t.owner || "Owner not set"} · ${t.city || "Location not set"}</span>
          </div>
          <span>${t.squadCount || 0}/${t.maxSquad || 0}</span>
        </button>
      `)
      .join("");
  }

  function renderHero(t, s) {
    const box = el("own-hero");
    if (!box || !t) return;
    box.innerHTML = `
      <img src="${t.logo || DEFAULT_TEAM}" alt="" />
      <div class="own-hero-copy">
        <strong>${t.name || "—"}</strong>
        <span>${t.owner || "Owner not set"} · ${t.city || "Location not set"}</span>
        <span>${s.auctionName || ""} · ${String(s.status || "").toUpperCase()}</span>
      </div>
    `;
  }

  function renderOverview(t, s) {
    const box = el("own-overview");
    if (!box || !t) return;
    const max = Number(t.maxSquad || s.rules?.max_squad || 0);
    const count = Number(t.squadCount || (t.squad || []).length);
    const roles = t.roleCounts || {};
    const cur = s.currentPlayer;
    box.innerHTML = `
      <div class="own-stats">
        <div class="own-stat"><em>Players</em><b>${count}/${max || "—"}</b></div>
        <div class="own-stat"><em>Balance left</em><b>${fmt(t.remaining)}</b></div>
        <div class="own-stat"><em>Spent</em><b>${fmt(t.spent)}</b></div>
        <div class="own-stat"><em>Slots left</em><b>${Math.max(0, max - count)}</b></div>
      </div>
      <div class="own-roles">
        <div class="own-role"><span>Batters</span><b>${roles.batter || 0}</b></div>
        <div class="own-role"><span>Bowlers</span><b>${roles.bowler || 0}</b></div>
        <div class="own-role"><span>All-rounders</span><b>${roles.allrounder || 0}</b></div>
        <div class="own-role"><span>Keepers</span><b>${roles.wicketkeeper || 0}</b></div>
      </div>
      <div class="own-live">
        <p>On the block</p>
        <strong>${cur ? (cur.playerName || "—") : "Waiting for next player"}</strong>
        <div class="own-muted">${cur ? `${fmt(s.currentBid || 0)} · ${(s.highestBidder || "No bids")}` : "The next player will appear here."}</div>
      </div>
    `;
  }

  function renderSquad(t) {
    const box = el("own-squad");
    if (!box || !t) return;
    const squad = t.squad || [];
    if (!squad.length) {
      box.innerHTML = `<div class="own-empty">No players bought yet.</div>`;
      return;
    }
    box.innerHTML = `<div class="own-squad">${squad
      .map((p) => `
        <article class="own-player">
          <img src="${p.playerPhoto || DEFAULT_AVATAR}" alt="" />
          <div>
            <strong>${p.playerName || "—"}</strong>
            <span>${(p.primaryRole || p.role || "Player").toUpperCase()}</span>
          </div>
          <div class="own-price">${fmt(p.soldPrice || 0)}</div>
        </article>
      `)
      .join("")}</div>`;
  }

  function renderDetails(t, s) {
    const box = el("own-details");
    if (!box || !t) return;
    const max = Number(t.maxSquad || s.rules?.max_squad || 0);
    const count = Number(t.squadCount || (t.squad || []).length);
    box.innerHTML = `
      <div class="own-details">
        <div class="own-row"><span>Starting purse</span><b>${fmt(t.startingPurse)}</b></div>
        <div class="own-row"><span>Amount spent</span><b>${fmt(t.spent)}</b></div>
        <div class="own-row"><span>Balance left</span><b>${fmt(t.remaining)}</b></div>
        <div class="own-row"><span>Players bought</span><b>${count}</b></div>
        <div class="own-row"><span>Players per team</span><b>${max || "—"}</b></div>
        <div class="own-row"><span>Min squad</span><b>${t.minSquad || "—"}</b></div>
        <div class="own-row"><span>Owner</span><b>${t.owner || "—"}</b></div>
        <div class="own-row"><span>Location</span><b>${t.city || "—"}</b></div>
      </div>
    `;
  }

  function render(s) {
    state = s || state;
    const league = el("own-league");
    if (league) league.textContent = s.auctionName || "Owner portal";
    renderPicker(s);
    const t = pickTeam(s);
    if (!t) {
      showPicker(true);
      return;
    }
    showPicker(false);
    renderHero(t, s);
    renderOverview(t, s);
    renderSquad(t);
    renderDetails(t, s);
  }

  el("own-picker-grid")?.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-pick-team]");
    if (!btn) return;
    teamId = Number(btn.dataset.pickTeam);
    localStorage.setItem(storageKey, String(teamId));
    render(state);
  });

  el("own-switch")?.addEventListener("click", () => {
    showPicker(true);
  });

  document.querySelectorAll(".own-tab").forEach((btn) => {
    btn.addEventListener("click", () => setTab(btn.dataset.tab));
  });

  const realtime = new CricketRealtime({
    room: window.AUCTION_ROOM,
    hydrateUrl: auctionId ? `/api/auctions/${auctionId}/state` : null,
    pollWhenLiveMs: 800,
    pollWhenOfflineMs: 400,
    onState: (s) => render(s),
  });
  realtime.start();
  render(state);
})();
