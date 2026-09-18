/**
 * Laravel control panel — pitch layout + full cricket action pad.
 */

const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
let roomId = window.CRICKET_ROOM || "match1";
let state = null;
let version = 0;
let busy = false;
let selectedZone = null;
let editingRole = "striker";
let realtime = null;

const $ = (id) => document.getElementById(id);

function api(path, options = {}) {
  const isForm = typeof FormData !== "undefined" && options.body instanceof FormData;
  const headers = {
    Accept: "application/json",
    "X-CSRF-TOKEN": csrf,
    "X-Requested-With": "XMLHttpRequest",
    ...(options.headers || {}),
  };
  if (!isForm && !headers["Content-Type"]) {
    headers["Content-Type"] = "application/json";
  }
  return fetch(`/api/matches/${encodeURIComponent(roomId)}${path}`, {
    ...options,
    headers,
  }).then(async (res) => {
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || data.message || res.statusText);
    return data;
  });
}

async function uploadLogoFile(slot, file) {
  if (!file) return null;
  const fd = new FormData();
  fd.append("logo", file);
  fd.append("slot", slot);
  const data = await api("/upload-logo", { method: "POST", body: fd });
  applyState(data.state);
  return data.url;
}

function setStatus(ok, label) {
  const el = $("connection-status");
  if (!el) return;
  const text = label || (ok ? "Live (WS)" : "Disconnected");
  el.textContent = text;
  el.className = `status-pill ${ok ? "status-connected" : "status-disconnected"}`;
}

function updateOverlayUrl() {
  $("overlay-url").value = `${window.location.origin}/overlay?room=${encodeURIComponent(roomId)}`;
}

