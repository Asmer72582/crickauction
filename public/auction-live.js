const root = document.getElementById("auction-live");
if (!root) throw new Error("live root missing");

const auctionId = root.dataset.auctionId;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const defaultInc = Number(root.dataset.increment || 10000);
let state = window.AUCTION_INITIAL_STATE || {};
let selectedTeamId = null;
let lastBidAmount = null;

const api = (path, method = "POST", body) =>
  fetch(`/api/auctions/${auctionId}${path}`, {
    method,
    headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": csrf, Accept: "application/json" },
    body: body ? JSON.stringify(body) : undefined,
  }).then(async (r) => {
    const data = await r.json().catch(() => ({}));
    if (!r.ok) {
      const err = data.errors || {};
      const first = Object.values(err).flat()[0];
      throw new Error(first || data.message || "Request failed");
    }
    if (data.state) renderState(data.state);
    return data;
  });

const DEFAULT_AVATAR = "/assets/graphics/defaultplayeravatar.png";
const DEFAULT_TEAM = "/assets/graphics/default_team.png";

function fmt(n) {
  return "₹" + Number(n || 0).toLocaleString("en-IN");
}

function fmtShort(n) {
  const v = Number(n || 0);
  if (v >= 100000) {
    const l = v / 100000;
    const text = Number.isInteger(l) ? String(l) : l.toFixed(1).replace(/\.0$/, "");
    return `₹${text}L`;
  }
  if (v >= 1000) return `₹${Math.round(v / 1000)}K`;
  return fmt(v);
}

function fmtInc(n) {
  const v = Number(n || 0);
  if (v >= 100000) {
    const l = v / 100000;
    const text = Number.isInteger(l) ? String(l) : l.toFixed(1).replace(/\.0$/, "");
    return `+₹${text}L`;
  }
  if (v >= 1000) return `+₹${Math.round(v / 1000)}K`;
  return `+${fmt(v)}`;
}

function minBid(s) {
  const cur = s.currentPlayer;
  if (!cur) return 0;
  const current = Number(s.currentBid || 0);
  const inc = Number(s.bidIncrement || defaultInc);
  const base = Number(cur.basePrice || 0);
  return current > 0 ? current + inc : base;
}

function amountInput() {
  return document.getElementById("bid-amount");
}

function setAmount(n) {
  const el = amountInput();
  if (el) el.value = String(Math.max(0, Math.round(n)));
}

function getAmount() {
  return Number(amountInput()?.value || 0);
}

function canOperateBids(s = state) {
  return Boolean(s.currentPlayer) && s.status === "live";
}

function showToast(message, type = "ok") {
  const el = document.getElementById("alc-toast");
  if (!el) return;
  el.hidden = false;
  el.className = `alc-toast is-${type}`;
  el.textContent = message;
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => {
    el.hidden = true;
  }, 2200);
}

function syncSelectedLabel() {
  const label = document.getElementById("selected-team-label");
  const team = (state.teams || []).find((t) => Number(t.id) === Number(selectedTeamId));
  if (label) {
    if (team) {
      label.textContent = `✓ ${team.name} · ${fmt(team.remaining)} remaining`;
      label.classList.remove("is-warn");
    } else {
      label.textContent = "Select a team to bid";
      label.classList.add("is-warn");
    }
  }
  document.querySelectorAll(".alc-team-card, .alc-team-row").forEach((btn) => {
    const selected = Number(btn.dataset.teamId) === Number(selectedTeamId);
    btn.classList.toggle("is-selected", selected);
    btn.setAttribute("aria-pressed", selected ? "true" : "false");
  });
}

function fillQuickEdit(cur) {
  const form = document.getElementById("quick-edit-form");
  const hint = document.getElementById("edit-empty-hint");
  if (!cur) {
    if (form) form.hidden = true;
    if (hint) hint.hidden = false;
    return;
  }
  if (hint) hint.hidden = true;
  if (form) form.hidden = false;
  const stats = cur.statistics || {};
  document.getElementById("edit-player-id").value = cur.auctionPlayerId || "";
  document.getElementById("edit-full_name").value = cur.playerName || "";
  document.getElementById("edit-playing_role").value = cur.role || "";
  document.getElementById("edit-batting_style").value = cur.battingStyle || "";
  document.getElementById("edit-bowling_style").value = cur.bowlingStyle || "";
  document.getElementById("edit-base_price").value = cur.basePrice ?? "";
  document.getElementById("edit-matches").value = stats.matches ?? "";
  document.getElementById("edit-runs").value = stats.runs ?? "";
  document.getElementById("edit-highest_score").value = stats.highestScore ?? "";
  document.getElementById("edit-wickets").value = stats.wickets ?? "";
}

