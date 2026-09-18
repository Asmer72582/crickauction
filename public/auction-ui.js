/** Shared auction operator UI — mobile nav + toasts */
(function () {
  const body = document.body;
  if (!body.classList.contains("au-desk")) return;

  const toggle = document.getElementById("au-menu-toggle");
  const backdrop = document.getElementById("au-sidebar-backdrop");

  function closeNav() {
    body.classList.remove("au-nav-open");
  }

  toggle?.addEventListener("click", () => {
    body.classList.toggle("au-nav-open");
  });

  backdrop?.addEventListener("click", closeNav);

  document.querySelectorAll(".dash-nav a").forEach((a) => {
    a.addEventListener("click", () => {
      if (window.innerWidth <= 900) closeNav();
    });
  });

  window.auToast = function (msg) {
    let el = document.getElementById("au-toast");
    if (!el) {
      el = document.createElement("div");
      el.id = "au-toast";
      el.className = "au-toast";
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.add("is-visible");
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove("is-visible"), 2400);
  };
})();
