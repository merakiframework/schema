<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\File;

use Meraki\Schema\Field;

/**
 * What a client says about one upload: its name, the type it claims, and the size it reports.
 *
 * All three are essential. They are one upload's metadata rather than three inputs a form renders,
 * and an upload described without one of them is not described.
 */
enum Part: string implements Field\Part
{
	case Name = 'name';
	case Type = 'type';
	case Size = 'size';

	public function isEssential(): bool
	{
		return true;
	}

	public function isList(): bool
	{
		return false;
	}
}
