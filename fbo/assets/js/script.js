// ── FBO typewriter animations ────────────────────────────────────────────────
(() => {
	const body = document.body;
	const frames = ['F', 'FB', 'FBO'];

	// Big full-screen overlay (page load + title click)
	function playBig(onDone) {
		const overlay = document.getElementById('introOverlay');
		const text = document.getElementById('introFboText');
		if (!overlay || !text) { if (onDone) onDone(); return; }
		body.classList.add('intro-loading');
		overlay.classList.remove('done');
		text.textContent = frames[0];
		let frame = 0;
		let cycles = 0;
		const timer = window.setInterval(() => {
			text.textContent = frames[frame];
			frame += 1;
			if (frame >= frames.length) {
				frame = 0;
				cycles += 1;
				if (cycles >= 2) {
					window.clearInterval(timer);
					overlay.classList.add('done');
					// wait for full opacity transition (220ms) then act
					window.setTimeout(() => {
						if (onDone) {
							// navigating away – skip DOM cleanup, just go
							onDone();
						} else {
							// page-load case: restore scroll + reset overlay
							body.classList.remove('intro-loading');
							overlay.classList.remove('done');
						}
					}, 220);
				}
			}
		}, 130);
	}

	// Play big intro on page load
	if (body.classList.contains('intro-loading')) {
		playBig();
	}

	// Title/logo click → big animation then navigate
	const logoLink = document.getElementById('siteTitleDisplay');
	if (logoLink) {
		logoLink.addEventListener('click', (e) => {
			if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.defaultPrevented) return;
			e.preventDefault();
			const href = logoLink.getAttribute('href') || '/';
			playBig(() => { window.location.href = href; });
		});
	}
})();

// ── FBO media buffering loader ────────────────────────────────────────────────
(() => {
	const frames = ['F', 'FB', 'FBO'];

	function attachLoader(mediaEl, wrap) {
		const loader = document.createElement('div');
		loader.className = 'media-fbo-indicator';
		const text = document.createElement('div');
		text.className = 'media-fbo-indicator-text';
		text.textContent = 'F';
		loader.appendChild(text);
		wrap.appendChild(loader);

		let timer = null;
		let frame = 0;

		function startTyping() {
			if (timer !== null) return;
			wrap.classList.add('media-loading');
			loader.classList.add('visible');
			frame = 0;
			text.textContent = frames[0];
			timer = window.setInterval(() => {
				text.textContent = frames[frame];
				frame = (frame + 1) % frames.length;
			}, 120);
		}

		function stopTyping() {
			if (timer !== null) { window.clearInterval(timer); timer = null; }
			loader.classList.remove('visible');
			wrap.classList.remove('media-loading');
		}

		const tag = mediaEl.tagName.toLowerCase();
		if (tag === 'img') {
			if (mediaEl.complete && mediaEl.naturalWidth > 0) {
				loader.remove();
				return;
			}
			startTyping();
			mediaEl.addEventListener('load', stopTyping, { once: true });
			mediaEl.addEventListener('error', stopTyping, { once: true });
			// fallback: dismiss after 6s in case load never fires (e.g. lazy off-screen)
			window.setTimeout(() => { if (timer !== null) stopTyping(); }, 6000);
		} else if (tag === 'video') {
			if (mediaEl.readyState >= 2) {
				// first decoded frame is available
				loader.remove();
				return;
			}
			startTyping();
			mediaEl.addEventListener('loadeddata', stopTyping, { once: true });
			mediaEl.addEventListener('canplay', stopTyping, { once: true });
			mediaEl.addEventListener('error', stopTyping, { once: true });
			// 4s hard timeout fallback
			window.setTimeout(() => { if (timer !== null) stopTyping(); }, 4000);
			mediaEl.addEventListener('waiting', startTyping);
			mediaEl.addEventListener('stalled', startTyping);
			mediaEl.addEventListener('seeking', startTyping);
			mediaEl.addEventListener('playing', () => {
				stopTyping();
			});
			mediaEl.addEventListener('pause', () => {
				stopTyping();
			});
		}
	}

	document.querySelectorAll('.media-wrap').forEach((wrap) => {
		const img = wrap.querySelector('img');
		const video = wrap.querySelector('video');
		if (video && wrap.closest('.archive.grid')) {
			return;
		}
		if (img) {
			attachLoader(img, wrap);
		} else if (video) {
			attachLoader(video, wrap);
		}
	});
})();

(() => {
	const label = document.getElementById('pageInfoLabel');
	const select = document.getElementById('pageJumpSelect');
	if (!label || !select) return;

	const close = () => {
		select.classList.remove('open');
		label.setAttribute('aria-expanded', 'false');
	};

	label.addEventListener('click', (event) => {
		event.stopPropagation();
		const nextState = !select.classList.contains('open');
		select.classList.toggle('open', nextState);
		label.setAttribute('aria-expanded', nextState ? 'true' : 'false');
		if (nextState) {
			select.focus();
		}
	});

	select.addEventListener('change', () => {
		const selectedPage = Number(select.value || '1');
		if (!Number.isFinite(selectedPage) || selectedPage < 1) return;

		const url = new URL(window.location.href);
		url.searchParams.set('page', String(selectedPage));
		window.location.href = url.toString();
	});

	document.addEventListener('click', (event) => {
		if (event.target === label || event.target === select) return;
		if (!select.classList.contains('open')) return;
		close();
	});

	select.addEventListener('blur', () => {
		window.setTimeout(() => {
			if (document.activeElement !== select) {
				close();
			}
		}, 80);
	});
})();

