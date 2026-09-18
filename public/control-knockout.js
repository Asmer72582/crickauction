/**
 * Knockout Stage — canvas bracket editor + overlay trigger.
 * Admin can add matches/rounds via +, SVG connectors align to next blocks.
 */

(function () {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
  let roomId = window.CRICKET_ROOM || "match1";
  let state = null;
  let version = 0;
  let realtime = null;
  let draft = null;
  let linksRaf = 0;

  const $ = (id) => document.getElementById(id);
  const isKnockoutPage = !!window.KNOCKOUT_PAGE;

  const STAGE_CATALOG = [
    { key: "r64", round: "R64", label: "Round of 64", dateKey: "r64" },
    { key: "r32", round: "R32", label: "Round of 32", dateKey: "r32" },
    { key: "r16", round: "R16", label: "Round of 16", dateKey: "r16" },
    { key: "qf", round: "QF", label: "Quarter-finals", dateKey: "qf" },
    { key: "sf", round: "SF", label: "Semi-finals", dateKey: "sf" },
    { key: "f", round: "F", label: "Final", dateKey: "final" },
  ];

  const KO_TEMPLATES = {
    4: [
      { slot: "sf1", round: "SF", label: "SF 1", side: "L", group: "", teamA: "", teamB: "", time: "" },
      { slot: "sf2", round: "SF", label: "SF 2", side: "R", group: "", teamA: "", teamB: "", time: "" },
      { slot: "final", round: "F", label: "FINAL", side: "C", group: "", teamA: "", teamB: "", time: "" },
    ],
    8: [
      { slot: "qf1", round: "QF", label: "QF 1", side: "L", group: "A", teamA: "", teamB: "", time: "" },
      { slot: "qf2", round: "QF", label: "QF 2", side: "L", group: "B", teamA: "", teamB: "", time: "" },
      { slot: "qf3", round: "QF", label: "QF 3", side: "R", group: "C", teamA: "", teamB: "", time: "" },
      { slot: "qf4", round: "QF", label: "QF 4", side: "R", group: "D", teamA: "", teamB: "", time: "" },
      { slot: "sf1", round: "SF", label: "SF 1", side: "L", group: "", teamA: "", teamB: "", time: "" },
      { slot: "sf2", round: "SF", label: "SF 2", side: "R", group: "", teamA: "", teamB: "", time: "" },
      { slot: "final", round: "F", label: "FINAL", side: "C", group: "", teamA: "", teamB: "", time: "" },
    ],
    16: [
      { slot: "r16_1", round: "R16", label: "R16 1", side: "L", group: "A", teamA: "", teamB: "", time: "" },
      { slot: "r16_2", round: "R16", label: "R16 2", side: "L", group: "A", teamA: "", teamB: "", time: "" },
      { slot: "r16_3", round: "R16", label: "R16 3", side: "L", group: "B", teamA: "", teamB: "", time: "" },
      { slot: "r16_4", round: "R16", label: "R16 4", side: "L", group: "B", teamA: "", teamB: "", time: "" },
      { slot: "r16_5", round: "R16", label: "R16 5", side: "R", group: "C", teamA: "", teamB: "", time: "" },
      { slot: "r16_6", round: "R16", label: "R16 6", side: "R", group: "C", teamA: "", teamB: "", time: "" },
      { slot: "r16_7", round: "R16", label: "R16 7", side: "R", group: "D", teamA: "", teamB: "", time: "" },
      { slot: "r16_8", round: "R16", label: "R16 8", side: "R", group: "D", teamA: "", teamB: "", time: "" },
      { slot: "qf1", round: "QF", label: "QF 1", side: "L", group: "A", teamA: "", teamB: "", time: "" },
      { slot: "qf2", round: "QF", label: "QF 2", side: "L", group: "B", teamA: "", teamB: "", time: "" },
      { slot: "qf3", round: "QF", label: "QF 3", side: "R", group: "C", teamA: "", teamB: "", time: "" },
      { slot: "qf4", round: "QF", label: "QF 4", side: "R", group: "D", teamA: "", teamB: "", time: "" },
      { slot: "sf1", round: "SF", label: "SF 1", side: "L", group: "", teamA: "", teamB: "", time: "" },
      { slot: "sf2", round: "SF", label: "SF 2", side: "R", group: "", teamA: "", teamB: "", time: "" },
      { slot: "final", round: "F", label: "FINAL", side: "C", group: "", teamA: "", teamB: "", time: "" },
    ],
  };

  function api(path, options = {}) {
    const headers = {
      Accept: "application/json",
      "X-CSRF-TOKEN": csrf,
      "X-Requested-With": "XMLHttpRequest",
      "Content-Type": "application/json",
      ...(options.headers || {}),
    };
    return fetch(`/api/matches/${encodeURIComponent(roomId)}${path}`, {
      ...options,
      headers,
    }).then(async (res) => {
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.error || data.message || res.statusText);
      return data;
    });
  }

  function setStatus(ok, label) {
    const el = $("connection-status");
    if (!el) return;
    el.textContent = label || (ok ? "Live (WS)" : "Disconnected");
    el.className = `status-pill ${ok ? "status-connected" : "status-disconnected"}`;
  }

  function koFormatOf(v) {
    const n = Number(v);
    return n === 4 || n === 16 ? n : 8;
  }

  function escAttr(s) {
    return String(s ?? "")
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;");
  }

  function cloneMatches(list) {
    return (list || []).map((m) => ({ ...m }));
  }

  function stageMeta(round) {
    return STAGE_CATALOG.find((s) => s.round === round) || { key: round.toLowerCase(), round, label: round, dateKey: round.toLowerCase() };
  }

  function roundsFromMatches(matches) {
    const present = new Set((matches || []).map((m) => String(m.round || "").toUpperCase()));
    return STAGE_CATALOG.filter((s) => present.has(s.round));
  }

  function matchesForRound(matches, round) {
    return (matches || []).filter((m) => String(m.round).toUpperCase() === round);
  }

  function nextSlot(matches, round) {
    const prefix = String(round).toLowerCase() + "_";
    let n = matchesForRound(matches, round).length + 1;
    const used = new Set((matches || []).map((m) => String(m.slot).toLowerCase()));
    while (used.has(prefix + n) || used.has(String(round).toLowerCase() + n)) n += 1;
    return prefix + n;
  }

  function emptyDates() {
    return { r64: "", r32: "", r16: "", qf: "", sf: "", final: "" };
  }

  function normalizeDraft(ko) {
    const format = koFormatOf(ko?.format);
    let matches = cloneMatches(ko?.matches);
    if (!matches.length) matches = cloneMatches(KO_TEMPLATES[format]);
    return {
      title: ko?.title || "KNOCKOUT ROUND",
      format,
      custom: !!ko?.custom,
      roundDates: { ...emptyDates(), ...(ko?.roundDates || {}) },
      matches,
    };
  }

  /* ── SVG connectors that track fixture centers ── */
  function relBox(el, root) {
    const a = el.getBoundingClientRect();
    const b = root.getBoundingClientRect();
    return {
      left: a.left - b.left,
      top: a.top - b.top,
      right: a.right - b.left,
      bottom: a.bottom - b.top,
      midY: (a.top + a.bottom) / 2 - b.top,
      midX: (a.left + a.right) / 2 - b.left,
      width: a.width,
      height: a.height,
    };
  }

  function drawKnockoutConnectors(root) {
    const bracket = root?.querySelector?.(".ko-bracket") || root;
    if (!bracket || !bracket.querySelectorAll) return;
    const stages = [...bracket.querySelectorAll(":scope > .ko-stage")];
    if (stages.length < 2) return;

    let svg = bracket.querySelector("svg.ko-svg-links");
    if (!svg) {
      svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
      svg.classList.add("ko-svg-links");
      svg.setAttribute("aria-hidden", "true");
      bracket.insertBefore(svg, bracket.firstChild);
    }

    const w = bracket.clientWidth;
    const h = bracket.clientHeight;
    svg.setAttribute("width", String(w));
    svg.setAttribute("height", String(h));
    svg.setAttribute("viewBox", `0 0 ${w} ${h}`);
    svg.innerHTML = "";

    const NS = "http://www.w3.org/2000/svg";
    const addPath = (d) => {
      const p = document.createElementNS(NS, "path");
      p.setAttribute("d", d);
      p.setAttribute("fill", "none");
      p.setAttribute("stroke", "#002153");
      p.setAttribute("stroke-width", "2.5");
      p.setAttribute("stroke-linecap", "square");
      p.setAttribute("stroke-linejoin", "miter");
      svg.appendChild(p);
    };

    for (let i = 0; i < stages.length - 1; i++) {
      const left = [...stages[i].querySelectorAll(".ko-fixture")];
      const right = [...stages[i + 1].querySelectorAll(".ko-fixture")];
      for (let r = 0; r < right.length; r++) {
        const dest = right[r];
        const a = left[r * 2];
        const b = left[r * 2 + 1];
        if (!dest) continue;
        const db = relBox(dest, bracket);
        const x2 = db.left + 2;
        const y2 = db.midY;

        if (a && b) {
          const ab = relBox(a, bracket);
          const bb = relBox(b, bracket);
          const x1a = ab.right - 2;
          const y1a = ab.midY;
          const x1b = bb.right - 2;
          const y1b = bb.midY;
          const midX = (Math.max(x1a, x1b) + x2) / 2;
          addPath(`M ${x1a} ${y1a} H ${midX}`);
          addPath(`M ${x1b} ${y1b} H ${midX}`);
          addPath(`M ${midX} ${y1a} V ${y1b}`);
          addPath(`M ${midX} ${y2} H ${x2}`);
        } else if (a) {
          const ab = relBox(a, bracket);
          const midX = (ab.right - 2 + x2) / 2;
          addPath(`M ${ab.right - 2} ${ab.midY} H ${midX} V ${y2} H ${x2}`);
        }
      }
    }
  }

  window.KnockoutLinks = { draw: drawKnockoutConnectors };

  function scheduleLinks(root) {
    cancelAnimationFrame(linksRaf);
    linksRaf = requestAnimationFrame(() => {
      requestAnimationFrame(() => drawKnockoutConnectors(root || $("ko-bracket-editor")));
    });
  }

  function koFixtureEditor(m, opts = {}) {
    if (!m) return "";
    const finalCls = opts.final || m.round === "F" ? " ko-fixture-final" : "";
    const canRemove = !opts.final && m.round !== "F";
    return `
      <div class="ko-fixture${finalCls}" data-slot="${escAttr(m.slot)}" data-round="${escAttr(m.round)}">
        ${isKnockoutPage && canRemove ? `<button type="button" class="ko-fx-remove" data-remove-slot="${escAttr(m.slot)}" title="Remove match">×</button>` : ""}
        <div class="ko-row">
          <span class="ko-team"><input type="text" id="ko-${escAttr(m.slot)}-a" value="${escAttr(m.teamA || "")}" placeholder="Team A" /></span>
        </div>
        <div class="ko-row">
          <span class="ko-team"><input type="text" id="ko-${escAttr(m.slot)}-b" value="${escAttr(m.teamB || "")}" placeholder="Team B" /></span>
        </div>
        <div class="ko-timebar">
          <label>Time</label>
          <input type="text" id="ko-${escAttr(m.slot)}-time" value="${escAttr(m.time || "")}" placeholder="e.g. 10:00 AM" />
        </div>
      </div>`;
  }

  function koStageEditor(stage, list, rd) {
    const isFinal = stage.round === "F";
    const dateVal = rd?.[stage.dateKey] || "";
    const fixtures = list.map((m) => koFixtureEditor(m, { final: isFinal })).join("");
    return `
      <section class="ko-stage" data-round="${stage.round}">
        <div class="ko-stage-h">
          <span class="ko-stage-title">${escAttr(stage.label)}</span>
          ${dateVal ? `<em>${escAttr(dateVal)}</em>` : ""}
          ${isKnockoutPage ? `
            <div class="ko-stage-actions">
              <button type="button" class="ko-plus" data-add-round="${stage.round}" title="Add match">+</button>
              ${!isFinal && list.length > 1 ? `<button type="button" class="ko-minus" data-trim-round="${stage.round}" title="Remove last match">−</button>` : ""}
            </div>
          ` : ""}
        </div>
        <div class="ko-stage-body">
          ${fixtures || `<div class="ko-empty-stage">No matches</div>`}
        </div>
      </section>`;
  }

  function renderKnockoutForm(ko) {
    const board = $("ko-bracket-editor");
    if (!board) return;
    draft = normalizeDraft(ko || draft);
    const format = draft.format;
    if ($("ko-format") && document.activeElement !== $("ko-format")) {
      $("ko-format").value = String(format);
    }
    if ($("ko-title") && document.activeElement !== $("ko-title")) {
      $("ko-title").value = draft.title;
    }

    const active = document.activeElement;
    const activeId = active?.id || "";
    const rd = draft.roundDates;
    const stages = roundsFromMatches(draft.matches);
    const stagesHtml = stages.map((st) => koStageEditor(st, matchesForRound(draft.matches, st.round), rd)).join("");

    const missingEarlier = STAGE_CATALOG.filter(
      (s) => s.round !== "F" && !stages.some((x) => x.round === s.round)
    );
    // Only offer rounds that come before the earliest active stage
    const earliestIdx = stages.length
      ? Math.min(...stages.map((s) => STAGE_CATALOG.findIndex((c) => c.round === s.round)))
      : STAGE_CATALOG.length - 1;
    const addable = missingEarlier.filter((s) => STAGE_CATALOG.findIndex((c) => c.round === s.round) < earliestIdx);

    board.className = `ko-board ko-editor-board ko-format-${format}${draft.custom ? " ko-custom" : ""}`;
    board.innerHTML = `
      <div class="ko-dates">
        ${STAGE_CATALOG.map((s) => `
          <label>${escAttr(s.label)} date
            <input type="text" id="ko-date-${s.dateKey}" value="${escAttr(rd[s.dateKey] || "")}" placeholder="e.g. 8 Feb"
              ${stages.some((x) => x.round === s.round) ? "" : "disabled"} />
          </label>
        `).join("")}
      </div>
      ${isKnockoutPage ? `
        <div class="ko-canvas-bar">
          <span>Bracket canvas — use <strong>+</strong> on each round to add matches</span>
          <div class="ko-canvas-actions">
            ${addable.map((s) => `
              <button type="button" class="ko-add-stage" data-add-stage="${s.round}">+ ${escAttr(s.label)}</button>
            `).join("")}
          </div>
        </div>
      ` : ""}
      <div class="ko-bracket">${stagesHtml}</div>
    `;

    if (activeId && $(activeId)) {
      const el = $(activeId);
      el.focus();
      try {
        const len = el.value?.length || 0;
        el.setSelectionRange(len, len);
      } catch (_) {}
    }
    scheduleLinks(board);
  }

  function readFieldValuesIntoDraft() {
    if (!draft) return;
    draft.title = ($("ko-title")?.value || draft.title || "KNOCKOUT ROUND").trim();
    draft.format = koFormatOf($("ko-format")?.value || draft.format);
    draft.roundDates = emptyDates();
    STAGE_CATALOG.forEach((s) => {
      draft.roundDates[s.dateKey] = ($(`ko-date-${s.dateKey}`)?.value || "").trim();
    });
    draft.matches = draft.matches.map((m) => ({
      ...m,
      teamA: ($(`ko-${m.slot}-a`)?.value || "").trim(),
      teamB: ($(`ko-${m.slot}-b`)?.value || "").trim(),
      time: ($(`ko-${m.slot}-time`)?.value || "").trim(),
    }));
  }

  function collectKnockout() {
    readFieldValuesIntoDraft();
    if (!draft) draft = normalizeDraft({ format: 8 });
    return {
      title: draft.title,
      format: draft.format,
      custom: draft.custom,
      roundDates: { ...draft.roundDates },
      matches: cloneMatches(draft.matches),
    };
  }

  function addMatchToRound(round) {
    readFieldValuesIntoDraft();
    const n = matchesForRound(draft.matches, round).length + 1;
    if (round === "F" && n > 1) return; // keep single final
    draft.custom = true;
    draft.matches.push({
      slot: nextSlot(draft.matches, round),
      round,
      label: round === "F" ? "FINAL" : `${round} ${n}`,
      side: round === "F" ? "C" : "L",
      group: "",
      teamA: "",
      teamB: "",
      time: "",
    });
    renderKnockoutForm(draft);
  }

  function removeLastFromRound(round) {
    readFieldValuesIntoDraft();
    const list = matchesForRound(draft.matches, round);
    if (list.length <= 1) return;
    const last = list[list.length - 1];
    draft.custom = true;
    draft.matches = draft.matches.filter((m) => m.slot !== last.slot);
    renderKnockoutForm(draft);
  }

  function removeSlot(slot) {
    readFieldValuesIntoDraft();
    const m = draft.matches.find((x) => x.slot === slot);
    if (!m || m.round === "F") return;
    const peers = matchesForRound(draft.matches, m.round);
    if (peers.length <= 1) return;
    draft.custom = true;
    draft.matches = draft.matches.filter((x) => x.slot !== slot);
    renderKnockoutForm(draft);
  }

  function addStage(round) {
    readFieldValuesIntoDraft();
    if (matchesForRound(draft.matches, round).length) return;
    draft.custom = true;
    // Seed with 2 matches (or 1 for oddly early rounds — use 2 as pair)
    const count = round === "SF" ? 2 : (round === "QF" ? 4 : 2);
    for (let i = 1; i <= count; i++) {
      draft.matches.unshift({
        slot: nextSlot(draft.matches, round),
        round,
        label: `${round} ${i}`,
        side: "L",
        group: "",
        teamA: "",
        teamB: "",
        time: "",
      });
    }
    // Re-sort by catalog order
    const order = Object.fromEntries(STAGE_CATALOG.map((s, i) => [s.round, i]));
    draft.matches.sort((a, b) => (order[a.round] ?? 99) - (order[b.round] ?? 99) || String(a.slot).localeCompare(String(b.slot)));
    renderKnockoutForm(draft);
  }

  function countFilled(ko) {
    const matches = ko?.matches || [];
    let filled = 0;
    matches.forEach((m) => {
      if ((m.teamA || "").trim() || (m.teamB || "").trim()) filled += 1;
    });
    return { filled, total: matches.length || 0 };
  }

  function updateKnockoutSummary(ko) {
    const title = ko?.title || "KNOCKOUT ROUND";
    const format = koFormatOf(ko?.format);
    const { filled, total } = countFilled(ko);
    if ($("ko-summary-title")) $("ko-summary-title").textContent = title;
    if ($("ko-summary-format")) {
      $("ko-summary-format").textContent = ko?.custom
        ? `Custom · ${total} matches`
        : format === 4 ? "4 teams · SF → Final"
          : format === 16 ? "16 teams · R16 → Final"
            : "8 teams · QF → Final";
    }
    if ($("ko-summary-filled")) $("ko-summary-filled").textContent = `${filled} / ${total} slots`;
    const link = $("ko-manage-link");
    if (link) link.href = `/control/knockout?room=${encodeURIComponent(roomId)}`;
  }

  async function saveKnockoutBracket() {
    const payload = isKnockoutPage && $("ko-bracket-editor")
      ? collectKnockout()
      : (state?.knockout || collectKnockout());
    const data = await api("/patch", {
      method: "POST",
      body: JSON.stringify({ knockout: payload }),
    });
    applyIncoming(data.state);
    return data.state;
  }

  async function showKnockoutOverlay() {
    if (isKnockoutPage) await saveKnockoutBracket();
    await api("/animate", {
      method: "POST",
      body: JSON.stringify({ animation: "knockout_round", payload: {} }),
    });
  }

  function applyIncoming(s) {
    if (!s) return;
    state = s;
    version = s.version ?? version;
    updateKnockoutSummary(s.knockout);
    if (isKnockoutPage) {
      // Don't clobber while typing unless no draft focus in bracket
      const ae = document.activeElement;
      const typing = ae && ae.closest && ae.closest("#ko-bracket-editor") && (ae.tagName === "INPUT" || ae.tagName === "TEXTAREA");
      if (!typing) renderKnockoutForm(s.knockout);
      else draft = normalizeDraft({ ...collectKnockout(), ...s.knockout, matches: draft?.matches || s.knockout?.matches });
    }
  }

  function onBoardClick(e) {
    const add = e.target.closest("[data-add-round]");
    if (add) {
      e.preventDefault();
      addMatchToRound(add.getAttribute("data-add-round"));
      return;
    }
    const trim = e.target.closest("[data-trim-round]");
    if (trim) {
      e.preventDefault();
      removeLastFromRound(trim.getAttribute("data-trim-round"));
      return;
    }
    const rm = e.target.closest("[data-remove-slot]");
    if (rm) {
      e.preventDefault();
      removeSlot(rm.getAttribute("data-remove-slot"));
      return;
    }
    const stage = e.target.closest("[data-add-stage]");
    if (stage) {
      e.preventDefault();
      addStage(stage.getAttribute("data-add-stage"));
    }
  }

  function bindUi() {
    const formatEl = $("ko-format");
    if (formatEl) {
      formatEl.addEventListener("change", () => {
        const format = koFormatOf(formatEl.value);
        draft = normalizeDraft({
          title: ($("ko-title")?.value || "KNOCKOUT ROUND").trim(),
          format,
          custom: false,
          roundDates: emptyDates(),
          matches: cloneMatches(KO_TEMPLATES[format]),
        });
        renderKnockoutForm(draft);
      });
    }
    const saveBtn = $("btn-ko-save");
    if (saveBtn) saveBtn.addEventListener("click", () => saveKnockoutBracket().catch((e) => alert(e.message)));
    const showBtn = $("btn-ko-show");
    if (showBtn) showBtn.addEventListener("click", () => showKnockoutOverlay().catch((e) => alert(e.message)));

    const board = $("ko-bracket-editor");
    if (board) {
      board.addEventListener("click", onBoardClick);
      window.addEventListener("resize", () => scheduleLinks(board));
    }

    const connectBtn = $("btn-connect");
    if (connectBtn && isKnockoutPage) {
      connectBtn.addEventListener("click", () => {
        const next = ($("room-input")?.value || "").trim() || "match1";
        window.location.href = `/control/knockout?room=${encodeURIComponent(next)}`;
      });
    }
  }

  async function bootPage() {
    bindUi();
    try {
      const data = await api("");
      applyIncoming(data.state || data);
      version = data.version || version;
      setStatus(true, "Connected");
    } catch (e) {
      setStatus(false, e.message || "Error");
    }

  if (window.CricketRealtime) {
    realtime = new CricketRealtime({
      room: roomId,
      hydrateUrl: `/api/matches/${encodeURIComponent(roomId)}`,
      pollWhenLiveMs: 2000,
      pollWhenOfflineMs: 800,
        onState: (s, ver) => {
          version = ver || version;
          applyIncoming(s);
        },
        onStatus: (status) => {
          if (status === "live") setStatus(true, "Live (Pusher)");
          else if (status === "connecting" || status === "reconnecting") setStatus(false, "Connecting…");
          else setStatus(false, "Pusher offline");
        },
      });
      realtime.start();
    }
  }

  window.KnockoutAdmin = {
    applyFromState(s) {
      state = s;
      roomId = window.CRICKET_ROOM || roomId;
      updateKnockoutSummary(s?.knockout);
    },
    setRoom(id) {
      roomId = id || roomId;
      const link = $("ko-manage-link");
      if (link) link.href = `/control/knockout?room=${encodeURIComponent(roomId)}`;
    },
    show: showKnockoutOverlay,
    save: saveKnockoutBracket,
    render: renderKnockoutForm,
    collect: collectKnockout,
    drawLinks: drawKnockoutConnectors,
  };

  if (isKnockoutPage) {
    bootPage();
  } else {
    const wireSlim = () => {
      const showBtn = $("btn-ko-show");
      if (showBtn && !showBtn.dataset.koBound) {
        showBtn.dataset.koBound = "1";
        showBtn.addEventListener("click", () => showKnockoutOverlay().catch((e) => alert(e.message)));
      }
      const link = $("ko-manage-link");
      if (link) link.href = `/control/knockout?room=${encodeURIComponent(roomId)}`;
    };
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", wireSlim);
    else wireSlim();
  }
})();
