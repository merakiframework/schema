<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use InvalidArgumentException;
use Throwable;
use Meraki\Schema\Exception;

/**
 * A rule that could not do what it says.
 *
 * Almost every factory here exists for one reason: **a rule that cannot fire raises nothing**, so
 * without a check it is indistinguishable from one whose condition simply never held. A form goes
 * out with a field that was supposed to become required and quietly did not, and the only symptom
 * is data nobody collected.
 *
 * So each of these is raised where the rule is *written* — at `addRule()`, or in a condition's own
 * constructor — rather than on the request where it would have fired. The cost is that rules must
 * be added after the fields they name; the return is that the failure lands next to the line that
 * caused it.
 *
 * {@see InvalidScope} covers the paths a rule addresses; this covers the rule itself.
 */
final class InvalidRule extends InvalidArgumentException implements Exception
{
	// ── conditions that could never hold ────────────────────────────────────

	/**
	 * The general form, written by the condition that knows why — an unreadable bound, a field
	 * with no order. See `Rule\Condition\Comparison::whyItCouldNeverHold()`.
	 */
	public static function because(string $why): self
	{
		return new self($why);
	}

	public static function matchesWasGivenAnInvalidPattern(string $pattern): self
	{
		return new self(sprintf(
			'matches() was given %s, which is not a valid pattern. It takes a PCRE with its '
			. 'delimiters, the same as Text::matching() — "/^INV-/" rather than "^INV-".',
			var_export($pattern, true),
		));
	}

	public static function isInWasGivenNothingToMatch(): self
	{
		return new self(
			'isIn() was given no values to match against, so the rule could never fire. '
			. 'Give it at least one, or remove the rule.',
		);
	}

	public static function containsWasGivenAnEmptyString(): self
	{
		return new self(
			'contains() was given an empty string, which every value contains. '
			. 'Use isNotEmpty() if that is what you meant.',
		);
	}

	// ── outcomes ────────────────────────────────────────────────────────────

	public static function targetsAFieldNotOnTheSchema(string $field): self
	{
		return new self(sprintf(
			'The rule says what happens to "%s", which is not a field on this schema. Add '
			. 'the field before the rule that acts on it.',
			$field,
		));
	}

	public static function outcomeDescribesTwoFields(string $authored, string $modified): self
	{
		return new self(sprintf(
			'A rule outcome must describe one field, but "%s" was compared against "%s".',
			$authored,
			$modified,
		));
	}

	public static function outcomeChangesTheKindOfField(string $field, string $onSchema, string $inRule): self
	{
		return new self(sprintf(
			'"%s" is a %s on the schema and a %s in the rule. A rule changes a field\'s '
			. 'configuration; it cannot change what kind of field it is.',
			$field,
			$onSchema,
			$inRule,
		));
	}

	/**
	 * Identity, not equality: a wither always clones, so the same instance means none was called.
	 */
	public static function outcomeWasHandedAnUnchangedField(string $field): self
	{
		return new self(sprintf(
			'The rule says what happens to "%s" but was handed the field unchanged. Configure '
			. 'it — then($field->makeRequired()) — or drop it from the rule.',
			$field,
		));
	}

	public static function outcomeDoesNotAddressAField(string $outcome, string $path): self
	{
		return new self(sprintf(
			'%s applies to a field, but "%s" addresses something else. Drop the trailing segment.',
			$outcome,
			$path,
		));
	}

	// ── composition ─────────────────────────────────────────────────────────

	public static function combinesFinishedRules(): self
	{
		return new self(
			'allOf()/anyOf() combine conditions, not finished rules. Attach the '
			. 'outcomes to the combined rule instead of to the parts.',
		);
	}

	public static function addressesSomethingTheSchemaCannot(string $target, Throwable $why): self
	{
		return new self(
			sprintf('The rule targets "%s", which this schema cannot address: %s', $target, $why->getMessage()),
			previous: $why,
		);
	}
}
