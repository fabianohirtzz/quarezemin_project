/**
 * Visitas e Eventos: o formulário de agendamento abre em pop-up (<dialog>).
 *  · [data-agendar="visitas|eventos"] abre na aba certa.
 *  · [data-experiencia] já deixa a experiência escolhida no formulário.
 *  · Links com #agendar, #agendar-visita ou #evento-privado abrem direto.
 * O envio continua com o visitas-form.js.
 */
(() => {
  const modal = document.getElementById("agendar");
  if (!modal || typeof modal.showModal !== "function") return;

  const abrir = (aba, experiencia) => {
    const tab = modal.querySelector(`.visitas__tab[data-tab="${aba}"]`);
    if (tab) tab.click();
    if (experiencia) {
      const sel = modal.querySelector('.visitas__form[data-form="visitas"] [name="experiencia"]');
      if (sel) sel.value = experiencia;
    }
    if (!modal.open) modal.showModal();
    document.documentElement.classList.add("agendar-lock");
    // As abas medem o destaque pela geometria: recalcula com o pop-up visível
    requestAnimationFrame(() => window.dispatchEvent(new Event("resize")));
    const primeiro = modal.querySelector(`.visitas__form[data-form="${aba}"] input:not([type="hidden"]):not([tabindex="-1"])`);
    if (primeiro && window.matchMedia("(hover: hover)").matches) primeiro.focus({ preventScroll: true });
  };

  const fechar = () => modal.close();
  modal.addEventListener("close", () => document.documentElement.classList.remove("agendar-lock"));

  document.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-agendar]");
    if (btn) {
      e.preventDefault();
      abrir(btn.dataset.agendar || "visitas", btn.dataset.experiencia || "");
      return;
    }
    if (e.target.closest("[data-agendar-fechar]")) fechar();
  });

  // Clique fora do cartão (no fundo escurecido) fecha
  modal.addEventListener("click", (e) => {
    if (e.target === modal) fechar();
  });

  const porHash = () => {
    const h = location.hash;
    if (h === "#agendar" || h === "#agendar-visita") abrir("visitas");
    if (h === "#evento-privado") abrir("eventos");
  };
  porHash();
  window.addEventListener("hashchange", porHash);
})();
