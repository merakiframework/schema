<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

/**
 * @template AcceptedType of mixed
 * @property-read string $type
 * @property-read string $name
 * @property-read boolean $optional
 * @property-read AcceptedType|null $value
 */
interface Serialized
{
	/**
	 * The names of the properties on this serialized field that are used for validation; not including 'type'.
	 * @return string[]
	 */
	public function getConstraints(): array;
}