function renderIncrements(s) {
  const wrap = document.getElementById("bid-increments");
  if (!wrap) return;
  const rules = s.rules || {};
  const list = rules.custom_increments?.length
    ? rules.custom_increments
    : [defaultInc, defaultInc * 2, defaultInc * 5, 100000, 200000, 500000];
  wrap.innerHTML = list
    .map((inc) => `<button type="button" class="alc-inc" data-inc="${Number(inc)}">${fmtInc(inc)}</button>`)
    .join("");
}

function teamInitials(t) {
  const src = String(t.shortName || t.name || "TM").trim();
  return src
    .split(/\s+/)
    .map((w) => w[0])
    .join("")
    .slice(0, 3)
    .toUpperCase();
}

function teamLogoHtml(t, cls = "alc-franchise-logo") {
  if (t.logo) {
    return `<div class="${cls}"><img src="${t.logo}" alt="" /></div>`;
  }
  return `<div class="${cls} is-fallback">${teamInitials(t)}</div>`;
}

function renderAllTeams(s) {
  const grid = document.getElementById("all-teams-grid");
  if (!grid) return;
  const teams = s.teams || [];
  if (!teams.length) {
    grid.innerHTML = `<p class="alc-muted">No teams on this auction yet.</p>`;
    return;
  }
  grid.innerHTML = teams
    .map((t) => {
      const squad = t.squad || [];
      const chips = squad
        .slice(0, 8)
        .map((p) => {
          const src = p.playerPhoto || DEFAULT_AVATAR;
          return `<li title="${p.playerName || ""}">
            <img src="${src}" alt="" />
            <span>${p.playerName || "—"}</span>
          </li>`;
        })
        .join("");
      const more = squad.length > 8 ? `<li class="more">+${squad.length - 8}</li>` : "";
      return `
      <article class="alc-franchise">
        <header class="alc-franchise-head">
          ${teamLogoHtml(t)}
          <div class="alc-franchise-copy">
            <h3>${t.name}</h3>
            <p>${t.shortName || "—"} · ${t.squadCount || 0}/${t.maxSquad || 25} players</p>
          </div>
        </header>
        <div class="alc-franchise-stats">
          <div><em>Purse left</em><strong>${fmt(t.remaining)}</strong></div>
          <div><em>Spent</em><strong>${fmt(t.spent)}</strong></div>
        </div>
        <ul class="alc-franchise-squad">${chips || `<li class="empty">No players yet</li>`}${more}</ul>
        <button type="button" class="alc-btn push" data-broadcast="team" data-team-id="${t.id}">Show this team on air</button>
      </article>`;
    })
    .join("");
}

function renderActivity(s) {
  const el = document.getElementById("live-activity");
  if (!el) return;
  const history = [...(s.bidHistory || [])].reverse();
  if (!history.length) {
    el.innerHTML = `<p class="alc-muted-sm">No bids yet</p>`;
    return;
  }
  el.innerHTML = history
    .slice(0, 12)
    .map((h) => {
      let time = "—";
      try {
        time = new Date(h.at).toLocaleTimeString("en-GB", {
          hour: "2-digit",
          minute: "2-digit",
          second: "2-digit",
          hour12: false,
        });
      } catch (_) {}
      return `<div class="alc-activity-row">
        <span class="t">${time}</span>
        <span class="n">${h.teamName || "—"}</span>
        <span class="a">${fmt(h.amount)}</span>
      </div>`;
    })
    .join("");
}

let queueDragging = false;

function queueRoleKey(p) {
  const r = String(p.primaryRole || p.role || "").toLowerCase();
  if (r.includes("wicket") || r.includes("keeper")) return "wicketkeeper";
  if (r.includes("all")) return "allrounder";
  if (r.includes("bowl")) return "bowler";
  return "batter";
}

function queueStatusLabel(status) {
  if (status === "current") return "On block";
  if (status === "pool") return "Up next";
  if (status === "sold") return "Sold";
  if (status === "unsold") return "Unsold";
  return status || "—";
}

function matchesQueueFilter(p) {
  const q = (document.getElementById("player-queue-search")?.value || "").trim().toLowerCase();
  const status = document.getElementById("player-queue-status")?.value || "upcoming";
  const role = document.getElementById("player-queue-role")?.value || "";
  if (status === "upcoming" && p.status !== "pool" && p.status !== "current") return false;
  if (status !== "upcoming" && status !== "all" && p.status !== status) return false;
  if (role && queueRoleKey(p) !== role) return false;
  if (q) {
    const hay = `${p.playerName || ""} ${p.role || ""} ${p.soldTeamName || ""}`.toLowerCase();
    if (!hay.includes(q)) return false;
  }
  return true;
}

