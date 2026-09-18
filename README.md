# Cricket Live Overlay (Laravel)

Local cricket streaming toolkit with:

1. **Tournament dashboard** — list all tournaments, create new ones  
2. **Multiple overlay themes** — pick a broadcast graphic package when creating  
3. **Full ball-by-ball scoring** — batsmen, bowler, overs, extras, wickets  
4. **OBS overlay** — transparent 1920×1080 graphics  

## Install & run

```bash
cd cricket-overlay
composer install
cp .env.example .env   # if needed
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve --port=8000
```

In a **second terminal**, start the WebSocket hub (required for live overlay — no polling):

```bash
cd websocket-hub
npm install
npm start
```

Hub: `ws://127.0.0.1:6001` · Laravel publishes to `http://127.0.0.1:6001/publish`.

Optional `.env`:

```
WS_HUB_PORT=6001
WS_HUB_URL=ws://127.0.0.1:6001
WS_HUB_PUBLISH_URL=http://127.0.0.1:6001/publish
```

## Product flow

1. Open **http://localhost:8000/** → My Tournaments dashboard  
2. Click **+ Create Tournament** and pick an overlay theme  
3. Land on **Edit Matches** for that tournament  
4. Click **+ Create Match** (as many as you need)  
5. Open **Score** on a match card → live control panel  
6. Point OBS at that match’s **Overlay** URL  

### URLs

| Page | URL |
|------|-----|
| Dashboard | http://localhost:8000/ |
| Tournament matches | http://localhost:8000/tournaments/{id}/matches |
| Control (per match) | http://localhost:8000/control?room=ROOM_ID |
| Overlay (per match) | http://localhost:8000/overlay?room=ROOM_ID |

## Overlay packages

| Package | ID | Status | Look |
|---------|-----|--------|------|
| **Overlay 01 · Arena Hub** | `arena` | **COMPLETED / LOCKED** | Center-circle hub scorebug — navy `#4964A1` / orange `#FFA503` / cyan `#00d4ff` |
| **Overlay 02 · Neon Prism** | `prism` | **COMPLETED / LOCKED** | Cyber lower-third strip — void `#050510` / magenta `#ff2d95` / cyan `#00f5ff` |

Only these two themes appear in the tournament picker. Legacy flavour IDs (`midnight`, `ember`, …) map to Arena Hub automatically.

Locked tokens:
- Package 01 → `public/overlay-packages/overlay-01-arena-hub.css`
- Package 02 → `public/overlay-packages/overlay-02-neon-prism.css`

Do not retune a locked package when building Overlay 03+ — add a new package file instead.

Themes are original broadcast identities — not copies of real league brands.

## Knockout Round overlay

1. Open **Control** → **Knockout Stage**  
2. Pick format: **4 / 8 / 16** teams (Groups A–D on 8 & 16)  
3. Fill the **visual bracket** (same L→center←R layout as stream) with team names + match times  
4. Optional round dates (R16 / QF / SF / Final)  
5. Click **Show Knockout Overlay** (saves + shows in one click)

Also available under Match cards → Knockout Round.

## Scoring

1. Set team names → Save match info  
2. Set **Striker / Non-striker / Bowler** → Set players  
3. Score with the ball pad (`•` `1–6`, wicket, wide, no-ball, bye, LB)  
4. After wicket / over → update players and continue  
5. **End innings** sets chase target  

## Architecture

| Piece | Role |
|-------|------|
| `Tournament` | Dashboard entity + selected `theme_id` + `room_id` |
| `OverlayThemeRegistry` | Theme catalog |
| `CricketScoringService` | Ball-by-ball engine |
| `MatchRoom` | Live JSON state per room (SQLite) |
| WebSocket hub | `websocket-hub/server.js` — push state + animations to overlay & control |
| Overlay / control | One HTTP hydrate, then `ws://…:6001` (light poll only if hub is down) |

## Broadcast Overlay (OBS / vMix)

Professional graphics system for live streaming:

1. Open a tournament → **Broadcast** (or Match card → **Broadcast**)
2. Choose **Theme**: Modern / Classic / Minimal / Premium
3. Choose **Panel**: Full Scoreboard, Live Score, Batsmen, Bowler, Last Ball, Last Over, Partnership, FOW, Match Info, Player Card
4. **Copy OBS URL** → paste into OBS Browser Source (1920×1080, transparent)
5. Same URL works in **vMix Web Browser**

### OBS sharpness checklist (fix soft / blurry overlay)

1. Set Browser Source **Width `1920`** and **Height `1080`** — must match canvas. Stretching a smaller source makes text soft.
2. Do **not** manually resize the Browser source on the canvas; keep it 1:1 / Fit without extra scale.
3. Right‑click Browser source → **Scale Filtering** → **Lanczos** (sharper than Bilinear).
4. Browser Source FPS: **60** (or match your canvas FPS). Leave “Shutdown source when not visible” off while scoring.
5. Keep OBS canvas / output at **1920×1080** — avoid downscale then upscale in the encoder.
6. After overlay updates: right‑click Browser source → **Refresh**.

When the Browser Source viewport is exactly 1920×1080, the overlay skips CSS scaling for a sharper image.

Public URL shape:

```text
/overlay/match/{matchId}?theme=modern&panel=full&token=...
```

- Transparent background (OBS-safe)
- Token-gated (no admin login on overlay)
- Live WebSocket from scoring engine (`/overlay?room=`). Fallback HTTP poll only if the hub is down.
- Themes only skin layout/colors — scoring stays in `CricketScoringService`

### Architecture notes

| Piece | Role |
|-------|------|
| Existing scoring | Unchanged ball-by-ball engine |
| `OverlayDataNormalizer` | Clean payload for graphics |
| `BroadcastThemeRegistry` | modern / classic / minimal / premium |
| `OverlayConfig` | theme, panel, token, branding per match |
| `/overlay/match/{id}` | Public OBS/vMix page |

Legacy `/overlay?room=` still works for the older graphics engine.
