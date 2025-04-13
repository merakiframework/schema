<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Exception\InputTypeConversionFailed;
use Meraki\Schema\Exception\InputTypeNotRegistered;
use InvalidArgumentException;

class InputTypeConverter
{
	/** @var array<string, callable> */
	private array $casters = [];

	public function __construct(
		private readonly bool $defaultForceNullIfMissing = true
	) {
		$this->registerType('string', $this->castToString(...));
		$this->registerType('int', $this->castToInteger(...));
		$this->registerType('integer', $this->castToInteger(...));
		$this->registerType('float', $this->castToFloat(...));
		$this->registerType('bool', $this->castToBoolean(...));
		$this->registerType('boolean', $this->castToBoolean(...));
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, string|array{type: string, forceNullIfMissing?: bool}> $types
	 * @return array<string, mixed>
	 * @throws InputTypeConversionFailed when a value cannot be cast to the correct type.
	 * @throws InputTypeNotRegistered when trying to cast to a type that has not been registered.
	 */
	public function convert(array $input, array $types): array
	{
		$result = [];

		foreach ($types as $key => $definition) {
			$type = is_array($definition) ? $definition['type'] ?? null : $definition;
			$forceNull = is_array($definition) && array_key_exists('forceNullIfMissing', $definition)
				? $definition['forceNullIfMissing']
				: $this->defaultForceNullIfMissing;
			$value = $this->getValue($input, $key);

			if ($value === null && !$this->hasKey($input, $key)) {
				if ($forceNull) {
					$this->setValue($result, $key, null);
				}

				continue;
			}

			$this->assertTypeRegistered($type, $key);

			try {
				$converted = $this->casters[$type]($value);
				$this->setValue($result, $key, $converted);
			} catch (\Throwable $e) {
				throw new InputTypeConversionFailed($key, $type, $value, $e);
			}
		}

		return $result;
	}

	private function assertTypeRegistered(string $typeName, string $fieldName): void
	{
		foreach (array_keys($this->casters) as $registeredType) {
			if ($typeName === $registeredType) {
				return;
			}
		}

		throw new InputTypeNotRegistered($fieldName, $typeName);
	}

	/**
	 * @template T
	 * @param string $typeName
	 * @param callable(mixed): T $caster
	 */
	public function registerType(string $typeName, callable $caster): void
	{
		$this->casters[$typeName] = $caster;
	}

	private function castToBoolean(mixed $value): bool
	{
		return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
			?? throw new InvalidArgumentException('Could not cast to boolean.');
	}

	private function castToString(mixed $value): string
	{
		return is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))
			? (string) $value
			: throw new InvalidArgumentException('Could not cast to string.');
	}

	private function castToInteger(mixed $value): int
	{
		return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
			?? throw new InvalidArgumentException('Could not cast to integer.');
	}

	public function castToFloat(mixed $value): float
	{
		return filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
			?? throw new InvalidArgumentException('Could not cast to float.');
	}

	private function hasKey(array $data, string $path): bool
	{
		$segments = explode('.', $path);

		foreach ($segments as $segment) {
			if (!is_array($data) || !array_key_exists($segment, $data)) {
				return false;
			}

			$data = $data[$segment];
		}

		return true;
	}

	private function getValue(array $data, string $path): mixed
	{
		foreach (explode('.', $path) as $segment) {
			if (!is_array($data) || !array_key_exists($segment, $data)) {
				return null;
			}

			$data = $data[$segment];
		}

		return $data;
	}

	private function setValue(array &$target, string $path, mixed $value): void
	{
		$segments = explode('.', $path);
		$ref = &$target;

		foreach ($segments as $segment) {
			if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
				$ref[$segment] = [];
			}

			$ref = &$ref[$segment];
		}

		$ref = $value;
	}
}
