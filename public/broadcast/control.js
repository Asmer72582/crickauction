/**
 * Broadcast control panel — theme / panel / branding / token
 */

const csrf = window.BC.csrf;
const matchId = window.BC.matchId;
const form = document.getElementById("bc-form");
let activePanel = document.querySelector(".bc-pbtn.active")?.dataset.panel || "full";

document.querySelectorAll("#panel-btns .bc-pbtn").forEach((btn) => {
  btn.addEventListener("click", () => {
    document.querySelectorAll("#panel-btns .bc-pbtn").forEach((b) => b.classList.remove("active"));
    btn.classList.add("active");
    activePanel = btn.dataset.panel;
    document.getElementById("panel-name").textContent = window.BC.panels[activePanel] || activePanel;
  });
});

document.getElementById("theme_id")?.addEventListener("change", (e) => {
  const t = window.BC.themes[e.target.value];
  if (t) document.getElementById("theme-name").textContent = t.name;
});

form?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const fd = new FormData(form);
  const payload = {
    theme_id: fd.get("theme_id"),
    active_panel: activePanel,
    enabled: fd.get("enabled") === "1",
    regenerate_token: fd.get("regenerate_token") === "1",
    branding: {
      poweredBy: fd.get("poweredBy") || "",
      sponsorText: fd.get("sponsorText") || "",
    },
  };

  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = "Saving…";

  try {
    const res = await fetch(`/broadcast/match/${matchId}`, {
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
    if (!res.ok) throw new Error(data.message || "Save failed");

    document.getElementById("obs-url").textContent = data.obs_url;
    document.getElementById("vmix-url").textContent = data.vmix_url;
    const preview = data.obs_url + (data.obs_url.includes("?") ? "&" : "?") + "preview=1";
    document.getElementById("preview-link").href = preview;
    document.getElementById("preview-frame").src = preview + "&t=" + Date.now();
    btn.textContent = "Saved!";
    setTimeout(() => { btn.textContent = "Save Broadcast Settings"; btn.disabled = false; }, 1000);
  } catch (err) {
    alert(err.message || "Could not save");
    btn.disabled = false;
    btn.textContent = "Save Broadcast Settings";
  }
});

document.querySelectorAll(".btn-copy").forEach((btn) => {
  btn.addEventListener("click", async () => {
    const id = btn.dataset.target;
    const text = document.getElementById(id)?.textContent?.trim();
    if (!text) return;
    try {
      await navigator.clipboard.writeText(text);
      const orig = btn.textContent;
      btn.textContent = "Copied!";
      setTimeout(() => { btn.textContent = orig; }, 1200);
    } catch {
      prompt("Copy URL", text);
    }
  });
});
