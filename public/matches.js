/**
 * Tournament matches — create match + mandatory toss gate
 * Team A/B: searchable combobox (select saved team OR type a new name)
 */

const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
const tournamentId = window.TOURNAMENT_ID;
const modal = document.getElementById("match-modal");
const form = document.getElementById("match-form");
const titleEl = document.getElementById("match-modal-title");
const tossModal = document.getElementById("toss-modal");

let pendingToss = null; // { roomId, teamA, teamB, matchNo, controlUrl, matchesUrl }
let tossWinnerKey = ""; // "A" | "B"
let tossDecision = ""; // "bat" | "bowl"
let savedTeams = Array.isArray(window.SAVED_TEAMS) ? window.SAVED_TEAMS.slice() : [];

function esc(s) {
  return String(s ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

async function refreshSavedTeams() {
  try {
    // Full team library (not filtered by tournament) so every saved team is selectable
    const res = await fetch(`/api/teams`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    const data = await res.json().catch(() => ({}));
    if (res.ok && Array.isArray(data.teams)) {
      savedTeams = data.teams;
    }
  } catch {
    /* keep cached list */
  }
}

function teamNames() {
  return savedTeams
    .map((t) => String(t.name || "").trim())
    .filter(Boolean)
    .sort((a, b) => a.localeCompare(b));
}

function setupTeamCombo(inputId, listId) {
  const input = document.getElementById(inputId);
  const list = document.getElementById(listId);
  if (!input || !list) return;

  let activeIdx = -1;

  const hide = () => {
    list.hidden = true;
    list.innerHTML = "";
    activeIdx = -1;
  };

  const showOptions = () => {
    const q = (input.value || "").trim();
    const qLower = q.toLowerCase();
    const names = teamNames();
    const matches = q
      ? names.filter((n) => n.toLowerCase().includes(qLower)).slice(0, 12)
      : names.slice(0, 12);

    const exact = names.some((n) => n.toLowerCase() === qLower);
    const items = [];

    matches.forEach((name) => {
      items.push({ type: "pick", name, label: name });
    });
    if (q && !exact) {
      items.push({ type: "create", name: q, label: `Add new team “${q}”` });
    }
    if (!items.length) {
      items.push({
        type: "empty",
        name: "",
        label: q ? "Keep typing a new team name…" : "No saved teams yet — type a name",
      });
    }

    list.innerHTML = items
      .map((it, i) => {
        const cls = [
          "team-combo-option",
          it.type === "create" ? "is-create" : "",
          it.type === "empty" ? "is-empty" : "",
          i === activeIdx ? "is-active" : "",
        ].filter(Boolean).join(" ");
        return `<li class="${cls}" role="option" data-type="${it.type}" data-name="${esc(it.name)}" data-idx="${i}">${esc(it.label)}</li>`;
      })
      .join("");
    list.hidden = false;
  };

  const pick = (name) => {
    if (!name) return;
    input.value = name;
    hide();
    input.dispatchEvent(new Event("change", { bubbles: true }));
  };

  input.addEventListener("focus", () => {
    activeIdx = -1;
    showOptions();
  });
  input.addEventListener("input", () => {
    activeIdx = -1;
    showOptions();
  });
  input.addEventListener("keydown", (e) => {
    if (list.hidden) {
      if (e.key === "ArrowDown") showOptions();
      return;
    }
    const opts = [...list.querySelectorAll(".team-combo-option:not(.is-empty)")];
    if (e.key === "ArrowDown") {
      e.preventDefault();
      activeIdx = Math.min(opts.length - 1, activeIdx + 1);
      showOptions();
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      activeIdx = Math.max(0, activeIdx - 1);
      showOptions();
    } else if (e.key === "Enter") {
      const choice = opts[activeIdx] || opts.find((o) => o.dataset.type === "create");
      if (choice && choice.dataset.name) {
        e.preventDefault();
        pick(choice.dataset.name);
      }
    } else if (e.key === "Escape") {
      hide();
    }
  });

  list.addEventListener("mousedown", (e) => {
    e.preventDefault(); // keep focus on input
    const opt = e.target.closest(".team-combo-option");
    if (!opt || opt.classList.contains("is-empty")) return;
    pick(opt.dataset.name || "");
  });

  input.addEventListener("blur", () => {
    setTimeout(hide, 140);
  });
}

setupTeamCombo("team-a-name", "team-a-list");
setupTeamCombo("team-b-name", "team-b-list");

function openMatchModal(asSchedule = false) {
  titleEl.textContent = asSchedule ? "Schedule Match" : "Create Match";
  const dt = document.getElementById("scheduled-at");
  if (!dt.value) {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    dt.value = now.toISOString().slice(0, 16);
  }
  modal.hidden = false;
  refreshSavedTeams().then(() => {
    document.getElementById("team-a-name")?.focus();
  });
}

function closeMatchModal() {
  modal.hidden = true;
}

function openTossModal(ctx) {
  pendingToss = ctx;
  tossWinnerKey = "";
  tossDecision = "";
  const aBtn = document.getElementById("toss-pick-a");
  const bBtn = document.getElementById("toss-pick-b");
  if (aBtn) aBtn.textContent = ctx.teamA || "Team A";
  if (bBtn) bBtn.textContent = ctx.teamB || "Team B";
  document.querySelectorAll("#toss-modal .toss-choice").forEach((b) => b.classList.remove("active"));
  const label = document.getElementById("toss-match-label");
  if (label) {
    label.textContent = `Match #${ctx.matchNo || "—"} · ${ctx.teamA || "Team A"} vs ${ctx.teamB || "Team B"}. Complete the toss before scoring.`;
  }
  updateTossPreview();
  tossModal.hidden = false;
}

function updateTossPreview() {
  const el = document.getElementById("toss-preview");
  if (!el || !pendingToss) return;
  if (!tossWinnerKey || !tossDecision) {
    el.textContent = "Select winner and bat/bowl.";
    return;
  }
  const winner = tossWinnerKey === "B" ? pendingToss.teamB : pendingToss.teamA;
  el.textContent = `'${winner}' won the toss and elected to ${tossDecision}.`;
}

document.getElementById("btn-open-match")?.addEventListener("click", () => openMatchModal(false));
document.getElementById("btn-schedule-match")?.addEventListener("click", () => openMatchModal(true));
document.getElementById("btn-empty-match")?.addEventListener("click", () => openMatchModal(false));
document.querySelectorAll("[data-close-match]").forEach((b) => b.addEventListener("click", closeMatchModal));
modal?.addEventListener("click", (e) => {
  if (e.target === modal) closeMatchModal();
});

// Toss modal is non-dismissible (no close button / no backdrop dismiss).
tossModal?.addEventListener("click", (e) => {
  e.stopPropagation();
});

document.getElementById("toss-winner-choices")?.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-winner]");
  if (!btn) return;
  tossWinnerKey = btn.dataset.winner === "B" ? "B" : "A";
  document.querySelectorAll("#toss-winner-choices .toss-choice").forEach((b) => {
    b.classList.toggle("active", b === btn);
  });
  updateTossPreview();
});

