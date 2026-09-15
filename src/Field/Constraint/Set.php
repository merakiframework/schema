<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Constraint;

use Meraki\Schema\Field\Constraint;
use Meraki\Schema\Field\ConstraintValidationResult;
use Closure;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * The checks a field makes, in the order it makes them.
 *
 * Ordered and unique by name — strictly an ordered set, which is the same shape
 * {@see \Meraki\Schema\Field\Set} and {@see \Meraki\Schema\Rule\Set} hold. Order is kept because a
 * failure report reads in it; uniqueness is enforced because a result is looked up by name.
 *
 * Not a name-keyed array, because a constraint now carries its own name.
 *
 * @implements IteratorAggregate<int, Constraint>
 */
final class Set implements IteratorAggregate, Countable
{
	/** @var list<Constraint> */
	private readonly array $constraints;

	public function __construct(Constraint ...$constraints)
	{
		$seen = [];

		foreach ($constraints as $constraint) {
			if (isset($seen[$constraint->name])) {
				throw new InvalidArgumentException(sprintf(
					'Duplicate constraint "%s": a field reports each name once, so a result can be looked up by it.',
					$constraint->name,
				));
			}

			$seen[$constraint->name] = true;
		}

		$this->constraints = array_values($constraints);
	}


	/**
	 * Every constraint reported as skipped, for when the shape failed and there was nothing
	 * for any of them to speak to.
	 *
	 * @return list<ConstraintValidationResult>
	 */
	public function allSkipped(): array
	{
		return array_map(
			static fn(Constraint $c): ConstraintValidationResult => $c->skipped(),
			$this->constraints,
		);
	}

	/**
	 * @return list<ConstraintValidationResult>
	 */
	public function against(mixed $value): array
	{
		return array_map(
			static fn(Constraint $c): ConstraintValidationResult => $c->against($value),
			$this->constraints,
		);
	}

	/** @return list<string> */
	/**
	 * One constraint by name, for reading the bound a message would interpolate without having
	 * to validate a value first.
	 *
	 * Named differently from {@see \Meraki\Schema\ResolvedField::forConstraint()} on purpose:
	 * this hands back a constraint *definition*, that one a verdict about a value. `named()` also
	 * reads as what it returns — the constraint named X — where the `for*()` methods read as what
	 * they are looking up.
	 */
	public function named(string $name): ?Constraint
	{
		foreach ($this->constraints as $constraint) {
			if ($constraint->name === $name) {
				return $constraint;
			}
		}

		return null;
	}

	/** @var list<string> The name each constraint reports under, in order. */
	public array $names {
		get => array_map(static fn(Constraint $c): string => $c->name, $this->constraints);
	}

	public function getIterator(): Traversable
	{
		yield from $this->constraints;
	}

	public function count(): int
	{
		return count($this->constraints);
	}
}
