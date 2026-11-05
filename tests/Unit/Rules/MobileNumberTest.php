<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\MobileNumber;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MobileNumberTest extends TestCase
{
    protected function setUp(): void
    {
        $container = new Container();
        $container->instance('config', new Repository(['phone' => ['mobile_patterns' => ['AU' => '/^\+614\d{8}$/D', 'NZ' => '/^\+642\d{7,9}$/D']]]));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    /** @return iterable<string, array{mixed, bool}> */
    public static function numbers(): iterable
    {
        yield 'an Australian mobile' => ['+61400000000', true];
        yield 'a mobile from another configured country' => ['+64211234567', true];
        yield 'a landline' => ['+61290000000', false];
        yield 'a country that is not configured' => ['+14155550123', false];
        yield 'local format' => ['0400000000', false];
        yield 'with spaces' => ['+61 400 000 000', false];
        yield 'too long' => ['+614000000000', false];
        yield 'a final newline' => ["+61400000000\n", false];
        yield 'empty' => ['', false];
        yield 'not a string' => [61400000000, false];
    }

    #[DataProvider('numbers')]
    public function testItAcceptsOnlyMobilesWeHaveAPatternFor(mixed $value, bool $valid): void
    {
        $failures = [];

        new MobileNumber()->validate('target', $value, static function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        self::assertSame($valid ? [] : ['The :attribute must be an Australian mobile, like +61400000000.'], $failures);
    }
}