(() => {
	const formatLocalEu = (tsSec) => {
		const d = new Date(tsSec * 1000);
		if (Number.isNaN(d.getTime())) return '';
		const pad = (v) => String(v).padStart(2, '0');
		return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
	};

	document.querySelectorAll('.stamp[data-ts]').forEach((el) => {
		const ts = Number(el.getAttribute('data-ts'));
		if (!Number.isFinite(ts) || ts <= 0) return;
		const formatted = formatLocalEu(ts);
		if (formatted) {
			el.textContent = formatted;
		}
	});
})();

(() => {
	const copyText = async (value) => {
		if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
			await navigator.clipboard.writeText(value);
			return;
		}

		const temp = document.createElement('textarea');
		temp.value = value;
		temp.setAttribute('readonly', 'readonly');
		temp.style.position = 'fixed';
		temp.style.opacity = '0';
		document.body.appendChild(temp);
		temp.select();
		document.execCommand('copy');
		temp.remove();
	};

	document.querySelectorAll('.location-stamp[data-coords]').forEach((btn) => {
		if (btn.closest('.archive.grid')) {
			btn.removeAttribute('title');
			return;
		}

		const originalTitle = btn.getAttribute('title') || 'Click to copy coordinates';
		const fullTextEl = btn.querySelector('.location-stamp-full');
		const shortTextEl = btn.querySelector('.location-stamp-short');
		const originalFullText = fullTextEl ? fullTextEl.textContent : '';
		const originalShortText = shortTextEl ? shortTextEl.textContent : '';
		let resetTimer = null;

		btn.addEventListener('click', async () => {
			const coords = (btn.getAttribute('data-coords') || '').trim();
			if (!coords) return;

			const showMobileHint = window.matchMedia('(max-width: 700px)').matches;

			try {
				await copyText(coords);
				btn.setAttribute('title', 'Copied coordinates');
				if (showMobileHint) {
					if (fullTextEl) fullTextEl.textContent = 'copied to clipboard';
					if (shortTextEl) shortTextEl.textContent = 'copied';
				}
			} catch (error) {
				btn.setAttribute('title', 'Could not copy');
				if (showMobileHint) {
					if (fullTextEl) fullTextEl.textContent = 'copy failed';
					if (shortTextEl) shortTextEl.textContent = 'failed';
				}
			}

			if (resetTimer !== null) {
				window.clearTimeout(resetTimer);
			}
			resetTimer = window.setTimeout(() => {
				btn.setAttribute('title', originalTitle);
				if (fullTextEl) fullTextEl.textContent = originalFullText;
				if (shortTextEl) shortTextEl.textContent = originalShortText;
				resetTimer = null;
			}, 1500);
		});
	});
})();

(() => {
	const storageKey = 'template-theme';
	const stored = localStorage.getItem(storageKey);
	if (stored === 'dark') {
		document.body.classList.add('dark');
	}
	const btn = document.getElementById('themeToggle');
	const refreshToggleLabel = () => {
		if (!btn) return;
		btn.textContent = document.body.classList.contains('dark') ? 'light mode' : 'dark mode';
	};

	refreshToggleLabel();
	if (btn) {
		btn.addEventListener('click', () => {
			document.body.classList.toggle('dark');
			localStorage.setItem(storageKey, document.body.classList.contains('dark') ? 'dark' : 'light');
			refreshToggleLabel();
		});
	}
})();

(() => {
	const body = document.body;
	const heroHead = document.querySelector('.hero .hero-head');
	if (!body || !heroHead) return;

	let lastY = window.scrollY || 0;
	let ticking = false;

	const applyState = () => {
		const currentY = window.scrollY || 0;
		const atTop = currentY <= 16;

		if (atTop) {
			body.classList.remove('header-peek');
			lastY = currentY;
			ticking = false;
			return;
		}

		const delta = currentY - lastY;
		if (delta <= -8 && currentY > 88) {
			body.classList.add('header-peek');
		} else if (delta >= 8) {
			body.classList.remove('header-peek');
		}

		lastY = currentY;
		ticking = false;
	};

	window.addEventListener('scroll', () => {
		if (ticking) return;
		ticking = true;
		window.requestAnimationFrame(applyState);
	}, { passive: true });
})();

(() => {
	const grid = document.querySelector('.archive.grid');
	if (!grid) return;

	const composeMode = document.body?.dataset?.composeMode === '1';
	if (composeMode) return;

	const articleItems = Array.from(grid.querySelectorAll('.item[data-post-id]'));
	if (!articleItems.length) return;

	articleItems.forEach((item) => {
		item.addEventListener('click', (event) => {
			if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
				return;
			}

			const target = event.target;
			if (!(target instanceof Element)) return;
			if (target.closest('a, button, input, textarea, select, label, form')) {
				return;
			}

			event.preventDefault();
			const postId = item.getAttribute('data-post-id') || '';
			if (!postId) return;

			const url = new URL(window.location.href);
			const currentPage = Number(url.searchParams.get('page') || '1');
			url.searchParams.set('view', 'single');
			url.searchParams.set('post_id', postId);
			url.searchParams.set('from_page', String(Number.isFinite(currentPage) && currentPage > 0 ? currentPage : 1));
			url.searchParams.delete('compose');
			window.location.href = url.toString();
		});
	});
})();

