<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;

/**
 * @template AcceptedType of mixed
 * @extends AtomicField<AcceptedType>
 */
abstract class AtomicMultiValue extends AtomicField
{
}
