<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Facade;
use Meraki\Schema\Property;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Scope;
use Meraki\Schema\Comparison;
use InvalidArgumentException;

/**
 * @phpstan-import-type SerializedCondition from Condition
 * @phpstan-type SerializedEquals = SerializedCondition&object{
 * 	type: 'equals',
 * 	target: string,
 * 	expected: mixed,
 * }
 * @implements Condition<SerializedEquals>
 */
final class Equals implements Condition
{
	public readonly string $target;

	public Scope $scope;
	public mixed $expected;

	public function __construct(string $target, mixed $expected)
	{
		$this->target = $target;
		$this->scope = new Scope($target);
		$this->expected = $expected;
	}

	public function matches(array $data, Facade $schema): bool
	{
		$value = $this->scope->resolve($schema)->value;

		if ($value instanceof Property) {
			$value = $value->value;
		}

		return $value === $this->expected;
	}

	public function getScopes(): array
	{
		return [$this->scope];
	}

	/**
	 * @return SerializedEquals
	 */
	public function serialize(): object
	{
		return (object)[
			'type' => 'equals',
			'target' => $this->target,
			'expected' => $this->expected,
		];
	}

	/**
	 * @param SerializedEquals $data
	 */
	public static function deserialize(object $data): static
	{
		if ($data->type !== 'equals') {
			throw new InvalidArgumentException('Invalid serialized condition type: ' . $data->type);
		}

		return new self($data->target, $data->expected);
	}
}
