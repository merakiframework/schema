<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Facade;

/**
 * Holds when what the scope points at is not the expected value.
 *
 * Unlike an outcome, this accepts any kind of scope: comparing a submitted value
 * (`#/fields/plan/value`) and comparing part of the definition (`#/fields/age/min`) are
 * both meaningful questions to ask.
 *
 * Exactly the negation of {@see Equals}, sharing its comparison rather than restating it — which
 * is the point of them having a common parent. Written separately, this one was the more dangerous
 * half of the defect {@see Comparison} describes: a `!==` between a parsed value and an unparsed
 * expectation is always *true*, so a `notEquals` rule fired on every request instead of never.
 */
final class NotEquals extends Comparison
{
	/**
	 * @param array<string, mixed> $data
	 */
	public function matches(array $data, Facade $schema): bool
	{
		return !$this->pointsAtTheExpectedValue($data, $schema);
	}
}
