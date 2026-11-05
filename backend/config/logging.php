<?php

declare(strict_types=1);

use Kampusly\Bootstrap\EnvLoader;

return [
    'channel' => EnvLoader::getString('LOG_CHANNEL', 'file'),
    'level' => strtolower(EnvLoader::getString('LOG_LEVEL', 'debug')),
    'path' => dirname(__DIR__) . '/storage/logs/kampusly.log',
];

