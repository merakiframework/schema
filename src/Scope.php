<?php
declare(strict_types=1);

namespace Meraki\Schema;

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

	/**
	 * Where this scope's tail is rooted. A bare {@see FieldName} means the schema's own field, so
	 * everything written before collections were addressable keeps building what it always built.
	 */
	public Scope\Locator $in;

	/**
	 * The schema field this is about — the collection, when the scope reaches inside one.
	 *
	 * Kept as a property rather than becoming `$in->field` at every call site because it is read in
	 * two places that have no interest in locators: grouping outcomes by field, and looking the
	 * field up to resolve against.
	 */
	public FieldName $field;

	public function __construct(FieldName|Scope\Locator $in)
	{
		$this->in = $in instanceof Scope\Locator ? $in : new Scope\SchemaField($in);

		// Copied rather than read through the locator on demand, because this class is readonly
		// and a readonly class may not hold a hooked property.
		$this->field = $this->in->field;
	}

	/**
	 * Reads a scope from its string form, returning whichever kind the path describes.
	 *
	 * @throws InvalidScope if the path is not a scope this schema can address
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

		if ($name === '') {
			throw InvalidScope::fieldNameIsMissing($path);
		}

		[$in, $tail] = self::locatorIn($segments, new FieldName($name), $path);

		return self::tail($in, $tail, $path);
	}

	/**
	 * Reads the locator off the front of a path, and hands back what is left for the tail.
	 *
	 * Positional throughout, so this never has to ask what kind of field `$name` is — which is what
	 * keeps parsing free of the schema. A locator is recognised by its marker segment, and a
	 * collection's by the wildcard or row name sitting where one belongs.
	 *
	 * @param list<string> $segments
	 * @return array{Scope\Locator, list<string>}
	 * @throws InvalidScope if a marker is there but what it introduces is not
	 */
	private static function locatorIn(array $segments, FieldName $name, string $path): array
	{
		$marker = $segments[2] ?? null;

		// `#/fields/<c>/template/<tf>` — but `#/fields/<c>/template` alone is still the ordinary
		// property scope it has always been, handing back the whole template list. The marker only
		// takes over once something follows it.
		if ($marker === Scope\Template::SEGMENT && count($segments) >= 4) {
			return [
				new Scope\Template($name, self::templateFieldIn($segments[3], $path)),
				array_slice($segments, 4),
			];
		}

		// `#/fields/<c>/value/<row|*>/<tf>`. Five segments is the shortest this can be, which is
		// exactly one more than a value part needs — so `#/fields/billing/value/country` is read
		// as it always was, without either reading having to know whether `billing` is a
		// collection.
		if ($marker === ValueScope::SEGMENT && count($segments) >= 5) {
			$row = $segments[3];
			$field = self::templateFieldIn($segments[4], $path);

			return [
				$row === Scope\Column::EVERY_ROW
					? new Scope\Column($name, $field)
					: new Scope\Row($name, $row, $field),
				array_slice($segments, 5),
			];
		}

		return [new Scope\SchemaField($name), array_slice($segments, 2)];
	}

	/**
	 * @throws InvalidScope if the segment naming a template field is empty or not a name
	 */
	private static function templateFieldIn(string $segment, string $path): FieldName
	{
		if (!FieldName::isUsable($segment)) {
			throw InvalidScope::templateFieldIsNotAName($path, $segment);
		}

		return new FieldName($segment);
	}

	/**
	 * What to read once the locator says where — the same four shapes wherever it is rooted.
	 *
	 * Written once and reached from all four namespaces, which is the whole point of splitting a
	 * scope into a locator and a tail: a row's field is addressed exactly like a top-level one
	 * because it goes through this same function.
	 *
	 * @param list<string> $tail
	 * @throws InvalidScope if the tail is not one of the four
	 */
	private static function tail(Scope\Locator $in, array $tail, string $path): self
	{
		return match (count($tail)) {
			0 => new FieldScope($in),
			1 => $tail[0] === ValueScope::SEGMENT ? new ValueScope($in) : new PropertyScope($in, $tail[0]),
			2 => $tail[0] === ValueScope::SEGMENT
				? new PartScope($in, $tail[1])
				: throw InvalidScope::formatIsIncorrectForTargetingAValuePart(
					$path,
					self::FIELD_COLLECTION,
					(string) $in->addresses(),
					ValueScope::SEGMENT,
				),
			default => throw InvalidScope::tooManySegments($path),
		};
	}

	/**
	 * The same tail, rooted somewhere else.
	 *
	 * The operation the locator/tail split exists to make possible. It is how a column is answered
	 * — by asking each row the very same question — rather than by a second implementation of every
	 * tail that knows about lists.
	 */
	public function rootedAt(Scope\Locator $in): self
	{
		return match (true) {
			$this instanceof PartScope => new PartScope($in, $this->part),
			$this instanceof PropertyScope => new PropertyScope($in, $this->property),
			$this instanceof ValueScope => new ValueScope($in),
			default => new FieldScope($in),
		};
	}

	/**
	 * Whether two scopes address the same thing.
	 */
	public function equals(self $other): bool
	{
		return $other::class === static::class && (string) $other === (string) $this;
	}

	/**
	 * The path up to and including the field this is about. The locator owns it, because the four
	 * of them are precisely the four ways of getting there.
	 */
	protected function prefix(): string
	{
		return (string) $this->in;
	}
}
