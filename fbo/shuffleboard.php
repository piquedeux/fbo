<?php
declare(strict_types=1);

if (!function_exists('array_is_list')) {
	function array_is_list(array $array): bool
	{
		$expectedKey = 0;
		foreach ($array as $key => $_value) {
			if ($key !== $expectedKey) {
				return false;
			}
			$expectedKey++;
		}
		return true;
	}
}

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

require_once dirname(__DIR__) . '/multi-tenant/core/db.php';

function local_asset_url(string $relativePath): string
{
	$cleanPath = ltrim($relativePath, '/');

	if (defined('ASSET_BASE_URL') && is_string(ASSET_BASE_URL) && ASSET_BASE_URL !== '') {
		$fullPath = __DIR__ . '/' . $cleanPath;
		$version = is_file($fullPath) ? (string) filemtime($fullPath) : '1';
		return htmlspecialchars(
			rtrim((string) ASSET_BASE_URL, '/') . '/' . $cleanPath . '?v=' . rawurlencode($version),
			ENT_QUOTES,
			'UTF-8'
		);
	}

	$fullPath = __DIR__ . '/' . $cleanPath;
	$version = is_file($fullPath) ? (string) filemtime($fullPath) : '1';
	return htmlspecialchars('/fbo/' . $cleanPath . '?v=' . rawurlencode($version), ENT_QUOTES, 'UTF-8');
}

function get_json_data(string $path): array
{
	if (!is_file($path) || !is_readable($path)) {
		return [];
	}
	$decoded = json_decode((string) @file_get_contents($path), true);
	if (!is_array($decoded)) {
		return [];
	}
	return array_is_list($decoded) ? $decoded : (is_array($decoded['items'] ?? null) ? $decoded['items'] : $decoded);
}

function request_scheme(): string
{
	$https = (string) ($_SERVER['HTTPS'] ?? '');
	if ($https !== '' && strtolower($https) !== 'off') {
		return 'https';
	}
	$forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
	if ($forwardedProto === 'https') {
		return 'https';
	}
	return ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http';
}

function shuffle_format_date_time(string $date): string
{
	$dt = DateTime::createFromFormat('Y-m-d H:i:s', $date);
	return $dt ? $dt->format('d.m.Y H:i') : $date;
}

$projectRoot = dirname(__DIR__);
$blogsDir = $projectRoot . '/multi-tenant/blogs';
$scheme = request_scheme();
$host = (string) ($_SERVER['HTTP_HOST'] ?? 'example.com');

$blogRows = function_exists('mt_list_blogs') ? mt_list_blogs() : [];
$blogMetaByWord = [];
foreach ($blogRows as $row) {
	if (!is_array($row)) {
		continue;
	}
	$blogWord = strtolower(trim((string) ($row['blog_word'] ?? '')));
	if ($blogWord === '') {
		continue;
	}
	$blogMetaByWord[$blogWord] = [
		'created_at' => (string) ($row['created_at'] ?? ''),
	];
}

$cards = [];
$blogs = [];

if (is_dir($blogsDir)) {
	$dirs = array_values(array_filter(scandir($blogsDir) ?: [], static function (string $dir): bool {
		return $dir !== '.' && $dir !== '..';
	}));

// Initialize counters before the loop
$totalBlogsCount = 0;
$activeBlogsCount = 0;

Foreach ($dirs as $word) {
		$blogPath = $blogsDir . '/' . $word;
		if (!is_dir($blogPath)) {
			continue;
		}

		$posts = get_json_data($blogPath . '/backend/posts.json');
		$settings = get_json_data($blogPath . '/backend/settings.json');
		$siteName = trim((string) ($settings['site_name'] ?? $word));
		$wordKey = strtolower((string) $word);
		$createdAt = (string) ($blogMetaByWord[$wordKey]['created_at'] ?? '');
		if ($createdAt === '') {
			$createdAt = date('Y-m-d H:i:s', (int) (@filemtime($blogPath) ?: time()));
		}
			$postCount = is_array($posts) ? count($posts) : 0;

			if ($postCount <= 0) {
				continue;
			}

			$mediaTypes = [];
			foreach ($posts as $p) {
				if (!is_array($p)) {
					continue;
				}
				$t = strtolower(trim((string) ($p['type'] ?? '')));
				if ($t === '') {
					continue;
				}
				$mediaTypes[$t] = true;
			}
			$mediaTypesList = array_values(array_map('strval', array_keys($mediaTypes)));
			$mediaTypesDisplay = $mediaTypesList ? implode(', ', $mediaTypesList) : 'mixed';
		$blogUrl = '/blog/' . rawurlencode($word);
		$fullUrl = $scheme . '://' . $host . $blogUrl;

		$blogs[] = [
			'word' => $wordKey,
			'url' => $blogUrl,
			'fullUrl' => $fullUrl,
			'name' => $siteName,
			'post_count' => $postCount,
			'media_types' => $mediaTypesList,
			'media_types_display' => $mediaTypesDisplay,
			'created_at' => $createdAt,
			'created_at_display' => shuffle_format_date_time($createdAt),
			'sort_created_at' => strtotime($createdAt) ?: 0,
		];

		foreach ($posts as $item) {
			if (!is_array($item)) {
				continue;
			}
			if (empty($item['allow_shuffleboard']) || (string) ($item['type'] ?? '') !== 'image') {
				continue;
			}

			$path = ltrim((string) ($item['path'] ?? ''), '/');
			$id = (string) ($item['id'] ?? '');
			if ($path === '' || $id === '') {
				continue;
			}

			$cards[] = [
				'id' => $id,
				'timestamp' => (int) ($item['timestamp'] ?? 0),
				'blog_name' => $siteName,
				'media_url' => '/multi-tenant/blogs/' . rawurlencode($word) . '/' . $path,
				'post_url' => '/blog/' . rawurlencode($word) . '?post_id=' . rawurlencode($id),
			];
		}
	}
}

