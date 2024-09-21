<?php
declare(strict_types=1);

namespace Meraki\Form\Field;

use Meraki\Form\Field;
use Meraki\Form\Constraint;

final class Factory
{
	public function create(string $name, string $type, Constraint\Set $constraints): Field
	{
		return new Field($name, $type, $constraints);
	}
}
