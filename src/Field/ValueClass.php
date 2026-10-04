<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;

/**
 * What a field parses to, read off its own `parse()` signature.
 *
 * Every field declares a return type on `parse()` — that is the contract
 * {@see Definition::parse()} exists to enforce — so the class of a field's value is a fact about
 * the field, knowable without a request. A rule needs it where it is *written*: whether a value
 * has an order is a fact about its class, and an ordered question asked of one that does not is
 * refused there rather than quietly never holding.
 *
 * A field whose value has parts returns an {@see Input} from `parse()` instead, and the input says
 * what it assembles to by narrowing {@see Input::$value} — so the class is read from there, and
 * money is still known to have an order.
 *
 * It used to answer "which parts does this value have" as well, by calling static methods on the
 * class it found. That is {@see \Meraki\Schema\Field::$parts} now, declared by the field's own
 * {@see Part} enum, so nothing has to reflect to learn it.
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

	/** @return class-string|null */
	private static function read(Field $field): ?string
	{
		// A field is free to implement Field directly and never declare parse() at all. That is a
		// field with no parsed value to speak of, not a broken one, so it answers "no class"
		// rather than raising.
		if (!method_exists($field, 'parse')) {
			return null;
		}

		$class = self::classNamedBy((new ReflectionMethod($field, 'parse'))->getReturnType());

		// An input is not the value; it holds one, and its own declaration says which.
		return ($class !== null && is_a($class, Input::class, true))
			? self::classNamedBy((new ReflectionProperty($class, 'value'))->getType())
			: $class;
	}

	/** @return class-string|null */
	private static function classNamedBy(?ReflectionType $type): ?string
	{
		/** @var class-string|null */
		return ($type instanceof ReflectionNamedType && !$type->isBuiltin())
			? $type->getName()
			: null;
	}
}
