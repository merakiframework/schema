<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Facade;

/**
 * Holds when what the scope points at is the expected value.
 *
 * Unlike an outcome, this accepts any kind of scope: comparing a submitted value
 * (`#/fields/plan/value`) and comparing part of the definition (`#/fields/age/min`) are
 * both meaningful questions to ask.
 *
 * See {@see Comparison} for how the expectation is read — it goes through the same field the
 * submitted value did, which is what makes `equals(18)` work on a field that parses to a
 * `BigDecimal`.
 */
final class Equals extends Comparison
{
	/**
	 * @param array<string, mixed> $data
	 */
	public function matches(array $data, Facade $schema): bool
	{
		return $this->pointsAtTheExpectedValue($data, $schema);
	}
}
