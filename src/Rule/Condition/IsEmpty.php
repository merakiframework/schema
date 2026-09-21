<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Facade;

/**
 * Holds when the scope points at nothing.
 *
 * The rule half of "this was left blank", and the one a branching form is usually built on:
 *
 *     $companyName->when()->isEmpty()->then($abn->makeOptional());
 *
 * See {@see Emptiness} for what counts — and in particular for why `false` and `0` do not.
 */
final class IsEmpty extends Emptiness
{
	/**
	 * @param array<string, mixed> $data
	 */
	public function matches(array $data, Facade $schema): bool
	{
		return $this->pointsAtNothing($data, $schema);
	}
}
