(() => {
  // Animate an HTML container: mobile WebKit inconsistently transforms SVG roots.
  const animateSymbol = (svg) => {
    let motion = svg.parentElement;
    if (!motion.classList.contains('visitor-motion')) {
      motion = document.createElement('span');
      motion.className = 'visitor-motion';
      svg.before(motion);
      motion.appendChild(svg);
    }
    const style = getComputedStyle(svg);
    motion.style.animation = `${style.getPropertyValue('--symbol-motion').trim() || 'visitor-header-turn'} ${style.getPropertyValue('--symbol-speed').trim() || '1.5s'} ease-in-out infinite`;
    motion.style.transformOrigin = svg.dataset.motion === 'burn' || svg.dataset.motion === 'grow' ? '50% 90%' : '50% 50%';
    return motion;
  };
  const selection = document.querySelector('.visitor-selection');
  if (selection) {
    const choices = Array.from(selection.querySelectorAll('[data-symbol-choice]'));
    const submit = selection.closest('form').querySelector('button[type="submit"]');
    const values = selection.closest('form').querySelector('[data-symbol-values]');
    const status = selection.closest('form').querySelector('.visitor-selection-status');
    let selected = [];
    const update = () => {
      values.replaceChildren();
      selected.forEach((value) => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'symbols[]'; input.value = value;
        values.appendChild(input);
      });
      choices.forEach((button) => {
        const count = selected.filter((value) => value === button.dataset.symbolChoice).length;
        button.disabled = selected.length === 3 && count === 0;
        button.classList.toggle('is-selected', count > 0);
        button.setAttribute('aria-pressed', String(count > 0));
        button.setAttribute('aria-label', `${button.dataset.symbolLabel}: ${count} selected`);
        button.querySelector('.visitor-choice-count').textContent = count > 1 ? String(count) : '';
        const motion = animateSymbol(button.querySelector('svg'));
        motion.style.animationPlayState = count ? 'running' : 'paused';
      });
      submit.disabled = selected.length !== 3;
      status.textContent = `${selected.length} / 3 · Tap again for repeats. At three, tap a selected symbol to clear it.`;
    };
    choices.forEach((button) => button.addEventListener('click', () => {
      const value = button.dataset.symbolChoice;
      if (selected.length < 3) selected.push(value);
      else selected = selected.filter((item) => item !== value);
      update();
    }));
    update();
    window.addEventListener('pageshow', update);
  }
  const track = document.querySelector('.shuffle-obolus-track');
  if (track) {
    const mobile = window.matchMedia('(max-width: 700px)');
    let previous = 0;
    let offset = 0;
    let frame = 0;
    const tick = (now) => {
      const width = track.firstElementChild.getBoundingClientRect().width;
      if (width > 0 && previous) offset = (offset + Math.min(now - previous, 64) * 0.028) % width;
      previous = now;
      track.style.transform = `translate3d(${-offset}px, 0, 0)`;
      frame = requestAnimationFrame(tick);
    };
    const start = () => {
      cancelAnimationFrame(frame); previous = 0;
      track.style.animation = 'none';
      track.style.transform = '';
      if (mobile.matches && !document.hidden) frame = requestAnimationFrame(tick);
    };
    [mobile].forEach((query) => {
      if (query.addEventListener) query.addEventListener('change', start);
      else query.addListener(start);
    });
    let resume;
    track.addEventListener('pointerdown', () => { clearTimeout(resume); cancelAnimationFrame(frame); });
    const resumeAfterTap = () => { clearTimeout(resume); resume = setTimeout(start, 250); };
    // The finger may leave the moving track before release.
    window.addEventListener('pointerup', resumeAfterTap);
    window.addEventListener('pointercancel', resumeAfterTap);
    window.addEventListener('touchend', resumeAfterTap, { passive: true });
    window.addEventListener('touchcancel', resumeAfterTap, { passive: true });
    document.addEventListener('visibilitychange', start);
    window.addEventListener('pageshow', start);
    start();
  }
  const heading = document.querySelector('.blog-heading');
  const mark = document.querySelector('.hero-right .fbo-title-mark-black');
  if (heading && mark) {
    const fit = () => {
      const size = mark.getBoundingClientRect().height;
      heading.style.setProperty('--blog-heading-size', `${size}px`);
      heading.querySelectorAll('.visitor-symbol-button').forEach((button) => {
        button.style.width = `${size + 4}px`;
        button.style.height = `${size + 4}px`;
        const svg = button.querySelector('svg');
        svg.style.width = `${size}px`;
        svg.style.height = `${size}px`;
      });
    };
    fit();
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(fit).observe(mark);
  }
  const buttons = document.querySelectorAll('[data-visitor-symbol]');
  if (!buttons.length) return;
  const overlay = document.createElement('div');
  overlay.className = 'visitor-symbol-overlay';
  overlay.hidden = true;
  overlay.setAttribute('aria-hidden', 'true');
  document.body.appendChild(overlay);
  let timeout;
  const close = () => {
    clearTimeout(timeout);
    overlay.hidden = true;
    overlay.replaceChildren();
  };
  buttons.forEach((button) => {
    button.addEventListener('mouseenter', () => animateSymbol(button.querySelector('svg')));
    button.addEventListener('mouseleave', () => {
      const motion = button.querySelector('.visitor-motion');
      if (motion) motion.style.animation = 'none';
    });
    button.addEventListener('click', () => {
      close();
      const stage = document.createElement('div');
      stage.className = 'visitor-symbol-stage';
      const sprite = document.createElement('div');
      sprite.className = 'visitor-symbol-large';
      const symbol = button.querySelector('svg').cloneNode(true);
      symbol.style.removeProperty('width');
      symbol.style.removeProperty('height');
      sprite.appendChild(symbol);
      stage.appendChild(sprite);
      overlay.appendChild(stage);
      if (button.hasAttribute('data-obolus')) {
        const caption = document.createElement('span');
        caption.className = 'visitor-obolus-caption';
        caption.textContent = 'FBO obolus';
        overlay.appendChild(caption);
      }
      overlay.hidden = false;
      animateSymbol(symbol);
      timeout = setTimeout(close, 3000);
    });
  });
  overlay.addEventListener('click', close);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') close();
  });
})();
