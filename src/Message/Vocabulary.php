<?php
declare(strict_types=1);

namespace Meraki\Schema\Message;

use Meraki\Schema\Exception\IncompleteVocabulary;
use Meraki\Schema\Field;
use Meraki\Schema\FieldName;
use ReflectionClass;

/**
 * Every key a language pack may define, and what each one is allowed to say.
 *
 * This is what makes a data-only pack type-safe. A pack is `.mfr` files with no PHP in them, so
 * nothing about it can be checked by a compiler — but the *vocabulary* it is written against is
 * entirely knowable from this library: the field kinds, the code each check reports under, and the
 * parts a structured value has. Reading it off the classes rather than keeping a list means it
 * cannot go stale, which is the failure this is here to prevent: a pack quietly missing wording for
 * a check added two releases ago.
 *
 *     vendor/bin/schema-lang validate .     # in a pack's own CI
 *     vendor/bin/schema-lang keys           # what a pack may define
 *
 * ### Every code, whichever step reports it
 *
 * A code is read from the field's {@see Field::$checks}, not from its constraints. A field that
 * assembles its value from parts reports `amountRequired` before any constraint runs, and a pack
 * words it under the same key either way — so the vocabulary cannot depend on which step a check
 * happens to belong to, or moving one would orphan a sentence.
 *
 * ### Why it builds one of every field
 *
 * A field's codes and parts are read off an instance, so there is one built of every field. Three
 * need more than a name to build — a currency, a country, a set of cases — and those are listed
 * below with the smallest thing that satisfies them. A new field that needs an argument and is not
 * listed raises, which is the point: the vocabulary should refuse to be quietly incomplete.
 */
final class Vocabulary
{
	/** The keys that are wording rather than sentences, and take no variables. */
	public const FIXED_KEYS = ['list.separator', 'list.lastSeparator', 'bound.true', 'bound.false'];

	/** Why a shape can fail; the suffixes of a `shape.*` key. */
	public const SHAPE_PROBLEMS = ['missing', 'unreadable'];

	/** What a `shape.*` message may name. */
	public const SHAPE_VARIABLES = ['field', 'kind'];

	/** What a message about any other code may name. */
	public const CHECK_VARIABLES = ['field', 'kind', 'part', 'bound'];

	/** @var array<string, Field>|null one of each, built once */
	private static ?array $fields = null;

	/**
	 * One built instance of every field this library ships, keyed by the name a pack calls it —
	 * the class's short name.
	 *
	 * @return array<string, Field>
	 */
	public static function fields(): array
	{
		if (self::$fields !== null) {
			return self::$fields;
		}

		$fields = [];

		foreach (glob(dirname(__DIR__) . '/Field/*.php') ?: [] as $file) {
			$kind = basename($file, '.php');
			$class = Field::class . '\\' . $kind;

			if (!class_exists($class) || !is_a($class, Field::class, true)) {
				continue;
			}

			if ((new ReflectionClass($class))->isAbstract()) {
				continue;
			}

			$fields[$kind] = self::build($class, $kind);
		}

		ksort($fields);

		return self::$fields = $fields;
	}

	/** @return list<string> */
	public static function kinds(): array
	{
		return array_keys(self::fields());
	}

	/**
	 * The codes each kind reports a failure under, whichever step reports it.
	 *
	 * @return array<string, list<string>>
	 */
	public static function checksByKind(): array
	{
		return array_map(
			static fn(Field $field): array => array_map(
				static fn(Field\Check $check): string => (string) $check->value,
				$field->checks,
			),
			self::fields(),
		);
	}

	/**
	 * The part each code concerns, for the codes that concern one.
	 *
	 * @return array<string, array<string, string>> kind => code => part
	 */
	public static function partsByCheck(): array
	{
		$parts = [];

		foreach (self::fields() as $kind => $field) {
			foreach ($field->checks as $check) {
				$part = $check->part();

				if ($part !== null) {
					$parts[$kind][(string) $check->value] = (string) $part->value;
				}
			}
		}

		return $parts;
	}

