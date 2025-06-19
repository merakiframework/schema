<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Scope;

/**
 * @phpstan-type SerializedCondition = object{
 * 	type: string,
 * }
 * @template T of SerializedCondition
 */
interface Condition
{
	public function matches(array $data): bool;

	/**
	 * @return array<Scope>
	 */
	public function getScopes(): array;

	/**
	 * @return T
	 */
	public function serialize(): object;

	/**
	 * @param T $data
	 */
	public static function deserialize(object $data): static;
}
