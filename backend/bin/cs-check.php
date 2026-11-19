<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);
$syntaxOnly = in_array('--syntax-only', $argv ?? [], true);

$phpcsBinary = $rootDir . '/vendor/bin/phpcs';
if (!$syntaxOnly && is_file($phpcsBinary)) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpcsBinary)
        . ' --standard=' . escapeshellarg($rootDir . '/phpcs.xml');
    passthru($cmd, $exitCode);
    exit($exitCode);
}

$directories = ['config', 'public', 'routes', 'src', 'bin', 'tests'];
$phpFiles = [];

foreach ($directories as $dir) {
    $fullDir = $rootDir . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($fullDir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullDir, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $fileInfo */
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php') {
            $phpFiles[] = $fileInfo->getPathname();
        }
    }
}

sort($phpFiles);
$errors = [];

foreach ($phpFiles as $filePath) {
    $relativePath = ltrim(str_replace(['\\', $rootDir], ['/', ''], $filePath), '/');

    $output = [];
    $returnVar = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($filePath) . ' 2>&1', $output, $returnVar);
    if ($returnVar !== 0) {
        $errors[] = sprintf('%s: %s', $relativePath, implode(' ', $output));
        continue;
    }

    if ($syntaxOnly) {
        continue;
    }

    $contents = file_get_contents($filePath);
    if ($contents === false) {
        $errors[] = sprintf('%s: Unable to read file.', $relativePath);
        continue;
    }

    if (!str_starts_with($contents, "<?php\n\ndeclare(strict_types=1);")
        && !str_starts_with($contents, "<?php\r\n\r\ndeclare(strict_types=1);")
    ) {
        $errors[] = sprintf('%s: Missing PSR-12 header "declare(strict_types=1);" after opening tag.', $relativePath);
    }

    if (str_contains($contents, "\t")) {
        $errors[] = sprintf('%s: Tabs are not permitted; use 4 spaces for indentation (PSR-12).', $relativePath);
    }

    $lines = preg_split('/\r\n|\r|\n/', $contents);
    if (is_array($lines)) {
        foreach ($lines as $index => $line) {
            $lineNo = $index + 1;
            if (preg_match('/[ \t]+$/', $line) === 1) {
                $errors[] = sprintf('%s:%d: Trailing whitespace is not permitted (PSR-12).', $relativePath, $lineNo);
            }
            if (strlen($line) > 140) {
                $errors[] = sprintf('%s:%d: Line exceeds 140 characters (%d chars).', $relativePath, $lineNo, strlen($line));
            }
        }
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[FAIL] ' . $error . PHP_EOL);
    }
    exit(1);
}

$modeLabel = $syntaxOnly ? 'PHP syntax lint' : 'PSR-12 code style & syntax check';
fwrite(STDOUT, sprintf('[OK] %s passed (%d files checked).%s', $modeLabel, count($phpFiles), PHP_EOL));
exit(0);

