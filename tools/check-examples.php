<?php
declare(strict_types=1);

/**
 * Runs every example and fails if any of them does not.
 *
 * The examples are documentation, and documentation that does not run is worse than none: it
 * is confidently wrong. Every one of them was broken by the 2.0 rewrite — calling `pairWith()`,
 * `Property\Name`, `Facade::for()`, `Number::minOf()` — while the test suite stayed green,
 * because nothing executed them. This is what notices.
 *
 * An example may read `$argv`, so each runs in its own process with no arguments, exactly as a
 * reader would run it.
 */

$examples = glob(__DIR__ . '/../examples/*.php');

if ($examples === false || $examples === []) {
	fwrite(STDERR, "No examples found.\n");

	exit(1);
}

sort($examples);

$failed = [];

foreach ($examples as $example) {
	$name = basename($example);
	$output = [];
	$status = 0;

	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($example) . ' 2>&1', $output, $status);

	printf('  %-6s %s%s', $status === 0 ? 'ok' : 'FAIL', $name, PHP_EOL);

	if ($status !== 0) {
		$failed[$name] = $output;
	}
}

foreach ($failed as $name => $output) {
	printf('%s--- %s ---%s%s%s', PHP_EOL, $name, PHP_EOL, implode(PHP_EOL, array_slice($output, -12)), PHP_EOL);
}

exit($failed === [] ? 0 : 1);
