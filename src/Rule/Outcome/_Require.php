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
 * @phpstan-type SerializedRequire = SerializedOutcome&object{
 * 	action: 'require',
 * 	field: string
 * }
 * @implements Outcome<SerializedRequire>
 */
final class _Require implements Outcome
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
			$target->require();

			return;
		}

		throw new InvalidArgumentException("Require can only be applied to fields.");
	}

	public function getScope(): Scope
	{
		return $this->scope;
	}

	/**
	 * @return SerializedRequire
	 */
	public function serialize(): object
	{
		return (object) [
			'action' => 'require',
			'field' => (string) $this->scope,
		];
	}

	/**
	 * @param SerializedRequire $data
	 */
	public static function deserialize(object $data): static
	{
		if ($data->action !== 'require') {
			throw new InvalidArgumentException('Invalid serialized outcome type: ' . $data->action);
		}

		return new self($data->field);
	}
}
