<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;

/**
 * @extends AtomicField<null|string|array>
 */
abstract class AtomicMultiValue extends AtomicField
{
}
