<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Uri;

use Meraki\Schema\Field;

/**
 * What a URI field checks, by the code a failure is reported under.
 *
 * Every check here is about the value as a whole, so none of them names a part.
 *
 * @see Field\Check for why a code is an enum rather than a string
 */
enum Check: string implements Field\Check
{
	case MinLength = 'minLength';
	case MaxLength = 'maxLength';
	case AllowedSchemes = 'allowedSchemes';

	public function part(): null
	{
		return null;
	}
}
