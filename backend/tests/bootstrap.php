<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/TestCase.php';

$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'testing';
$_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? 'true';
$_ENV['APP_TIMEZONE'] = $_ENV['APP_TIMEZONE'] ?? 'Asia/Manila';
$_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? '127.0.0.1';
$_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? '3306';
$_ENV['DB_DATABASE'] = $_ENV['DB_DATABASE'] ?? 'kampusly';
$_ENV['DB_USERNAME'] = $_ENV['DB_USERNAME'] ?? 'kampusly_app';
$_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? 'replace_me';
