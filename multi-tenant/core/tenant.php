<?php
declare(strict_types=1);

function mt_root_dir(): string
{
    return dirname(__DIR__);
}

function mt_blogs_dir(): string
{
    return mt_root_dir() . '/blogs';
}

function mt_normalize_blog_word(string $value): string
{
    $blog = trim($value);
    $blog = preg_replace('/[^A-Za-z0-9_-]/', '', $blog) ?? '';
    return strtolower(mb_substr($blog, 0, 24));
}

/**
 * Creates the blog data directory (backend, media, uploads) and registers in DB.
 * The central fbo/ code is NOT copied — loaded directly by bootstrap.php.
 *
 * @return array{ok: bool, message: string, blog?: string, path?: string, already_exists?: bool}
 */
function mt_provision_blog(string $blogWord, string $password = ''): array
{
    $blog = mt_normalize_blog_word($blogWord);
    if ($blog === '') {
        return ['ok' => false, 'message' => 'Invalid blog name.'];
    }

    require_once __DIR__ . '/db.php';

    if (mt_blog_exists_in_db($blog)) {
        return ['ok' => false, 'message' => 'Blog name already exists. Choose a different one.', 'blog' => $blog, 'already_exists' => true];
    }

    $blogsDir = mt_blogs_dir();
    $blogDir  = $blogsDir . '/' . $blog;

    if (!is_dir($blogsDir) && !mkdir($blogsDir, 0775, true) && !is_dir($blogsDir)) {
        return ['ok' => false, 'message' => 'Failed to create blogs directory.'];
    }

    if (is_dir($blogDir)) {
        return ['ok' => false, 'message' => 'Blog name already exists. Choose a different one.', 'blog' => $blog, 'already_exists' => true];
    }

    if (!mkdir($blogDir, 0775, true)) {
        if (is_dir($blogDir)) {
            return ['ok' => false, 'message' => 'Blog name already exists. Choose a different one.', 'blog' => $blog, 'already_exists' => true];
        }
        return ['ok' => false, 'message' => 'Failed to create blog directory.'];
    }

    $backendDir = $blogDir . '/backend';
    @mkdir($backendDir,            0775, true);
    @mkdir($blogDir . '/media',    0775, true);
    @mkdir($blogDir . '/uploads',  0775, true);

    file_put_contents($backendDir . '/posts.json',
        json_encode(['items' => []], JSON_UNESCAPED_SLASHES));
    file_put_contents($backendDir . '/settings.json',
        json_encode(['site_name' => $blog, 'hero_subtitle' => ''], JSON_UNESCAPED_SLASHES));

    $hash = ($password !== '') ? (string) password_hash($password, PASSWORD_BCRYPT) : '';
    file_put_contents($backendDir . '/.auth.json',
        json_encode(['password_hash' => $hash], JSON_UNESCAPED_SLASHES));

    if (!mt_register_blog_in_db($blog)) {
        if (mt_blog_exists_in_db($blog)) {
            return ['ok' => false, 'message' => 'Blog name already exists. Choose a different one.', 'blog' => $blog, 'already_exists' => true];
        }
        return ['ok' => false, 'message' => 'DB registration failed. Check config.php and run schema.sql.'];
    }

    return [
        'ok'             => true,
        'message'        => 'Blog provisioned.',
        'blog'           => $blog,
        'path'           => $blogDir,
        'already_exists' => false,
    ];
}

/**
 * Returns the public URL for a blog — path-based, no subdomains.
 */
function mt_blog_url(string $blogWord): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        ? 'https' : 'http';
    $host = trim((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $blog = mt_normalize_blog_word($blogWord);
    return $scheme . '://' . $host . '/blog/' . rawurlencode($blog);
}
