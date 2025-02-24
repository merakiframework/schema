<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;

final class Multiple extends Attribute
{
	public function __construct(bool $value = true)
	{
		parent::__construct('multiple', $value);
	}
}
