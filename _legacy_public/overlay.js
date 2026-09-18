/**
 * OBS overlay client — Socket.io bridge + broadcast graphics engine.
 */

const socket = io();

function getRoomFromUrl() {
  const params = new URLSearchParams(window.location.search);
  return params.get("room")?.trim() || "match1";
}

const roomId = getRoomFromUrl();

const graphics = new GraphicsEngine({
  canvas: document.getElementById("broadcast-canvas"),
  scoreboardRoot: document.getElementById("scoreboard-mount"),
  eventLayer: document.getElementById("event-layer"),
  fullscreenLayer: document.getElementById("fullscreen-layer"),
});

function applyState(state) {
  const root = document.getElementById("graphics-root");
  root.classList.toggle("overlay-hidden", !state.visible);
  graphics.updateState(state);
}

socket.on("connect", () => socket.emit("join-room", roomId));
socket.on("state-update", applyState);
socket.on("play-animation", ({ animation, payload }) => {
  graphics.playAnimation(animation, payload);
});
