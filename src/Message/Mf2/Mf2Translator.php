<?php
declare(strict_types=1);

namespace Meraki\Schema\Message\Mf2;

use Meraki\Schema\Field;
use Meraki\Schema\Message\Translator;

/**
 * One language's wording, resolved and ready to answer about verdicts.
 *
 * This is the part that survives. {@see Formatter} renders a message and goes away when a real MF2
 * implementation arrives; everything here — which key to look up, in what order, and what values a
 * message is entitled to name — is about *this library's* vocabulary and would be needed whatever
 * rendered the sentences.
 *
 * ### The ladder, most specific first
 *
 * A pack says as much or as little as it wants. Each of these is tried in turn and the first that
 * exists wins:
 *
 * | For | Keys, in order |
 * | --- | --- |
 * | A shape failure | `EmailAddress.shape.unreadable`, `shape.unreadable` |
 * | A constraint failure | `Address.postal_code.postalCodeFormat`, `Address.postalCodeFormat`, `postal_code.postalCodeFormat`, `postalCodeFormat` |
 *
 * So a pack can write one sentence for every `minLength` in the library and then override it for
 * `Password`, which is a different kind of advice even though it is the same constraint. A rung
 * nobody fills in costs nothing.
 *
 * A field somebody else wrote is looked up under its own class name and falls through to the
 * generic rung, which is usually right: the meaning is carried by the constraint's name, not by
 * whose field emitted it. There is deliberately no walk up the parent classes — a field extending
 * `Text` does not thereby mean what `Text` means.
 *
 * ### Four variables, and they are always supplied
 *
 * `{$field}`, `{$kind}`, `{$part}`, `{$bound}`. A message naming anything else raises rather than
 * rendering a gap, which is why {@see PackValidator} checks them before a pack ships.
 *
 * They arrive **already in the language**, which is what lets {@see Formatter} stay as small as it
 * is. `{$kind}` is the type's name translated through a `kind.*` entry, `{$part}` through a
 * `part.*` entry, and a list-valued bound is already joined using the pack's own `list.separator`
 * and `list.lastSeparator` — MF2's default function registry has no `:list`, so a pack could not do
 * that itself even with a complete implementation.
 *
 * `{$field}` is the field's name as the schema author wrote it, not a label. This library has no
 * labels: a label is what a form calls something, and that belongs to whatever draws the form.
 */
final class Mf2Translator implements Translator
{
	private const DEFAULT_SEPARATOR = ', ';
	private const DEFAULT_LAST_SEPARATOR = ' or ';

	public function __construct(
		public readonly string $locale,
		private readonly Resource $resource,
		private readonly Formatter $formatter = new Formatter(),
	) {
	}

	public function forShape(Field $field, Field\ShapeValidationResult $shape): ?string
	{
		$problem = match (true) {
			$shape->wasMissing() => 'missing',
			$shape->wasUnreadable() => 'unreadable',
			default => null,
		};

		if ($problem === null) {
			return null;
		}

		$kind = self::kindOf($field);

		return $this->render(
			["{$kind}.shape.{$problem}", "shape.{$problem}"],
			[
				'field' => (string) $field->name,
				'kind' => $this->nameOfKind($kind),
			],
		);
	}

	public function forConstraint(Field $field, Field\ConstraintValidationResult $constraint): ?string
	{
		$kind = self::kindOf($field);
		$part = $constraint->part;
		$name = $constraint->name;

		$keys = $part === null
			? ["{$kind}.{$name}", $name]
			: ["{$kind}.{$part}.{$name}", "{$kind}.{$name}", "{$part}.{$name}", $name];

		return $this->render($keys, [
			'field' => (string) $field->name,
			'kind' => $this->nameOfKind($kind),
			'part' => $part === null ? '' : $this->nameOfPart($part),
			'bound' => $this->boundAsText($constraint->bound),
		]);
	}

	/**
	 * The first key that exists, rendered; null when none of them do.
	 *
	 * @param list<string> $keys
	 * @param array<string, string> $variables
	 * @throws BadMessage if the message that was found cannot be rendered
	 */
	private function render(array $keys, array $variables): ?string
	{
		foreach ($keys as $key) {
			$message = $this->resource->get($key);

			if ($message !== null) {
				return $this->formatter->format($message, $variables);
			}
		}

		return null;
	}

	/**
	 * A vocabulary entry — a kind's name, a part's name, a list separator.
	 *
	 * Rendered through the formatter like any other message, so the escapes work and a translator
	 * who puts a variable somewhere it cannot be filled finds out. Vocabulary entries take no
	 * variables: they are the raw material a message is assembled from, so nothing is available to
	 * them yet.
	 */
	private function word(string $key, ?string $fallback = null): ?string
	{
		$message = $this->resource->get($key);

		return $message === null ? $fallback : $this->formatter->format($message, []);
	}

	/** The type's name in this language — "email address" for `EmailAddress`. */
	private function nameOfKind(string $kind): string
	{
		return $this->word("kind.{$kind}", $kind) ?? $kind;
	}

	/** The part's name in this language — "postcode" for `postal_code`, in Australia. */
	private function nameOfPart(string $part): string
	{
		return $this->word("part.{$part}", $part) ?? $part;
	}

	/**
	 * The limit that applied, as text a sentence can hold.
	 *
	 * A list is joined here rather than in the message because MF2's default function registry has
	 * no `:list`, so there is nothing a pack could write even once a full implementation exists.
	 * The separators are the pack's own, because "a, b or c" is an English shape and not a
	 * universal one.
	 *
	 * @param string|int|float|bool|list<string>|null $bound
	 */
	private function boundAsText(string|int|float|bool|array|null $bound): string
	{
		if ($bound === null) {
			return '';
		}

		if (is_bool($bound)) {
			return $this->word('bound.' . ($bound ? 'true' : 'false'), $bound ? 'true' : 'false') ?? '';
		}

		if (!is_array($bound)) {
			return (string) $bound;
		}

		$items = array_values(array_map(strval(...), $bound));
		$last = array_pop($items);

		if ($last === null) {
			return '';
		}

		if ($items === []) {
			return $last;
		}

		return implode($this->word('list.separator', self::DEFAULT_SEPARATOR) ?? self::DEFAULT_SEPARATOR, $items)
			. ($this->word('list.lastSeparator', self::DEFAULT_LAST_SEPARATOR) ?? self::DEFAULT_LAST_SEPARATOR)
			. $last;
	}

	/**
	 * The field's type, as a pack names it: the class's short name, so `Meraki\Schema\Field\Money`
	 * is `Money` and somebody's own `App\Field\Abn` is `Abn`.
	 */
	private static function kindOf(Field $field): string
	{
		$class = $field::class;
		$at = strrpos($class, '\\');

		return $at === false ? $class : substr($class, $at + 1);
	}
}