function esc(t) {
  return String(t).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

function findPlayer(list, id) {
  return (list || []).find((p) => p.id === id) || null;
}

function updatePitchPlayers(s) {
  const bat = s.battingTeam === "A" ? s.teamA : s.teamB;
  const bowl = s.battingTeam === "A" ? s.teamB : s.teamA;

  const striker = findPlayer(bat.batsmen, bat.strikerId);
  const nonStriker = findPlayer(bat.batsmen, bat.nonStrikerId);
  const bowler = findPlayer(bowl.bowlers, bowl.currentBowlerId);

  $("ck-striker-name").textContent = striker?.name || ($("striker-name").value || "Set striker");
  $("ck-nonstriker-name").textContent = nonStriker?.name || ($("nonstriker-name").value || "Set non-striker");
  $("ck-bowler-name").textContent = bowler?.name || ($("bowler-name").value || "Set bowler");

  $("ck-striker-stats").textContent = striker
    ? `${striker.runs} (${striker.balls})`
    : "";
  $("ck-nonstriker-stats").textContent = nonStriker
    ? `${nonStriker.runs} (${nonStriker.balls})`
    : "";

  if ($("keeper-name")) {
    $("ck-keeper-name").textContent = $("keeper-name").value.trim() || "Keeper";
  }

  const live = $("live-lines");
  if (live) {
    live.innerHTML = `${esc(bat.batsman1 || "—")} · ${esc(bowl.bowler || "—")}`;
  }

  // Prefill empty inputs from state
  if (striker && !$("striker-name").value) $("striker-name").value = striker.name;
  if (nonStriker && !$("nonstriker-name").value) $("nonstriker-name").value = nonStriker.name;
  if (bowler && !$("bowler-name").value) $("bowler-name").value = bowler.name;
}

function applyState(s) {
  state = s;
  $("match-title").value = s.matchTitle || "";
  $("match-no").value = s.matchNo || "";
  $("total-overs").value = s.totalOvers || 20;
  $("team-a-name").value = s.teamA?.name || "";
  $("team-b-name").value = s.teamB?.name || "";
  $("batting-a").checked = s.battingTeam === "A";
  $("batting-b").checked = s.battingTeam === "B";
  $("visible-toggle").checked = !!s.visible;
  $("powerplay-toggle").checked = !!s.powerplay;
  $("result-text").value = s.result || "";
  if ($("organizer-logo")) $("organizer-logo").value = s.organizerLogo || "";
  if ($("streamer-logo")) $("streamer-logo").value = s.streamerLogo || "";
  if ($("player-avatar")) $("player-avatar").value = s.playerAvatar || "";
  if ($("team-a-logo")) $("team-a-logo").value = s.teamA?.logo || "";
  if ($("team-b-logo")) $("team-b-logo").value = s.teamB?.logo || "";
  paintLogoPreview("organizer-logo-preview", s.organizerLogo);
  paintLogoPreview("streamer-logo-preview", s.streamerLogo);
  paintLogoPreview("player-avatar-preview", s.playerAvatar);
  paintLogoPreview("team-a-logo-preview", s.teamA?.logo);
  paintLogoPreview("team-b-logo-preview", s.teamB?.logo);
  if (window.KnockoutAdmin) {
    window.KnockoutAdmin.setRoom(roomId);
    window.KnockoutAdmin.applyFromState(s);
  }

  $("hero-a-name").textContent = s.teamA?.name || "Team A";
  $("hero-a-score").textContent = `${s.teamA?.score ?? 0}/${s.teamA?.wickets ?? 0}`;
  $("hero-a-overs").textContent = `(${s.teamA?.overs || "0.0"} ov)`;
  $("hero-b-name").textContent = s.teamB?.name || "Team B";
  $("hero-b-score").textContent = `${s.teamB?.score ?? 0}/${s.teamB?.wickets ?? 0}`;
  $("hero-b-overs").textContent = `(${s.teamB?.overs || "0.0"} ov)`;
  $("hero-team-a").classList.toggle("batting", s.battingTeam === "A");
  $("hero-team-b").classList.toggle("batting", s.battingTeam === "B");

  const resultEl = $("hero-result");
  const phase = matchStatusOf(s);
  if (phase === "completed" && s.result?.trim()) {
    resultEl.textContent = `RESULT: ${s.result}`;
    resultEl.classList.add("has-result");
  } else if (phase === "innings_break") {
    resultEl.textContent = `INNINGS BREAK · TARGET ${s.target ?? "—"}`;
    resultEl.classList.remove("has-result");
  } else if (s.toss?.text) {
    resultEl.textContent = s.toss.text;
    resultEl.classList.remove("has-result");
  } else if (s.target != null && (Number(s.innings) || 1) >= 2) {
    resultEl.textContent = `TARGET: ${s.target}`;
    resultEl.classList.remove("has-result");
  } else {
    resultEl.textContent = "Toss not set";
    resultEl.classList.remove("has-result");
  }

  if ($("nd-match-meta")) {
    $("nd-match-meta").textContent = `Match No. ${s.matchNo || "1"} | ${s.totalOvers || 20} Overs`;
  }
  if ($("hero-a-crr")) $("hero-a-crr").textContent = `CRR: ${calcCrr(s.teamA)}`;
  if ($("hero-b-crr")) $("hero-b-crr").textContent = `CRR: ${calcCrr(s.teamB)}`;
  if ($("hero-a-proj")) $("hero-a-proj").textContent = `Projected: ${projected(s.teamA, s.totalOvers)}`;
  if ($("hero-b-rrr")) {
    const need = s.target != null ? Math.max(0, s.target - (s.teamB?.score || 0)) : null;
    const left = ballsLeftIn(s.teamB, s.totalOvers);
    $("hero-b-rrr").textContent = need != null && left > 0
      ? `Req RR: ${((need / left) * 6).toFixed(2)}`
      : "Req RR: —";
  }
  if ($("team-a-color") && s.teamA?.primaryColor) $("team-a-color").value = s.teamA.primaryColor;
  if ($("team-b-color") && s.teamB?.primaryColor) $("team-b-color").value = s.teamB.primaryColor;
  paintLogo("hero-a-logo", s.teamA);
  paintLogo("hero-b-logo", s.teamB);
  if ($("toss-winner")) $("toss-winner").value = s.toss?.winner || "";
  if ($("toss-decision") && s.toss?.decision) $("toss-decision").value = s.toss.decision;
  if ($("commentator")) $("commentator").value = s.commentator || "";
  if ($("custom-message") && s.customMessage != null) $("custom-message").value = s.customMessage || "";
  if ($("auto-gfx-toggle")) $("auto-gfx-toggle").checked = s.autoGraphics !== false;
  if ($("auto-loop-toggle")) $("auto-loop-toggle").checked = !!s.autoLoop;
  if ($("boundaries-counter-toggle")) $("boundaries-counter-toggle").checked = s.showBoundariesCounter !== false;
  if ($("show-logo-toggle")) $("show-logo-toggle").checked = s.showLogo !== false;

  updatePitchPlayers(s);
  updateNdPlayerStats(s);
  renderSquads(s);
  renderFieldEditor(s);
  renderCommentary(s);
  drawCharts(s);
  renderThisOver(s.thisOver || []);
  renderScorecard(s);
  updateMatchPhaseUi(s);
  maybePromptTargetEnd(s);
  updateTossGate(s);
}

function isTossSet(s) {
  const winner = (s?.toss?.winner || "").trim();
  const decision = String(s?.toss?.decision || "").toLowerCase().trim();
  return !!winner && (decision === "bat" || decision === "bowl");
}

function needsTossGate(s) {
  if (!s) return false;
  if (matchStatusOf(s) === "completed") return false;
  if (isTossSet(s)) return false;
  const a = Number(s.teamA?.balls) || 0;
  const b = Number(s.teamB?.balls) || 0;
  return a === 0 && b === 0;
}

let tossGateWinner = "";
let tossGateDecision = "";

function updateTossGatePreview() {
  const el = $("toss-gate-preview");
  if (!el) return;
  if (!tossGateWinner || !tossGateDecision || !state) {
    el.textContent = "Select winner and bat/bowl.";
    return;
  }
  const winner = tossGateWinner === "B" ? (state.teamB?.name || "Team B") : (state.teamA?.name || "Team A");
  el.textContent = `'${winner}' won the toss and elected to ${tossGateDecision}.`;
}

function updateTossGate(s) {
  const modal = $("toss-gate-modal");
  if (!modal) return;
  const required = needsTossGate(s);
  document.body.classList.toggle("toss-required", required);
  modal.hidden = !required;
  if (!required) return;

  const aBtn = $("toss-gate-a");
  const bBtn = $("toss-gate-b");
  if (aBtn) aBtn.textContent = s.teamA?.name || "Team A";
  if (bBtn) bBtn.textContent = s.teamB?.name || "Team B";
  updateTossGatePreview();
}

/** When chase target is reached, ask before completing the match. */
let targetEndPromptOpen = false;
async function maybePromptTargetEnd(s) {
  if (!s || matchStatusOf(s) !== "live") return;
  const pending = s.pendingMatchEnd;
  if (!pending || pending.reason !== "target") return;
  if (targetEndPromptOpen) return;

  targetEndPromptOpen = true;
  const team = pending.teamName || "Chasing side";
  const score = pending.score ?? "?";
  const target = pending.target ?? "?";
  let ok = false;
  try {
    ok = confirm(
      `TARGET REACHED\n\n${team}: ${score}\nTarget: ${target}\n\nEnd the match now?`
    );
  } finally {
    targetEndPromptOpen = false;
  }

  if (ok) {
    await doAction({ type: "end_innings" });
    return;
  }

  // Operator chose to continue — do not re-prompt until score drops below target (undo).
  try {
    const data = await api("/patch", {
      method: "POST",
      body: JSON.stringify({ pendingMatchEnd: null, pendingMatchEndDismissed: true }),
    });
    version = data.version;
    // Apply without re-entering prompt (pending cleared).
    const next = data.state;
    if (next) {
      next.pendingMatchEnd = null;
      next.pendingMatchEndDismissed = true;
    }
    applyState(next);
  } catch (e) {
    console.error(e);
  }
}

function matchStatusOf(s) {
  if (!s) return "live";
  if (s.matchStatus === "innings_break" || s.matchStatus === "completed" || s.matchStatus === "live") {
    return s.matchStatus;
  }
  // Do NOT treat a non-empty result string as match completion.
  return "live";
}

function updateMatchPhaseUi(s) {
  const status = matchStatusOf(s);
  const ended = status === "completed";
  const onBreak = status === "innings_break";
  const innings = Number(s.innings) || 1;

  const pm = $("post-match");
  const ib = $("innings-break");
  document.body.classList.toggle("post-match-active", ended);
  document.body.classList.toggle("innings-break-active", onBreak);

  if (pm) {
    pm.hidden = !ended;
  }
  if (ib) {
    ib.hidden = !onBreak;
  }

  const endInn = $("btn-end-innings");
  const endMatch = $("btn-end-match");
  if (endInn) {
    if (innings >= 2) {
      endInn.textContent = "End 2nd Innings";
      endInn.hidden = false;
      endInn.disabled = ended || onBreak;
    } else {
      endInn.textContent = "End 1st Innings";
      endInn.hidden = false;
      endInn.disabled = ended || onBreak;
    }
  }
  if (endMatch) {
    // Alias for ending 2nd innings only — never visible/usable in 1st innings.
    endMatch.textContent = "End 2nd Innings";
    endMatch.hidden = innings < 2 || onBreak;
    endMatch.disabled = ended || onBreak || innings < 2;
  }

  const setResult = $("btn-set-result");
  if (setResult) {
    setResult.title = "Admin override only — does not apply during a live innings without confirmation";
  }

  if (onBreak) {
    updateInningsBreak(s);
  }
  if (ended) {
    updatePostMatch(s);
  }
}

function updateInningsBreak(s) {
  const batKey = s.battingTeam === "B" ? "teamB" : "teamA";
  const finished = s[batKey] || {};
  const chaseKey = batKey === "teamA" ? "teamB" : "teamA";
  const chase = s[chaseKey] || {};
  const target = s.target != null ? Number(s.target) : (Number(finished.score) || 0) + 1;

  if ($("ib-finished-line")) {
    $("ib-finished-line").textContent =
      `${finished.name || "Team"}  ${finished.score ?? 0}/${finished.wickets ?? 0}  (${finished.overs || "0.0"} ov)`;
  }
  if ($("ib-target-line")) {
    $("ib-target-line").textContent = `TARGET ${target}`;
  }
  if ($("ib-need-line")) {
    $("ib-need-line").textContent =
      `${chase.name || "Chasing side"} needs ${target} runs to win.`;
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
  return (runs / overs).toFixed(2);
}

function ballsToOvers(balls) {
  const n = Number(balls) || 0;
  return `${Math.floor(n / 6)}.${n % 6}`;
}

function pickPom(s) {
  const all = [];
  (s.teamA?.batsmen || []).forEach((b) => all.push({ ...b, teamName: s.teamA.name, team: "A" }));
  (s.teamB?.batsmen || []).forEach((b) => all.push({ ...b, teamName: s.teamB.name, team: "B" }));
  all.sort((a, b) => (b.runs || 0) - (a.runs || 0) || (a.balls || 0) - (b.balls || 0));
  return all[0] || null;
}

function computeMatchResult(s) {
  // Intentionally does NOT invent a winner from raw team scores.
  // Only return an existing engine-generated result.
  if (matchStatusOf(s) === "completed" && s.result?.trim()) {
    return s.result.trim();
  }
  return "";
}

function renderTeamScorecard(team, bowlingTeam, teamKey) {
  const bats = (team.batsmen || [])
    .map((b) => {
      const how = b.out ? esc(b.dismissal || "out") : "not out";
      return `<tr>
        <td>${esc(b.name)}</td>
        <td>${how}</td>
        <td>${b.runs ?? 0}</td>
        <td>${b.balls ?? 0}</td>
        <td>${b.fours ?? 0}</td>
        <td>${b.sixes ?? 0}</td>
        <td>${calcSR(b.runs, b.balls)}</td>
      </tr>`;
    })
    .join("") || `<tr><td colspan="7" class="sc-empty">No batsmen</td></tr>`;

  const bowls = (bowlingTeam.bowlers || [])
    .map((b) => `<tr>
      <td>${esc(b.name)}</td>
      <td>${ballsToOvers(b.balls)}</td>
      <td>${b.maidens ?? 0}</td>
      <td>${b.runs ?? 0}</td>
      <td>${b.wickets ?? 0}</td>
      <td>${calcEcon(b.runs, b.balls)}</td>
      <td>${b.dots ?? 0}</td>
    </tr>`)
    .join("") || `<tr><td colspan="7" class="sc-empty">No bowlers</td></tr>`;

  const extras = team.extras || {};
  const exTotal = (extras.wd || 0) + (extras.nb || 0) + (extras.b || 0) + (extras.lb || 0);
  const onCard = new Set((team.batsmen || []).map((b) => String(b.name || "").toLowerCase()).filter(Boolean));
  const didNotBat = (team.squad || [])
    .map((p) => (typeof p === "string" ? p : p?.name) || "")
    .filter((n) => n && !onCard.has(String(n).toLowerCase()))
    .join(", ") || "—";
  const fow = (team.batsmen || []).filter((b) => b.out).map((b, i) => `${i + 1}-${b.fowScore ?? team.score ?? 0} (${b.name})`).join(", ") || "—";

  return `
    <div class="pm-col-toolbar">
      <button type="button" class="btn btn-sm btn-success" data-anim="batting_summary" data-team="${teamKey}">Batting Summary</button>
      <button type="button" class="btn btn-sm btn-success" data-anim="bowling_summary" data-team="${teamKey}">Bowling Summary</button>
    </div>
    <div class="pm-col-head ${teamKey === "B" ? "team-b" : ""}">
      <span>${esc(team.name || "Team")}</span>
      <span>${team.score ?? 0}/${team.wickets ?? 0} (${team.overs || "0.0"} ov)</span>
    </div>
    <div class="pm-table-wrap">
      <div class="pm-section-title">Batting</div>
      <table class="pm-table">
        <thead>
          <tr><th>Batsmen</th><th>Out</th><th>R</th><th>B</th><th>4s</th><th>6s</th><th>SR</th></tr>
        </thead>
        <tbody>${bats}</tbody>
      </table>
      <div class="pm-meta-line"><strong>Total</strong> ${team.score ?? 0}/${team.wickets ?? 0} &nbsp;·&nbsp; Extras ${exTotal} (wd ${extras.wd || 0} nb ${extras.nb || 0} b ${extras.b || 0} lb ${extras.lb || 0})</div>
      <div class="pm-meta-line"><strong>Did not bat</strong> ${esc(didNotBat)}</div>
      <div class="pm-meta-line"><strong>Fall of wickets</strong> ${esc(fow)}</div>
      <div class="pm-section-title">Bowling</div>
      <table class="pm-table">
        <thead>
          <tr><th>Bowler</th><th>O</th><th>M</th><th>R</th><th>W</th><th>Econ</th><th>0s</th></tr>
        </thead>
        <tbody>${bowls}</tbody>
      </table>
    </div>
  `;
}

function updatePostMatch(s) {
  const ended = matchStatusOf(s) === "completed";
  const pm = $("post-match");
  if (!ended || !pm) return;

  $("pm-match-meta").textContent = `Match No. ${s.matchNo || "—"} · ${s.totalOvers || 20} Overs · ${s.matchTitle || ""}`;
  $("pm-a-name").textContent = s.teamA?.name || "Team A";
  $("pm-b-name").textContent = s.teamB?.name || "Team B";
  $("pm-a-score").textContent = `${s.teamA?.score ?? 0}/${s.teamA?.wickets ?? 0} (${s.teamA?.overs || "0.0"} ov)`;
  $("pm-b-score").textContent = `${s.teamB?.score ?? 0}/${s.teamB?.wickets ?? 0} (${s.teamB?.overs || "0.0"} ov)`;
  $("pm-result-banner").textContent = `RESULT: ${s.result}`;

  const pom = s.playerOfMatch || pickPom(s);
  if (pom) {
    $("pm-pom-avatar").textContent = String(pom.name || "P").slice(0, 1).toUpperCase();
    if (!$("pm-pom-name").dataset.touched) $("pm-pom-name").value = pom.name || "";
    $("pm-pom-team").textContent = pom.teamName || (pom.team === "B" ? s.teamB?.name : s.teamA?.name) || "—";
    $("pm-pom-stats").textContent = `${pom.runs ?? 0} (${pom.balls ?? 0}) · 4s ${pom.fours ?? 0} · 6s ${pom.sixes ?? 0} · SR ${calcSR(pom.runs, pom.balls)}`;
  }

  $("pm-col-a").innerHTML = renderTeamScorecard(s.teamA, s.teamB, "A");
  $("pm-col-b").innerHTML = renderTeamScorecard(s.teamB, s.teamA, "B");

  // Bind newly injected anim buttons
  $("pm-col-a").querySelectorAll("[data-anim]").forEach(bindAnimBtn);
  $("pm-col-b").querySelectorAll("[data-anim]").forEach(bindAnimBtn);
}

function bindAnimBtn(btn) {
  if (btn.dataset.bound) return;
  btn.dataset.bound = "1";
  btn.addEventListener("click", () => fireAnim(btn));
}

async function fireAnim(btn) {
  const animation = btn.dataset.anim;
  const payload = {};
  if (btn.dataset.team) payload.team = btn.dataset.team;
  if (btn.dataset.role) payload.role = btn.dataset.role;
  if (animation === "milestone" && btn.dataset.runs) {
    payload.runs = parseInt(btn.dataset.runs, 10);
    payload.sub = "RUNS";
  }
  if (animation === "best_batsman") {
    payload.playerName = $("pm-pom-name")?.value || undefined;
    payload.teamName = $("pm-pom-team")?.textContent || undefined;
  }
  if (animation === "blackboard") {
    payload.text = $("custom-message")?.value || state?.customMessage || "Black Board";
  }
  if (animation === "custom" || animation === "message") {
    payload.text = $("custom-message")?.value || btn.dataset.text || " ";
  }
  if (animation === "field_position") {
    payload.zone = $("field-zone")?.value || state?.fieldZone || "Standard";
    try {
      await saveFieldPositions(collectFieldPositions(), payload.zone);
    } catch (_) {}
  }
  if (animation === "boundaries_counter") {
    payload.scope = btn.dataset.scope || state?.boundariesScope || "tournament";
  }
  await api("/animate", {
    method: "POST",
    body: JSON.stringify({ animation, payload }),
  });
}

/** Durations aligned with GraphicsEngine.defaultDuration (+ exit buffer). */
const MATCH_STARTER_DURATIONS = {
  team_vs_team: 7600,
  toss: 7600,
  team_lineup: 8100,
  tournament_name: 6600,
  commentator: 7000,
  message: 7000,
  field_position: 10100,
};

let matchStarterCtrl = null;
let matchStarterPrevVisible = true;

function matchStarterButtons() {
  return [$("btn-match-starter"), $("btn-match-starter-panel")].filter(Boolean);
}

function setMatchStarterUi(running, label) {
  const status = $("match-starter-status");
  const bar = document.querySelector(".match-starter-bar");
  matchStarterButtons().forEach((btn) => {
    btn.classList.toggle("is-running", running);
    btn.textContent = running ? (label || "Stop Sequence") : (btn.id === "btn-match-starter-panel" ? "Start Match Sequence" : "Match Starter");
  });
  if (bar) bar.classList.toggle("is-running", running);
  if (status && !running) {
    status.textContent = "Plays Team VS → Toss → Squad A → Squad B → Tournament → Commentator → Field on one click";
  } else if (status && label) {
    status.textContent = label;
  }
}

function stopMatchStarter() {
  if (matchStarterCtrl) {
    matchStarterCtrl.abort();
    matchStarterCtrl = null;
  }
  setMatchStarterUi(false);
}

async function restoreScoreboardAfterStarter() {
  try {
    await patchFlags({ visible: matchStarterPrevVisible });
    if ($("visible-toggle")) $("visible-toggle").checked = !!matchStarterPrevVisible;
  } catch (_) {}
}

function sleepAbortable(ms, signal) {
  return new Promise((resolve, reject) => {
    if (signal?.aborted) {
      reject(new DOMException("Aborted", "AbortError"));
      return;
    }
    const t = setTimeout(resolve, ms);
    signal?.addEventListener(
      "abort",
      () => {
        clearTimeout(t);
        reject(new DOMException("Aborted", "AbortError"));
      },
      { once: true }
    );
  });
}

async function fireMatchStarterStep(animation, payload = {}) {
  if (animation === "field_position") {
    payload.zone = payload.zone || $("field-zone")?.value || state?.fieldZone || "Standard";
    try {
      await saveFieldPositions(collectFieldPositions(), payload.zone);
    } catch (_) {}
  }
  if (animation === "commentator") {
    const name = $("commentator")?.value || state?.commentator || "Commentator";
    await patchFlags({ commentator: name });
    payload = { commentator: name, text: name, organizerLogo: state?.organizerLogo || "" };
  }
  await api("/animate", {
    method: "POST",
    body: JSON.stringify({ animation, payload }),
  });
  const wait = MATCH_STARTER_DURATIONS[animation] || 5500;
  await sleepAbortable(wait, matchStarterCtrl?.signal);
}

async function runMatchStarter() {
  if (matchStarterCtrl) {
    stopMatchStarter();
    return;
  }

  const steps = [
    { label: "1/7 Team VS Team", animation: "team_vs_team", payload: {} },
    { label: "2/7 Toss details", animation: "toss", payload: {} },
    { label: "3/7 Squad A", animation: "team_lineup", payload: { team: "A" } },
    { label: "4/7 Squad B", animation: "team_lineup", payload: { team: "B" } },
    { label: "5/7 Tournament Name", animation: "tournament_name", payload: {} },
    { label: "6/7 Commentator", animation: "commentator", payload: {} },
    { label: "7/7 Field Position", animation: "field_position", payload: {} },
  ];

  matchStarterCtrl = new AbortController();
  matchStarterPrevVisible = state?.visible !== false;
  setMatchStarterUi(true, "Hiding score panel…");

  try {
    // Hide bottom scorebug for the whole intro sequence (including gaps between cards)
    await patchFlags({ visible: false });
    if ($("visible-toggle")) $("visible-toggle").checked = false;

    for (const step of steps) {
      if (matchStarterCtrl.signal.aborted) break;
      setMatchStarterUi(true, `${step.label} — click again to stop`);
      await fireMatchStarterStep(step.animation, { ...step.payload });
    }
  } catch (err) {
    if (err?.name !== "AbortError") {
      console.error(err);
      alert(err.message || "Match Starter failed");
    }
  } finally {
    matchStarterCtrl = null;
    setMatchStarterUi(false);
    await restoreScoreboardAfterStarter();
  }
}

function renderThisOver(balls) {
  const el = $("this-over-display");
  if (!el) return;
  el.innerHTML = "";
  balls.forEach((b) => {
    const span = document.createElement("span");
    span.className = "over-ball";
    if (b === "4") span.classList.add("four");
    if (b === "6") span.classList.add("six");
    if (b === "W") span.classList.add("wicket");
    if (b === "WD" || b === "NB" || b === "B" || b === "LB") span.classList.add("extra");
    span.textContent = b;
    el.appendChild(span);
  });
}

function renderScorecard(s) {
  const bat = s.battingTeam === "A" ? s.teamA : s.teamB;
  const bowl = s.battingTeam === "A" ? s.teamB : s.teamA;
  const batsmen = (bat.batsmen || [])
    .map((b) => {
      const mark = b.out ? `  ${b.dismissal || "out"}` : b.onStrike ? " *" : "";
      return `<div class="sc-row">${esc(b.name)} <span>${b.runs} (${b.balls})${esc(mark)}</span></div>`;
    })
    .join("");
  const bowlers = (bowl.bowlers || [])
    .map((b) => {
      const overs = `${Math.floor((b.balls || 0) / 6)}.${(b.balls || 0) % 6}`;
      return `<div class="sc-row">${esc(b.name)} <span>${b.wickets}/${b.runs} (${overs})</span></div>`;
    })
    .join("");
  const extras = bat.extras || {};
  $("scorecard").innerHTML = `
    <h3>${esc(bat.name)} batting</h3>
    ${batsmen || "<div class='sc-empty'>No batsmen yet</div>"}
    <div class="sc-extras">Extras: wd ${extras.wd || 0} nb ${extras.nb || 0} b ${extras.b || 0} lb ${extras.lb || 0}</div>
    <h3>${esc(bowl.name)} bowling</h3>
    ${bowlers || "<div class='sc-empty'>No bowlers yet</div>"}
  `;
}

async function refresh() {
  try {
    const data = await api("");
    version = data.version;
    applyState(data.state);
    setStatus(true);
  } catch (e) {
    console.error(e);
    setStatus(false);
  }
}

async function doAction(body) {
  if (busy) return;
  const scoringTypes = new Set([
    "dot", "run", "wide", "noball", "bye", "legbye", "penalty", "wicket", "end_innings",
  ]);
  if (scoringTypes.has(body?.type) && needsTossGate(state)) {
    updateTossGate(state);
    alert("Set the toss first before scoring.");
    return;
  }
  busy = true;
  try {
    if (selectedZone) body.zone = selectedZone;
    const data = await api("/action", { method: "POST", body: JSON.stringify(body) });
    version = data.version;
    applyState(data.state);
    setStatus(true);
    return data;
  } catch (e) {
    alert(e.message || "Action failed — set striker & bowler first");
    setStatus(false);
  } finally {
    busy = false;
  }
}

function buildDismissal(code) {
  const bowlingTeam = state && (state.battingTeam === "A" ? state.teamB : state.teamA);
  const bowlName = (bowlingTeam?.bowlers || []).find((b) => b.id === bowlingTeam?.currentBowlerId)?.name || "";
  const d = code || $("dismissal-type")?.value || "OUT";
  if (d === "b") return `b ${bowlName}`.trim();
  if (d === "c") return `c ? b ${bowlName}`.trim();
  if (d === "c & b" || d === "c &amp; b") return `c & b ${bowlName}`.trim();
  if (d === "lbw") return `lbw b ${bowlName}`.trim();
  if (d === "st") return `st ? b ${bowlName}`.trim();
  if (d === "hit wicket") return "hit wicket";
  if (d === "run out") return "run out";
  if (d === "retired hurt") return "retired hurt";
  return d;
}

async function connectRoom() {
  const id = ($("room-input").value || "").trim();
  if (!id) return alert("Enter a room id");
  roomId = id;
  $("current-room").textContent = roomId;
  updateOverlayUrl();
  history.replaceState({}, "", `/control?room=${encodeURIComponent(roomId)}`);
  if (window.KnockoutAdmin) window.KnockoutAdmin.setRoom(roomId);
  if (realtime) realtime.setRoom(roomId);
}

$("btn-connect").addEventListener("click", connectRoom);

$("btn-save-meta").addEventListener("click", async () => {
  const data = await api("/patch", {
    method: "POST",
    body: JSON.stringify({
      matchTitle: $("match-title").value,
      matchNo: $("match-no").value,
      totalOvers: parseInt($("total-overs").value, 10) || 20,
      visible: $("visible-toggle").checked,
      powerplay: $("powerplay-toggle").checked,
      teamA: {
        name: $("team-a-name").value,
        logo: $("team-a-logo")?.value || "",
      },
      teamB: {
        name: $("team-b-name").value,
        logo: $("team-b-logo")?.value || "",
      },
      organizerLogo: $("organizer-logo")?.value || "",
      streamerLogo: $("streamer-logo")?.value || "",
      playerAvatar: $("player-avatar")?.value || "",
    }),
  });
  applyState(data.state);
});

$("btn-set-players").addEventListener("click", async () => {
  const data = await api("/players", {
    method: "POST",
    body: JSON.stringify({
      strikerName: $("striker-name").value,
      nonStrikerName: $("nonstriker-name").value,
      bowlerName: $("bowler-name").value,
    }),
  });
  applyState(data.state);
  if ($("keeper-name")) {
    $("ck-keeper-name").textContent = $("keeper-name").value.trim() || "Keeper";
  }
});

// Sync pitch labels while typing
["striker-name", "nonstriker-name", "bowler-name", "keeper-name"].forEach((id) => {
  $(id)?.addEventListener("input", () => {
    if (id === "striker-name") $("ck-striker-name").textContent = $("striker-name").value || "Set striker";
    if (id === "nonstriker-name") $("ck-nonstriker-name").textContent = $("nonstriker-name").value || "Set non-striker";
    if (id === "bowler-name") $("ck-bowler-name").textContent = $("bowler-name").value || "Set bowler";
    if (id === "keeper-name") $("ck-keeper-name").textContent = $("keeper-name").value || "Keeper";
  });
});

$("batting-a").addEventListener("change", async () => {
  if ($("batting-a").checked) {
    const data = await api("/batting", { method: "POST", body: JSON.stringify({ team: "A" }) });
    applyState(data.state);
  }
});
$("batting-b").addEventListener("change", async () => {
  if ($("batting-b").checked) {
    const data = await api("/batting", { method: "POST", body: JSON.stringify({ team: "B" }) });
    applyState(data.state);
  }
});

$("visible-toggle").addEventListener("change", async () => {
  const data = await api("/patch", {
    method: "POST",
    body: JSON.stringify({ visible: $("visible-toggle").checked }),
  });
  applyState(data.state);
});

$("powerplay-toggle").addEventListener("change", async () => {
  const data = await api("/patch", {
    method: "POST",
    body: JSON.stringify({ powerplay: $("powerplay-toggle").checked }),
  });
  applyState(data.state);
});

$("btn-set-result").addEventListener("click", async () => {
  const text = ($("result-text").value || "").trim();
  if (!text) {
    const data = await api("/patch", {
      method: "POST",
      body: JSON.stringify({ result: "", matchStatus: "live", forceComplete: true }),
    });
    applyState(data.state);
    return;
  }
  if (matchStatusOf(state) !== "completed") {
    const ok = confirm(
      "ADMIN OVERRIDE: Writing a result will force the match to COMPLETED and skip normal cricket lifecycle.\n\nOnly use this for walkovers/admin corrections.\n\nContinue?"
    );
    if (!ok) return;
  }
  const data = await api("/patch", {
    method: "POST",
    body: JSON.stringify({ result: text, forceComplete: true }),
  });
  applyState(data.state);
});

$("btn-end-match")?.addEventListener("click", async () => {
  if (!state) return;
  const status = matchStatusOf(state);
  if (status === "innings_break") {
    alert("Start the 2nd innings before ending the match.");
    return;
  }
  if (status === "completed") return;
  if ((Number(state.innings) || 1) <= 1) {
    alert("First innings is still in progress. Use “End 1st Innings” — that does not finish the match.");
    return;
  }
  await doAction({ type: "end_innings" });
});

$("btn-start-second-innings")?.addEventListener("click", async () => {
  await doAction({ type: "start_second_innings" });
});

$("btn-resume-scoring")?.addEventListener("click", async () => {
  const data = await api("/patch", {
    method: "POST",
    body: JSON.stringify({ result: "", matchStatus: "live", forceComplete: true }),
  });
  applyState(data.state);
});

$("pm-pom-name")?.addEventListener("input", () => {
  $("pm-pom-name").dataset.touched = "1";
});

$("btn-pom-save")?.addEventListener("click", async () => {
  const name = ($("pm-pom-name").value || "").trim();
  if (!name) return alert("Enter player name");
  const data = await api("/patch", {
    method: "POST",
    body: JSON.stringify({
      playerOfMatch: {
        name,
        teamName: $("pm-pom-team").textContent,
        runs: pickPom(state)?.runs,
        balls: pickPom(state)?.balls,
        fours: pickPom(state)?.fours,
        sixes: pickPom(state)?.sixes,
      },
    }),
  });
  applyState(data.state);
});

function wireRotate(btn) {
  btn?.addEventListener("click", async () => {
    if (btn.disabled) return;
    const strikerCard = $("card-striker");
    const nonStrikerCard = $("card-nonstriker");
    const row = $("card-striker")?.parentElement;
    btn.disabled = true;

    const leftRect = strikerCard?.getBoundingClientRect();
    const rightRect = nonStrikerCard?.getBoundingClientRect();
    const delta = leftRect && rightRect ? rightRect.left - leftRect.left : 0;

    try {
      await doAction({ type: "rotate_strike" });
      // FLIP: content already swapped in-place — animate cards into their new settled spots
      if (strikerCard && nonStrikerCard && delta) {
        row?.classList.add("is-swapping");
        strikerCard.style.transition = "none";
        nonStrikerCard.style.transition = "none";
        strikerCard.style.transform = `translateX(${delta}px)`;
        nonStrikerCard.style.transform = `translateX(${-delta}px)`;
        // force layout so the starting transform sticks
        void strikerCard.offsetWidth;
        const ease = "transform 0.45s cubic-bezier(0.22, 1, 0.36, 1)";
        strikerCard.style.transition = ease;
        nonStrikerCard.style.transition = ease;
        strikerCard.style.transform = "";
        nonStrikerCard.style.transform = "";
        await new Promise((r) => setTimeout(r, 460));
        strikerCard.style.transition = "";
        nonStrikerCard.style.transition = "";
        row?.classList.remove("is-swapping");
      }
    } finally {
      btn.disabled = false;
    }
  });
}
function wireEndInnings(btn) {
  btn?.addEventListener("click", () => doAction({ type: "end_innings" }));
}

wireRotate($("btn-rotate"));
wireRotate($("btn-rotate-2"));
wireEndInnings($("btn-end-innings"));
wireEndInnings($("btn-end-innings-2"));

$("btn-reset").addEventListener("click", () => {
  if (confirm("Reset this match?")) doAction({ type: "reset" });
});

$("btn-end-over-hint")?.addEventListener("click", () => {
  alert("Legal overs end automatically after 6 balls. Extras (wide/no-ball) do not count as a legal ball.");
});

document.querySelectorAll('[data-quick="powerplay"]').forEach((btn) => {
  btn.addEventListener("click", () => {
    $("powerplay-toggle").checked = !$("powerplay-toggle").checked;
    $("powerplay-toggle").dispatchEvent(new Event("change"));
  });
});

/* Cricketer click → focus edit field */
document.querySelectorAll(".cricketer").forEach((ck) => {
  ck.addEventListener("click", () => {
    document.querySelectorAll(".cricketer").forEach((c) => c.classList.remove("active"));
    ck.classList.add("active");
    editingRole = ck.dataset.role;
    const map = {
      striker: "striker-name",
      nonstriker: "nonstriker-name",
      bowler: "bowler-name",
      keeper: "keeper-name",
    };
    const input = $(map[editingRole]);
    if (input) {
      input.focus();
      input.select?.();
      input.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }
  });
});

/* Shot zones */
document.querySelectorAll(".shot-zone").forEach((z) => {
  z.addEventListener("click", () => {
    document.querySelectorAll(".shot-zone").forEach((x) => x.classList.remove("active"));
    z.classList.add("active");
    selectedZone = z.dataset.zone;
    $("shot-zone-label").textContent = `Shot zone: ${z.dataset.hint || selectedZone}`;
  });
});

/* Action pad */
document.querySelectorAll(".pad-btn").forEach((btn) => {
  btn.addEventListener("click", () => {
    const action = btn.dataset.action;
    if (!action) return;

    if (action === "wicket") {
      const code = btn.dataset.dismissal || $("dismissal-type")?.value || "OUT";
      if ($("dismissal-type")) $("dismissal-type").value = code === "c & b" ? "c" : code;
      doAction({ type: "wicket", dismissal: buildDismissal(code) });
      $("striker-name").value = "";
      $("ck-striker-name").textContent = "Set new striker";
      return;
    }
    if (action === "dot") return doAction({ type: "dot", runs: 0 });
    if (action === "run") return doAction({ type: "run", runs: parseInt(btn.dataset.runs, 10) || 0 });
    if (action === "wide") return doAction({ type: "wide", extra: parseInt(btn.dataset.extra, 10) || 0 });
    if (action === "noball") return doAction({ type: "noball", runs: parseInt(btn.dataset.runs, 10) || 0 });
    if (action === "bye") return doAction({ type: "bye", runs: parseInt(btn.dataset.runs, 10) || 1 });
    if (action === "legbye") return doAction({ type: "legbye", runs: parseInt(btn.dataset.runs, 10) || 1 });
    if (action === "penalty") return doAction({ type: "penalty", runs: parseInt(btn.dataset.runs, 10) || 5 });
  });
});

document.querySelectorAll("[data-anim]").forEach(bindAnimBtn);
document.querySelectorAll("[data-gfx-fire]").forEach((btn) => {
  btn.addEventListener("click", async () => {
    const sel = $(btn.dataset.gfxFire);
    if (!sel) return;
    const raw = sel.value || "";
    const [anim, extra] = raw.split("|");
    const fake = document.createElement("button");
    fake.dataset.anim = anim;
    if (anim === "team_lineup" || anim === "batting_summary" || anim === "bowling_summary") {
      fake.dataset.team = extra || "A";
    }
    if (anim === "boundaries_counter") fake.dataset.scope = extra || "match";
    if (anim === "milestone") fake.dataset.runs = extra || "50";
    await fireAnim(fake);
  });
});
$("btn-copy-url").addEventListener("click", async () => {
  try {
    await navigator.clipboard.writeText($("overlay-url").value);
    $("btn-copy-url").textContent = "Copied!";
    setTimeout(() => { $("btn-copy-url").textContent = "Copy"; }, 1200);
  } catch {
    $("overlay-url").select();
  }
});

updateOverlayUrl();

/* ── ND Sports desk helpers ── */

function calcCrr(team) {
  const balls = team?.balls || 0;
  if (balls <= 0) return "0.00";
  return (((team.score || 0) / balls) * 6).toFixed(2);
}
function projected(team, totalOvers) {
  const balls = team?.balls || 0;
  if (balls <= 0) return 0;
  const totalBalls = (totalOvers || 20) * 6;
  return Math.round(((team.score || 0) / balls) * totalBalls);
}
function ballsLeftIn(team, totalOvers) {
  return Math.max(0, ((totalOvers || 20) * 6) - (team?.balls || 0));
}
function paintLogo(id, team) {
  const el = $(id);
  if (!el) return;
  const name = team?.name || "?";
  if (team?.logo) {
    el.style.backgroundImage = `url(${team.logo})`;
    el.textContent = "";
  } else {
    el.style.backgroundImage = "";
    el.textContent = name.slice(0, 2).toUpperCase();
  }
}
function paintLogoPreview(id, url) {
  const el = $(id);
  if (!el) return;
  const src = (url || "").trim();
  if (src) {
    el.src = src;
    el.hidden = false;
  } else {
    el.removeAttribute("src");
    el.hidden = true;
  }
}
function sr(runs, balls) {
  if (!balls) return "0.00";
  return ((runs / balls) * 100).toFixed(2);
}
function econ(runs, balls) {
  if (!balls) return "0.00";
  return ((runs / balls) * 6).toFixed(2);
}
function updateNdPlayerStats(s) {
  const bat = s.battingTeam === "A" ? s.teamA : s.teamB;
  const bowl = s.battingTeam === "A" ? s.teamB : s.teamA;
  const striker = findPlayer(bat.batsmen, bat.strikerId);
  const non = findPlayer(bat.batsmen, bat.nonStrikerId);
  const bowler = findPlayer(bowl.bowlers, bowl.currentBowlerId);

  // Always keep name fields in sync with live strike (needed for swap)
  if ($("striker-name")) $("striker-name").value = striker?.name || "";
  if ($("nonstriker-name")) $("nonstriker-name").value = non?.name || "";
  if ($("bowler-name") && bowler?.name) $("bowler-name").value = bowler.name;
  if ($("ck-striker-name")) $("ck-striker-name").textContent = striker?.name || "Set striker";
  if ($("ck-nonstriker-name")) $("ck-nonstriker-name").textContent = non?.name || "Set non-striker";
  if ($("ck-bowler-name")) $("ck-bowler-name").textContent = bowler?.name || "Set bowler";

  if ($("stat-striker")) $("stat-striker").textContent = striker ? `${striker.runs} (${striker.balls})` : "0 (0)";
  if ($("stat-nonstriker")) $("stat-nonstriker").textContent = non ? `${non.runs} (${non.balls})` : "0 (0)";
  if ($("st-4s")) $("st-4s").textContent = `4s: ${striker?.fours ?? 0}`;
  if ($("st-6s")) $("st-6s").textContent = `6s: ${striker?.sixes ?? 0}`;
  if ($("st-sr")) $("st-sr").textContent = `SR: ${striker ? sr(striker.runs, striker.balls) : "0"}`;
  if ($("ns-4s")) $("ns-4s").textContent = `4s: ${non?.fours ?? 0}`;
  if ($("ns-6s")) $("ns-6s").textContent = `6s: ${non?.sixes ?? 0}`;
  if ($("ns-sr")) $("ns-sr").textContent = `SR: ${non ? sr(non.runs, non.balls) : "0"}`;

  if ($("av-striker")) $("av-striker").textContent = "★";
  if ($("av-nonstriker")) $("av-nonstriker").textContent = "NS";

  if ($("stat-bowler") && bowler) {
    const overs = `${Math.floor((bowler.balls || 0) / 6)}.${(bowler.balls || 0) % 6}`;
    $("stat-bowler").textContent = `${overs} - ${bowler.maidens ?? 0} - ${bowler.runs ?? 0} - ${bowler.wickets ?? 0}`;
    if ($("bw-econ")) $("bw-econ").textContent = `Econ: ${econ(bowler.runs, bowler.balls)}`;
    if ($("bw-dots")) $("bw-dots").textContent = `Dots: ${bowler.dots ?? 0}`;
  }

  const pRuns = (striker?.runs || 0) + (non?.runs || 0);
  const pBalls = (striker?.balls || 0) + (non?.balls || 0);
  if ($("nd-partnership")) $("nd-partnership").textContent = `Partnership: ${pRuns} (${pBalls})`;

  const lo = s.lastOut;
  if ($("nd-last-out")) {
    $("nd-last-out").textContent = lo?.player
      ? `Last Out: ${lo.player} ${lo.runs}(${lo.balls}b ${lo.fours || 0}x4s ${lo.sixes || 0}x6s) ${lo.dismissal || ""}`
      : "Last Out: —";
  }

  const overRuns = (s.thisOver || []).reduce((n, b) => {
    if (b === "•" || b === "W") return n;
    if (typeof b === "string" && b.startsWith("WD")) return n + 1 + (parseInt(b.replace(/\D/g, ""), 10) || 0);
    if (b === "NB" || (typeof b === "string" && b.startsWith("NB"))) return n + 1;
    const num = parseInt(b, 10);
    return n + (Number.isFinite(num) ? num : 0);
  }, 0);
  if ($("this-over-runs")) $("this-over-runs").textContent = `${overRuns} runs`;
  if ($("squad-a-title")) $("squad-a-title").textContent = s.teamA?.name || "Team A";
  if ($("squad-b-title")) $("squad-b-title").textContent = s.teamB?.name || "Team B";
}

function renderSquads(s) {
  [["A", "squad-a-list"], ["B", "squad-b-list"]].forEach(([key, id]) => {
    const el = $(id);
    if (!el) return;
    const team = key === "A" ? s.teamA : s.teamB;
    const list = team?.squad || [];
    const outNames = new Set((team?.batsmen || []).filter((b) => b.out).map((b) => (b.name || "").toLowerCase()));
    const bowlCurrent = (team?.bowlers || []).find((p) => p.id === team?.currentBowlerId);
    el.innerHTML = list.map((p) => {
      const isOut = outNames.has((p.name || "").toLowerCase());
      const isBowl = bowlCurrent && (bowlCurrent.name || "").toLowerCase() === (p.name || "").toLowerCase();
      const selected = squadEdit?.team === key && squadEdit?.id === p.id;
      const av = p.avatar
        ? `<img class="sq-av-img" src="${esc(p.avatar)}" alt="" />`
        : `<span class="sq-av">${esc((p.name || "?").slice(0, 2).toUpperCase())}</span>`;
      return `
      <li data-id="${esc(p.id)}" data-squad-team="${key}" class="${isOut ? "is-out" : ""} ${selected ? "is-selected" : ""}">
        ${av}
        <button type="button" class="sq-pick" data-edit-squad="${key}" data-pid="${esc(p.id)}">
          <span>${esc(p.name)}</span>
          <small>${esc((p.role || "batsman").toUpperCase())}</small>
        </button>
        ${isOut ? `<em class="sq-out">OUT</em>` : ""}
        ${isBowl ? `<em class="sq-bowl">BOWLING</em>` : ""}
        <button type="button" class="sq-show" title="Show player card" data-show-squad-card="${key}" data-pid="${esc(p.id)}">▶</button>
        <button type="button" data-remove-squad="${key}" data-pid="${esc(p.id)}">✕</button>
      </li>`;
    }).join("") || `<li style="opacity:.6">No players yet</li>`;
  });
}

let squadEdit = null; // { team, id }

function findSquadPlayerLocal(teamKey, playerId) {
  const team = teamKey === "B" ? state?.teamB : state?.teamA;
  return (team?.squad || []).find((p) => p.id === playerId) || null;
}

function openSquadEditor(teamKey, playerId) {
  const p = findSquadPlayerLocal(teamKey, playerId);
  if (!p) return;
  squadEdit = { team: teamKey, id: playerId };
  const ed = $("squad-editor");
  if (!ed) return;
  ed.hidden = false;
  $("squad-ed-title").textContent = `${teamKey === "B" ? (state?.teamB?.name || "Team B") : (state?.teamA?.name || "Team A")} · ${p.name}`;
  $("squad-ed-name").value = p.name || "";
  $("squad-ed-role").value = p.role || "batsman";
  $("squad-ed-bat-style").value = p.battingStyle || "";
  $("squad-ed-bowl-style").value = p.bowlingStyle || "";
  $("squad-ed-avatar").value = p.avatar || "";
  paintLogoPreview("squad-ed-avatar-preview", p.avatar);
  const h = p.history || {};
  $("squad-ed-matches").value = h.matches ?? 0;
  $("squad-ed-runs").value = h.runs ?? 0;
  $("squad-ed-wickets").value = h.wickets ?? 0;
  $("squad-ed-avg").value = h.average || "";
  $("squad-ed-best").value = h.best || "";
  $("squad-ed-sr").value = h.strikeRate || "";
  renderSquads(state);
}

function closeSquadEditor() {
  squadEdit = null;
  if ($("squad-editor")) $("squad-editor").hidden = true;
  if (state) renderSquads(state);
}

async function saveSquadEditor() {
  if (!squadEdit) return;
  const data = await doAction({
    type: "update_squad",
    team: squadEdit.team,
    playerId: squadEdit.id,
    player: {
      name: $("squad-ed-name").value.trim(),
      role: $("squad-ed-role").value,
      battingStyle: $("squad-ed-bat-style").value.trim(),
      bowlingStyle: $("squad-ed-bowl-style").value.trim(),
      avatar: $("squad-ed-avatar").value.trim(),
      history: {
        matches: parseInt($("squad-ed-matches").value, 10) || 0,
        runs: parseInt($("squad-ed-runs").value, 10) || 0,
        wickets: parseInt($("squad-ed-wickets").value, 10) || 0,
        average: $("squad-ed-avg").value.trim(),
        best: $("squad-ed-best").value.trim(),
        strikeRate: $("squad-ed-sr").value.trim(),
      },
    },
  });
  applyState(data.state);
  openSquadEditor(squadEdit.team, squadEdit.id);
}

async function showSquadPlayerCard(teamKey, playerId) {
  const p = findSquadPlayerLocal(teamKey, playerId);
  if (!p) return;
  await api("/animate", {
    method: "POST",
    body: JSON.stringify({
      animation: "player_card",
      payload: {
        playerId: p.id,
        playerName: p.name,
        team: teamKey,
        mode: "history",
      },
    }),
  });
}

const DEFAULT_FIELD = [
  { id: "keeper", label: "WK", x: 50, y: 78 },
  { id: "slip", label: "Slip", x: 58, y: 72 },
  { id: "gully", label: "Gully", x: 66, y: 68 },
  { id: "point", label: "Point", x: 78, y: 52 },
  { id: "cover", label: "Cover", x: 72, y: 38 },
  { id: "mid-off", label: "Mid Off", x: 58, y: 28 },
  { id: "mid-on", label: "Mid On", x: 42, y: 28 },
  { id: "midwicket", label: "Mid Wicket", x: 28, y: 38 },
  { id: "square-leg", label: "Square Leg", x: 22, y: 52 },
  { id: "fine-leg", label: "Fine Leg", x: 28, y: 72 },
  { id: "third-man", label: "Third Man", x: 82, y: 72 },
  { id: "bowler", label: "Bowler", x: 50, y: 18 },
];

let fieldEditorSnapshot = "";
let fieldDragging = false;
let fieldAssignSlot = "";

function bowlingTeamFrom(s) {
  return s?.battingTeam === "A" ? s.teamB : s.teamA;
}

function bowlingSquadNames(s) {
  const bowl = bowlingTeamFrom(s);
  const fromSquad = (bowl?.squad || []).map((p) => p.name).filter(Boolean);
  const fromBowl = (bowl?.bowlers || []).map((p) => p.name).filter(Boolean);
  return [...new Set([...fromSquad, ...fromBowl])];
}

function renderFieldEditor(s) {
  const oval = $("field-oval");
  const list = $("field-list");
  if (!oval || !list) return;
  if (fieldDragging) return;
  if (document.activeElement && document.activeElement.closest?.("#field-list, #field-oval, #field-zone, #field-bowl-list")) return;

  const positions = (s.fieldPositions && s.fieldPositions.length) ? s.fieldPositions : DEFAULT_FIELD;
  const bowlNames = bowlingSquadNames(s);
  const snap = JSON.stringify({ z: s.fieldZone || "Standard", p: positions, n: bowlNames, slot: fieldAssignSlot });
  if (snap === fieldEditorSnapshot && oval.querySelector(".nd-fielder")) {
    return;
  }
  fieldEditorSnapshot = snap;

  if ($("field-zone") && s.fieldZone) $("field-zone").value = s.fieldZone;

  const bowlTitle = $("field-bowl-title");
  if (bowlTitle) bowlTitle.textContent = `${bowlingTeamFrom(s)?.name || "Bowling team"} — bowling squad`;
  const bowlList = $("field-bowl-list");
  if (bowlList) {
    const currentBowler = findPlayer(bowlingTeamFrom(s)?.bowlers, bowlingTeamFrom(s)?.currentBowlerId)?.name || "";
    bowlList.innerHTML = bowlNames.map((name) => `
      <button type="button" class="nd-bowl-chip${currentBowler && name.toLowerCase() === currentBowler.toLowerCase() ? " is-current" : ""}" data-bowl-name="${esc(name)}">
        ${esc(name)}${currentBowler && name.toLowerCase() === currentBowler.toLowerCase() ? " · bowl" : ""}
      </button>`).join("") || `<span class="muted">Add bowling-team players in Squad Settings</span>`;
  }

  oval.innerHTML = `<div class="nd-field-ring"></div><div class="nd-field-pitch"></div>` + positions.map((p) => `
    <button type="button" class="nd-fielder${fieldAssignSlot === p.id ? " is-active" : ""}" data-id="${esc(p.id)}" data-label="${esc(p.label || p.id)}"
      style="left:${Number(p.x) || 50}%;top:${Number(p.y) || 50}%">
      <i></i>
      <span class="nd-fielder-player">${esc(p.player || "")}</span>
      <em>${esc(p.label || p.id)}</em>
    </button>`).join("");

  list.innerHTML = positions.map((p) => `
    <li class="${fieldAssignSlot === p.id ? "is-active" : ""}">
      <button type="button" class="nd-field-slot" data-field-slot="${esc(p.id)}">${esc(p.label || p.id)}</button>
      <select data-field-player="${esc(p.id)}">
        <option value="">— pick player —</option>
        ${bowlNames.map((name) => `<option value="${esc(name)}" ${p.player === name ? "selected" : ""}>${esc(name)}</option>`).join("")}
      </select>
    </li>`).join("");

  oval.querySelectorAll(".nd-fielder").forEach(bindFieldDrag);
}

function bindFieldDrag(dot) {
  if (dot.dataset.dragBound) return;
  dot.dataset.dragBound = "1";
  dot.addEventListener("pointerdown", (e) => {
    e.preventDefault();
    const oval = $("field-oval");
    fieldDragging = true;
    dot.setPointerCapture(e.pointerId);
    const move = (ev) => {
      const r = oval.getBoundingClientRect();
      const x = Math.max(4, Math.min(96, ((ev.clientX - r.left) / r.width) * 100));
      const y = Math.max(4, Math.min(96, ((ev.clientY - r.top) / r.height) * 100));
      dot.style.left = `${x}%`;
      dot.style.top = `${y}%`;
    };
    const up = () => {
      fieldDragging = false;
      dot.removeEventListener("pointermove", move);
      dot.removeEventListener("pointerup", up);
    };
    dot.addEventListener("pointermove", move);
    dot.addEventListener("pointerup", up);
  });
}

function collectFieldPositions() {
  return [...document.querySelectorAll("#field-oval .nd-fielder")].map((dot) => ({
    id: dot.dataset.id,
    label: dot.dataset.label,
    x: parseFloat(dot.style.left) || 50,
    y: parseFloat(dot.style.top) || 50,
    player: document.querySelector(`[data-field-player="${dot.dataset.id}"]`)?.value.trim() || "",
  }));
}

async function saveFieldPositions(positions, zone) {
  const data = await api("/patch", {
    method: "POST",
    body: JSON.stringify({
      fieldZone: zone || $("field-zone")?.value || "Standard",
      fieldPositions: positions || collectFieldPositions(),
    }),
  });
  fieldEditorSnapshot = "";
  applyState(data.state);
}

function fillFieldFromSquad() {
  if (!state) return;
  const names = bowlingSquadNames(state);
  const bowl = bowlingTeamFrom(state);
  const bowler = findPlayer(bowl?.bowlers, bowl?.currentBowlerId)?.name || "";
  let i = 0;
  document.querySelectorAll("#field-oval .nd-fielder").forEach((dot) => {
    let name = "";
    if (dot.dataset.id === "bowler" && bowler) {
      name = bowler;
    } else {
      while (i < names.length && names[i] === bowler) i += 1;
      name = names[i++] || "";
    }
    const sel = document.querySelector(`[data-field-player="${dot.dataset.id}"]`);
    if (sel) sel.value = name;
    const label = dot.querySelector(".nd-fielder-player");
    if (label) label.textContent = name;
  });
}

function assignBowlNameToSlot(name) {
  const slot = fieldAssignSlot || "bowler";
  const sel = document.querySelector(`[data-field-player="${slot}"]`);
  if (sel) sel.value = name;
  const label = document.querySelector(`#field-oval .nd-fielder[data-id="${slot}"] .nd-fielder-player`);
  if (label) label.textContent = name;
}

document.addEventListener("change", (e) => {
  const input = e.target.closest?.("[data-field-player]");
  if (!input) return;
  const dot = document.querySelector(`#field-oval .nd-fielder[data-id="${input.dataset.fieldPlayer}"]`);
  const label = dot?.querySelector(".nd-fielder-player");
  if (label) label.textContent = input.value;
});
document.addEventListener("click", (e) => {
  const slotBtn = e.target.closest?.("[data-field-slot]");
  if (slotBtn) {
    fieldAssignSlot = slotBtn.dataset.fieldSlot;
    document.querySelectorAll(".nd-fielder").forEach((d) => d.classList.toggle("is-active", d.dataset.id === fieldAssignSlot));
    document.querySelectorAll(".nd-field-list li").forEach((li) => {
      li.classList.toggle("is-active", li.querySelector("[data-field-slot]")?.dataset.fieldSlot === fieldAssignSlot);
    });
    return;
  }
  const fielder = e.target.closest?.("#field-oval .nd-fielder");
  if (fielder && !fieldDragging) {
    fieldAssignSlot = fielder.dataset.id;
    document.querySelectorAll(".nd-fielder").forEach((d) => d.classList.toggle("is-active", d.dataset.id === fieldAssignSlot));
    document.querySelectorAll(".nd-field-list li").forEach((li) => {
      li.classList.toggle("is-active", li.querySelector("[data-field-slot]")?.dataset.fieldSlot === fieldAssignSlot);
    });
    return;
  }
  const chip = e.target.closest?.("[data-bowl-name]");
  if (chip) {
    assignBowlNameToSlot(chip.dataset.bowlName);
  }
});

$("btn-field-save")?.addEventListener("click", () => saveFieldPositions());
$("btn-field-fill")?.addEventListener("click", () => fillFieldFromSquad());
$("btn-field-reset")?.addEventListener("click", () => saveFieldPositions(DEFAULT_FIELD.map((p) => ({ ...p, player: "" })), "Standard"));
$("field-zone")?.addEventListener("change", () => saveFieldPositions());

function renderCommentary(s) {
  const el = $("commentary-list");
  if (!el) return;
  const list = s.commentary || [];
  el.innerHTML = list.map((c) => `
    <li><span class="c-over">${esc(c.over || "")}</span>${esc(c.text)}</li>
  `).join("") || `<li style="opacity:.6">No commentary yet</li>`;
}

function drawCharts(s) {
  const log = s.ballLog || [];
  drawSimpleChart("chart-manhattan", buildManhattan(log), "#22c55e");
  drawSimpleChart("chart-worm", buildWorm(log), "#38bdf8");
  drawSimpleChart("chart-rr", buildRr(log), "#f97316");
  drawSimpleChart("chart-partnership", buildPartnershipBars(s), "#a855f7");
}
function buildManhattan(log) {
  const overs = {};
  log.forEach((b) => {
    const o = Math.floor((b.balls || 1) / 6);
    overs[o] = (overs[o] || 0) + (b.runs || 0);
  });
  return Object.keys(overs).sort((a, b) => a - b).map((k) => overs[k]);
}
function buildWorm(log) {
  return log.filter((b) => b.team === (state?.battingTeam || "A") || true).map((b) => b.score || 0);
}
function buildRr(log) {
  return log.map((b) => {
    const balls = Math.max(1, b.balls || 1);
    return ((b.score || 0) / balls) * 6;
  });
}
function buildPartnershipBars(s) {
  const bat = s.battingTeam === "A" ? s.teamA : s.teamB;
  const outs = (bat?.batsmen || []).filter((b) => b.out);
  return outs.map((b) => b.runs || 0).concat([
    ((findPlayer(bat?.batsmen, bat?.strikerId)?.runs || 0) + (findPlayer(bat?.batsmen, bat?.nonStrikerId)?.runs || 0)),
  ]);
}
function drawSimpleChart(canvasId, values, color) {
  const canvas = $(canvasId);
  if (!canvas) return;
  const ctx = canvas.getContext("2d");
  const w = canvas.width = canvas.clientWidth || 400;
  const h = canvas.height = 180;
  ctx.fillStyle = "#0f172a";
  ctx.fillRect(0, 0, w, h);
  if (!values.length) {
    ctx.fillStyle = "#64748b";
    ctx.fillText("No data yet", 16, 24);
    return;
  }
  const max = Math.max(...values, 1);
  const barW = Math.max(2, (w - 20) / values.length - 2);
  values.forEach((v, i) => {
    const bh = (v / max) * (h - 30);
    ctx.fillStyle = color;
    ctx.fillRect(10 + i * (barW + 2), h - 10 - bh, barW, bh);
  });
}

/* Tabs */
document.querySelectorAll(".nd-tab").forEach((tab) => {
  tab.addEventListener("click", () => {
    document.querySelectorAll(".nd-tab").forEach((t) => t.classList.remove("active"));
    tab.classList.add("active");
    const id = tab.dataset.tab;
    ["center", "scorecard", "graphs", "commentary"].forEach((name) => {
      const panel = $(`tab-${name}`);
      if (!panel) return;
      const on = name === id;
      panel.hidden = !on;
      panel.classList.toggle("active", on);
    });
    if (id === "graphs" && state) drawCharts(state);
  });
});

/* Undo / penalty / walkover */
$("btn-undo")?.addEventListener("click", () => doAction({ type: "undo" }));
$("btn-walkover")?.addEventListener("click", () => {
  if (confirm("Mark walkover / abandon match?")) {
    doAction({ type: "walkover", winner: state?.battingTeam === "A" ? "B" : "A" });
  }
});

document.querySelectorAll('.pad-btn[data-action="penalty"]').forEach((btn) => {
  btn.addEventListener("click", () => doAction({ type: "penalty", runs: parseInt(btn.dataset.runs, 10) || 5 }));
});

/* Squad add/remove + library import/save */
let teamLibrary = [];

async function teamsApi(path = "", options = {}) {
  const headers = {
    Accept: "application/json",
    "X-CSRF-TOKEN": csrf,
    "X-Requested-With": "XMLHttpRequest",
    ...(options.headers || {}),
  };
  if (options.body && !headers["Content-Type"]) {
    headers["Content-Type"] = "application/json";
  }
  const res = await fetch(`/api/teams${path}`, { ...options, headers });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || data.message || res.statusText);
  return data;
}

function fillTeamImportSelects() {
  ["import-team-a", "import-team-b"].forEach((id) => {
    const sel = $(id);
    if (!sel) return;
    const current = sel.value;
    sel.innerHTML = `<option value="">Import saved team…</option>` +
      teamLibrary.map((t) =>
        `<option value="${t.id}">${esc(t.name)} (${t.playerCount || 0})</option>`
      ).join("");
    if (current) sel.value = current;
  });
}

async function refreshTeamLibrary() {
  try {
    const tid = state?.tournamentId;
    const q = tid ? `?tournament_id=${encodeURIComponent(tid)}` : "";
    const data = await teamsApi(q);
    teamLibrary = data.teams || [];
    fillTeamImportSelects();
  } catch (e) {
    console.error(e);
  }
}

async function importSavedTeam(side) {
  const sel = $(side === "B" ? "import-team-b" : "import-team-a");
  const teamId = parseInt(sel?.value || "0", 10);
  if (!teamId) {
    alert("Choose a saved team to import.");
    return;
  }
  const team = teamLibrary.find((t) => Number(t.id) === teamId);
  const label = team?.name || "this team";
  if (!confirm(`Import “${label}” into ${side === "B" ? (state?.teamB?.name || "Team B") : (state?.teamA?.name || "Team A")}?\n\nThis replaces the current squad for that side.`)) {
    return;
  }
  await doAction({ type: "import_team", team: side, teamId });
  await refreshTeamLibrary();
}

async function saveTeamToLibrary(side) {
  const key = side === "B" ? "teamB" : "teamA";
  const team = state?.[key];
  if (!team?.name) {
    alert("Set the team name first (Match Info).");
    return;
  }
  if (!(team.squad || []).length) {
    alert("Add at least one player before saving the team.");
    return;
  }
  await doAction({ type: "save_team", team: side });
  await refreshTeamLibrary();
  alert(`Saved “${team.name}” to the team library.`);
}

async function addSquad(team) {
  const input = $(team === "A" ? "squad-a-input" : "squad-b-input");
  const name = input?.value.trim();
  if (!name) return;
  const data = await doAction({ type: "add_squad", team, name });
  if (input) input.value = "";
  refreshTeamLibrary();
  return data;
}
$("btn-add-squad-a")?.addEventListener("click", () => addSquad("A"));
$("btn-add-squad-b")?.addEventListener("click", () => addSquad("B"));
$("btn-import-team-a")?.addEventListener("click", () => importSavedTeam("A"));
$("btn-import-team-b")?.addEventListener("click", () => importSavedTeam("B"));
$("btn-save-team-a")?.addEventListener("click", () => saveTeamToLibrary("A"));
$("btn-save-team-b")?.addEventListener("click", () => saveTeamToLibrary("B"));
document.addEventListener("click", (e) => {
  const editBtn = e.target.closest("[data-edit-squad]");
  if (editBtn) {
    openSquadEditor(editBtn.dataset.editSquad, editBtn.dataset.pid);
    return;
  }
  const showBtn = e.target.closest("[data-show-squad-card]");
  if (showBtn) {
    showSquadPlayerCard(showBtn.dataset.showSquadCard, showBtn.dataset.pid);
    return;
  }
  const btn = e.target.closest("[data-remove-squad]");
  if (!btn) return;
  if (squadEdit && squadEdit.id === btn.dataset.pid && squadEdit.team === btn.dataset.removeSquad) {
    closeSquadEditor();
  }
  doAction({ type: "remove_squad", team: btn.dataset.removeSquad, playerId: btn.dataset.pid }).then(() => refreshTeamLibrary());
});

$("btn-squad-ed-close")?.addEventListener("click", () => closeSquadEditor());
$("btn-squad-ed-save")?.addEventListener("click", async () => {
  await saveSquadEditor();
  refreshTeamLibrary();
});
$("btn-squad-ed-show")?.addEventListener("click", async () => {
  if (!squadEdit) return;
  await saveSquadEditor();
  await showSquadPlayerCard(squadEdit.team, squadEdit.id);
  refreshTeamLibrary();
});
$("squad-ed-avatar")?.addEventListener("input", () => {
  paintLogoPreview("squad-ed-avatar-preview", $("squad-ed-avatar").value);
});
$("squad-ed-avatar-file")?.addEventListener("change", async () => {
  const file = $("squad-ed-avatar-file")?.files?.[0];
  if (!file) return;
  try {
    const url = await uploadLogoFile("player", file);
    if (url && $("squad-ed-avatar")) {
      $("squad-ed-avatar").value = url;
      paintLogoPreview("squad-ed-avatar-preview", url);
    }
  } catch (err) {
    alert(err.message || "Avatar upload failed");
  } finally {
    if ($("squad-ed-avatar-file")) $("squad-ed-avatar-file").value = "";
  }
});

/* Commentary */
$("btn-add-commentary")?.addEventListener("click", async () => {
  const text = $("commentary-input")?.value.trim();
  if (!text) return;
  await doAction({ type: "commentary", text });
  if ($("commentary-input")) $("commentary-input").value = "";
});

/* Toss / colors / toggles save via patch */
$("btn-save-toss")?.addEventListener("click", async () => {
  const winner = $("toss-winner")?.value.trim() || "";
  const decision = $("toss-decision")?.value || "bat";
  if (!winner) {
    alert("Enter toss winner.");
    return;
  }
  const data = await api("/patch", {
    method: "POST",
    body: JSON.stringify({
      toss: { winner, decision, text: `'${winner}' won the toss and elected to ${decision}` },
    }),
  });
  applyState(data.state);
});

/* Mandatory toss gate on score open */
$("toss-gate-a")?.addEventListener("click", () => {
  tossGateWinner = "A";
  $("toss-gate-a")?.classList.add("active");
  $("toss-gate-b")?.classList.remove("active");
  updateTossGatePreview();
});
$("toss-gate-b")?.addEventListener("click", () => {
  tossGateWinner = "B";
  $("toss-gate-b")?.classList.add("active");
  $("toss-gate-a")?.classList.remove("active");
  updateTossGatePreview();
});
$("toss-gate-bat")?.addEventListener("click", () => {
  tossGateDecision = "bat";
  $("toss-gate-bat")?.classList.add("active");
  $("toss-gate-bowl")?.classList.remove("active");
  updateTossGatePreview();
});
$("toss-gate-bowl")?.addEventListener("click", () => {
  tossGateDecision = "bowl";
  $("toss-gate-bowl")?.classList.add("active");
  $("toss-gate-bat")?.classList.remove("active");
  updateTossGatePreview();
});
$("btn-toss-gate-save")?.addEventListener("click", async () => {
  if (!tossGateWinner || !tossGateDecision) {
    alert("Select toss winner and bat/bowl.");
    return;
  }
  const winner = tossGateWinner === "B"
    ? (state?.teamB?.name || "Team B")
    : (state?.teamA?.name || "Team A");
  const btn = $("btn-toss-gate-save");
  if (btn) {
    btn.disabled = true;
    btn.textContent = "Saving…";
  }
  try {
    const data = await api("/patch", {
      method: "POST",
      body: JSON.stringify({
        toss: {
          winner,
          decision: tossGateDecision,
          text: `'${winner}' won the toss and elected to ${tossGateDecision}`,
        },
      }),
    });
    version = data.version;
    applyState(data.state);
    // Show toss on overlay when auto graphics on
    if (data.state?.autoGraphics !== false) {
      await api("/animate", { method: "POST", body: JSON.stringify({ animation: "toss", payload: {} }) });
    }
  } catch (e) {
    alert(e.message || "Could not save toss");
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = "Save Toss";
    }
  }
});
// Prevent backdrop dismiss
$("toss-gate-modal")?.addEventListener("click", (e) => {
  if (e.target === $("toss-gate-modal")) e.stopPropagation();
});

