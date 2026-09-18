/**
 * Control panel client — modern dashboard UI, live hero scores, overlay preview.
 */

const socket = io();

let roomId = null;
let state = null;
let syncingFromServer = false;

const $ = (id) => document.getElementById(id);

function getRoomFromUrl() {
  const params = new URLSearchParams(window.location.search);
  return params.get("room")?.trim() || "";
}

function updateOverlayUrl() {
  if (!roomId) return;
  const url = `${window.location.origin}/overlay.html?room=${encodeURIComponent(roomId)}`;
  $("overlay-url").value = url;
}

function setConnectionStatus(connected) {
  const el = $("connection-status");
  el.textContent = connected ? "Connected" : "Disconnected";
  el.className = `status-pill ${connected ? "status-connected" : "status-disconnected"}`;
}

function connectToRoom(id) {
  const trimmed = (id || "").trim();
  if (!trimmed) {
    alert("Enter a room id (e.g. match1)");
    return;
  }
  roomId = trimmed;
  $("current-room").textContent = roomId;
  $("room-input").value = roomId;
  updateOverlayUrl();
  socket.emit("join-room", roomId);
}

function sendPatch(patch) {
  if (!roomId) return;
  socket.emit("update-state", { room: roomId, patch });
}

function teamFields(prefix) {
  return {
    name: $(`${prefix}-name`).value,
    score: parseInt($(`${prefix}-score`).value, 10) || 0,
    wickets: parseInt($(`${prefix}-wickets`).value, 10) || 0,
    overs: $(`${prefix}-overs`).value || "0.0",
    batsman1: $(`${prefix}-batsman1`).value,
    batsman2: $(`${prefix}-batsman2`).value,
    bowler: $(`${prefix}-bowler`).value,
  };
}

function sendFormPatch() {
  if (!roomId || syncingFromServer) return;

  sendPatch({
    matchTitle: $("match-title").value,
    matchNo: $("match-no").value,
    teamA: teamFields("team-a"),
    teamB: teamFields("team-b"),
    battingTeam: $("batting-a").checked ? "A" : "B",
    result: $("result-text").value,
    visible: $("visible-toggle").checked,
  });
}

function applyTeamToForm(prefix, team) {
  $(`${prefix}-name`).value = team.name;
  $(`${prefix}-score`).value = team.score;
  $(`${prefix}-wickets`).value = team.wickets;
  $(`${prefix}-overs`).value = team.overs;
  $(`${prefix}-batsman1`).value = team.batsman1 || "";
  $(`${prefix}-batsman2`).value = team.batsman2 || "";
  $(`${prefix}-bowler`).value = team.bowler || "";
}

function applyStateToForm(s) {
  syncingFromServer = true;
  state = s;

  $("match-title").value = s.matchTitle;
  $("match-no").value = s.matchNo;
  applyTeamToForm("team-a", s.teamA);
  applyTeamToForm("team-b", s.teamB);
  $("batting-a").checked = s.battingTeam === "A";
  $("batting-b").checked = s.battingTeam === "B";
  $("result-text").value = s.result;
  $("visible-toggle").checked = s.visible;

  updatePreview(s);
  syncingFromServer = false;
}

function updateHero(s) {
  $("hero-a-name").textContent = s.teamA.name;
  $("hero-a-score").textContent = `${s.teamA.score}/${s.teamA.wickets}`;
  $("hero-a-overs").textContent = `(${s.teamA.overs} ov)`;
  $("hero-b-name").textContent = s.teamB.name;
  $("hero-b-score").textContent = `${s.teamB.score}/${s.teamB.wickets}`;
  $("hero-b-overs").textContent = `(${s.teamB.overs} ov)`;

  $("hero-team-a").classList.toggle("batting", s.battingTeam === "A");
  $("hero-team-b").classList.toggle("batting", s.battingTeam === "B");

  const resultEl = $("hero-result");
  if (s.result && s.result.trim()) {
    resultEl.textContent = `RESULT: ${s.result}`;
    resultEl.classList.add("has-result");
  } else {
    resultEl.textContent = "RESULT: Match in progress";
    resultEl.classList.remove("has-result");
  }
}

function updatePreview(s) {
  updateHero(s);

  $("mini-a").textContent = `${s.teamA.name.toUpperCase()} · ${s.teamA.score}-${s.teamA.wickets} (${s.teamA.overs})`;
  $("mini-b").textContent = `${s.teamB.name.toUpperCase()} · ${s.teamB.score}-${s.teamB.wickets} (${s.teamB.overs})`;

  $("preview-json").textContent = JSON.stringify(s, null, 2);
}