function renderQueue(s) {
  const list = document.getElementById("player-queue-list");
  const meta = document.getElementById("player-queue-meta");
  if (!list) return;
  if (queueDragging) return;

  const queue = [...(s.queue || [])].sort((a, b) => Number(a.sortOrder || 0) - Number(b.sortOrder || 0));
  const upcoming = queue.filter((p) => p.status === "pool");
  const upcomingIndex = new Map(upcoming.map((p, i) => [p.auctionPlayerId, i + 1]));
  const rows = queue.filter(matchesQueueFilter);

  if (meta) {
    const total = queue.length;
    const left = upcoming.length + (queue.some((p) => p.status === "current") ? 1 : 0);
    meta.textContent = `${rows.length} shown · ${left} still to come · ${total} in auction`;
  }

  if (!rows.length) {
    list.innerHTML = `<p class="alc-muted-sm">No players match these filters.</p>`;
    return;
  }

  list.innerHTML = rows
    .map((p) => {
      const canDrag = p.status === "pool";
      const seq =
        p.status === "current" ? "NOW" : p.status === "pool" ? String(upcomingIndex.get(p.auctionPlayerId) || "—").padStart(2, "0") : "—";
      const photo = p.playerPhoto || DEFAULT_AVATAR;
      const sub = p.status === "sold"
        ? `${p.soldTeamName || "Sold"} · ${fmt(p.soldPrice || 0)}`
        : `${p.primaryRole || p.role || "Player"} · ${fmt(p.basePrice || 0)}`;
      return `<article class="alc-queue-row ${canDrag ? "is-draggable" : ""} is-${p.status}" data-queue-id="${p.auctionPlayerId}" ${canDrag ? 'draggable="true"' : ""}>
        <span class="alc-queue-handle" aria-hidden="true">${canDrag ? "⋮⋮" : ""}</span>
        <span class="alc-queue-seq">${seq}</span>
        <img class="alc-queue-photo" src="${photo}" alt="" />
        <div class="alc-queue-copy">
          <strong>${p.playerName || "—"}</strong>
          <span>${sub}</span>
        </div>
        <span class="alc-queue-status">${queueStatusLabel(p.status)}</span>
      </article>`;
    })
    .join("");
}

function renderTeamButtons(s) {
  const grid = document.getElementById("live-team-buttons");
  if (!grid) return;
  const min = minBid(s);
  const leaderId = s.highestBidderId != null ? Number(s.highestBidderId) : null;
  const live = canOperateBids(s);

  grid.innerHTML = (s.teams || [])
    .map((t) => {
      const selected = Number(t.id) === Number(selectedTeamId);
      const remaining = Number(t.remaining || 0);
      const canAfford = remaining >= min;
      const disabled = !live || !canAfford || min <= 0;
      const leader = leaderId != null && Number(t.id) === leaderId;
      return `
      <button type="button"
        class="alc-team-card ${selected ? "is-selected" : ""} ${leader ? "is-leader" : ""} ${disabled ? "is-disabled" : ""}"
        data-team-id="${t.id}"
        data-select="1"
        aria-pressed="${selected ? "true" : "false"}"
        title="${!live ? "Auction not live" : !canAfford ? "Insufficient purse" : "Select team"}">
        <span class="name">${t.name}</span>
        <span class="purse">${fmtShort(remaining)}</span>
        <span class="qb" data-team-id="${t.id}" data-quick="1">${canAfford ? `Bid ${fmtShort(min || defaultInc)}` : "Can't afford"}</span>
      </button>`;
    })
    .join("");
}

function syncBiddingUi(s) {
  const consoleEl = document.querySelector(".alc-bid-console");
  const placeBtn = document.getElementById("btn-place-bid");
  const undoBtn = document.getElementById("btn-undo-bid");
  const live = canOperateBids(s);
  const hasBids = Number(s.currentBid || 0) > 0 || (s.bidHistory || []).length > 0;
  consoleEl?.classList.toggle("is-disabled", !live);
  root.classList.toggle("is-paused", s.status === "paused");
  root.classList.toggle("is-live-run", s.status === "live");

  if (placeBtn) {
    placeBtn.disabled = !live || !selectedTeamId;
    placeBtn.title = !live
      ? "Start / resume auction to bid"
      : !selectedTeamId
        ? "Select a team first"
        : "Place bid (Space)";
  }
  if (undoBtn) {
    const canUndo = (s.status === "live" || s.status === "paused") && hasBids && !!s.currentPlayer;
    undoBtn.disabled = !canUndo;
    undoBtn.title = !s.currentPlayer
      ? "No player on block"
      : !hasBids
        ? "No bids to undo"
        : "Undo last bid (Z)";
  }
}

