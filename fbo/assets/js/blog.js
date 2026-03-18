(() => {
	const textarea = document.getElementById('textPostContent');
	const count = document.getElementById('textPostCount');
	const epoch = document.getElementById('textPostClientEpoch');
	const form = document.getElementById('textPostForm');
	const toggleBtn = document.getElementById('textPostToggleBtn');
	if (!textarea || !count || !epoch || !form) return;

	const limit = Number(document.body?.dataset?.maxTextPostLength || '280');
	const refreshCount = () => {
		const len = textarea.value.length;
		count.textContent = `${len} / ${limit}`;
	};

	if (toggleBtn) {
		toggleBtn.addEventListener('click', () => {
			const open = form.hidden;
			form.hidden = !open;
			toggleBtn.classList.toggle('active', open);
			toggleBtn.textContent = open ? 'Cancel' : 'Post text';
			if (open) { textarea.focus(); }
		});
	}

	textarea.addEventListener('input', refreshCount);
	form.addEventListener('submit', () => {
		epoch.value = String(Date.now());
	});
	refreshCount();
})();

(() => {
	const input = document.getElementById('inlineUploadFiles');
	const epochInput = document.getElementById('uploadClientEpoch');
	const form = document.getElementById('inlineUploadForm');
	const preview = document.getElementById('inlineUploadPreview');
	const empty = document.getElementById('inlineUploadEmpty');
	const cancelBtn = document.getElementById('cancelInlineUpload');
	if (!input || !preview || !empty || !cancelBtn || !epochInput || !form) return;

	const imageExt = new Set(['jpg', 'jpeg', 'png', 'webp', 'gif']);
	const videoExt = new Set(['mp4', 'mov', 'webm', 'm4v']);
	const audioExt = new Set(['mp3', 'wav', 'flac', 'ogg', 'm4a', 'webm']);

	const clearPreview = () => {
		preview.innerHTML = '';
		empty.style.display = '';
	};

	const render = () => {
		epochInput.value = String(Date.now());
		preview.innerHTML = '';
		const files = Array.from(input.files || []);
		if (files.length === 0) {
			empty.style.display = '';
			return;
		}
		empty.style.display = 'none';

		for (const file of files) {
			const card = document.createElement('article');
			card.className = 'item';

			const mediaWrap = document.createElement('div');
			mediaWrap.className = 'media-wrap';
			const ext = (file.name.split('.').pop() || '').toLowerCase();
			const url = URL.createObjectURL(file);

			const mime = (file.type || '').toLowerCase();

			if (mime.startsWith('audio/') || audioExt.has(ext)) {
				const audio = document.createElement('audio');
				audio.src = url;
				audio.preload = 'metadata';
				audio.controls = true;
				mediaWrap.appendChild(audio);
			} else if (mime.startsWith('video/') || videoExt.has(ext)) {
				const video = document.createElement('video');
				video.src = url;
				video.preload = 'metadata';
				video.playsInline = true;
				mediaWrap.appendChild(video);
			} else if (mime.startsWith('image/') || imageExt.has(ext)) {
				const img = document.createElement('img');
				img.src = url;
				img.alt = file.name;
				mediaWrap.appendChild(img);
			} else {
				const fallback = document.createElement('div');
				fallback.className = 'stamp';
				fallback.textContent = file.name;
				mediaWrap.appendChild(fallback);
			}

			const stamp = document.createElement('div');
			stamp.className = 'stamp';
			stamp.textContent = file.name;

			card.appendChild(mediaWrap);
			card.appendChild(stamp);
			preview.appendChild(card);
		}
	};

	cancelBtn.addEventListener('click', () => {
		input.value = '';
		epochInput.value = '';
		clearPreview();
	});

	form.addEventListener('submit', () => {
		if (!epochInput.value) {
			epochInput.value = String(Date.now());
		}
	});

	input.addEventListener('change', render);
})();



