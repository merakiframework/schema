<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\PhoneNumber;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\HasParts;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\ParsedValue;
use libphonenumber\NumberParseException;
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
	/**
	 * The parsed number, which is what every comparison and constraint reads.
	 *
	 * `null` when a country was chosen and nothing typed yet — the ordinary state of a
	 * half-filled form, which the field reports as `numberRequired` rather than calling the
	 * whole value unreadable.
	 */
	public ?LibPhoneNumber $number;

	/** The country as submitted, upper-cased. Always present: the value refuses one without. */
	public string $country;

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
			$this->country = (string) PhoneNumberUtil::getInstance()->getRegionCodeForNumber($number);

			return;
		}

		$parts = get_object_vars($number);
		$unknown = array_diff(array_keys($parts), self::partNames());

		// Named rather than ignored, the rule every record-shaped value here follows. A port
		// still sending `e164` — which was a part until it stopped being one — would otherwise
		// be told its number is missing, which names the symptom and hides the stale key.
		if ($unknown !== []) {
			throw MalformedValue::of(self::class, sprintf(
				'"%s" is not a part of a phone number. The parts are: "%s"',
				implode('", "', $unknown),
				implode('", "', self::partNames()),
			));
		}

		// A part that was sent and holds nothing is unreadable, not absent — the rule every
		// record-shaped value here follows. `''` was a decision somebody made.
		foreach (['number', 'country'] as $key) {
			if (is_string($parts[$key] ?? null) && trim($parts[$key]) === '') {
				throw MalformedValue::of(self::class, "its {$key} was given but holds nothing");
			}
		}

		// The country is to a phone number what it is to an address: the thing that gives the
		// rest meaning. `0411 222 333` is a different number in a different country, and
		// libphonenumber cannot parse one without a region — so this is refused rather than
		// reported, exactly as a country-less address is.
		if (!isset($parts['country']) || !is_string($parts['country'])) {
			throw MalformedValue::of(self::class, 'it has no "country", and a number cannot be read without one');
		}

		// The number itself is kept absent rather than refused. A country chosen with nothing
		// typed yet is the ordinary state of a half-filled form, and reporting the whole field
		// unreadable named neither the problem nor the box to mark. `numberRequired` does both.
		if (!isset($parts['number'])) {
			$this->number = null;
			$this->country = strtoupper($parts['country']);

			return;
		}

		if (!is_string($parts['number'])) {
			throw MalformedValue::of(self::class, 'a number is a string');
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
		$this->country = $region;
	}

	/**
	 * Compared in E.164, so two spellings of one number are one number.
	 *
	 * The country is carried by E.164 itself — the calling code is part of it — so there is no
	 * second half to compare, unlike {@see \Meraki\Schema\Field\Money\Value}.
	 */
	public function equals(Equality $other): bool
	{
		if (!$other instanceof self) {
			return false;
		}

		// Two half-filled numbers are the same when the same half is missing and the country
		// agrees, which keeps this total rather than comparing against nothing.
		if ($this->number === null || $other->number === null) {
			return $this->number === $other->number && $this->country === $other->country;
		}

		return $this->toE164() === $other->toE164();
	}

	/**
	 * The number in E.164 — `+61411222333` — or `null` when there is no number yet.
	 *
	 * The one form that is the same everywhere, which is what makes it the right thing to store,
	 * to send to a gateway, and to compare on.
	 */
	public function toE164(): ?string
	{
		return $this->number === null
			? null
			: PhoneNumberUtil::getInstance()->format($this->number, PhoneNumberFormat::E164);
	}

	public function __toString(): string
	{
		return $this->toE164() ?? '';
	}

	/**
	 * The parts as they arrive: a number and the country to read it in.
	 *
	 * These used to be `country` and `e164`, which is what the value *holds* rather than what it
	 * is *given* — so a port could not derive its input names from them, and
	 * `PartedSet::forPart('number')` raised on the one part a form definitely renders. Every
	 * other value here names its input keys, and this is no longer the exception.
	 *
	 * E.164 has not gone anywhere; it is {@see self::toE164()}, a derived reading rather than a
	 * part. The region libphonenumber resolved the number to is also still readable, as
	 * {@see self::$number}, but `country` here is the country that was *submitted* — those differ
	 * only for input the value would have refused anyway.
	 *
	 * @return list<string>
	 */
	public static function partNames(): array
	{
		return ['number', 'country'];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function parts(): array
	{
		return [
			'number' => $this->toE164(),
			'country' => $this->country,
		];
	}

	/**
	 * Nothing here is canonicalised, so a rule compares against exactly what it was written
	 * with. {@see \Meraki\Schema\Field\Address\Value::canonicalPartValue()} is the one that
	 * has work to do.
	 */
	public function canonicalPartValue(string $part, mixed $expected): mixed
	{
		return $expected;
	}

	/** Every part here is one string. @see HasParts::listParts() */
	public static function listParts(): array
	{
		return [];
	}
}
