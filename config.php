<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

const APP_ROOT = __DIR__;

if (!function_exists('load_env_file')) {
    function load_env_file(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (($value[0] ?? '') === '"' && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }
}

load_env_file(APP_ROOT . DIRECTORY_SEPARATOR . '.env');

if (!function_exists('detect_app_base_url')) {
    function detect_app_base_url(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $https ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        if ($host === '') {
            $host = 'localhost';
        }

        $basePath = '';
        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
        $appRoot = realpath(APP_ROOT) ?: APP_ROOT;

        if ($documentRoot !== '' && str_starts_with($appRoot, $documentRoot)) {
            $relative = substr($appRoot, strlen($documentRoot));
            $relative = str_replace('\\', '/', (string) $relative);
            $relative = trim($relative, '/');
            if ($relative !== '') {
                $basePath = '/' . $relative;
            }
        }

        return $scheme . '://' . $host . $basePath;
    }
}

if (!function_exists('resolve_app_base_url')) {
    function resolve_app_base_url(): string
    {
        $configured = trim((string) env('APP_BASE_URL', ''));
        if ($configured !== '' && strtolower($configured) !== 'auto') {
            return rtrim($configured, '/');
        }

        return rtrim(detect_app_base_url(), '/');
    }
}

define('APP_BASE_URL', resolve_app_base_url());
define('APP_ENV', (string) env('APP_ENV', 'production'));
define('APP_DEBUG', filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL));
define('DB_HOST', (string) env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) env('DB_PORT', '3306'));
define('DB_NAME', (string) env('DB_NAME', 'foodmandu'));
define('DB_USER', (string) env('DB_USER', 'root'));
define('DB_PASS', (string) env('DB_PASS', ''));
define('RESTAURANT_LAT', (float) env('RESTAURANT_LAT', '27.717245'));
define('RESTAURANT_LNG', (float) env('RESTAURANT_LNG', '85.323960'));
define('DELIVERY_TIME_LIMIT_MINUTES', max(1, (int) env('DELIVERY_TIME_LIMIT_MINUTES', '20')));
define('LATE_COUPON_PERCENT', max(1, min(100, (int) env('LATE_COUPON_PERCENT', '20'))));
define('LATE_COUPON_CLAIM_MINUTES', max(1, (int) env('LATE_COUPON_CLAIM_MINUTES', '20')));
define('LATE_COUPON_MIN_ORDER_AMOUNT', max(0.0, (float) env('LATE_COUPON_MIN_ORDER_AMOUNT', '2000')));
define('STRIPE_SECRET_KEY', (string) env('STRIPE_SECRET_KEY', ''));
define('STRIPE_PUBLISHABLE_KEY', (string) env('STRIPE_PUBLISHABLE_KEY', ''));
define('STRIPE_WEBHOOK_SECRET', (string) env('STRIPE_WEBHOOK_SECRET', ''));
define('ESEWA_MERCHANT_CODE', (string) env('ESEWA_MERCHANT_CODE', 'EPAYTEST'));
define('ESEWA_SECRET', (string) env('ESEWA_SECRET', ''));
define('KHALTI_SECRET_KEY', (string) env('KHALTI_SECRET_KEY', ''));
define('SESSION_IDLE_TIMEOUT_MINUTES', max(1, (int) env('SESSION_IDLE_TIMEOUT_MINUTES', '30')));
define('SESSION_ABSOLUTE_TIMEOUT_HOURS', max(1, (int) env('SESSION_ABSOLUTE_TIMEOUT_HOURS', '12')));
define('MAX_CART_ITEM_QTY', max(1, (int) env('MAX_CART_ITEM_QTY', '20')));

date_default_timezone_set('Asia/Kathmandu');

error_reporting(APP_DEBUG ? E_ALL : (E_ALL & ~E_NOTICE & ~E_WARNING));
ini_set('display_errors', APP_DEBUG ? '1' : '0');

if (!headers_sent()) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self)');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://unpkg.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; connect-src 'self'; form-action 'self' https://rc-epay.esewa.com.np");

    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

require_once APP_ROOT . '/components/helpers.php';
require_once APP_ROOT . '/components/flash.php';
require_once APP_ROOT . '/components/db.php';
require_once APP_ROOT . '/components/guards.php';
require_once APP_ROOT . '/components/functions.php';
