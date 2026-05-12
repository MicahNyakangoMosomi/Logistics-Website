<?php

$rootPath = dirname(__DIR__);
$autoloadPath = $rootPath . '/vendor/autoload.php';

if (is_file($autoloadPath)) {
    require_once $autoloadPath;

    if (class_exists(Dotenv\Dotenv::class)) {
        $dotenv = Dotenv\Dotenv::createImmutable($rootPath);
        $dotenv->safeLoad();
    }
}

if (!function_exists('env_value')) {
    function env_value(string $key, string $default = ''): string
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string) $_SERVER[$key];
        }

        $value = getenv($key);

        return $value !== false && $value !== '' ? (string) $value : $default;
    }
}

return [
    'db' => [
        'host' => env_value('DB_HOST', 'localhost'),
        'name' => env_value('DB_NAME', 'mashirikianosacc_mashirikiano'),
        'user' => env_value('DB_USER', 'mashirikianosacc_mashirikianosacco'),
        'pass' => env_value('DB_PASS'),
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Mashirikiano SACCO',
        'base_url' => env_value('APP_BASE_URL'),
        'admin_token' => env_value('ADMIN_REPORT_TOKEN'),
    ],
    'mpesa' => [
        'consumer_key' => env_value('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env_value('MPESA_CONSUMER_SECRET'),
        'shortcode' => env_value('MPESA_SHORTCODE'),
        'passkey' => env_value('MPESA_PASSKEY'),
        'environment' => env_value('MPESA_ENV', 'production'),
    ],
];
