<?php
// Test bootstrap: define constants that backend files expect
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'wellness_test');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_ENV', 'test');
define('APP_SECRET', 'test-secret-key-32-chars-minimum!');

// Autoload via Composer
require_once dirname(__DIR__) . '/vendor/autoload.php';
