<?php
declare(strict_types=1);

namespace Meraki\Schema;

/**
 * Names what a field was given — `#/fields/username/value`.
 *
 * The only scope kind whose answer depends on the request rather than on the schema, which
 * is why {@see ScopeResolver} needs the submitted data to resolve one at all. Everything
 * else a scope can address is the definition, and the definition is the same for every
 * request.
 */
final readonly class ValueScope extends Scope
{
	/**
	 * The trailing segment that distinguishes this from a property of the same name. A
	 * field cannot declare a public property called `value` that means anything else,
	 * because this reading wins.
	 */
	public const SEGMENT = 'value';

	/**
	 * @param FieldName|string $field
	 */
	public static function of(FieldName|string $field): self
	{
		return new self($field instanceof FieldName ? $field : new FieldName($field));
	}

	public function __toString(): string
	{
		return $this->prefix() . '/' . self::SEGMENT;
	}
}
