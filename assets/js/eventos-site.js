/*
 * Visitas e Eventos: troca os cards fixos de "Vem aí" pelos eventos publicados no painel.
 * Sem eventos futuros, a seção some. Em host sem PHP (prévia estática) os cards fixos ficam.
 */
(() => {
  const section = document.querySelector(".eventos-feature");
  const wrap = section && section.querySelector(".eventos-feature__wrap");
  if (!wrap) return;

  const ICON = {
    hora: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
    local: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  };

  const el = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  };

  function card(ev) {
    const art = el("article", "evento-card");
    const imgBox = el("div", "evento-card__image");
    imgBox.append(el("span", "evento-card__date", ev.data_card));
    const img = el("img");
    img.src = ev.foto;
    img.alt = ev.titulo;
    img.loading = "lazy";
    imgBox.append(img);

    const content = el("div", "evento-card__content");
    if (ev.linha) content.append(el("p", "evento-card__line", ev.linha));
    const h3 = el("h3", "evento-card__title");
    const i = ev.titulo.indexOf(" ");
    h3.append(el("em", null, i > 0 ? ev.titulo.slice(0, i) : ev.titulo), i > 0 ? ev.titulo.slice(i) : "");
    content.append(h3, el("p", "evento-card__desc", ev.descricao));

    const meta = el("div", "evento-card__meta");
    [["hora", ev.horario], ["local", ev.local], ["info", ev.info]].forEach(([icon, txt]) => {
      if (!txt) return;
      const s = el("span");
      s.innerHTML = ICON[icon]; // ícone fixo, sem dado do usuário
      s.append(" " + txt);
      meta.append(s);
    });
    if (meta.childElementCount) content.append(meta);
    art.append(imgBox, content);
    return art;
  }

  fetch("api/eventos.php", { headers: { Accept: "application/json" } })
    .then((r) => r.json())
    .then((data) => {
      if (!data || !data.ok || !Array.isArray(data.eventos)) return;
      if (!data.eventos.length) {
        section.hidden = true;
        return;
      }
      wrap.replaceChildren(...data.eventos.map(card));
      wrap.classList.toggle("eventos-feature__wrap--solo", data.eventos.length === 1);
    })
    .catch(() => { /* prévia estática: mantém os cards fixos */ });
})();
