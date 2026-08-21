<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field\PhoneNumber\Type;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;
use InvalidArgumentException;

/**
 * A "phone number" field, validated with libphonenumber.
 *
 * With no allowed countries it accepts any valid international (E.164, '+'-prefixed)
 * number. Once one or more countries are allowed it additionally accepts those
 * countries' national/local format (e.g. AU '0412 345 678'), and restricts which
 * countries a number may belong to. An optional number-type restriction (mobile,
 * landline, or either) can be applied on top.
 *
 * @extends AtomicField<string|null, object>
 */
final class PhoneNumber extends AtomicField
{
	/**
	 * Allowed regions as ISO 3166-1 alpha-2 codes, upper-cased. Empty means
	 * international-only (no national/local parsing).
	 *
	 * @var array<string>
	 */
	public array $allowed = [];

	public Type $allowedType = Type::Any;

	public function __construct(
		Property\Name $name,
		array $allowedCountries = [],
	) {
		parent::__construct($name);

		$this->allow(...$allowedCountries);
	}

	public function allow(string ...$countries): self
	{
		$supported = self::util()->getSupportedRegions();

		foreach ($countries as $country) {
			$country = strtoupper($country);

			if (!in_array($country, $supported, true)) {
				throw new InvalidArgumentException("Country '{$country}' is not a supported region.");
			}

			if (!in_array($country, $this->allowed, true)) {
				$this->allowed[] = $country;
			}
		}

		return $this;
	}

	public function ofType(Type $type): self
	{
		$this->allowedType = $type;

		return $this;
	}

	public function validateValue(mixed $value): bool
	{
		return is_string($value) && $this->parse($value) !== null;
	}

	protected function getConstraints(): array
	{
		return [
			'allowedCountries' => $this->validateAllowedCountries(...),
			'numberType' => $this->validateNumberType(...),
		];
	}

	private function validateAllowedCountries(mixed $value): ?bool
	{
		if ($this->allowed === []) {
			return null;
		}

		$proto = is_string($value) ? $this->parse($value) : null;

		if ($proto === null) {
			return null;
		}

		return in_array(self::util()->getRegionCodeForNumber($proto), $this->allowed, true);
	}

	private function validateNumberType(mixed $value): ?bool
	{
		if ($this->allowedType === Type::Any) {
			return null;
		}

		$proto = is_string($value) ? $this->parse($value) : null;

		if ($proto === null) {
			return null;
		}

		return $this->allowedType->matches(self::util()->getNumberType($proto));
	}

	/**
	 * Parses the value into a valid number, or null if it is not a valid phone
	 * number. International ('+') numbers are parsed region-agnostically; otherwise
	 * each allowed region is tried (so local format only works once countries are
	 * configured).
	 */
	private function parse(string $value): ?LibPhoneNumber
	{
		$util = self::util();
		$value = trim($value);

		try {
			if (str_starts_with($value, '+')) {
				$proto = $util->parse($value, null);

				return $util->isValidNumber($proto) ? $proto : null;
			}

			foreach ($this->allowed as $region) {
				$proto = $util->parse($value, $region);

				if ($util->isValidNumberForRegion($proto, $region)) {
					return $proto;
				}
			}
		} catch (NumberParseException) {
			return null;
		}

		return null;
	}

	private static function util(): PhoneNumberUtil
	{
		return PhoneNumberUtil::getInstance();
	}
}
