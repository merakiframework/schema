<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Secret;

use InvalidArgumentException;

final class PolicyParser
{
	public function __construct(private array $schema, private array $values)
	{
	}

	public static function parse(string $spec, array $schema): self
	{
		$values = [];

		foreach (explode(';', $spec) as $entry) {
			if (trim($entry) === '') {
				continue;
			}

			[$key, $raw] = explode(':', $entry, 2) + [1 => null];

			if (!isset($schema[$key])) {
				throw new InvalidArgumentException("Unknown key '$key' in spec.");
			}

			$type = $schema[$key]['type'];
			$default = $schema[$key]['default'] ?? null;
			$itemType = $schema[$key]['item_type'] ?? null;
			$parsed = self::parseValue($type, $raw, $default, $itemType);
			$values[$key] = $parsed;
		}

		// Fill in any missing values with defaults
		foreach ($schema as $key => $def) {
			if (!array_key_exists($key, $values)) {
				$values[$key] = $def['default'] ?? null;
			}
		}

		return new self($schema, $values);
	}

	public function get(string $key): mixed
	{
		return $this->values[$key] ?? throw new InvalidArgumentException("Key '$key' not found.");
	}

	public function toArray(): array
	{
		return $this->values;
	}

	public function __toString(): string
	{
		$parts = [];

		foreach ($this->schema as $key => $def) {
			$val = $this->values[$key] ?? $def['default'] ?? null;
			$parts[] = $key . ':' . self::serializeValue($def['type'], $val);
		}

		return implode(';', $parts);
	}

	// --- Internal Helpers ---

	private static function parseValue(string $type, ?string $raw, mixed $default, ?string $itemType = null): mixed
	{
		if ($raw === '' || $raw === null) {
			return $default;
		}

		return match ($type) {
			'int' => self::parseInt($raw),
			'string' => $raw,
			'range' => self::parseRange($raw),
			'list' => self::parseList($raw, $itemType),
			default => throw new InvalidArgumentException("Unsupported type '$type'."),
		};
	}

	private static function serializeValue(string $type, mixed $value): string
	{
		return match ($type) {
			'int' => (string) $value,
			'string' => $value,
			'range' => implode(',', array_map(fn($v) => $v ?? '', $value)),
			'list' => implode(',', $value),
			default => '',
		};
	}

	private static function parseInt(string $raw): int
	{
		if (!ctype_digit($raw)) {
			throw new InvalidArgumentException("Invalid integer: '$raw'");
		}

		return (int)$raw;
	}

	private static function parseRange(string $raw): array
	{
		[$min, $max] = explode(',', $raw, 2) + ['', ''];

		if ($min === '') {
			$min = null;
		}

		if ($max === '') {
			$max = null;
		}

		// make sure $min and $max are integers or null
		if ($min !== null && !ctype_digit($min)) {
			throw new InvalidArgumentException("Invalid range: '$min' is not a valid integer");
		}

		if ($max !== null && !ctype_digit($max)) {
			throw new InvalidArgumentException("Invalid range: '$max' is not a valid integer");
		}

		$min = $min !== null ? (int)$min : null;
		$max = $max !== null ? (int)$max : null;

		if ($min !== null && $max !== null && $min > $max) {
			throw new InvalidArgumentException("Invalid range: min ($min) cannot be greater than max ($max)");
		}

		return [$min, $max];
	}

	private static function parseList(string $raw, ?string $itemType): array
	{
		$items = array_map('trim', explode(',', $raw));

		foreach ($items as $item) {
			if ($itemType === 'int' && !ctype_digit($item)) {
				throw new InvalidArgumentException("List contains non-integer: '$item'");
			}

			if ($itemType === 'string' && !is_string($item)) {
				throw new InvalidArgumentException("List contains non-string item");
			}
		}

		return match ($itemType) {
			'int' => array_map('intval', $items),
			'string' => $items,
			null => $items, // no enforcement
			default => throw new InvalidArgumentException("Unsupported list item type '$itemType'"),
		};
	}
}