function renderState(s) {
  state = s;
  const cur = s.currentPlayer;
  const box = document.getElementById("live-current-player");
  if (cur && box) {
    const src = cur.playerPhoto || DEFAULT_AVATAR;
    box.innerHTML = `
      <img src="${src}" class="alc-spotlight-photo" alt="" />
      <div>
        <div class="alc-kicker">Live Auction</div>
        <h2>${cur.playerName || ""}</h2>
        <div class="role">${cur.role || "PLAYER"}</div>
        <div class="meta">${cur.registrationCode || "—"} · Base ${fmt(cur.basePrice)}</div>
      </div>`;
  } else if (box) {
    box.innerHTML = `<p class="alc-muted">No player on block — press <b>N</b> or Next Player</p>`;
  }

  const bid = document.getElementById("live-current-bid");
  const hb = document.getElementById("live-highest-bidder");
  const minEl = document.getElementById("live-min-bid");
  const pool = document.getElementById("live-pool-count");
  const bidCard = document.getElementById("bid-now-card");
  const nextAmount = minBid(s);

  if (bid) {
    const nextText = fmt(s.currentBid);
    if (lastBidAmount !== null && Number(s.currentBid) !== Number(lastBidAmount)) {
      bidCard?.classList.remove("is-pop");
      // force reflow for animation
      void bidCard?.offsetWidth;
      bidCard?.classList.add("is-pop");
    }
    bid.textContent = nextText;
    lastBidAmount = Number(s.currentBid || 0);
  }
  if (hb) {
    hb.textContent = s.highestBidder || "No bids yet";
    hb.classList.toggle("is-empty", !s.highestBidder);
  }
  if (minEl) minEl.textContent = fmt(nextAmount);
  if (pool) pool.textContent = String(s.summary?.pool ?? 0);

  const pill = document.getElementById("live-status-pill");
  if (pill) {
    pill.textContent = String(s.status || "—").toUpperCase();
    pill.classList.remove("is-live", "is-paused", "is-completed");
    if (s.status === "live") pill.classList.add("is-live");
    if (s.status === "paused") pill.classList.add("is-paused");
    if (s.status === "completed") pill.classList.add("is-completed");
  }

  const teamsEl = document.getElementById("live-teams");
  if (teamsEl) {
    const leaderId = s.highestBidderId != null ? Number(s.highestBidderId) : null;
    teamsEl.innerHTML = (s.teams || [])
      .map((t) => {
        const leader = leaderId != null && Number(t.id) === leaderId;
        return `
      <div class="alc-purse-item ${leader ? "is-leader" : ""}">
        <div class="n">${t.name}</div>
        <div class="row"><span class="left">${fmtShort(t.remaining)} left</span><span>${t.squadCount || 0} pl</span></div>
        <button type="button" class="alc-purse-edit" data-edit-purse="${t.id}" data-team-name="${String(t.name || "").replace(/"/g, "")}" data-remaining="${t.remaining || 0}">Edit balance</button>
      </div>`;
      })
      .join("");
  }

  renderTeamButtons(s);
  renderAllTeams(s);
  renderActivity(s);
  renderQueue(s);
  fillQuickEdit(cur);
  renderIncrements(s);
  syncSelectedLabel();
  syncBroadcastChips(s);
  syncBiddingUi(s);

  const min = minBid(s);
  if (min > 0 && getAmount() < min) setAmount(min);

  if (s.rules?.bid_increment) {
    root.dataset.increment = String(s.rules.bid_increment);
  }
}

function syncBroadcastChips(s) {
  const view = s.broadcastView || "player";
  document.querySelectorAll("[data-broadcast]").forEach((el) => {
    if (el.tagName === "A") return;
    const on = el.dataset.broadcast === view;
    const teamMatch =
      view !== "team" || !el.dataset.teamId || Number(el.dataset.teamId) === Number(s.broadcastTeamId);
    el.classList.toggle("is-active", on && teamMatch);
  });
  syncLiveToggles(s);
}

function syncLiveToggles(s) {
  const status = s.status || "";
  document.getElementById("tog-start")?.classList.toggle(
    "is-active",
    status !== "live" && status !== "paused" && status !== "completed"
  );
  const live = document.getElementById("tog-live");
  live?.classList.toggle("is-active", status === "live");
  live?.classList.toggle("is-on", status === "live");
  document.getElementById("tog-pause")?.classList.toggle("is-active", status === "paused");
}

