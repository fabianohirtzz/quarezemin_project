/* Nossas Bebidas · filtro da grade de rótulos.
 * Os dropdowns (.fluid-dd) são iniciados por bebidas-carousel.js, que
 * dispara o evento "bebidas:filter" a cada escolha. Aqui a grade escuta
 * esse evento e mostra só os cards de linha e tipo selecionados. */
(function () {
  const wrap  = document.getElementById("bebidas-grid-wrap");
  const count = document.getElementById("bebidas-grid-count");
  const empty = document.getElementById("bebidas-grid-empty");
  if (!wrap) return;
  const cards = Array.from(wrap.querySelectorAll(".bebida-card"));

  const current = (filter) =>
    document.querySelector(`.fluid-dd[data-filter="${filter}"] .fluid-dd__value`)?.dataset.value || "";

  function apply() {
    const ln = current("line");
    const tp = current("type");
    let shown = 0;
    cards.forEach((card) => {
      const match = (!ln || card.dataset.line === ln) && (!tp || card.dataset.type === tp);
      card.hidden = !match;
      if (match) {
        shown += 1;
        card.classList.remove("is-entering");
        void card.offsetWidth; // reinicia a animação de entrada
        card.classList.add("is-entering");
      }
    });
    if (count) count.textContent = shown === 1 ? "1 rótulo" : `${shown} rótulos`;
    if (empty) empty.hidden = shown !== 0;
  }

  document.addEventListener("bebidas:filter", apply);
})();
