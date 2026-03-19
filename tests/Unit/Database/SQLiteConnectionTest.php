<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Database\SQLiteConnection;
use PDO;
use PHPUnit\Framework\TestCase;

final class SQLiteConnectionTest extends TestCase
{
    private SQLiteConnection $connection;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->connection = new SQLiteConnection($pdo);
    }

    public function testConvertTzReturnsNullWhenDateTimeIsNull(): void
    {
        self::assertNull($this->convertTz(null, 'UTC', 'Australia/Melbourne'));
    }

    public function testConvertTzReturnsDateTimeWhenTimezonesMatch(): void
    {
        self::assertSame('1985-10-26 01:21:00', $this->convertTz('1985-10-26 01:21:00', 'UTC', 'UTC'));
    }

    public function testConvertTzHandlesNamesOutsideDST(): void
    {
        self::assertSame('1985-04-25 17:21:00', $this->convertTz('1985-04-26 01:21:00', 'UTC', 'America/Los_Angeles'));
    }

    public function testConvertTzHandlesNamesInsideDST(): void
    {
        self::assertSame('1985-10-25 18:21:00', $this->convertTz('1985-10-26 01:21:00', 'UTC', 'America/Los_Angeles'));
    }

    public function testConvertTzHandlesOffsets(): void
    {
        self::assertSame('1985-10-26 03:21:00', $this->convertTz('1985-10-26 01:21:00', '+00:00', '+02:00'));
    }

    private function convertTz(?string $dt, string $from, string $to): ?string
    {
        $stmt = $this->connection->getPdo()->prepare('SELECT CONVERT_TZ(?, ?, ?)');
        $stmt->execute([$dt, $from, $to]);

        return $stmt->fetchColumn() ?: null;
    }
}
