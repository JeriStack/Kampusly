<?php

declare(strict_types=1);

use Kampusly\Bootstrap\EnvLoader;

return [
    'name' => EnvLoader::getString('APP_NAME', 'Kampusly'),
    'env' => EnvLoader::getString('APP_ENV', 'local'),
    'debug' => EnvLoader::getBool('APP_DEBUG', false),
    'url' => rtrim(EnvLoader::getString('APP_URL', 'http://localhost:8080'), '/'),
    'timezone' => EnvLoader::getString('APP_TIMEZONE', 'Asia/Manila'),
    'api_prefix' => '/api/v1',
    'version' => '0.1.0',
];

