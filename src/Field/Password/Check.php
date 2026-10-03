<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Password;

use Meraki\Schema\Field;

/**
 * What a password field checks, by the code a failure is reported under.
 *
 * Every check here is about the value as a whole, so none of them names a part.
 *
 * @see Field\Check for why a code is an enum rather than a string
 */
enum Check: string implements Field\Check
{
	case MinLength = 'minLength';
	case MaxLength = 'maxLength';
	case MinStrength = 'minStrength';
	case MinUppercaseChars = 'minUppercaseChars';
	case MinLowercaseChars = 'minLowercaseChars';
	case MinDigits = 'minDigits';
	case MinSymbols = 'minSymbols';

	public function part(): null
	{
		return null;
	}
}
