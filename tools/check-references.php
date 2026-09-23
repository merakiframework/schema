<?php
declare(strict_types=1);

/**
 * Every {@see ...} in src/, checked against what actually exists.
 *
 * This is the rot that keeps appearing, because nothing notices it: an IDE renders a dead link
 * silently, and a test suite has no opinion about comments. Five had accumulated by the time this
 * was written.
 *
 * The comments in this library carry the reasoning, which is the expensive part to reconstruct. A
 * dead link erodes trust in all of them, so this runs in CI.
 */

require __DIR__ . '/../vendor/autoload.php';

chdir(__DIR__ . '/..');

$files = array_merge(
    glob('src/*.php'),
    glob('src/*/*.php'),
    glob('src/*/*/*.php'),
);

$bad = [];

foreach ($files as $path) {
    $source = file_get_contents($path);

    // The file's own namespace and imports, so a relative reference can be resolved.
    preg_match('/^namespace\s+([^;]+);/m', $source, $ns);
    $namespace = $ns[1] ?? '';

    preg_match_all('/^use\s+([^;]+);/m', $source, $uses);
    $imports = [];

    foreach ($uses[1] as $use) {
        $use = trim($use);

        if (str_contains($use, ' as ')) {
            [$fqcn, $alias] = array_map('trim', explode(' as ', $use));
            $imports[$alias] = $fqcn;

            continue;
        }

        $imports[substr($use, strrpos($use, '\\') + 1)] = $use;
    }

    preg_match_all('/\{@see\s+([^}\s]+)/', $source, $refs);

    foreach ($refs[1] as $ref) {
        // Split a member off: Class::method(), Class::$prop, self::CONST
        $member = null;
        $class = $ref;

        if (str_contains($ref, '::')) {
            [$class, $member] = explode('::', $ref, 2);
        }

        $member = $member === null ? null : rtrim($member, '()');

        // Resolve the class name.
        if ($class === '' || $class === 'self' || $class === 'static') {
            preg_match('/^(?:final\s+|abstract\s+)?(?:readonly\s+)?(?:class|interface|trait|enum)\s+(\w+)/m', $source, $own);
            $resolved = $namespace . '\\' . ($own[1] ?? '');
        } elseif (str_starts_with($class, '\\')) {
            $resolved = ltrim($class, '\\');
        } elseif (isset($imports[strtok($class, '\\')])) {
            $head = strtok($class, '\\');
            $rest = substr($class, strlen($head));
            $resolved = $imports[$head] . $rest;
        } else {
            $resolved = $namespace . '\\' . $class;
        }

        $exists = class_exists($resolved) || interface_exists($resolved) || trait_exists($resolved) || enum_exists($resolved);

        if (!$exists) {
            // A reference to a class in another package, or plain prose, is not this check's business.
            if (!str_starts_with($resolved, 'Meraki\\')) {
                continue;
            }

            $bad[] = sprintf('%s: {@see %s} -> class %s not found', $path, $ref, $resolved);

            continue;
        }

        if ($member === null) {
            continue;
        }

        $has = method_exists($resolved, $member)
            || property_exists($resolved, ltrim($member, '$'))
            || defined($resolved . '::' . $member);

        if (!$has) {
            $bad[] = sprintf('%s: {@see %s} -> %s has no %s', $path, $ref, $resolved, $member);
        }
    }
}

if ($bad === []) {
    echo "  every {@see} in src/ resolves\n";

    exit(0);
}

echo "  " . count($bad) . " dead reference(s):\n";

foreach ($bad as $line) {
    echo "    " . $line . "\n";
}

exit(1);
