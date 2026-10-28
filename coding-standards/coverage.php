<?php

declare(strict_types=1);

/**
 * Fails unless every executable line and every method is covered, so a drop in coverage fails `composer check`.
 *
 * Usage: php coding-standards/coverage.php [path/to/clover.xml]
 */
$file = $argv[1] ?? 'coverage/clover.xml';

if (\is_file($file) === false) {
    \fwrite(STDERR, \sprintf("No coverage report at %s. Run `composer coverage-check` to create one.\n", $file));
    exit(2);
}

$clover = \simplexml_load_file($file);

if ($clover === false) {
    \fwrite(STDERR, \sprintf("Could not read the coverage report at %s.\n", $file));
    exit(2);
}

$metrics = $clover->project->metrics;

$lines = [(int) $metrics['coveredstatements'], (int) $metrics['statements']];
$methods = [(int) $metrics['coveredmethods'], (int) $metrics['methods']];

$percentage = static fn (array $covered): float => $covered[1] === 0 ? 100.0 : $covered[0] / $covered[1] * 100;

$passed = $lines[0] === $lines[1] && $methods[0] === $methods[1];

echo \sprintf(
    "\n%s Lines %d/%d (%0.2f%%), methods %d/%d (%0.2f%%) %s\n\n",
    $passed ? "\e[42m\e[30m" : "\e[41m\e[97m",
    $lines[0],
    $lines[1],
    $percentage($lines),
    $methods[0],
    $methods[1],
    $percentage($methods),
    "\e[0m",
);

if ($passed) {
    exit(0);
}

echo "Not covered:\n";

foreach ($clover->xpath('//file') ?: [] as $source) {
    $missing = [];

    foreach ($source->line as $line) {
        if ((int) $line['count'] === 0) {
            $missing[] = (string) $line['num'];
        }
    }

    if ($missing !== []) {
        echo \sprintf("  %s: line %s\n", (string) $source['name'], \implode(', ', $missing));
    }
}

echo "\n";

exit(1);
