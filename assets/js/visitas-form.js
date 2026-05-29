/**
 * Visitas e Eventos — vertical tab switcher + WhatsApp deeplink submit.
 * Clicking a tab swaps the visible form panel (Visitas ↔ Eventos) and
 * slides the highlight pill. Submitting any form opens WhatsApp with
 * a pre-filled message — the form data lands in Guilherme's chat
 * instead of going through a backend.
 */
(() => {
  const tabs = document.querySelectorAll(".visitas__tab");
  const tabBar = document.querySelector(".visitas__tabs");
  const forms = document.querySelectorAll(".visitas__form");
  const WHATSAPP = "5548999546255";

  if (!tabs.length || !forms.length) return;

  function setActive(name) {
    tabs.forEach((t) => {
      const active = t.dataset.tab === name;
      t.classList.toggle("is-active", active);
      t.setAttribute("aria-selected", String(active));
    });
    forms.forEach((f) => {
      const active = f.dataset.form === name;
      f.classList.toggle("is-active", active);
      f.hidden = !active;
    });
    if (tabBar) {
      tabBar.classList.toggle("is-eventos", name === "eventos");
      tabBar.classList.toggle("is-visitas", name === "visitas");
    }
  }

  tabs.forEach((t) => t.addEventListener("click", () => setActive(t.dataset.tab)));

  // Submit → WhatsApp deeplink with a structured message
  forms.forEach((form) => {
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(form).entries());
      const kind = form.dataset.form;

      let lines;
      if (kind === "visitas") {
        lines = [
          "Olá! Quero agendar uma *visita* à Vinícola Quarezemin:",
          "",
          `*Nome:* ${data.nome || "—"}`,
          `*WhatsApp:* ${data.whatsapp || "—"}`,
          `*E-mail:* ${data.email || "—"}`,
          `*Experiência:* ${labelFor(form, "experiencia") || "—"}`,
          `*Pessoas:* ${data.pessoas || "—"}`,
          `*Data:* ${formatDate(data.data) || "—"}`,
          `*Horário:* ${labelFor(form, "horario") || "—"}`,
          data.observacoes ? `*Observações:* ${data.observacoes}` : null,
        ];
      } else {
        lines = [
          "Olá! Gostaria de uma *proposta para evento* na Vinícola Quarezemin:",
          "",
          `*Organizador:* ${data.nome || "—"}`,
          `*WhatsApp:* ${data.whatsapp || "—"}`,
          `*E-mail:* ${data.email || "—"}`,
          `*Tipo:* ${labelFor(form, "tipo") || "—"}`,
          `*Convidados:* ${data.convidados || "—"}`,
          `*Data desejada:* ${formatDate(data.data) || "—"}`,
          `*Descrição:* ${data.descricao || "—"}`,
        ];
      }

      const msg = lines.filter(Boolean).join("\n");
      const url = `https://api.whatsapp.com/send?phone=${WHATSAPP}&text=${encodeURIComponent(msg)}`;
      window.open(url, "_blank", "noopener");
    });
  });

  function labelFor(form, name) {
    const sel = form.querySelector(`[name="${name}"]`);
    if (!sel) return "";
    if (sel.tagName === "SELECT") {
      const opt = sel.options[sel.selectedIndex];
      return opt && opt.value ? opt.textContent.trim() : "";
    }
    return sel.value;
  }
  function formatDate(d) {
    if (!d) return "";
    const [y, m, day] = d.split("-");
    return `${day}/${m}/${y}`;
  }
})();
