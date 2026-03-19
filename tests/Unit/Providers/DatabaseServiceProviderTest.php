<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Database\SQLiteConnection;
use App\Providers\DatabaseServiceProvider;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatabaseServiceProviderTest extends TestCase
{
    public function testItRegistersCustomSqliteConnectionResolver(): void
    {
        new DatabaseServiceProvider(new Application())->boot();

        $resolver = Connection::getResolver('sqlite');
        $connection = $resolver(new PDO('sqlite::memory:'), ':memory:', '', []);

        self::assertInstanceOf(SQLiteConnection::class, $connection);
    }
}
