<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Comparison\Order;
use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use Meraki\Schema\ScopeResolver;
use Meraki\Schema\ValueScope;

/**
 * A comparison that asks *where* a value sits rather than only whether it is the same.
 *
 * The four matchers below it are one method each, because all of them are
 * {@see Comparable::compareTo()} and a question put to the {@see Order} it returns:
 *
 * | Matcher | Holds when |
 * | --- | --- |
 * | {@see IsAtLeast} | `$order->isAtLeast()` |
 * | {@see IsGreaterThan} | `$order->isGreater()` |
 * | {@see IsAtMost} | `$order->isAtMost()` |
 * | {@see IsLessThan} | `$order->isLess()` |
 *
 * That is the whole reason {@see Comparable} is a separate interface from
 * {@see \Meraki\Schema\Comparison\Equality}: the ordering is written once, against the interface,
 * rather than once per field type. Six value types implement it — number, date, date-time, time,
 * duration and money — and adding a seventh gives it these four matchers with no change here.
 *
 * ### Not ordered is not an error at match time
 *
 * A scope that resolved to nothing, or to a value with no order, means the condition **does not
 * hold**. It is not a failure: "is age at least 18" on a request that submitted no age is a
 * question with an answer, and the answer is no.
 *
 * The case worth naming is the one that *does* raise. Two amounts of money in different currencies
 * are not ordered, and {@see \Meraki\Schema\Field\Money\Value::compareTo()} says so rather than
 * inventing a ranking. That exception is allowed through, because the alternative — reading it as
 * "does not hold" — would make an `isAtLeast` on a multi-currency field silently false for every
 * request in the wrong currency. A rule that never fires and never complains is the exact defect
 * {@see Comparison} exists to have fixed.
 *
 * ### A field with no order is refused where the rule is written
 *
 * `when($username)->isAtLeast(3)` reads plausibly and can never hold, because text has no order
 * here. Since a field's value class is knowable without a request — see
 * {@see Field\ValueClass} — that is caught by {@see self::whyItCouldNeverHold()} at
 * {@see Facade::addRule()} rather than by nothing at all.
 *
 * Only for a {@see ValueScope}. A part of a structured value resolves to whatever the value put in
 * it, which the field's own class says nothing about.
 */
abstract class Ordered extends Comparison
{
	/**
	 * @param array<string, mixed> $data
	 */
	final public function matches(array $data, Facade $schema): bool
	{
		$order = $this->orderAgainst($this->expected, $data, $schema);

		return $order !== null && $this->holdsWhen($order);
	}

	/** What this matcher makes of where the value sits. */
	abstract protected function holdsWhen(Order $order): bool;

	/**
	 * Where the scope's value sits relative to one expectation, or null when the two are not
	 * ordered things at all.
	 *
	 * @param array<string, mixed> $data
	 */
	final protected function orderAgainst(mixed $expectation, array $data, Facade $schema): ?Order
	{
		$resolver = new ScopeResolver($schema, $data);
		$value = $resolver->resolve($this->scope);

		if (!$value instanceof Comparable) {
			return null;
		}

		$against = $this->readExpectation($expectation, $schema, $resolver);

		return $against instanceof Comparable ? $value->compareTo($against) : null;
	}

	public function whyItCouldNeverHold(Facade $schema): ?string
	{
		if ($this->scope instanceof ValueScope) {
			$field = $schema->fields->findByName($this->scope->field);
			$valueClass = $field === null ? null : Field\ValueClass::of($field);

			if ($valueClass !== null && !is_a($valueClass, Comparable::class, true)) {
				return sprintf(
					'The rule asks where "%s" sits relative to %s, but what that field holds has no '
					. 'order — so the comparison could never be true and the rule would never fire. '
					. 'Ordering is defined for numbers, dates, times, durations and money.',
					(string) $this->scope,
					self::describe($this->expected),
				);
			}
		}

		return parent::whyItCouldNeverHold($schema);
	}
}
