<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;

class Date extends Field
{
	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('date'), $name, ...$attributes);
	}

	public static function getSupportedAttributes(): array
	{
		return Attribute\Set::ALLOW_ANY;
	}
}
