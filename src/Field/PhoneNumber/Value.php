<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\PhoneNumber;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\MalformedValue;
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
 *
 * ### Always whole
 *
 * A number and the country it is valid in, neither of them null. A number typed before a country
 * was picked, or a country picked before anything was typed, is an {@see Input} that has not made
 * a value yet, and is reported part by part — so every constraint handed one of these has a number
 * it can read.
 */
final readonly class Value implements ParsedValue
{
	/**
	 * Built by {@see Input} from what was submitted. The constructor still guards the one thing
	 * that makes a number and a country a pair, so a value made any other way cannot hold a number
	 * from one country under another's name.
	 *
	 * @param LibPhoneNumber $number the number, parsed — which is what every comparison and
	 *        constraint reads
	 * @param string $country ISO 3166-1 alpha-2, upper-cased: the country it was read in
	 * @throws MalformedValue if the number is not a valid one in that country
	 */
	public function __construct(
		public LibPhoneNumber $number,
		public string $country,
	) {
		if (!PhoneNumberUtil::getInstance()->isValidNumberForRegion($number, $country)) {
			throw MalformedValue::of(self::class, "it is not a valid number in {$country}");
		}
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
}