$blogs[] = [
	'word' => 'moritz',
	'url' => 'https://blog.piquedeux.de',
	'fullUrl' => 'https://blog.piquedeux.de',
	'name' => 'moritz',
	'post_count' => 999,
	'post_count_display' => '999+',
	'media_types' => ['image', 'video', 'text', 'audio'],
	'media_types_display' => 'image, video, text, audio',
	'created_at' => '2026-01-01 00:00:00',
	'created_at_display' => shuffle_format_date_time('2026-01-01 00:00:00'),
	'sort_created_at' => 0,
];

usort($blogs, static function (array $left, array $right): int {
	$leftSort = (int) ($left['sort_created_at'] ?? 0);
	$rightSort = (int) ($right['sort_created_at'] ?? 0);
	if ($leftSort !== $rightSort) {
		return $rightSort <=> $leftSort;
	}
	return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
});

if ($cards !== []) {
	shuffle($cards);
}

$heartMask = [
	'11111111111',
	'11001110011',
	'10000100001',
	'10000000001',
	'11000000011',
	'11100000111',
	'11110001111',
	'11111011111',
	'11111111111',
];

$rows = count($heartMask);
$cols = strlen($heartMask[0]);

$maskCoordLookup = [];
for ($rowIndex = 0; $rowIndex < $rows; $rowIndex++) {
	$rowMask = $heartMask[$rowIndex];
	for ($colIndex = 0; $colIndex < $cols; $colIndex++) {
		if (($rowMask[$colIndex] ?? '0') === '0') {
			$maskCoordLookup[$rowIndex . '-' . $colIndex] = true;
		}
	}
}

$initialMaskCard = $cards[0] ?? null;
$maskImageCss = $initialMaskCard !== null
	? 'url(' . json_encode($initialMaskCard['media_url'], JSON_UNESCAPED_SLASHES) . ')'
	: 'none';

$gridCards = $cards;
$gridCardCount = count($gridCards);
$blogCount = count($blogs);
?>

<!--
 __  __       ____  
|  \/  |     / ___| 
| \  / |    | | __  
| |\/| |    | |(  | 
| |  | |  _ | |_) |  _ 
(_)  (_) (_) \____| (_)
moritzgauss.com | @piquedeux | github.com/piquedeux
-->
<!doctype html>
<html lang="de">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="FBO Shuffleboard with rotating featured mask and blog search.">
	<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
	<link rel="icon" type="image/png" href="<?= local_asset_url('assets/icon/icon.png') ?>">
	<link rel="apple-touch-icon" href="<?= local_asset_url('assets/icon/heart-icon.png') ?>">
	<title>FBO Shuffleboard</title>
	<link rel="stylesheet" href="<?= local_asset_url('assets/css/styles.css') ?>">
	<link rel="stylesheet" href="<?= local_asset_url('assets/css/shuffleboard.css') ?>">
</head>

