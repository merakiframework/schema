<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Attribute;
use Meraki\Schema\SchemaFacade;

class Outcome
{
	private array $otherAttributes;

	public function __construct(
		public Attribute $action,
		public Attribute $target,
		Attribute ...$attrs,
	) {
		$this->otherAttributes = $attrs;
	}

	public static function __callStatic(string $method, array $args): self
	{
		$target = new Attribute('target', array_shift($args));

		if (is_array($args[0] ?? null)) {
			$attributes = array_map(fn(string $attr) => new Attribute($method, $attr), $args);
		} else {
			$attributes = $args;
		}

		return new self(new Attribute('action', $method), $target, ...$attributes);
	}

	public function __toObject(): object
	{
		$object = new \stdClass();
		$object->action = $this->action->value;
		$object->target = $this->target->value;

		foreach ($this->otherAttributes as $attr) {
			$object->{$attr->name} = $attr->value;
		}

		return $object;
	}

	public function __isset(string $name): bool
	{
		foreach ($this->otherAttributes as $attr) {
			if ($attr->hasNameOf($name)) {
				return true;
			}
		}

		return false;
	}

	public function __get(string $name): Attribute
	{
		foreach ($this->otherAttributes as $attr) {
			if ($attr->hasNameOf($name)) {
				return $attr;
			}
		}

		throw new \InvalidArgumentException('Attribute not found: '.$name);
	}

	public function execute(array $data, SchemaFacade $schema): void
	{
		$target = $this->target;
		$action = $this->action;

		switch ($action->value) {
			case 'require':
				$target = $target->getScope()->resolve($schema);
				if (!($target instanceof Field)) {
					throw new \RuntimeException('Cannot require non-field');
				}
				$target->require();
				break;

			case 'set':
				if (!isset($this->to)) {
					throw new \RuntimeException('Missing "to" value for "set" action');
				}
				$scope = $target->getScope();
				$target = $scope->resolveWithBackTracking($schema);
				if (!($target instanceof Field)) {
					throw new \RuntimeException('Cannot set attribute on non-field');
				}
				$attr = $this->attributeFactory->create($scope->getLastSegment(), $this->to->value);
				$target->setAttribute($attr);
				break;

			case 'replace':
				if (!isset($this->with)) {
					throw new \RuntimeException('Missing "with" value for "replace" action');
				}
				$scope = $target->getScope();
				$target = $scope->resolve($schema);
				throw new \RuntimeException('Not implemented yet');
			// break;

			default:
				throw new \RuntimeException('Unknown action: ' . $action->value);
		}
	}
}
