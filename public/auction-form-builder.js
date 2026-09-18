const root = document.getElementById("form-builder");
if (!root) throw new Error("form builder missing");

let schema = window.FORM_SCHEMA || { sections: [] };
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

function uid() {
  return "f_" + Math.random().toString(36).slice(2, 9);
}

function fieldRow(section, field, si, fi) {
  const row = document.createElement("div");
  row.className = "au-field-row";
  row.innerHTML = `
    <span class="au-field-drag" title="Drag">☰</span>
    <div class="au-field-controls">
      <input type="text" value="${(field.label || "").replace(/"/g, "&quot;")}" data-field-label placeholder="Field label" />
      <div class="au-field-meta">
        <select data-field-type>
          ${["text","long_text","number","date","dropdown","radio","checkbox","phone","email","image","file"]
            .map((t) => `<option value="${t}" ${field.type === t ? "selected" : ""}>${t.replace("_", " ")}</option>`).join("")}
        </select>
        <label><input type="checkbox" data-field-required ${field.required ? "checked" : ""} /> Required</label>
        <label><input type="checkbox" data-field-visible ${field.visible !== false ? "checked" : ""} /> Visible</label>
      </div>
    </div>
    <button type="button" class="au-field-remove" data-remove-field title="Remove">×</button>
  `;
  row.querySelector("[data-remove-field]")?.addEventListener("click", () => {
    section.fields.splice(fi, 1);
    render();
  });
  row.querySelector("[data-field-label]")?.addEventListener("input", (e) => { field.label = e.target.value; });
  row.querySelector("[data-field-type]")?.addEventListener("change", (e) => { field.type = e.target.value; });
  row.querySelector("[data-field-required]")?.addEventListener("change", (e) => { field.required = e.target.checked; });
  row.querySelector("[data-field-visible]")?.addEventListener("change", (e) => { field.visible = e.target.checked; });
  return row;
}

function render() {
  const sectionsRoot = document.getElementById("sections-root");
  sectionsRoot.innerHTML = "";
  (schema.sections || []).forEach((section, si) => {
    const sec = document.createElement("div");
    sec.className = "au-section";
    sec.innerHTML = `<div class="au-section-title"><input type="text" value="${(section.title || "").replace(/"/g, "&quot;")}" data-section-title placeholder="SECTION TITLE" /></div>`;
    const body = document.createElement("div");
    (section.fields || []).forEach((field, fi) => body.appendChild(fieldRow(section, field, si, fi)));
    sec.appendChild(body);
    sec.querySelector("[data-section-title]")?.addEventListener("input", (e) => { section.title = e.target.value; });
    sectionsRoot.appendChild(sec);
  });
}

document.getElementById("btn-add-section")?.addEventListener("click", () => {
  schema.sections = schema.sections || [];
  schema.sections.push({ id: uid(), title: "NEW SECTION", fields: [] });
  render();
});

document.getElementById("btn-add-field")?.addEventListener("click", () => {
  if (!schema.sections?.length) {
    schema.sections = [{ id: uid(), title: "DETAILS", fields: [] }];
  }
  const section = schema.sections[schema.sections.length - 1];
  section.fields.push({
    id: uid(),
    type: "text",
    label: "New Field",
    required: false,
    visible: true,
    placeholder: "",
    help: "",
    options: [],
  });
  render();
});

document.getElementById("btn-save-form")?.addEventListener("click", async () => {
  document.querySelectorAll(".au-section").forEach((el, si) => {
    const title = el.querySelector("[data-section-title]")?.value;
    if (schema.sections[si]) schema.sections[si].title = title;
  });
  const btn = document.getElementById("btn-save-form");
  btn.disabled = true;
  btn.textContent = "Saving…";
  try {
    const res = await fetch(root.dataset.saveUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": csrf, Accept: "application/json" },
      body: JSON.stringify({
        name: document.getElementById("form-name")?.value,
        status: document.getElementById("form-status")?.value,
        deadline_at: document.getElementById("form-deadline")?.value || null,
        schema,
      }),
    });
    if (res.ok) window.auToast?.("Form saved successfully");
    else window.auToast?.("Save failed — try again");
  } finally {
    btn.disabled = false;
    btn.textContent = "Save";
  }
});

render();
