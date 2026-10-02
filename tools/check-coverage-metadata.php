<?php
declare(strict_types=1);

/**
 * Does every `#[CoversClass]` in tests/ name a type that exists in src/?
 *
 * PHPUnit does not say when one does not. It attributes nothing and carries on, so the class
 * reads as untested in the coverage report while the attribute sits there looking like proof
 * that somebody tested it. Both halves of that are wrong and neither is visible.
 *
 * The failure is easy to write and impossible to spot by eye, because `#[CoversClass]` resolves
 * against the *test file's* namespace. In `tests/Rule/ScopeValidationTest.php`, which is
 * `namespace Meraki\Schema\Rule`, writing `Rule\Guards::class` means
 * `Meraki\Schema\Rule\Rule\Guards`. That is exactly how four attributes were added here and
 * silently did nothing until this script was written to find them.
 *
 * The resolution rules are PHP's own: a leading `\` is absolute, a first segment matching an
 * import uses that import, and anything else is relative to the file's namespace.
 *
 * This is deliberately a source scan rather than reflection. Autoloading every candidate to ask
 * `class_exists()` re-enters Composer for names that differ only in case, which on a
 * case-insensitive filesystem re-includes a file that is already loaded and kills the process.
 */

chdir(__DIR__ . '/..');

/**
 * Every type declared under a directory, by fully-qualified name.
 *
 * @return array<string, true>
 */
function declaredTypesIn(string $directory): array
{
	$known = [];
	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
	);

	foreach ($files as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}

		$source = file_get_contents($file->getPathname());

		if (!preg_match('/^namespace ([^;]+);/m', $source, $namespace)) {
			continue;
		}

		if (preg_match('/^(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $source, $type)) {
			$known[$namespace[1] . '\\' . $type[1]] = true;
		}
	}

	return $known;
}

/**
 * What a class reference in a file resolves to, by PHP's rules.
 *
 * @param array<string, string> $imports last segment => fully-qualified name
 */
function resolve(string $reference, string $namespace, array $imports): string
{
	if (str_starts_with($reference, '\\')) {
		return ltrim($reference, '\\');
	}

	$head = explode('\\', $reference)[0];

	return isset($imports[$head])
		? $imports[$head] . substr($reference, strlen($head))
		: $namespace . '\\' . $reference;
}

$known = declaredTypesIn('src');
$checked = 0;
$bad = [];

$files = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator('tests', FilesystemIterator::SKIP_DOTS),
);

foreach ($files as $file) {
	if ($file->getExtension() !== 'php') {
		continue;
	}

	$source = file_get_contents($file->getPathname());

	if (!preg_match_all('/#\[CoversClass\(([^)]+)::class\)\]/', $source, $matches)) {
		continue;
	}

	preg_match('/^namespace ([^;]+);/m', $source, $namespace);
	$imports = [];

	if (preg_match_all('/^use ([^;]+);/m', $source, $used)) {
		foreach ($used[1] as $import) {
			$import = trim($import);
			$segments = explode('\\', $import);
			$imports[end($segments)] = $import;
		}
	}

	foreach ($matches[1] as $reference) {
		$checked++;
		$resolved = resolve(trim($reference), $namespace[1] ?? '', $imports);

		if (!isset($known[$resolved])) {
			$bad[] = sprintf(
				'%s: #[CoversClass(%s::class)] means %s, which does not exist',
				$file->getPathname(),
				trim($reference),
				$resolved,
			);
		}
	}
}

if ($bad === []) {
	echo "  every #[CoversClass] in tests/ names a type that exists ({$checked} checked)\n";

	exit(0);
}

echo '  ' . count($bad) . " #[CoversClass] attribute(s) naming nothing:\n";

foreach ($bad as $line) {
	echo '    ' . $line . "\n";
}

exit(1);
