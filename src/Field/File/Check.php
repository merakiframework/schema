<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\File;

use Meraki\Schema\Field;

/**
 * What a file field checks, by the code a failure is reported under.
 *
 * @see Field\Check for why a code is an enum rather than a string
 */
enum Check: string implements Field\Check
{
	case MinSize = 'minSize';
	case MaxSize = 'maxSize';
	case AllowedTypes = 'allowedTypes';
	case DisallowedTypes = 'disallowedTypes';

	public function part(): null
	{
		return null;
	}
}
