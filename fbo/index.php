<?php
declare(strict_types=1);

if (!function_exists('str_starts_with')) {
	function str_starts_with(string $haystack, string $needle): bool
	{
		if ($needle === '') {
			return true;
		}
		return substr($haystack, 0, strlen($needle)) === $needle;
	}
}

if (!function_exists('str_contains')) {
	function str_contains(string $haystack, string $needle): bool
	{
		if ($needle === '') {
			return true;
		}
		return strpos($haystack, $needle) !== false;
	}
}

session_start();

const MAX_TEXT_POST_LENGTH = 280;
const MAX_UPLOAD_FILE_SIZE_BYTES = 104857600;
const MAX_UPLOAD_FILES_PER_REQUEST = 10;
const MEDIA_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'webm', 'm4v', 'mp3', 'wav', 'flac', 'ogg', 'm4a'];
const BLOG_WORD_MAX_LENGTH = 24;

function blog_root(): string
{
	if (defined('BLOG_ROOT') && is_string(BLOG_ROOT) && BLOG_ROOT !== '') {
		return rtrim((string) BLOG_ROOT, '/');
	}

	$cwd = getcwd();
	if ($cwd !== false && $cwd !== '') {
		return rtrim($cwd, '/');
	}

	return rtrim(__DIR__, '/');
}

function backend_dir_path(): string
{
	$path = blog_root() . '/backend';
	if (!is_dir($path)) {
		$realBase = realpath(blog_root());
		if ($realBase !== false) {
			mkdir($path, 0775, true);
		}
	}
	return $path;
}

function settings_path(): string
{
	return backend_dir_path() . '/settings.json';
}

function auth_path(): string
{
	return backend_dir_path() . '/.auth.json';
}

function otp_path(): string
{
	return backend_dir_path() . '/.otp.json';
}

function posts_path(): string
{
	return backend_dir_path() . '/posts.json';
}

function media_dir_path(): string
{
	$path = blog_root() . '/media';
	if (!is_dir($path)) {
		$realBase = realpath(blog_root());
		if ($realBase !== false) {
			mkdir($path, 0775, true);
		}
	}
	return $path;
}

function asset_url(string $path): string
{
	if ($path === '' || preg_match('#^https?://#i', $path)) {
		return $path;
	}

	if (defined('MEDIA_WEB_ROOT') && is_string(MEDIA_WEB_ROOT) && MEDIA_WEB_ROOT !== '') {
		if (str_starts_with(ltrim($path, '/'), 'media/')) {
			$filename = substr(ltrim($path, '/'), strlen('media/'));
			return rtrim((string) MEDIA_WEB_ROOT, '/') . '/' . rawurlencode($filename);
		}
	}

	$segments = explode('/', $path);
	$encodedSegments = array_map(static function (string $segment): string {
		return rawurlencode($segment);
	}, $segments);

	$encoded = implode('/', $encodedSegments);
	if ($path[0] === '/' && ($encoded === '' || $encoded[0] !== '/')) {
		$encoded = '/' . $encoded;
	}

	return $encoded;
}

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

	$fullPath = blog_root() . '/' . $cleanPath;
	$version = is_file($fullPath) ? (string) filemtime($fullPath) : '1';
	return htmlspecialchars($cleanPath . '?v=' . rawurlencode($version), ENT_QUOTES, 'UTF-8');
}

