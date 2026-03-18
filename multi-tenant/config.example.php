<?php
if (isset($_SERVER['HTTP_HOST'])) {
    http_response_code(403);
    exit;
}

/**
 * DB + SMTP config — copy to config.php and fill in your values.
 * config.php is in .gitignore and will never be committed.
 */
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'your_database_name',
    'db_user' => 'your_database_user',
    'db_password' => 'your_database_password',
    'db_charset' => 'utf8mb4',
    'app_debug' => false,

    // SMTP — used to deliver password-reset one-time codes.
    // Gmail: use an App Password (16-char code) as smtp_pass.
    // Port 465 = SSL, 587 = STARTTLS.
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 465,
    'smtp_user' => 'your@gmail.com',
    'smtp_pass' => 'your-16-char-app-password',
    'smtp_from' => 'your@gmail.com',
];
