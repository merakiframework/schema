<?php
declare(strict_types=1);

namespace Meraki\Schema;

/**
 * Names what a field was given, `#/fields/username/value`, the parsed and canonicalized
 * form of the `value` segment of a field's input.
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
	 * The whole value, or one named part of it.
	 *
	 *     ValueScope::of('billing')              // #/fields/billing/value
	 *     ValueScope::of('billing', 'country')   // #/fields/billing/value/country
	 *
	 * One entry point, because a part is always *inside* a value and there is nowhere else it
	 * could hang from. {@see PartScope::of()} still exists for building one directly; this is the
	 * spelling to reach for, and it means nobody has to know `PartScope` is there before they can
	 * address a part.
	 *
	 * ### It hands back a different class, and that is checked rather than surprising
	 *
	 * The two are **siblings, not a subtype pair** — see {@see PartScope} for why a part is not a
	 * kind of value — so this returns whichever the arguments describe. The conditional return type
	 * is what keeps that honest: PHPStan and Psalm narrow it at the call site, so
	 * `ValueScope::of('billing')->part` is an error before it is a runtime one, and passing a part
	 * where a whole value is wanted is caught too.
	 *
	 * At runtime the type really is the union, which changes nothing: every dispatch on a scope's
	 * kind is an `instanceof` and always was.
	 *
	 * @return ($part is null ? ValueScope : PartScope)
	 */
	public static function of(FieldName|string $field, ?string $part = null): ValueScope|PartScope
	{
		$name = $field instanceof FieldName ? $field : new FieldName($field);

		return $part === null ? new self($name) : PartScope::of($name, $part);
	}

	public function __toString(): string
	{
		return $this->prefix() . '/' . self::SEGMENT;
	}
}
