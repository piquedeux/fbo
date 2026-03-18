-- FBO Multi-Tenant Blog Registry
-- Run once: mysql -u USER -p DATABASE < schema.sql

CREATE TABLE IF NOT EXISTS blogs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blog_word   VARCHAR(24)  NOT NULL UNIQUE,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
