<?php
declare(strict_types=1);

function local_asset_url(string $relativePath): string
{
	$cleanPath = ltrim($relativePath, '/');
	$fullPath = dirname(__DIR__) . '/' . $cleanPath;
	$version = is_file($fullPath) ? (string) filemtime($fullPath) : '1';
	return htmlspecialchars('../' . $cleanPath . '?v=' . rawurlencode($version), ENT_QUOTES, 'UTF-8');
}

$blogs = [];
$dbFile = dirname(dirname(__DIR__)) . '/multi-tenant/core/db.php';
if (is_file($dbFile)) {
	try {
		require_once $dbFile;
		$blogs = mt_list_blogs();
	} catch (Throwable $e) {
	}
}

$blogs[] = ['blog_word' => 'moritzgauss', 'created_at' => '2026-01-01 00:00:00', 'custom_url' => 'https://blog.piquedeux.de'];

function fbo_format_date(string $date): string
{
	if ($date === '') {
		return '';
	}
	$dt = DateTime::createFromFormat('Y-m-d H:i:s', $date);
	return $dt ? $dt->format('d.m.Y H:i') : $date;
}

$scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? 'example.com');
?>
<!doctype html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="A blogging tool for people tired of platforms. Open source. No ads. No app. No ai.">
	<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
	<link rel="icon" type="image/png" href="<?= local_asset_url('assets/icon/icon.png') ?>">
	<link rel="apple-touch-icon" href="<?= local_asset_url('assets/icon/icon.png') ?>">
	<title>FBO Project — Fuck Being Online</title>
	<link rel="stylesheet" href="<?= local_asset_url('assets/css/styles.css') ?>">
	<style>
		:root {
			--fbo-header-h: 80px;
		}

		.fbo-page-wrap {
			display: grid;
			grid-template-columns: 280px 1fr;
			height: calc(100vh - var(--fbo-header-h));
		}


		.fbo-index-col {
			border-right: 2px solid var(--line);
			padding: 14px 12px;
			display: flex;
			flex-direction: column;
			gap: 10px;
			overflow-y: auto;
		}


		.fbo-info-col {
			padding: 14px;
			overflow-y: auto;
		}

		.fbo-info-col .archive.single {
			max-width: 100%;
			margin: 0;
			padding: 0;
			border: none;
		}


		@media (max-width: 700px) {
			.fbo-page-wrap {
				grid-template-columns: 1fr;
				height: auto;
			}

			.fbo-index-col {
				border-right: none;
				border-bottom: 2px solid var(--line);
				overflow-y: visible;
			}

			.fbo-info-col {
				overflow-y: visible;
			}
		}


		.fbo-index-heading {
			font-size: 11px;
			font-weight: 700;
			letter-spacing: 0.07em;
			text-transform: uppercase;
			color: var(--muted);
			margin: 0;
			flex-shrink: 0;
		}

		.fbo-search {
			flex-shrink: 0;
			width: 100%;
			border: 2px solid var(--line);
			background: var(--bg);
			color: var(--fg);
			padding: 6px 10px;
			font-size: 13px;
			font-family: inherit;
			outline: none;
			border-radius: 0;
			-webkit-appearance: none;
		}

		.fbo-search::placeholder {
			color: var(--muted);
		}

		.fbo-search:focus {
			outline: 2px solid var(--fg);
			outline-offset: -2px;
		}

		.fbo-blog-list {
			display: flex;
			flex-direction: column;
			gap: 0;
		}

		.fbo-blog-item {
			display: flex;
			flex-direction: column;
			gap: 2px;
			padding: 7px 0;
			border-bottom: 1px solid var(--line);
		}

		.fbo-blog-item:last-child {
			border-bottom: none;
		}

		.fbo-blog-item[hidden] {
			display: none;
		}

		.fbo-blog-item a {
			color: var(--fg);
			text-decoration: none;
			font-size: 13px;
			font-weight: 600;
		}

		.fbo-blog-item a:hover {
			text-decoration: underline;
		}

		.fbo-blog-date {
			font-size: 11px;
			color: var(--muted);
		}

		.fbo-no-results {
			font-size: 13px;
			color: var(--muted);
			display: none;
		}

		.fbo-load-more-wrap {
			display: none;
			padding-top: 4px;
			flex-shrink: 0;
		}

		.fbo-count {
			font-size: 11px;
			color: var(--muted);
		}

		.fbo-legal {
			display: grid;
			gap: 10px;
			margin-top: 10px;
		}

		details.fbo-legal-item {
			border: 2px solid var(--line);
			padding: 8px 10px;
		}

		details.fbo-legal-item summary {
			cursor: pointer;
			font-weight: 700;
			text-decoration: underline;
			list-style: none;
		}

		details.fbo-legal-item summary::-webkit-details-marker {
			display: none;
		}

		details.fbo-legal-item summary::marker {
			content: '';
		}

		details.fbo-legal-item[open] summary {
			margin-bottom: 8px;
		}

		.fbo-legal-copy {
			font-size: 13px;
			line-height: 1.45;
			color: var(--fg);
		}
	</style>
