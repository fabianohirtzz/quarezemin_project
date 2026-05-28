/**
 * Hero scroll-driven video scrubbing.
 *
 * Pattern (borrowed from assamtea.vercel.app):
 *   - The hero is a sticky pin inside a tall track.
 *   - Page scroll progress through the track drives video.currentTime.
 *   - Text overlays cross-fade based on the playhead time:
 *       0s  → 7s   : LEFT  overlay
 *       7s  → end  : RIGHT overlay
 *
 * Uses Motion One's `scroll()` for the progress source.
 */
import { scroll } from "https://cdn.jsdelivr.net/npm/motion@11/+esm";

const SPLIT_SECONDS = 7;                 // boundary requested by the brief
const CROSSFADE_S   = 1.0;               // 1 second crossfade window
const FADE_IN_S     = 0.6;               // initial fade-in window
const FADE_OUT_TAIL = 0.8;               // tail fade-out near video end

const track       = document.querySelector(".hero__track");
const hero        = document.querySelector(".hero");
const video       = document.getElementById("hero-video");
const overlayLeft = document.getElementById("overlay-left");
const overlayRight= document.getElementById("overlay-right");
const statusFill  = document.getElementById("status-fill");
const statusCurr  = document.getElementById("status-current");
const heroHint    = document.getElementById("hero-hint");

if (!track || !video || !overlayLeft || !overlayRight) {
  console.warn("[hero-scroll] missing elements — aborting");
} else {
  init();
}

function init() {
  const reduceMotion = matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Smooth lerp loop — decouples scroll events from video.currentTime
  // writes so we don't thrash the decoder on rapid scrolls.
  let targetTime  = 0;
  let currentTime = 0;
  let ready       = false;
  let duration    = 15.37; // fallback; replaced when metadata loads

  video.addEventListener("loadedmetadata", () => {
    duration = video.duration;
    ready = true;
    // Make sure we render the first frame
    try { video.currentTime = 0.05; } catch (_) { /* noop */ }
  });

  // Some browsers won't surface "loadedmetadata" without an explicit load()
  video.load();

  function applyOverlays(timeSec) {
    // Returns a 0..1 opacity for each overlay given the current playhead.
    let leftOp = 0, rightOp = 0;

    // LEFT: fade in over FADE_IN_S, hold, then crossfade out at SPLIT_SECONDS
    if (timeSec < SPLIT_SECONDS + CROSSFADE_S / 2) {
      const fadeIn  = clamp(timeSec / FADE_IN_S, 0, 1);
      const fadeOut = 1 - clamp(
        (timeSec - (SPLIT_SECONDS - CROSSFADE_S / 2)) / CROSSFADE_S,
        0, 1
      );
      leftOp = Math.min(fadeIn, fadeOut);
    }

    // RIGHT: crossfades in around SPLIT_SECONDS, holds, fades out at end
    if (timeSec > SPLIT_SECONDS - CROSSFADE_S / 2) {
      const fadeIn  = clamp(
        (timeSec - (SPLIT_SECONDS - CROSSFADE_S / 2)) / CROSSFADE_S,
        0, 1
      );
      const fadeOut = 1 - clamp(
        (timeSec - (duration - FADE_OUT_TAIL)) / FADE_OUT_TAIL,
        0, 1
      );
      rightOp = Math.min(fadeIn, fadeOut);
    }

    overlayLeft.style.opacity  = leftOp.toFixed(3);
    overlayRight.style.opacity = rightOp.toFixed(3);
    overlayLeft.classList.toggle("is-active",  leftOp  > 0.05);
    overlayRight.classList.toggle("is-active", rightOp > 0.05);

    if (statusCurr) statusCurr.textContent = timeSec < SPLIT_SECONDS ? "01" : "02";
  }

  // Drive scroll → targetTime; the rAF loop below lerps + applies it.
  scroll(
    (info) => {
      // Motion v11 passes a progress object (`{ progress, ... }`) OR a number
      // depending on version — normalize to a 0..1 number.
      const p = typeof info === "number" ? info : (info && info.progress) || 0;
      const progress = clamp(p, 0, 1);

      targetTime = progress * (ready ? duration : 15.37);

      // Status bar reflects raw scroll progress (responsive feel)
      if (statusFill) statusFill.style.width = (progress * 100).toFixed(1) + "%";

      // Dismiss the scroll hint once the user starts moving
      if (progress > 0.02) hero.classList.add("is-scrolled");
      else hero.classList.remove("is-scrolled");

      if (reduceMotion) {
        // No video scrubbing for reduced-motion — still apply overlays
        applyOverlays(targetTime);
        if (ready) {
          try { video.currentTime = targetTime; } catch (_) {}
        }
      }
    },
    { target: track, offset: ["start start", "end end"] }
  );

  // rAF loop: smoothly interpolate currentTime toward targetTime
  function tick() {
    if (ready && !reduceMotion) {
      const delta = targetTime - currentTime;
      if (Math.abs(delta) > 0.005) {
        currentTime += delta * 0.22; // lerp factor — feel-tuned
        const t = clamp(currentTime, 0, duration - 0.01);
        try { video.currentTime = t; } catch (_) {}
        applyOverlays(t);
      } else {
        // settle on target
        if (Math.abs(targetTime - currentTime) > 0) {
          currentTime = targetTime;
          applyOverlays(currentTime);
        }
      }
    }
    requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);

  // Initial state: render first frame + show left overlay at 0
  applyOverlays(0);
}

function clamp(v, lo, hi) {
  return v < lo ? lo : v > hi ? hi : v;
}
