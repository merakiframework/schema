<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

/**
 * @template AcceptedType of mixed
 * @property-read string $type
 * @property-read string $name
 * @property-read boolean $optional
 * @property-read AcceptedType|null $value
 * @property-read array<Serialized> $fields
 */
interface Serialized
{
}
