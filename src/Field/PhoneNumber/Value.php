<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\PhoneNumber;

use Meraki\Schema\Field\ParsedValue;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * One telephone number, as this library compares it.
 *
 * {@see ParsedValue} but deliberately **not** {@see \Meraki\Schema\Field\Comparable}: phone numbers
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
final readonly class Value implements ParsedValue
{
	public function __construct(public LibPhoneNumber $number)
	{
	}

	/**
	 * Compared in E.164, so two spellings of one number are one number.
	 *
	 * The country is carried by E.164 itself — the calling code is part of it — so there is no
	 * second half to compare, unlike {@see \Meraki\Schema\Field\Money\Value}.
	 */
	public function equals(ParsedValue $other): bool
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
