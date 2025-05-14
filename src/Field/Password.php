<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field\Value\Range;
use Meraki\Schema\Property;
use InvalidArgumentException;

final class Password extends AtomicField
{
	public Range $length;

	public Range $lowercase;

	public Range $uppercase;

	public Range $digits;

	public Range $symbols;

	/** @var string[] */
	public array $anyOf = [];

	private bool $anyOfPassed = false;

	public function __construct(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	) {
		parent::__construct(new Property\Type('password'), $name, $value, $defaultValue, $optional);

		$this->length = Range::unrestricted();
		$this->lowercase = Range::unrestricted();
		$this->uppercase = Range::unrestricted();
		$this->digits = Range::unrestricted();
		$this->symbols = Range::unrestricted();
	}

	public static function strong(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	): self {
		return (new self($name, $value, $defaultValue, $optional))
			->minLengthOf(12)
			->minNumberOfLowercase(2)
			->minNumberOfUppercase(2)
			->minNumberOfDigits(2)
			->minNumberOfSymbols(1);
	}

	public static function moderate(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	): self {
		return (new self($name, $value, $defaultValue, $optional))
			->minLengthOf(8)
			->minNumberOfLowercase(1)
			->minNumberOfUppercase(1)
			->minNumberOfDigits(1)
			->minNumberOfSymbols(1);
	}

	public static function common(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	): self {
		return (new self($name, $value, $defaultValue, $optional))
			->minLengthOf(6)
			->minNumberOfLowercase(1)
			->minNumberOfUppercase(1)
			->minNumberOfDigits(1)
			->minNumberOfSymbols(1)
			->satisfyAnyOf('digits', 'symbols');
	}

	public static function weak(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	): self {
		return (new self($name, $value, $defaultValue, $optional))->minLengthOf(8);
	}

	public static function none(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	): self {
		return new self($name, $value, $defaultValue, $optional);
	}

	public function minLengthOf(?int $min): self
	{
		$this->length = new Range($min, $this->length->max);

		return $this;
	}

	public function maxLengthOf(?int $max): self
	{
		$this->length = new Range($this->length->min, $max);

		return $this;
	}

	public function minNumberOfLowercase(?int $min): self
	{
		$this->lowercase = new Range($min, $this->lowercase->max);

		return $this;
	}

	public function maxNumberOfLowercase(?int $max): self
	{
		$this->lowercase = new Range($this->lowercase->min, $max);

		return $this;
	}

	public function minNumberOfUppercase(?int $min): self
	{
		$this->uppercase = new Range($min, $this->uppercase->max);

		return $this;
	}

	public function maxNumberOfUppercase(?int $max): self
	{
		$this->uppercase = new Range($this->uppercase->min, $max);

		return $this;
	}

	public function minNumberOfDigits(?int $min): self
	{
		$this->digits = new Range($min, $this->digits->max);

		return $this;
	}

	public function maxNumberOfDigits(?int $max): self
	{
		$this->digits = new Range($this->digits->min, $max);

		return $this;
	}

	public function minNumberOfSymbols(?int $min): self
	{
		$this->symbols = new Range($min, $this->symbols->max);

		return $this;
	}

	public function maxNumberOfSymbols(?int $max): self
	{
		$this->symbols = new Range($this->symbols->min, $max);

		return $this;
	}

	public function satisfyAnyOf(string ...$anyOf): self
	{
		if (count($anyOf) < 2) {
			throw new InvalidArgumentException('AnyOf group must contain at least two elements.');
		}

		foreach ($anyOf as $key) {
			if (!in_array($key, ['length', 'lowercase', 'uppercase', 'digits', 'symbols'], true)) {
				throw new InvalidArgumentException("Invalid constraint key in 'anyof': $key");
			}
		}

		$this->anyOf = $anyOf;

		return $this;
	}

	protected function validateType(mixed $value): bool
	{
		$this->anyOfPassed = false;

		return is_string($value);
	}

	protected function getConstraints(): array
	{
		return [
			'length' => $this->validateLength(...),
			'lowercase' => $this->validateLowercase(...),
			'uppercase' => $this->validateUppercase(...),
			'digits' => $this->validateDigits(...),
			'symbols' => $this->validateSymbols(...),
			'any_of' => $this->validateAnyOf(...),
		];
	}

	private function validateLength(string $value): ?bool
	{
		$result = $this->length->contains(mb_strlen($value));

		if (in_array('length', $this->anyOf, true) && $result) {
			$this->anyOfPassed = true;
		} elseif (in_array('length', $this->anyOf, true) && !$result) {
			$result = null; // If any of the other constraints are passed, we don't want to fail the validation
		}

		return $result;
	}

	private function validateLowercase(string $value): ?bool
	{
		$result = $this->lowercase->contains((int)preg_match_all('/\p{Ll}/u', $value));

		if (in_array('lowercase', $this->anyOf, true) && $result) {
			$this->anyOfPassed = true;
		} elseif (in_array('lowercase', $this->anyOf, true) && !$result) {
			$result = null; // If any of the other constraints are passed, we don't want to fail the validation
		}

		return $result;
	}

	private function validateUppercase(string $value): ?bool
	{
		$result = $this->uppercase->contains((int)preg_match_all('/\p{Lu}/u', $value));

		if (in_array('uppercase', $this->anyOf, true) && $result) {
			$this->anyOfPassed = true;
		} elseif (in_array('uppercase', $this->anyOf, true) && !$result) {
			$result = null; // If any of the other constraints are passed, we don't want to fail the validation
		}

		return $result;
	}

	private function validateDigits(string $value): ?bool
	{
		$result = $this->digits->contains((int)preg_match_all('/\p{Nd}/u', $value));

		if (in_array('digits', $this->anyOf, true) && $result) {
			$this->anyOfPassed = true;
		} elseif (in_array('digits', $this->anyOf, true) && !$result) {
			$result = null; // If any of the other constraints are passed, we don't want to fail the validation
		}

		return $result;
	}

	private function validateSymbols(string $value): ?bool
	{
		$result = $this->symbols->contains((int)preg_match_all('/[^\p{L}\p{Nd}]/u', $value));

		if (in_array('symbols', $this->anyOf, true) && $result) {
			$this->anyOfPassed = true;
		} elseif (in_array('symbols', $this->anyOf, true) && !$result) {
			$result = null; // If any of the other constraints are passed, we don't want to fail the validation
		}

		return $result;
	}

	private function validateAnyOf(string $value): ?bool
	{
		if (empty($this->anyOf)) {
			return null;
		}

		return $this->anyOfPassed;
	}
}
