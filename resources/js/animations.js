/**
 * MyPage.ng Shared Motion & Micro-Interaction Engine
 * Designed for light-first editorial luxury. High performance, 60fps on mobile.
 * Respects prefers-reduced-motion and prefers-reduced-data.
 */

const prefersReduced = () =>
  window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
  window.matchMedia('(prefers-reduced-data: reduce)').matches ||
  navigator.connection?.saveData === true ||
  document.documentElement.dataset.reduceMotion === 'true' ||
  document.body?.dataset.reduceMotion === 'true';

const syncDataSaver = () => {
  document.documentElement.classList.toggle('save-data', navigator.connection?.saveData === true);
};

let scrollObserver = null;
let counterObserver = null;

export function initPlatformMotion() {
  syncDataSaver();
  navigator.connection?.addEventListener?.('change', syncDataSaver);

  document.documentElement.classList.add('js-motion');
  document.documentElement.classList.add('platform-motion-ready');

  // 1. Scroll-Linked Progress Bar
  const progressBar = document.querySelector('.platform-scroll-progress');
  if (progressBar) {
    const updateProgress = () => {
      const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
      const progress = scrollHeight > 0 ? Math.min(1, Math.max(0, window.scrollY / scrollHeight)) : 0;
      progressBar.style.transform = `scaleX(${progress})`;
    };
    window.addEventListener('scroll', updateProgress, { passive: true });
    updateProgress();
  }

  // 2. Declarative Reveal Elements
  const revealElements = document.querySelectorAll('[data-reveal]');
  if (prefersReduced() || !('IntersectionObserver' in window)) {
    revealElements.forEach((el) => el.classList.add('is-visible'));
  } else {
    scrollObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            entry.target.classList.add('is-in-view');
            if (entry.target.dataset.revealOnce !== 'false') {
              scrollObserver.unobserve(entry.target);
            }
          }
        });
      },
      { rootMargin: '0px 0px -8% 0px', threshold: 0.05 }
    );
    revealElements.forEach((el) => scrollObserver.observe(el));
  }

  // 3. Stagger indices assignment for child lists
  document.querySelectorAll('[data-stagger]').forEach((container) => {
    const children = container.querySelectorAll(':scope > *:not([data-reveal])');
    children.forEach((child, index) => {
      child.setAttribute('data-reveal', '');
      child.style.setProperty('--stagger-index', String(index % 8));
      if (scrollObserver && !prefersReduced()) {
        scrollObserver.observe(child);
      } else {
        child.classList.add('is-visible');
      }
    });
  });

  // Duplicate marquee content once so the -50% loop is seamless.
  document.querySelectorAll('.platform-marquee-inner').forEach((track) => {
    if (track.dataset.marqueeReady === 'true') {
      return;
    }

    const original = Array.from(track.children);

    original.forEach((node) => {
      const clone = node.cloneNode(true);
      clone.setAttribute('aria-hidden', 'true');
      track.appendChild(clone);
    });

    track.dataset.marqueeReady = 'true';
  });

  // 4. Animated Number Counters
  const counterElements = document.querySelectorAll('[data-counter]');
  if (counterElements.length > 0) {
    if (prefersReduced()) {
      counterElements.forEach((el) => {
        const val = Number(el.dataset.counter || 0);
        el.textContent = new Intl.NumberFormat('en-NG').format(val);
      });
    } else {
      counterObserver = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            const el = entry.target;
            const targetVal = Number(el.dataset.counter || 0);
            const prefix = el.dataset.counterPrefix || '';
            const suffix = el.dataset.counterSuffix || '';
            const duration = Number(el.dataset.counterDuration || 1400);
            const start = performance.now();
            const formatter = new Intl.NumberFormat('en-NG');

            const tick = (now) => {
              const elapsed = now - start;
              const progress = Math.min(1, elapsed / duration);
              // Refined cubic ease out
              const eased = 1 - Math.pow(1 - progress, 3);
              const current = Math.round(targetVal * eased);
              el.textContent = prefix + formatter.format(current) + suffix;
              if (progress < 1) {
                requestAnimationFrame(tick);
              }
            };
            requestAnimationFrame(tick);
            counterObserver.unobserve(el);
          });
        },
        { threshold: 0.3 }
      );
      counterElements.forEach((el) => counterObserver.observe(el));
    }
  }

  // 5. Parallax Scroll Effect
  if (!prefersReduced()) {
    const parallaxItems = document.querySelectorAll('[data-parallax]');
    if (parallaxItems.length > 0) {
      let ticking = false;
      window.addEventListener(
        'scroll',
        () => {
          if (!ticking) {
            window.requestAnimationFrame(() => {
              const scrollY = window.scrollY;
              parallaxItems.forEach((el) => {
                const speed = Number(el.dataset.parallax || 0.05);
                const rect = el.getBoundingClientRect();
                // only calculate if element is anywhere near viewport
                if (rect.top < window.innerHeight + 200 && rect.bottom > -200) {
                  const offset = (scrollY - (rect.top + scrollY - window.innerHeight / 2)) * speed;
                  el.style.transform = `translate3d(0, ${offset.toFixed(1)}px, 0)`;
                }
              });
              ticking = false;
            });
            ticking = true;
          }
        },
        { passive: true }
      );
    }
  }

  // 6. Magnetic Buttons (Desktop Only, subtle luxury feel)
  if (!prefersReduced() && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    document.querySelectorAll('.btn-magnetic').forEach((btn) => {
      btn.addEventListener('mousemove', (e) => {
        const rect = btn.getBoundingClientRect();
        const x = e.clientX - rect.left - rect.width / 2;
        const y = e.clientY - rect.top - rect.height / 2;
        btn.style.transform = `translate3d(${(x * 0.2).toFixed(1)}px, ${(y * 0.2).toFixed(1)}px, 0)`;
      });
      btn.addEventListener('mouseleave', () => {
        btn.style.transform = 'translate3d(0, 0, 0)';
      });
    });

    // Cursor Spotlight
    window.addEventListener(
      'pointermove',
      (e) => {
        document.documentElement.style.setProperty('--mouse-x', `${e.clientX}px`);
        document.documentElement.style.setProperty('--mouse-y', `${e.clientY}px`);
        document.documentElement.style.setProperty('--grid-shift-x', `${((e.clientX / window.innerWidth) - 0.5) * 10}px`);
        document.documentElement.style.setProperty('--grid-shift-y', `${((e.clientY / window.innerHeight) - 0.5) * 10}px`);
      },
      { passive: true }
    );
  }

  // 7. Dynamic Rotating Search Placeholders
  const rotatingSearch = document.querySelector('[data-rotating-search]');
  if (rotatingSearch) {
    const phrases = [
      '“birthday cake in Abuja”',
      '“bridal makeup in Lagos”',
      '“bespoke agbada in Port Harcourt”',
      '“handcrafted leather shoes in Enugu”',
      '“specialty coffee roaster in VI”',
      '“architectural ceramics in Maitama”',
    ];
    let phraseIdx = 0;
    let charIdx = 0;
    let isDeleting = false;
    let holdCounter = 0;

    const typeStep = () => {
      const current = phrases[phraseIdx];
      if (document.activeElement === rotatingSearch) {
        // Pause typing when user focuses input
        setTimeout(typeStep, 600);
        return;
      }

      if (!isDeleting) {
        rotatingSearch.setAttribute('placeholder', current.slice(0, charIdx + 1));
        charIdx++;
        if (charIdx === current.length) {
          isDeleting = true;
          holdCounter = 30; // pause at end
        }
      } else {
        if (holdCounter > 0) {
          holdCounter--;
        } else {
          rotatingSearch.setAttribute('placeholder', current.slice(0, charIdx - 1));
          charIdx--;
          if (charIdx <= 0) {
            isDeleting = false;
            phraseIdx = (phraseIdx + 1) % phrases.length;
          }
        }
      }
      const speed = isDeleting ? 30 : 60;
      setTimeout(typeStep, speed);
    };
    setTimeout(typeStep, 1000);
  }

  // 8. Sticky Storytelling Stepper Synchronization
  const storySteps = document.querySelectorAll('[data-story-step]');
  const storyVisuals = document.querySelectorAll('[data-story-visual]');
  if (storySteps.length > 0 && storyVisuals.length > 0 && !prefersReduced()) {
    const storyObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) {
            return;
          }

          const stepNum = entry.target.dataset.storyStep;

          storySteps.forEach((step) => {
            step.classList.toggle('is-active', step === entry.target);
          });

          storyVisuals.forEach((visual) => {
            const active = visual.dataset.storyVisual === stepNum;

            visual.style.opacity = active ? '1' : '0';
            visual.style.transform = active ? 'scale(1)' : 'scale(.95)';
            visual.classList.toggle('pointer-events-none', !active);
          });
        });
      },
      { threshold: 0.55, rootMargin: '-20% 0px -20% 0px' },
    );
    storySteps.forEach((s) => storyObserver.observe(s));
    storySteps[0]?.classList.add('is-active');
  }

  // 9. Navbar Tone Shift & Compact on Scroll
  const header = document.querySelector('.platform-shell-header');
  if (header) {
    const handleScrollNav = () => {
      header.classList.toggle('is-scrolled', window.scrollY > 30);
    };
    window.addEventListener('scroll', handleScrollNav, { passive: true });
    handleScrollNav();
  }

  // Pause decorative motion when the tab is hidden and restore it on return.
  document.addEventListener('visibilitychange', () => {
    document.documentElement.classList.toggle('motion-paused', document.hidden);
  });

  // Draw route paths when their section enters the viewport. SVG remains a
  // static fallback when JavaScript or motion is unavailable.
  const routePaths = document.querySelectorAll('.platform-route-path');
  if (routePaths.length && !prefersReduced() && 'IntersectionObserver' in window) {
    routePaths.forEach((path) => {
      const length = path.getTotalLength?.() || 600;
      path.style.strokeDasharray = `${length}`;
      path.style.strokeDashoffset = `${length}`;
      path.style.transition = 'stroke-dashoffset 1400ms var(--ease-cinematic)';
    });
    const routeObserver = new IntersectionObserver((entries) => entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const path = entry.target;
      path.style.strokeDashoffset = '0';
      routeObserver.unobserve(path);
    }), { threshold: 0.1 });
    routePaths.forEach((path) => routeObserver.observe(path));
  }
}

// Auto-run on document ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPlatformMotion, { once: true });
} else {
  initPlatformMotion();
}
