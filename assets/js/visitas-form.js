/**
 * Visitas e Eventos: abas verticais + envio dos pedidos.
 * As abas trocam o formulário visível (Visitas / Eventos) e deslizam o destaque.
 * O envio vai para api/agendamento.php, que confere vagas e grava no painel.
 * Em host sem PHP (prévia no GitHub Pages) o pedido segue pelo WhatsApp.
 */
(() => {
  const tabs = document.querySelectorAll(".visitas__tab");
  const tabBar = document.querySelector(".visitas__tabs");
  const highlight = document.querySelector(".visitas__tab-highlight");
  const forms = document.querySelectorAll(".visitas__form");
  const WHATSAPP = "5548999546255";

  if (!tabs.length || !forms.length) return;

  // Destaque posicionado exatamente atrás da aba ativa (vertical no desktop,
  // horizontal no celular), lendo a geometria real.
  function moveHighlight() {
    if (!highlight) return;
    const active = tabBar.querySelector(".visitas__tab.is-active");
    if (!active) return;
    highlight.style.top    = active.offsetTop + "px";
    highlight.style.left   = active.offsetLeft + "px";
    highlight.style.width  = active.offsetWidth + "px";
    highlight.style.height = active.offsetHeight + "px";
    highlight.style.right  = "auto";
    highlight.classList.add("is-ready");
  }

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
    requestAnimationFrame(moveHighlight);
  }

  tabs.forEach((t) => t.addEventListener("click", () => setActive(t.dataset.tab)));
  window.addEventListener("resize", moveHighlight);
  requestAnimationFrame(() => requestAnimationFrame(moveHighlight));

  /* ---------- Datas e vagas ---------- */

  const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
  const amanha = new Date();
  amanha.setDate(amanha.getDate() + 1);
  forms.forEach((f) => { if (f.data) f.data.min = iso(amanha); });

  const visitForm = document.querySelector('.visitas__form[data-form="visitas"]');
  let backendOff = false; // vira true se o host não tiver a API (prévia estática)

  async function checarDisponibilidade() {
    const form = visitForm;
    const hint = form.querySelector('[data-hint="data"]');
    const sel = form.horario;
    const reset = () => {
      [...sel.options].forEach((o) => {
        if (!o.value) return;
        o.disabled = false;
        o.textContent = o.dataset.label || o.textContent;
      });
    };
    [...sel.options].forEach((o) => { if (o.value && !o.dataset.label) o.dataset.label = o.textContent; });
    hint.textContent = "";
    hint.dataset.kind = "";
    reset();
    if (!form.data.value || backendOff) return;

    let info = null;
    try {
      const res = await fetch(`api/disponibilidade.php?data=${encodeURIComponent(form.data.value)}`, { headers: { Accept: "application/json" } });
      info = await res.json();
    } catch (_) {
      backendOff = true;
      return;
    }
    if (!info || !info.ok || !info.turnos) return;

    if (!info.aberto) {
      hint.textContent = info.motivo || "Data indisponível. Escolha outra.";
      hint.dataset.kind = "error";
    }
    [...sel.options].forEach((o) => {
      const t = info.turnos[o.value];
      if (!t) return;
      if (!t.aberto) {
        o.disabled = true;
        o.textContent = `${o.dataset.label} · indisponível`;
      } else if (t.vagas <= 10) {
        o.textContent = `${o.dataset.label} · ${t.vagas} ${t.vagas === 1 ? "vaga" : "vagas"}`;
      }
    });
    if (sel.value && sel.selectedOptions[0]?.disabled) sel.value = "";
    if (info.aberto) {
      hint.textContent = "Data disponível.";
      hint.dataset.kind = "ok";
    }
  }
  if (visitForm) visitForm.data.addEventListener("change", checarDisponibilidade);

  /* ---------- Envio ---------- */

  forms.forEach((form) => {
    const status = form.querySelector(".visitas__status");
    const button = form.querySelector('button[type="submit"]');
    const setStatus = (msg, kind) => {
      status.textContent = msg;
      status.dataset.kind = kind || "";
    };

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const invalid = [...form.querySelectorAll("[required]")].find((el) => !el.value.trim() || !el.checkValidity());
      form.querySelectorAll("[aria-invalid]").forEach((el) => el.removeAttribute("aria-invalid"));
      if (invalid) {
        invalid.setAttribute("aria-invalid", "true");
        invalid.focus();
        return setStatus(invalid.validity.rangeOverflow || invalid.validity.rangeUnderflow
          ? "Confira o número de pessoas e a data."
          : "Preencha os campos obrigatórios.", "error");
      }

      const data = Object.fromEntries(new FormData(form).entries());
      data.tipo_form = form.dataset.form;
      button.disabled = true;
      setStatus("Enviando o seu pedido...", "");

      let body = null;
      try {
        const res = await fetch(form.getAttribute("action"), {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify(data),
        });
        body = await res.json();
      } catch (_) {
        body = null;
      }
      button.disabled = false;

      if (body && body.ok) {
        form.reset();
        if (form === visitForm) checarDisponibilidade();
        setStatus(form.dataset.form === "visitas"
          ? "Pedido recebido! Você vai receber a confirmação por e-mail em até 24h."
          : "Pedido recebido! Retornamos com a proposta em até 48h.", "ok");
        return;
      }
      if (body && body.error) {
        if (form === visitForm && body.disponibilidade) checarDisponibilidade();
        return setStatus(body.error, "error");
      }
      whatsappFallback(form, data);
      setStatus("Abrimos o WhatsApp com o seu pedido preenchido. É só enviar.", "ok");
    });
  });

  function whatsappFallback(form, data) {
    let lines;
    if (form.dataset.form === "visitas") {
      lines = [
        "Olá! Quero agendar uma *visita* à Vinícola Quarezemin:",
        "",
        `*Nome:* ${data.nome || "-"}`,
        `*WhatsApp:* ${data.whatsapp || "-"}`,
        `*E-mail:* ${data.email || "-"}`,
        `*Experiência:* ${labelFor(form, "experiencia") || "-"}`,
        `*Pessoas:* ${data.pessoas || "-"}`,
        `*Data:* ${formatDate(data.data) || "-"}`,
        `*Horário:* ${labelFor(form, "horario") || "-"}`,
        data.observacoes ? `*Observações:* ${data.observacoes}` : null,
      ];
    } else {
      lines = [
        "Olá! Gostaria de uma *proposta para evento* na Vinícola Quarezemin:",
        "",
        `*Organizador:* ${data.nome || "-"}`,
        `*WhatsApp:* ${data.whatsapp || "-"}`,
        `*E-mail:* ${data.email || "-"}`,
        `*Tipo:* ${labelFor(form, "tipo") || "-"}`,
        `*Convidados:* ${data.convidados || "-"}`,
        `*Data desejada:* ${formatDate(data.data) || "-"}`,
        `*Descrição:* ${data.descricao || "-"}`,
      ];
    }
    const msg = lines.filter(Boolean).join("\n");
    window.open(`https://api.whatsapp.com/send?phone=${WHATSAPP}&text=${encodeURIComponent(msg)}`, "_blank", "noopener");
  }

  function labelFor(form, name) {
    const sel = form.querySelector(`[name="${name}"]`);
    if (!sel) return "";
    if (sel.tagName === "SELECT") {
      const opt = sel.options[sel.selectedIndex];
      return opt && opt.value ? (opt.dataset.label || opt.textContent).trim() : "";
    }
    return sel.value;
  }
  function formatDate(d) {
    if (!d) return "";
    const [y, m, day] = d.split("-");
    return `${day}/${m}/${y}`;
  }
})();
