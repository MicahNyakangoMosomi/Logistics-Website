<?php

$rootPath = dirname(__DIR__);
$autoloadPath = $rootPath . '/vendor/autoload.php';

/**
 * 1. Load Composer autoload
 */
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found. Run composer install.");
}

require_once $autoloadPath;

/**
 * 2. Load .env safely but strictly
 */
if (!class_exists(Dotenv\Dotenv::class)) {
    die("Dotenv library not installed.");
}

$dotenv = Dotenv\Dotenv::createImmutable($rootPath);
$dotenv->load();

/**
 * 3. Hard fail if .env is missing critical values
 */
function env(string $key, string $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

function env_required(string $key)
{
    $value = env($key);

    if ($value === null || $value === '') {
        die("Missing required environment variable: {$key}");
    }

    return $value;
}

/**
 * 4. CONFIG ARRAY
 */
return [
    'db' => [
        'host' => env_required('DB_HOST'),
        'name' => env_required('DB_NAME'),
        'user' => env_required('DB_USER'),
        'pass' => env_required('DB_PASS'),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ],

    'app' => [
        'name' => env('APP_NAME', 'App'),
    ],
];