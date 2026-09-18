let step = 1;
const totalSteps = 6;
const panels = document.querySelectorAll(".au-step-panel");
const stepperSteps = document.querySelectorAll(".au-stepper-step");
const btnNext = document.getElementById("btn-next-step");
const btnPrev = document.getElementById("btn-prev-step");
const tournamentPicker = document.getElementById("auction-tournament-picker");
const tournamentIdInput = document.getElementById("auction-tournament-id");
const initialTournamentId = tournamentIdInput?.dataset.initial || "";
const regModeInput = document.getElementById("registration-mode-input");

const reviewName = document.getElementById("review-name");
const reviewTournamentName = document.getElementById("review-tournament-name");
const reviewRegistrationMode = document.getElementById("review-registration-mode");
const reviewTeamCount = document.getElementById("review-team-count");
const reviewPlayerCount = document.getElementById("review-player-count");
const reviewPurse = document.getElementById("review-purse");
const reviewPricing = document.getElementById("review-pricing");
const reviewTimer = document.getElementById("review-timer");
const reviewSquad = document.getElementById("review-squad");
const reviewTeamsList = document.getElementById("review-teams-list");
const reviewPlayersList = document.getElementById("review-players-list");

const teamsList = document.getElementById("teams-list");
const teamsHidden = document.getElementById("teams-hidden");
const teamNameInput = document.getElementById("team-name-input");
const teamShortInput = document.getElementById("team-short-input");
const teamLogoInput = document.getElementById("team-logo-input");
const teamLogoPreview = document.getElementById("team-logo-preview");
const teamLogoPlaceholder = document.getElementById("team-logo-placeholder");

const playersList = document.getElementById("manual-players-list");
const playersHidden = document.getElementById("manual-players-hidden");
const playerPhotoInput = document.getElementById("player-photo-input");
const playerPhotoPreview = document.getElementById("player-photo-preview");
const playerPhotoPlaceholder = document.getElementById("player-photo-placeholder");

/** @type {{ name: string, short_name: string, owner: string, city: string, logo: File|null, previewUrl: string|null }[]} */
let teams = [];

/** @type {{ name: string, role: string, base_price: string, batting_style: string, bowling_style: string, matches: string, runs: string, highest_score: string, wickets: string, photo: File|null, previewUrl: string|null }[]} */
let players = [];

/** @type {File|null} */
let pendingTeamLogo = null;

function selectedSources() {
  const formOn = document.getElementById("mode-form")?.checked;
  const manualOn = document.getElementById("mode-manual")?.checked;
  return { form: !!formOn, manual: !!manualOn };
}

function registrationModeValue() {
  const { form, manual } = selectedSources();
  if (form && manual) return "both";
  if (form) return "form";
  return "manual";
}

function formatInr(n) {
  const num = Number(n) || 0;
  return `₹${num.toLocaleString("en-IN")}`;
}

function updateReview() {
  const mode = registrationModeValue();
  const selectedOption = tournamentPicker?.selectedOptions?.[0];
  const name = document.getElementById("auction-name-input")?.value?.trim() || "—";

  if (reviewName) reviewName.textContent = name;
  if (reviewTournamentName) {
    reviewTournamentName.textContent = selectedOption?.value
      ? selectedOption.textContent
      : "Standalone";
  }
  if (reviewRegistrationMode) {
    reviewRegistrationMode.textContent =
      mode === "both" ? "Form + Manual" : mode === "form" ? "Registration form" : "Manual player entry";
  }
  if (reviewTeamCount) reviewTeamCount.textContent = String(teams.length);

  const formSelected = document.querySelectorAll('.au-player-item input[type="checkbox"]:checked').length;
  const playerTotal =
    mode === "form" ? formSelected : mode === "manual" ? players.length : formSelected + players.length;
  if (reviewPlayerCount) reviewPlayerCount.textContent = String(playerTotal);

  const purse = document.getElementById("rule-purse")?.value;
  const base = document.getElementById("rule-base")?.value;
  const inc = document.getElementById("rule-inc")?.value;
  const timer = document.getElementById("rule-timer")?.value;
  const min = document.getElementById("rule-min")?.value;
  const max = document.getElementById("rule-max")?.value;

  if (reviewPurse) reviewPurse.textContent = formatInr(purse);
  if (reviewPricing) reviewPricing.textContent = `${formatInr(base)} / ${formatInr(inc)}`;
  if (reviewTimer) reviewTimer.textContent = `${timer || 30}s`;
  if (reviewSquad) reviewSquad.textContent = `${min || 11}–${max || 25}`;

  if (reviewTeamsList) {
    reviewTeamsList.innerHTML =
      teams.length === 0
        ? `<li class="au-muted">None yet</li>`
        : teams.map((t) => `<li>${escapeHtml(t.name)}${t.short_name ? ` (${escapeHtml(t.short_name)})` : ""}</li>`).join("");
  }
  if (reviewPlayersList) {
    reviewPlayersList.innerHTML =
      players.length === 0
        ? `<li class="au-muted">None yet</li>`
        : players.map((p) => `<li>${escapeHtml(p.name)} · ${escapeHtml(p.role)}</li>`).join("");
  }
}