document.querySelectorAll("#toss-modal [data-decision]").forEach((btn) => {
  btn.addEventListener("click", () => {
    tossDecision = btn.dataset.decision === "bowl" ? "bowl" : "bat";
    document.querySelectorAll("#toss-modal [data-decision]").forEach((b) => {
      b.classList.toggle("active", b === btn);
    });
    updateTossPreview();
  });
});

document.getElementById("btn-confirm-toss")?.addEventListener("click", async () => {
  if (!pendingToss?.roomId) return;
  if (!tossWinnerKey || !tossDecision) {
    alert("Select toss winner and bat/bowl decision.");
    return;
  }
  const winner = tossWinnerKey === "B" ? pendingToss.teamB : pendingToss.teamA;
  const btn = document.getElementById("btn-confirm-toss");
  btn.disabled = true;
  btn.textContent = "Saving…";
  try {
    const res = await fetch(`/api/matches/${encodeURIComponent(pendingToss.roomId)}/patch`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-TOKEN": csrf,
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        toss: {
          winner,
          decision: tossDecision,
          text: `'${winner}' won the toss and elected to ${tossDecision}`,
        },
      }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || data.message || "Failed to save toss");
    window.location.href = pendingToss.controlUrl || pendingToss.matchesUrl || `/tournaments/${tournamentId}/matches`;
  } catch (err) {
    alert(err.message || "Could not save toss");
    btn.disabled = false;
    btn.textContent = "Save Toss & Continue";
  }
});

document.getElementById("match-search")?.addEventListener("input", (e) => {
  const q = (e.target.value || "").trim().toLowerCase();
  document.querySelectorAll(".match-card").forEach((card) => {
    const hay = card.dataset.search || "";
    card.style.display = !q || hay.includes(q) ? "" : "none";
  });
});

document.querySelectorAll(".btn-copy-overlay").forEach((btn) => {
  btn.addEventListener("click", async () => {
    try {
      await navigator.clipboard.writeText(btn.dataset.url);
      btn.textContent = "Copied!";
      setTimeout(() => { btn.textContent = "Copy Overlay"; }, 1200);
    } catch {
      prompt("Copy overlay URL:", btn.dataset.url);
    }
  });
});

form?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const fd = new FormData(form);
  const payload = Object.fromEntries(fd.entries());
  payload.team_a_name = String(payload.team_a_name || "").trim();
  payload.team_b_name = String(payload.team_b_name || "").trim();
  if (!payload.team_a_name || !payload.team_b_name) {
    alert("Enter both Team A and Team B.");
    return;
  }
  if (payload.team_a_name.toLowerCase() === payload.team_b_name.toLowerCase()) {
    alert("Team A and Team B must be different.");
    return;
  }
  payload.overs = parseInt(payload.overs, 10) || 20;
  if (payload.match_no) payload.match_no = parseInt(payload.match_no, 10);
  else delete payload.match_no;
  if (payload.scheduled_at) {
    payload.scheduled_at = payload.scheduled_at.replace("T", " ") + ":00";
  }

  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = "Creating…";

  try {
    const res = await fetch(`/tournaments/${tournamentId}/matches`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-TOKEN": csrf,
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!res.ok) {
      const msg = data.message || Object.values(data.errors || {}).flat().join("\n") || "Failed";
      throw new Error(msg);
    }
    closeMatchModal();
    form.reset();
    openTossModal({
      roomId: data.match?.room_id,
      teamA: data.match?.team_a_name || payload.team_a_name,
      teamB: data.match?.team_b_name || payload.team_b_name,
      matchNo: data.match?.match_no,
      controlUrl: data.control_url,
      matchesUrl: data.matches_url || `/tournaments/${tournamentId}/matches`,
    });
  } catch (err) {
    alert(err.message || "Could not create match");
  } finally {
    btn.disabled = false;
    btn.textContent = "Create Match";
  }
});
