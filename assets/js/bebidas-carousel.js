/**
 * Nossas Bebidas — coverflow carousel + fluid dropdown filters.
 *
 * - Carousel: arrow buttons step one product at a time (clicks
 *   during transitions are queued so rapid clicks still advance
 *   one-by-one without visually skipping).
 * - Filters: custom fluid dropdown with sliding hover-highlight,
 *   inspired by 21st.dev/r/koustubhayadiyala36/fluid-dropdown.
 */

const PRODUCTS = [
  {
    name: "Margherita Branco Seco",
    line: "Linha Margherita · Goethe 100%",
    desc: "Mel, flores brancas e toque mineral. Homenagem à mãe do fundador.",
    meta: "750ml · 11.5% · IP Vales da Uva Goethe",
  },
  {
    name: "Margherita Demi-Sec",
    line: "Linha Margherita · Goethe 100%",
    desc: "Mesmo perfil floral elegante, com dulçor residual que amplia a maciez.",
    meta: "750ml · 11.5%",
  },
  {
    name: "Branco Seco Goethe",
    line: "Linha Mesa · Goethe / Niágara",
    desc: "Fermentado com as cascas pelo método tradicional do século XX.",
    meta: "750ml · 12.5%",
  },
  {
    name: "Branco Suave Goethe",
    line: "Linha Mesa · Goethe / Niágara",
    desc: "Aroma intenso e frutado com dulçor equilibrado — versátil e fresco.",
    meta: "750ml · 12.5%",
  },
  {
    name: "Rosé Goethe",
    line: "Linha Goethe · 100% Goethe",
    desc: "Morango, framboesa e pêssego. Demi-sec premiado, fácil de amar.",
    meta: "750ml · 11% · IP Vales da Uva Goethe",
    prize: "Medalha de Prata · 2024",
  },
  {
    name: "Tinto Seco Bordô",
    line: "Linha Mesa · Bordô",
    desc: "Vermelho vivo a violeta intenso, frutas vermelhas maduras, aveludado.",
    meta: "750ml · 11.8%",
  },
  {
    name: "Tinto Suave Bordô",
    line: "Linha Mesa · Bordô",
    desc: "Bordô com dulçor que agrega complexidade — o coringa da mesa italiana.",
    meta: "750ml · 11.8%",
  },
  {
    name: "Cabernet Sauvignon",
    line: "Linha Fino · 100% Cabernet",
    desc: "Tânico, final longo e elegante, leve evolução em madeira de carvalho.",
    meta: "750ml · 12%",
  },
  {
    name: "Merlot",
    line: "Linha Fino · 100% Merlot",
    desc: "Taninos macios, ameixa e mirtilo. Elegância e potência em equilíbrio.",
    meta: "750ml · 12.5%",
  },
  {
    name: "Espumante Moscatel Branco",
    line: "Linha Espumante · Moscatel",
    desc: "Borbulhas finas, flores brancas e mel. Para celebrar cada momento.",
    meta: "750ml · 7.5%",
  },
  {
    name: "Espumante Moscatel Rosé",
    line: "Linha Espumante · Moscatel",
    desc: "Cereja, morango e cítricos. Cremoso, acidez equilibrada, brinde perfeito.",
    meta: "750ml · 7.5%",
  },
  {
    name: "Suco de Uva Integral",
    line: "Suco · Isabel / Bordô",
    desc: "Frutos vermelhos maduros, sem álcool, sem adição de açúcar.",
    meta: "1L · sem álcool",
  },
];

const TRANSITION_MS = 550; // matches CSS transform duration

const track       = document.getElementById("bebidas-track");
const dotsWrap    = document.getElementById("bebidas-dots");
const info        = document.getElementById("bebidas-info");
const elName      = document.getElementById("bebidas-name");
const elLine      = document.getElementById("bebidas-line");
const elDesc      = document.getElementById("bebidas-desc");
const elMeta      = document.getElementById("bebidas-meta");
const elPrize     = document.getElementById("bebidas-prize");
const elPrizeLbl  = document.getElementById("bebidas-prize-label");
const prevBtn     = document.querySelector(".bebidas__arrow--prev");
const nextBtn     = document.querySelector(".bebidas__arrow--next");

