<?php
declare(strict_types=1);

/**
 * Two conventions that nothing else in CI enforces.
 *
 * PHPStan does not check either of these, at any level, and that is not an oversight on its part:
 * an unused import is not unsound, and `string[]` is a type it understands perfectly well. They are
 * house style, and house style with no check behind it is a preference somebody will drift from.
 *
 * The alternative was a coding-standard package — `slevomat/coding-standard` has sniffs for both —
 * and it was not worth ~15 transitive dependencies to answer two questions. `tools/` already holds
 * three checks of this shape.
 *
 * ## Unused imports
 *
 * They accumulate wherever code moves, which in a rewrite is everywhere. Twenty-four had built up
 * by the time this was written, most of them left behind when a method was deleted or a
 * responsibility moved into a value object. None of them broke anything — which is the problem.
 * An import is a claim that a file depends on something, and a stale one sends the next reader to
 * a class that has nothing to do with what they are reading.
 *
 * ## `Type[]` versus `list<Type>`
 *
 * Both are valid and PHPStan reads both. They are not the same thing, though: `Type[]` says
 * "an array of Type" and nothing about the keys, while `list<Type>` promises sequential integer
 * keys from zero. Everything here that returns a list really does, so writing the weaker one is
 * throwing away a guarantee the code already keeps.
 */

chdir(__DIR__ . '/..');

/** Directories checked, in the order they are reported. */
const ROOTS = ['src', 'tests', 'tools', 'examples', 'bin'];

$problems = [];

foreach (ROOTS as $root) {
    if (!is_dir($root)) {
        continue;
    }

    foreach (phpFilesIn($root) as $path) {
        $source = file_get_contents($path);

        if ($source === false) {
            continue;
        }

        foreach (unusedImportsIn($source) as $import) {
            $problems[] = sprintf('%s: unused import — use %s;', $path, $import);
        }

        foreach (arrayShorthandIn($source) as $line => $tag) {
            $problems[] = sprintf('%s:%d: %s — write list<T> or array<K, V> instead', $path, $line, $tag);
        }
    }
}

if ($problems === []) {
    echo "  imports are used and array types are spelled out\n";

    exit(0);
}

echo '  ' . count($problems) . " convention problem(s):\n";

foreach ($problems as $problem) {
    echo '    ' . $problem . "\n";
}

exit(1);

/**
 * @return list<string>
 */
function phpFilesIn(string $root): array
{
    $paths = [];
    $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($walk as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $paths[] = str_replace('\\', '/', $file->getPathname());
        }
    }

    sort($paths);

    return $paths;
}

/**
 * Imports whose short name never appears again in the file.
 *
 * Token-based rather than regex, because three things here are not imports and look like one: a
 * closure's `use (...)`, a trait's `use` inside a class body, and a group `use A\B\{C, D};` whose
 * members this does not split. A name counts as used if it appears as an identifier *or* inside a
 * comment, so a class referenced only by `{@see}` or `@param` keeps its import.
 *
 * @return list<string> the full path of each unused import
 */
function unusedImportsIn(string $source): array
{
    $tokens = token_get_all($source);
    $imports = [];
    $used = [];
    $inUse = false;
    $grouped = false;
    $depth = 0;
    $alias = null;
    $name = '';

    foreach ($tokens as $i => $token) {
        if (is_string($token)) {
            if ($token === '{') {
                $depth++;

                if ($inUse) {
                    $grouped = true;
                }
            }

            if ($token === '}') {
                $depth--;
            }

            if ($inUse && $token === ';') {
                $at = strrpos($name, '\\');
                $short = $alias ?? ($at === false ? $name : substr($name, $at + 1));

                if ($short !== '' && !$grouped) {
                    $imports[$short] = trim($name);
                }

                $inUse = false;
                $grouped = false;
                $alias = null;
                $name = '';
            }

            continue;
        }

        [$id, $text] = $token;

        if ($id === T_USE) {
            $next = $tokens[$i + 2] ?? null;
            $inUse = $depth === 0 && !(is_string($next) && $next === '(');
            $grouped = false;
            $alias = null;
            $name = '';

            continue;
        }

        if ($inUse) {
            if ($id === T_AS) {
                $alias = '';
            } elseif ($id === T_STRING || $id === T_NAME_QUALIFIED || $id === T_NAME_FULLY_QUALIFIED) {
                if ($alias === '') {
                    $alias = $text;
                } else {
                    $name .= $text;
                }
            }

            continue;
        }

        if ($id === T_STRING || $id === T_NAME_QUALIFIED || $id === T_NAME_FULLY_QUALIFIED) {
            $used[explode('\\', ltrim($text, '\\'))[0]] = true;

            continue;
        }

        if ($id === T_DOC_COMMENT || $id === T_COMMENT || $id === T_CONSTANT_ENCAPSED_STRING) {
            preg_match_all('/[A-Za-z_][A-Za-z0-9_]*/', $text, $words);

            foreach ($words[0] as $word) {
                $used[$word] = true;
            }
        }
    }

    return array_values(array_diff_key($imports, $used));
}

/**
 * @return array<int, string> line number => the offending tag
 */
function arrayShorthandIn(string $source): array
{
    $found = [];

    foreach (explode("\n", str_replace("\r\n", "\n", $source)) as $index => $line) {
        if (preg_match('/@(param|return|var)\s+\S*\[\]/', $line, $match) === 1) {
            $found[$index + 1] = trim($match[0]);
        }
    }

    return $found;
}
