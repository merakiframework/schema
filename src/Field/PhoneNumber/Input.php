<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\PhoneNumber;

use Meraki\Schema\Exception\BrokenInputContract;
use Meraki\Schema\Field;
use Meraki\Schema\Field\Violation;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * A telephone number as it was submitted, whether or not it is one yet: the number as typed, the
 * country to read it in, and what stops the two making a {@see Value}.
 *
 * Five things decide whether this is a phone number at all, and nothing else:
 *
 * | Code | The part | Wrong when |
 * | --- | --- | --- |
 * | `countryRequired` | country | it was not sent, or sent as `null` |
 * | `knownCountry` | country | it was sent and is not a region libphonenumber knows |
 * | `numberRequired` | number | it was not sent, or sent as `null` |
 * | `numberFormat` | number | it was sent and is not a phone number at all |
 * | `numberInCountry` | number | it is a number, and not one in that country |
 *
 * None of them reads the field's configuration, which is what makes them assembly rather than
 * constraints. Whether this field takes numbers from that country, or of that type, is the
 * field's to say once there is a number to say it about.
 *
 * ### The number waits for its country
 *
 * libphonenumber cannot read a national number without a region, and `+1` alone does not say
 * which of twenty-five regions it is in. So with no country, or one that is not a region, the
 * number is not judged at all: telling somebody to fix a number they typed correctly would be the
 * wrong message, and the country is what is actually in the way. What can be said without a
 * country still is — a number sent blank is wrong whatever country it was meant for.
 */
final readonly class Input implements Field\Input
{
	/** The country to read the number in: ISO 3166-1 alpha-2, upper-cased; `null` when it was not sent or is not a region. */
	public ?string $country;

	/** The number, once it has been read in its country; `null` until it could be. */
	public ?LibPhoneNumber $number;

	/** A phone number, once both halves are sound and agree. */
	public ?Value $value;

	/** @var list<Violation> */
	public array $violations;

	/** @var list<Part> */
	public array $missingParts;

	/**
	 * Takes the record a field takes: a number, and the country to read it in.
	 *
	 * A record with nothing in it is read like any other — both halves missing — though a field
	 * never hands one over: to a field it is nothing submitted.
	 *
	 * @param object{number?: string|null, country?: string|null} $phone
	 * @throws BrokenInputContract if it carries a key a phone number does not have
	 */
	public function __construct(object $phone)
	{
		$parts = get_object_vars($phone);
		$names = array_column(Part::cases(), 'value');
		$unknown = array_diff(array_keys($parts), $names);

		// Raised, not reported: a port still sending `e164` — which was a part until it stopped
		// being one — is a port that needs changing, not a request that needs a message.
		if ($unknown !== []) {
			throw BrokenInputContract::recordHasKeysItDoesNotAccept(Value::class, array_values($unknown), $names);
		}

		$typed = $parts['number'] ?? null;
		$country = $parts['country'] ?? null;
		$violations = [];
		$missing = [];

		if ($country === null) {
			$violations[] = new Violation(Check::CountryRequired, true);
			$missing[] = Part::Country;
		} else {
			$country = self::regionIn($country);
			$violations = $country === null ? [...$violations, new Violation(Check::KnownCountry)] : $violations;
		}

		$number = null;

		if ($typed === null) {
			$violations[] = new Violation(Check::NumberRequired, true);
			$missing[] = Part::Number;
		} elseif (!is_string($typed) || trim($typed) === '') {
			// Sent and holding nothing is not the same as not sent: `''` was a decision somebody
			// made. Wrong in any country, so it needs none to be said.
			$violations[] = new Violation(Check::NumberFormat);
		} elseif ($country !== null) {
			[$number, $problem] = self::numberIn($typed, $country);
			$violations = $problem === null ? $violations : [...$violations, $problem];
		}

		$this->country = $country;
		$this->number = $number;
		$this->violations = $violations;
		$this->missingParts = $missing;
		$this->value = ($number !== null && $country !== null) ? new Value($number, $country) : null;
	}

	/**
	 * The input a whole number would have been read from, so a field handed its own value reads it
	 * the way it reads anything else.
	 */
	public static function of(Value $phone): self
	{
		return new self((object) ['number' => $phone->toE164(), 'country' => $phone->country]);
	}

	/**
	 * The region, upper-cased because ISO 3166-1 defines the codes that way, or null when it is
	 * not one libphonenumber knows. Not trimmed: `' AU '` is not a code.
	 */
	private static function regionIn(mixed $given): ?string
	{
		if (!is_string($given)) {
			return null;
		}

		$region = strtoupper($given);

		return in_array($region, PhoneNumberUtil::getInstance()->getSupportedRegions(), true) ? $region : null;
	}

	/**
	 * The number read in its country, or what is wrong with it.
	 *
	 * Valid **for that region** rather than valid somewhere. It is what stops an Australian number
	 * passing as a New Zealand one, and it settles the international case too: libphonenumber
	 * ignores the region once a number is already E.164, so `+61…` said to be `US` would otherwise
	 * pass with the two halves disagreeing.
	 *
	 * @return array{?LibPhoneNumber, ?Violation}
	 */
	private static function numberIn(string $typed, string $country): array
	{
		$util = PhoneNumberUtil::getInstance();

		try {
			$number = $util->parse($typed, $country);
		} catch (NumberParseException) {
			return [null, new Violation(Check::NumberFormat)];
		}

		return $util->isValidNumberForRegion($number, $country)
			? [$number, null]
			: [null, new Violation(Check::NumberInCountry, $country)];
	}

	/**
	 * The number in E.164 once it has been read — the form it is compared in — and the country.
	 *
	 * @return array<string, mixed>
	 */
	public function parts(): array
	{
		return [
			'number' => $this->number === null ? null : PhoneNumberUtil::getInstance()->format($this->number, PhoneNumberFormat::E164),
			'country' => $this->country,
		];
	}

	/**
	 * Read the way the parts were. A rule written with `'0411 222 333'` is about the number stored
	 * as `+61411222333`, so it is read in this input's country; a rule written `equals('au')` is
	 * about AU. An expectation that cannot be read so is compared as written.
	 */
	public function canonicalPartValue(Field\Part $part, mixed $expected): mixed
	{
		if (!is_string($expected)) {
			return $expected;
		}

		if ($part === Part::Country) {
			return strtoupper($expected);
		}

		if ($part !== Part::Number || $this->country === null) {
			return $expected;
		}

		$util = PhoneNumberUtil::getInstance();

		try {
			return $util->format($util->parse($expected, $this->country), PhoneNumberFormat::E164);
		} catch (NumberParseException) {
			return $expected;
		}
	}
}
