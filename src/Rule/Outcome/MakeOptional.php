<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Outcome;

use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Scope;
use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use InvalidArgumentException;

/**
 * @phpstan-import-type SerializedOutcome from Outcome
 * @phpstan-type SerializedMakeOptional = SerializedOutcome&object{
 * 	action: 'make_optional',
 * 	field: string
 * }
 * @implements Outcome<SerializedMakeOptional>
 */
final class MakeOptional implements Outcome
{
	private Scope $scope;

	public function __construct(public readonly string $field)
	{
		$this->scope = new Scope($this->field);
	}

	public function apply(Facade $schema): void
	{
		$target = $this->scope->resolve($schema)->value;

		if ($target instanceof Field) {
			$target->makeOptional();

			return;
		}

		throw new InvalidArgumentException("MakeOptional can only be applied to fields.");
	}

	public function getScope(): Scope
	{
		return $this->scope;
	}

	/**
	 * @return SerializedMakeOptional
	 */
	public function serialize(): object
	{
		return (object)[
			'action' => 'make_optional',
			'field' => (string)$this->scope,
		];
	}

	/**
	 * @param SerializedMakeOptional $data
	 */
	public static function deserialize(object $data): static
	{
		if ($data->action !== 'make_optional') {
			throw new InvalidArgumentException('Invalid serialized outcome type: ' . $data->action);
		}

		return new self($data->field);
	}
}
