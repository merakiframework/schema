<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;

/**
 * @template TSerialized of Serialized
 * @extends AtomicField<null|string|array>
 * @extends AtomicField<TSerialized>
 */
abstract class AtomicMultiValue extends AtomicField
{
}