function normalize_blog_word(string $value): string
{
	$word = trim($value);
	$word = preg_replace('/[^A-Za-z0-9_-]/', '', $word) ?? '';
	$word = mb_substr($word, 0, BLOG_WORD_MAX_LENGTH);
	return $word === '' ? 'fbo' : $word;
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

function blog_self_url(): string
{
	$scheme = request_scheme();
	$host = (string) ($_SERVER['HTTP_HOST'] ?? 'example.com');
	$raw = trim((string) ($_GET['blog'] ?? ''));
	if ($raw !== '') {
		$word = preg_replace('/[^A-Za-z0-9_-]/', '', $raw) ?? '';
		$word = strtolower(mb_substr($word, 0, 24));
		if ($word !== '') {
			return $scheme . '://' . $host . '/blog/' . rawurlencode($word);
		}
	}
	$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
	$path = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');
	return $scheme . '://' . $host . $path;
}


function blog_path_preview_url(string $blogWord): string
{
	$scheme = request_scheme();
	$host = (string) ($_SERVER['HTTP_HOST'] ?? 'example.com');
	$word = normalize_blog_word($blogWord);
	return $scheme . '://' . $host . '/blog/' . rawurlencode(strtolower($word));
}

function load_settings(): array
{
	$default = [
		'site_name' => 'fbo',
		'hero_subtitle' => '',
		'recovery_email' => '',
	];

	$path = settings_path();
	if (!is_file($path)) {
		return $default;
	}

	$decoded = json_decode((string) file_get_contents($path), true);
	if (!is_array($decoded)) {
		return $default;
	}

	$siteName = normalize_blog_word((string) ($decoded['site_name'] ?? $default['site_name']));
	$subtitle = trim((string) ($decoded['hero_subtitle'] ?? $default['hero_subtitle']));
	$rawEmail = trim((string) ($decoded['recovery_email'] ?? ''));

	return [
		'site_name' => $siteName,
		'hero_subtitle' => mb_substr($subtitle, 0, 180),
		'recovery_email' => (filter_var($rawEmail, FILTER_VALIDATE_EMAIL) !== false) ? $rawEmail : '',
	];
}

function save_settings(array $settings): void
{
	$rawEmail = trim((string) ($settings['recovery_email'] ?? ''));
	$payload = [
		'site_name' => normalize_blog_word((string) ($settings['site_name'] ?? 'fbo')),
		'hero_subtitle' => mb_substr(trim((string) ($settings['hero_subtitle'] ?? '')), 0, 180),
		'recovery_email' => (filter_var($rawEmail, FILTER_VALIDATE_EMAIL) !== false) ? $rawEmail : '',
	];

	file_put_contents(settings_path(), json_encode($payload, JSON_UNESCAPED_SLASHES));
}

function load_smtp_config(): array
{
	// 1. Standalone: fbo/smtp-config.php
	$standalone = __DIR__ . '/smtp-config.php';
	if (is_file($standalone)) {
		$cfg = @(include $standalone);
		if (is_array($cfg) && isset($cfg['smtp_host']) && $cfg['smtp_host'] !== '') {
			return $cfg;
		}
	}
	// 2. Multi-tenant: project-root/multi-tenant/config.php
	$mt = dirname(__DIR__) . '/multi-tenant/config.php';
	if (is_file($mt)) {
		$cfg = @(include $mt);
		if (is_array($cfg) && isset($cfg['smtp_host']) && $cfg['smtp_host'] !== '') {
			return $cfg;
		}
	}
	return [];
}

function smtp_send(array $cfg, string $to, string $subject, string $body): bool
{
	$host = (string) ($cfg['smtp_host'] ?? '');
	$port = (int) ($cfg['smtp_port'] ?? 465);
	$user = (string) ($cfg['smtp_user'] ?? '');
	$pass = (string) ($cfg['smtp_pass'] ?? '');
	$from = (string) ($cfg['smtp_from'] ?? $user);

	if ($host === '' || $user === '' || $pass === '' || $to === '') {
		return false;
	}

	$useSSL = ($port === 465);
	$useTLS = ($port === 587);
	$address = ($useSSL ? 'ssl://' : '') . $host . ':' . $port;

	$context = stream_context_create([
		'ssl' => [
			'verify_peer' => true,
			'verify_peer_name' => true,
			'allow_self_signed' => false,
		],
	]);

	$socket = @stream_socket_client($address, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
	if ($socket === false) {
		return false;
	}
	stream_set_timeout($socket, 30);

	$readResponse = static function () use ($socket): string {
		$buf = '';
		while (!feof($socket)) {
			$line = fgets($socket, 512);
			if ($line === false) {
				break;
			}
			$buf .= $line;
			if (strlen($line) >= 4 && $line[3] === ' ') {
				break;
			}
		}
		return $buf;
	};

	$send = static function (string $data) use ($socket): void {
		@fwrite($socket, $data . "\r\n");
	};

	$r = $readResponse();
	if (!str_starts_with(trim($r), '220')) {
		fclose($socket);
		return false;
	}

	$send('EHLO localhost');
	$r = $readResponse();
	if (!str_starts_with(trim($r), '250')) {
		fclose($socket);
		return false;
	}

	if ($useTLS) {
		$send('STARTTLS');
		$r = $readResponse();
		if (!str_starts_with(trim($r), '220')) {
			fclose($socket);
			return false;
		}
		if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
			fclose($socket);
			return false;
		}
		$send('EHLO localhost');
		$r = $readResponse();
		if (!str_starts_with(trim($r), '250')) {
			fclose($socket);
			return false;
		}
	}

	$send('AUTH LOGIN');
	$r = $readResponse();
	if (!str_starts_with(trim($r), '334')) {
		fclose($socket);
		return false;
	}

	$send(base64_encode($user));
	$r = $readResponse();
	if (!str_starts_with(trim($r), '334')) {
		fclose($socket);
		return false;
	}

	$send(base64_encode($pass));
	$r = $readResponse();
	if (!str_starts_with(trim($r), '235')) {
		fclose($socket);
		return false;
	}

	$send('MAIL FROM:<' . $from . '>');
	$r = $readResponse();
	if (!str_starts_with(trim($r), '250')) {
		fclose($socket);
		return false;
	}

	$send('RCPT TO:<' . $to . '>');
	$r = $readResponse();
	if (!str_starts_with(trim($r), '25')) {
		fclose($socket);
		return false;
	}

	$send('DATA');
	$r = $readResponse();
	if (!str_starts_with(trim($r), '354')) {
		fclose($socket);
		return false;
	}

	$safeBody = str_replace("\r\n", "\n", $body);
	$safeBody = str_replace("\r", "\n", $safeBody);
	$lines = explode("\n", $safeBody);
	$dotStuffed = implode("\r\n", array_map(
		static fn(string $l): string => ($l !== '' && $l[0] === '.') ? '.' . $l : $l,
		$lines
	));

	$headers = 'From: ' . $from . "\r\n"
		. 'To: ' . $to . "\r\n"
		. 'Subject: ' . $subject . "\r\n"
		. "MIME-Version: 1.0\r\n"
		. "Content-Type: text/plain; charset=UTF-8\r\n"
		. "\r\n";

	@fwrite($socket, $headers . $dotStuffed . "\r\n.\r\n");
	$r = $readResponse();
	if (!str_starts_with(trim($r), '250')) {
		fclose($socket);
		return false;
	}

	$send('QUIT');
	fclose($socket);
	return true;
}

function onboarding_required(): bool
{
	if (!empty($_SESSION[OTP_RESET_SESSION_KEY])) {
		return true;
	}
	return !is_file(settings_path()) || !is_file(auth_path());
}

function clear_media_files(): void
{
	$mediaPath = blog_root() . '/media';
	$realMedia = safe_realpath_within($mediaPath, blog_root());
	if ($realMedia === false || !is_dir($realMedia)) {
		return;
	}

	$files = scandir($realMedia);
	if (!is_array($files)) {
		return;
	}

	foreach ($files as $file) {
		if ($file === '.' || $file === '..') {
			continue;
		}
		$target = $realMedia . DIRECTORY_SEPARATOR . $file;
		if (!str_starts_with($target, $realMedia . DIRECTORY_SEPARATOR)) {
			continue;
		}
		if (is_file($target)) {
			@unlink($target);
		}
	}
}

function delete_directory_recursive(string $dir): bool
{
	$realDir = realpath($dir);
	if ($realDir === false || !is_dir($realDir)) {
		return true;
	}

	$items = scandir($realDir);
	if (!is_array($items)) {
		return false;
	}

	foreach ($items as $item) {
		if ($item === '.' || $item === '..') {
			continue;
		}
		$target = $realDir . DIRECTORY_SEPARATOR . $item;
		if (!str_starts_with($target, $realDir . DIRECTORY_SEPARATOR)) {
			continue;
		}

		if (is_dir($target)) {
			if (!delete_directory_recursive($target)) {
				return false;
			}
			continue;
		}
		if (is_file($target) && !@unlink($target)) {
			return false;
		}
	}

	return @rmdir($realDir);
}

function delete_single_tenant_blog_data(): bool
{
	clear_media_files();
	$targets = [posts_path(), settings_path(), auth_path(), otp_path()];
	foreach ($targets as $target) {
		if (is_file($target) && !@unlink($target)) {
			return false;
		}
	}
	return true;
}

function multi_tenant_blog_word_from_request(): string
{
	$raw = trim((string) ($_GET['blog'] ?? ''));
	if ($raw === '') {
		return '';
	}
	$word = preg_replace('/[^A-Za-z0-9_-]/', '', $raw) ?? '';
	return strtolower(mb_substr($word, 0, BLOG_WORD_MAX_LENGTH));
}

function delete_multi_tenant_blog_data(string $blogWord): bool
{
	$blogWord = trim($blogWord);
	if ($blogWord === '' || !defined('BLOG_ROOT')) {
		return false;
	}

	$blogRoot = rtrim((string) BLOG_ROOT, '/');
	if ($blogRoot === '' || !is_dir($blogRoot)) {
		return false;
	}

	$realBlogRoot = realpath($blogRoot);
	if ($realBlogRoot === false) {
		return false;
	}

	if (!str_contains($realBlogRoot, '/multi-tenant/blogs/')) {
		return false;
	}

	$expectedBlogRoot = dirname(__DIR__) . '/multi-tenant/blogs/' . $blogWord;
	$realExpected = realpath($expectedBlogRoot);

	if ($realExpected === false || $realBlogRoot !== $realExpected) {
		return false;
	}

	$dbDeleted = false;
	try {
		$dbFile = dirname(__DIR__) . '/multi-tenant/core/db.php';
		if (is_file($dbFile)) {
			require_once $dbFile;
			if (function_exists('mt_delete_blog_in_db')) {
				$dbDeleted = (bool) mt_delete_blog_in_db($blogWord);
			}
		}
	} catch (Throwable $throwable) {
		return false;
	}

	if (!$dbDeleted) {
		return false;
	}

	$cwd = getcwd();
	if (is_string($cwd) && str_starts_with($cwd, $realBlogRoot)) {
		$parentDir = dirname($realBlogRoot);
		if (is_dir($parentDir)) {
			@chdir($parentDir);
		}
	}

	return delete_directory_recursive($realBlogRoot);
}

function save_password_hash(string $password): void
{
	$hash = password_hash($password, PASSWORD_BCRYPT);
	file_put_contents(auth_path(), json_encode(['password_hash' => $hash], JSON_UNESCAPED_SLASHES));
}

function is_valid_password_hash(string $hash): bool
{
	$info = password_get_info($hash);
	return is_array($info) && ((int) ($info['algo'] ?? 0)) !== 0;
}

function load_password_hash(): string
{
	$path = auth_path();
	if (!is_file($path)) {
		return '';
	}

	$decoded = json_decode((string) file_get_contents($path), true);
	if (!is_array($decoded)) {
		return '';
	}

	$hash = (string) ($decoded['password_hash'] ?? '');
	return is_valid_password_hash($hash) ? $hash : '';
}

function load_otp(): ?array
{
	$path = otp_path();
	if (!is_file($path)) {
		return null;
	}

	$decoded = json_decode((string) file_get_contents($path), true);
	if (!is_array($decoded)) {
		return null;
	}

	if ((int) ($decoded['expires'] ?? 0) < time()) {
		@unlink($path);
		return null;
	}

	return $decoded;
}

function parse_location_coords(string $coords): ?array
{
	$coords = trim($coords);
	if ($coords === '') {
		return null;
	}

	if (!preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $coords, $m)) {
		return null;
	}

	$lat = (float) $m[1];
	$lon = (float) $m[2];
	if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
		return null;
	}

	return ['lat' => $lat, 'lon' => $lon];
}

