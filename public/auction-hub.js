document.getElementById("btn-copy-reg-link")?.addEventListener("click", async () => {
  const input = document.getElementById("reg-link");
  if (!input) return;
  try {
    await navigator.clipboard.writeText(input.value);
    window.auToast?.("Registration link copied!");
  } catch {
    input.select();
    document.execCommand("copy");
    window.auToast?.("Link copied");
  }
});

document.querySelectorAll("[data-copy-reg]").forEach((btn) => {
  btn.addEventListener("click", async () => {
    const value = btn.getAttribute("data-copy-reg") || "";
    if (!value) return;
    try {
      await navigator.clipboard.writeText(value);
      window.auToast?.("Registration link copied!");
    } catch {
      window.auToast?.("Copy the link from the auction workspace.");
    }
  });
});
