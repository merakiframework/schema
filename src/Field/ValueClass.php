<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * What a field parses to, read off its own `parse()` signature.
 *
 * Every field declares a return type on `parse()` — that is the contract
 * {@see Definition::parse()} exists to enforce — so the class of a field's value is a fact about
 * the field, knowable without a request. Two things need it before any value exists:
 * {@see \Meraki\Schema\ScopeResolver} checks a {@see \Meraki\Schema\PartScope} when the rule is
 * *written*, and {@see \Meraki\Schema\Message\Set} decides whether a field's messages are grouped
 * by part before it knows whether anything failed.
 *
 * Both used to reach for reflection themselves. Having one of them own it meant the other either
 * duplicated the cache or asked the wrong question, and "does this field have parts" is not a
 * question about scopes.
 */
final class ValueClass
{
	/** @var array<class-string, class-string|null> */
	private static array $cache = [];

	/**
	 * The class a field's `parse()` returns, or `null` when it does not name one.
	 *
	 * @return class-string|null
	 */
	public static function of(Field $field): ?string
	{
		$key = $field::class;

		if (!array_key_exists($key, self::$cache)) {
			self::$cache[$key] = self::read($field);
		}

		return self::$cache[$key];
	}

	/**
	 * Whether the field's value is made of named parts — an address, a card, a money amount —
	 * rather than being one thing.
	 */
	public static function hasParts(Field $field): bool
	{
		$class = self::of($field);

		return $class !== null && is_a($class, HasParts::class, true);
	}

	/**
	 * Every part the field's value has, in the order the value declares them; empty when it has
	 * none.
	 *
	 * @return list<string>
	 */
	public static function partNamesOf(Field $field): array
	{
		$class = self::of($field);

		return $class !== null && is_a($class, HasParts::class, true) ? $class::partNames() : [];
	}

	/** @return class-string|null */
	private static function read(Field $field): ?string
	{
		// A field is free to implement Field directly and never declare parse() at all. That is a
		// field with no parsed value to speak of, not a broken one, so it answers "no class"
		// rather than raising.
		if (!method_exists($field, 'parse')) {
			return null;
		}

		$returns = (new ReflectionMethod($field, 'parse'))->getReturnType();

		return $returns instanceof ReflectionNamedType && !$returns->isBuiltin()
			? $returns->getName()
			: null;
	}
}