async function patchFlags(partial) {
  const data = await api("/patch", { method: "POST", body: JSON.stringify(partial) });
  applyState(data.state);
}
$("auto-gfx-toggle")?.addEventListener("change", (e) => patchFlags({ autoGraphics: e.target.checked }));
$("auto-loop-toggle")?.addEventListener("change", (e) => patchFlags({ autoLoop: e.target.checked }));
$("boundaries-counter-toggle")?.addEventListener("change", (e) => patchFlags({ showBoundariesCounter: e.target.checked }));
$("show-logo-toggle")?.addEventListener("change", (e) => patchFlags({ showLogo: e.target.checked }));
$("team-a-color")?.addEventListener("change", (e) => patchFlags({ teamA: { primaryColor: e.target.value } }));
$("team-b-color")?.addEventListener("change", (e) => patchFlags({ teamB: { primaryColor: e.target.value } }));

$("btn-custom-msg")?.addEventListener("click", async () => {
  const text = $("custom-message")?.value || "Custom message";
  await patchFlags({ customMessage: text });
  await api("/animate", { method: "POST", body: JSON.stringify({ animation: "custom", payload: { text } }) });
});
$("btn-show-commentator")?.addEventListener("click", async () => {
  const text = $("commentator")?.value || "Commentator";
  await patchFlags({ commentator: text });
  await api("/animate", {
    method: "POST",
    body: JSON.stringify({
      animation: "commentator",
      payload: { commentator: text, text, organizerLogo: state?.organizerLogo || "" },
    }),
  });
});
$("btn-clear-screen")?.addEventListener("click", () => {
  stopMatchStarter();
  api("/animate", { method: "POST", body: JSON.stringify({ animation: "custom", payload: { text: " " } }) });
});
$("btn-match-starter")?.addEventListener("click", () => runMatchStarter());
$("btn-match-starter-panel")?.addEventListener("click", () => runMatchStarter());

