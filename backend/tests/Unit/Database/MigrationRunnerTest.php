<?php

declare(strict_types=1);

namespace Kampusly\Tests\Unit\Database;

use Kampusly\Shared\Infrastructure\Database\MigrationRunner;
use Kampusly\Tests\Support\TestCase;

final class MigrationRunnerTest extends TestCase
{
    public function testSplitSqlStatementsStripsCommentsAndSplitsCorrectly(): void
    {
        $sql = <<<SQL
-- This is a comment at the top
CREATE TABLE `test1` (
    `id` INT NOT NULL,
    PRIMARY KEY (`id`)
);

-- Another comment in the middle
CREATE TABLE `test2` (
    `name` VARCHAR(50) NOT NULL
);
SQL;

        $statements = MigrationRunner::splitSqlStatements($sql);
        $this->assertCount(2, $statements);
        $this->assertStringContainsString('CREATE TABLE `test1`', $statements[0]);
        $this->assertStringContainsString('CREATE TABLE `test2`', $statements[1]);
        $this->assertFalse(str_contains($statements[0], '-- This is a comment'));
    }

    public function testComputeChecksumIsDeterministic(): void
    {
        $sqlUnix = "CREATE TABLE `sample` (`id` INT);\n";
        $sqlWindows = "CREATE TABLE `sample` (`id` INT);\r\n";

        $checksum1 = MigrationRunner::computeChecksum($sqlUnix);
        $checksum2 = MigrationRunner::computeChecksum($sqlWindows);

        $this->assertSame($checksum1, $checksum2, 'Checksum must be newline-invariant');
        $this->assertSame(64, strlen($checksum1));
    }
}

