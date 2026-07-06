/**
 * Luux theme — site interactions.
 */
(function () {
  const header = document.querySelector('[data-mobile-nav]');
  if (header) {
    const toggle = header.querySelector('[data-mobile-nav-toggle]');
    const panel = header.querySelector('[data-mobile-nav-panel]');
    if (toggle && panel) {
      toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
        panel.hidden = open;
      });
    }
  }

  /* Tab panels — hotel showcase */
  document.querySelectorAll('[data-tab-panel]').forEach((root) => {
    const triggers = root.querySelectorAll('[data-tab-trigger]');
    const panels = root.querySelectorAll('[data-tab-content]');
    if (!triggers.length || !panels.length) return;

    triggers.forEach((trigger) => {
      trigger.addEventListener('click', () => {
        const index = trigger.getAttribute('data-tab-trigger');
        triggers.forEach((btn) => {
          const active = btn.getAttribute('data-tab-trigger') === index;
          btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((panel) => {
          const active = panel.getAttribute('data-tab-content') === index;
          panel.classList.toggle('is-active', active);
          panel.hidden = !active;
        });
      });
    });
  });

  /* Suite grid filter */
  document.querySelectorAll('[data-suite-filter]').forEach((root) => {
    const triggers = root.querySelectorAll('[data-suite-filter-trigger]');
    const cards = root.querySelectorAll('[data-suite-category]');
    if (!triggers.length) return;

    function filter(slug) {
      triggers.forEach((btn) => {
        btn.setAttribute('aria-selected', btn.getAttribute('data-suite-filter-trigger') === slug ? 'true' : 'false');
      });
      cards.forEach((card) => {
        const match = !slug || card.getAttribute('data-suite-category') === slug;
        card.classList.toggle('is-hidden', !match);
      });
    }

    triggers.forEach((trigger) => {
      trigger.addEventListener('click', () => {
        filter(trigger.getAttribute('data-suite-filter-trigger'));
      });
    });

    const first = triggers[0];
    if (first) {
      filter(first.getAttribute('data-suite-filter-trigger'));
    }
  });

  /* FAQ accordion */
  document.querySelectorAll('[data-faq]').forEach((root) => {
    root.querySelectorAll('.faq__trigger').forEach((trigger) => {
      trigger.addEventListener('click', () => {
        const expanded = trigger.getAttribute('aria-expanded') === 'true';
        const panelId = trigger.getAttribute('aria-controls');
        const panel = panelId ? document.getElementById(panelId) : null;
        trigger.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        if (panel) {
          panel.hidden = expanded;
        }
      });
    });
  });

  /* Sticky in-page nav */
  const stickyNav = document.querySelector('[data-sticky-nav]');
  if (stickyNav) {
    document.body.classList.add('has-sticky-nav');
    const showAfter = 320;
    function updateStickyNav() {
      stickyNav.classList.toggle('is-visible', window.scrollY > showAfter);
    }
    window.addEventListener('scroll', updateStickyNav, { passive: true });
    updateStickyNav();
  }

  /* Travel style carousel */
  const carousels = document.querySelectorAll('[data-travel-carousel]');
  if (!carousels.length) return;

  const desktopQuery = window.matchMedia('(min-width: 1024px)');
  const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

  carousels.forEach((root) => {
    const track = root.querySelector('.travel-carousel__track');
    const slides = root.querySelectorAll('.travel-carousel__slide');
    const dots = root.querySelectorAll('[data-travel-carousel-dot]');

    if (!track || slides.length < 2) return;

    let index = 0;
    let timer = null;
    const intervalMs = 5000;

    function slideStep() {
      const slide = slides[0];
      if (!slide) return 0;
      return slide.offsetWidth + 16;
    }

    function setActiveDot(i) {
      dots.forEach((dot, dotIndex) => {
        const active = dotIndex === i;
        dot.classList.toggle('is-active', active);
        dot.setAttribute('aria-selected', active ? 'true' : 'false');
      });
    }

    function goTo(nextIndex) {
      index = (nextIndex + slides.length) % slides.length;
      track.style.transform = `translateX(-${index * slideStep()}px)`;
      setActiveDot(index);
    }

    function stop() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    }

    function start() {
      stop();
      if (!desktopQuery.matches || reducedMotionQuery.matches) return;
      timer = window.setInterval(() => goTo(index + 1), intervalMs);
    }

    function reset() {
      track.style.transform = '';
      index = 0;
      setActiveDot(0);
      stop();
      if (desktopQuery.matches) {
        goTo(0);
        start();
      }
    }

    dots.forEach((dot) => {
      dot.addEventListener('click', () => {
        goTo(Number(dot.getAttribute('data-travel-carousel-dot')));
        start();
      });
    });

    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    root.addEventListener('focusin', stop);
    root.addEventListener('focusout', start);

    desktopQuery.addEventListener('change', reset);
    window.addEventListener('resize', () => {
      if (desktopQuery.matches) goTo(index);
    });

    reset();
  });
})();
