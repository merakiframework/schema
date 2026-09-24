<?php
declare(strict_types=1);

/**
 * Formatting, fixed automatically. Only rules that cannot change behaviour live here; see
 * .php-cs-fixer-risky.dist.php for the ones that report instead.
 *
 * ## Why this exists, when tools/check-conventions.php argued against it
 *
 * That script's header made the case for not taking a coding-standard package: it was "not
 * worth ~15 transitive dependencies to answer two questions". That was right while the
 * questions numbered two. It is not right now — the list below encodes about thirty
 * conventions, and three of them (tab indentation, declare(strict_types=1) on line 2, and
 * parenthesised compound ternary conditions) are load-bearing enough that finding out about
 * a violation in review rather than in the editor is the wrong order.
 *
 * The dependency cost went up, not down: PHP-CS-Fixer brings 34 packages, mostly symfony/*.
 * What it buys is one command that *fixes* rather than reports, which is the difference that
 * changed the answer.
 *
 * ## Rules that must never be enabled here
 *
 * Someone will eventually try to "upgrade" this to @Symfony or @PER-CS. These are the
 * landmines, each with the reason it is one *here*:
 *
 * - no_unneeded_control_parentheses — it strips parentheses this style deliberately keeps.
 *   `return ($v);` becomes `return $v;`. The whole point of the ternary rule below is that a
 *   parenthesis is for the reader, not the parser. (Tested on v3.95.27: it is clone-with
 *   aware and leaves `clone($this, $changes)` alone, and it does not touch ternary-condition
 *   parentheses. So it is not *dangerous* — it is just pulling the other way.)
 *
 * - no_useless_concat_operator — collapses the 36 leading-dot continuation sites in
 *   src/Exception/ into single lines well over 110 columns. Leading-dot continuation is line
 *   wrapping, not concatenation, and is deliberate.
 *
 * - strict_comparison — rewrites the `==` in tests/Field/MoneyTest.php, which is there on
 *   purpose and says so in its own assertion message. The test would still pass, having lost
 *   the thing it asserts.
 *
 * - native_function_invocation — prefixes every builtin with `\` for opcode-cache dispatch.
 *   That is exactly the kind of performance-for-readability trade this style rejects.
 *
 * - ordered_imports — sorts the whole block flat and destroys the tier order that
 *   Meraki/grouped_imports exists to impose.
 *
 * - binary_operator_spaces with its default config — collapses the deliberately aligned `=>`
 *   tables in the test data providers. See the configured entry below.
 *
 * - ordered_class_elements — member order here is deliberate and documented; Field's
 *   $constraints must be assigned last.
 *
 * - no_superfluous_phpdoc_tags, phpdoc_summary, phpdoc_separation, phpdoc_align, phpdoc_order
 *   — the docblocks are long-form prose carrying design rationale. These reflow and delete it.
 */

$finder = (require __DIR__ . '/tools/CodeStyle/finder.php')(__DIR__);

return (new PhpCsFixer\Config())
	->setFinder($finder)
	->setIndent("\t")
	->setLineEnding("\n")
	// Everything that could change behaviour reports instead. See the risky config.
	->setRiskyAllowed(false)
	->setRules([
		'@PSR12' => true,

		// ── PSR-12 overrides ────────────────────────────────────────────────────────────
		// PSR-12 wants a blank line between <?php and declare(). All 212 src and tests files
		// put declare(strict_types=1) on line 2 instead. Disabling only stops the rule
		// inserting one; Meraki/no_blank_line_after_opening_tag removes the 10 in examples/.
		'blank_line_after_opening_tag' => false,

		// PSR-12's default explodes 38 deliberately "hugged" call sites of the shape
		// `throw X::of(self::class, sprintf(` where sprintf's own arguments are multiline.
		// 'ignore' leaves the shape alone and still normalises comma spacing.
		'method_argument_space' => ['on_multiline' => 'ignore'],

		// PSR-12 writes `fn (`. Every one of the 67 arrow functions here writes `fn(`.
		'function_declaration' => ['closure_fn_spacing' => 'none'],

		// ── Imports ─────────────────────────────────────────────────────────────────────
		// Replaces the unused-import half of tools/check-conventions.php. Note it is
		// stricter than that script was: it also removes same-namespace imports, which the
		// old check counted as "used" because the short name appeared elsewhere in the file.
		'no_unused_imports' => true,
		'single_import_per_statement' => true,

		// ── Strings ─────────────────────────────────────────────────────────────────────
		// Zero-churn today, and that is the point: all 44 interpolations already use the
		// braced {$x} form. These make "$x" and the 8.2-deprecated "${x}" unwritable.
		'explicit_string_variable' => true,
		'simple_to_complex_string_variable' => true,
		'single_quote' => true,
		// Verified against the leading-dot continuation sites: a dot that already has a
		// newline around it is left alone, so src/Exception/ is untouched.
		'concat_space' => ['spacing' => 'one'],

		// ── Ambiguity ───────────────────────────────────────────────────────────────────
		// '=>' => null preserves the aligned tables in the test data providers. A table
		// should line up; everything else gets one space.
		'binary_operator_spaces' => ['default' => 'single_space', 'operators' => ['=>' => null]],
		'trailing_comma_in_multiline' => true,

		// ── Whitespace ──────────────────────────────────────────────────────────────────
		'indentation_type' => true,
		'line_ending' => true,
		'phpdoc_indent' => true,
		'single_blank_line_at_eof' => true,
	]);
