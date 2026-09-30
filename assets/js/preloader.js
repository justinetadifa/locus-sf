/* Inlined before other assets; the page stays accessible if this never runs. */
(() => {
  const config = document.currentScript.dataset;
  const sessionKey = `locus-sf.preloader:${config.basePath || ""}`;

  try {
    if (sessionStorage.getItem(sessionKey)) return;
    // Consume the first visit even when it finishes too quickly to show anything.
    sessionStorage.setItem(sessionKey, "1");
  } catch {
    return;
  }

  // Older browsers keep their accessible page instead of an incomplete focus trap.
  if (!("inert" in HTMLElement.prototype) || document.readyState === "complete") return;

  let overlay;
  let observer;
  let showTimer;
  let failTimer;
  let removeTimer;
  let dismissed = false;
  const restricted = new Set();

  function remove() {
    clearTimeout(removeTimer);
    observer?.disconnect();
    restricted.forEach((element) => element.removeAttribute("inert"));
    restricted.clear();
    document.documentElement.classList.remove("locus-preloader-active");
    overlay?.remove();
    window.removeEventListener("pagehide", leavePage);
    window.removeEventListener("pageshow", restorePage);
  }

  function dismiss(immediate = false) {
    if (dismissed) {
      if (immediate) remove();
      return;
    }
    dismissed = true;
    clearTimeout(showTimer);
    clearTimeout(failTimer);
    if (!overlay || immediate || window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      remove();
      return;
    }
    overlay.classList.add("is-leaving");
    // Keep the covered content inert until the fade ends. The timer also works
    // when transitionend is unavailable or the tab is backgrounded.
    overlay.addEventListener("transitionend", remove, { once: true });
    removeTimer = setTimeout(remove, 180);
  }

  function leavePage() { dismiss(true); }
  function restorePage(event) { if (event.persisted) dismiss(true); }

  function restrict(element) {
    if (!(element instanceof HTMLElement) || element === overlay || element.inert) return;
    restricted.add(element);
    element.inert = true;
  }

  function show() {
    if (dismissed) return;
    if (!document.body) {
      showTimer = setTimeout(show, 16);
      return;
    }
    // Someone already using a server-rendered control should keep their focus.
    if (document.activeElement && ![document.body, document.documentElement].includes(document.activeElement)) {
      dismiss(true);
      return;
    }

    try {
      overlay = document.createElement("div");
      overlay.id = "locusPreloader";
      overlay.className = "locus-preloader";
      overlay.setAttribute("role", "status");
      overlay.setAttribute("aria-live", "polite");
      overlay.setAttribute("aria-atomic", "true");
      overlay.innerHTML = '<div class="locus-preloader__content">'
        + '<div class="locus-preloader__ring" aria-hidden="true"><img class="locus-preloader__logo" alt="" width="100" height="100"></div>'
        + '<span class="locus-preloader__name"></span>'
        + '<span class="locus-preloader__label">Loading\u2026</span></div>';
      overlay.querySelector("img").src = config.logo;
      overlay.querySelector(".locus-preloader__name").textContent = config.appName;
      document.body.append(overlay);
      Array.from(document.body.children).forEach(restrict);
      // Initialization can append drawers and notifications outside the layout.
      observer = new MutationObserver((records) => {
        records.forEach((record) => record.addedNodes.forEach(restrict));
      });
      observer.observe(document.body, { childList: true });
      document.documentElement.classList.add("locus-preloader-active");
    } catch {
      dismiss(true);
    }
  }

  // This only releases the presentation layer; it never resolves application
  // promises, marks operations successful, or clears their error messages.
  window.LOCUS_PRELOADER = { dismiss };
  window.addEventListener("pagehide", leavePage);
  window.addEventListener("pageshow", restorePage);
  showTimer = setTimeout(show, 150);
  failTimer = setTimeout(() => dismiss(), 4000);
})();
