<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Collection;

use Meraki\Schema\AggregatedValidationResult;
use Meraki\Schema\ResolvedField;

/**
 * One item of a collection, resolved against one request: a result per template field, and the
 * key the item arrived under.
 *
 * Being able to say *which* item is the point. Results used to be flattened into one list per
 * collection, so you could tell that an item had failed but never which — which made a repeatable
 * section impossible to report on in a real form. That was a documented defect, and this is its
 * fix.
 *
 * @extends AggregatedValidationResult<ResolvedField>
 */
final class Item extends AggregatedValidationResult
{
	/**
	 * @param string|int $key what the item arrived under. A plain list gives `0, 1, 2`; an array
	 *        with string keys — `['line item 1' => …]` — gives the name, which is worth having
	 *        because "row 3 is wrong" is a poor thing to tell someone about a named section.
	 *
	 *        One field rather than a position *and* an optional name: there is only ever one
	 *        answer to "which item is this", and carrying two would invite them to disagree. A
	 *        list still keys itself by position, so nothing is lost when no name was given.
	 */
	public function __construct(
		public readonly string|int $key,
		ResolvedField ...$fields,
	) {
		parent::__construct(...$fields);
	}

	/**
	 * One of this item's fields, by the name the template gave it — `starts_at`, not
	 * `sessions.0.starts_at`. An item has no idea what collection it belongs to, and a template
	 * field is named once rather than once per item.
	 */
	public function forField(string $fieldName): ?ResolvedField
	{
		foreach ($this->results as $result) {
			if ($result instanceof ResolvedField && (string) $result->field->name === $fieldName) {
				return $result;
			}
		}

		return null;
	}
}