<body class="shuffleboard-page">
	<header class="hero">
		<div class="hero-head">
			<a href="/" class="logo logo-link"><span class="fbo-title-mark-black">FBO</span>Shuffleboard</a>
			<div class="hero-right">
				<div class="hero-actions">
					<a href="/create" class="ui-btn">create blog</a>
					<a href="/fbo/fbo" class="ui-btn shuffle-info-btn" aria-label="About FBO" title="FBO info">i</a>
				</div>
			</div>
		</div>
		<div class="subtitle-line">Fuck Being Online.</div>
	</header>

	<nav class="topbar shuffleboard-topbar">
		<div class="shuffleboard-topbar-main">
			<div class="shuffleboard-topbar-left-actions">
				<button type="button" class="ui-btn" id="themeToggle">dark mode</button>
			</div>
			<div class="shuffleboard-topbar-actions">
				<button type="button" class="ui-btn" id="shuffleMaskNow">shuffle</button>
			</div>
		</div>
		<div class="shuffleboard-topbar-meta">
			<span class="meta"><?= $gridCardCount ?> posts in shuffle</span>
		</div>
	</nav>

	<main class="shuffleboard-wrap" data-grid-count="<?= $gridCardCount ?>">
		<div
			class="shuffleboard-grid"
			data-mask-image="<?= htmlspecialchars($maskImageCss, ENT_QUOTES, 'UTF-8') ?>"
			data-grid-cols="<?= $cols ?>"
			data-grid-rows="<?= $rows ?>"
		>
			<?php
			$cursor = 0;
			for ($rowIndex = 0; $rowIndex < $rows; $rowIndex++) {
				$rowMask = $heartMask[$rowIndex];
				for ($colIndex = 0; $colIndex < $cols; $colIndex++) {
					$isPostSlot = ($rowMask[$colIndex] ?? '0') === '1';
					$coordKey = $rowIndex . '-' . $colIndex;

					if ($isPostSlot) {
						if (isset($gridCards[$cursor])) {
							$card = $gridCards[$cursor++];
							?>
							<article class="item shuffle-cell shuffle-item">
								<a class="shuffle-post-link" href="<?= htmlspecialchars($card['post_url'], ENT_QUOTES, 'UTF-8') ?>">
									<div class="shuffle-card-body">
										<div class="media-wrap">
											<img src="<?= htmlspecialchars($card['media_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($card['blog_name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
										</div>
									</div>
								</a>
								<div class="shuffle-blog-stamp"><?= htmlspecialchars($card['blog_name'], ENT_QUOTES, 'UTF-8') ?></div>
							</article>
							<?php
						} else {
							?>
							<div class="shuffle-cell shuffle-empty" aria-hidden="true"></div>
							<?php
						}
						continue;
					}

					if (isset($maskCoordLookup[$coordKey]) && $initialMaskCard !== null) {
						?>
						<a
							class="shuffle-cell shuffle-mask-cell"
							href="<?= htmlspecialchars((string) $initialMaskCard['post_url'], ENT_QUOTES, 'UTF-8') ?>"
							data-mask-cell="1"
							data-col="<?= $colIndex ?>"
							data-row="<?= $rowIndex ?>"
							aria-label="featured post"
						></a>
						<?php
						continue;
					}
					?>
					<div class="shuffle-cell shuffle-hole" aria-hidden="true"></div>
					<?php
				}
			}
			?>
		</div>

		<section class="shuffle-search-block" aria-labelledby="shuffleSearchLabel">
<label class="shuffle-search-label" id="shuffleSearchLabel" for="shuffleBlogSearch">
    Search blogs <span class="meta"><?= $allBlogsFinal ?></span> 
</label>
			<input
				type="search"
				id="shuffleBlogSearch"
				class="shuffle-search-input"
				placeholder="Search by BLOGNAME"
				autocomplete="off"
				spellcheck="false"
			>
			<div class="shuffle-search-preview" id="shuffleSearchPreview"></div>
		</section>

		<section class="shuffle-blog-directory" aria-labelledby="shuffleBlogDirectoryLabel">
			<div class="shuffle-blog-directory-head">
				<div>
					<h2 class="shuffle-blog-directory-title" id="shuffleBlogDirectoryLabel">Browse the network</h2>
				</div>
			</div>
			<div class="shuffle-blog-directory-grid">
				<?php foreach ($blogs as $blog): ?>
					<div class="shuffle-blog-card" data-blog-word="<?= htmlspecialchars((string) ($blog['word'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
						<a class="shuffle-blog-card-link" href="<?= htmlspecialchars((string) $blog['url'], ENT_QUOTES, 'UTF-8') ?>">
							<div class="shuffle-blog-card-name"><?= htmlspecialchars((string) $blog['name'], ENT_QUOTES, 'UTF-8') ?></div>
							<div class="shuffle-blog-card-meta"><?= htmlspecialchars((string) ($blog['media_types_display'] ?? 'mixed'), ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars((string) ($blog['post_count_display'] ?? ((string) ((int) ($blog['post_count'] ?? 0)) . ' posts')), ENT_QUOTES, 'UTF-8') ?></div>
							<div class="shuffle-blog-card-meta">created <?= htmlspecialchars((string) ($blog['created_at_display'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
						</a>
						<div class="shuffle-blog-card-actions">
							<button type="button" class="shuffle-blog-favorite-btn ui-btn" data-blog-word="<?= htmlspecialchars((string) ($blog['word'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-pressed="false">favorite</button>
							<button type="button" class="shuffle-blog-preview-btn ui-btn" data-fullurl="<?= htmlspecialchars((string) ($blog['fullUrl'] ?? $blog['url']), ENT_QUOTES, 'UTF-8') ?>" aria-label="Quick view <?= htmlspecialchars((string) $blog['name'], ENT_QUOTES, 'UTF-8') ?>">quickview</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	</main>

	<script id="shuffleMaskCardsData" type="application/json"><?= htmlspecialchars(json_encode($cards, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_NOQUOTES, 'UTF-8') ?></script>
	<script id="shuffleBlogsData" type="application/json"><?= htmlspecialchars(json_encode($blogs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_NOQUOTES, 'UTF-8') ?></script>
	<script src="<?= local_asset_url('assets/js/script.js') ?>" defer></script>
	<script src="<?= local_asset_url('assets/js/shuffleboard.js') ?>" defer></script>
</body>

</html>
