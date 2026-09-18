/**
 * Cricket Live Overlay — WebSocket server
 *
 * Holds one in-memory state object per room (room id = match id).
 * All clients in a room receive state-update broadcasts; play-animation
 * is transient and only sent to overlay listeners in that room.
 */

const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const path = require("path");

const PORT = process.env.PORT || 3000;
const SAMPLE_ROOM = "match1";

const app = express();
const server = http.createServer(app);
const io = new Server(server);

// In-memory store: roomId -> state object (source of truth)
const rooms = {};

/** Default state for a new room */
function createDefaultState() {
  return {
    matchTitle: "Live Match",
    matchNo: "1",
    teamA: {
      name: "Team A",
      shortName: "TMA",
      score: 0,
      wickets: 0,
      overs: "0.0",
      batsman1: "",
      batsman2: "",
      bowler: "",
      primaryColor: "",
      secondaryColor: "",
      logo: "",
    },
    teamB: {
      name: "Team B",
      shortName: "TMB",
      score: 0,
      wickets: 0,
      overs: "0.0",
      batsman1: "",
      batsman2: "",
      bowler: "",
      primaryColor: "",
      secondaryColor: "",
      logo: "",
    },
    battingTeam: "A",
    target: null,
    result: "",
    visible: true,
    thisOver: [],
    powerplay: false,
    displayMode: "scoreboard",
  };
}

/** Returns existing room state or creates defaults on first join */
function getOrCreateRoom(roomId) {
  if (!rooms[roomId]) {
    rooms[roomId] = createDefaultState();
  }
  return rooms[roomId];
}

/**
 * Shallow-merge patch into room state. Nested teamA/teamB patches
 * are merged one level deep so partial team updates work.
 */
function applyPatch(state, patch) {
  for (const key of Object.keys(patch)) {
    if (key === "teamA" || key === "teamB") {
      state[key] = { ...state[key], ...patch[key] };
    } else {
      state[key] = patch[key];
    }
  }

  // Auto-set chase target when Team B is batting and Team A has a score
  if (state.battingTeam === "B" && state.teamA.score > 0) {
    state.target = state.teamA.score + 1;
  } else if (state.battingTeam === "A") {
    state.target = null;
  }

  return state;
}

/** Placeholder for future shared-secret auth — no-op for MVP */
function checkAuth(socket) {
  // e.g. const token = socket.handshake.auth?.token;
  // if (token !== process.env.SHARED_SECRET) throw new Error("unauthorized");
  return true;
}

app.use(express.static(path.join(__dirname, "public")));

io.on("connection", (socket) => {
  let currentRoom = null;

  socket.on("join-room", (roomId) => {
    try {
      checkAuth(socket);
    } catch {
      return;
    }

    if (!roomId || typeof roomId !== "string") return;

    // Leave previous room if switching
    if (currentRoom) {
      socket.leave(currentRoom);
    }

    currentRoom = roomId.trim();
    socket.join(currentRoom);

    const state = getOrCreateRoom(currentRoom);
    socket.emit("state-update", state);
  });

  socket.on("update-state", ({ room, patch }) => {
    try {
      checkAuth(socket);
    } catch {
      return;
    }

    if (!room || !patch || typeof patch !== "object") return;

    const state = getOrCreateRoom(room);
    applyPatch(state, patch);

  // Broadcast to everyone in the room (control panel + overlays)
    io.to(room).emit("state-update", state);
  });

  socket.on("trigger-animation", ({ room, animation, payload }) => {
    try {
      checkAuth(socket);
    } catch {
      return;
    }

    if (!room || !animation) return;

    const allowed = [
      "four", "six", "wicket", "out", "milestone", "custom",
      "toss", "need_to_win", "innings_break", "winner",
      "partnership", "player_card", "team_lineup", "match_summary",
      "sponsor", "message",
    ];
    if (!allowed.includes(animation)) return;

    // Transient event — not stored in room state
    io.to(room).emit("play-animation", { animation, payload });
  });

  socket.on("reset-room", (roomId) => {
    try {
      checkAuth(socket);
    } catch {
      return;
    }

    if (!roomId) return;

    rooms[roomId] = createDefaultState();
    io.to(roomId).emit("state-update", rooms[roomId]);
  });

  socket.on("disconnect", () => {
    currentRoom = null;
  });
});

server.listen(PORT, () => {
  const base = `http://localhost:${PORT}`;
  console.log("\n🏏 Cricket Overlay Server ready\n");
  console.log(`  Control panel:  ${base}/control.html?room=${SAMPLE_ROOM}`);
  console.log(`  Overlay (OBS):  ${base}/overlay.html?room=${SAMPLE_ROOM}`);
  console.log(`\n  Second room example: ?room=match2\n`);
});
