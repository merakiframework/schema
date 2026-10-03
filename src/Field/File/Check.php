<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\File;

use Meraki\Schema\Field;

/**
 * What a file field checks, by the code a failure is reported under.
 *
 * The first six decide whether what arrived describes an upload at all, and are reported by
 * {@see Input} before anything else is asked. The last four decide whether this field accepts it,
 * and are about the upload as a whole: a form draws one input for a file, not three.
 *
 * @see Field\Check for why a code is an enum rather than a string
 */
enum Check: string implements Field\Check
{
	case NameRequired = 'nameRequired';
	case TypeRequired = 'typeRequired';
	case SizeRequired = 'sizeRequired';
	case NameFormat = 'nameFormat';
	case TypeFormat = 'typeFormat';
	case SizeFormat = 'sizeFormat';
	case MinSize = 'minSize';
	case MaxSize = 'maxSize';
	case AllowedTypes = 'allowedTypes';
	case DisallowedTypes = 'disallowedTypes';

	public function part(): ?Part
	{
		return match ($this) {
			self::NameRequired, self::NameFormat => Part::Name,
			self::TypeRequired, self::TypeFormat => Part::Type,
			self::SizeRequired, self::SizeFormat => Part::Size,
			self::MinSize, self::MaxSize, self::AllowedTypes, self::DisallowedTypes => null,
		};
	}
}
