<?php
$cookieAssetUrl = static function (string $path): string {
	$base = defined('ASSET_BASE_URL') ? rtrim((string) ASSET_BASE_URL, '/') : '/fbo';
	$version = (string) filemtime(dirname(__DIR__) . '/' . $path);
	return htmlspecialchars($base . '/' . $path . '?v=' . rawurlencode($version), ENT_QUOTES, 'UTF-8');
};
?>
<link rel="stylesheet" href="<?= $cookieAssetUrl('assets/css/cookie-banner.css') ?>">
<aside class="cookie-banner" id="cookieBanner" aria-labelledby="cookieBannerTitle"<?= ($_COOKIE['fbo_cookie_notice'] ?? '') === '1' ? ' hidden' : '' ?>>
	<h2 id="cookieBannerTitle">Cookies on FBO</h2>
	<p>We use cookies to keep you logged in, remember your blog and save your Shuffleboard favorites. Your theme is saved in this browser too. No tracking or advertising cookies.</p>
	<div class="cookie-banner-actions">
		<button type="button" class="ui-btn ui-btn-strong" id="cookieBannerDismiss" hidden>Got it</button>
		<a class="ui-btn" href="/info#cookies">Cookie details</a>
	</div>
</aside>
<script src="<?= $cookieAssetUrl('assets/js/cookie-banner.js') ?>" defer></script>
