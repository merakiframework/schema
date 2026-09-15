<?php
declare(strict_types=1);

namespace Meraki\Schema;

use InvalidArgumentException;
use Stringable;

/**
 * A reference to something in a schema, written as `#/fields/username/value`.
 *
 * A scope used to be a cursor: it implemented `Iterator`, and resolving one walked its
 * position to the end of the path. Because a rule builds its scope once and keeps it, that
 * made resolution a write to shared state — two requests could move each other's cursor,
 * an outcome applied twice started from an exhausted cursor, and a schema's own state
 * changed as a side effect of being read. Two workarounds downstream existed only to paper
 * over it. A scope is now an immutable value: resolving one cannot disturb it, so those
 * problems have nowhere left to live.
 *
 * It is also typed by what it points at. `#/fields/x` names a field, `#/fields/x/value`
 * names what that field was given, and `#/fields/x/min` names part of its definition —
 * three different questions that used to be one class distinguished by counting segments
 * at the point of use. An outcome that only makes sense against a field can now say so in
 * its signature, instead of resolving a scope and throwing if it turns out to be the
 * wrong kind.
 *
 * The string form is unchanged, because it is the wire format `meraki/schema-json` reads
 * and writes.
 */
abstract readonly class Scope implements Stringable
{
	/**
	 * The only collection addressable at the root. Kept as a constant because both the
	 * parser and every subclass's string form depend on it agreeing.
	 */
	public const COLLECTION = 'fields';

	public function __construct(public FieldName $field)
	{
	}

	/**
	 * Reads a scope from its string form, returning whichever kind the path describes.
	 *
	 * Strict on purpose. The old parser accepted a path with trailing junk and silently
	 * ignored it, so `#/fields/x/min/anything` resolved as `min`; a typo that should have
	 * been an error behaved like a working scope.
	 *
	 * @throws InvalidArgumentException if the path is not a scope this schema can address
	 */
	public static function parse(string $path): self
	{
		if (!str_starts_with($path, '#/')) {
			throw new InvalidArgumentException(sprintf(
				'"%s" is not a scope path: it must start with "#/".',
				$path,
			));
		}

		$segments = explode('/', substr($path, 2));

		if (($segments[0] ?? null) !== self::COLLECTION) {
			throw new InvalidArgumentException(sprintf(
				'"%s" does not address anything: the only addressable collection is "%s".',
				$path,
				self::COLLECTION,
			));
		}

		$name = $segments[1] ?? '';
		$property = $segments[2] ?? null;

		if ($name === '') {
			throw new InvalidArgumentException(sprintf('"%s" is missing a field name.', $path));
		}

		// A fourth segment addresses a part of the value — `#/fields/billing/value/country` — and
		// only that. A collection's items are not addressable: which row `0` is depends on what
		// was submitted, so a stored rule naming one would mean different rows on different
		// requests.
		if (count($segments) === 4) {
			if ($property !== ValueScope::SEGMENT) {
				throw new InvalidArgumentException(sprintf(
					'"%s" addresses a part, which only a value has. Write it as "#/%s/%s/%s/<part>".',
					$path,
					self::COLLECTION,
					$name,
					ValueScope::SEGMENT,
				));
			}

			return new PartScope(new FieldName($name), $segments[3]);
		}

		if (count($segments) > 4) {
			throw new InvalidArgumentException(sprintf(
				'"%s" has more segments than a scope can address. A part of a value is as deep as '
				. 'this goes; collection items are not addressable.',
				$path,
			));
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
		return '#/' . self::COLLECTION . '/' . $this->field;
	}
}