function syncModeUi() {
  const sources = selectedSources();
  if (!sources.form && !sources.manual) {
    document.getElementById("mode-manual").checked = true;
    sources.manual = true;
  }
  if (regModeInput) regModeInput.value = registrationModeValue();

  document.querySelectorAll("[data-mode-card]").forEach((card) => {
    const key = card.dataset.modeCard;
    const on = key === "form" ? sources.form : sources.manual;
    card.classList.toggle("is-active", on);
  });

  document.querySelectorAll("[data-player-source]").forEach((section) => {
    const key = section.dataset.playerSource;
    section.hidden = key === "form" ? !sources.form : !sources.manual;
  });

  updateReview();
}

function syncTeamsHidden() {
  if (!teamsHidden) return;
  teamsHidden.innerHTML = "";
  teams.forEach((team, i) => {
    const name = document.createElement("input");
    name.type = "hidden";
    name.name = `teams[${i}][name]`;
    name.value = team.name;
    const shortName = document.createElement("input");
    shortName.type = "hidden";
    shortName.name = `teams[${i}][short_name]`;
    shortName.value = team.short_name;
    teamsHidden.append(name, shortName);

    if (team.owner) {
      const owner = document.createElement("input");
      owner.type = "hidden";
      owner.name = `teams[${i}][owner]`;
      owner.value = team.owner;
      teamsHidden.appendChild(owner);
    }
    if (team.city) {
      const city = document.createElement("input");
      city.type = "hidden";
      city.name = `teams[${i}][city]`;
      city.value = team.city;
      teamsHidden.appendChild(city);
    }

    if (team.logo instanceof File) {
      const fileInput = document.createElement("input");
      fileInput.type = "file";
      fileInput.name = `teams[${i}][logo]`;
      fileInput.accept = "image/*";
      fileInput.hidden = true;
      const dt = new DataTransfer();
      dt.items.add(team.logo);
      fileInput.files = dt.files;
      teamsHidden.appendChild(fileInput);
    }
  });
  updateReview();
}

function clearTeamLogoComposer() {
  pendingTeamLogo = null;
  if (teamLogoInput) teamLogoInput.value = "";
  if (teamLogoPreview) {
    teamLogoPreview.hidden = true;
    teamLogoPreview.removeAttribute("src");
  }
  if (teamLogoPlaceholder) teamLogoPlaceholder.hidden = false;
}

function renderTeams() {
  if (!teamsList) return;
  teamsList.innerHTML = "";
  if (teams.length === 0) {
    teamsList.innerHTML = `<div class="au-entry-empty">No teams yet. Add your first team below.</div>`;
  } else {
    teams.forEach((team, i) => {
      const card = document.createElement("div");
      card.className = "au-entry-card";
      const thumb = team.previewUrl
        ? `<img class="au-entry-thumb" src="${team.previewUrl}" alt="" />`
        : `<div class="au-entry-thumb au-entry-thumb-empty">No logo</div>`;
      card.innerHTML = `
        ${thumb}
        <div class="au-entry-card-main">
          <strong>${escapeHtml(team.name)}</strong>
          <span>${escapeHtml(team.short_name || "—")}${team.owner ? ` · ${escapeHtml(team.owner)}` : ""}${team.city ? ` · ${escapeHtml(team.city)}` : ""}</span>
        </div>
        <button type="button" class="au-btn au-btn-ghost au-btn-sm" data-remove-team="${i}">Remove</button>
      `;
      teamsList.appendChild(card);
    });
  }
  syncTeamsHidden();
}

