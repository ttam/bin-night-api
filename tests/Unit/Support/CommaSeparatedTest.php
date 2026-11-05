<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CommaSeparated;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommaSeparatedTest extends TestCase
{
    /** @return iterable<string, array{mixed, list<string>}> */
    public static function values(): iterable
    {
        yield 'one' => ['https://binnight.app', ['https://binnight.app']];
        yield 'several' => ['http://localhost:5173,https://binnight.app', ['http://localhost:5173', 'https://binnight.app']];
        yield 'spaces around them' => [' a , b ', ['a', 'b']];
        yield 'empty items' => ['a,,b,', ['a', 'b']];
        yield 'an empty string' => ['', []];
        yield 'only commas and spaces' => [' , ,', []];
        yield 'not set' => [null, []];
        yield 'not a string' => [false, []];
    }

    /** @param list<string> $expected */
    #[DataProvider('values')]
    public function testItReadsAList(mixed $value, array $expected): void
    {
        self::assertSame($expected, CommaSeparated::list($value));
    }
}
