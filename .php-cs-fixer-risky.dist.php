<?php
declare(strict_types=1);

/**
 * Rules that could change behaviour. This config REPORTS; it never writes.
 *
 * It is only ever invoked with --dry-run, by `composer style:report`, by the pre-commit hook,
 * and by CI. Running it without --dry-run would let a rule rewrite code on the strength of an
 * assumption it cannot check, which is the one thing the split exists to prevent. If you find
 * yourself wanting to, fix the finding by hand instead.
 *
 * Every rule here is zero-churn against the tree as it stands. They are insurance, not cleanup:
 * they report the day somebody writes `sizeof`, `and`, or a `T[]` docblock.
 *
 * It earned its keep immediately. `phpdoc_list_type` found 11 files promising only `array<T>`
 * where the method really does return a list, and reporting rather than rewriting was the right
 * shape: a human read each one and PHPStan confirmed the narrowing before it was committed.
 */

$finder = (require __DIR__ . '/tools/CodeStyle/finder.php')(__DIR__);

return (new PhpCsFixer\Config())
	->setFinder($finder)
	->setIndent("\t")
	->setLineEnding("\n")
	->setRiskyAllowed(true)
	->setRules([
		// Risky because adding declare(strict_types=1) to a file that never had it changes how
		// that file coerces arguments — a behaviour change, not a formatting one. All 235 files
		// have it today, including examples/validate-with-rules.php, where it sits below a file
		// docblock rather than on line 2.
		'declare_strict_types' => true,

		// Risky because a project could define its own function with an alias's name. An alias
		// is a second name for one thing, which is the ambiguity this style set exists to
		// remove: `count` is the name of the thing, `sizeof` is not. Zero today.
		'no_alias_functions' => true,
		'no_alias_language_construct_call' => true,

		// `and`/`or` bind LOOSER than `?:` and `=`, which is the canonical PHP precedence
		// gotcha. Keeping them at zero is also what makes Meraki/parenthesized_ternary_condition
		// provably safe — see that fixer. Zero today.
		'logical_operators' => true,

		// sprintf() with only a format string and nothing to interpolate. Zero today.
		'no_useless_sprintf' => true,

		// Replaces the `T[]` half of tools/check-conventions.php. Reporting rather than fixing
		// is deliberate and is *better* than what that script did: auto-fixing would silently
		// write the weaker array<T>, whereas the old check refused T[] and made a human choose
		// list<T> where the keys really are sequential. A report keeps the human in the loop
		// and shows the diff. Eleven findings on first run, all narrowings, all now applied.
		'phpdoc_array_type' => true,
		'phpdoc_list_type' => true,
	]);
