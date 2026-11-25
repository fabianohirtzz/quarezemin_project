/* ============================================================
 *  Reels player
 *  · A lista vem do painel (api/reels.php). Em host sem PHP
 *    (prévia estática) ficam os cards fixos do HTML.
 *  · Cada card toca um vídeo hospedado no site (data-reel-src)
 *    ou abre o player oficial do Instagram (data-reel-embed).
 *  · Lightbox com anterior/próximo, Esc e setas do teclado.
 *  · No desktop, o hover toca uma prévia muda dos vídeos do site.
 * ============================================================ */
(function () {
  "use strict";

  const PLAY = '<svg viewBox="0 0 24 24"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg>';
  const LUPA = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="m21 3-7 7"/><path d="m3 21 7-7"/></svg>';

  // Cada galeria é uma <section data-reels="home|personalize"> com o próprio player dentro.
  document.querySelectorAll("[data-reels]").forEach(montarGaleria);

  function montarGaleria(section) {
  const track = section.querySelector(".reels__track");
  const modal = section.querySelector(".reels-modal");
  if (!track || !modal) return;
  const pagina = section.dataset.reels || "home";

  function cardItem(r) {
    const li = document.createElement("li");
    li.className = "reels__item";
    const b = document.createElement("button");
    b.type = "button";
    b.className = "reels__card" + (r.capa ? "" : " reels__card--sem-capa");
    if (r.video) b.dataset.reelSrc = r.video;
    if (r.foto) b.dataset.reelFoto = r.foto;
    if (r.embed) b.dataset.reelEmbed = r.embed;
    if (r.instagram) b.dataset.reelIg = r.instagram;
    b.dataset.reelTitle = r.titulo;
    b.setAttribute("aria-label", (r.foto ? "Ver foto: " : "Assistir: ") + r.titulo);
    if (r.foto) b.classList.add("reels__card--foto");
    if (r.capa) {
      const img = document.createElement("img");
      img.src = r.capa;
      img.alt = r.alt || r.titulo;
      img.loading = "lazy";
      b.append(img);
    } else {
      const mark = document.createElement("span");
      mark.className = "reels__mark";
      mark.setAttribute("aria-hidden", "true");
      const logo = document.createElement("img");
      logo.src = "imagens/logo-quarezemin.png";
      logo.alt = "";
      mark.append(logo);
      b.append(mark);
    }
    const play = document.createElement("span");
    play.className = "reels__play";
    play.setAttribute("aria-hidden", "true");
    play.innerHTML = r.foto ? LUPA : PLAY; // ícone fixo
    const cap = document.createElement("span");
    cap.className = "reels__caption";
    cap.textContent = r.titulo;
    b.append(play, cap);
    li.append(b);
    return li;
  }

  function carregarDoPainel() {
    return fetch("api/reels.php?pagina=" + encodeURIComponent(pagina), { headers: { Accept: "application/json" } })
      .then((res) => res.json())
      .then((data) => {
        if (!data || !data.ok || !Array.isArray(data.reels)) return;
        if (!data.reels.length) {
          section.hidden = true;
          return;
        }
        track.replaceChildren(...data.reels.map(cardItem));
      })
      .catch(() => { /* prévia estática: mantém os cards do HTML */ });
  }

  carregarDoPainel().then(iniciar);

  function iniciar() {
    const cards = Array.from(track.querySelectorAll(".reels__card"));
    if (!cards.length) return;

    /* A seção isola o empilhamento; o player sobe para o <body>
       para ficar acima do cabeçalho fixo. */
    document.body.appendChild(modal);

    const video = modal.querySelector(".reels-modal__video");
    const embed = modal.querySelector(".reels-modal__embed");
    let foto = modal.querySelector(".reels-modal__foto");
    if (!foto) {
      foto = document.createElement("img");
      foto.className = "reels-modal__foto";
      foto.alt = "";
      foto.hidden = true;
      video.after(foto);
    }
    const title = modal.querySelector(".reels-modal__title");
    const igLink = modal.querySelector(".reels-modal__ig");
    let index = 0;
    let lastFocus = null;

    function limparEmbed() {
      if (embed) {
        embed.replaceChildren();
        embed.hidden = true;
      }
    }

    function load(i) {
      index = (i + cards.length) % cards.length;
      const card = cards[index];
      title.textContent = card.dataset.reelTitle || "";
      if (igLink) {
        igLink.hidden = !card.dataset.reelIg;
        if (card.dataset.reelIg) igLink.href = card.dataset.reelIg;
      }

      foto.hidden = true;
      if (card.dataset.reelFoto) {
        limparEmbed();
        video.pause();
        video.removeAttribute("src");
        video.hidden = true;
        const img = card.querySelector("img");
        foto.src = card.dataset.reelFoto;
        foto.alt = img ? img.alt : "";
        foto.hidden = false;
      } else if (card.dataset.reelSrc) {
        limparEmbed();
        video.hidden = false;
        const poster = card.querySelector("img");
        video.poster = poster ? poster.currentSrc || poster.src : "";
        video.src = card.dataset.reelSrc;
        video.muted = false;
        const playing = video.play();
        if (playing && playing.catch) playing.catch(() => {});
      } else if (card.dataset.reelEmbed && embed) {
        video.pause();
        video.removeAttribute("src");
        video.load();
        video.hidden = true;
        const frame = document.createElement("iframe");
        frame.src = card.dataset.reelEmbed;
        frame.title = "Reel no Instagram: " + (card.dataset.reelTitle || "");
        frame.allow = "autoplay; encrypted-media; picture-in-picture; clipboard-write";
        frame.setAttribute("allowfullscreen", "");
        frame.loading = "eager";
        embed.replaceChildren(frame);
        embed.hidden = false;
      }
    }

    function open(i) {
      lastFocus = document.activeElement;
      modal.hidden = false;
      document.documentElement.classList.add("reels-lock");
      requestAnimationFrame(() => modal.classList.add("is-open"));
      load(i);
      modal.querySelector(".reels-modal__close").focus({ preventScroll: true });
    }

    function close() {
      modal.classList.remove("is-open");
      video.pause();
      document.documentElement.classList.remove("reels-lock");
      setTimeout(() => {
        modal.hidden = true;
        video.removeAttribute("src");
        video.load();
        limparEmbed();
      }, 350);
      if (lastFocus) lastFocus.focus({ preventScroll: true });
    }

    cards.forEach((card, i) => card.addEventListener("click", () => open(i)));

    modal.addEventListener("click", (e) => {
      if (e.target.closest("[data-reel-close]")) close();
      const step = e.target.closest("[data-reel-step]");
      if (step) load(index + Number(step.dataset.reelStep));
    });

    video.addEventListener("ended", () => load(index + 1));

    document.addEventListener("keydown", (e) => {
      if (modal.hidden) return;
      if (e.key === "Escape") close();
      if (e.key === "ArrowRight") load(index + 1);
      if (e.key === "ArrowLeft") load(index - 1);
    });

    /* Prévia muda no hover, só em dispositivos com mouse e só para vídeos do site */
    const canHover = window.matchMedia("(hover: hover) and (pointer: fine)").matches;
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (!canHover || reduce) return;

    cards.forEach((card) => {
      if (!card.dataset.reelSrc) return;
      let preview = null;
      card.addEventListener("mouseenter", () => {
        if (!preview) {
          preview = document.createElement("video");
          preview.className = "reels__preview";
          preview.src = card.dataset.reelSrc;
          preview.muted = true;
          preview.loop = true;
          preview.playsInline = true;
          preview.setAttribute("aria-hidden", "true");
          card.insertBefore(preview, card.querySelector(".reels__play"));
        }
        const p = preview.play();
        if (p && p.catch) p.catch(() => {});
        card.classList.add("is-previewing");
      });
      card.addEventListener("mouseleave", () => {
        if (preview) preview.pause();
        card.classList.remove("is-previewing");
      });
    });
  }
  }
})();