function fillTeamPicker() {
  const grid = document.getElementById("team-picker-grid");
  if (!grid) return;
  const teams = state.teams || [];
  if (!teams.length) {
    grid.innerHTML = `<p class="alc-muted">No teams to display.</p>`;
    return;
  }
  grid.innerHTML = teams
    .map(
      (t) => `
      <button type="button" class="alc-picker-team" data-pick-team="${t.id}">
        ${teamLogoHtml(t, "alc-picker-logo")}
        <strong>${t.name}</strong>
        <span>${t.shortName || "—"} · ${t.squadCount || 0}/${t.maxSquad || 25} · ${fmt(t.remaining)} left</span>
      </button>`
    )
    .join("");
}

function openTeamPicker() {
  fillTeamPicker();
  const modal = document.getElementById("team-picker-modal");
  if (!modal) return;
  modal.hidden = false;
}

function closeTeamPicker() {
  const modal = document.getElementById("team-picker-modal");
  if (modal) modal.hidden = true;
}

async function placeBid(teamId, amount) {
  await api("/bid", "POST", {
    team_id: Number(teamId),
    amount: Number(amount),
  });
  showToast(`Bid placed · ${fmt(amount)}`, "ok");
}

async function pushBroadcast(view, teamId = null) {
  await api("/broadcast", "POST", {
    view,
    team_id: teamId ? Number(teamId) : null,
  });
}

function openSoldModal() {
  const modal = document.getElementById("sold-modal");
  const body = document.getElementById("sold-modal-body");
  const cur = state.currentPlayer;
  if (!cur) {
    showToast("No player on block", "err");
    return;
  }
  if (!state.highestBidder && !Number(state.currentBid || 0)) {
    showToast("No winning bid to sell", "err");
    return;
  }
  if (body) {
    body.innerHTML = `
      <div class="line"><span>Player</span><b>${cur.playerName || "—"}</b></div>
      <div class="line"><span>Team</span><b>${state.highestBidder || "—"}</b></div>
      <div class="line"><span>Amount</span><b>${fmt(state.currentBid)}</b></div>`;
  }
  if (modal) modal.hidden = false;
}

function closeSoldModal() {
  const modal = document.getElementById("sold-modal");
  if (modal) modal.hidden = true;
}

async function runAction(action, btn) {
  if (btn) btn.disabled = true;
  try {
    if (action === "start") await api("/start");
    if (action === "pause") await api("/pause");
    if (action === "resume") await api("/resume");
    if (action === "next") await api("/next");
    if (action === "sold") await api("/sold");
    if (action === "unsold") await api("/unsold");
    if (action === "complete") {
      if (!confirm("Finish this auction?")) return;
      await api("/complete");
    }
    if (action === "bid") {
      if (!canOperateBids()) throw new Error("Auction must be live to bid");
      if (!selectedTeamId) throw new Error("Select a team first");
      const team = (state.teams || []).find((t) => Number(t.id) === Number(selectedTeamId));
      const amount = getAmount();
      if (team && Number(team.remaining) < amount) throw new Error("Team cannot afford this bid");
      await placeBid(selectedTeamId, amount);
      return;
    }
    if (action === "undo-bid") {
      const hist = state.bidHistory || [];
      if (!hist.length && !Number(state.currentBid || 0)) {
        throw new Error("No bids to undo");
      }
      await api("/undo-bid");
      showToast("Last bid undone", "ok");
      return;
    }
    if (action === "sold") showToast("Player SOLD", "ok");
    if (action === "unsold") showToast("Player marked unsold", "ok");
    if (action === "next") showToast("Next player loaded", "ok");
  } catch (e) {
    showToast(e.message || "Action failed", "err");
    alert(e.message || "Action failed");
  } finally {
    if (btn) btn.disabled = false;
  }
}

function isTypingTarget(el) {
  if (!el) return false;
  const tag = el.tagName;
  return tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT" || el.isContentEditable;
}

/* Panels */
root.querySelectorAll(".alc-tab[data-panel]").forEach((tab) => {
  tab.addEventListener("click", () => {
    const name = tab.dataset.panel;
    root.querySelectorAll(".alc-tab[data-panel]").forEach((t) => t.classList.toggle("is-active", t === tab));
    root.querySelectorAll("[data-panel-view]").forEach((p) => {
      p.classList.toggle("is-active", p.dataset.panelView === name);
    });
  });
});

document.getElementById("btn-open-edit")?.addEventListener("click", () => {
  root.querySelector('.alc-tab[data-panel="edit"]')?.click();
});
document.getElementById("open-players-tab")?.addEventListener("click", () => {
  root.querySelector('.alc-tab[data-panel="players"]')?.click();
});
document.getElementById("btn-open-players")?.addEventListener("click", () => {
  root.querySelector('.alc-tab[data-panel="players"]')?.click();
});

