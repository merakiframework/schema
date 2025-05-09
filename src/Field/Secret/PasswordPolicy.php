<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Secret;

use Meraki\Schema\Field\Secret\Policy;
use InvalidArgumentException;

final class PasswordPolicy implements Policy
{
	public const UNRESTRICTED = null;

	private const ALLOWED_CONSTRAINTS = [
		'length',
		'uppercase',
		'lowercase',
		'digits',
		'symbols',
		'anyof',
	];

	public readonly string $name;

	public function __construct(
		public readonly array $length = [],
		public readonly array $uppercase = [],
		public readonly array $lowercase = [],
		public readonly array $digits = [],
		public readonly array $symbols = [],
		public readonly array $anyof = [],
	) {
		$this->name = 'password_policy';

		foreach (['length', 'uppercase', 'lowercase', 'digits', 'symbols'] as $key) {
			$this->validateTuple($this->$key);
		}

		$this->validateAnyOf();
	}

	public static function parse(string $policy): self
	{
		$rules = array_fill_keys(self::ALLOWED_CONSTRAINTS, []);

		foreach (explode(';', $policy) as $constraint) {
			[$key, $range] = explode(':', $constraint, 2) + [1 => []];

			if (!isset($rules[$key]) || $range === []) {
				continue;
			}

			if ($key === 'anyof') {
				$rules[$key] = explode(',', $range);
				continue;
			}

			[$min, $max] = explode(',', $range, 2) + [1 => ''];
			$rules[$key] = [
				$min !== '' ? (int)$min : self::UNRESTRICTED,
				$max !== '' ? (int)$max : self::UNRESTRICTED,
			];
		}

		return new self(
			length: $rules['length'],
			uppercase: $rules['uppercase'],
			lowercase: $rules['lowercase'],
			digits: $rules['digits'],
			symbols: $rules['symbols'],
			anyof: $rules['anyof'],
		);
	}

	public function matches(string $value): bool
	{
		$types = [
			'length' => mb_strlen($value),
			'lowercase' => preg_match_all('/\p{Ll}/u', $value),
			'uppercase' => preg_match_all('/\p{Lu}/u', $value),
			'digits' => preg_match_all('/\p{Nd}/u', $value),
			'symbols' => preg_match_all('/[^\p{L}\p{Nd}]/u', $value),
		];

		$allof = array_diff(self::ALLOWED_CONSTRAINTS, ['anyof', ...$this->anyof]);
		$anyof = $this->anyof;

		if ($anyof !== []) {
			$anyPassed = false;

			foreach ($this->anyof as $key) {
				$constraint = $this->$key;
				$count = $types[$key];

				if ($count === false) {
					$count = 0;
				}

				if ($this->inRange($count, $constraint)) {
					$anyPassed = true;
					break;
				}
			}

			if (!$anyPassed) {
				return false;
			}
		}

		foreach ($allof as $key) {
			$constraint = $this->$key;
			$count = $types[$key];

			if ($count === false) {
				$count = 0;
			}

			if (!$this->inRange($types[$key], $constraint)) {
				return false;
			}
		}

		return true;
	}

	public function __toString(): string
	{
		$rules = [];

		foreach (self::ALLOWED_CONSTRAINTS as $key) {
			$value = $this->$key;

			if ($key === 'anyof') {
				$rules[] = sprintf('%s:%s', $key, implode(',', $value));
				continue;
			}

			if ($value !== []) {
				$rules[] = $this->formatRule($key, $value);
			}
		}

		return implode(';', $rules);
	}

	private function validateTuple(array $tuple): void
	{
		// empty tuple means no restrictions
		if ($tuple === []) {
			return;
		}

		if (count($tuple) !== 2) {
			throw new InvalidArgumentException('Tuple must contain exactly two elements or be empty.');
		}

		[$min, $max] = $tuple;

		if ((!is_int($min) && $min !== self::UNRESTRICTED) || (!is_int($max) && $max !== self::UNRESTRICTED)) {
			throw new InvalidArgumentException('Tuple elements must be integers or null.');
		}

		if (($min !== self::UNRESTRICTED && $min < 0) || ($max !== self::UNRESTRICTED && $max < 0)) {
			throw new InvalidArgumentException('Tuple values must be non-negative.');
		}

		if ($min !== self::UNRESTRICTED && $max !== self::UNRESTRICTED && $min > $max) {
			throw new InvalidArgumentException('Min cannot be greater than max.');
		}
	}

	private function formatRule(string $name, array $range): string
	{
		[$min, $max] = $range;

		return sprintf(
			'%s:%s,%s',
			$name,
			$min !== self::UNRESTRICTED ? $min : '',
			$max !== self::UNRESTRICTED ? $max : '',
		);
	}

	private function inRange(int $count, array $range): bool
	{
		if ($range === []) {
			return true;
		}

		[$min, $max] = $range;

		if ($min !== self::UNRESTRICTED && $count < $min) {
			return false;
		}

		if ($max !== self::UNRESTRICTED && $count > $max) {
			return false;
		}

		return true;
	}

	private function validateAnyOf(): void
	{
		if ($this->anyof === []) {
			return;
		}

		if (count($this->anyof) < 2) {
			throw new InvalidArgumentException('AnyOf group must contain at least two elements.');
		}

		foreach ($this->anyof as $key) {
			if (!in_array($key, ['uppercase', 'lowercase', 'digits', 'symbols'], true)) {
				throw new InvalidArgumentException("Invalid constraint key in 'anyof': $key");
			}
		}
	}

	// --- Presets ---

	public static function strong(): self
	{
		return new self(
			length: [12, self::UNRESTRICTED],
			uppercase: [2, self::UNRESTRICTED],
			lowercase: [2, self::UNRESTRICTED],
			digits: [2, self::UNRESTRICTED],
			symbols: [1, self::UNRESTRICTED],
		);
	}

	public static function moderate(): self
	{
		return new self(
			length: [8, self::UNRESTRICTED],
			uppercase: [1, self::UNRESTRICTED],
			lowercase: [1, self::UNRESTRICTED],
			digits: [1, self::UNRESTRICTED],
			symbols: [1, self::UNRESTRICTED],
		);
	}

	public static function common(): self
	{
		return new self(
			length: [8, self::UNRESTRICTED],
			uppercase: [1, self::UNRESTRICTED],
			lowercase: [1, self::UNRESTRICTED],
			digits: [1, self::UNRESTRICTED],
			symbols: [1, self::UNRESTRICTED],
			anyof: ['digits', 'symbols'],
		);
	}

	public static function weak(): self
	{
		return new self(
			length: [8, self::UNRESTRICTED]
		);
	}

	public static function none(): self
	{
		return new self();
	}
}
