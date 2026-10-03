<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AggregatedValidationResult;

/**
 * The verdicts a field's constraints reached, on their own.
 *
 * One half of a deliberate pair. A {@see \Meraki\Schema\ResolvedField} holds two different kinds
 * of answer — *could this be read at all* and *does it satisfy the rules* — and mixing them into
 * one list makes both harder to ask about: `getFailed()` on the field returns the shape failure
 * alongside the constraint ones, and for a collection it returns failing rows too.
 *
 * So the shape lives on `$shape` and the constraints live here, each with the full aggregate API:
 *
 *     $field->constraints->getFailed()->getFirst();
 *     $field->constraints->allPassed();
 *     foreach ($field->constraints as $verdict) { ... }
 *
 * and the readings almost everyone wants have shorthand on the field itself —
 * {@see \Meraki\Schema\ResolvedField::getFailedConstraints()},
 * {@see \Meraki\Schema\ResolvedField::wasUnreadable()}. Simple where it is simple, richer when it
 * needs to be, and the two are the same data rather than two answers that could disagree.
 *
 * @extends AggregatedValidationResult<ConstraintValidationResult>
 */
final class ConstraintResults extends AggregatedValidationResult
{
	public function __construct(ConstraintValidationResult ...$results)
	{
		parent::__construct(...$results);
	}

	/**
	 * One verdict, by its code — or by the code's wire name, which is what a serialised schema and
	 * a language pack hold.
	 */
	public function named(Check|string $code): ?ConstraintValidationResult
	{
		$name = $code instanceof Check ? (string) $code->value : $code;

		foreach ($this->results as $result) {
			if ($result->name === $name) {
				return $result;
			}
		}

		return null;
	}

	/**
	 * The name each one reported under, in order.
	 *
	 * @var list<string>
	 */
	public array $names {
		get => array_map(static fn(ConstraintValidationResult $r): string => $r->name, $this->results);
	}
}
