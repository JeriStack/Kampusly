<?php

declare(strict_types=1);

use Kampusly\Bootstrap\EnvLoader;

return [
    'driver' => EnvLoader::getString('DB_CONNECTION', 'mysql'),
    'host' => EnvLoader::getString('DB_HOST', '127.0.0.1'),
    'port' => EnvLoader::getInt('DB_PORT', 3306),
    'database' => EnvLoader::getString('DB_DATABASE', 'kampusly'),
    'username' => EnvLoader::getString('DB_USERNAME', 'kampusly_app'),
    'password' => EnvLoader::getString('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'timezone' => '+08:00',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ],
];

