<?php
declare(strict_types=1);

// A navigation preference only. Authentication still uses the per-blog session.
const MT_REMEMBERED_BLOG_COOKIE = 'fbo_last_blog_word';

function mt_set_remembered_blog_cookie(string $word, int $expires): void
{
    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    setcookie(MT_REMEMBERED_BLOG_COOKIE, $word, [
        'expires' => $expires,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function mt_remember_blog(string $word): void
{
    if (!preg_match('/\A[a-z0-9_-]{1,24}\z/', $word)) {
        return;
    }
    mt_set_remembered_blog_cookie($word, time() + 365 * 24 * 60 * 60);
    $_COOKIE[MT_REMEMBERED_BLOG_COOKIE] = $word;
    $_SESSION['fbo_last_blog_word'] = $word;
}

function mt_forget_blog(string $word): void
{
    if (($_COOKIE[MT_REMEMBERED_BLOG_COOKIE] ?? null) === $word) {
        mt_set_remembered_blog_cookie('', time() - 3600);
        unset($_COOKIE[MT_REMEMBERED_BLOG_COOKIE]);
    }
    if (($_SESSION['fbo_last_blog_word'] ?? null) === $word) {
        unset($_SESSION['fbo_last_blog_word']);
    }
}

function mt_remembered_blog(array $blogs): string
{
    // Existing sessions migrate to the persistent cookie on their next visit.
    $candidates = [$_COOKIE[MT_REMEMBERED_BLOG_COOKIE] ?? '', $_SESSION['fbo_last_blog_word'] ?? ''];
    foreach ($candidates as $word) {
        if (!is_string($word) || !preg_match('/\A[a-z0-9_-]{1,24}\z/', $word)) {
            continue;
        }
        foreach ($blogs as $blog) {
            if (($blog['blog_word'] ?? '') === $word && is_dir(dirname(__DIR__) . '/blogs/' . $word)) {
                if (($_COOKIE[MT_REMEMBERED_BLOG_COOKIE] ?? '') !== $word) {
                    mt_remember_blog($word);
                }
                return $word;
            }
        }
    }
    return '';
}
