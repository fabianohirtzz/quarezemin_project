/* ============================================================
 *  Reels player
 *  · Cards in .reels__track open a lightbox with the self-hosted
 *    video (with sound), prev/next, Esc and arrow keys.
 *  · Desktop hover plays a muted preview inside the card.
 * ============================================================ */
(function () {
  "use strict";

  const cards = Array.from(document.querySelectorAll(".reels__card[data-reel-src]"));
  const modal = document.getElementById("reels-modal");
  if (!cards.length || !modal) return;

  const video = modal.querySelector(".reels-modal__video");
  const title = modal.querySelector(".reels-modal__title");
  let index = 0;
  let lastFocus = null;

  function load(i) {
    index = (i + cards.length) % cards.length;
    const card = cards[index];
    const poster = card.querySelector("img");
    video.poster = poster ? poster.currentSrc || poster.src : "";
    video.src = card.dataset.reelSrc;
    title.textContent = card.dataset.reelTitle || "";
    video.muted = false;
    const playing = video.play();
    if (playing && playing.catch) playing.catch(() => {});
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
    }, 350);
    if (lastFocus) lastFocus.focus({ preventScroll: true });
  }

  cards.forEach((card, i) => {
    card.addEventListener("click", () => open(i));
  });

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

  /* Muted hover preview, only on devices with a real pointer */
  const canHover = window.matchMedia("(hover: hover) and (pointer: fine)").matches;
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (!canHover || reduce) return;

  cards.forEach((card) => {
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
})();