["player-queue-search", "player-queue-status", "player-queue-role"].forEach((id) => {
  document.getElementById(id)?.addEventListener("input", () => renderQueue(state));
  document.getElementById(id)?.addEventListener("change", () => renderQueue(state));
});

const queueList = document.getElementById("player-queue-list");
queueList?.addEventListener("dragstart", (e) => {
  const row = e.target.closest(".alc-queue-row.is-draggable");
  if (!row) return;
  queueDragging = true;
  row.classList.add("is-dragging");
  e.dataTransfer.effectAllowed = "move";
  e.dataTransfer.setData("text/plain", row.dataset.queueId);
});
queueList?.addEventListener("dragover", (e) => {
  const over = e.target.closest(".alc-queue-row.is-draggable");
  if (!over || !queueDragging) return;
  e.preventDefault();
  const dragging = queueList.querySelector(".alc-queue-row.is-dragging");
  if (!dragging || dragging === over) return;
  const rect = over.getBoundingClientRect();
  const before = e.clientY < rect.top + rect.height / 2;
  queueList.insertBefore(dragging, before ? over : over.nextSibling);
});
queueList?.addEventListener("drop", (e) => e.preventDefault());
queueList?.addEventListener("dragend", async (e) => {
  const row = e.target.closest(".alc-queue-row");
  row?.classList.remove("is-dragging");
  queueDragging = false;
  const visiblePoolIds = [...queueList.querySelectorAll(".alc-queue-row.is-draggable")]
    .map((el) => Number(el.dataset.queueId))
    .filter(Boolean);
  const allPool = [...(state.queue || [])]
    .filter((p) => p.status === "pool")
    .sort((a, b) => Number(a.sortOrder || 0) - Number(b.sortOrder || 0))
    .map((p) => Number(p.auctionPlayerId));
  const visibleSet = new Set(visiblePoolIds);
  const merged = [];
  let vi = 0;
  allPool.forEach((id) => {
    if (visibleSet.has(id)) merged.push(visiblePoolIds[vi++]);
    else merged.push(id);
  });
  const current = (state.queue || []).find((p) => p.status === "current");
  const order = current ? [Number(current.auctionPlayerId), ...merged] : merged;
  const previous = current ? [Number(current.auctionPlayerId), ...allPool] : allPool;
  if (order.join(",") === previous.join(",")) return;
  try {
    await api("/queue", "PATCH", { order });
    showToast("Sequence updated", "ok");
  } catch (err) {
    showToast(err.message || "Could not save order", "err");
    renderQueue(state);
  }
});

document.getElementById("btn-toggle-urls")?.addEventListener("click", () => {
  const drawer = document.getElementById("urls-drawer");
  const btn = document.getElementById("btn-toggle-urls");
  if (!drawer || !btn) return;
  const open = drawer.hasAttribute("hidden");
  if (open) drawer.removeAttribute("hidden");
  else drawer.setAttribute("hidden", "");
  btn.setAttribute("aria-expanded", open ? "true" : "false");
  btn.classList.toggle("is-active", open);
});

document.getElementById("btn-toggle-purses")?.addEventListener("click", () => {
  const aside = document.getElementById("alc-aside");
  const btn = document.getElementById("btn-toggle-purses");
  if (!aside || !btn) return;
  const collapsed = aside.classList.toggle("is-purses-collapsed");
  btn.setAttribute("aria-expanded", collapsed ? "false" : "true");
});

document.getElementById("tog-live")?.addEventListener("click", async (e) => {
  e.preventDefault();
  const btn = e.currentTarget;
  if (state.status === "live") await runAction("pause", btn);
  else if (state.status === "paused") await runAction("resume", btn);
  else await runAction("start", btn);
});

document.getElementById("btn-sold")?.addEventListener("click", (e) => {
  e.preventDefault();
  openSoldModal();
});

document.getElementById("sold-modal-cancel")?.addEventListener("click", () => closeSoldModal());
document.getElementById("sold-modal")?.addEventListener("click", (e) => {
  if (e.target.id === "sold-modal") closeSoldModal();
});
document.getElementById("sold-modal-confirm")?.addEventListener("click", async (e) => {
  closeSoldModal();
  await runAction("sold", e.currentTarget);
});

