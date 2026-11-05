<?php

declare(strict_types=1);

namespace App\Support;

/** Reads a list out of an environment variable: `a, b,,c` is `['a', 'b', 'c']`, and nothing at all is an empty list. */
final class CommaSeparated
{
    /** @return list<string> */
    public static function list(mixed $value): array
    {
        if (\is_string($value) === false) {
            return [];
        }

        return \array_values(\array_filter(
            \array_map(\trim(...), \explode(',', $value)),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
