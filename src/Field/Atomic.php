<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\ValidationResult;
use Meraki\Schema\ResolvedField;

/**
 * @template AcceptedType of mixed
 * @extends Field<AcceptedType>
 */
abstract class Atomic extends Field
{
	/**
	 * An atomic field resolves to exactly one result. Narrowed from the base declaration so
	 * callers keep `given`, `value` and `transformed` without a cast; composites widen it
	 * again because they resolve to one result per sub-field.
	 */
	public function resolveWith(mixed $given, array $appliedOutcomes = []): ResolvedField
	{
		/** @var ResolvedField */
		return parent::resolveWith($given, $appliedOutcomes);
	}

	public function validateWith(mixed $given, array $appliedOutcomes = []): ResolvedField
	{
		/** @var ResolvedField */
		return parent::validateWith($given, $appliedOutcomes);
	}

	public function validate(): ValidationResult
	{
		return parent::validate();
	}
}
