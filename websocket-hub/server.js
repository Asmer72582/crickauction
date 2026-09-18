/**
 * Lightweight WebSocket hub for cricket-overlay.
 * Overlay + control + auction desk subscribe by room; Laravel POSTs state/animation pushes.
 *
 * Start: node websocket-hub/server.js
 * Default: ws://127.0.0.1:6001
 */

import http from "http";
import { WebSocketServer } from "ws";

const PORT = Number(process.env.WS_HUB_PORT || 6001);
const rooms = new Map(); // roomId -> Set<WebSocket>
const snapshots = new Map(); // roomId -> last publish payload

function joinRoom(ws, roomId) {
  const id = String(roomId || "match1").trim() || "match1";
  if (ws.roomId && rooms.has(ws.roomId)) {
    rooms.get(ws.roomId).delete(ws);
  }
  ws.roomId = id;
  if (!rooms.has(id)) rooms.set(id, new Set());
  rooms.get(id).add(ws);
  return id;
}

function leaveRoom(ws) {
  if (ws.roomId && rooms.has(ws.roomId)) {
    rooms.get(ws.roomId).delete(ws);
    if (rooms.get(ws.roomId).size === 0) rooms.delete(ws.roomId);
  }
}

function broadcast(roomId, message) {
  const set = rooms.get(String(roomId));
  if (!set || !set.size) return 0;
  const raw = typeof message === "string" ? message : JSON.stringify(message);
  let n = 0;
  for (const client of set) {
    if (client.readyState === 1) {
      client.send(raw);
      n++;
    }
  }
  return n;
}

const server = http.createServer((req, res) => {
  if (req.method === "POST" && req.url === "/publish") {
    let body = "";
    req.on("data", (chunk) => {
      body += chunk;
      if (body.length > 5_000_000) req.destroy();
    });
    req.on("end", () => {
      try {
        const payload = JSON.parse(body || "{}");
        const room = payload.room || "match1";
        snapshots.set(String(room), payload);
        const sent = broadcast(room, payload);
        res.writeHead(200, { "Content-Type": "application/json" });
        res.end(JSON.stringify({ ok: true, sent }));
      } catch (e) {
        res.writeHead(400, { "Content-Type": "application/json" });
        res.end(JSON.stringify({ ok: false, error: String(e.message || e) }));
      }
    });
    return;
  }

  if (req.method === "GET" && req.url === "/health") {
    res.writeHead(200, { "Content-Type": "application/json" });
    res.end(JSON.stringify({
      ok: true,
      rooms: [...rooms.keys()],
      clients: [...rooms.values()].reduce((n, s) => n + s.size, 0),
      snapshots: snapshots.size,
    }));
    return;
  }

  res.writeHead(404);
  res.end("Not found");
});

const wss = new WebSocketServer({ server });

wss.on("connection", (ws) => {
  ws.isAlive = true;
  ws.on("pong", () => { ws.isAlive = true; });

  ws.send(JSON.stringify({ type: "hello", message: "cricket-overlay ws hub" }));

  ws.on("message", (buf) => {
    let msg;
    try {
      msg = JSON.parse(String(buf));
    } catch {
      return;
    }

    if (msg.type === "subscribe" || msg.type === "join") {
      const room = joinRoom(ws, msg.room || msg.roomId);
      ws.send(JSON.stringify({ type: "subscribed", room }));
      const snap = snapshots.get(String(room));
      if (snap && ws.readyState === 1) {
        ws.send(JSON.stringify(snap));
      }
      return;
    }

    if (msg.type === "ping") {
      ws.send(JSON.stringify({ type: "pong", at: Date.now() }));
    }
  });

  ws.on("close", () => leaveRoom(ws));
  ws.on("error", () => leaveRoom(ws));
});

setInterval(() => {
  for (const ws of wss.clients) {
    if (!ws.isAlive) {
      leaveRoom(ws);
      ws.terminate();
      continue;
    }
    ws.isAlive = false;
    ws.ping();
  }
}, 25000);

server.listen(PORT, "0.0.0.0", () => {
  console.log(`[ws-hub] listening on ws://0.0.0.0:${PORT}`);
  console.log(`[ws-hub] publish POST http://127.0.0.1:${PORT}/publish`);
});
