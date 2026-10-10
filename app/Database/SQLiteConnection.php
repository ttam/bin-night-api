<?php

declare(strict_types=1);

namespace App\Database;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\SQLiteConnection as LaravelConnection;

final class SQLiteConnection extends LaravelConnection
{
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);
        $this->registerSqliteFunctions();
    }

    private function registerSqliteFunctions(): void
    {
        $pdo = $this->getPdo();

        $pdo->createFunction('CONVERT_TZ', static function (?string $dt, string $fromTz, string $toTz): ?string {
            static $timezones = [];

            if ($dt === null || $fromTz === $toTz) {
                return $dt;
            }

            $format = 'Y-m-d H:i:s';
            return DateTimeImmutable::createFromFormat($format, $dt, $timezones[$fromTz] ??= new DateTimeZone($fromTz))
                ->setTimezone($timezones[$toTz] ??= new DateTimeZone($toTz))
                ->format($format);
        }, 3);
    }
}
