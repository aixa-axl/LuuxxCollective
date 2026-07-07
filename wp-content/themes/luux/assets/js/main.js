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

  /* Hotel showcase — tabbed carousel (manual, no autoplay) */
  document.querySelectorAll('[data-hotel-showcase]').forEach((root) => {
    const track = root.querySelector('[data-hotel-track]');
    const slides = root.querySelectorAll('.hotel-showcase__slide');
    const tabs = root.querySelectorAll('[data-hotel-tab]');
    const dotsContainer = root.querySelector('[data-hotel-dots]');
    if (!track || slides.length < 2) return;

    let index = 0;

    function renderDots() {
      if (!dotsContainer) return;
      dotsContainer.innerHTML = '';
      slides.forEach((_, i) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.tabIndex = -1;
        dot.className = 'hotel-showcase__dot' + (i === index ? ' is-active' : '');
        dot.setAttribute('aria-label', `Go to hotel ${i + 1}`);
        dot.addEventListener('click', () => goTo(i));
        dotsContainer.appendChild(dot);
      });
    }

    function goTo(next) {
      index = (next + slides.length) % slides.length;
      track.style.transform = `translateX(-${index * 100}%)`;
      tabs.forEach((tab, i) => {
        tab.setAttribute('aria-selected', i === index ? 'true' : 'false');
      });
      slides.forEach((slide, i) => {
        if (i === index) {
          slide.removeAttribute('aria-hidden');
        } else {
          slide.setAttribute('aria-hidden', 'true');
        }
      });
      if (dotsContainer) {
        dotsContainer.querySelectorAll('.hotel-showcase__dot').forEach((dot, i) => {
          dot.classList.toggle('is-active', i === index);
        });
      }
    }

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        goTo(Number(tab.getAttribute('data-hotel-tab')));
      });
    });

    renderDots();
    goTo(0);
  });

  /* Suite grid — filter + carousel */
  document.querySelectorAll('[data-suite-grid]').forEach((root) => {
    const triggers = root.querySelectorAll('[data-suite-filter-trigger]');
    const allCards = root.querySelectorAll('.suite-grid__card');
    const track = root.querySelector('.suite-grid__track');
    const dotsContainer = root.querySelector('.suite-grid__dots');
    const carousel = root.querySelector('.suite-grid__carousel');
    if (!track || !allCards.length) return;

    const desktopQuery = window.matchMedia('(min-width: 1024px)');
    const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    let activeFilter = '';
    let pageIndex = 0;
    let timer = null;
    const intervalMs = 6000;

    function visibleCards() {
      return Array.from(allCards).filter((card) => !card.classList.contains('is-hidden'));
    }

    function slidesPerView() {
      return desktopQuery.matches ? 3 : 1;
    }

    function pageCount() {
      const count = visibleCards().length;
      const perView = slidesPerView();
      if (count <= perView) return 0;
      return count - perView + 1;
    }

    function slideStep() {
      const card = visibleCards()[0];
      if (!card) return 0;
      const gap = 32;
      return card.offsetWidth + gap;
    }

    function applyFilter(slug) {
      activeFilter = slug;
      allCards.forEach((card) => {
        const cat = card.getAttribute('data-suite-category');
        const match = !slug || cat === slug;
        card.classList.toggle('is-hidden', !match);
      });
      triggers.forEach((btn) => {
        const selected = Boolean(slug) && btn.getAttribute('data-suite-filter-trigger') === slug;
        btn.setAttribute('aria-selected', selected ? 'true' : 'false');
      });
      pageIndex = 0;
      updateCarousel();
    }

    triggers.forEach((trigger) => {
      trigger.addEventListener('click', () => {
        const slug = trigger.getAttribute('data-suite-filter-trigger');
        applyFilter(activeFilter === slug ? '' : slug);
      });
    });

    function renderDots() {
      if (!dotsContainer) return;
      const pages = pageCount();
      dotsContainer.innerHTML = '';
      if (pages < 2) {
        dotsContainer.hidden = true;
        return;
      }
      dotsContainer.hidden = false;
      for (let i = 0; i < pages; i++) {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'suite-grid__dot' + (i === pageIndex ? ' is-active' : '');
        dot.setAttribute('role', 'tab');
        dot.setAttribute('aria-label', `Go to page ${i + 1}`);
        dot.setAttribute('aria-selected', i === pageIndex ? 'true' : 'false');
        dot.dataset.suiteGridDot = String(i);
        dot.addEventListener('click', () => {
          pageIndex = i;
          goToPage();
          startAutoplay();
        });
        dotsContainer.appendChild(dot);
      }
    }

    function setActiveDots() {
      if (!dotsContainer) return;
      dotsContainer.querySelectorAll('.suite-grid__dot').forEach((dot, i) => {
        const active = i === pageIndex;
        dot.classList.toggle('is-active', active);
        dot.setAttribute('aria-selected', active ? 'true' : 'false');
      });
    }

    function goToPage() {
      const step = slideStep();
      track.style.transform = `translateX(-${pageIndex * step}px)`;
      setActiveDots();
    }

    function stopAutoplay() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    }

    function startAutoplay() {
      stopAutoplay();
      if (pageCount() < 2 || reducedMotionQuery.matches) return;
      timer = window.setInterval(() => {
        const pages = pageCount();
        if (pages < 2) return;
        pageIndex = (pageIndex + 1) % pages;
        goToPage();
      }, intervalMs);
    }

    function updateCarousel() {
      const pages = pageCount();
      if (pageIndex >= pages) {
        pageIndex = 0;
      }
      track.style.transform = pages ? '' : '';
      renderDots();
      goToPage();
      startAutoplay();
    }

    if (carousel) {
      carousel.addEventListener('mouseenter', stopAutoplay);
      carousel.addEventListener('mouseleave', startAutoplay);
      carousel.addEventListener('focusin', stopAutoplay);
      carousel.addEventListener('focusout', startAutoplay);
    }

    desktopQuery.addEventListener('change', updateCarousel);
    window.addEventListener('resize', () => {
      goToPage();
      renderDots();
    });

    updateCarousel();
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

  /* Reviews carousel — mobile only, auto-scroll */
  document.querySelectorAll('[data-reviews-carousel]').forEach((root) => {
    const track = root.querySelector('.reviews__track');
    const cards = root.querySelectorAll('.reviews__card');
    const dotsContainer = root.querySelector('[data-reviews-dots]');
    if (!track || cards.length < 2) return;

    const mobileQuery = window.matchMedia('(max-width: 1023px)');
    const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0;
    let timer = null;
    const intervalMs = 7000; // long enough to read a few lines

    function slideStep() {
      const card = cards[0];
      if (!card) return 0;
      const gap = 16;
      return card.offsetWidth + gap;
    }

    function setActiveDot() {
      if (!dotsContainer) return;
      dotsContainer.querySelectorAll('.reviews__dot').forEach((dot, i) => {
        const active = i === index;
        dot.classList.toggle('is-active', active);
        dot.setAttribute('aria-selected', active ? 'true' : 'false');
      });
    }

    function renderDots() {
      if (!dotsContainer) return;
      dotsContainer.innerHTML = '';
      cards.forEach((_, i) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'reviews__dot' + (i === index ? ' is-active' : '');
        dot.setAttribute('role', 'tab');
        dot.setAttribute('aria-label', `Go to review ${i + 1}`);
        dot.setAttribute('aria-selected', i === index ? 'true' : 'false');
        dot.addEventListener('click', () => {
          goTo(i);
          start();
        });
        dotsContainer.appendChild(dot);
      });
    }

    function goTo(next) {
      index = (next + cards.length) % cards.length;
      track.style.transform = `translateX(-${index * slideStep()}px)`;
      setActiveDot();
    }

    function stop() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    }

    function start() {
      stop();
      if (!mobileQuery.matches || reducedMotionQuery.matches) return;
      timer = window.setInterval(() => goTo(index + 1), intervalMs);
    }

    function reset() {
      stop();
      if (mobileQuery.matches) {
        goTo(index);
        start();
      } else {
        track.style.transform = '';
        index = 0;
        setActiveDot();
      }
    }

    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    root.addEventListener('focusin', stop);
    root.addEventListener('focusout', start);
    root.addEventListener('touchstart', stop, { passive: true });
    root.addEventListener('touchend', start, { passive: true });

    mobileQuery.addEventListener('change', reset);
    window.addEventListener('resize', () => {
      if (mobileQuery.matches) goTo(index);
    });

    renderDots();
    reset();
  });

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
