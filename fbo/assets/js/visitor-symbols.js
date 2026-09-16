(() => {
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
    button.addEventListener('click', () => {
      close();
      const stage = document.createElement('div');
      stage.className = 'visitor-symbol-stage';
      const sprite = document.createElement('div');
      sprite.className = 'visitor-symbol-large';
      sprite.appendChild(button.querySelector('svg').cloneNode(true));
      stage.appendChild(sprite);
      overlay.appendChild(stage);
      overlay.hidden = false;
      timeout = setTimeout(close, 3000);
    });
  });
  overlay.addEventListener('click', close);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') close();
  });
})();
