/**
 * Teams library page — list / add / edit / delete
 */

const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
const modal = document.getElementById("team-modal");
const form = document.getElementById("team-form");
const grid = document.getElementById("teams-grid");
const titleEl = document.getElementById("team-modal-title");
const countEl = document.getElementById("teams-count");

let teams = Array.isArray(window.INITIAL_TEAMS) ? window.INITIAL_TEAMS.slice() : [];
let editingId = null;

function esc(s) {
  return String(s ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

async function api(path, options = {}) {
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

function playersCsv(team) {
  return (team.players || []).map((p) => p.name).filter(Boolean).join(", ");
}

function openModal(editTeam = null) {
  editingId = editTeam?.id || null;
  titleEl.textContent = editingId ? "Edit Team" : "Add Team";
  document.getElementById("team-id").value = editingId || "";
  document.getElementById("team-name").value = editTeam?.name || "";
  document.getElementById("team-players-csv").value = editTeam ? playersCsv(editTeam) : "";
  modal.hidden = false;
  document.getElementById("team-name")?.focus();
}

function closeModal() {
  modal.hidden = true;
  editingId = null;
  form?.reset();
}

function renderTeams(list = teams) {
  if (countEl) countEl.textContent = `(${list.length})`;
  if (!grid) return;

  if (!list.length) {
    grid.innerHTML = `
      <div class="dash-empty" id="empty-teams">
        <h3>No teams saved yet</h3>
        <p>Add a team with comma-separated player names. It will be available to import in any match.</p>
        <button type="button" class="btn-create" id="btn-empty-team">+ Add Team</button>
      </div>`;
    document.getElementById("btn-empty-team")?.addEventListener("click", () => openModal());
    return;
  }

  grid.innerHTML = list.map((team) => {
    const players = team.players || [];
    const playerHtml = players.length
      ? players.map((p) => `<li>${esc(p.name)}</li>`).join("")
      : `<li class="empty">No players yet</li>`;
    const search = esc(`${team.name} ${players.map((p) => p.name).join(" ")}`.toLowerCase());
    return `
      <article class="team-card" data-id="${team.id}" data-search="${search}">
        <div class="team-card-head">
          <div>
            <h2>${esc(team.name)}</h2>
            <p>${players.length} players</p>
          </div>
          <div class="team-card-actions">
            <button type="button" class="btn-link ghost btn-edit-team" data-id="${team.id}">Edit</button>
            <button type="button" class="btn-link danger btn-delete-team" data-id="${team.id}">Delete</button>
          </div>
        </div>
        <ul class="team-player-list">${playerHtml}</ul>
      </article>`;
  }).join("");
}

async function refresh() {
  const data = await api("");
  teams = data.teams || [];
  renderTeams(teams);
}

document.getElementById("btn-open-team")?.addEventListener("click", () => openModal());
document.getElementById("btn-empty-team")?.addEventListener("click", () => openModal());
document.querySelectorAll("[data-close-team]").forEach((b) => b.addEventListener("click", closeModal));
modal?.addEventListener("click", (e) => {
  if (e.target === modal) closeModal();
});

document.getElementById("team-search")?.addEventListener("input", (e) => {
  const q = (e.target.value || "").trim().toLowerCase();
  if (!q) {
    renderTeams(teams);
    return;
  }
  renderTeams(teams.filter((t) => {
    const hay = `${t.name} ${(t.players || []).map((p) => p.name).join(" ")}`.toLowerCase();
    return hay.includes(q);
  }));
});

grid?.addEventListener("click", async (e) => {
  const editBtn = e.target.closest(".btn-edit-team");
  if (editBtn) {
    const id = Number(editBtn.dataset.id);
    const team = teams.find((t) => Number(t.id) === id);
    if (team) openModal(team);
    return;
  }
  const delBtn = e.target.closest(".btn-delete-team");
  if (!delBtn) return;
  const id = Number(delBtn.dataset.id);
  const team = teams.find((t) => Number(t.id) === id);
  if (!confirm(`Delete team “${team?.name || id}”?`)) return;
  try {
    await api(`/${id}`, { method: "DELETE" });
    teams = teams.filter((t) => Number(t.id) !== id);
    renderTeams(teams);
  } catch (err) {
    alert(err.message || "Delete failed");
  }
});

form?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const name = document.getElementById("team-name")?.value.trim() || "";
  const players_csv = document.getElementById("team-players-csv")?.value.trim() || "";
  if (!name) return alert("Enter a team name");
  if (!players_csv) return alert("Enter at least one player name (comma separated)");

  const btn = document.getElementById("btn-save-team");
  btn.disabled = true;
  btn.textContent = "Saving…";

  const payload = { name, players_csv };
  try {
    if (editingId) {
      const data = await api(`/${editingId}`, {
        method: "PUT",
        body: JSON.stringify(payload),
      });
      const updated = data.team;
      teams = teams.map((t) => (Number(t.id) === Number(updated.id) ? updated : t));
    } else {
      const data = await api("", {
        method: "POST",
        body: JSON.stringify(payload),
      });
      const created = data.team;
      // Upsert by name may update existing — replace if same id/name_key.
      const idx = teams.findIndex((t) => Number(t.id) === Number(created.id));
      if (idx >= 0) teams[idx] = created;
      else teams.unshift(created);
    }
    teams.sort((a, b) => String(a.name).localeCompare(String(b.name)));
    closeModal();
    renderTeams(teams);
  } catch (err) {
    alert(err.message || "Could not save team");
  } finally {
    btn.disabled = false;
    btn.textContent = "Save Team";
  }
});
