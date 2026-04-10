(() => {
	const titleInput = document.getElementById('onboardingBlogWord');
	const titlePreview = document.getElementById('onboardingTitlePreview');
	const urlPreview = document.getElementById('onboardingUrlPreview');

	if (!titleInput || !titlePreview) {
		return;
	}

	const updatePreview = () => {
		const raw = titleInput.value.trim();
		const safe = raw !== '' ? raw : 'yourname';
		titlePreview.textContent = safe.toUpperCase();
		if (urlPreview) {
			const protocol = window.location.protocol || 'https:';
			const host = window.location.host || 'example.com';
			urlPreview.textContent = `${protocol}//${host}/blog/${safe.toLowerCase()}`;
		}
	};

	['input', 'keyup', 'change', 'blur'].forEach((eventName) => {
		titleInput.addEventListener(eventName, updatePreview);
	});

	window.addEventListener('pageshow', updatePreview);
	updatePreview();
})();

(() => {
	document.addEventListener('submit', (event) => {
		const form = event.target;
		const btn = form.querySelector('[data-confirm]');
		if (!btn) return;
		const message = btn.getAttribute('data-confirm') || 'Are you sure?';
		if (!window.confirm(message)) {
			event.preventDefault();
		}
	});
})();

(() => {
	const svgNodes = Array.from(document.querySelectorAll('.icon-thumb svg'));
	if (!svgNodes.length) return;
	const previewSize = 120;

	const toPng = (svg) => {
		try {
			const serializer = new XMLSerializer();
			const svgMarkup = serializer.serializeToString(svg);
			const svgData = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svgMarkup)}`;
			const image = new Image();
			image.onload = () => {
				const canvas = document.createElement('canvas');
				canvas.width = previewSize;
				canvas.height = previewSize;
				const ctx = canvas.getContext('2d');
				if (!ctx) return;
				ctx.clearRect(0, 0, previewSize, previewSize);
				ctx.drawImage(image, 0, 0, previewSize, previewSize);
				const pngUrl = canvas.toDataURL('image/png');
				const img = document.createElement('img');
				img.src = pngUrl;
				img.alt = 'Icon preview';
				img.width = previewSize;
				img.height = previewSize;
				svg.replaceWith(img);
			};
			image.src = svgData;
		} catch {
		}
	};

	svgNodes.forEach(toPng);
})();

(() => {
	const pageSize = 20;
	const search = document.getElementById('obSearch');
	const list = document.getElementById('obBlogList');
	const noResults = document.getElementById('obNoResults');
	const count = document.getElementById('obCount');
	const loadMoreWrap = document.getElementById('obLoadMoreWrap');
	const loadMoreButton = document.getElementById('obLoadMore');

	if (!list) {
		return;
	}

	const items = Array.from(list.querySelectorAll('.ob-blog-item'));
	let visibleLimit = pageSize;

	const getMatches = (query) => items.filter((item) => {
		const word = (item.dataset.word || '').toLowerCase();
		const url = (item.dataset.url || '').toLowerCase();
		const fullUrl = (item.dataset.fullurl || '').toLowerCase();
		return query === '' || word.includes(query) || url.includes(query) || fullUrl.includes(query);
	});

	const applyState = () => {
		const query = search ? search.value.toLowerCase().trim() : '';
		const matches = getMatches(query);

		items.forEach((item) => {
			const matchIndex = matches.indexOf(item);
			const isVisible = matchIndex !== -1 && (query !== '' || matchIndex < visibleLimit);
			item.hidden = !isVisible;
		});

		if (noResults) {
			noResults.style.display = query !== '' && matches.length === 0 ? '' : 'none';
		}

		if (loadMoreWrap) {
			loadMoreWrap.style.display = query === '' && matches.length > visibleLimit ? '' : 'none';
		}

		if (count) {
			const visibleCount = query === '' ? Math.min(matches.length, visibleLimit) : matches.length;
			const isCappedAtTwenty = query === '' && visibleLimit === pageSize && matches.length > visibleLimit;
			count.textContent = `(${visibleCount}/${items.length})${isCappedAtTwenty ? ' search for more' : ''}`;
		}
	};

	if (search) {
		search.addEventListener('input', () => {
			visibleLimit = pageSize;
			applyState();
		});
	}

	if (loadMoreButton) {
		loadMoreButton.addEventListener('click', () => {
			visibleLimit += pageSize;
			applyState();
		});
	}

	applyState();
})();