/**
 * Site header — mobile menu toggle.
 */
(function () {
  const header = document.querySelector('[data-mobile-nav]');
  if (!header) return;

  const toggle = header.querySelector('[data-mobile-nav-toggle]');
  const panel = header.querySelector('[data-mobile-nav-panel]');
  if (!toggle || !panel) return;

  toggle.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
    panel.hidden = open;
  });
})();
