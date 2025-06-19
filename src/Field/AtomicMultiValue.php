<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;

/**
 * @phpstan-import-type SerializedField from Field
 * @template AcceptedType of mixed
 * @template TSerialized of SerializedField
 * @extends AtomicField<AcceptedType, TSerialized>
 */
abstract class AtomicMultiValue extends AtomicField
{
}
