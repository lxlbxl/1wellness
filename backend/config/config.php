<?php
/**
 * Main Configuration File
 * 1wellness - Health Assessment System
 */

// Load environment variables from .env file
require_once __DIR__ . '/env_loader.php';

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load database configuration from file if exists (created by installer)
$dbConfigFile = __DIR__ . '/db_config.php';
if (file_exists($dbConfigFile)) {
    require_once $dbConfigFile;
}

// Default Configuration (Fallback) - Ensure these are defined even if db_config.php exists
if (!defined('DB_TYPE'))
    define('DB_TYPE', getenv('DB_TYPE') ?: 'pgsql'); // 'mysql', 'sqlite' or 'pgsql'

// SQLite Configuration
if (!defined('DB_PATH'))
    define('DB_PATH', APP_ROOT . '/database/1wellness.db');

// MySQL Configuration
if (!defined('DB_HOST'))
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME'))
    define('DB_NAME', getenv('DB_NAME') ?: 'wellness_db');
if (!defined('DB_USER'))
    define('DB_USER', getenv('DB_USER') ?: 'wellness_user');
if (!defined('DB_PASS'))
    define('DB_PASS', getenv('DB_PASS') ?: 'securepassword123');
if (!defined('DB_PORT'))
    define('DB_PORT', getenv('DB_PORT') ?: 5432); // Postgres port

// Webhook Settings
define('WEBHOOK_MAX_RETRIES', env('WEBHOOK_MAX_RETRIES', 5));
define('WEBHOOK_TIMEOUT', env('WEBHOOK_TIMEOUT', 10));

// Application Settings
define('APP_NAME', env('APP_NAME', '1wellness Admin'));
define('APP_VERSION', '2.0.0');
define('APP_ENV', env('APP_ENV', 'development'));
define('APP_URL', env('APP_URL', 'https://1wellness.club'));

// Security Settings
define('SESSION_NAME', env('SESSION_NAME', '1wellness_admin_session'));
define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 3600 * 8));
define('SESSION_SECRET', env('SESSION_SECRET', bin2hex(random_bytes(32))));
define('CSRF_TOKEN_NAME', 'csrf_token');
define('JWT_SECRET', env('JWT_SECRET', bin2hex(random_bytes(32))));

// Admin Settings
define('ADMIN_USERNAME', env('ADMIN_USERNAME', 'admin'));
define('ADMIN_PASSWORD_HASH', env('ADMIN_PASSWORD_HASH', password_hash('admin123', PASSWORD_DEFAULT)));
define('ADMIN_EMAIL', env('ADMIN_EMAIL', 'admin@1wellness.club'));

// API Settings
define('API_VERSION', env('API_VERSION', 'v1'));
define('API_RATE_LIMIT', (int) env('API_RATE_LIMIT', 100));

// N8N API Access Key
define('N8N_API_KEY', env('N8N_API_KEY', ''));

// File Upload Settings
define('UPLOAD_MAX_SIZE', (int) env('UPLOAD_MAX_SIZE', 5 * 1024 * 1024));
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);
define('UPLOAD_PATH', env('UPLOAD_PATH', APP_ROOT . '/uploads/'));

// Email Settings (for notifications)
define('SMTP_HOST', env('SMTP_HOST', 'localhost'));
define('SMTP_PORT', env('SMTP_PORT', 587));
define('SMTP_USERNAME', env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', env('SMTP_PASSWORD', ''));
define('FROM_EMAIL', env('SMTP_FROM_EMAIL', 'noreply@1wellness.club'));
define('FROM_NAME', env('SMTP_FROM_NAME', '1wellness'));

// Categories Configuration
define('HEALTH_CATEGORIES', [
    'pcos' => [
        'name' => 'PCOS',
        'table' => 'pcos_assessments',
        'fields' => ['age', 'weight', 'height', 'symptoms', 'menstrual_cycle', 'lifestyle']
    ],
    'acne' => [
        'name' => 'Acne',
        'table' => 'acne_assessments',
        'fields' => ['age', 'skin_type', 'acne_severity', 'triggers', 'current_treatment', 'lifestyle']
    ],
    'weight' => [
        'name' => 'Weight Management',
        'table' => 'weight_assessments',
        'fields' => ['age', 'current_weight', 'target_weight', 'height', 'activity_level', 'diet_preferences']
    ]
]);

// Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    define('DEBUG_MODE', true);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    define('DEBUG_MODE', false);
}

// Timezone
date_default_timezone_set('UTC');

// CORS Settings (for API)
define('CORS_ALLOWED_ORIGINS', [
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:8080',
    env('APP_URL', 'https://1wellness.club'),
    'https://www.1wellness.club',
    'https://pcos.1wellness.club',
    'https://skin.1wellness.club',
    'https://lean.1wellness.club',
    'https://men.1wellness.club',
    'https://member.1wellness.club',
    'https://admin.1wellness.club',
    'file://'
]);

// Security Headers
function setSecurityHeaders()
{
    // Only set headers if they haven't been sent yet
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        if (APP_ENV === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}

// Auto-load classes
spl_autoload_register(function ($class) {
    $classFile = APP_ROOT . '/backend/classes/' . $class . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// Start session if not already started
if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Set security headers
setSecurityHeaders();
?>