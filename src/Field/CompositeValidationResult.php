<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AggregatedValidationResult;
use Meraki\Schema\ResolvedField;

/**
 * A composite resolved against one request: one {@see ResolvedField} per sub-field.
 *
 * This exists because a composite is currently *made of* fields, so its result is made of
 * their results. That model goes in 2.0 proper, when `Address`, `Money` and `CreditCard`
 * become distinct types owning their whole value and reporting their own constraint names
 * — and this class goes with it.
 *
 * @extends AggregatedValidationResult<ResolvedField>
 */
final class CompositeValidationResult extends AggregatedValidationResult
{
	/**
	 * Children are typed as the shared supertype only while the deprecated
	 * {@see Composite::validate()} path still produces {@see ValidationResult}s. Once that
	 * goes, this narrows to `ResolvedField ...$fieldResults`.
	 *
	 * @param ResolvedField|ValidationResult ...$fieldResults
	 */
	public function __construct(
		public readonly Composite|Variant $composite,
		AggregatedValidationResult ...$fieldResults,
	) {
		parent::__construct(...$fieldResults);
	}

	/**
	 * One sub-field's result, by its fully-qualified name — `price.amount`, not `amount`.
	 */
	public function get(string $fieldName): ResolvedField|ValidationResult|null
	{
		foreach ($this->results as $result) {
			if ((string) $result->field->name === $fieldName) {
				return $result;
			}
		}

		return null;
	}
}
