<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

function local_asset_url(string $relativePath): string
{
	$cleanPath = ltrim($relativePath, '/');
	$fullPath = __DIR__ . '/' . $cleanPath;
	$version = is_file($fullPath) ? (string) filemtime($fullPath) : '1';
	return htmlspecialchars('/fbo/' . $cleanPath . '?v=' . rawurlencode($version), ENT_QUOTES, 'UTF-8');
}

function normalize_blog_word(string $value): string
{
	$word = strtolower(trim($value));
	$word = preg_replace('/[^a-z0-9_-]/', '', $word) ?? '';
	return mb_substr($word, 0, 24);
}

function encode_path_segments(string $path): string
{
	$segments = array_values(array_filter(explode('/', $path), static function (string $segment): bool {
		return $segment !== '';
	}));
	$encoded = array_map(static function (string $segment): string {
		return rawurlencode($segment);
	}, $segments);
	return implode('/', $encoded);
}

function read_json_array_file(string $path): array
{
	if (!is_file($path)) {
		return [];
	}
	$decoded = json_decode((string) file_get_contents($path), true);
	if (!is_array($decoded)) {
		return [];
	}
	if (isset($decoded['items']) && is_array($decoded['items'])) {
		return $decoded['items'];
	}
	return array_is_list($decoded) ? $decoded : [];
}

function read_blog_display_name(string $settingsPath, string $fallback): string
{
	if (!is_file($settingsPath)) {
		return $fallback;
	}
	$decoded = json_decode((string) file_get_contents($settingsPath), true);
	if (!is_array($decoded)) {
		return $fallback;
	}
	$siteName = trim((string) ($decoded['site_name'] ?? ''));
	return $siteName !== '' ? $siteName : $fallback;
}

function normalize_shuffle_item(array $item): ?array
{
	$id = trim((string) ($item['id'] ?? ''));
	$type = trim((string) ($item['type'] ?? ''));
	$timestamp = (int) ($item['timestamp'] ?? 0);
	$allowed = !empty($item['allow_shuffleboard']);

	if (!$allowed || $id === '' || $timestamp <= 0 || $type !== 'image') {
		return null;
	}

	$path = trim((string) ($item['path'] ?? ''));
	if ($path === '') {
		return null;
	}

	return [
		'id' => $id,
		'type' => $type,
		'path' => $path,
		'timestamp' => $timestamp,
	];
}

function build_standalone_media_url(string $path): string
{
	if (preg_match('#^https?://#i', $path)) {
		return $path;
	}
	return '/fbo/' . ltrim($path, '/');
}

function build_multi_tenant_media_url(string $blogWord, string $path): string
{
	if (preg_match('#^https?://#i', $path)) {
		return $path;
	}

	$cleanPath = ltrim($path, '/');
	if (str_starts_with($cleanPath, 'media/')) {
		return '/multi-tenant/blogs/' . rawurlencode($blogWord) . '/' . encode_path_segments($cleanPath);
	}

	return '/multi-tenant/blogs/' . rawurlencode($blogWord) . '/' . encode_path_segments($cleanPath);
}

function linkify_text_post_content(string $text): string
{
	$escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	$linked = preg_replace_callback(
		'~((?:https?://|www\.)[^\s<]+)~iu',
		static function (array $matches): string {
			$display = (string) ($matches[1] ?? '');
			if ($display === '') {
				return '';
			}

			$trimmedDisplay = rtrim($display, '.,!?;:)]}');
			$suffix = substr($display, strlen($trimmedDisplay));
			if ($trimmedDisplay === '') {
				return $display;
			}

			$hrefRaw = html_entity_decode($trimmedDisplay, ENT_QUOTES, 'UTF-8');
			if (!preg_match('~^https?://~i', $hrefRaw)) {
				$hrefRaw = 'https://' . $hrefRaw;
			}

			$href = htmlspecialchars($hrefRaw, ENT_QUOTES, 'UTF-8');
			return '<a class="text-link" href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $trimmedDisplay . '</a>' . $suffix;
		},
		$escaped
	);

	if (!is_string($linked)) {
		return nl2br($escaped);
	}

	return nl2br($linked);
}

$cards = [];
$projectRoot = dirname(__DIR__);
$standaloneRoot = __DIR__;

