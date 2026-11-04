<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);
require_once $rootDir . '/tests/bootstrap.php';

$phpunitBinary = $rootDir . '/vendor/bin/phpunit';
if (is_file($phpunitBinary)) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpunitBinary)
        . ' --configuration ' . escapeshellarg($rootDir . '/phpunit.xml');
    passthru($cmd, $exitCode);
    exit($exitCode);
}

$testsDir = $rootDir . '/tests';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testsDir, FilesystemIterator::SKIP_DOTS)
);

$testFiles = [];
/** @var SplFileInfo $fileInfo */
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && str_ends_with($fileInfo->getFilename(), 'Test.php')) {
        $testFiles[] = $fileInfo->getPathname();
    }
}

sort($testFiles);

$totalTests = 0;
$totalAssertions = 0;
$failures = [];

foreach ($testFiles as $file) {
    require_once $file;
}

$declaredClasses = get_declared_classes();
foreach ($declaredClasses as $className) {
    if (!str_starts_with($className, 'Kampusly\\Tests\\') || !str_ends_with($className, 'Test')) {
        continue;
    }

    $reflection = new ReflectionClass($className);
    if ($reflection->isAbstract()) {
        continue;
    }

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        $totalTests++;
        /** @var Kampusly\Tests\Support\TestCase $instance */
        $instance = $reflection->newInstance();

        try {
            $instance->runTestMethod($method->getName());
            $totalAssertions += $instance->getAssertionCount();
            fwrite(STDOUT, '.');
        } catch (Throwable $failure) {
            $failures[] = sprintf(
                '%s::%s — %s (%s:%d)',
                $className,
                $method->getName(),
                $failure->getMessage(),
                basename($failure->getFile()),
                $failure->getLine()
            );
            fwrite(STDOUT, 'F');
        }
    }
}

fwrite(STDOUT, PHP_EOL . PHP_EOL);

if ($failures !== []) {
    fwrite(STDERR, sprintf("Failures (%d):\n", count($failures)));
    foreach ($failures as $index => $message) {
        fwrite(STDERR, sprintf("  %d) %s\n", $index + 1, $message));
    }
    exit(1);
}

fwrite(
    STDOUT,
    sprintf("OK (%d tests, %d assertions)\n", $totalTests, $totalAssertions)
);
exit(0);

