<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);
require_once $rootDir . '/vendor/autoload.php';

$phpstanBinary = $rootDir . '/vendor/bin/phpstan';
if (is_file($phpstanBinary)) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpstanBinary)
        . ' analyse --configuration=' . escapeshellarg($rootDir . '/phpstan.neon');
    passthru($cmd, $exitCode);
    exit($exitCode);
}

$errors = [];
$srcDir = str_replace('\\', '/', $rootDir . '/src');
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS)
);

$checkedClasses = 0;

/** @var SplFileInfo $fileInfo */
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }

    $fullPath = str_replace('\\', '/', $fileInfo->getPathname());
    $relativeToSrc = ltrim(substr($fullPath, strlen($srcDir)), '/');
    $expectedClass = 'Kampusly\\' . str_replace('/', '\\', substr($relativeToSrc, 0, -4));

    if (!class_exists($expectedClass) && !interface_exists($expectedClass) && !enum_exists($expectedClass)) {
        $errors[] = sprintf(
            'PSR-4 mismatch: File "src/%s" does not define expected symbol "%s".',
            $relativeToSrc,
            $expectedClass
        );
        continue;
    }

    $reflection = new ReflectionClass($expectedClass);
    foreach ($reflection->getMethods() as $method) {
        if ($method->getDeclaringClass()->getName() !== $expectedClass) {
            continue;
        }
        if ($method->isConstructor() || $method->isDestructor()) {
            continue;
        }
        if (!$method->hasReturnType()) {
            $errors[] = sprintf(
                'Missing return type declaration on %s::%s().',
                $expectedClass,
                $method->getName()
            );
        }
    }

    $checkedClasses++;
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[STAN FAIL] ' . $error . PHP_EOL);
    }
    exit(1);
}

fwrite(
    STDOUT,
    sprintf('[OK] Static analysis passed (%d PSR-4 classes verified with strict return types).%s', $checkedClasses, PHP_EOL)
);
exit(0);
