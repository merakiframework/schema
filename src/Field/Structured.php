<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;

/**
 * @phpstan-import-type SerializedField from Field
 * @template TSerialized of SerializedField
 * @extends Field<string|null, TSerialized>
 */
abstract class Structured extends Field
{
}
