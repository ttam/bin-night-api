<?php

declare(strict_types=1);

namespace App\Providers;

use App\Database\SQLiteConnection;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;

final class DatabaseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Connection::resolverFor('sqlite', static function ($pdo, $database, $prefix, $config) {
            return new SQLiteConnection($pdo, $database, $prefix, $config);
        });
    }
}
