/**
 * Tournament dashboard — create flow + theme picker
 */

const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
const createModal = document.getElementById("create-modal");
const themeModal = document.getElementById("theme-modal");
const form = document.getElementById("create-form");

function openCreate() {
  createModal.hidden = false;
}

function closeCreate() {
  createModal.hidden = true;
}

function openTheme() {
  themeModal.hidden = false;
}

function closeTheme() {
  themeModal.hidden = true;
}

document.getElementById("btn-open-create")?.addEventListener("click", openCreate);
document.getElementById("btn-empty-create")?.addEventListener("click", openCreate);
document.getElementById("btn-select-theme")?.addEventListener("click", openTheme);

document.querySelectorAll("[data-close]").forEach((btn) => btn.addEventListener("click", closeCreate));
document.querySelectorAll("[data-close-theme]").forEach((btn) => btn.addEventListener("click", closeTheme));

createModal?.addEventListener("click", (e) => {
  if (e.target === createModal) closeCreate();
});
themeModal?.addEventListener("click", (e) => {
  if (e.target === themeModal) closeTheme();
});

function selectTheme(id, name, charge, accent) {
  document.getElementById("theme-id").value = id;
  document.getElementById("theme-selected-name").textContent = name;
  document.getElementById("theme-selected-charge").textContent =
    charge > 0 ? `Charges: Rs. ${charge}/-` : "Charges: Free";
  const preview = document.getElementById("theme-selected-preview");
  preview.dataset.theme = id;
  preview.style.borderLeftColor = accent || "#38bdf8";
  preview.style.background = `linear-gradient(90deg, #0a1224, ${accent || "#1e293b"}33)`;

  document.querySelectorAll(".theme-card").forEach((card) => {
    card.classList.toggle("selected", card.dataset.themeId === id);
  });
  closeTheme();
}

document.querySelectorAll(".theme-card").forEach((card) => {
  card.addEventListener("click", () => {
    selectTheme(
      card.dataset.themeId,
      card.dataset.themeName,
      parseInt(card.dataset.themeCharge, 10) || 0,
      card.dataset.themeAccent
    );
  });
});

// Search filter
const searchInput = document.getElementById("search-input");
function filterCards() {
  const q = (searchInput.value || "").trim().toLowerCase();
  document.querySelectorAll(".t-card").forEach((card) => {
    const name = card.dataset.name || "";
    card.style.display = !q || name.includes(q) ? "" : "none";
  });
}
searchInput?.addEventListener("input", filterCards);
document.getElementById("btn-search")?.addEventListener("click", filterCards);

const typeSelect = form?.querySelector('select[name="type"]');
const knockoutTeams = document.getElementById("knockout-teams");
const groupsField = document.getElementById("groups-field");
const r16Row = document.getElementById("r16-schedule-row");

function syncKnockoutFields() {
  const isKo = /knockout/i.test(typeSelect?.value || "");
  if (knockoutTeams) knockoutTeams.hidden = !isKo;
  if (groupsField) groupsField.hidden = isKo;
  if (r16Row) r16Row.hidden = !isKo;
  knockoutTeams?.querySelectorAll('input[name="teams[]"]').forEach((input) => {
    input.required = isKo;
  });
}
typeSelect?.addEventListener("change", syncKnockoutFields);
syncKnockoutFields();

form?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const fd = new FormData(form);
  const payload = Object.fromEntries(fd.entries());
  payload.wickets = parseInt(payload.wickets, 10) || 10;
  payload.groups = parseInt(payload.groups, 10) || 1;

  const isKo = /knockout/i.test(payload.type || "");
  if (isKo) {
    payload.teams = [...form.querySelectorAll('input[name="teams[]"]')].map((el) => el.value.trim());
    if (payload.teams.length !== 16 || payload.teams.some((t) => !t)) {
      alert("Enter all 16 team names for a Knockout Tournament.");
      return;
    }
  }

  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = "Creating…";

  try {
    const res = await fetch("/tournaments", {
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
    window.location.href = data.matches_url || `/tournaments/${data.tournament.id}/matches`;
  } catch (err) {
    alert(err.message || "Could not create tournament");
    btn.disabled = false;
    btn.textContent = "Create Tournament";
  }
});