function normalize_location_payload(string $coords, string $label): ?array
{
	$parsed = parse_location_coords($coords);
	if ($parsed === null) {
		return null;
	}

	$normalizedCoords = number_format($parsed['lat'], 6, '.', '') . ', ' . number_format($parsed['lon'], 6, '.', '');
	$normalizedLabel = trim($label);
	if ($normalizedLabel === '') {
		$normalizedLabel = $normalizedCoords;
	}

	return [
		'coords' => $normalizedCoords,
		'label' => $normalizedLabel,
	];
}

function parse_exif_fraction(string $value): ?float
{
	$value = trim($value);
	if ($value === '') {
		return null;
	}

	if (str_contains($value, '/')) {
		[$num, $den] = array_pad(explode('/', $value, 2), 2, '1');
		$numF = (float) $num;
		$denF = (float) $den;
		if ($denF == 0.0) {
			return null;
		}
		return $numF / $denF;
	}

	return (float) $value;
}

function exif_gps_to_decimal(array $coords, string $hemisphere): ?float
{
	if (count($coords) < 3) {
		return null;
	}

	$deg = parse_exif_fraction((string) ($coords[0] ?? ''));
	$min = parse_exif_fraction((string) ($coords[1] ?? ''));
	$sec = parse_exif_fraction((string) ($coords[2] ?? ''));
	if ($deg === null || $min === null || $sec === null) {
		return null;
	}

	$decimal = $deg + ($min / 60.0) + ($sec / 3600.0);
	$hemisphere = strtoupper(trim($hemisphere));
	if ($hemisphere === 'S' || $hemisphere === 'W') {
		$decimal *= -1;
	}
	return $decimal;
}

function extract_media_location_from_file(string $path, string $type): ?array
{
	if ($type !== 'image' || !function_exists('exif_read_data')) {
		return null;
	}

	$exif = @exif_read_data($path, 'GPS', true);
	if (!is_array($exif)) {
		return null;
	}

	$gps = $exif['GPS'] ?? [];
	if (!is_array($gps)) {
		return null;
	}

	$lat = exif_gps_to_decimal((array) ($gps['GPSLatitude'] ?? []), (string) ($gps['GPSLatitudeRef'] ?? ''));
	$lon = exif_gps_to_decimal((array) ($gps['GPSLongitude'] ?? []), (string) ($gps['GPSLongitudeRef'] ?? ''));
	if (!is_float($lat) || !is_float($lon)) {
		return null;
	}

	$coords = number_format($lat, 6, '.', '') . ', ' . number_format($lon, 6, '.', '');
	return [
		'coords' => $coords,
		'label' => $coords,
	];
}

function normalize_post_entry(array $item): ?array
{
	$id = trim((string) ($item['id'] ?? ''));
	$timestamp = (int) ($item['timestamp'] ?? 0);
	$type = trim((string) ($item['type'] ?? 'text'));
	$pinned = !empty($item['pinned']);

	if ($id === '' || $timestamp <= 0 || !in_array($type, ['text', 'image', 'video', 'audio'], true)) {
		return null;
	}

	if ($type === 'text') {
		$text = trim((string) ($item['text'] ?? ''));
		if ($text === '') {
			return null;
		}

		return [
			'id' => $id,
			'type' => 'text',
			'text' => mb_substr($text, 0, MAX_TEXT_POST_LENGTH),
			'pinned' => $pinned,
			'timestamp' => $timestamp,
		];
	}

	$path = trim((string) ($item['path'] ?? ''));
	if ($path === '') {
		return null;
	}

	$location = normalize_location_payload(
		trim((string) ($item['location_coords'] ?? '')),
		trim((string) ($item['location_label'] ?? ''))
	);

	$result = [
		'id' => $id,
		'type' => $type,
		'path' => $path,
		'pinned' => $pinned,
		'timestamp' => $timestamp,
	];

	if ($location !== null) {
		$result['location_coords'] = $location['coords'];
		$result['location_label'] = $location['label'];
	}

	return $result;
}

function safe_realpath_within(string $path, string $basePath): ?string
{
	$realPath = realpath($path);
	$realBase = realpath($basePath);

	if ($realPath === false || $realBase === false) {
		return null;
	}

	if (!str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR) && $realPath !== $realBase) {
		return null;
	}

	return $realPath;
}

function resolve_local_media_path_for_delete(string $relativePath): ?string
{
	$relativePath = trim($relativePath);
	if ($relativePath === '' || !str_starts_with($relativePath, 'media/')) {
		return null;
	}

	$target = blog_root() . '/' . ltrim($relativePath, '/');
	$mediaRoot = blog_root() . '/media';

	$realTarget = safe_realpath_within($target, $mediaRoot);

	if ($realTarget === null || !is_file($realTarget)) {
		return null;
	}

	return $realTarget;
}

function load_posts(): array
{
	$path = posts_path();
	if (!is_file($path)) {
		return [];
	}

	$decoded = json_decode((string) file_get_contents($path), true);
	if (!is_array($decoded)) {
		return [];
	}

	$items = isset($decoded['items']) && is_array($decoded['items']) ? $decoded['items'] : (array_is_list($decoded) ? $decoded : []);
	$result = [];
	foreach ($items as $item) {
		if (!is_array($item)) {
			continue;
		}
		$normalized = normalize_post_entry($item);
		if ($normalized !== null) {
			$result[] = $normalized;
		}
	}

	usort($result, static function (array $a, array $b): int {
		$pinnedCompare = ((int) !empty($b['pinned'])) <=> ((int) !empty($a['pinned']));
		if ($pinnedCompare !== 0) {
			return $pinnedCompare;
		}
		return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
	});
	return $result;
}