if (track && dotsWrap && info) initCarousel();
initFluidDropdowns();

// — Carousel —
function initCarousel() {
  const allCards = Array.from(track.querySelectorAll(".bebidas__card"));
  let visible = allCards.slice();
  let active  = 0;

  // — Click queueing: never advance more than one card per transition
  //   frame. Extra clicks queue up and fire sequentially.
  let isTransitioning = false;
  let queuedDir = 0;
  function advance(dir) {
    if (isTransitioning) {
      // collapse rapid taps into a single queued step
      queuedDir = Math.sign(dir);
      return;
    }
    setActive(active + dir);
    isTransitioning = true;
    setTimeout(() => {
      isTransitioning = false;
      if (queuedDir !== 0) {
        const d = queuedDir;
        queuedDir = 0;
        advance(d);
      }
    }, TRANSITION_MS + 30);
  }

  function buildDots() {
    dotsWrap.innerHTML = "";
    visible.forEach((_, i) => {
      const dot = document.createElement("button");
      dot.type = "button";
      dot.className = "bebidas__dot";
      dot.setAttribute("role", "tab");
      dot.setAttribute("aria-label", `Bebida ${i + 1} de ${visible.length}`);
      dot.addEventListener("click", () => setActive(i));
      dotsWrap.appendChild(dot);
    });
  }

  function setActive(i) {
    if (!visible.length) return;
    active = ((i % visible.length) + visible.length) % visible.length;

    allCards.forEach((card) => {
      const visIdx = visible.indexOf(card);
      if (visIdx === -1) {
        card.style.display = "none";
        return;
      }
      card.style.display = "";
      const offset = visIdx - active;
      card.style.setProperty("--offset", String(offset));
      card.style.setProperty("--abs-offset", String(Math.abs(offset)));
      card.dataset.far = Math.abs(offset) > 3 ? "true" : "false";
      card.classList.toggle("is-active", offset === 0);
    });

    dotsWrap.querySelectorAll(".bebidas__dot").forEach((d, idx) => {
      d.classList.toggle("is-active", idx === active);
      d.setAttribute("aria-selected", String(idx === active));
    });

    info.classList.add("is-transitioning");
    setTimeout(() => {
      const card = visible[active];
      const idx  = allCards.indexOf(card);
      const data = PRODUCTS[idx] || PRODUCTS[0];
      elName.textContent = data.name;
      elLine.textContent = data.line;
      elDesc.textContent = data.desc;
      elMeta.textContent = data.meta;
      if (data.prize) {
        elPrizeLbl.textContent = data.prize;
        elPrize.hidden = false;
      } else {
        elPrize.hidden = true;
      }
      info.classList.remove("is-transitioning");
    }, 170);
  }

  // — Wired interactions —
  // Arrows: stopPropagation so the click never bubbles to anything
  // else (track, cards, document) that could trigger a second advance.
  prevBtn?.addEventListener("click", (e) => { e.stopPropagation(); advance(-1); });
  nextBtn?.addEventListener("click", (e) => { e.stopPropagation(); advance( 1); });

  document.addEventListener("keydown", (e) => {
    if (!isSectionInView()) return;
    if (e.key === "ArrowLeft")  { e.preventDefault(); advance(-1); }
    if (e.key === "ArrowRight") { e.preventDefault(); advance( 1); }
  });

  function isSectionInView() {
    const section = document.getElementById("bebidas");
    if (!section) return false;
    const r = section.getBoundingClientRect();
    return r.top < window.innerHeight * 0.6 && r.bottom > window.innerHeight * 0.4;
  }

  // Touch swipe — restricted to a single advance per gesture
  let touchStartX = null;
  let touchHandled = false;
  track.addEventListener("touchstart", (e) => {
    touchStartX = e.touches[0].clientX;
    touchHandled = false;
  }, { passive: true });
  track.addEventListener("touchend", (e) => {
    if (touchStartX == null || touchHandled) return;
    const dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) > 50) {
      touchHandled = true;
      advance(dx < 0 ? 1 : -1);
    }
    touchStartX = null;
  });

  // NOTE: We intentionally do NOT add click-to-jump on side cards.
  // The previous implementation let a user click any visible bottle
  // to jump to it — but on a coverflow with 5 visible bottles per
  // side, this made it easy to click a card 2–3 positions away by
  // accident and perceive it as the arrow "skipping" products.
  // Navigation is now strictly: arrows + dots + keyboard + swipe.

  // Filter via fluid dropdown CustomEvent
  document.addEventListener("bebidas:filter", (e) => {
    const { filter, value } = e.detail;
    const ln = document.querySelector('.fluid-dd[data-filter="line"] .fluid-dd__value')?.dataset.value || "";
    const tp = document.querySelector('.fluid-dd[data-filter="type"] .fluid-dd__value')?.dataset.value || "";
    visible = allCards.filter((c) =>
      (!ln || c.dataset.line === ln) &&
      (!tp || c.dataset.type === tp)
    );
    if (!visible.length) visible = allCards.slice();
    buildDots();
    setActive(0);
  });

  // Initial render — first product as focal (left-to-right reading)
  buildDots();
  setActive(0);
}

