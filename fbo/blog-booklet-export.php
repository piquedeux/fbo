<?php
declare(strict_types=1);

function fbo_booklet_escape(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fbo_booklet_data_uri(string $path, string $mime): string
{
	if (!is_file($path) || !is_readable($path)) {
		return '';
	}
	$data = @file_get_contents($path);
	return is_string($data) ? 'data:' . $mime . ';base64,' . base64_encode($data) : '';
}

function fbo_booklet_media_uri(array $post): string
{
	if ((string) ($post['type'] ?? '') !== 'image' || !function_exists('media_dir_path')) {
		return '';
	}
	$relativePath = ltrim(trim((string) ($post['path'] ?? '')), '/');
	$mediaRoot = realpath(media_dir_path());
	$filePath = realpath(blog_root() . '/' . $relativePath);
	if ($mediaRoot === false || $filePath === false || !is_file($filePath) || !str_starts_with($filePath, $mediaRoot . DIRECTORY_SEPARATOR)) {
		return '';
	}
	$mime = function_exists('mime_content_type') ? (string) @mime_content_type($filePath) : 'image/jpeg';
	return fbo_booklet_data_uri($filePath, str_starts_with($mime, 'image/') ? $mime : 'image/jpeg');
}

function fbo_booklet_estimate_size_bytes(array $posts, array $captions = []): int
{
	$bytes = 180000;
	foreach ([
		__DIR__ . '/assets/fonts/American-Typewriter-Bold.woff2',
		__DIR__ . '/assets/icon/logo.svg',
	] as $assetPath) {
		$size = @filesize($assetPath);
		if (is_int($size) && $size > 0) {
			$bytes += $size;
		}
	}
	$shuffleImages = 0;
	foreach ($posts as $post) {
		if (!is_array($post)) {
			continue;
		}
		$imageUri = fbo_booklet_media_uri($post);
		if ($imageUri !== '') {
			$bytes += (int) ceil(strlen($imageUri) * 1.03);
			if (!empty($post['allow_shuffleboard']) && $shuffleImages < 12) {
				$shuffleImages++;
			}
		}
		if (in_array((string) ($post['type'] ?? ''), ['audio', 'video'], true)) {
			$bytes += 8000;
		}
	}
	return $bytes + ($shuffleImages * 1000);
}

function fbo_booklet_format_size(int $bytes): string
{
	if ($bytes >= 1048576) {
		return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
	}
	return number_format(max(1, $bytes) / 1024, 0, ',', '.') . ' KB';
}

function fbo_booklet_post_url(array $post): string
{
	if (function_exists('blog_share_url')) {
		return blog_share_url((string) ($post['id'] ?? ''));
	}
	return '';
}

function fbo_booklet_link_markup(string $url): string
{
	return '<div class="post-link">' . fbo_booklet_escape($url) . '</div>';
}

function fbo_booklet_shuffleboard_posts(array $fallbackPosts, string $siteName): array
{
	$cards = [];
	$blogsDir = dirname(__DIR__) . '/multi-tenant/blogs';
	$blogDirs = is_dir($blogsDir) ? (scandir($blogsDir) ?: []) : [];
	foreach ($blogDirs as $blogWord) {
		if ($blogWord === '.' || $blogWord === '..') {
			continue;
		}
		$blogPath = $blogsDir . '/' . $blogWord;
		if (!is_dir($blogPath)) {
			continue;
		}
		$settingsPath = $blogPath . '/backend/settings.json';
		$settings = is_file($settingsPath) ? json_decode((string) @file_get_contents($settingsPath), true) : [];
		$accountName = trim((string) (($settings['site_name'] ?? '') ?: $blogWord));
		$postsPath = $blogPath . '/backend/posts.json';
		$blogPosts = is_file($postsPath) ? json_decode((string) @file_get_contents($postsPath), true) : [];
		$blogPosts = is_array($blogPosts) ? $blogPosts : [];
		foreach ($blogPosts as $post) {
			if (!is_array($post) || empty($post['allow_shuffleboard']) || (string) ($post['type'] ?? '') !== 'image') {
				continue;
			}
			$relativePath = ltrim(trim((string) ($post['path'] ?? '')), '/');
			$filePath = realpath($blogPath . '/' . $relativePath);
			if ($relativePath === '' || $filePath === false || !is_file($filePath)) {
				continue;
			}
			$mime = function_exists('mime_content_type') ? (string) @mime_content_type($filePath) : 'image/jpeg';
			$image = fbo_booklet_data_uri($filePath, str_starts_with($mime, 'image/') ? $mime : 'image/jpeg');
			if ($image !== '') {
				$cards[] = ['image' => $image, 'account' => $accountName];
			}
		}
	}
	if ($cards === []) {
		foreach ($fallbackPosts as $post) {
			if (!is_array($post) || empty($post['allow_shuffleboard'])) {
				continue;
			}
			$image = fbo_booklet_media_uri($post);
			if ($image !== '') {
				$cards[] = ['image' => $image, 'account' => $siteName];
			}
		}
	}
	return $cards;
}

function fbo_booklet_snapshot_markup(array $posts, string $siteName): string
{
	$cards = fbo_booklet_shuffleboard_posts($posts, $siteName);
	$accounts = array_values(array_unique(array_map(static fn(array $card): string => (string) $card['account'], $cards)));
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
	$maskImage = $cards[0]['image'] ?? '';
	$markup = '<div class="shuffle-snapshot"><div class="snapshot-title">FBO SHUFFLEBOARD</div><div class="snapshot-blog">' . fbo_booklet_escape(implode(' / ', $accounts)) . '</div><div class="snapshot-grid">';
	$cardIndex = 0;
	$rows = count($heartMask);
	$cols = strlen($heartMask[0]);
	for ($rowIndex = 0; $rowIndex < $rows; $rowIndex++) {
		for ($colIndex = 0; $colIndex < $cols; $colIndex++) {
			if (($heartMask[$rowIndex][$colIndex] ?? '0') === '0' && $maskImage !== '') {
				$x = $cols > 1 ? ($colIndex / ($cols - 1)) * 100 : 0;
				$y = $rows > 1 ? ($rowIndex / ($rows - 1)) * 100 : 0;
				$style = ' style="background-image: linear-gradient(0deg, #ff1a1a 0%, #ff1a1a 100%), url(' . fbo_booklet_escape($maskImage) . '); background-size: 1100% 900%; background-position: ' . number_format($x, 3, '.', '') . '% ' . number_format($y, 3, '.', '') . '%;"';
				$markup .= '<div class="snapshot-cell snapshot-mask"' . $style . '></div>';
				continue;
			}
			$markup .= '<div class="snapshot-cell">';
			if (isset($cards[$cardIndex])) {
				$card = $cards[$cardIndex++];
				$markup .= '<img src="' . fbo_booklet_escape($card['image']) . '" alt=""><span>' . fbo_booklet_escape($card['account']) . '</span>';
			}
			$markup .= '</div>';
		}
	}
	return $markup . '</div></div>';
}

function fbo_booklet_post_markup(array $post, array $captions): string
{
	$type = (string) ($post['type'] ?? 'text');
	$date = date('d.m.Y H:i', (int) ($post['timestamp'] ?? 0));
	$markup = '<div class="post-frame">';
	if ($type === 'text') {
		$text = trim(strip_tags((string) ($post['text'] ?? '')));
		return $markup . '<div class="post-text">' . nl2br(fbo_booklet_escape($text), false) . '</div><div class="post-date">' . fbo_booklet_escape($date) . '</div></div>';
	}
	$image = fbo_booklet_media_uri($post);
	if ($image !== '') {
		$markup .= '<img class="post-image" src="' . fbo_booklet_escape($image) . '" alt=""><div class="post-image-separator"></div>';
		$caption = trim((string) ($captions[(string) ($post['id'] ?? '')] ?? ''));
		if ($caption !== '') {
			$markup .= '<div class="post-caption">' . fbo_booklet_escape($caption) . '</div>';
		}
		return $markup . '<div class="post-date">' . fbo_booklet_escape($date) . '</div></div>';
	}
	if ($type === 'audio' || $type === 'video') {
		$markup .= '<div class="media-placeholder">' . fbo_booklet_escape(strtoupper($type)) . fbo_booklet_link_markup(fbo_booklet_post_url($post)) . '<small>Open this post online.</small></div>';
	} else {
		$markup .= '<div class="media-placeholder">' . fbo_booklet_escape($type) . '<br><small>This media format is not printable as a still image.</small></div>';
	}
	return $markup . '<div class="post-date">' . fbo_booklet_escape($date) . '</div></div>';
}

function fbo_booklet_page(string $class, string $content): string
{
	return '<section class="booklet-page ' . $class . '">' . $content . '</section>';
}

function fbo_export_blog_booklet(array $posts, array $captions, string $siteName): void
{
	$fontPath = __DIR__ . '/assets/fonts/American-Typewriter-Bold.woff2';
	$logoPath = __DIR__ . '/assets/icon/logo.svg';
	$fontUri = fbo_booklet_data_uri($fontPath, 'font/woff2');
	$logo = is_file($logoPath) ? (string) @file_get_contents($logoPath) : '';
	if ($fontUri === '' || $logo === '') {
		http_response_code(500);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'Booklet assets are missing: American-Typewriter-Bold.woff2 or logo.svg.';
		exit;
	}

	$pages = [];
	$pages[] = fbo_booklet_page('cover', '<div class="cover-mark"><span>I</span>' . $logo . '</div><h1>' . fbo_booklet_escape($siteName) . '</h1>');
	foreach ($posts as $post) {
		if (is_array($post)) {
			$pages[] = fbo_booklet_page('post-page', fbo_booklet_post_markup($post, $captions));
		}
	}
	while ((count($pages) + 1) % 4 !== 0) {
		$pages[] = fbo_booklet_page('blank-page', '');
	}
	$pages[] = fbo_booklet_page('back-cover', fbo_booklet_snapshot_markup($posts, $siteName));

	$sheetMarkup = '';
	$total = count($pages);
	for ($sheet = 0; $sheet < $total / 4; $sheet++) {
		$pairs = [[$total - 1 - ($sheet * 2), $sheet * 2], [$sheet * 2 + 1, $total - 2 - ($sheet * 2)]];
		foreach ($pairs as $pair) {
			$sheetMarkup .= '<div class="print-side">' . $pages[$pair[0]] . $pages[$pair[1]] . '</div>';
		}
	}
	$css = <<<'CSS'
@page { size: A4 landscape; margin: 0; }
@font-face { font-family: AmericanTypewriter; src: url('__FONT__') format('woff2'); font-weight: 700; }
@font-face { font-family: Inter; src: local('Inter'); font-weight: 100 900; }
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #777; color: #141414; font-family: AmericanTypewriter, Georgia, serif; }
.print-side { width: 297mm; height: 210mm; display: flex; page-break-after: always; background: #fff; }
.booklet-page { width: 148.5mm; height: 210mm; padding: 15mm; overflow: hidden; background: #fff; position: relative; }
.cover, .back-cover { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: baseline; }
.cover-mark { display: flex; align-items: center; justify-content: center; height: 46mm; font-size: 42mm; line-height: 1; transform: translateY(-40px); }
.cover-mark span { display: block; }
.cover-mark svg { width: 31.5mm; height: 31.5mm; margin-left: 3mm; }
h1 { font-size: 25pt; margin: 8mm 0 0; text-transform: uppercase; }
.post-frame { height: 178mm; border: .5mm solid #141414; padding: 8mm; display: flex; flex-direction: column; }
.post-text { flex: 1; font-family: Inter, sans-serif; font-size: 17pt; line-height: 1.4; white-space: normal; }
.post-image { display: block; width: calc(100% + 16mm); height: 135mm; margin-left: -8mm; object-fit: contain; }
.post-image-separator { border-top: .5mm solid #141414; width: calc(100% + 16mm); margin-left: -8mm; }
.post-caption { font-family: Inter, sans-serif; font-size: 9pt; margin-top: 4mm; }
.post-date { font-size: 8pt; margin-top: auto; padding-top: 4mm; }
.media-placeholder { flex: 1; margin: 0; padding: 12mm; text-align: center; font-size: 16pt; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.media-placeholder small { font-family: Georgia, serif; font-size: 8pt; }
.post-link { font-family: Inter, sans-serif; font-size: 7pt; line-height: 1.25; overflow-wrap: anywhere; margin: 12mm 0 8mm; }
.shuffle-snapshot { width: 100%; }
.snapshot-title { font-size: 16pt; }
.snapshot-blog { font-size: 9pt; margin: 3mm 0 8mm; }
.snapshot-grid { display: grid; grid-template-columns: repeat(11, minmax(0, 1fr)); gap: .45mm; }
.snapshot-cell { aspect-ratio: 3 / 4; border: .35mm solid #101010; display: flex; align-items: flex-end; justify-content: center; overflow: hidden; position: relative; background: #eee; }
.snapshot-mask { border-color: #fff; background-repeat: no-repeat; background-blend-mode: color, normal; filter: saturate(1.1) brightness(.9) contrast(1.1); }
.snapshot-cell img { width: 100%; height: 100%; object-fit: cover; }
.snapshot-cell span { position: absolute; left: 0; right: 0; bottom: 0; padding: 1.5mm; color: #fff; background: rgba(0, 0, 0, .7); font: 6pt Inter, sans-serif; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.blank-page { background: #fff; }
@media screen { .print-side { margin: 10mm auto; box-shadow: 0 1mm 4mm #333; } }
@media print { html, body { background: #fff; } }
CSS;
	$css = str_replace('__FONT__', $fontUri, $css);
	$html = '<!doctype html><html lang="de"><head><meta charset="utf-8"><title>' . fbo_booklet_escape($siteName) . ' - A5 booklet</title><style>' . $css . '</style></head><body>' . $sheetMarkup . '</body></html>';
	$filename = 'fbo-blog-' . preg_replace('/[^A-Za-z0-9_-]/', '', $siteName) . '-a5-booklet.html';
	header('Content-Type: text/html; charset=utf-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Content-Length: ' . (string) strlen($html));
	header('Cache-Control: no-store');
	echo $html;
	exit;
}
