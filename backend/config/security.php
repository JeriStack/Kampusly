<?php

declare(strict_types=1);

use Kampusly\Bootstrap\EnvLoader;

return [
    'frontend_origin' => rtrim(EnvLoader::getString('FRONTEND_ORIGIN', 'http://localhost:5173'), '/'),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Accept', 'X-CSRF-Token', 'X-Request-Id'],
    'allow_credentials' => true,
    'session_cookie_name' => EnvLoader::getString('SESSION_COOKIE_NAME', 'kampusly_session'),
    'session_cookie_secure' => EnvLoader::getBool('SESSION_COOKIE_SECURE', false),
    'session_cookie_httponly' => EnvLoader::getBool('SESSION_COOKIE_HTTPONLY', true),
    'session_cookie_samesite' => EnvLoader::getString('SESSION_COOKIE_SAMESITE', 'Lax'),
    'session_idle_timeout_minutes' => EnvLoader::getInt('SESSION_IDLE_TIMEOUT_MINUTES', 30),
    'session_absolute_timeout_hours' => EnvLoader::getInt('SESSION_ABSOLUTE_TIMEOUT_HOURS', 8),
];

