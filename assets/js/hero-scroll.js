/**
 * Hero scroll-driven video scrubbing.
 *
 * Pattern (borrowed from assamtea.vercel.app):
 *   - Lenis takes over the wheel/touch and produces a smoothly
 *     interpolated `scrollY` (no more discrete jumps per wheel tick).
 *   - The hero is a sticky pin inside a tall track.
 *   - Page scroll progress through the track drives video.currentTime.
 *   - Text overlays cross-fade based on the playhead time:
 *       0s  → 7s   : LEFT  overlay
 *       7s  → end  : RIGHT overlay
 *
 * The video is encoded all-intra (every frame is a keyframe) so
 * video.currentTime seeks are O(1) and the playhead tracks the scroll
 * with no decode lag. Lenis + all-intra together give the experience
 * of continuous playback rather than frame-by-frame stepping.
 */
import { scroll } from "https://cdn.jsdelivr.net/npm/motion@11/+esm";
import Lenis      from "https://cdn.jsdelivr.net/npm/lenis@1.1.20/+esm";

const SPLIT_SECONDS = 7;
// On desktop the two overlays sit at opposite sides of the frame, so
// a soft crossfade reads well. On mobile they share the same bottom
// anchor; any crossfade visibly stacks the chapter 01 and 02 titles.
// Mobile therefore uses a SEQUENTIAL fade — left fully vanishes
// before right starts to appear (governed by GUARD_S) so the two
// captions are never on screen at the same time.
//
// `isMobileNow()` is intentionally re-evaluated inside applyOverlays
// every frame. iOS Safari sometimes evaluates a module-scope
// matchMedia before the viewport meta is applied, which produced
// `IS_MOBILE = false` on real iPhones and routed the page back into
// the desktop crossfade. Per-frame detection makes that impossible.
const isMobileNow = () => window.innerWidth <= 720;
const CROSSFADE_S = 1.0;
const FADE_IN_S_M = 0.4;
const FADE_IN_S_D = 0.6;
const FADE_OUT_M  = 0.5;
const FADE_OUT_D  = 0.8;
const GUARD_S     = 0.32;

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

  // Smooth-scroll engine: intercepts wheel/touch and interpolates
  // the page's scroll position with momentum + easing, so the video
  // scrub is driven by a continuous signal rather than discrete
  // wheel events. This is the same technique assamtea uses.
  if (!reduceMotion) {
    const lenis = new Lenis({
      duration: 1.15,
      easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
      smoothWheel: true,
      touchMultiplier: 1.1,
      wheelMultiplier: 1,
    });
    function rafLenis(time) {
      lenis.raf(time);
      requestAnimationFrame(rafLenis);
    }
    requestAnimationFrame(rafLenis);
  }

  let targetTime  = 0;
  let currentTime = 0;
  let ready       = false;
  let duration    = 15.37;

  // Mark video as ready once we have metadata + first frame decoded
  video.addEventListener("loadedmetadata", () => {
    duration = video.duration;
  });
  video.addEventListener("loadeddata", () => {
    ready = true;
    try { video.currentTime = 0.001; } catch (_) {}
    applyOverlays(0);
  });

  // Decode hint — force the browser to load and decode the first frame
  video.load();

  function applyOverlays(timeSec) {
    let leftOp = 0, rightOp = 0;
    const mobile = isMobileNow();

    if (mobile) {
      // Mobile: sequential fade. Only one caption is ever on
      // screen — the left fully vanishes by SPLIT_SECONDS, then
      // the right fades up from zero.
      if (timeSec < SPLIT_SECONDS) {
        const fadeIn  = clamp(timeSec / FADE_IN_S_M, 0, 1);
        const fadeOut = clamp((SPLIT_SECONDS - timeSec) / GUARD_S, 0, 1);
        leftOp = Math.min(fadeIn, fadeOut);
      } else {
        const fadeIn  = clamp((timeSec - SPLIT_SECONDS) / GUARD_S, 0, 1);
        const fadeOut = clamp((duration - timeSec) / FADE_OUT_M, 0, 1);
        rightOp = Math.min(fadeIn, fadeOut);
      }
    } else {
      // Desktop: original crossfade — the two overlays live on
      // opposite sides of the frame, so brief overlap reads as
      // intentional cinematic blend rather than a layout crash.
      if (timeSec < SPLIT_SECONDS + CROSSFADE_S / 2) {
        const fadeIn  = clamp(timeSec / FADE_IN_S_D, 0, 1);
        const fadeOut = 1 - clamp(
          (timeSec - (SPLIT_SECONDS - CROSSFADE_S / 2)) / CROSSFADE_S,
          0, 1
        );
        leftOp = Math.min(fadeIn, fadeOut);
      }

      if (timeSec > SPLIT_SECONDS - CROSSFADE_S / 2) {
        const fadeIn  = clamp(
          (timeSec - (SPLIT_SECONDS - CROSSFADE_S / 2)) / CROSSFADE_S,
          0, 1
        );
        const fadeOut = 1 - clamp(
          (timeSec - (duration - FADE_OUT_D)) / FADE_OUT_D,
          0, 1
        );
        rightOp = Math.min(fadeIn, fadeOut);
      }
    }

    // Defensive winner-take-all on mobile — even if some upstream
    // race condition makes both fades produce opacity > 0 at the
    // same moment, the lower-opacity overlay is forced off so the
    // titles can never stack.
    if (mobile && leftOp > 0 && rightOp > 0) {
      if (leftOp >= rightOp) rightOp = 0;
      else                   leftOp  = 0;
    }

    overlayLeft.style.opacity  = leftOp.toFixed(3);
    overlayRight.style.opacity = rightOp.toFixed(3);
    overlayLeft.classList.toggle("is-active",  leftOp  > 0.05);
    overlayRight.classList.toggle("is-active", rightOp > 0.05);

    if (statusCurr) statusCurr.textContent = timeSec < SPLIT_SECONDS ? "01" : "02";
  }

  // Scroll → targetTime
  scroll(
    (info) => {
      const p = typeof info === "number" ? info : (info && info.progress) || 0;
      const progress = clamp(p, 0, 1);

      targetTime = progress * duration;

      if (statusFill) statusFill.style.width = (progress * 100).toFixed(1) + "%";

      if (progress > 0.02) hero.classList.add("is-scrolled");
      else hero.classList.remove("is-scrolled");
    },
    { target: track, offset: ["start start", "end end"] }
  );

  // rAF loop: tight lerp toward target. All-intra means seeks are
  // cheap, so we can update on every frame at high lerp factor —
  // the playhead chases the scroll position smoothly.
  let lastApplied = -1;
  const seek = (t) => {
    // throttle to avoid setting the same time twice
    if (Math.abs(t - lastApplied) < 0.012) return;
    try { video.currentTime = t; } catch (_) {}
    lastApplied = t;
  };

  function tick() {
    if (ready && !reduceMotion) {
      // High lerp factor for responsive feel (0.35 ≈ catch-up in ~3 frames)
      const lerp = 0.35;
      const delta = targetTime - currentTime;
      if (Math.abs(delta) > 0.001) {
        currentTime += delta * lerp;
        const t = clamp(currentTime, 0, duration - 0.001);
        seek(t);
        applyOverlays(t);
      }
    } else if (reduceMotion && ready) {
      // Reduced-motion: jump directly to target, no lerp
      const t = clamp(targetTime, 0, duration - 0.001);
      seek(t);
      applyOverlays(t);
    }
    requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);

  applyOverlays(0);
}

function clamp(v, lo, hi) {
  return v < lo ? lo : v > hi ? hi : v;
}