function save_posts(array $posts): void
{
	$payload = ['items' => array_values($posts)];
	file_put_contents(posts_path(), json_encode($payload, JSON_UNESCAPED_SLASHES));
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

function set_flash_message(string $message): void
{
	$_SESSION[FLASH_MESSAGE_SESSION_KEY] = $message;
}

function pop_flash_message(): string
{
	$message = (string) ($_SESSION[FLASH_MESSAGE_SESSION_KEY] ?? '');
	unset($_SESSION[FLASH_MESSAGE_SESSION_KEY]);
	return $message;
}

function pop_otp_display(): string
{
	$otp = (string) ($_SESSION[OTP_DISPLAY_SESSION_KEY] ?? '');
	unset($_SESSION[OTP_DISPLAY_SESSION_KEY]);
	return $otp;
}

function pinned_media_summary(array $posts): string
{
	$pinnedTotal = 0;
	$images = 0;
	$videos = 0;
	$audios = 0;

	foreach ($posts as $post) {
		if (!is_array($post) || empty($post['pinned'])) {
			continue;
		}
		$pinnedTotal++;
		$type = (string) ($post['type'] ?? '');
		if ($type === 'image') {
			$images++;
		} elseif ($type === 'video') {
			$videos++;
		} elseif ($type === 'audio') {
			$audios++;
		}
	}

	return sprintf(
		'Pinned: %d/4 | image: %d, video: %d, audio: %d',
		$pinnedTotal,
		$images,
		$videos,
		$audios
	);
}

$blogQ = '';
$_blogSafe = '';
$_blogRaw = trim((string) ($_GET['blog'] ?? ''));
if ($_blogRaw !== '') {
	$_tmp = preg_replace('/[^A-Za-z0-9_-]/', '', $_blogRaw) ?? '';
	$_tmp = strtolower(mb_substr($_tmp, 0, BLOG_WORD_MAX_LENGTH));
	if ($_tmp !== '') {
		$_blogSafe = $_tmp;
		$blogQ = 'blog=' . rawurlencode($_blogSafe) . '&';
	}
}
$_sk = $_blogSafe !== '' ? '_' . $_blogSafe : '';
define('ADMIN_SESSION_KEY', 'fbo_admin_auth' . $_sk);
define('FLASH_MESSAGE_SESSION_KEY', 'fbo_flash' . $_sk);
define('OTP_DISPLAY_SESSION_KEY', 'fbo_otp_once' . $_sk);
define('OTP_RESET_SESSION_KEY', 'fbo_otp_reset' . $_sk);
define('LAST_BLOG_SESSION_KEY', 'fbo_last_blog_word');
unset($_blogRaw, $_tmp, $_sk);

$isOtpReset = !empty($_SESSION[OTP_RESET_SESSION_KEY]);
$onboardingError = '';
$flashMessage = pop_flash_message();
$otpDisplay = pop_otp_display();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['generate_otp'])) {
	$_genSettings = load_settings();
	$_recoveryEmail = (string) ($_genSettings['recovery_email'] ?? '');
	if ($_recoveryEmail === '') {
		set_flash_message('No recovery email configured yet. Please log in first and add one.');
		header('Location: ?' . $blogQ . 'edit=1');
		exit;
	}
	$otp = strtoupper(bin2hex(random_bytes(4)));
	$hash = password_hash($otp, PASSWORD_BCRYPT);
	$written = file_put_contents(
		otp_path(),
		json_encode(['hash' => $hash, 'expires' => time() + 900], JSON_UNESCAPED_SLASHES)
	);
	if ($written === false) {
		set_flash_message('Could not create one-time password. Please check write permissions.');
		header('Location: ?' . $blogQ . 'edit=1');
		exit;
	}
	$_smtpCfg = load_smtp_config();
	if (empty($_smtpCfg)) {
		@unlink(otp_path());
		set_flash_message('SMTP is not configured. Please update smtp-config.php.');
		header('Location: ?' . $blogQ . 'edit=1');
		exit;
	}
	$_subject = 'Your one-time password';
	$_emailBody = "Your one-time password is:\n\n  " . $otp . "\n\nIt is valid for 15 minutes. Do not share it with anyone.";
	$_sent = smtp_send($_smtpCfg, $_recoveryEmail, $_subject, $_emailBody);
	if (!$_sent) {
		@unlink(otp_path());
		set_flash_message('Email could not be sent. Please check SMTP settings.');
		header('Location: ?' . $blogQ . 'edit=1');
		exit;
	}
	set_flash_message('A one-time password was sent to your recovery email.');
	header('Location: ?' . $blogQ . 'edit=1');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['otp_login'])) {
	$inputOtp = strtoupper(trim((string) ($_POST['otp_password'] ?? '')));
	$otpData = load_otp();
	if ($otpData !== null && $inputOtp !== '' && password_verify($inputOtp, (string) ($otpData['hash'] ?? ''))) {
		@unlink(otp_path());
		unset($_SESSION[ADMIN_SESSION_KEY]);
		$_SESSION[OTP_RESET_SESSION_KEY] = true;
		header('Location: ?' . $blogQ . 'edit=1');
		exit;
	}
	$otpLoginError = 'Invalid or expired one-time password.';
}

if (onboarding_required()) {
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['complete_onboarding'])) {
		$password = (string) ($_POST['admin_password'] ?? '');
		$passwordConfirm = (string) ($_POST['admin_password_confirm'] ?? '');
		if (mb_strlen($password) < 6) {
			$onboardingError = 'Password must be at least 6 characters.';
		} elseif ($password !== $passwordConfirm) {
			$onboardingError = 'Passwords do not match.';
		} else {
			save_password_hash($password);
			unset($_SESSION[OTP_RESET_SESSION_KEY]);
			$_SESSION[ADMIN_SESSION_KEY] = true;
			if ($_blogSafe !== '') {
				$_SESSION[LAST_BLOG_SESSION_KEY] = $_blogSafe;
			}
			set_flash_message('Password updated.');
			header('Location: ?' . $blogQ . 'edit=1');
			exit;
		}
	}

	if (!$isOtpReset || !is_file(auth_path())) {
		header('Location: /');
		exit;
	}
}

$settings = load_settings();
$siteName = (string) ($settings['site_name'] ?? 'fbo');
$siteNameDisplay = strtoupper($siteName);
$heroSubtitle = (string) ($settings['hero_subtitle'] ?? '');
$recoveryEmail = (string) ($settings['recovery_email'] ?? '');
$passwordHash = load_password_hash();

