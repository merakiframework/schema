<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\PhoneNumber;

use libphonenumber\PhoneNumberType;

/**
 * The kind of phone number a field will accept.
 *
 * `Any` imposes no restriction. The others map onto libphonenumber's number
 * types, treating the ambiguous `FIXED_LINE_OR_MOBILE` (regions that cannot tell
 * the two apart, e.g. the USA) as satisfying both `Mobile` and `FixedLine`.
 */
enum Type: string
{
	case Mobile = 'mobile';
	case FixedLine = 'fixed-line';
	case Either = 'either';
	case Any = 'any';

	public function matches(int $libType): bool
	{
		return match ($this) {
			self::Mobile => in_array($libType, [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE], true),
			self::FixedLine => in_array($libType, [PhoneNumberType::FIXED_LINE, PhoneNumberType::FIXED_LINE_OR_MOBILE], true),
			self::Either => in_array($libType, [PhoneNumberType::FIXED_LINE, PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE], true),
			self::Any => true,
		};
	}
}
