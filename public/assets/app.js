// Tiny vanilla JS — no framework. Three concerns only:
//   1. Theme toggle (light / dark, persisted in localStorage)
//   2. Console panel collapse / expand
//   3. Dynamic key-value row add / remove on the form screens

(() => {
    "use strict";

    // ── Theme toggle ──────────────────────────────────────────────────────
    const root = document.documentElement;
    const stored = localStorage.getItem("logdb-sample-theme");
    if (stored === "dark") root.dataset.theme = "dark";

    document.querySelectorAll("[data-theme-toggle]").forEach((btn) => {
        btn.addEventListener("click", () => {
            const next = root.dataset.theme === "dark" ? "light" : "dark";
            root.dataset.theme = next;
            localStorage.setItem("logdb-sample-theme", next);
        });
    });

    // ── Console panel collapse ────────────────────────────────────────────
    document.querySelectorAll("[data-console-toggle]").forEach((btn) => {
        btn.addEventListener("click", () => {
            const panel = btn.closest(".console-panel");
            if (!panel) return;
            const collapsed = panel.dataset.collapsed === "true";
            panel.dataset.collapsed = collapsed ? "false" : "true";
            btn.textContent = collapsed ? "collapse" : "expand";
        });
    });

    // Auto-scroll the console to the most recent line on load.
    document.querySelectorAll(".console-content").forEach((el) => {
        el.scrollTop = 0; // newest is at the top in our render order
    });

    // ── Dynamic key/value rows ────────────────────────────────────────────
    // Buttons with `data-add="<rows-id>"` clone the LAST row of `#<rows-id>`,
    // empty its inputs, and append it. Inputs with `data-remove` delete the
    // closest `.kv-row`.
    document.body.addEventListener("click", (e) => {
        const target = e.target;
        if (!(target instanceof HTMLElement)) return;

        if (target.matches("[data-add]")) {
            const id = target.getAttribute("data-add");
            const container = id ? document.getElementById(id) : null;
            if (!container) return;
            const last = container.querySelector(".kv-row:last-of-type");
            if (!last) return;
            const clone = last.cloneNode(true);
            clone.querySelectorAll("input").forEach((input) => {
                input.value = "";
                input.removeAttribute("placeholder");
            });
            container.appendChild(clone);
        }

        if (target.matches("[data-remove]")) {
            const row = target.closest(".kv-row");
            if (row && row.parentElement && row.parentElement.querySelectorAll(".kv-row").length > 1) {
                row.remove();
            } else if (row) {
                row.querySelectorAll("input").forEach((input) => (input.value = ""));
            }
        }
    });
})();