$view = isset($_GET['view']) && in_array($_GET['view'], ['grid', 'single'], true)
	? $_GET['view']
	: (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|Android|iPhone|iPad|iPod|Opera Mini|IEMobile|WPDesktop/i', $_SERVER['HTTP_USER_AGENT']) ? 'single' : 'grid');
$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';
$composeMode = isset($_GET['compose']) && $_GET['compose'] === '1';
$shuffleRequested = isset($_GET['shuffle']) && (string) $_GET['shuffle'] === '1';
$shuffleSeedParam = (int) ($_GET['shuffle_seed'] ?? 0);
if ($composeMode) {
	$view = 'grid';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$fromPage = max(1, (int) ($_GET['from_page'] ?? $page));
$requestedPostId = trim((string) ($_GET['post_id'] ?? ''));
$showIntroAnimation = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') && empty($_GET);
$adminAuthed = !empty($_SESSION[ADMIN_SESSION_KEY]);
$authError = '';

if ($adminAuthed && $_blogSafe !== '') {
	$_SESSION[LAST_BLOG_SESSION_KEY] = $_blogSafe;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['admin_logout'])) {
	unset($_SESSION[ADMIN_SESSION_KEY]);
	header('Location: ' . blog_self_url());
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['admin_login_password'])) {
	$inputPassword = (string) ($_POST['admin_login_password'] ?? '');
	$loginTarget = (string) ($_POST['login_target'] ?? 'edit');
	$redirectAfterLogin = ($loginTarget === 'compose') ? '?' . $blogQ . 'compose=1' : '?' . $blogQ . 'edit=1';
	if ($passwordHash !== '' && password_verify($inputPassword, $passwordHash)) {
		$_SESSION[ADMIN_SESSION_KEY] = true;
		if ($_blogSafe !== '') {
			$_SESSION[LAST_BLOG_SESSION_KEY] = $_blogSafe;
		}
		header('Location: ' . $redirectAfterLogin);
		exit;
	}
	$authError = 'Wrong password.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['delete_blog']) && $adminAuthed) {
	$deletePassword = (string) ($_POST['delete_blog_password'] ?? '');
	$confirmCompose = (string) ($_POST['delete_blog_confirm_compose'] ?? '0') === '1';
	$confirmDanger = (string) ($_POST['delete_blog_confirm_irreversible'] ?? '0') === '1';

	if (!$confirmCompose || !$confirmDanger) {
		set_flash_message('Delete cancelled. Both confirmations are required.');
		header('Location: ?' . $blogQ . 'edit=1');
		exit;
	}

	if ($passwordHash === '' || !password_verify($deletePassword, $passwordHash)) {
		set_flash_message('Delete cancelled. Wrong password.');
		header('Location: ?' . $blogQ . 'edit=1');
		exit;
	}

	$deleted = false;
	$blogWord = multi_tenant_blog_word_from_request();
	if ($blogWord !== '' && defined('BLOG_ROOT')) {
		$deleted = delete_multi_tenant_blog_data($blogWord);
	} else {
		$deleted = delete_single_tenant_blog_data();
	}

	if (!$deleted) {
		set_flash_message('Delete failed. Please try again.');
		header('Location: ?' . $blogQ . 'edit=1');
		exit;
	}

	unset($_SESSION[FLASH_MESSAGE_SESSION_KEY]);
	unset($_SESSION[ADMIN_SESSION_KEY]);
	unset($_SESSION[OTP_RESET_SESSION_KEY]);
	session_regenerate_id(true);
	header('Location: /');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['save_settings']) && $adminAuthed) {
	save_settings([
		'site_name' => $siteName,
		'hero_subtitle' => (string) ($_POST['hero_subtitle'] ?? $heroSubtitle),
		'recovery_email' => $recoveryEmail,
	]);
	set_flash_message('Settings saved.');
	header('Location: ?' . $blogQ . 'edit=1');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['save_recovery_email']) && $adminAuthed) {
	$_newEmail = trim((string) ($_POST['recovery_email'] ?? ''));
	if ($_newEmail !== '' && filter_var($_newEmail, FILTER_VALIDATE_EMAIL) === false) {
		set_flash_message('Invalid email address.');
		header('Location: ?' . $blogQ . 'edit=1');
		exit;
	}
	save_settings([
		'site_name' => $siteName,
		'hero_subtitle' => $heroSubtitle,
		'recovery_email' => $_newEmail,
	]);
	set_flash_message($_newEmail === '' ? 'Recovery email removed.' : 'Recovery email saved.');
	header('Location: ?' . $blogQ . 'edit=1');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['create_text_post']) && $adminAuthed) {
	$text = mb_substr(trim((string) ($_POST['text_post_content'] ?? '')), 0, MAX_TEXT_POST_LENGTH);
	if ($text === '') {
		set_flash_message('Post is empty.');
		header('Location: ?' . $blogQ . 'compose=1');
		exit;
	}

	$posts = load_posts();
	array_unshift($posts, [
		'id' => 'post_' . time() . '_' . bin2hex(random_bytes(3)),
		'type' => 'text',
		'text' => $text,
		'pinned' => false,
		'timestamp' => time(),
	]);
	save_posts($posts);
	set_flash_message('Post created.');
	header('Location: ?' . $blogQ . 'compose=1');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['upload_media']) && $adminAuthed) {
	$files = $_FILES['files'] ?? null;
	$saved = 0;
	$failed = 0;
	$newPosts = [];
	$attachExifLocation = (string) ($_POST['include_exif_location'] ?? '') === '1';
	$instantLocationCoords = trim((string) ($_POST['instant_location_coords'] ?? ''));
	$instantLocationLabel = trim((string) ($_POST['instant_location_label'] ?? ''));
	$instantLocation = normalize_location_payload($instantLocationCoords, $instantLocationLabel);
	$manualLocationApplied = false;
	$uploadDir = media_dir_path();
	$clientUploadTimestamp = (int) ($_POST['upload_client_epoch'] ?? 0);
	if ($clientUploadTimestamp > 1000000000000) {
		$clientUploadTimestamp = (int) floor($clientUploadTimestamp / 1000);
	}
	if ($clientUploadTimestamp <= 0) {
		$clientUploadTimestamp = time();
	}

	if (is_array($files) && isset($files['name'], $files['tmp_name'], $files['error']) && is_array($files['name'])) {
		$count = count($files['name']);
		if ($count > MAX_UPLOAD_FILES_PER_REQUEST) {
			set_flash_message('Upload limit: max ' . MAX_UPLOAD_FILES_PER_REQUEST . ' files per upload.');
			header('Location: ?' . $blogQ . 'compose=1');
			exit;
		}
		for ($index = 0; $index < $count; $index++) {
			$name = (string) ($files['name'][$index] ?? '');
			$tmp = (string) ($files['tmp_name'][$index] ?? '');
			$err = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
			$size = (int) ($files['size'][$index] ?? 0);
			$reportedMime = strtolower(trim((string) ($files['type'][$index] ?? '')));

			if ($err !== UPLOAD_ERR_OK || $name === '' || $tmp === '') {
				$failed++;
				continue;
			}

			if ($size <= 0 || $size > MAX_UPLOAD_FILE_SIZE_BYTES) {
				$failed++;
				continue;
			}

			$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			if (!in_array($extension, MEDIA_EXTENSIONS, true)) {
				$failed++;
				continue;
			}

			$targetName = sprintf('%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(4)), $extension);
			$targetPath = $uploadDir . '/' . $targetName;

			$validateTarget = safe_realpath_within($targetPath, $uploadDir);
			if ($validateTarget === null) {
				$realUploadDir = realpath($uploadDir);
				if ($realUploadDir === false || !is_dir($realUploadDir)) {
					$failed++;
					continue;
				}
				if (!str_starts_with($realUploadDir . '/' . $targetName, $realUploadDir . '/')) {
					$failed++;
					continue;
				}
			}

			if (!move_uploaded_file($tmp, $targetPath)) {
				$failed++;
				continue;
			}

			@touch($targetPath, $clientUploadTimestamp, $clientUploadTimestamp);

			$detectedMime = '';
			if (function_exists('finfo_open')) {
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				if ($finfo !== false) {
					$mime = finfo_file($finfo, $targetPath);
					if (is_string($mime)) {
						$detectedMime = strtolower(trim($mime));
					}
					finfo_close($finfo);
				}
			}
			$effectiveMime = $detectedMime !== '' ? $detectedMime : $reportedMime;

			$type = 'image';
			if (in_array($extension, ['mp4', 'mov', 'm4v'], true)) {
				$type = 'video';
			} elseif ($extension === 'webm') {
				if (str_starts_with($reportedMime, 'video/')) {
					$type = 'video';
				} elseif (str_starts_with($reportedMime, 'audio/')) {
					$type = 'audio';
				} else {
					$type = str_starts_with($effectiveMime, 'audio/') ? 'audio' : 'video';
				}
			} elseif (in_array($extension, ['mp3', 'wav', 'flac', 'ogg', 'm4a'], true)) {
				$type = 'audio';
			}

			$postEntry = [
				'id' => 'media_' . $clientUploadTimestamp . '_' . bin2hex(random_bytes(3)),
				'type' => $type,
				'path' => 'media/' . $targetName,
				'pinned' => false,
				'timestamp' => $clientUploadTimestamp,
			];

			if ($instantLocation !== null && !$manualLocationApplied) {
				$postEntry['location_coords'] = $instantLocation['coords'];
				$postEntry['location_label'] = $instantLocation['label'];
				$manualLocationApplied = true;
			} elseif ($attachExifLocation) {
				$metaLocation = extract_media_location_from_file($targetPath, $type);
				if ($metaLocation !== null) {
					$postEntry['location_coords'] = $metaLocation['coords'];
					$postEntry['location_label'] = $metaLocation['label'];
				}
			}

			$newPosts[] = $postEntry;
			$saved++;
		}
	}

	if ($saved > 0) {
		$posts = load_posts();
		save_posts(array_merge($newPosts, $posts));
	}

	$message = 'Uploaded: ' . $saved;
	if ($failed > 0) {
		$message .= ' | Failed: ' . $failed . ' (limit: ' . ((int) (MAX_UPLOAD_FILE_SIZE_BYTES / 1048576)) . 'MB per file)';
	}
	set_flash_message($message);
	header('Location: ?' . $blogQ . 'compose=1');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (isset($_POST['delete_page_posts']) || isset($_POST['delete_page_media'])) && $adminAuthed) {
	$ids = $_POST['page_post_ids'] ?? [];
	$closeAfterSave = isset($_POST['close_after_save']) && (string) $_POST['close_after_save'] === '1';
	$selectedIds = [];
	if (is_array($ids)) {
		foreach ($ids as $id) {
			$id = trim((string) $id);
			if ($id !== '') {
				$selectedIds[$id] = true;
			}
		}
	}

	$deleted = 0;
	$failed = 0;
	$posts = load_posts();
	$nextPosts = [];

	foreach ($posts as $post) {
		$postId = (string) ($post['id'] ?? '');
		if ($postId === '' || !isset($selectedIds[$postId])) {
			$nextPosts[] = $post;
			continue;
		}

		$postType = (string) ($post['type'] ?? 'text');
		if (in_array($postType, ['image', 'video', 'audio'], true)) {
			$target = resolve_local_media_path_for_delete((string) ($post['path'] ?? ''));
			if ($target === null || !@unlink($target)) {
				$failed++;
				$nextPosts[] = $post;
				continue;
			}
		}

		$deleted++;
	}

	save_posts($nextPosts);

	$message = 'Deleted: ' . $deleted;
	if ($failed > 0) {
		$message .= ' | Failed: ' . $failed;
	}
	set_flash_message($message);

	if ($closeAfterSave) {
		header('Location: ?' . $blogQ . 'view=' . rawurlencode($view) . '&page=' . rawurlencode((string) $page));
		exit;
	}

	header('Location: ?' . $blogQ . 'compose=1&view=' . rawurlencode($view) . '&page=' . rawurlencode((string) $page));
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['delete_post']) && $adminAuthed) {
	$postId = trim((string) ($_POST['post_id'] ?? ''));
	$posts = load_posts();
	$deleteMediaPath = '';
	foreach ($posts as $post) {
		if ((string) ($post['id'] ?? '') !== $postId) {
			continue;
		}
		if (in_array((string) ($post['type'] ?? ''), ['image', 'video', 'audio'], true)) {
			$deleteMediaPath = (string) ($post['path'] ?? '');
		}
		break;
	}
	$posts = array_values(array_filter($posts, static fn(array $p): bool => (string) ($p['id'] ?? '') !== $postId));
	save_posts($posts);
	if ($deleteMediaPath !== '') {
		$target = resolve_local_media_path_for_delete($deleteMediaPath);
		if ($target !== null) {
			@unlink($target);
		}
	}
	set_flash_message('Post deleted.');
	header('Location: ?' . $blogQ . 'compose=1');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['pin_post']) && $adminAuthed) {
	$postId = trim((string) ($_POST['post_id'] ?? ''));
	$posts = load_posts();
	$postExists = false;
	$alreadyPinned = false;
	$pinnedCount = 0;
	foreach ($posts as $entry) {
		if ((string) ($entry['id'] ?? '') === $postId) {
			$postExists = true;
			$alreadyPinned = !empty($entry['pinned']);
		}
		if (!empty($entry['pinned'])) {
			$pinnedCount++;
		}
	}

	if (!$postExists) {
		set_flash_message('Post not found.');
		header('Location: ?' . $blogQ . 'compose=1');
		exit;
	}

	if ($alreadyPinned) {
		set_flash_message('Post is already pinned. ' . pinned_media_summary($posts));
		header('Location: ?' . $blogQ . 'compose=1');
		exit;
	}

	if ($pinnedCount >= 4) {
		set_flash_message('You can pin up to 4 posts only. Unpin one first. ' . pinned_media_summary($posts));
		header('Location: ?' . $blogQ . 'compose=1');
		exit;
	}

	$updated = false;
	foreach ($posts as &$post) {
		if ((string) ($post['id'] ?? '') !== $postId) {
			continue;
		}
		$post['pinned'] = true;
		$updated = true;
		break;
	}
	unset($post);
	if ($updated) {
		save_posts($posts);
		set_flash_message('Post pinned. ' . pinned_media_summary($posts));
	}
	header('Location: ?' . $blogQ . 'compose=1');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['unpin_post']) && $adminAuthed) {
	$postId = trim((string) ($_POST['post_id'] ?? ''));
	$posts = load_posts();
	$updated = false;
	foreach ($posts as &$post) {
		if ((string) ($post['id'] ?? '') !== $postId) {
			continue;
		}
		$post['pinned'] = false;
		$updated = true;
		break;
	}
	unset($post);
	if ($updated) {
		save_posts($posts);
		set_flash_message('Post unpinned. ' . pinned_media_summary($posts));
	}
	header('Location: ?' . $blogQ . 'compose=1');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['download_backup']) && $adminAuthed) {
	if (!class_exists('ZipArchive')) {
		http_response_code(500);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'ZipArchive is not available on this server.';
		exit;
	}

	$_backupDir = backend_dir_path();
	$_mediaDir = media_dir_path();
	$_blogLabel = preg_replace('/[^A-Za-z0-9_-]/', '', $siteName);
	$_tmpFile = tempnam(sys_get_temp_dir(), 'fbo_backup_');
	if ($_tmpFile === false) {
		http_response_code(500);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'Could not create temporary file.';
		exit;
	}

	$_zip = new ZipArchive();
	if ($_zip->open($_tmpFile, ZipArchive::OVERWRITE) !== true) {
		@unlink($_tmpFile);
		http_response_code(500);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'Could not create ZIP archive.';
		exit;
	}

	foreach ([
		'posts.json' => $_backupDir . '/posts.json',
		'settings.json' => $_backupDir . '/settings.json',
		'.auth.json' => $_backupDir . '/.auth.json',
	] as $_zipName => $_filePath) {
		if (is_file($_filePath)) {
			$_zip->addFile($_filePath, $_zipName);
		}
	}

	if (is_dir($_mediaDir)) {
		$_realMedia = safe_realpath_within($_mediaDir, blog_root());
		if ($_realMedia !== null && is_dir($_realMedia)) {
			$_iter = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($_realMedia, FilesystemIterator::SKIP_DOTS)
			);
			foreach ($_iter as $_fileInfo) {
				if (!$_fileInfo->isFile()) {
					continue;
				}
				$_realFile = $_fileInfo->getRealPath();
				if ($_realFile === false) {
					continue;
				}
				if (strncmp($_realFile, $_realMedia . DIRECTORY_SEPARATOR, strlen($_realMedia) + 1) !== 0) {
					continue;
				}
				$_relPath = 'media/' . substr($_realFile, strlen($_realMedia) + 1);
				$_zip->addFile($_realFile, $_relPath);
			}
		}
	}

	$_zip->close();

	$_filename = 'fbo-blog-' . ($_blogLabel !== '' ? $_blogLabel . '-' : '') . date('Y-m-d') . '.zip';
	header('Content-Type: application/zip');
	header('Content-Disposition: attachment; filename="' . $_filename . '"');
	header('Content-Length: ' . (string) filesize($_tmpFile));
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Pragma: no-cache');
	readfile($_tmpFile);
	@unlink($_tmpFile);
	exit;
}

