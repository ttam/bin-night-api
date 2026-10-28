<?php

declare(strict_types=1);

namespace App\Database;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\SQLiteConnection as LaravelConnection;
use Pdo\Sqlite;

final class SQLiteConnection extends LaravelConnection
{
    /**
     * @param \PDO|\Closure(): \PDO $pdo
     * @param array<mixed> $config
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);
        $this->registerSqliteFunctions();
    }

    private function registerSqliteFunctions(): void
    {
        /** @var Sqlite $pdo */
        $pdo = $this->getPdo();

        $pdo->createFunction('CONVERT_TZ', static function (?string $dt, string $fromTz, string $toTz): ?string {
            /** @var array<string, DateTimeZone> $timezones */
            static $timezones = [];

            if ($dt === null || $fromTz === $toTz) {
                return $dt;
            }

            $format = 'Y-m-d H:i:s';
            /** @var DateTimeImmutable $converted */
            $converted = DateTimeImmutable::createFromFormat($format, $dt, $timezones[$fromTz] ??= new DateTimeZone($fromTz));
            return $converted
                ->setTimezone($timezones[$toTz] ??= new DateTimeZone($toTz))
                ->format($format);
        }, 3);
    }
}
