<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\PhoneNumber;

use libphonenumber\NumberParseException;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\HasParts;
use Meraki\Schema\Field\ParsedValue;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * One telephone number, as this library compares it.
 *
 * {@see ParsedValue} but deliberately **not** {@see \Meraki\Schema\Comparison\Comparable}: phone numbers
 * have no order. One is not before another, and sorting them by their digits would be inventing a
 * relationship nobody asked for.
 *
 * ### Why it cannot be left as libphonenumber's object
 *
 * `libphonenumber\PhoneNumber` carries the *raw input* alongside the parsed number, plus flags for
 * which optional pieces were present. So `==` says `0411 222 333` and `+61411222333` are different
 * numbers, when they are one number written two ways — and that is the comparison a collection of
 * contacts, or a rule matching on a number, actually wants.
 *
 * E.164 is the canonical form and the only one that settles it: it is what the number *is*, with
 * every question of spacing, national prefix and local convention already resolved.
 */
final readonly class Value implements ParsedValue, HasParts
{
	/** The parsed number, which is what every comparison and constraint reads. */
	public LibPhoneNumber $number;

	/**
	 * Takes the record a field takes: a number and the country to read it in.
	 *
	 * Both halves are required, and valid **for that region** rather than valid somewhere. It
	 * is what stops an Australian number passing a field told it is a New Zealand one, and it
	 * settles the international case too: libphonenumber ignores the region when a number is
	 * already E.164, so `+61…` paired with `US` would otherwise sail through with the two
	 * halves disagreeing.
	 *
	 * @param object $number with a `number` and a `country`, or an already-parsed LibPhoneNumber
	 *        — which is how a field hands back a value it resolved using its own default country
	 * @throws MalformedValue if either half is missing, or the pair does not describe a number
	 */
	public function __construct(object $number)
	{
		// Already parsed, by a field applying its own defaults for the country.
		if ($number instanceof LibPhoneNumber) {
			$this->number = $number;

			return;
		}

		$parts = get_object_vars($number);

		if (!isset($parts['number']) || !is_string($parts['number'])) {
			throw MalformedValue::of(self::class, 'it has no "number"');
		}

		if (!isset($parts['country']) || !is_string($parts['country'])) {
			throw MalformedValue::of(self::class, 'it has no "country", and a number cannot be read without one');
		}

		// Upper-cased because ISO 3166-1 defines the codes that way, so `au` and `AU` are one
		// country. Not trimmed: `' AU '` is not a code, and libphonenumber agrees — it refuses it.
		$region = strtoupper($parts['country']);
		$util = PhoneNumberUtil::getInstance();

		try {
			$proto = $util->parse($parts['number'], $region);
		} catch (NumberParseException) {
			throw MalformedValue::of(self::class, sprintf('"%s" is not a phone number', $parts['number']));
		}

		if (!$util->isValidNumberForRegion($proto, $region)) {
			throw MalformedValue::of(self::class, sprintf(
				'"%s" is not a valid number in %s',
				$parts['number'],
				$region,
			));
		}

		$this->number = $proto;
	}

	/**
	 * Compared in E.164, so two spellings of one number are one number.
	 *
	 * The country is carried by E.164 itself — the calling code is part of it — so there is no
	 * second half to compare, unlike {@see \Meraki\Schema\Field\Money\Value}.
	 */
	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->toE164() === $other->toE164();
	}

	/**
	 * The number in E.164 — `+61411222333`.
	 *
	 * The one form that is the same everywhere, which is what makes it the right thing to store,
	 * to send to a gateway, and to compare on.
	 */
	public function toE164(): string
	{
		return PhoneNumberUtil::getInstance()->format($this->number, PhoneNumberFormat::E164);
	}

	public function __toString(): string
	{
		return $this->toE164();
	}

	/**
	 * The country and the canonical number.
	 *
	 * `country` is the region libphonenumber resolves the number to, which is the part a rule
	 * actually wants — "is the participant's phone in the same country as the organiser's" is a
	 * real question and comparing whole numbers cannot answer it.
	 *
	 * It is `null` for a number whose region is genuinely ambiguous: `+1` covers twenty-five
	 * regions, so libphonenumber declines to guess and so does this.
	 *
	 * @return list<string>
	 */
	public static function partNames(): array
	{
		return ['country', 'e164'];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function parts(): array
	{
		return [
			'country' => PhoneNumberUtil::getInstance()->getRegionCodeForNumber($this->number),
			'e164' => $this->toE164(),
		];
	}
}