function closeTeamModal() {
  const modal = document.getElementById("team-details-modal");
  if (modal) modal.hidden = true;
}

function openTeamModal() {
  const name = (teamNameInput?.value || "").trim();
  if (!name) {
    alert("Enter a team name.");
    teamNameInput?.focus();
    return;
  }
  const modal = document.getElementById("team-details-modal");
  const title = document.getElementById("team-modal-name");
  if (title) title.textContent = name;
  const ownerInput = document.getElementById("team-owner-input");
  const cityInput = document.getElementById("team-city-input");
  if (ownerInput) ownerInput.value = "";
  if (cityInput) cityInput.value = "";
  if (modal) modal.hidden = false;
  ownerInput?.focus();
}

function addTeam() {
  openTeamModal();
}

function confirmTeamFromModal() {
  const name = (teamNameInput?.value || "").trim();
  const short_name = (teamShortInput?.value || "").trim();
  const owner = (document.getElementById("team-owner-input")?.value || "").trim();
  const city = (document.getElementById("team-city-input")?.value || "").trim();
  if (!name) {
    closeTeamModal();
    alert("Enter a team name.");
    teamNameInput?.focus();
    return;
  }
  const logo = pendingTeamLogo;
  const previewUrl = logo ? URL.createObjectURL(logo) : null;
  teams.push({ name, short_name, owner, city, logo, previewUrl });
  if (teamNameInput) teamNameInput.value = "";
  if (teamShortInput) teamShortInput.value = "";
  clearTeamLogoComposer();
  closeTeamModal();
  renderTeams();
  teamNameInput?.focus();
}

function syncPlayersHidden() {
  if (!playersHidden) return;
  playersHidden.innerHTML = "";
  players.forEach((player, i) => {
    const fields = [
      ["name", player.name],
      ["role", player.role],
      ["base_price", player.base_price],
      ["batting_style", player.batting_style],
      ["bowling_style", player.bowling_style],
      ["matches", player.matches],
      ["runs", player.runs],
      ["highest_score", player.highest_score],
      ["wickets", player.wickets],
    ];
    fields.forEach(([key, value]) => {
      if (value === "" || value == null) return;
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = `manual_players[${i}][${key}]`;
      input.value = value;
      playersHidden.appendChild(input);
    });
    if (player.photo instanceof File) {
      const fileInput = document.createElement("input");
      fileInput.type = "file";
      fileInput.name = `manual_players[${i}][photo]`;
      fileInput.accept = "image/*";
      fileInput.hidden = true;
      const dt = new DataTransfer();
      dt.items.add(player.photo);
      fileInput.files = dt.files;
      playersHidden.appendChild(fileInput);
    }
  });
  updateReview();
}

function selectedRoles() {
  return [...document.querySelectorAll('input[name="player_role_picker"]:checked')].map((el) => el.value);
}

function clearPlayerComposer() {
  [
    "player-name-input",
    "player-base-input",
    "player-bat-input",
    "player-bowl-input",
    "player-matches-input",
    "player-runs-input",
    "player-hs-input",
    "player-wkts-input",
  ].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    if (id === "player-base-input") el.value = "20000";
    else el.value = "";
  });
  document.querySelectorAll('input[name="player_role_picker"]').forEach((el) => {
    el.checked = false;
  });
  document.querySelectorAll("#player-role-group .au-role-card").forEach((card) => {
    card.classList.remove("is-selected");
  });
  if (playerPhotoInput) playerPhotoInput.value = "";
  if (playerPhotoPreview) {
    playerPhotoPreview.hidden = true;
    playerPhotoPreview.removeAttribute("src");
  }
  if (playerPhotoPlaceholder) playerPhotoPlaceholder.hidden = false;
}

