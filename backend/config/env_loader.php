<?php
/**
 * Environment Variable Loader
 * Loads environment variables from .env file
 * 
 * Usage: Include this file at the very beginning of your application
 * require_once __DIR__ . '/env_loader.php';
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

/**
 * Load environment variables from .env file
 */
function loadEnv($path = null)
{
    if ($path === null) {
        $path = APP_ROOT . '/.env';
    }

    if (!file_exists($path)) {
        // Check if we're in production - warn if .env missing
        if (getenv('APP_ENV') === 'production') {
            error_log('Warning: .env file not found at ' . $path);
        }
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse key=value
        if (strpos($line, '=') === false) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove quotes if present
        $value = trim($value, '"\'');

        // Set environment variable if not already set
        if (!getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    return true;
}

// Auto-load environment variables
loadEnv();

// Helper function to get environment variable with default
function env($key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? $default;
    }
    return $value;
}

// Helper function to get required environment variable
function envRequired($key, $errorMessage = null)
{
    $value = env($key);
    if ($value === null || $value === '') {
        $message = $errorMessage ?? "Required environment variable '{$key}' is not set";
        if (env('APP_ENV') === 'development') {
            throw new Exception($message);
        } else {
            error_log($message);
            return null;
        }
    }
    return $value;
}
?>