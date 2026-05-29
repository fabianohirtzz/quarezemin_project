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
  document.querySelectorAll(".nav-item, .nav-drawer__item").forEach((a) => {
    const href = (a.getAttribute("href") || "").toLowerCase();
    if (!href || href.startsWith("http") || href.startsWith("mailto:") || href.startsWith("tel:")) return;
    if (href === path) a.classList.add("is-active");
    if (path === "" && href === "index.html") a.classList.add("is-active");
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
