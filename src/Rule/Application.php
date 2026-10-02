<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Field;
use Meraki\Schema\Rule;

/**
 * Runs a set of rules against a set of fields, and reports what they did.
 *
 * The fold itself, lifted out of {@see \Meraki\Schema\Definition} so that it has two callers rather
 * than a copy: a schema applies its rules to its own fields, and a collection applies its *row*
 * rules to a copy of its template, once per row. Both want the same interleaving, the same
 * `applyTo()`, and the same record of what happened — and a second implementation of any of that
 * would be a second place for rule ordering to be subtly wrong.
 *
 * ### Why evaluation and application interleave
 *
 * Order matters. A rule reading `#/fields/x/optional` must see the value as it stands when *that*
 * rule runs, including any change an earlier rule made, so the two cannot happen in separate
 * passes. Rules observably read each other's writes, and that is the documented behaviour rather
 * than an accident of implementation.
 *
 * ### Nothing here is written to the caller's fields
 *
 * An outcome is an operation — `applyTo(Field): Field` — so a modified copy takes the old field's
 * place in a **new** set, and the set handed in is untouched. That is what lets a collection's
 * template resolve every row independently: each row folds over its own copy.
 */
final class Application
{
	/**
	 * @param array<string, mixed> $given what was submitted, under each field's name
	 * @return array{Field\Set, list<AppliedOutcome>} the fields as the rules left them, and why
	 */
	public static function of(Set $rules, Field\Set $fields, array $given): array
	{
		$applied = [];

		foreach ($rules as $rule) {
			foreach ($rule->evaluate($fields, $given) as $outcome) {
				$name = $outcome->outcome->getScope()->field;

				// An outcome is an operation, so it is handed the field as it currently stands and
				// what it returns takes that field's place.
				$fields = $fields->replace($outcome->outcome->applyTo($fields->getByName($name)));

				$applied[] = $outcome;
			}
		}

		return [$fields, $applied];
	}

	/**
	 * The outcomes that landed on one field, in the order they were applied.
	 *
	 * @param list<AppliedOutcome> $applied
	 * @return list<AppliedOutcome>
	 */
	public static function forField(array $applied, string $field): array
	{
		$mine = [];

		foreach ($applied as $outcome) {
			if ((string) $outcome->outcome->getScope()->field === $field) {
				$mine[] = $outcome;
			}
		}

		return $mine;
	}

	/**
	 * Whether a rule said to treat this field as though nothing had been sent.
	 *
	 * Read from the outcomes rather than from a flag on the field, which keeps it a fact about one
	 * request. {@see Outcome\Ignore} is the only outcome that is not a reconfiguration, because
	 * ignoring is about a request rather than about a definition.
	 *
	 * @param list<AppliedOutcome> $applied
	 */
	public static function ignores(array $applied): bool
	{
		foreach ($applied as $outcome) {
			if ($outcome->is(Outcome\Ignore::class)) {
				return true;
			}
		}

		return false;
	}
}
