<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Date;

use Meraki\Schema\Field;

/**
 * What a date field checks, by the code a failure is reported under.
 *
 * Every check here is about the value as a whole, so none of them names a part.
 *
 * @see Field\Check for why a code is an enum rather than a string
 */
enum Check: string implements Field\Check
{
	case From = 'from';
	case After = 'after';
	case Until = 'until';
	case Through = 'through';
	case Interval = 'interval';

	public function part(): null
	{
		return null;
	}
}