$standalonePostsPath = $standaloneRoot . '/backend/posts.json';
$standaloneSettingsPath = $standaloneRoot . '/backend/settings.json';
$standaloneName = read_blog_display_name($standaloneSettingsPath, 'fbo');
foreach (read_json_array_file($standalonePostsPath) as $item) {
	if (!is_array($item)) {
		continue;
	}

	$normalized = normalize_shuffle_item($item);
	if ($normalized === null) {
		continue;
	}

	if (isset($normalized['path'])) {
		$normalized['media_url'] = build_standalone_media_url((string) $normalized['path']);
	}
	$normalized['blog_name'] = $standaloneName;
	$normalized['post_url'] = '/fbo/?post_id=' . rawurlencode((string) $normalized['id']);
	$cards[] = $normalized;
}

$blogs = [];
$dbFile = $projectRoot . '/multi-tenant/core/db.php';
if (is_file($dbFile)) {
	try {
		require_once $dbFile;
		if (function_exists('mt_list_blogs')) {
			$blogs = mt_list_blogs();
		}
	} catch (Throwable $e) {
		$blogs = [];
	}
}

$blogEntries = [];
if (is_array($blogs)) {
	foreach ($blogs as $row) {
		if (!is_array($row)) {
			continue;
		}
		$word = normalize_blog_word((string) ($row['blog_word'] ?? ''));
		if ($word !== '') {
			$blogEntries[$word] = true;
		}
	}
}

$blogsDir = $projectRoot . '/multi-tenant/blogs';
if (is_dir($blogsDir)) {
	$entries = scandir($blogsDir);
	if (is_array($entries)) {
		foreach ($entries as $entry) {
			$word = normalize_blog_word((string) $entry);
			if ($word !== '') {
				$blogEntries[$word] = true;
			}
		}
	}
}

foreach (array_keys($blogEntries) as $blogWord) {
	$blogRoot = $blogsDir . '/' . $blogWord;
	$postsPath = $blogRoot . '/backend/posts.json';
	$settingsPath = $blogRoot . '/backend/settings.json';
	$blogName = read_blog_display_name($settingsPath, $blogWord);

	foreach (read_json_array_file($postsPath) as $item) {
		if (!is_array($item)) {
			continue;
		}

		$normalized = normalize_shuffle_item($item);
		if ($normalized === null) {
			continue;
		}

		if (isset($normalized['path'])) {
			$normalized['media_url'] = build_multi_tenant_media_url($blogWord, (string) $normalized['path']);
		}
		$normalized['blog_name'] = $blogName;
		$normalized['post_url'] = '/blog/' . rawurlencode($blogWord) . '?post_id=' . rawurlencode((string) $normalized['id']);
		$cards[] = $normalized;
	}
}

$createdBlogsCount = count($blogEntries);

$boardShuffleSeed = (int) ($_GET['shuffle_seed'] ?? 0);
if ($boardShuffleSeed > 0) {
	usort($cards, static function (array $a, array $b) use ($boardShuffleSeed): int {
		$idA = (string) ($a['id'] ?? '');
		$idB = (string) ($b['id'] ?? '');
		$hashA = hash('sha256', $boardShuffleSeed . '|' . $idA);
		$hashB = hash('sha256', $boardShuffleSeed . '|' . $idB);
		$cmp = $hashA <=> $hashB;
		if ($cmp !== 0) {
			return $cmp;
		}
		return $idA <=> $idB;
	});
} else {
	usort($cards, static function (array $a, array $b): int {
		$timeA = (int) ($a['timestamp'] ?? 0);
		$timeB = (int) ($b['timestamp'] ?? 0);
		if ($timeA === $timeB) {
			return strcmp((string) ($b['id'] ?? ''), (string) ($a['id'] ?? ''));
		}
		return $timeB <=> $timeA;
	});
}

$shuffleRefreshUrl = '/shuffleboard?shuffle_seed=' . rawurlencode((string) random_int(100000, 999999999));

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

