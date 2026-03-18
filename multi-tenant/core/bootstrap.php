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

require __DIR__ . '/tenant.php';
require __DIR__ . '/db.php';

function mt_is_debug_mode(): bool
{
    $fromQuery = isset($_GET['debug']) && (string) $_GET['debug'] === '1';
    if ($fromQuery) {
        return true;
    }

    $cfgFile = dirname(__DIR__) . '/config.php';
    if (!is_file($cfgFile)) {
        return false;
    }

    $cfg = require $cfgFile;
    return is_array($cfg) && !empty($cfg['app_debug']);
}

function mt_render_error_page(int $statusCode, string $message, array $details = []): void
{
    http_response_code($statusCode);

    if (!mt_is_debug_mode()) {
        require dirname(__DIR__) . '/templates/index.php';
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>FBO Debug</title>';
    echo '<style>body{font-family:system-ui,Arial;padding:20px;max-width:920px;margin:auto}pre{background:#f5f5f5;padding:12px;border-radius:8px;overflow:auto}</style>';
    echo '</head><body>';
    echo '<h1>Bootstrap error (' . $statusCode . ')</h1>';
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($details !== []) {
        echo '<pre>' . htmlspecialchars(print_r($details, true), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo '<p>Disable debug by removing <code>?debug=1</code> or setting <code>app_debug</code> to false.</p>';
    echo '</body></html>';
    exit;
}

$mtRoot = dirname(__DIR__);             // multi-tenant/
$webRoot = dirname($mtRoot);             // htdocs/
$blogsDir = $mtRoot . '/blogs';
$fboCandidates = [
    $webRoot . '/fbo/index.php',
    $mtRoot . '/fbo/index.php',
];

$fboIndex = '';
foreach ($fboCandidates as $candidate) {
    if (is_file($candidate)) {
        $fboIndex = $candidate;
        break;
    }
}

// ── Routing ────────────────────────────────────────────────────────────────
// URL: /blog/<blogword>  →  rewritten by .htaccess to index.php?blog=<blogword>
$blogWord = mt_normalize_blog_word((string) ($_GET['blog'] ?? ''));

// fallthrough: show landing/onboarding page
if ($blogWord === '') {
    require $mtRoot . '/templates/index.php';
    exit;
}

// security: validate against DB so no arbitrary filesystem paths are possible
if (!mt_blog_exists_in_db($blogWord)) {
    mt_render_error_page(404, 'Blog not found in database.', [
        'blog' => $blogWord,
    ]);
}

$targetBlogDir = $blogsDir . '/' . $blogWord;

// double-check filesystem traversal
$realBlogsRoot = realpath($blogsDir);
$realTarget = realpath($targetBlogDir);

if (
    $realBlogsRoot === false
    || $realTarget === false
    || !str_starts_with($realTarget, $realBlogsRoot . DIRECTORY_SEPARATOR)
    || $fboIndex === ''
) {
    mt_render_error_page(500, 'Filesystem bootstrap check failed.', [
        'blogs_dir' => $blogsDir,
        'real_blogs_root' => $realBlogsRoot,
        'target_blog_dir' => $targetBlogDir,
        'real_target' => $realTarget,
        'fbo_candidates' => $fboCandidates,
        'fbo_index' => $fboIndex,
    ]);
}

// ── Define context constants for fbo/index.php ────────────────────────────
if (!defined('BLOG_ROOT')) {
    define('BLOG_ROOT', $realTarget);
}

// Absolute URL prefix for shared CSS/JS (fbo/assets/ lives at webroot)
if (!defined('ASSET_BASE_URL')) {
    define('ASSET_BASE_URL', '/fbo');
}

// Absolute URL prefix for this blog's media files
if (!defined('MEDIA_WEB_ROOT')) {
    define('MEDIA_WEB_ROOT', '/multi-tenant/blogs/' . $blogWord . '/media');
}

// ── SMTP constants (loaded from config.php, never per-blog settings) ───────
$_mtCfgPath = dirname(__DIR__) . '/config.php';
if (is_file($_mtCfgPath)) {
    $_mtCfg = require $_mtCfgPath;
    if (is_array($_mtCfg)) {
        if (!defined('FBO_SMTP_HOST'))
            define('FBO_SMTP_HOST', (string) ($_mtCfg['smtp_host'] ?? ''));
        if (!defined('FBO_SMTP_PORT'))
            define('FBO_SMTP_PORT', (int) ($_mtCfg['smtp_port'] ?? 587));
        if (!defined('FBO_SMTP_USER'))
            define('FBO_SMTP_USER', (string) ($_mtCfg['smtp_user'] ?? ''));
        if (!defined('FBO_SMTP_PASS'))
            define('FBO_SMTP_PASS', (string) ($_mtCfg['smtp_pass'] ?? ''));
        if (!defined('FBO_SMTP_FROM'))
            define('FBO_SMTP_FROM', (string) ($_mtCfg['smtp_from'] ?? ''));
    }
    unset($_mtCfg);
}
unset($_mtCfgPath);

chdir($realTarget);
try {
    require $fboIndex;
} catch (Throwable $throwable) {
    error_log('FBO bootstrap fatal: ' . $throwable->getMessage());
    mt_render_error_page(500, 'Fatal while loading fbo/index.php', [
        'file' => $throwable->getFile(),
        'line' => $throwable->getLine(),
        'message' => $throwable->getMessage(),
    ]);
}
exit;
