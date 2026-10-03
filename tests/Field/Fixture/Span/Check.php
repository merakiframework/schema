<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Fixture\Span;

use Meraki\Schema\Field;

/**
 * What a span checks. Every code but the last is assembly — no configuration can make a span
 * without its ends, or with its ends the wrong way round, into one. The last is a constraint.
 */
enum Check: string implements Field\Check
{
	case FromRequired = 'fromRequired';
	case ToRequired = 'toRequired';
	case FromFormat = 'fromFormat';
	case ToFormat = 'toFormat';
	case LabelFormat = 'labelFormat';
	case InOrder = 'inOrder';
	case MaxWidth = 'maxWidth';

	public function part(): ?Part
	{
		return match ($this) {
			self::FromRequired, self::FromFormat => Part::From,
			self::ToRequired, self::ToFormat, self::InOrder => Part::To,
			self::LabelFormat => Part::Label,
			self::MaxWidth => null,
		};
	}
}