function renderPlayers(animateIndex = -1) {
  if (!playersList) return;
  playersList.innerHTML = "";
  if (players.length === 0) {
    playersList.innerHTML = `<div class="au-entry-empty">No player cards yet. Fill one card below and add it.</div>`;
  } else {
    players.forEach((player, i) => {
      const card = document.createElement("div");
      card.className = "au-player-rail-card";
      if (i === animateIndex) card.classList.add("is-entering");
      const thumb = player.previewUrl
        ? `<img class="au-entry-thumb" src="${player.previewUrl}" alt="" />`
        : `<div class="au-entry-thumb au-entry-thumb-empty">No photo</div>`;
      card.innerHTML = `
        ${thumb}
        <div class="au-entry-card-main">
          <strong>${escapeHtml(player.name)}</strong>
          <span>${escapeHtml(player.role)}${player.base_price ? ` · ${formatInr(player.base_price)}` : ""}</span>
        </div>
        <button type="button" class="au-btn au-btn-ghost au-btn-sm" data-remove-player="${i}">Remove</button>
      `;
      playersList.appendChild(card);
    });
  }
  syncPlayersHidden();
}

function addPlayer() {
  const name = (document.getElementById("player-name-input")?.value || "").trim();
  const roles = selectedRoles();
  if (!name) {
    alert("Enter a player name.");
    document.getElementById("player-name-input")?.focus();
    return;
  }
  if (roles.length < 1) {
    alert("Select at least one playing role.");
    return;
  }
  if (roles.length > 2) {
    alert("You can select maximum 2 playing roles.");
    return;
  }

  const photo = playerPhotoInput?.files?.[0] || null;
  const previewUrl = photo ? URL.createObjectURL(photo) : null;

  players.push({
    name,
    role: roles.join(", "),
    base_price: document.getElementById("player-base-input")?.value || "",
    batting_style: document.getElementById("player-bat-input")?.value || "",
    bowling_style: document.getElementById("player-bowl-input")?.value || "",
    matches: document.getElementById("player-matches-input")?.value || "",
    runs: document.getElementById("player-runs-input")?.value || "",
    highest_score: document.getElementById("player-hs-input")?.value || "",
    wickets: document.getElementById("player-wkts-input")?.value || "",
    photo,
    previewUrl,
  });

  clearPlayerComposer();
  renderPlayers(players.length - 1);
  document.getElementById("player-name-input")?.focus();
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}

function validateStep() {
  if (step === 1) {
    const name = document.querySelector('input[name="name"]')?.value?.trim();
    if (!name) {
      alert("Enter an auction name.");
      return false;
    }
    if (tournamentIdInput) tournamentIdInput.value = tournamentPicker?.value || "";
  }

  if (step === 2) {
    const sources = selectedSources();
    if (!sources.form && !sources.manual) {
      alert("Select at least one registration source.");
      return false;
    }
    syncModeUi();
  }

  if (step === 3 && teams.length < 2) {
    alert("Add at least 2 teams.");
    return false;
  }

  return true;
}

function showStep(n) {
  step = Math.max(1, Math.min(totalSteps, n));
  panels.forEach((el) => el.classList.toggle("active", Number(el.dataset.step) === step));
  stepperSteps.forEach((el) => {
    const s = Number(el.dataset.step);
    el.classList.toggle("active", s === step);
    el.classList.toggle("done", s < step);
  });
  if (btnPrev) btnPrev.style.visibility = step === 1 ? "hidden" : "visible";
  if (btnNext) {
    btnNext.textContent = step === totalSteps ? "Done" : "Next";
    btnNext.style.display = step === totalSteps ? "none" : "";
  }
  updateReview();
}

btnNext?.addEventListener("click", () => {
  if (!validateStep()) return;
  if (step === 1 && (tournamentPicker?.value || "") !== initialTournamentId) {
    const url = new URL(window.location.href);
    if (tournamentPicker.value) {
      url.searchParams.set("tournament", tournamentPicker.value);
    } else {
      url.searchParams.delete("tournament");
    }
    window.location.href = url.toString();
    return;
  }
  if (step < totalSteps) showStep(step + 1);
});

btnPrev?.addEventListener("click", () => showStep(step - 1));

tournamentPicker?.addEventListener("change", () => {
  if (tournamentIdInput) tournamentIdInput.value = tournamentPicker.value;
  updateReview();
});

