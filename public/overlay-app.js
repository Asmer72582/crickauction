/**
 * OBS overlay — WebSocket realtime (with light poll fallback).
 */

const roomId = window.CRICKET_ROOM || new URLSearchParams(location.search).get("room") || "match1";

const PACKAGE_STYLES = {
  "01": [
    { id: "overlay-pkg-01", href: "/overlay-packages/overlay-01-arena-hub.css" },
    { id: "overlay-anim-01", href: "/overlay-animations-ipl.css" },
    { id: "overlay-out-01", href: "/overlay-out-sting.css" },
  ],
  "02": [
    { id: "overlay-pkg-02", href: "/overlay-packages/overlay-02-neon-prism.css" },
    { id: "overlay-sb-02", href: "/overlay-scoreboard-prism.css" },
    { id: "overlay-anim-02", href: "/overlay-animations-prism.css" },
  ],
};

let activePackage = window.CRICKET_THEME?.package
  || document.getElementById("broadcast-canvas")?.dataset?.overlayPackage
  || "01";

const graphics = new GraphicsEngine({
  canvas: document.getElementById("broadcast-canvas"),
  scoreboardRoot: document.getElementById("scoreboard-mount"),
  eventLayer: document.getElementById("event-layer"),
  fullscreenLayer: document.getElementById("fullscreen-layer"),
  package: activePackage,
});

function getThemeMeta(themeId) {
  const themes = window.OVERLAY_THEMES || [];
  if (Array.isArray(themes)) {
    return themes.find((t) => t.id === themeId) || window.CRICKET_THEME;
  }
  return themes[themeId] || window.CRICKET_THEME;
}

function ensurePackageStyles(pkg) {
  const target = pkg === "02" ? "02" : "01";
  Object.entries(PACKAGE_STYLES).forEach(([key, links]) => {
    links.forEach(({ id, href }) => {
      let el = document.getElementById(id);
      if (!el) {
        el = document.createElement("link");
        el.id = id;
        el.rel = "stylesheet";
        el.href = href;
        document.head.appendChild(el);
      }
      el.disabled = key !== target;
    });
  });
}

function applyThemeColors(themeId) {
  const theme = getThemeMeta(themeId) || {};
  const colors = theme.colors || {};
  const primary = colors.primary || theme.primary || theme.panel || "#4964A1";
  const deep = colors.deep || theme.deep || "#002153";
  const accent = colors.accent || theme.accent || "#FFA503";
  const glow = colors.glow || theme.glow || "#00d4ff";

  const root = document.documentElement;
  const canvas = document.getElementById("broadcast-canvas");
  [root, canvas].filter(Boolean).forEach((el) => {
    el.style.setProperty("--hub-navy", primary);
    el.style.setProperty("--hub-navy-deep", deep);
    el.style.setProperty("--hub-orange", accent);
    el.style.setProperty("--hub-cyan", glow);
    el.style.setProperty("--dual-a", primary);
    el.style.setProperty("--dual-b", accent);
    el.style.setProperty("--dual-glow", glow);
    el.style.setProperty("--broadcast-accent", accent);
    el.style.setProperty("--broadcast-panel", deep);
    el.style.setProperty("--gfx-accent", accent);
    el.style.setProperty("--arena-navy", primary);
    el.style.setProperty("--arena-navy-deep", deep);
    el.style.setProperty("--arena-orange", accent);
    el.style.setProperty("--arena-cyan", glow);
  });
}

function applyThemePackage(themeId) {
  const theme = getThemeMeta(themeId) || {};
  const pkg = theme.package === "02" ? "02" : "01";
  const canvas = document.getElementById("broadcast-canvas");
  if (canvas) {
    canvas.dataset.theme = themeId;
    canvas.dataset.overlayPackage = pkg;
  }
  if (pkg !== activePackage) {
    ensurePackageStyles(pkg);
    graphics.setPackage(pkg);
    activePackage = pkg;
  }
}

function applyState(state) {
  const root = document.getElementById("graphics-root");
  root.classList.toggle("overlay-hidden", !state.visible);

  const themeId = state.themeId || window.CRICKET_THEME?.id || "arena";
  applyThemePackage(themeId);
  applyThemeColors(themeId);
  graphics.updateState(state);
}

function setWsBadge(status) {
  let el = document.getElementById("ws-status");
  if (status === "live") {
    if (el) el.remove();
    return;
  }
  if (!el) {
    el = document.createElement("div");
    el.id = "ws-status";
    el.style.cssText = "position:fixed;top:8px;right:8px;z-index:9999;font:700 11px/1 myFont,sans-serif;padding:6px 10px;border-radius:4px;opacity:.85;pointer-events:none";
    document.body.appendChild(el);
  }
  const map = {
    reconnecting: ["WS…", "#92400e"],
    offline: ["WS OFF", "#7f1d1d"],
  };
  const [label, bg] = map[status] || ["WS", "#334155"];
  el.textContent = label;
  el.style.background = bg;
  el.style.color = "#fff";
}

const initialTheme = window.CRICKET_THEME?.id || "arena";
ensurePackageStyles(activePackage);
applyThemeColors(initialTheme);

const realtime = new CricketRealtime({
  room: roomId,
  hydrateUrl: `/api/matches/${encodeURIComponent(roomId)}`,
  pollWhenLiveMs: 1000,
  pollWhenOfflineMs: 400,
  onState: (state) => applyState(state),
  onAnimation: (animation, payload) => {
    graphics.playAnimation(animation, payload || {});
  },
  onStatus: setWsBadge,
});

realtime.start();