$posts = load_posts();
$allPostsCount = count($posts);
$singlePostMode = false;
if ($requestedPostId !== '' && !$composeMode) {
	foreach ($posts as $post) {
		if ((string) ($post['id'] ?? '') !== $requestedPostId) {
			continue;
		}

		$singlePostMode = true;
		$view = 'single';
		$posts = [$post];
		$page = 1;
		break;
	}
}

$shuffleThreshold = 100;
$shuffleRemaining = max(0, $shuffleThreshold - $allPostsCount);
$shuffleEligible = !$composeMode && !$singlePostMode && $allPostsCount >= $shuffleThreshold;
$shuffleSeed = $shuffleSeedParam > 0 ? $shuffleSeedParam : random_int(100000, 999999999);
$shuffleActive = $shuffleEligible && $shuffleRequested;

if ($shuffleActive) {
	$pinnedPosts = [];
	$unpinnedPosts = [];
	foreach ($posts as $post) {
		if (!empty($post['pinned'])) {
			$pinnedPosts[] = $post;
			continue;
		}
		$unpinnedPosts[] = $post;
	}

	usort($unpinnedPosts, static function (array $a, array $b) use ($shuffleSeed): int {
		$idA = (string) ($a['id'] ?? '');
		$idB = (string) ($b['id'] ?? '');
		$hashA = hash('sha256', $shuffleSeed . '|' . $idA);
		$hashB = hash('sha256', $shuffleSeed . '|' . $idB);
		$cmp = $hashA <=> $hashB;
		if ($cmp !== 0) {
			return $cmp;
		}
		return $idA <=> $idB;
	});

	$posts = array_merge($pinnedPosts, $unpinnedPosts);
}

