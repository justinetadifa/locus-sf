(() => {
  const root = document.documentElement;
  const welcome = document.querySelector('[data-locus-welcome]');

  if (!welcome || !root.classList.contains('locus-welcome-active')) {
    welcome?.remove();
    return;
  }

  if (!("inert" in HTMLElement.prototype)) {
    root.classList.remove('locus-welcome-active');
    welcome.remove();
    return;
  }

  const continueControl = welcome.querySelector('[data-locus-welcome-continue]');
  const homepage = document.getElementById('main-content');
  const sessionKey = `locus-sf.welcome:${window.SFC_APP_CONFIG?.basePath || ''}`;
  const restricted = new Set();
  let observer;
  let leaving = false;

  const restrict = (element) => {
    if (!(element instanceof HTMLElement) || element === welcome || element.inert) return;
    element.inert = true;
    restricted.add(element);
  };

  Array.from(document.body.children).forEach(restrict);
  observer = new MutationObserver((records) => {
    records.forEach((record) => record.addedNodes.forEach(restrict));
  });
  observer.observe(document.body, { childList: true });

  const revealHomepage = () => {
    observer?.disconnect();
    restricted.forEach((element) => {
      element.inert = false;
    });
    restricted.clear();
    root.classList.remove('locus-welcome-active');
    welcome.remove();
    homepage?.focus({ preventScroll: true });
  };

  continueControl?.addEventListener('click', (event) => {
    if (leaving) return;
    leaving = true;
    event.preventDefault();

    try {
      window.sessionStorage.setItem(sessionKey, '1');
    } catch {
      // Continue for this visit even when session storage is unavailable.
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      revealHomepage();
      return;
    }

    const fallbackTimer = window.setTimeout(revealHomepage, 280);
    welcome.addEventListener('transitionend', (transitionEvent) => {
      if (transitionEvent.target !== welcome || transitionEvent.propertyName !== 'opacity') return;
      window.clearTimeout(fallbackTimer);
      revealHomepage();
    });
    welcome.classList.add('is-leaving');
  });

  continueControl?.focus({ preventScroll: true });
})();
