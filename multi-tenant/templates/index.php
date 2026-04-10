<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/tenant.php';
require_once dirname(__DIR__) . '/core/db.php';

$error = '';
$blogInput = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['create_blog'])) {
	$blogInput = (string) ($_POST['blog_word'] ?? '');
	$password = (string) ($_POST['admin_password'] ?? '');

	if ($blogInput === '' || $password === '') {
		$error = 'Blog name and password are required.';
	} elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $blogInput)) {
		$error = 'Use letters, numbers, - or _ only.';
	} elseif (mb_strlen($password) < 6) {
		$error = 'Password must be at least 6 characters.';
	} else {
		$result = mt_provision_blog($blogInput, $password);
		if (empty($result['ok'])) {
			$error = (string) ($result['message'] ?? 'Provisioning failed.');
		} else {
			$targetUrl = mt_blog_url((string) ($result['blog'] ?? $blogInput));
			header('Location: ' . $targetUrl);
			exit;
		}
	}
}

$blogs = mt_list_blogs();

function mt_asset_url(string $path): string
{
	$webRoot = dirname(dirname(__DIR__));
	$full = $webRoot . '/fbo/' . ltrim($path, '/');
	$v = is_file($full) ? (string) filemtime($full) : '1';
	return htmlspecialchars('/fbo/' . ltrim($path, '/') . '?v=' . rawurlencode($v), ENT_QUOTES, 'UTF-8');
}

function mt_format_date(string $date): string
{
	$dt = DateTime::createFromFormat('Y-m-d H:i:s', $date);
	return $dt ? $dt->format('d.m.Y H:i') : $date;
}

$scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? 'example.com');
$previewWord = $blogInput !== '' ? mt_normalize_blog_word($blogInput) : 'myblog';
$previewUrl = $scheme . '://' . $host . '/blog/' . rawurlencode($previewWord);
?>
<!--
 __  __       ____  
|  \/  |     / ___| 
| \  / |    | | __  
| |\/| |    | |(  | 
| |  | |  _ | |_) |  _ 
(_)  (_) (_) \____| (_)
-->
<!doctype html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>FBO Project - Onboarding</title>
	<meta name="description" content="A blogging tool for people tired of platforms. Open source. No ads. No app. No ai.">
	<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
	<link rel="icon" type="image/png" href="<?= mt_asset_url('assets/icon/icon.png') ?>">
	<link rel="apple-touch-icon" href="<?= mt_asset_url('assets/icon/icon.png') ?>">
	<link rel="stylesheet" href="<?= mt_asset_url('assets/css/styles.css') ?>">
	<link rel="stylesheet" href="<?= mt_asset_url('assets/css/admin.css') ?>">
	<link rel="stylesheet" href="<?= mt_asset_url('assets/css/onboarding.css') ?>">
</head>

<body class="onboarding-page">
	<main class="onboarding-wrap">
		<section class="onboarding-card">

			<div class="hero onboarding-preview">
				<div class="hero-head onboarding-preview-head">
					<div class="logo" id="onboardingTitlePreview">
						<?= htmlspecialchars(strtoupper($previewWord), ENT_QUOTES, 'UTF-8') ?>
					</div>
					<a href="/fbo/fbo" class="fbo fbo-link">FBO</a>
				</div>
			</div>


			<p class="subtitle-line onboarding-lead">Create your private blog on the <a href="/fbo/fbo/#server">FBO Project Server</a>. Pick a BLOGNAME. You don't need to register an email now, but for resetting your password, you could later.</p>
			<p class="subtitle-line">1–24 characters. Letters, numbers _ and - only.</p>
			<p class="subtitle-line">Your address: <span class="onboarding-url-preview"
					id="onboardingUrlPreview"><?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?></span></p>

			<?php if ($error !== ''): ?>
				<div class="subtitle-line onboarding-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
			<?php endif; ?>

			<form method="post" class="upload-panel onboarding-form" id="onboardingForm">
				<input id="onboardingBlogWord" class="upload-auth-input" type="text" name="blog_word" maxlength="24"
					pattern="[A-Za-z0-9_-]+" value="<?= htmlspecialchars($blogInput, ENT_QUOTES, 'UTF-8') ?>"
					placeholder="BLOGNAME" required autocomplete="off">
				<div class="upload-note">Choose carefully! The BLOGNAME cannot be changed later.</div>
				<input class="upload-auth-input" type="password" name="admin_password" minlength="6" maxlength="120"
					placeholder="Admin password (min 6 chars)" required>
				<div class="hero-actions">
					<button type="submit" name="create_blog" value="1" class="ui-btn">Create blog &rarr;</button>
				</div>
			</form>

			<?php if ($blogs !== []): ?>
				<div class="subtitle-line">
					<strong>Existing blogs</strong>
					<span class="ob-blog-count" id="obCount">(<?= count($blogs) ?>)</span>
				</div>

				<input type="search" class="ob-search" id="obSearch" placeholder="Search by name or URL…" autocomplete="off"
					spellcheck="false">

				<div class="upload-panel" id="obBlogList">
					<?php foreach ($blogs as $blog):
						$word = (string) ($blog['blog_word'] ?? '');
						$date = (string) ($blog['created_at'] ?? '');
						$url = '/blog/' . rawurlencode($word);
						$fullUrl = $scheme . '://' . $host . $url;
						$safeWord = htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
						$safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
						$safeFullUrl = htmlspecialchars($fullUrl, ENT_QUOTES, 'UTF-8');
						$safeDate = htmlspecialchars(mt_format_date($date), ENT_QUOTES, 'UTF-8');
						?>
						<div class="subtitle-line ob-blog-item" data-word="<?= $safeWord ?>" data-url="<?= $safeUrl ?>"
							data-fullurl="<?= $safeFullUrl ?>">
							<a href="<?= $safeUrl ?>" class="text-link">/blog/<?= $safeWord ?></a>
							<span class="upload-note">&mdash; <?= $safeDate ?></span>
						</div>
					<?php endforeach; ?>
					<div class="subtitle-line ob-blog-item" data-word="moritz" data-url="/blog/moritz"
						data-fullurl="https://blog.piquedeux.de">
						<a href="https://blog.piquedeux.de" target="_blank" rel="noopener noreferrer"
							class="text-link">/blog/moritz</a>
						<span class="upload-note">&mdash; 01.01.2026 00:00</span>
					</div>
				</div>

				<p class="ob-no-results" id="obNoResults">No blogs match your search.</p>

				<div id="obLoadMoreWrap" class="ob-load-more-wrap">
					<button class="ui-btn" id="obLoadMore">Load more</button>
				</div>
			<?php endif; ?>

		</section>
	</main>

	<script src="<?= mt_asset_url('assets/js/onboarding.js') ?>" defer></script>
</body>

</html>