$searchBlogs = [];
if (is_array($blogs)) {
	foreach ($blogs as $row) {
		if (!is_array($row)) {
			continue;
		}
		$word = normalize_blog_word((string) ($row['blog_word'] ?? ''));
		if ($word === '') {
			continue;
		}
		$searchBlogs[] = [
			'word' => $word,
			'url' => '/blog/' . rawurlencode($word),
			'fullUrl' => 'https://' . (string) ($_SERVER['HTTP_HOST'] ?? 'example.com') . '/blog/' . rawurlencode($word),
		];
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="FBO Shuffleboard - image posts by FBO blogs.">
	<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
	<link rel="icon" type="image/png" href="<?= local_asset_url('assets/icon/icon.png') ?>">
	<link rel="apple-touch-icon" href="<?= local_asset_url('assets/icon/icon.png') ?>">
	<title>FBO Shuffleboard</title>
	<link rel="stylesheet" href="<?= local_asset_url('assets/css/styles.css') ?>">
	<link rel="stylesheet" href="<?= local_asset_url('assets/css/shuffleboard.css') ?>">
</head>
<body class="intro-loading">
	<div class="intro-overlay" id="introOverlay" aria-hidden="true">
		<div class="intro-fbo" id="introFboText">F</div>
	</div>

	<header class="hero">
		<div class="hero-head">
			<a href="/shuffleboard" class="logo logo-link"><span class="fbo-title-mark-black">FBO</span> <sup>Shuffleboard</sup></a>
			<div class="hero-right">
				<div class="hero-actions shuffleboard-actions">
					<a href="/login" class="ui-btn">Create blog</a>
					<a href="/fbo/fbo" class="ui-btn shuffle-info-btn" aria-label="Project page" title="Project page">i</a>
				</div>
			</div>
		</div>
		<div class="subtitle-line">Image posts by FBO blogs.</div>
	</header>

	<div class="shuffleboard-wrap">
		<div class="shuffleboard-headline">
			<div class="shuffleboard-headline-left">
				<button type="button" class="ui-btn" id="themeToggle">dark mode</button>
			</div>
			<div class="shuffleboard-headline-right">
				<a href="<?= htmlspecialchars($shuffleRefreshUrl, ENT_QUOTES, 'UTF-8') ?>" class="ui-btn">shuffle</a>
				<div class="shuffleboard-meta"><?= $createdBlogsCount ?> created blogs</div>
			</div>
		</div>

		<main class="shuffleboard-grid">
			<?php $cursor = 0; ?>
			<?php foreach ($heartMask as $rowMask): ?>
				<?php for ($col = 0; $col < 11; $col++): ?>
					<?php if (($rowMask[$col] ?? '0') !== '1'): ?>
						<div class="shuffle-cell shuffle-hole" aria-hidden="true"></div>
						<?php continue; ?>
					<?php endif; ?>

					<?php if (!isset($cards[$cursor])): ?>
						<div class="shuffle-cell shuffle-empty" aria-hidden="true"></div>
						<?php continue; ?>
					<?php endif; ?>

					<?php $card = $cards[$cursor]; ?>
					<?php $cursor++; ?>
					<?php $blogNameRaw = trim((string) ($card['blog_name'] ?? '')); ?>
					<?php $blogNameShort = mb_substr($blogNameRaw, 0, 12); ?>
					<?php if (mb_strlen($blogNameRaw) > 12) {
						$blogNameShort .= '..';
					} ?>
					<article class="item shuffle-cell shuffle-item">
						<a class="shuffle-post-link" href="<?= htmlspecialchars((string) ($card['post_url'] ?? '/'), ENT_QUOTES, 'UTF-8') ?>">
							<div class="shuffle-card-body">
								<?php $mediaUrl = htmlspecialchars((string) ($card['media_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
								<div class="media-wrap">
									<img src="<?= $mediaUrl ?>" alt="Shuffleboard image post" loading="lazy">
								</div>
							</div>
						</a>
						<div class="shuffle-blog-stamp"><?= htmlspecialchars($blogNameShort, ENT_QUOTES, 'UTF-8') ?></div>
					</article>
				<?php endfor; ?>
			<?php endforeach; ?>
		</main>

		<?php if ($cards === []): ?>
			<div class="shuffleboard-empty-state">No image posts are enabled for FBO Shuffleboard yet. In compose mode, creators can allow image posts on Shuffleboard.</div>
		<?php endif; ?>

		<section class="shuffle-search-block">
			<div class="shuffle-search-hint">Existing blogs</div>
			<input type="search" id="shuffleBlogSearch" class="shuffle-search-input" placeholder="Search blog name..." autocomplete="off" spellcheck="false">
			<div class="shuffle-search-preview" id="shuffleSearchPreview"></div>
		</section>
	</div>

	<script id="shuffleBlogsData" type="application/json"><?= json_encode($searchBlogs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
	<script src="<?= local_asset_url('assets/js/script.js') ?>" defer></script>
	<script src="<?= local_asset_url('assets/js/shuffleboard.js') ?>" defer></script>
</body>
</html>