$stateQuery = '';
if ($editMode) {
	$stateQuery .= '&edit=1';
}
if ($composeMode) {
	$stateQuery .= '&compose=1';
}

$shuffleQuery = '';
if ($shuffleActive) {
	$shuffleQuery = '&shuffle=1&shuffle_seed=' . rawurlencode((string) $shuffleSeed);
}

$shuffleToggleQuery = $stateQuery;
if (!$shuffleActive && $shuffleEligible) {
	$shuffleToggleQuery .= '&shuffle=1&shuffle_seed=' . rawurlencode((string) $shuffleSeed);
}

$perPage = $view === 'grid' ? 180 : 60;
$totalItems = count($posts);
$totalPages = max(1, (int) ceil($totalItems / max(1, $perPage)));
$page = min($page, $totalPages);
$postsOnPage = array_slice($posts, ($page - 1) * $perPage, $perPage);
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
	<meta name="description" content="A blogging tool for people tired of platforms. Open source. No ads. No app. No ai.">
	<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
	<link rel="icon" type="image/png" href="<?= local_asset_url('assets/icon/icon.png') ?>">
	<link rel="apple-touch-icon" href="<?= local_asset_url('assets/icon/icon.png') ?>">
	<title><?= htmlspecialchars($siteNameDisplay, ENT_QUOTES, 'UTF-8') ?></title>
	<link rel="stylesheet" href="<?= local_asset_url('assets/css/styles.css') ?>">
	<link rel="stylesheet" href="<?= local_asset_url('assets/css/upload.css') ?>">
	<link rel="stylesheet" href="<?= local_asset_url('assets/css/audio-player.css') ?>">
	<?php if ($editMode || $composeMode): ?>
		<link rel="stylesheet" href="<?= local_asset_url('assets/css/admin.css') ?>">
	<?php endif; ?>
</head>