document.getElementById("team-picker-cancel")?.addEventListener("click", () => closeTeamPicker());
document.getElementById("team-picker-modal")?.addEventListener("click", (e) => {
  if (e.target.id === "team-picker-modal") closeTeamPicker();
});
document.getElementById("team-picker-grid")?.addEventListener("click", async (e) => {
  const btn = e.target.closest("[data-pick-team]");
  if (!btn) return;
  const teamId = Number(btn.dataset.pickTeam);
  try {
    await pushBroadcast("team", teamId);
    closeTeamPicker();
    const team = (state.teams || []).find((t) => Number(t.id) === teamId);
    showToast(`On air · ${team?.name || "Team"}`, "ok");
  } catch (err) {
    showToast(err.message || "Broadcast failed", "err");
  }
});

root.querySelectorAll("[data-action]").forEach((btn) => {
  btn.addEventListener("click", () => runAction(btn.dataset.action, btn));
});

root.addEventListener("click", async (e) => {
  const copyBtn = e.target.closest("[data-copy]");
  if (copyBtn) {
    e.preventDefault();
    const card = copyBtn.closest(".alc-screen-card, .alc-youtube-card, .alc-urls-drawer");
    const input = card?.querySelector("[data-copy-url]");
    const url = input?.value || "";
    try {
      await navigator.clipboard.writeText(url);
      const prev = copyBtn.textContent;
      copyBtn.textContent = "Copied";
      setTimeout(() => {
        copyBtn.textContent = prev;
      }, 1200);
    } catch (_) {
      if (input) {
        input.focus();
        input.select();
      }
      showToast("Copy failed — select URL manually", "err");
    }
    return;
  }

  const broadcast = e.target.closest("[data-broadcast]");
  if (broadcast && broadcast.tagName !== "A") {
    e.preventDefault();
    try {
      const view = broadcast.dataset.broadcast;
      let teamId = broadcast.dataset.teamId || null;
      if (view === "team" && !teamId) {
        openTeamPicker();
        return;
      }
      await pushBroadcast(view, teamId);
      showToast(view === "teams" ? "On air · All Teams" : `On air · ${view}`, "ok");
    } catch (err) {
      showToast(err.message || "Broadcast failed", "err");
    }
    return;
  }

  const quick = e.target.closest("[data-quick]");
  const select = e.target.closest("[data-select]");
  if (!quick && !select) return;

  const id = Number((quick || select).dataset.teamId);
  const team = (state.teams || []).find((t) => Number(t.id) === id);
  const min = minBid(state);

  if (quick) {
    e.preventDefault();
    e.stopPropagation();
    if (!canOperateBids()) {
      showToast("Auction must be live to bid", "err");
      return;
    }
    if (team && Number(team.remaining) < min) {
      showToast("Team cannot afford next bid", "err");
      return;
    }
    try {
      selectedTeamId = id;
      syncSelectedLabel();
      syncBiddingUi(state);
      await placeBid(id, min);
    } catch (err) {
      showToast(err.message || "Bid failed", "err");
    }
    return;
  }

  selectedTeamId = id;
  setAmount(min);
  syncSelectedLabel();
  syncBiddingUi(state);
});

document.getElementById("bid-inc")?.addEventListener("click", () => {
  const step = Number(state.bidIncrement || defaultInc);
  setAmount(getAmount() + step);
});

document.getElementById("bid-dec")?.addEventListener("click", () => {
  const step = Number(state.bidIncrement || defaultInc);
  setAmount(Math.max(minBid(state), getAmount() - step));
});

document.getElementById("bid-set-min")?.addEventListener("click", () => {
  setAmount(minBid(state));
});

document.getElementById("bid-increments")?.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-inc]");
  if (!btn) return;
  const add = Number(btn.dataset.inc);
  const floor = minBid(state);
  if (getAmount() < floor) setAmount(floor + add);
  else setAmount(Math.max(getAmount(), floor) + add);
});

document.getElementById("quick-edit-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const id = document.getElementById("edit-player-id")?.value;
  if (!id) {
    showToast("No player on block", "err");
    return;
  }
  const body = {
    full_name: document.getElementById("edit-full_name").value,
    playing_role: document.getElementById("edit-playing_role").value,
    batting_style: document.getElementById("edit-batting_style").value,
    bowling_style: document.getElementById("edit-bowling_style").value,
    base_price: Number(document.getElementById("edit-base_price").value || 0),
    matches: document.getElementById("edit-matches").value,
    runs: document.getElementById("edit-runs").value,
    highest_score: document.getElementById("edit-highest_score").value,
    wickets: document.getElementById("edit-wickets").value,
  };
  try {
    await api(`/pool/${id}`, "PATCH", body);
    showToast("Player updated", "ok");
  } catch (err) {
    showToast(err.message || "Save failed", "err");
  }
});

