/**
 * Shared realtime client — Laravel Echo + Pusher, with HTTP poll fallback.
 * Hydrates over HTTP, listens on public channel live.{room}, and keeps
 * polling so OBS overlays / owner pages still update if Pusher drops
 * or the event is too large for Pusher's 10KB limit.
 */
(function (global) {
  "use strict";

  function pusherConfig() {
    return global.CRICKET_PUSHER || {};
  }

  function echoConstructor() {
    const E = global.Echo;
    if (!E) return null;
    if (typeof E === "function") return E;
    if (typeof E.default === "function") return E.default;
    if (typeof E.Echo === "function") return E.Echo;
    return null;
  }

  function createEcho() {
    const cfg = pusherConfig();
    if (!cfg.key) {
      console.warn("[CricketRealtime] Missing PUSHER_APP_KEY — realtime disabled");
      return null;
    }
    if (!global.Pusher) {
      console.warn("[CricketRealtime] Pusher JS not loaded");
      return null;
    }
    const EchoClass = echoConstructor();
    if (!EchoClass) {
      console.warn("[CricketRealtime] Laravel Echo not loaded");
      return null;
    }

    if (global.__cricketEcho) return global.__cricketEcho;

    global.__cricketEcho = new EchoClass({
      broadcaster: "pusher",
      key: cfg.key,
      cluster: cfg.cluster || "mt1",
      wsHost: cfg.wsHost || undefined,
      wsPort: cfg.wsPort || undefined,
      wssPort: cfg.wssPort || undefined,
      forceTLS: cfg.forceTLS !== false,
      enabledTransports: cfg.enabledTransports || ["ws", "wss"],
      disableStats: true,
      namespace: "",
      authEndpoint: cfg.authEndpoint || "/broadcasting/auth",
    });

    return global.__cricketEcho;
  }

  class CricketRealtime {
    constructor(options = {}) {
      this.room = options.room || "match1";
      this.onState = options.onState || (() => {});
      this.onAnimation = options.onAnimation || (() => {});
      this.onStatus = options.onStatus || (() => {});
      this.hydrateUrl = options.hydrateUrl || null;
      this.enablePoll = options.enablePoll !== false;
      this.pollWhenLiveMs = options.pollWhenLiveMs ?? 1500;
      this.pollWhenOfflineMs = options.pollWhenOfflineMs ?? 500;
      this.echo = null;
      this.channel = null;
      this.version = 0;
      this._appliedVersion = -1;
      this._lastState = null;
      this.lastEventId = null;
      this.seen = new Set();
      this._closed = false;
      this._status = "offline";
      this._pollTimer = null;
      this._boundConnection = false;
    }

    start() {
      this._closed = false;
      this.hydrateOnce({ fromPoll: false });
      this.subscribe();
      this.schedulePoll();
    }

    stop() {
      this._closed = true;
      this.clearPoll();
      this.unsubscribe();
      this.setStatus("offline");
    }

    setRoom(room) {
      this.room = room || "match1";
      this.version = 0;
      this._appliedVersion = -1;
      this._lastState = null;
      this.lastEventId = null;
      this.seen.clear();
      this.stop();
      this.start();
    }

    setStatus(status) {
      if (this._status === status) return;
      this._status = status;
      this.onStatus(status);
      if (!this._closed && this.enablePoll) this.schedulePoll();
    }

    channelName() {
      return `live.${this.room}`;
    }

    subscribe() {
      if (this._closed) return;
      this.echo = createEcho();
      if (!this.echo) {
        this.setStatus("offline");
        return;
      }

      this.unsubscribe();
      this.setStatus("connecting");

      try {
        this.channel = this.echo.channel(this.channelName());
        const onMsg = (msg) => this.handleMessage(msg);
        this.channel.listen(".realtime", onMsg);
        this.channel.listen("realtime", onMsg);

        const raw = this.channel.subscription;
        if (raw && typeof raw.bind === "function") {
          raw.bind("realtime", onMsg);
        }

        if (typeof this.channel.subscribed === "function") {
          this.channel.subscribed(() => this.setStatus("live"));
        }
        if (typeof this.channel.error === "function") {
          this.channel.error(() => this.setStatus("offline"));
        }

        const pusher = this.echo.connector?.pusher;
        if (pusher && !this._boundConnection) {
          this._boundConnection = true;
          pusher.connection.bind("connected", () => this.setStatus("live"));
          pusher.connection.bind("disconnected", () => this.setStatus("reconnecting"));
          pusher.connection.bind("unavailable", () => this.setStatus("offline"));
          pusher.connection.bind("failed", () => this.setStatus("offline"));
        }
        if (pusher?.connection?.state === "connected") {
          this.setStatus("live");
        }
      } catch (e) {
        console.warn("[CricketRealtime] subscribe failed", e);
        this.setStatus("offline");
      }
    }

    unsubscribe() {
      if (this.echo && this.room) {
        try {
          this.echo.leave(this.channelName());
        } catch (_) {}
      }
      this.channel = null;
    }

    handleMessage(msg) {
      if (!msg) return;

      const version = typeof msg.version === "number" ? msg.version : 0;
      if (msg.state) {
        this.applyState(msg.state, version, { fromPoll: false });
      } else if (version > 0) {
        this.hydrateOnce({ fromPoll: false });
      }

      if (msg.type === "animation" || msg.animation) {
        const id = msg.id || `${msg.animation}-${msg.at || Date.now()}`;
        if (this.seen.has(id)) return;
        this.seen.add(id);
        this.lastEventId = id;
        if (this.seen.size > 80) {
          this.seen = new Set([...this.seen].slice(-40));
        }
        if (msg.animation) {
          this.onAnimation(msg.animation, msg.payload || {}, id);
        }
      }
    }

    mergeState(next) {
      const prev = this._lastState;
      if (!prev || !next || typeof next !== "object") {
        this._lastState = next || prev;
        return this._lastState;
      }

      const merged = { ...next };
      if (merged.queue == null && prev.queue) merged.queue = prev.queue;
      if (Array.isArray(merged.teams) && Array.isArray(prev.teams)) {
        merged.teams = merged.teams.map((team) => {
          const old = prev.teams.find((p) => Number(p.id) === Number(team.id));
          if (old && team.squad == null && old.squad) {
            return { ...old, ...team, squad: old.squad };
          }
          return team;
        });
      }

      this._lastState = merged;
      return merged;
    }

    applyState(state, version, { fromPoll } = {}) {
      if (this._closed || !state) return;

      const ver = Number(version || state.version || 0);
      if (ver > 0) {
        if (ver < this.version) return;
        if (ver === this._appliedVersion) {
          const incomingFuller = !state._partial && this._lastState?._partial;
          if (!incomingFuller) return;
        }
        this.version = Math.max(this.version, ver);
        this._appliedVersion = ver;
      } else if (fromPoll && this._appliedVersion >= 0) {
        return;
      }

      this.onState(this.mergeState(state), this.version);
    }

    resolveHydrateUrl() {
      if (this.hydrateUrl) return this.hydrateUrl;
      return `/api/matches/${encodeURIComponent(this.room)}`;
    }

    clearPoll() {
      if (this._pollTimer) {
        clearTimeout(this._pollTimer);
        this._pollTimer = null;
      }
    }

    schedulePoll() {
      this.clearPoll();
      if (this._closed || !this.enablePoll) return;
      const ms = this._status === "live" ? this.pollWhenLiveMs : this.pollWhenOfflineMs;
      this._pollTimer = setTimeout(() => this.pollTick(), ms);
    }

    async pollTick() {
      if (this._closed) return;
      await this.hydrateOnce({ fromPoll: true });
      this.schedulePoll();
    }

    async hydrateOnce({ fromPoll } = {}) {
      try {
        const res = await fetch(this.resolveHydrateUrl(), {
          headers: { Accept: "application/json" },
          cache: "no-store",
        });
        if (!res.ok) return;
        const data = await res.json();
        const state = data.state || data;
        const version = Number(data.version ?? state?.version ?? 0);
        this.applyState(state, version, { fromPoll: Boolean(fromPoll) });
      } catch (_) {}
    }
  }

  global.CricketRealtime = CricketRealtime;
})(window);
