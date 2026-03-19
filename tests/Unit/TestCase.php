<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Database\SQLiteConnection;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected function bootEloquent(): self
    {
        $capsule = new Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $this;
    }

    protected function bootConnection(): self
    {
        Connection::resolverFor('sqlite', static function ($pdo, $database, $prefix, $config) {
            return new SQLiteConnection($pdo, $database, $prefix, $config);
        });

        return $this;
    }
}
