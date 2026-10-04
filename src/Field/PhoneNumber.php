<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AtomicField;
use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\Field\PhoneNumber\Type;
use Meraki\Schema\Field\PhoneNumber\Value;
use Meraki\Schema\FieldName;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\ValueScope;
use libphonenumber\PhoneNumberUtil;

/**
 * A telephone number, validated with libphonenumber.
 *
 * ### A number is submitted with its country, always
 *
 * The same pairing {@see Money} makes between an amount and its currency, and for the same
 * reason: `0411 222 333` means nothing until you know where it is from, and neither does
 * `+15551234567` — the `+1` prefix covers **twenty-five** regions, so E.164 alone cannot tell
 * you whether that is American or Canadian. libphonenumber agrees: asked for the region of a
 * bare `+1` number it answers `null`.
 *
 * ```php
 * ['number' => '0411 222 333', 'country' => 'AU']
 * ```
 *
 * Both halves are required, and each reports itself: `numberRequired` and `countryRequired`
 * name the box that is empty. A bare string is still a shape failure, because a string is not
 * a record and never described a pair at all.
 *
 * The country used to be refused rather than reported, on the grounds that libphonenumber
 * cannot parse a number without a region. That is true and is why the number stays unread, but
 * it is a fact about this library rather than about the submitter: somebody part-way through a
 * form offering several countries has not made a mistake, and "that is not a valid phone
 * number" named neither the problem nor the box.
 *
 * ### A number is whole before it is judged
 *
 * Whether what arrived is a phone number at all — both halves there, a country that is a region,
 * a number that is valid *in it* — is decided by {@see PhoneNumber\Input} before any constraint
 * runs, and no configuration changes it. The constraints below each receive a whole
 * {@see Value}, so they ask only what this field accepts: which countries, and which kind.
 *
 * This replaced an `unambiguous` constraint and a rule that resolved a national number against
 * the allow-list when exactly one country was on it. Both were machinery for guessing what the
 * submitter meant, and asking for the country outright removes the need for either.
 *
 * The country is an ISO 3166-1 alpha-2 code rather than a dialling prefix, because a prefix does
 * not identify a country — `+44` covers four regions, `+61` three, `+7` two — and because it is
 * what libphonenumber parses against, and what a localisation lookup needs.
 *
 * @psalm-type NumberAndCountry = array{number: string, country: string}
 * @extends AtomicField<NumberAndCountry|null>
 */
final readonly class PhoneNumber extends AtomicField
{
	/**
	 * Allowed regions as ISO 3166-1 alpha-2 codes, upper-cased. Empty accepts a number from any
	 * region libphonenumber knows.
	 *
	 * Which countries a field *accepts*, and nothing more: the country a number is read in is
	 * always the one submitted with it.
	 *
	 * @var list<string>
	 */
	public array $allowedCountries;

	public Type $numberType;

	/**
	 * @param list<string> $allowedCountries
	 * @throws InvalidConfiguration if a country is not a region libphonenumber knows
	 */
	public function __construct(
		public FieldName $name,
		array $allowedCountries = [],
	) {
		parent::__construct();

		$this->numberType = self::initially(Type::Any);
		$this->allowedCountries = self::initially(self::supported([], $allowedCountries));

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * Adds to the acceptable countries. Accumulates, like every other `allow*()`.
	 *
	 * @throws InvalidConfiguration if a country is not a region libphonenumber knows
	 */
	public function allowCountries(string $country, string ...$countries): static
	{
		return $this->with(['allowedCountries' => self::supported($this->allowedCountries, [$country, ...$countries])]);
	}

	/**
	 * Accepts a number from any country again.
	 */
	public function clearAllowedCountries(): static
	{
		return $this->with(['allowedCountries' => []]);
	}

	public function ofType(Type $type): static
	{
		return $this->with(['numberType' => $type]);
	}

	/**
	 * What a rule may ask about this field: a number is matched by prefix or pattern, never ranked.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Text
	{
		return new Matcher\Text(ValueScope::of($this->name));
	}

	/**
	 * The record read half by half. Whether the halves make a number is the input's to say, and
	 * the lifecycle's to report — see {@see PhoneNumber\Input}.
	 *
	 * @param NumberAndCountry|Value $value
	 */
	protected function parse(mixed $value): PhoneNumber\Input
	{
		if ($value instanceof Value) {
			return PhoneNumber\Input::of($value);
		}

		// An object is a record; an array is a list. A number and its country are named parts,
		// so they arrive as the former — see Definition::recordIn().
		if (!is_object($value)) {
			throw MalformedValue::of(Value::class, 'a phone number is submitted as a record with a number and a country');
		}

		return new PhoneNumber\Input($value);
	}

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint(PhoneNumber\Check::AllowedCountries, $this->isFromAnAllowedCountry(...), $this->allowedCountries),
			new Constraint(PhoneNumber\Check::NumberType, $this->isAnAllowedType(...), $this->numberType->value),
		);
	}

	protected static function declaredChecks(): array
	{
		return PhoneNumber\Check::cases();
	}

	protected static function declaredParts(): array
	{
		return PhoneNumber\Part::cases();
	}

	private function isFromAnAllowedCountry(Value $phone): ?bool
	{
		return $this->allowedCountries === []
			? null
			: in_array(self::util()->getRegionCodeForNumber($phone->number), $this->allowedCountries, true);
	}

	private function isAnAllowedType(Value $phone): ?bool
	{
		return $this->numberType === Type::Any
			? null
			: $this->numberType->matches(self::util()->getNumberType($phone->number));
	}

	/**
	 * @param list<string> $existing
	 * @param list<string> $additional
	 * @return list<string>
	 * @throws InvalidConfiguration
	 */
	private static function supported(array $existing, array $additional): array
	{
		$known = self::util()->getSupportedRegions();

		foreach ($additional as $country) {
			$country = strtoupper($country);

			if (!in_array($country, $known, true)) {
				throw InvalidConfiguration::regionIsNotSupported($country);
			}

			if (!in_array($country, $existing, true)) {
				$existing[] = $country;
			}
		}

		return $existing;
	}

	private static function util(): PhoneNumberUtil
	{
		return PhoneNumberUtil::getInstance();
	}
}
