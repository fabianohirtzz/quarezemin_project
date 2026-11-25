/**
 * Site chrome — shared across every page.
 *  · Mobile drawer toggle (hamburger ↔ X)
 *  · Active-nav highlight based on the current pathname
 *  · IntersectionObserver-driven reveal for [data-reveal] elements
 *  · "is-loading" body class is cleared on first paint
 */

(() => {
  // — Reveal header / intro after first paint —
  requestAnimationFrame(() => {
    requestAnimationFrame(() => document.body.classList.remove("is-loading"));
  });

  // — Mobile drawer toggle —
  const toggle = document.querySelector(".nav-toggle");
  const drawer = document.getElementById("nav-drawer");
  if (toggle && drawer) {
    toggle.addEventListener("click", () => {
      const open = drawer.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", String(open));
    });
    drawer.querySelectorAll(".nav-drawer__item").forEach((link) => {
      link.addEventListener("click", () => {
        drawer.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && drawer.classList.contains("is-open")) {
        drawer.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
      }
    });
  }

  // — Active nav state based on current path —
  const path = (location.pathname.split("/").pop() || "index.html").toLowerCase();
  document.querySelectorAll(".nav-item, .nav-drawer__item, .nav-dropdown__item").forEach((a) => {
    const href = (a.getAttribute("href") || "").toLowerCase();
    if (!href || href.startsWith("http") || href.startsWith("mailto:") || href.startsWith("tel:")) return;
    if (href === path) a.classList.add("is-active");
    if (path === "" && href === "index.html") a.classList.add("is-active");
  });

  // — Submenu "Nossa História": hover no desktop; toque/teclado pela seta —
  document.querySelectorAll(".nav-group").forEach((group) => {
    const btn = group.querySelector(".nav-group__toggle");
    if (!btn) return;
    const set = (open) => {
      group.classList.toggle("is-open", open);
      group.classList.toggle("is-closed", !open);
      btn.setAttribute("aria-expanded", String(open));
    };
    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      set(!group.classList.contains("is-open"));
    });
    group.addEventListener("mouseenter", () => group.classList.remove("is-closed"));
    group.addEventListener("keydown", (e) => {
      if (e.key === "Escape") { set(false); btn.focus(); }
    });
    document.addEventListener("click", (e) => {
      if (!group.contains(e.target) && group.classList.contains("is-open")) set(false);
    });
    // Página do submenu aberta: o item pai também fica marcado
    if (group.querySelector(".nav-dropdown .is-active")) {
      group.querySelector(".nav-item--parent")?.classList.add("is-active");
    }
  });

  // — IntersectionObserver reveal —
  const revealEls = document.querySelectorAll("[data-reveal]");
  if (revealEls.length && "IntersectionObserver" in window) {
    const obs = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            e.target.classList.add("is-revealed");
            obs.unobserve(e.target);
          }
        });
      },
      { threshold: 0.18 }
    );
    // Also observe sections that pre-declared reveal hooks on themselves.
    document.querySelectorAll(".origem").forEach((s) => obs.observe(s));
    revealEls.forEach((el) => obs.observe(el));
  } else {
    revealEls.forEach((el) => el.classList.add("is-revealed"));
  }
})();

// Assinatura Freela In Home: a taça se enche quando o rodapé entra na tela
(function () {
  const sig = document.querySelector(".credit-sig");
  if (!sig) return;
  if (!("IntersectionObserver" in window)) { sig.classList.add("is-filled"); return; }
  const io = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) { sig.classList.add("is-filled"); io.disconnect(); }
    });
  }, { threshold: 0.6 });
  io.observe(sig);
})();

// Horários de visitação vindos do painel (rodapé e página de visitas).
// Sem PHP (prévia estática) ficam os horários escritos no HTML.
(function () {
  const alvos = document.querySelectorAll("[data-horarios]");
  if (!alvos.length) return;
  fetch("api/horarios.php", { headers: { Accept: "application/json" } })
    .then((r) => (r.ok ? r.json() : null))
    .then((j) => {
      if (!j || !j.ok || !Array.isArray(j.horarios) || !j.horarios.length) return;
      alvos.forEach((el) => {
        el.replaceChildren(...j.horarios.map((h) => {
          const s = document.createElement("span");
          s.textContent = `${h.dias} · ${h.horas}`;
          return s;
        }));
      });
    })
    .catch(() => {});
})();