/* Extend save meta with colors */
const _saveMeta = $("btn-save-meta");
if (_saveMeta) {
  const clone = _saveMeta.cloneNode(true);
  _saveMeta.parentNode.replaceChild(clone, _saveMeta);
  clone.addEventListener("click", async () => {
    const data = await api("/patch", {
      method: "POST",
      body: JSON.stringify({
        matchTitle: $("match-title").value,
        matchNo: $("match-no").value,
        totalOvers: parseInt($("total-overs").value, 10) || 20,
        battingTeam: $("batting-a").checked ? "A" : "B",
        visible: $("visible-toggle").checked,
        powerplay: $("powerplay-toggle").checked,
        teamA: {
          name: $("team-a-name").value,
          primaryColor: $("team-a-color")?.value,
          logo: $("team-a-logo")?.value || "",
        },
        teamB: {
          name: $("team-b-name").value,
          primaryColor: $("team-b-color")?.value,
          logo: $("team-b-logo")?.value || "",
        },
        organizerLogo: $("organizer-logo")?.value || "",
        streamerLogo: $("streamer-logo")?.value || "",
        playerAvatar: $("player-avatar")?.value || "",
        commentator: $("commentator")?.value || "",
      }),
    });
    applyState(data.state);
  });
}

/* Player picker modal */
let pickerRole = "striker";
function openPicker(role) {
  pickerRole = role;
  const modal = $("player-modal");
  if (!modal) return;
  $("player-modal-title").textContent = role === "bowler" ? "Select Bowler" : "Select Batsman";
  fillPickerList();
  modal.hidden = false;
}
function fillPickerList(filter = "") {
  const list = $("player-modal-list");
  if (!list || !state) return;
  const team = pickerRole === "bowler"
    ? (state.battingTeam === "A" ? state.teamB : state.teamA)
    : (state.battingTeam === "A" ? state.teamA : state.teamB);
  const squad = team?.squad || [];
  const q = filter.toLowerCase();
  const currentBowler = (team?.bowlers || []).find((p) => p.id === team?.currentBowlerId);
  const names = (squad.length
    ? squad.map((p) => p.name)
    : [
        ...(team?.batsmen || []).map((b) => b.name),
        ...(team?.bowlers || []).map((b) => b.name),
      ]
  ).filter(Boolean);
  const unique = [...new Set(names.map((n) => String(n).trim()).filter(Boolean))];
  list.innerHTML = unique
    .filter((name) => !q || name.toLowerCase().includes(q))
    .map((name) => {
      const live = (team?.batsmen || []).find((b) => (b.name || "").toLowerCase() === name.toLowerCase());
      const isOut = pickerRole !== "bowler" && !!live?.out;
      const isBowling = pickerRole === "bowler" && currentBowler && (currentBowler.name || "").toLowerCase() === name.toLowerCase();
      const tags = [
        isOut ? `<em class="pick-out">OUT</em>` : "",
        isBowling ? `<em class="pick-bowl">CURRENT</em>` : "",
      ].join("");
      return `<li data-pick="${esc(name)}" class="${isOut ? "is-out" : ""}">
        <span>${esc(name)}${tags}</span>
        <span>${isOut ? "Select anyway" : "Select"}</span>
      </li>`;
    })
    .join("") || `<li style="opacity:.6">${pickerRole === "bowler" ? "Add bowlers in Squad Settings" : "Add batsmen in Squad Settings"}</li>`;
}
$("btn-pick-striker")?.addEventListener("click", () => openPicker("striker"));
$("btn-pick-nonstriker")?.addEventListener("click", () => openPicker("nonstriker"));
$("btn-pick-bowler")?.addEventListener("click", () => openPicker("bowler"));
$("player-modal-close")?.addEventListener("click", () => { $("player-modal").hidden = true; });
$("player-modal-search")?.addEventListener("input", (e) => fillPickerList(e.target.value));
$("player-modal-list")?.addEventListener("click", (e) => {
  const li = e.target.closest("[data-pick]");
  if (!li) return;
  const name = li.dataset.pick;
  if (pickerRole === "striker") $("striker-name").value = name;
  if (pickerRole === "nonstriker") $("nonstriker-name").value = name;
  if (pickerRole === "bowler") $("bowler-name").value = name;
  $("player-modal").hidden = true;
  $("btn-set-players")?.click();
});
$("player-modal-add")?.addEventListener("click", async () => {
  const name = $("player-modal-new")?.value.trim();
  if (!name) return;
  const team = pickerRole === "bowler"
    ? (state?.battingTeam === "A" ? "B" : "A")
    : (state?.battingTeam === "A" ? "A" : "B");
  await doAction({ type: "add_squad", team, name });
  $("player-modal-new").value = "";
  fillPickerList($("player-modal-search")?.value || "");
});