document.getElementById("mode-form")?.addEventListener("change", syncModeUi);
document.getElementById("mode-manual")?.addEventListener("change", syncModeUi);

document.getElementById("btn-select-all")?.addEventListener("click", () => {
  document.querySelectorAll('.au-player-item input[type="checkbox"]').forEach((cb) => {
    cb.checked = true;
  });
  updateReview();
});

document.getElementById("player-search")?.addEventListener("input", (e) => {
  const q = e.target.value.toLowerCase();
  document.querySelectorAll(".au-player-item").forEach((row) => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? "" : "none";
  });
});

document.querySelectorAll('.au-player-item input[type="checkbox"]').forEach((cb) => {
  cb.addEventListener("change", updateReview);
});

["rule-purse", "rule-base", "rule-inc", "rule-timer", "rule-min", "rule-max"].forEach((id) => {
  document.getElementById(id)?.addEventListener("input", updateReview);
});

document.getElementById("auction-name-input")?.addEventListener("input", updateReview);

document.getElementById("btn-add-team")?.addEventListener("click", addTeam);
document.getElementById("team-modal-cancel")?.addEventListener("click", closeTeamModal);
document.getElementById("team-modal-confirm")?.addEventListener("click", confirmTeamFromModal);
document.getElementById("team-details-modal")?.addEventListener("click", (e) => {
  if (e.target.id === "team-details-modal") closeTeamModal();
});
["team-owner-input", "team-city-input"].forEach((id) => {
  document.getElementById(id)?.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      confirmTeamFromModal();
    }
    if (e.key === "Escape") closeTeamModal();
  });
});
teamNameInput?.addEventListener("keydown", (e) => {
  if (e.key === "Enter") {
    e.preventDefault();
    addTeam();
  }
});
teamShortInput?.addEventListener("keydown", (e) => {
  if (e.key === "Enter") {
    e.preventDefault();
    addTeam();
  }
});

teamLogoInput?.addEventListener("change", () => {
  const file = teamLogoInput.files?.[0];
  if (!file) {
    clearTeamLogoComposer();
    return;
  }
  pendingTeamLogo = file;
  if (teamLogoPreview) {
    teamLogoPreview.src = URL.createObjectURL(file);
    teamLogoPreview.hidden = false;
  }
  if (teamLogoPlaceholder) teamLogoPlaceholder.hidden = true;
});

teamsList?.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-remove-team]");
  if (!btn) return;
  const idx = Number(btn.dataset.removeTeam);
  const removed = teams.splice(idx, 1)[0];
  if (removed?.previewUrl) URL.revokeObjectURL(removed.previewUrl);
  renderTeams();
});

document.getElementById("btn-add-manual-player")?.addEventListener("click", addPlayer);

document.getElementById("player-role-group")?.addEventListener("change", (e) => {
  if (!(e.target instanceof HTMLInputElement) || e.target.type !== "checkbox") return;
  const checked = selectedRoles();
  if (checked.length > 2) {
    e.target.checked = false;
    alert("You can select maximum 2 playing roles.");
  }
  document.querySelectorAll("#player-role-group .au-role-card").forEach((card) => {
    const input = card.querySelector('input[type="checkbox"]');
    card.classList.toggle("is-selected", !!input?.checked);
  });
});

playerPhotoInput?.addEventListener("change", () => {
  const file = playerPhotoInput.files?.[0];
  if (!file || !playerPhotoPreview) return;
  const url = URL.createObjectURL(file);
  playerPhotoPreview.src = url;
  playerPhotoPreview.hidden = false;
  if (playerPhotoPlaceholder) playerPhotoPlaceholder.hidden = true;
});

playersList?.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-remove-player]");
  if (!btn) return;
  const idx = Number(btn.dataset.removePlayer);
  const removed = players.splice(idx, 1)[0];
  if (removed?.previewUrl) URL.revokeObjectURL(removed.previewUrl);
  renderPlayers();
});

document.getElementById("auction-create-form")?.addEventListener("submit", () => {
  syncModeUi();
  syncTeamsHidden();
  syncPlayersHidden();
});

renderTeams();
renderPlayers();
syncModeUi();
showStep(1);