document.getElementById("settings-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const rawInc = document.getElementById("set-custom_increments").value || "";
  const custom = rawInc
    .split(/[,\s]+/)
    .map((x) => Number(String(x).replace(/,/g, "")))
    .filter((n) => n > 0);
  const body = {
    bid_increment: Number(document.getElementById("set-bid_increment").value || defaultInc),
    bid_timer_seconds: Number(document.getElementById("set-bid_timer_seconds").value || 30),
    default_base_price: Number(document.getElementById("set-default_base_price").value || 0),
    max_squad: Number(document.getElementById("set-max_squad")?.value || 25),
    league_title: document.getElementById("set-league_title").value || null,
    sponsor_line: document.getElementById("set-sponsor_line").value || null,
    custom_increments: custom.length ? custom : undefined,
  };
  try {
    await api("/rules", "PATCH", body);
    showToast("Settings saved", "ok");
  } catch (err) {
    showToast(err.message || "Save failed", "err");
  }
});

let purseEditTeamId = null;
const purseModal = document.getElementById("purse-edit-modal");
function closePurseEdit() {
  purseEditTeamId = null;
  if (purseModal) purseModal.hidden = true;
}
function openPurseEdit(teamId, name, remaining) {
  purseEditTeamId = Number(teamId);
  const title = document.getElementById("purse-edit-team");
  const input = document.getElementById("purse-edit-remaining");
  if (title) title.textContent = name || "Team";
  if (input) input.value = String(remaining || 0);
  if (purseModal) purseModal.hidden = false;
  input?.focus();
}
document.getElementById("live-teams")?.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-edit-purse]");
  if (!btn) return;
  openPurseEdit(btn.dataset.editPurse, btn.dataset.teamName, btn.dataset.remaining);
});
document.getElementById("purse-edit-cancel")?.addEventListener("click", closePurseEdit);
purseModal?.addEventListener("click", (e) => {
  if (e.target === purseModal) closePurseEdit();
});
document.getElementById("purse-edit-save")?.addEventListener("click", async () => {
  if (!purseEditTeamId) return;
  const remaining = Number(document.getElementById("purse-edit-remaining")?.value || 0);
  try {
    await api(`/teams/${purseEditTeamId}`, "PATCH", { remaining });
    showToast("Balance updated", "ok");
    closePurseEdit();
  } catch (err) {
    showToast(err.message || "Save failed", "err");
  }
});

/* Keyboard shortcuts */
document.addEventListener("keydown", (e) => {
  if (isTypingTarget(e.target)) return;
  const soldOpen = !document.getElementById("sold-modal")?.hidden;
  const pickerOpen = !document.getElementById("team-picker-modal")?.hidden;
  const purseOpen = !document.getElementById("purse-edit-modal")?.hidden;

  if (e.key === "Escape") {
    if (soldOpen) {
      e.preventDefault();
      closeSoldModal();
    }
    if (pickerOpen) {
      e.preventDefault();
      closeTeamPicker();
    }
    if (purseOpen) {
      e.preventDefault();
      closePurseEdit();
    }
    return;
  }

  if (soldOpen) {
    if (e.key === "Enter") {
      e.preventDefault();
      document.getElementById("sold-modal-confirm")?.click();
    }
    return;
  }

  const key = e.key.toLowerCase();
  if (e.code === "Space" || key === " ") {
    e.preventDefault();
    runAction("bid", document.getElementById("btn-place-bid"));
    return;
  }
  if (key === "z") {
    e.preventDefault();
    runAction("undo-bid", document.getElementById("btn-undo-bid"));
    return;
  }
  if (key === "s") {
    e.preventDefault();
    openSoldModal();
    return;
  }
  if (key === "u") {
    e.preventDefault();
    runAction("unsold");
    return;
  }
  if (key === "n") {
    e.preventDefault();
    runAction("next");
    return;
  }
  if (e.key === "ArrowUp") {
    e.preventDefault();
    setAmount(getAmount() + Number(state.bidIncrement || defaultInc));
    return;
  }
  if (e.key === "ArrowDown") {
    e.preventDefault();
    setAmount(Math.max(minBid(state), getAmount() - Number(state.bidIncrement || defaultInc)));
  }
});

const realtime = new CricketRealtime({
  room: root.dataset.room,
  hydrateUrl: `/api/auctions/${auctionId}/state`,
  pollWhenLiveMs: 2000,
  pollWhenOfflineMs: 800,
  onState: (s) => renderState(s),
  onStatus: (status) => {
    const pill = document.getElementById("alc-ws-status");
    if (!pill) return;
    pill.dataset.status = status;
    pill.textContent =
      status === "live"
        ? "Pusher live"
        : status === "connecting" || status === "reconnecting"
          ? "Pusher connecting"
          : "Pusher offline";
  },
});
realtime.start();
renderState(state);