<body class="<?= $showIntroAnimation ? 'intro-loading' : '' ?>" data-max-text-post-length="<?= MAX_TEXT_POST_LENGTH ?>"
	data-compose-mode="<?= $composeMode ? '1' : '0' ?>">
	<div class="intro-overlay" id="introOverlay" aria-hidden="true">
		<div class="intro-fbo" id="introFboText">F</div>
	</div>
	<?php include __DIR__ . '/snippets/header.php'; ?>

	<nav class="topbar<?= $composeMode ? ' topbar-compose' : '' ?>">
		<div class="topbar-left">
			<?php if (!$composeMode): ?>
				<?php if ($singlePostMode): ?>
					<a class="ui-btn"
						href="?<?= $blogQ ?>view=grid&page=<?= $fromPage ?><?= $stateQuery ?><?= $shuffleQuery ?>">back to
						grid</a>
				<?php else: ?>
					<a href="?<?= $blogQ ?>view=grid<?= $stateQuery ?><?= $shuffleQuery ?>"
						class="ui-btn <?= $view === 'grid' ? 'active' : '' ?>" id="gridViewBtn">grid</a>
					<a href="?<?= $blogQ ?>view=single<?= $stateQuery ?><?= $shuffleQuery ?>"
						class="ui-btn <?= $view === 'single' ? 'active' : '' ?>" id="listViewBtn">list</a>
					<?php if ($shuffleEligible): ?>
						<a href="?<?= $blogQ ?>view=<?= rawurlencode($view) ?>&page=1<?= $shuffleToggleQuery ?>"
							class="ui-btn <?= $shuffleActive ? 'active' : '' ?>" id="shuffleModeBtn">shuffle</a>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
			<button type="button" class="ui-btn" id="themeToggle">dark mode</button>
		</div>
		<?php if (!$composeMode && !$singlePostMode): ?>
			<div class="topbar-right">
				<span class="meta" id="pageInfoLabel" aria-haspopup="listbox"
					aria-expanded="false"><?= count($postsOnPage) ?> / <?= $totalItems ?> posts (page
					<?= $page ?>/<?= $totalPages ?>)</span>
				<select id="pageJumpSelect" class="page-jump" aria-label="Jump to page">
					<?php for ($p = 1; $p <= $totalPages; $p++): ?>
						<option value="<?= $p ?>" <?= $p === $page ? 'selected' : '' ?>>page <?= $p ?></option>
					<?php endfor; ?>
				</select>
			</div>
		<?php endif; ?>
	</nav>

	<?php if (!$postsOnPage): ?>
		<main class="archive <?= $view ?>">
			<article class="item">
				<div class="text-post-body">No posts yet. Use compose mode to create your first post.</div>
				<div class="stamp">Placeholder content</div>
			</article>
		</main>
	<?php else: ?>
		<main class="archive <?= $view ?><?= $singlePostMode ? ' single-post-mode' : '' ?>">
			<?php foreach ($postsOnPage as $post): ?>
				<?php $isPinned = !empty($post['pinned']); ?>
				<?php $postType = (string) ($post['type'] ?? 'text'); ?>
				<?php $postMediaPath = in_array($postType, ['image', 'video', 'audio'], true) ? asset_url((string) ($post['path'] ?? '')) : ''; ?>
				<?php $postLocationCoords = trim((string) ($post['location_coords'] ?? '')); ?>
				<?php $postLocationLabel = trim((string) ($post['location_label'] ?? '')); ?>
				<?php if ($postLocationCoords !== '' && $postLocationLabel === '') {
					$postLocationLabel = $postLocationCoords;
				} ?>
				<?php $postLocationDisplay = ($view === 'single' && $postLocationCoords !== '') ? $postLocationCoords : $postLocationLabel; ?>
				<?php $postLocationIsCoordsOnly = ($view === 'grid' && $postLocationCoords !== '' && $postLocationDisplay === $postLocationCoords); ?>
				<?php $postLocationShort = $postLocationDisplay !== '' ? (preg_split('/\s+/', $postLocationDisplay, 2)[0] ?? $postLocationDisplay) : ''; ?>
				<article class="item<?= $isPinned ? ' is-pinned' : '' ?>"
					data-post-id="<?= htmlspecialchars((string) $post['id'], ENT_QUOTES, 'UTF-8') ?>"
					data-post-type="<?= htmlspecialchars($postType, ENT_QUOTES, 'UTF-8') ?>"
					data-media-path="<?= htmlspecialchars($postMediaPath, ENT_QUOTES, 'UTF-8') ?>">
					<?php if ($adminAuthed && $composeMode): ?>
						<form method="post" class="pin-form">
							<input type="hidden" name="post_id"
								value="<?= htmlspecialchars((string) $post['id'], ENT_QUOTES, 'UTF-8') ?>">
							<button type="submit" name="<?= $isPinned ? 'unpin_post' : 'pin_post' ?>" value="1"
								class="ui-btn delete-btn pin-btn"><?= $isPinned ? 'Unpin' : 'Pin' ?></button>
						</form>
						<button type="button" class="ui-btn delete-btn mark-delete-btn"
							data-post-id="<?= htmlspecialchars((string) $post['id'], ENT_QUOTES, 'UTF-8') ?>">Delete</button>
					<?php endif; ?>
					<?php if ((string) ($post['type'] ?? 'text') === 'text'): ?>
						<div class="text-post-body"><?= linkify_text_post_content((string) ($post['text'] ?? '')) ?></div>
					<?php else: ?>
						<?php $mediaUrl = htmlspecialchars(asset_url((string) ($post['path'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
						<div class="media-wrap">
							<?php if ((string) ($post['type'] ?? '') === 'video'): ?>
								<?php if ($view === 'grid'): ?>
									<video class="grid-video-thumb" src="<?= $mediaUrl ?>#t=0.1" preload="metadata" playsinline muted aria-hidden="true"></video>
									<div class="grid-video-overlay" aria-hidden="true">
										<svg class="grid-play-icon" width="36px" height="36px" viewBox="0 0 24 24" fill="none"
											xmlns="http://www.w3.org/2000/svg">
											<path
												d="M6.90588 4.53682C6.50592 4.2998 6 4.58808 6 5.05299V18.947C6 19.4119 6.50592 19.7002 6.90588 19.4632L18.629 12.5162C19.0211 12.2838 19.0211 11.7162 18.629 11.4838L6.90588 4.53682Z"
												stroke="#ffffff" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path>
										</svg>
									</div>
								<?php else: ?>
										<video src="<?= $mediaUrl ?>#t=0.1" preload="metadata" playsinline></video>
									<div class="list-video-overlay" aria-label="Play video">
										<svg class="list-play-icon" width="36px" height="36px" viewBox="0 0 24 24" fill="none"
											xmlns="http://www.w3.org/2000/svg">
											<path
												d="M6.90588 4.53682C6.50592 4.2998 6 4.58808 6 5.05299V18.947C6 19.4119 6.50592 19.7002 6.90588 19.4632L18.629 12.5162C19.0211 12.2838 19.0211 11.7162 18.629 11.4838L6.90588 4.53682Z"
												stroke="#ffffff" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path>
										</svg>
									</div>
								<?php endif; ?>
							<?php elseif ((string) ($post['type'] ?? '') === 'audio'): ?>
								<div class="grid-audio-placeholder" aria-hidden="true"></div>
							<?php else: ?>
								<img src="<?= $mediaUrl ?>" alt="Uploaded media" loading="lazy">
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if ($isPinned): ?>
						<div class="pinned-badge<?= ($composeMode && $adminAuthed) ? ' with-delete' : '' ?>">Pinned</div>
					<?php endif; ?>
					<div class="post-meta-row">
						<div class="stamp" data-ts="<?= (int) ($post['timestamp'] ?? 0) ?>">
							<?= date('d.m.Y H:i', (int) ($post['timestamp'] ?? 0)) ?>
						</div>
						<?php if ($postLocationCoords !== '' && $postLocationDisplay !== ''): ?>
							<button type="button" class="location-stamp<?= $postLocationIsCoordsOnly ? ' location-stamp-icon-only' : '' ?>" data-coords="<?= htmlspecialchars($postLocationCoords, ENT_QUOTES, 'UTF-8') ?>"
								title="Click to copy coordinates">
								<span class="location-stamp-icon" aria-hidden="true">
									<svg width="24px" height="24px" stroke-width="1" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M20 10C20 14.4183 12 22 12 22C12 22 4 14.4183 4 10C4 5.58172 7.58172 2 12 2C16.4183 2 20 5.58172 20 10Z" stroke="currentColor" stroke-width="1"></path>
										<path d="M12 11C12.5523 11 13 10.5523 13 10C13 9.44772 12.5523 9 12 9C11.4477 9 11 9.44772 11 10C11 10.5523 11.4477 11 12 11Z" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path>
									</svg>
								</span>
								<span class="location-stamp-full"><?= htmlspecialchars($postLocationDisplay, ENT_QUOTES, 'UTF-8') ?></span>
								<span class="location-stamp-short"><?= htmlspecialchars($postLocationShort, ENT_QUOTES, 'UTF-8') ?></span>
							</button>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</main>
	<?php endif; ?>

	<?php if (!$composeMode && !$singlePostMode): ?>
		<div class="shuffle-hint-row">
			<?php if ($shuffleEligible): ?>
				<span class="meta">Shuffle mode is available<?= $shuffleActive ? ' (active).' : '.' ?></span>
			<?php else: ?>
				<span class="meta">Shuffle mode unlocks in <?= $shuffleRemaining ?> more
					post<?= $shuffleRemaining === 1 ? '' : 's' ?>.</span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<nav class="topbar topbar-pagination">
		<?php if ($page > 1): ?>
			<a class="ui-btn"
				href="?<?= $blogQ ?>view=<?= $view ?>&page=<?= $page - 1 ?><?= $stateQuery ?><?= $shuffleQuery ?>">newer</a>
		<?php endif; ?>
		<?php if ($page < $totalPages): ?>
			<a class="ui-btn"
				href="?<?= $blogQ ?>view=<?= $view ?>&page=<?= $page + 1 ?><?= $stateQuery ?><?= $shuffleQuery ?>">older</a>
		<?php endif; ?>
	</nav>

	<script src="<?= local_asset_url('assets/js/script.js') ?>" defer></script>
	<script src="<?= local_asset_url('assets/js/blog.js') ?>" defer></script>
	<script src="<?= local_asset_url('assets/js/instant-capture.js') ?>" defer></script>
	<script src="<?= local_asset_url('assets/js/audio-player.js') ?>" defer></script>
</body>

</html>