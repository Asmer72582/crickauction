(function () {
  "use strict";

  /* ── Playing role picker (max 2) ── */
  function initRolePicker() {
    const grid = document.getElementById("playing-role-grid");
    if (!grid) return;

    const max = Number(grid.closest("[data-max-roles]")?.dataset.maxRoles || 2);
    const counter = document.getElementById("role-count");
    const inputs = () => [...grid.querySelectorAll('input[type="checkbox"]')];

    function update() {
      const checked = inputs().filter((i) => i.checked);
      if (counter) counter.textContent = String(checked.length);

      inputs().forEach((input) => {
        const card = input.closest(".au-role-card");
        card?.classList.toggle("is-selected", input.checked);
        if (!input.checked && checked.length >= max) {
          input.disabled = true;
          card?.classList.add("is-disabled");
        } else {
          input.disabled = false;
          card?.classList.remove("is-disabled");
        }
      });

      updatePreviewRole();
    }

    grid.addEventListener("change", (e) => {
      const input = e.target;
      if (input.type !== "checkbox") return;
      const checked = inputs().filter((i) => i.checked);
      if (checked.length > max) {
        input.checked = false;
        showRoleHint(max, grid);
      }
      update();
    });

    update();
  }

  function showRoleHint(max, grid) {
    let el = document.getElementById("role-limit-hint");
    if (!el) {
      el = document.createElement("p");
      el.id = "role-limit-hint";
      el.className = "au-error";
      el.style.marginTop = "8px";
      grid.parentElement?.appendChild(el);
    }
    el.textContent = `You can select maximum ${max} roles.`;
    setTimeout(() => el.remove(), 2500);
  }

  /* ── Stats calculator (mirrors server CricketStatsCalculator) ── */
  function num(id) {
    const el = document.getElementById(id);
    if (!el) return 0;
    const v = parseInt(el.value, 10);
    return Number.isFinite(v) && v >= 0 ? v : 0;
  }

  function battingAverage(runs, matches) {
    if (matches < 1 || runs < 1) return null;
    return (runs / matches).toFixed(2);
  }

  function strikeRate(runs, matches, highest) {
    if (matches < 1 || runs < 1) return null;
    const rpm = runs / matches;
    const ballsPerInnings = Math.min(90, Math.max(16, rpm * 0.32 + (highest > 0 ? highest * 0.08 : 12)));
    const balls = matches * ballsPerInnings;
    if (balls < 1) return null;
    return ((runs / balls) * 100).toFixed(1);
  }

  function economy(wickets, matches) {
    if (matches < 1 || wickets < 1) return null;
    const overs = matches * 3.2 + wickets * 0.45;
    const runsConceded = wickets * 23.5 + matches * 9;
    if (overs < 0.1) return null;
    return (runsConceded / overs).toFixed(2);
  }

  function bestBowling(wickets, matches) {
    if (wickets < 1 || matches < 1) return null;
    const peak = Math.min(wickets, Math.max(1, Math.ceil((wickets / matches) * 1.6)));
    const runsInSpell = Math.max(peak * 7, Math.round(peak * 14 + 6 + (matches % 5)));
    return `${peak}/${runsInSpell}`;
  }

  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value ?? "—";
  }

  function setHidden(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? "";
  }

  function fmt(n) {
    if (n === null || n === undefined || n === "") return "—";
    return String(n);
  }

  function updateStats() {
    const matches = num("stat-matches");
    const runs = num("stat-runs");
    const highest = num("stat-highest_score");
    const wickets = num("stat-wickets");

    const avg = battingAverage(runs, matches);
    const sr = strikeRate(runs, matches, highest);
    const econ = economy(wickets, matches);
    const best = bestBowling(wickets, matches);

    setHidden("calc-average", avg);
    setHidden("calc-strike_rate", sr);
    setHidden("calc-economy", econ);
    setHidden("calc-best_bowling", best);

    setText("disp-matches-bat", matches || "—");
    setText("disp-runs-ref", runs ? `${runs} total` : "—");
    setText("disp-runs", runs || "—");
    setText("disp-highest", highest ? `${highest}` : "—");
    setText("disp-average", fmt(avg));
    setText("disp-strike_rate", sr ? `${sr}` : "—");

    setText("disp-matches-bowl", matches || "—");
    setText("disp-wickets-ref", wickets ? `${wickets} wkts` : "—");
    setText("disp-wickets", wickets || "—");
    setText("disp-economy", econ ? `${econ}` : "—");
    setText("disp-best_bowling", fmt(best));

    setText("preview-matches", matches || "—");
    setText("preview-runs", runs || "—");
    setText("preview-hs", highest || "—");
    setText("preview-wickets", wickets || "—");
  }

  function initStatsBoard() {
    const board = document.getElementById("stats-board");
    if (!board) return;

    ["stat-matches", "stat-runs", "stat-highest_score", "stat-wickets"].forEach((id) => {
      const el = document.getElementById(id);
      el?.addEventListener("input", updateStats);
    });

    board.querySelectorAll("[data-stats-tab]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const tab = btn.dataset.statsTab;
        board.querySelectorAll(".au-stats-tab").forEach((b) => {
          const active = b.dataset.statsTab === tab;
          b.classList.toggle("is-active", active);
          b.setAttribute("aria-selected", active ? "true" : "false");
        });
        board.querySelectorAll("[data-stats-panel]").forEach((panel) => {
          const show = panel.dataset.statsPanel === tab;
          panel.classList.toggle("is-active", show);
          panel.hidden = !show;
        });
      });
    });

    updateStats();
  }

  /* ── Live player preview card ── */
  function updatePreviewRole() {
    const roleEl = document.getElementById("preview-role");
    const checked = [...document.querySelectorAll('#playing-role-grid input[type="checkbox"]:checked')];
    if (roleEl) {
      roleEl.textContent = checked.length ? checked.map((i) => i.value).join(" · ") : "Playing role";
    }
  }

  function initPlayerPreview() {
    const nameInput = document.getElementById("field-full_name");
    const nameEl = document.getElementById("preview-name");
    nameInput?.addEventListener("input", () => {
      if (nameEl) nameEl.textContent = nameInput.value.trim() || "Your Name";
    });
    if (nameInput?.value && nameEl) nameEl.textContent = nameInput.value.trim();

    document.querySelectorAll(".au-file-input[data-preview='profile']").forEach((input) => {
      input.addEventListener("change", () => {
        const photo = document.getElementById("preview-photo");
        if (!photo || !input.files?.[0]) return;
        const url = URL.createObjectURL(input.files[0]);
        photo.innerHTML = `<img src="${url}" alt="" />`;
      });
    });
  }

  /* ── Card opens edit modal ── */
  function initRegEditorModal() {
    const modal = document.getElementById("reg-editor-modal");
    const openBtn = document.getElementById("open-reg-editor");
    if (!modal || !openBtn) return;

    const closeBtns = [
      document.getElementById("close-reg-editor"),
      document.getElementById("done-reg-editor"),
      document.getElementById("reg-editor-backdrop"),
    ].filter(Boolean);

    function open() {
      modal.hidden = false;
      document.body.classList.add("au-reg-modal-open");
      const first = modal.querySelector("input:not([type=hidden]), select, textarea, button");
      first?.focus?.({ preventScroll: true });
    }

    function close() {
      modal.hidden = true;
      document.body.classList.remove("au-reg-modal-open");
      updatePreviewRole();
      openBtn.focus({ preventScroll: true });
    }

    openBtn.addEventListener("click", open);
    closeBtns.forEach((btn) => btn.addEventListener("click", close));
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !modal.hidden) close();
    });

    // Open modal if a required field inside fails validation on submit
    document.getElementById("registration-form")?.addEventListener(
      "invalid",
      (e) => {
        if (modal.contains(e.target) && modal.hidden) open();
      },
      true,
    );

    // Re-open if server validation errors exist inside the modal
    if (modal.querySelector(".au-error")) open();
  }

  /* ── File upload labels ── */
  function initFileLabels() {
    document.querySelectorAll(".au-file-input").forEach((input) => {
      const nameEl = document.getElementById(`file-name-${input.id.replace("file-", "")}`);
      input.addEventListener("change", () => {
        if (nameEl) {
          nameEl.textContent = input.files?.[0]?.name || "No file chosen";
          nameEl.classList.toggle("has-file", !!input.files?.[0]);
        }
      });
    });
  }

  /* ── Ensure stats computed before submit ── */
  function initFormSubmit() {
    const form = document.getElementById("registration-form");
    form?.addEventListener("submit", () => updateStats());
  }

  initRolePicker();
  initStatsBoard();
  initPlayerPreview();
  initRegEditorModal();
  initFileLabels();
  initFormSubmit();
})();