(() => {
	const markButtons = Array.from(document.querySelectorAll('.mark-delete-btn[data-post-id]'));
	const form = document.getElementById('pendingDeleteForm');
	const inputs = document.getElementById('pendingDeleteInputs');
	const countEl = document.getElementById('pendingDeleteCount');
	const cancelBtn = document.getElementById('cancelDeleteBtn');
	const saveDeleteBtn = document.getElementById('saveDeleteBtn');
	const saveCloseBtn = document.getElementById('saveCloseUploadBtn');
	const closeAfterSaveInput = document.getElementById('closeAfterSaveInput');

	if (!markButtons.length || !form || !inputs || !countEl || !cancelBtn) {
		if (saveCloseBtn) {
			saveCloseBtn.addEventListener('click', () => {
				const closeUrl = saveCloseBtn.getAttribute('data-close-url');
				if (closeUrl) window.location.href = closeUrl;
			});
		}
		return;
	}

	const selected = new Set();

	const refreshState = () => {
		inputs.innerHTML = '';
		selected.forEach((postId) => {
			const input = document.createElement('input');
			input.type = 'hidden';
			input.name = 'page_post_ids[]';
			input.value = postId;
			inputs.appendChild(input);
		});

		const count = selected.size;
		countEl.textContent = `${count} selected for delete.`;
		form.classList.toggle('active', count > 0);
	};

	markButtons.forEach((button) => {
		button.addEventListener('click', () => {
			const postId = button.getAttribute('data-post-id') || '';
			if (!postId) return;

			const article = button.closest('.item');
			if (selected.has(postId)) {
				selected.delete(postId);
				button.textContent = 'Delete';
				if (article) article.classList.remove('marked-delete');
			} else {
				selected.add(postId);
				button.textContent = 'Undo';
				if (article) article.classList.add('marked-delete');
			}

			refreshState();
		});
	});

	cancelBtn.addEventListener('click', () => {
		selected.clear();
		markButtons.forEach((button) => {
			button.textContent = 'Delete';
			const article = button.closest('.item');
			if (article) article.classList.remove('marked-delete');
		});
		if (closeAfterSaveInput) closeAfterSaveInput.value = '0';
		refreshState();
	});

	if (saveDeleteBtn) {
		saveDeleteBtn.addEventListener('click', (event) => {
			if (selected.size <= 0) {
				event.preventDefault();
				return;
			}
		});
	}

	if (saveCloseBtn) {
		saveCloseBtn.addEventListener('click', () => {
			const closeUrl = saveCloseBtn.getAttribute('data-close-url');
			if (selected.size > 0) {
				if (closeAfterSaveInput) closeAfterSaveInput.value = '1';
				form.submit();
				return;
			}
			if (closeUrl) window.location.href = closeUrl;
		});
	}

	refreshState();
})();

(() => {
	const form = document.getElementById('deleteBlogForm');
	const composeConfirmInput = document.getElementById('deleteBlogConfirmCompose');
	const irreversibleConfirmInput = document.getElementById('deleteBlogConfirmIrreversible');
	if (!form || !composeConfirmInput || !irreversibleConfirmInput) return;

	form.addEventListener('submit', (event) => {
		composeConfirmInput.value = '0';
		irreversibleConfirmInput.value = '0';

		const stepOne = window.confirm('Switch to compose mode and delete media manually first. Continue anyway?');
		if (!stepOne) {
			const deniedConfirm = window.confirm('You declined the compose/media cleanup warning. Continue to final danger confirmation anyway?');
			if (!deniedConfirm) {
				event.preventDefault();
				return;
			}
		}

		const stepTwo = window.confirm('Really delete blog and all data? This cannot be undone nor restored.');
		if (!stepTwo) {
			event.preventDefault();
			return;
		}

		composeConfirmInput.value = '1';
		irreversibleConfirmInput.value = '1';
	});
})();