</head>

<body class="intro-loading">
	<div class="intro-overlay" id="introOverlay" aria-hidden="true">
		<div class="intro-fbo" id="introFboText">F</div>
	</div>

	<header class="hero" id="fboHeader">
		<div class="hero-head">
			<a href="#" onclick="history.back(); return false;" class="logo logo-link">FBO Project</a>
			<div class="hero-right">
				<div class="hero-actions">
					<a href="/" class="ui-btn">Create blog</a>
					<a href="https://www.instagram.com/fbeing.online" target="_blank" rel="noopener noreferrer"
						class="text-link">IG</a>
					<a href="mailto:fboproject@proton.me" class="text-link">M</a>
				</div>
			</div>
		</div>
		<div class="subtitle-line">FBO Project stands for Fuck Being Online.</div>
	</header>

	<div class="fbo-page-wrap" id="fboPageWrap">

		<aside class="fbo-index-col">
			<p class="fbo-index-heading">Blogs <?php if ($blogs !== []): ?><span class="fbo-count" id="fboCount">(<?= count($blogs) ?>)</span><?php endif; ?></p>

			<input type="search" class="fbo-search" id="fboSearch" placeholder="Search by name or URL…" autocomplete="off" spellcheck="false">

			<?php if ($blogs === []): ?>
				<p class="fbo-count">No blogs yet.</p>
			<?php else: ?>
				<div class="fbo-blog-list" id="fboBlogList">
					<?php foreach ($blogs as $blog):
						$word = (string) ($blog['blog_word'] ?? '');
						$date = (string) ($blog['created_at'] ?? '');
						$customUrl = (string) ($blog['custom_url'] ?? '');
						$url = $customUrl !== '' ? $customUrl : '/blog/' . rawurlencode($word);
						$fullUrl = preg_match('/^https?:\/\//i', $url) ? $url : ($scheme . '://' . $host . $url);
						$safeWord = htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
						$safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
						$safeFullUrl = htmlspecialchars($fullUrl, ENT_QUOTES, 'UTF-8');
						$formattedDate = fbo_format_date($date);
						$safeDate = htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8');
						?>
						<div class="fbo-blog-item" data-word="<?= $safeWord ?>" data-url="<?= $safeUrl ?>" data-fullurl="<?= $safeFullUrl ?>">
							<a href="<?= $safeUrl ?>">/blog/<?= $safeWord ?></a>
							<?php if ($formattedDate !== ''): ?>
								<span class="fbo-blog-date"><?= $safeDate ?></span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<p class="fbo-no-results" id="fboNoResults">No blogs match your search.</p>

				<div class="fbo-load-more-wrap" id="fboLoadMoreWrap">
					<button class="ui-btn" id="fboLoadMore">Load more</button>
				</div>
			<?php endif; ?>
		</aside>

		<div class="fbo-info-col">
			<main class="archive single" id="idea">
				<article class="item">
					<div class="text-post-body">FBO is a blogging tool built around one idea: your content belongs to you. You get your own URL, you set a name, you write and publish. No feed to optimize for, no follower count to grow, no platform making decisions about who sees your media. What you post stays yours. The web as a place to put things, not to perform.</div>
					<div class="stamp">Core ideas</div>
				</article>
				<article class="item" id="architecture">
					<div class="text-post-body">FBO keeps things separated and readable. Reusable snippets keep templates consistent across blogs. The multi-tenant core handles provisioning and routing. Fast, readable, and easy to extend, whether it's running one blog or a hundred.</div>
					<div class="stamp">Template architecture</div>
				</article>

				<article class="item" id="server">
					<div class="text-post-body">FBO is running on the project server in Germany, hosted by <a
							href="https://www.ionos-group.com/de/investor-relations/newsroom/neues-cloud-rechenzentrum-in-frankfurt-ionos-setzt-signal-fuer-digitale-souveraenitaet-in-europa.html" target="_blank"
							rel="noopener noreferrer">IONOS</a>. You can start posting immediately, without technical knowledge or backend/cms handling. The setup is simple while staying lightweight. Made with PHP and a little of JavaScript.</div>
					<div class="stamp">Server setup</div>
				</article>
				
					<article class="item" id="privacy">
					<div class="text-post-body"><p>No tracking, no ads, no data harvesting. Your posts are stored as simple files on servers in Germany, not in some US database shared with third parties. Nobody at FBO reads your content, builds a profile on you, or sells your data to anyone. The platform is fully compliant with the <a href="https://eur-lex.europa.eu/legal-content/DE/TXT/?uri=CELEX%3A32016R0679" target="_blank" rel="noopener noreferrer">DSGVO</a> the European data protection law that gives you the right to know what data is stored about you, have it corrected or deleted, and object to how it is used. In short: your data belongs to you, not to us.</p></div>
					<div class="stamp">Data privacy</div>
				</article>

				<article class="item" id="legal">
					<div class="text-post-body fbo-legal">
						<details class="fbo-legal-item">
							<summary>Annoying stuff</summary>
							<div class="fbo-legal-copy">

								<p><strong>Privacy Policy (GDPR)</strong></p>
								<p><strong>2. Hosting and server logs</strong><br>When you access this website, technically required data (for example IP address, date/time, requested page, browser details) may be processed in server log files to ensure secure operation (Art. 6(1)(f) GDPR).</p>
								<p><strong>3. Contact</strong><br>If you contact us, your transmitted data is processed to handle your request (Art. 6(1)(b) or (f) GDPR).</p>
								<p><strong>4. Storage period</strong><br>Personal data is stored only as long as required for the stated purposes or legal retention duties.</p>
								<p><strong>5. Your rights</strong><br>You have rights of access, rectification, deletion, restriction, data portability, and objection where applicable, plus the right to lodge a complaint with a supervisory authority.</p>
								<p><strong>6. Cookies</strong><br>This website currently does not use non-essential tracking or marketing cookies. If this changes, a consent banner will be shown before such cookies are set.</p>
								<p><strong>Cookie note</strong><br>No non-essential tracking cookies are active right now, so no cookie banner is currently displayed.</p>
							</div>
						</details>
					</div>
					<div class="stamp">Legal</div>
				</article>

				<article class="item" id="source">
					<div class="text-post-body">Source code is available on <a
							href="https://github.com/piquedeux/fbo-blog-template" target="_blank"
							rel="noopener noreferrer">GitHub</a>.</div>
					<div class="stamp">For nerds</div>
				</article>


			</main>
		</div>

	</div>

	<script src="<?= local_asset_url('assets/js/script.js') ?>" defer></script>
	<script>
		(function () {
			var MOBILE_BREAKPOINT = 700;
			var PAGE_SIZE = 20;

			function setHeaderVar() {
				var h = document.getElementById('fboHeader');
				var wrap = document.getElementById('fboPageWrap');
				if (h && wrap) {
					document.documentElement.style.setProperty('--fbo-header-h', h.offsetHeight + 'px');
				}
			}
			setHeaderVar();
			window.addEventListener('resize', setHeaderVar);

			var search = document.getElementById('fboSearch');
			var list = document.getElementById('fboBlogList');
			var noResult = document.getElementById('fboNoResults');
			var loadWrap = document.getElementById('fboLoadMoreWrap');
			var loadBtn = document.getElementById('fboLoadMore');
			var countEl = document.getElementById('fboCount');

			if (!list) return;

			var allItems = Array.from(list.querySelectorAll('.fbo-blog-item'));
			var visibleEnd = PAGE_SIZE;
			var currentQuery = '';

			function isMobile() { return window.innerWidth <= MOBILE_BREAKPOINT; }

			function applyState() {
				var q = currentQuery.toLowerCase().trim();
				var matched = [];

				allItems.forEach(function (item) {
					var fits =
						q === '' ||
						(item.dataset.word || '').toLowerCase().indexOf(q) !== -1 ||
						(item.dataset.url || '').toLowerCase().indexOf(q) !== -1 ||
						(item.dataset.fullurl || '').toLowerCase().indexOf(q) !== -1;
					if (fits) matched.push(item);
				});

				allItems.forEach(function (item) {
					var idx = matched.indexOf(item);
					if (idx === -1) { item.hidden = true; return; }
					item.hidden = (!isMobile() || q !== '') ? false : idx >= visibleEnd;
				});

				if (noResult) noResult.style.display = (q !== '' && matched.length === 0) ? '' : 'none';

				if (loadWrap) loadWrap.style.display = (isMobile() && q === '' && matched.length > visibleEnd) ? '' : 'none';

				if (countEl) {
					var shown = matched.filter(function (i) { return !i.hidden; }).length;
					countEl.textContent = q !== '' ? '(' + shown + ' of ' + allItems.length + ')' : '(' + allItems.length + ')';
				}
			}

			if (search) {
				search.addEventListener('input', function () {
					currentQuery = search.value;
					visibleEnd = PAGE_SIZE;
					applyState();
				});
			}

			if (loadBtn) {
				loadBtn.addEventListener('click', function () {
					visibleEnd += PAGE_SIZE;
					applyState();
				});
			}

			window.addEventListener('resize', applyState);
			applyState();
		})();
	</script>
</body>

</html>