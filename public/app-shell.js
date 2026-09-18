(() => {
  const toggle = document.getElementById("app-nav-toggle");
  const backdrop = document.getElementById("app-sidebar-backdrop");
  const sidebar = document.getElementById("app-sidebar");

  function setOpen(open) {
    document.body.classList.toggle("app-nav-open", open);
    if (toggle) toggle.setAttribute("aria-expanded", open ? "true" : "false");
  }

  toggle?.addEventListener("click", () => {
    setOpen(!document.body.classList.contains("app-nav-open"));
  });

  backdrop?.addEventListener("click", () => setOpen(false));

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") setOpen(false);
  });

  window.addEventListener("resize", () => {
    if (window.innerWidth > 980) setOpen(false);
  });

  if (sidebar) sidebar.setAttribute("aria-hidden", "false");
})();
