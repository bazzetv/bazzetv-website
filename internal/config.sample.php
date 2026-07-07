<?php
// Copy to config.php for local use. In production this file is generated
// during deploy from GitHub Actions secrets — never commit real values.

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'bazzetv_internal');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');

define('ADMIN_USER', 'admin');
// Generate with: php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
define('ADMIN_PASSWORD_HASH', '$2y$10$replace-with-a-real-bcrypt-hash');

// Reused by internal/cron/collect-stats.php
define('YOUTUBE_API_KEY', '');
define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REFRESH_TOKEN', '');
