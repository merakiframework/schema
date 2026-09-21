<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Facade;

/**
 * Holds when the scope points at something.
 *
 *     $companyName->when()->isNotEmpty()->then($abn->makeRequired());
 *
 * Exactly the negation of {@see IsEmpty}, sharing its reading of what "nothing" means rather than
 * restating it — the same arrangement as {@see Equals} and {@see NotEquals}, and for the same
 * reason: two definitions of empty would eventually disagree, and a form where a field is required
 * and optional at once is a very confusing thing to debug.
 */
final class IsNotEmpty extends Emptiness
{
	/**
	 * @param array<string, mixed> $data
	 */
	public function matches(array $data, Facade $schema): bool
	{
		return !$this->pointsAtNothing($data, $schema);
	}
}