function addBall(oversStr) {
  const parts = (oversStr || "0.0").split(".");
  let overs = parseInt(parts[0], 10) || 0;
  let balls = parseInt(parts[1], 10) || 0;
  balls += 1;
  if (balls >= 6) {
    overs += 1;
    balls = 0;
  }
  return `${overs}.${balls}`;
}

function getTeamKey(team) {
  return team === "A" ? "teamA" : "teamB";
}

function getTeamFromState(team) {
  return team === "A" ? state.teamA : state.teamB;
}

function ballSymbol(action) {
  switch (action) {
    case "+1": return "1";
    case "+4": return "4";
    case "+6": return "6";
    case "+wicket": return "W";
    case "+ball": return "•";
    default: return null;
  }
}

function nextThisOver(current, symbol, oversStr) {
  const list = Array.isArray(current) ? [...current] : [];
  if (!symbol) return list;
  const parts = (oversStr || "0.0").split(".");
  const balls = parseInt(parts[1], 10) || 0;
  if (balls === 0 && list.length > 0) return [symbol];
  list.push(symbol);
  if (list.length >= 6 || balls === 0) return [];
  return list;
}

function applyQuickAction(team, action) {
  if (!state || !roomId) return;

  const key = getTeamKey(team);
  const t = { ...getTeamFromState(team) };
  const isBatting = state.battingTeam === team;
  let thisOver = state.thisOver;

  switch (action) {
    case "+1":
      t.score += 1;
      break;
    case "+4":
      t.score += 4;
      break;
    case "+6":
      t.score += 6;
      break;
    case "+wicket":
      t.wickets = Math.min(10, t.wickets + 1);
      break;
    case "-1":
      t.score = Math.max(0, t.score - 1);
      break;
    case "+ball":
      t.overs = addBall(t.overs);
      break;
    default:
      return;
  }

  if (isBatting) {
    const sym = ballSymbol(action);
    if (sym) {
      thisOver = nextThisOver(state.thisOver, sym, t.overs);
    }
  }

  const patch = { [key]: t };
  if (isBatting && thisOver !== state.thisOver) {
    patch.thisOver = thisOver;
  }
  sendPatch(patch);
}

socket.on("connect", () => {
  setConnectionStatus(true);
  if (roomId) socket.emit("join-room", roomId);
});

socket.on("disconnect", () => setConnectionStatus(false));

socket.on("state-update", applyStateToForm);

$("btn-connect").addEventListener("click", () => connectToRoom($("room-input").value));

document.querySelectorAll(".quick-buttons").forEach((group) => {
  const team = group.dataset.team;
  group.querySelectorAll("[data-action]").forEach((btn) => {
    btn.addEventListener("click", () => applyQuickAction(team, btn.dataset.action));
  });
});

const fieldIds = [
  "match-title", "match-no",
  "team-a-name", "team-a-score", "team-a-wickets", "team-a-overs",
  "team-a-batsman1", "team-a-batsman2", "team-a-bowler",
  "team-b-name", "team-b-score", "team-b-wickets", "team-b-overs",
  "team-b-batsman1", "team-b-batsman2", "team-b-bowler",
];

fieldIds.forEach((id) => {
  $(id).addEventListener("change", sendFormPatch);
});

$("batting-a").addEventListener("change", () => {
  if ($("batting-a").checked) sendPatch({ battingTeam: "A" });
});
$("batting-b").addEventListener("change", () => {
  if ($("batting-b").checked) sendPatch({ battingTeam: "B" });
});

$("visible-toggle").addEventListener("change", () => {
  sendPatch({ visible: $("visible-toggle").checked });
});

$("btn-set-result").addEventListener("click", () => {
  sendPatch({ result: $("result-text").value });
});

document.querySelectorAll("[data-anim]").forEach((btn) => {
  btn.addEventListener("click", () => {
    if (!roomId) return;
    socket.emit("trigger-animation", { room: roomId, animation: btn.dataset.anim });
  });
});

$("btn-copy-url").addEventListener("click", async () => {
  const input = $("overlay-url");
  try {
    await navigator.clipboard.writeText(input.value);
    $("btn-copy-url").textContent = "Copied!";
    setTimeout(() => { $("btn-copy-url").textContent = "Copy"; }, 1500);
  } catch {
    input.select();
    document.execCommand("copy");
  }
});

$("btn-reset").addEventListener("click", () => {
  if (!roomId) return;
  if (confirm(`Reset all state for room "${roomId}"? This cannot be undone.`)) {
    socket.emit("reset-room", roomId);
  }
});

const urlRoom = getRoomFromUrl();
if (urlRoom) {
  $("room-input").value = urlRoom;
  connectToRoom(urlRoom);
} else {
  $("room-input").value = "match1";
  updateOverlayUrl();
}
