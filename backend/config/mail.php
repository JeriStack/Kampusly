<?php

declare(strict_types=1);

use Kampusly\Bootstrap\EnvLoader;

return [
    'enabled' => EnvLoader::getBool('MAIL_ENABLED', false),
    'host' => EnvLoader::getString('SMTP_HOST', 'mailpit'),
    'port' => EnvLoader::getInt('SMTP_PORT', 1025),
    'username' => EnvLoader::getString('SMTP_USERNAME', ''),
    'password' => EnvLoader::getString('SMTP_PASSWORD', ''),
    'encryption' => EnvLoader::getString('SMTP_ENCRYPTION', 'none'),
    'from_address' => EnvLoader::getString('SMTP_FROM_ADDRESS', 'no-reply@kampusly.local'),
    'from_name' => EnvLoader::getString('SMTP_FROM_NAME', 'Kampusly'),
];

