<?php

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'name' => getenv('DB_NAME') ?: 'mashirikianosacc_mashirikiano',
        'user' => getenv('DB_USER') ?: 'mashirikianosacc_mashirikianosacco',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Mashirikiano SACCO',
        'base_url' => getenv('APP_BASE_URL') ?: '',
        'admin_token' => getenv('ADMIN_REPORT_TOKEN') ?: '',
    ],
    'mpesa' => [
        'consumer_key' => getenv('MPESA_CONSUMER_KEY') ?: '',
        'consumer_secret' => getenv('MPESA_CONSUMER_SECRET') ?: '',
        'shortcode' => getenv('MPESA_SHORTCODE') ?: '',
        'passkey' => getenv('MPESA_PASSKEY') ?: '',
        'environment' => getenv('MPESA_ENV') ?: 'production',
    ],
];