	/**
	 * Every part any value has, whether or not a code mentions it.
	 *
	 * Wider than {@see self::partsByCheck()} on purpose: `{$part}` is translated through a
	 * `part.*` entry, and a pack should be able to name a part that nothing currently fails on.
	 *
	 * @return array<string, list<string>>
	 */
	public static function partsByKind(): array
	{
		$parts = [];

		foreach (self::fields() as $kind => $field) {
			$names = array_column($field->parts, 'value');

			if ($names !== []) {
				$parts[$kind] = $names;
			}
		}

		return $parts;
	}

	/** @return list<string> */
	public static function checkNames(): array
	{
		$names = [];

		foreach (self::checksByKind() as $forKind) {
			$names = [...$names, ...$forKind];
		}

		$names = array_values(array_unique($names));
		sort($names);

		return $names;
	}

	/** @return list<string> */
	public static function partNames(): array
	{
		$names = [];

		foreach (self::partsByKind() as $forKind) {
			$names = [...$names, ...$forKind];
		}

		$names = array_values(array_unique($names));
		sort($names);

		return $names;
	}

	/**
	 * Every key a pack may define, sorted.
	 *
	 * Not every key it *should*: most of these are overrides that exist so a pack can say something
	 * more specific when the generic wording is wrong, and leaving one out is how a pack says the
	 * generic wording is fine.
	 *
	 * @return list<string>
	 */
	public static function keys(): array
	{
		$keys = self::FIXED_KEYS;

		foreach (self::SHAPE_PROBLEMS as $problem) {
			$keys[] = "shape.{$problem}";
		}

		foreach (self::checkNames() as $name) {
			$keys[] = $name;
		}

		foreach (self::partNames() as $part) {
			$keys[] = "part.{$part}";
		}

		$byCheck = self::partsByCheck();

		foreach (self::checksByKind() as $kind => $names) {
			$keys[] = "kind.{$kind}";

			foreach (self::SHAPE_PROBLEMS as $problem) {
				$keys[] = "{$kind}.shape.{$problem}";
			}

			foreach ($names as $name) {
				$keys[] = "{$kind}.{$name}";

				if (isset($byCheck[$kind][$name])) {
					$part = $byCheck[$kind][$name];
					$keys[] = "{$kind}.{$part}.{$name}";
					$keys[] = "{$part}.{$name}";
				}
			}
		}

		$keys = array_values(array_unique($keys));
		sort($keys);

		return $keys;
	}

	/**
	 * What a message under this key is allowed to name.
	 *
	 * Null for a key that is not part of the vocabulary at all, which a caller reports differently:
	 * an unknown key is a typo or a stale pack, while a message naming the wrong variable is a
	 * mistake inside a key that does exist.
	 *
	 * @return list<string>|null
	 */
	public static function variablesFor(string $key): ?array
	{
		if (!in_array($key, self::keys(), true)) {
			return null;
		}

		if (in_array($key, self::FIXED_KEYS, true)
			|| str_starts_with($key, 'kind.')
			|| str_starts_with($key, 'part.')
		) {
			return [];
		}

		return str_contains($key, 'shape.') ? self::SHAPE_VARIABLES : self::CHECK_VARIABLES;
	}

	/**
	 * The smallest thing that builds each field.
	 *
	 * @param class-string<Field> $class
	 */
	private static function build(string $class, string $kind): Field
	{
		$name = new FieldName('f');

		return match ($class) {
			Field\Enum::class => new Field\Enum($name, ['a', 'b']),
			Field\Money::class => new Field\Money($name, ['AUD' => 2]),
			Field\Address::class => new Field\Address($name, ['AU']),
			Field\PhoneNumber::class => new Field\PhoneNumber($name, ['AU']),
			Field\Collection::class => new Field\Collection($name, new Field\Text(new FieldName('item'))),
			default => self::buildPlain($class, $kind),
		};
	}

	/**
	 * @param class-string<Field> $class
	 * @throws IncompleteVocabulary if the field needs more than a name and nobody has said what
	 */
	private static function buildPlain(string $class, string $kind): Field
	{
		$constructor = (new ReflectionClass($class))->getConstructor();

		if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 1) {
			throw IncompleteVocabulary::fieldNeedsMoreThanAName($kind, self::class);
		}

		return new $class(new FieldName('f'));
	}
}
