/**
 * Nossas Bebidas — coverflow carousel.
 *
 * Inspired by privios.com/nuestros-vinos: bottles in a horizontal
 * lineup, the focal one centered and enlarged, side ones dimmed.
 * Arrow buttons + dots + filters + keyboard + touch swipe.
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

const track       = document.getElementById("bebidas-track");
const dotsWrap    = document.getElementById("bebidas-dots");
const info        = document.getElementById("bebidas-info");
const elName      = document.getElementById("bebidas-name");
const elLine      = document.getElementById("bebidas-line");
const elDesc      = document.getElementById("bebidas-desc");
const elMeta      = document.getElementById("bebidas-meta");
const elPrize     = document.getElementById("bebidas-prize");
const elPrizeLbl  = document.getElementById("bebidas-prize-label");
const filterLine  = document.getElementById("filter-line");
const filterType  = document.getElementById("filter-type");
const prevBtn     = document.querySelector(".bebidas__arrow--prev");
const nextBtn     = document.querySelector(".bebidas__arrow--next");

if (track && dotsWrap && info) initCarousel();

function initCarousel() {
  const allCards = Array.from(track.querySelectorAll(".bebidas__card"));
  let visible = allCards.slice();   // filtered subset
  let active  = 0;

  // Build dots (one per card; rebuilt on filter change)
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

    // Update card offsets
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

    // Update dots
    dotsWrap.querySelectorAll(".bebidas__dot").forEach((d, idx) => {
      d.classList.toggle("is-active", idx === active);
      d.setAttribute("aria-selected", String(idx === active));
    });

    // Update info panel with a quick crossfade
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
    }, 180);
  }

  // — Navigation —
  prevBtn?.addEventListener("click", () => setActive(active - 1));
  nextBtn?.addEventListener("click", () => setActive(active + 1));

  // Keyboard support when section is focused
  document.addEventListener("keydown", (e) => {
    const inView = isSectionInView();
    if (!inView) return;
    if (e.key === "ArrowLeft")  { e.preventDefault(); setActive(active - 1); }
    if (e.key === "ArrowRight") { e.preventDefault(); setActive(active + 1); }
  });

  function isSectionInView() {
    const section = document.getElementById("bebidas");
    if (!section) return false;
    const r = section.getBoundingClientRect();
    return r.top < window.innerHeight * 0.6 && r.bottom > window.innerHeight * 0.4;
  }

  // — Touch swipe —
  let touchStartX = null;
  track.addEventListener("touchstart", (e) => {
    touchStartX = e.touches[0].clientX;
  }, { passive: true });
  track.addEventListener("touchend", (e) => {
    if (touchStartX == null) return;
    const dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) > 40) setActive(active + (dx < 0 ? 1 : -1));
    touchStartX = null;
  });

  // — Click on a non-focal card jumps to it —
  allCards.forEach((card) => {
    card.addEventListener("click", () => {
      const idx = visible.indexOf(card);
      if (idx >= 0 && idx !== active) setActive(idx);
    });
    card.style.pointerEvents = "auto";
    card.style.cursor = "pointer";
  });

  // — Filters —
  function applyFilters() {
    const ln = filterLine?.value || "";
    const tp = filterType?.value || "";
    visible = allCards.filter((c) => {
      const okLine = !ln || c.dataset.line === ln;
      const okType = !tp || c.dataset.type === tp;
      return okLine && okType;
    });
    if (!visible.length) visible = allCards.slice();
    buildDots();
    setActive(0);
  }
  filterLine?.addEventListener("change", applyFilters);
  filterType?.addEventListener("change", applyFilters);

  // Initial render
  buildDots();
  // Land on Rosé Goethe (the premiado) as default focal product — slot index 4
  const defaultIdx = 4;
  setActive(Math.min(defaultIdx, visible.length - 1));
}