// — Fluid Dropdown —
function initFluidDropdowns() {
  const dropdowns = document.querySelectorAll(".fluid-dd");
  dropdowns.forEach((dd) => {
    const trigger   = dd.querySelector(".fluid-dd__trigger");
    const panel     = dd.querySelector(".fluid-dd__panel");
    const highlight = dd.querySelector(".fluid-dd__highlight");
    const items     = Array.from(dd.querySelectorAll(".fluid-dd__item"));
    const valueEl   = dd.querySelector(".fluid-dd__value");
    if (!trigger || !panel || !items.length) return;

    const close = () => {
      dd.classList.remove("is-open");
      trigger.setAttribute("aria-expanded", "false");
    };
    const open = () => {
      // Close any other open dropdown
      document.querySelectorAll(".fluid-dd.is-open").forEach((d) => {
        if (d !== dd) d.classList.remove("is-open");
      });
      dd.classList.add("is-open");
      trigger.setAttribute("aria-expanded", "true");
      // Position highlight on currently active item
      const active = panel.querySelector(".fluid-dd__item.is-active") || items[0];
      moveHighlight(active);
    };

    function moveHighlight(target) {
      if (!target || !highlight) return;
      const rect = target.getBoundingClientRect();
      const parentRect = panel.getBoundingClientRect();
      highlight.style.transform = `translateY(${rect.top - parentRect.top - 6}px)`;
      highlight.style.height = `${rect.height}px`;
    }

    trigger.addEventListener("click", (e) => {
      e.stopPropagation();
      dd.classList.contains("is-open") ? close() : open();
    });

    items.forEach((item) => {
      item.addEventListener("mouseenter", () => moveHighlight(item));
      item.addEventListener("focus",      () => moveHighlight(item));
      item.addEventListener("click", () => {
        items.forEach((b) => b.classList.remove("is-active"));
        item.classList.add("is-active");
        const value = item.dataset.value || "";
        const label = item.textContent.trim();
        valueEl.textContent = label.replace(/Todas as linhas|Todos os tipos/, value === "" ? (dd.dataset.filter === "line" ? "Todas" : "Todos") : label);
        valueEl.dataset.value = value;
        document.dispatchEvent(new CustomEvent("bebidas:filter", {
          detail: { filter: dd.dataset.filter, value }
        }));
        close();
      });
    });

    // Mark "All" as initial active
    items[0].classList.add("is-active");

    // Click outside / Escape to close
    document.addEventListener("click", (e) => {
      if (!dd.contains(e.target)) close();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") close();
    });
  });
}
