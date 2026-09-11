(() => {
  'use strict';

  const header = document.querySelector('[data-site-header]');
  const menuButton = document.querySelector('.menu-toggle');
  const menu = document.querySelector('.primary-navigation');
  let menuReturnFocus = null;

  const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 24);
  updateHeader();
  window.addEventListener('scroll', updateHeader, { passive: true });

  const closeMenu = () => {
    if (!menu || !menuButton) return;
    menu.classList.remove('is-open');
    document.body.classList.remove('menu-open');
    menuButton.setAttribute('aria-expanded', 'false');
    menuButton.querySelector('i')?.classList.replace('bi-x-lg', 'bi-list');
    menuReturnFocus?.focus();
  };

  menuButton?.addEventListener('click', () => {
    const opening = menuButton.getAttribute('aria-expanded') !== 'true';
    if (!menu || !opening) {
      closeMenu();
      return;
    }
    menuReturnFocus = menuButton;
    menu.classList.add('is-open');
    document.body.classList.add('menu-open');
    menuButton.setAttribute('aria-expanded', 'true');
    menuButton.querySelector('i')?.classList.replace('bi-list', 'bi-x-lg');
    menu.querySelector('a')?.focus();
  });

  menu?.addEventListener('click', event => {
    if (event.target.closest('a')) closeMenu();
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && menu?.classList.contains('is-open')) closeMenu();
    if (event.key !== 'Tab' || !menu?.classList.contains('is-open')) return;
    const focusable = [menuButton, ...menu.querySelectorAll('a, button')];
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reducedMotion && 'IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(element => revealObserver.observe(element));
  } else {
    document.querySelectorAll('.reveal').forEach(element => element.classList.add('is-visible'));
  }

  document.querySelectorAll('.filter-button').forEach(button => {
    button.addEventListener('click', () => {
      document.querySelectorAll('.filter-button').forEach(item => {
        item.classList.remove('active');
        item.setAttribute('aria-pressed', 'false');
      });
      button.classList.add('active');
      button.setAttribute('aria-pressed', 'true');
      const filter = button.dataset.filter;
      document.querySelectorAll('.work-card').forEach(card => {
        card.classList.toggle('is-hidden', filter !== 'all' && card.dataset.category !== filter);
      });
    });
  });

  // Smooth Page Navigation Fallback for browsers without native View Transitions
  if (!('onpagereveal' in window) && !reducedMotion) {
    document.addEventListener('click', event => {
      const link = event.target.closest('a[href]');
      if (!link) return;
      const href = link.getAttribute('href');
      if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:') || link.target === '_blank') return;
      if (link.origin !== window.location.origin) return;

      event.preventDefault();
      document.body.classList.add('is-page-leaving');
      setTimeout(() => {
        window.location.href = href;
      }, 160);
    });
  }

})();
