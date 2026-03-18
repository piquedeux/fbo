<?php
declare(strict_types=1);

require __DIR__ . '/tenant.php';

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

$blog     = mt_normalize_blog_word((string) ($argv[1] ?? ''));
$password = (string) ($argv[2] ?? '');

if ($blog === '') {
    echo "Usage: php provision.php <blogword> [password]\n";
    exit(1);
}

if ($password !== '' && mb_strlen($password) < 6) {
    echo "Password must be at least 6 characters (or leave empty to set via onboarding).\n";
    exit(1);
}

$result = mt_provision_blog($blog, $password);

echo ($result['message'] ?? 'Done.') . "\n";

if (!empty($result['ok'])) {
    echo 'Blog word : ' . ($result['blog'] ?? $blog) . "\n";
    if (!empty($result['path'])) {
        echo 'Data path : ' . $result['path'] . "\n";
    }
    if (empty($result['already_exists'])) {
        echo 'URL (once deployed): /blog/' . ($result['blog'] ?? $blog) . "\n";
    }
    exit(0);
}

exit(1);
