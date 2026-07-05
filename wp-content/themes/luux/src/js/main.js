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
