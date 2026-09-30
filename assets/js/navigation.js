(() => {
  const header = document.querySelector('.site-header');
  const nav = header?.querySelector('.top-nav');
  const toggle = header?.querySelector('.nav-mobile-toggle');
  if (!header || !nav || !toggle) return;

  const mobile = window.matchMedia('(max-width: 900px)');
  const activeLink = () => nav.querySelector('[aria-current="page"]');
  const highlight = (link) => {
    if (!link || !link.getClientRects().length) {
      nav.classList.remove('has-nav-highlight');
      return;
    }
    nav.style.setProperty('--nav-x', `${link.offsetLeft}px`);
    nav.style.setProperty('--nav-y', `${link.offsetTop}px`);
    nav.style.setProperty('--nav-width', `${link.offsetWidth}px`);
    nav.style.setProperty('--nav-height', `${link.offsetHeight}px`);
    nav.classList.add('has-nav-highlight');
  };
  const resetHighlight = () => highlight(nav.contains(document.activeElement) ? document.activeElement.closest('.nav-link') : activeLink());
  const setOpen = (open) => {
    header.classList.toggle('is-mobile-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    if (!open) header.querySelectorAll('.portal-menu.is-open').forEach(menu => {
      menu.classList.remove('is-open');
      menu.querySelector('[data-sfc-menu-toggle]')?.setAttribute('aria-expanded', 'false');
    });
    requestAnimationFrame(resetHighlight);
  };
  toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
  header.addEventListener('keydown', (event) => {
    const openMenuTrigger = header.querySelector('.portal-menu.is-open [data-sfc-menu-toggle]');
    if (event.key === 'Escape' && openMenuTrigger) {
      openMenuTrigger.click();
      openMenuTrigger.focus();
      return;
    }
    if (event.key === 'Escape' && mobile.matches && header.classList.contains('is-mobile-open')) {
      setOpen(false);
      toggle.focus();
    }
  });
  document.addEventListener('click', (event) => {
    if (!header.contains(event.target)) setOpen(false);
  });
  nav.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('pointerenter', (event) => { if (event.pointerType !== 'touch') highlight(link); });
    link.addEventListener('focus', () => highlight(link));
  });
  nav.addEventListener('pointerleave', resetHighlight);
  nav.addEventListener('focusout', () => requestAnimationFrame(resetHighlight));
  mobile.addEventListener('change', () => {
    const focusInsideMenu = nav.contains(document.activeElement) || header.querySelector('.nav-actions').contains(document.activeElement);
    setOpen(false);
    if (mobile.matches && focusInsideMenu) toggle.focus();
  });
  if ('ResizeObserver' in window) new ResizeObserver(resetHighlight).observe(nav);
  document.fonts?.ready.then(resetHighlight);
  let scrollPending = false;
  const syncScroll = () => {header.classList.toggle('is-scrolled', window.scrollY > 24);scrollPending = false;};
  window.addEventListener('scroll', () => {
    if (!scrollPending) {scrollPending = true;requestAnimationFrame(syncScroll);}
  }, {passive:true});
  header.classList.add('has-mobile-menu');
  syncScroll();
  resetHighlight();
})();
