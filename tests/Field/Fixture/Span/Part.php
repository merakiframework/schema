<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Fixture\Span;

use Meraki\Schema\Field;

/**
 * The parts of a span: two ends that no span exists without, and a label it can do without.
 */
enum Part: string implements Field\Part
{
	case From = 'from';
	case To = 'to';
	case Label = 'label';

	public function isEssential(): bool
	{
		return $this !== self::Label;
	}

	public function isList(): bool
	{
		return false;
	}
}
