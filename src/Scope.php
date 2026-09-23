<?php
declare(strict_types=1);

namespace Meraki\Schema;

use InvalidArgumentException;
use Meraki\Schema\Exception\InvalidScope;
use Stringable;

/**
 * A reference to something in the schema, usually written as `#/fields/username/value`.
 *
 * A scope provides a path to a field, its value (and parts), or a property of the field. It is
 * used in rules to say what the rule applies to, and in validation results to say what was validated.
 *
 * It is also typed by what it points at. `#/fields/x` names a field, `#/fields/x/value`
 * names what that field was given, and `#/fields/x/min` names part of its definition.
 */
abstract readonly class Scope implements Stringable
{
	/**
	 * Currently, the only "element" in a schema that can be addressed is a field. All fields are
	 * in the same collection, so the first segment of a scope path is always `fields`.
	 *
	 * Other top-level elements are name and rules, but are not currently addressable by scope.
	 */
	public const FIELD_COLLECTION = 'fields';

	public function __construct(public FieldName $field)
	{
	}

	/**
	 * Reads a scope from its string form, returning whichever kind the path describes.
	 *
	 * @throws InvalidArgumentException if the path is not a scope this schema can address
	 */
	public static function parse(string $path): self
	{
		if (!str_starts_with($path, '#/')) {
			throw InvalidScope::prefixIsMissing($path);
		}

		$segments = explode('/', substr($path, 2));	// drop the `#/` prefix and split the rest into segments

		if (($segments[0] ?? null) !== self::FIELD_COLLECTION) {
			throw InvalidScope::notAnAddressableElement($path, self::FIELD_COLLECTION);
		}

		$name = $segments[1] ?? '';
		$property = $segments[2] ?? null;

		if ($name === '') {
			throw InvalidScope::fieldNameIsMissing($path);
		}

		// A fourth segment addresses a part of a value (i.e. `#/fields/billing/value/country`).
		if (count($segments) === 4) {
			if ($property !== ValueScope::SEGMENT) {
				throw InvalidScope::formatIsIncorrectForTargetingAValuePart($path, self::FIELD_COLLECTION, $name, ValueScope::SEGMENT);
			}

			return new PartScope(new FieldName($name), $segments[3]);
		}

		if (count($segments) > 4) {
			throw InvalidScope::tooManySegments($path);
		}

		return match (true) {
			$property === null => new FieldScope(new FieldName($name)),
			$property === ValueScope::SEGMENT => new ValueScope(new FieldName($name)),
			default => new PropertyScope(new FieldName($name), $property),
		};
	}

	/**
	 * Whether two scopes address the same thing.
	 */
	public function equals(self $other): bool
	{
		return $other::class === static::class && (string) $other === (string) $this;
	}

	protected function prefix(): string
	{
		return '#/' . self::FIELD_COLLECTION . '/' . $this->field;
	}
}
