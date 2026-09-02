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

function fbo_booklet_image_data_uri(string $path): string
{
	static $cache = [];
	$cacheKey = $path . '|' . (string) @filemtime($path);
	if (array_key_exists($cacheKey, $cache)) {
		return $cache[$cacheKey];
	}

	$empty = static function () use (&$cache, $cacheKey): string {
		$cache[$cacheKey] = '';
		return '';
	};
	if (!is_file($path) || !is_readable($path)) {
		return $empty();
	}

	// Do not load files that could exhaust the request memory limit.
	$sourceBytes = @filesize($path);
	if (!is_int($sourceBytes) || $sourceBytes <= 0 || $sourceBytes > 16 * 1024 * 1024) {
		return $empty();
	}
	$dimensions = @getimagesize($path);
	if (!is_array($dimensions) || (int) ($dimensions[0] ?? 0) < 1 || (int) ($dimensions[1] ?? 0) < 1) {
		return $empty();
	}
	$sourceWidth = (int) $dimensions[0];
	$sourceHeight = (int) $dimensions[1];
	if ($sourceWidth * $sourceHeight > 40_000_000 || !function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
		return $empty();
	}

	$data = @file_get_contents($path);
	$image = is_string($data) ? @imagecreatefromstring($data) : false;
	unset($data);
	if ($image === false) {
		return $empty();
	}

	$maxDimension = 1800;
	$scale = min(1.0, $maxDimension / max($sourceWidth, $sourceHeight));
	$width = max(1, (int) round($sourceWidth * $scale));
	$height = max(1, (int) round($sourceHeight * $scale));
	$resized = imagecreatetruecolor($width, $height);
	$white = imagecolorallocate($resized, 255, 255, 255);
	imagefill($resized, 0, 0, $white);
	imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
	imagedestroy($image);

	ob_start();
	$encoded = imagejpeg($resized, null, 82);
	$output = $encoded ? (string) ob_get_clean() : '';
	if (!$encoded) {
		ob_end_clean();
	}
	imagedestroy($resized);
	if ($output === '') {
		return $empty();
	}

	$uri = 'data:image/jpeg;base64,' . base64_encode($output);
	$cache[$cacheKey] = $uri;
	return $uri;
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
	return fbo_booklet_image_data_uri($filePath);
}

function fbo_booklet_estimate_size_bytes(array $posts, array $captions = []): int
{
	$bytes = 180000;
	foreach ([__DIR__ . '/assets/fonts/American-Typewriter-Bold.woff2', __DIR__ . '/assets/icon/logo.svg'] as $assetPath) {
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

function fbo_booklet_qr_markup(string $url): string
{
	if ($url === '' || !function_exists('shell_exec')) {
		return '<div class="qr-fallback">' . fbo_booklet_escape($url) . '</div>';
	}
	$command = 'command -v qrencode >/dev/null 2>&1 && qrencode -t SVG -o - -- ' . escapeshellarg($url) . ' 2>/dev/null';
	$svg = @shell_exec($command);
	if (!is_string($svg) || strpos($svg, '<svg') === false) {
		return '<div class="qr-fallback">' . fbo_booklet_escape($url) . '</div>';
	}
	$svg = preg_replace('/<\?xml[^>]*\?>/i', '', $svg) ?? $svg;
	$svg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svg) ?? $svg;
	return '<div class="qr-code">' . $svg . '</div>';
}

function fbo_booklet_snapshot_markup(array $posts, string $siteName): string
{
	$cards = [];
	foreach ($posts as $post) {
		if (!is_array($post) || empty($post['allow_shuffleboard'])) {
			continue;
		}
		$image = fbo_booklet_media_uri($post);
		if ($image !== '') {
			$cards[] = $image;
		}
		if (count($cards) >= 12) {
			break;
		}
	}
	$markup = '<div class="shuffle-snapshot"><div class="snapshot-title">FBO SHUFFLEBOARD</div><div class="snapshot-blog">' . fbo_booklet_escape($siteName) . '</div><div class="snapshot-grid">';
	for ($index = 0; $index < 12; $index++) {
		$markup .= '<div class="snapshot-cell">';
		if (isset($cards[$index])) {
			$markup .= '<img src="' . fbo_booklet_escape($cards[$index]) . '" alt="">';
		}
		$markup .= '</div>';
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
		$markup .= '<div class="media-placeholder">' . fbo_booklet_escape(strtoupper($type)) . fbo_booklet_qr_markup(fbo_booklet_post_url($post)) . '<small>Scan to open this post online.</small></div>';
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
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #777; color: #141414; font-family: AmericanTypewriter, Georgia, serif; }
.print-side { width: 297mm; height: 210mm; display: flex; page-break-after: always; background: #fff; }
.booklet-page { width: 148.5mm; height: 210mm; padding: 15mm; overflow: hidden; background: #fff; position: relative; }
.cover, .back-cover { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: baseline; }
.back-cover { font-family: Inter, Arial, sans-serif; }
.cover-mark { display: flex; align-items: center; justify-content: center; height: 46mm; font-size: 42mm; line-height: 1; }
.cover-mark span { display: block; }
.cover-mark svg { width: 31.5mm; height: 31.5mm; margin-left: 3mm; transform: translateY(-40px); }
h1 { font-size: 25pt; margin: 8mm 0 0; text-transform: uppercase; }
.post-frame { height: 178mm; border: .5mm solid #141414; padding: 8mm; display: flex; flex-direction: column; }
.post-text { flex: 1; font-family: Inter, Arial, sans-serif; font-size: 17pt; line-height: 1.4; white-space: normal; }
.post-image { display: block; width: calc(100% + 16mm); height: 135mm; margin-left: -8mm; object-fit: contain; }
.post-image-separator { border-top: .5mm solid #141414; width: calc(100% + 16mm); margin-left: -8mm; }
.post-caption { font-family: Inter, Arial, sans-serif; font-size: 9pt; margin-top: 4mm; }
.post-date { font-family: Inter, Arial, sans-serif; font-size: 8pt; margin-top: auto; padding-top: 4mm; }
.media-placeholder { flex: 1; margin: 0; padding: 12mm; text-align: center; font-size: 16pt; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.media-placeholder small { font-family: Georgia, serif; font-size: 8pt; }
.qr-code { width: 45mm; height: 45mm; margin: 12mm auto 8mm; }
.qr-code svg { width: 100%; height: 100%; }
.qr-fallback { font-family: Georgia, serif; font-size: 7pt; overflow-wrap: anywhere; margin: 8mm 0; }
.shuffle-snapshot { width: 100%; }
.snapshot-title { font-size: 16pt; }
.snapshot-blog { font-size: 9pt; margin: 3mm 0 8mm; }
.snapshot-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3mm; }
.snapshot-cell { aspect-ratio: 1.4; border: .5mm solid #f00; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.snapshot-cell img { width: 100%; height: 100%; object-fit: cover; }
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
