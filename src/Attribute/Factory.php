<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;

final class Factory
{
	public static $registeredAttributes = [];

	/**
	 * Create a new attribute factory.
	 *
	 * @param array<string, class-string|callable> $registeredAttributes An array of attribute factories to register.
	 */
	public function __construct(
		array $registeredAttributes = []
	) {
		$this->registerDefaultAttributes();

		foreach ($registeredAttributes as $name => $class) {
			$this->register($name, $class);
		}
	}

	private function registerDefaultAttributes(): void
	{
		$this->register('default_value', Attribute\DefaultValue::class);
		$this->register('max', Attribute\Max::class);
		$this->register('min', Attribute\Min::class);
		$this->register('name', Attribute\Name::class);
		$this->register('one_of', Attribute\OneOf::class);
		$this->register('optional', Attribute\Optional::class);
		$this->register('pattern', Attribute\Pattern::class);
		$this->register('step', Attribute\Step::class);
		$this->register('type', Attribute\Type::class);
		$this->register('value', Attribute\Value::class);
		$this->register('version', Attribute\Version::class);
	}

	public function create(string $name, mixed ...$args): Attribute
	{
		return $this->createFromName($name, ...$args);
	}

	/**
	 * Register a new attribute factory.
	 *
	 * This method will overwrite any existing factory registered with the same name.
	 *
	 * @param string $name The name of the attribute.
	 * @param class-string|callable $class If a class-string is provided, the factory will create
	 * 									   instances of this class and pass value into constructor.
	 * 									   If a callable is provided, it will be called with the
	 * 									   value as the only argument. An instance of the Attribute
	 * 									   interface MUST be returned.
	 */
	public function register(string $name, string|callable $class): void
	{
		if (is_string($class)) {
			$class = fn(mixed $value): Attribute => new $class($value);
		}

		self::$registeredAttributes[$name] = $class;
	}

	public function isRegistered(string $name): bool
	{
		return isset(self::$registeredAttributes[$name]);
	}

	/**
	 * Create an attribute from a name and a value.
	 *
	 * @param string $name The name of the attribute.
	 * @param mixed $value The value to pass to the attribute factory.
	 *
	 * @return Attribute The created attribute.
	 * @throws \RuntimeException If no factory is registered for the given name.
	 */
	public function createFromName(string $name, mixed ...$args): Attribute
	{
		foreach (self::$registeredAttributes as $registeredName => $factory) {
			if ($registeredName === $name) {
				return call_user_func($factory, ...$args);
			}
		}

		throw new \RuntimeException("Could not create attribute {$name}: no factory registered.");
	}
}