/* Patch pad handler for penalty if not caught */
document.querySelectorAll(".pad-btn").forEach((btn) => {
  if (btn.dataset.action !== "penalty") return;
  // already bound above
});

/* Logo file uploads → Match Info branding */
document.querySelectorAll(".logo-file").forEach((input) => {
  input.addEventListener("change", async () => {
    const file = input.files?.[0];
    if (!file) return;
    const slot = input.dataset.slot || "organizer";
    const targetId = input.dataset.target;
    try {
      const url = await uploadLogoFile(slot, file);
      if (url && targetId && $(targetId)) $(targetId).value = url;
    } catch (err) {
      alert(err.message || "Logo upload failed");
    } finally {
      input.value = "";
    }
  });
});

if (window.CricketRealtime) {
  realtime = new CricketRealtime({
    room: roomId,
    hydrateUrl: `/api/matches/${encodeURIComponent(roomId)}`,
    pollWhenLiveMs: 2000,
    pollWhenOfflineMs: 800,
    onState: (s, ver) => {
      version = ver || version;
      applyState(s);
    },
    onStatus: (status) => {
      if (status === "live") setStatus(true, "Live (Pusher)");
      else if (status === "connecting" || status === "reconnecting") setStatus(false, "Connecting…");
      else setStatus(false, "Pusher offline");
    },
  });
  realtime.start();
} else {
  refresh();
}
refreshTeamLibrary();

