<?php
declare(strict_types=1);

function mt_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $cfgFile = dirname(__DIR__) . '/config.php';
    if (!is_file($cfgFile)) {
        throw new RuntimeException('Missing config.php — copy config.example.php and fill in DB credentials.');
    }

    $cfg = require $cfgFile;
    if (!is_array($cfg)) {
        throw new RuntimeException('config.php must return an array.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string) ($cfg['db_host']    ?? 'localhost'),
        (int)    ($cfg['db_port']    ?? 3306),
        (string) ($cfg['db_name']    ?? ''),
        (string) ($cfg['db_charset'] ?? 'utf8mb4')
    );

    $pdo = new PDO($dsn, (string) ($cfg['db_user'] ?? ''), (string) ($cfg['db_password'] ?? ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

function mt_blog_exists_in_db(string $blogWord): bool
{
    try {
        $stmt = mt_db()->prepare('SELECT 1 FROM blogs WHERE blog_word = ? LIMIT 1');
        $stmt->execute([$blogWord]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function mt_register_blog_in_db(string $blogWord): bool
{
    try {
        $stmt = mt_db()->prepare('INSERT INTO blogs (blog_word) VALUES (?)');
        $stmt->execute([$blogWord]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function mt_delete_blog_in_db(string $blogWord): bool
{
    try {
        $stmt = mt_db()->prepare('DELETE FROM blogs WHERE blog_word = ?');
        $stmt->execute([$blogWord]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function mt_list_blogs(): array
{
    try {
        return mt_db()->query('SELECT id, blog_word, created_at FROM blogs ORDER BY created_at DESC')->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
