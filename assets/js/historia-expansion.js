/**
 * Nossa História — scroll-driven media expansion.
 *
 * Adapted from 21st.dev/r/arunachalam0606/scroll-expansion-hero,
 * rewritten as vanilla JS over a sticky pin (instead of hijacking
 * the page's wheel events) so it composes with Lenis smooth scroll.
 *
 * As scroll progresses through the .historia__track:
 *   - the static background fades
 *   - the centered video card grows from 300x400 to viewport size
 *   - its border-radius softens to 0
 *   - the overlay text fades out
 *   - the lede + CTA fade in
 */
import { scroll } from "https://cdn.jsdelivr.net/npm/motion@11/+esm";

const section = document.getElementById("historia");
const track   = section?.querySelector(".historia__track");
const bg      = document.querySelector(".historia__bg");
const bgOverlay = document.querySelector(".historia__bg-overlay");
const media   = document.getElementById("historia-media");
const overlay = document.getElementById("historia-overlay");
const tint    = document.querySelector(".historia__media-tint");

if (section && track && media && overlay) initExpansion();

function initExpansion() {
  // Initial dims for the starting card (matches CSS)
  const START_W = 300;
  const START_H = 400;

  function frame(progress) {
    const vw = window.innerWidth;
    const vh = window.innerHeight;

    // Card grows from START_W × START_H to the full viewport (capped a bit
    // by CSS max-width/max-height so it never bleeds outside the section).
    const w = START_W + progress * (vw - START_W);
    const h = START_H + progress * (vh - START_H);
    media.style.width  = `${w}px`;
    media.style.height = `${h}px`;
    media.style.borderRadius = `${24 * (1 - progress)}px`;

    // Static background fades out as the video takes over
    if (bg) bg.style.opacity = String(1 - progress * 0.92);
    if (bgOverlay) bgOverlay.style.opacity = String(1 - progress * 0.6);

    // Video tint thins out as it expands
    if (tint) tint.style.opacity = String(0.45 - progress * 0.35);

    // Overlay text fades out as the video swallows the screen
    const overlayOp = progress < 0.65 ? 1 : Math.max(0, 1 - (progress - 0.65) / 0.2);
    overlay.style.opacity = overlayOp.toFixed(3);

    // Mark "expanded" once we're near the end to flip the content visibility
    if (progress >= 0.95) section.classList.add("is-expanded");
    else                  section.classList.remove("is-expanded");
  }

  // Lock initial state
  frame(0);

  scroll(
    (info) => {
      const p = typeof info === "number" ? info : (info && info.progress) || 0;
      frame(Math.max(0, Math.min(1, p)));
    },
    { target: track, offset: ["start start", "end end"] }
  );

  // Re-apply on resize so dimensions stay accurate
  window.addEventListener("resize", () => {
    const rect = track.getBoundingClientRect();
    const total = rect.height - window.innerHeight;
    const passed = -rect.top;
    const p = Math.max(0, Math.min(1, passed / total));
    frame(p);
  });
}
