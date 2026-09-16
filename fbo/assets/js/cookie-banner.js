(() => {
	const banner = document.getElementById('cookieBanner');
	const dismiss = document.getElementById('cookieBannerDismiss');
	let dismissedHere = false;

	const syncNotice = () => {
		if (!banner) return;
		try {
			banner.hidden = dismissedHere || document.cookie.split(';').some((cookie) => cookie.trim() === 'fbo_cookie_notice=1');
		} catch {
			banner.hidden = dismissedHere;
		}
	};

	if (banner && dismiss) {
		dismiss.hidden = false;
		dismiss.addEventListener('click', () => {
			dismissedHere = true;
			try {
				const secure = window.location.protocol === 'https:' ? '; Secure' : '';
				document.cookie = `fbo_cookie_notice=1; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
			} catch {
				// The notice can still be dismissed when browser storage is blocked.
			}
			syncNotice();
		});
		syncNotice();
		window.addEventListener('pageshow', syncNotice);
		window.addEventListener('focus', syncNotice);
	}

	const openCookieDetails = () => {
		if (window.location.hash !== '#cookies') return;
		const details = document.getElementById('cookies');
		if (details instanceof HTMLDetailsElement) {
			details.open = true;
			details.scrollIntoView();
		}
	};
	openCookieDetails();
	window.addEventListener('hashchange', openCookieDetails);
})();
