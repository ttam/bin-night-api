<?php

declare(strict_types=1);

namespace App\Providers;

use App\Database\SQLiteConnection;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use PDO;

final class DatabaseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Connection::resolverFor('sqlite', static function (Closure|PDO $pdo, string $database, string $prefix, array $config): SQLiteConnection {
            return new SQLiteConnection($pdo, $database, $prefix, $config);
        });
    }
}
