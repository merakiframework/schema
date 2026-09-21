<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Outcome;

use InvalidArgumentException;
use Meraki\Schema\Field;
use Meraki\Schema\FieldScope;
use Meraki\Schema\Rule\Outcome;

/**
 * Puts a field into the state the author configured, by applying the same changes they made.
 *
 * The outcome behind `then($field->makeRequired()->mustBeAccepted())`. The author calls the
 * field's own withers, which is why the whole thing is type-safe with no machinery: `$insurance`
 * is a `Boolean`, so `mustBeAccepted()` is on it and `minLengthOf()` is not, and their editor
 * knows both. Nothing here had to be taught about `Boolean`.
 *
 * ### It stores the difference, not the field
 *
 * The obvious implementation — keep the modified field and swap it in — loses under composition.
 * Two rules both targeting one field would each replace it wholesale, so whichever ran last would
 * silently undo the other. So the modified copy is compared against the authored one *when the
 * rule is added*, and what survives is the set of properties that changed.
 *
 * Those compose: two rules touching one field merge their changes rather than clobbering. They
 * also serialise as data a JavaScript implementation can apply, which a stored field object would
 * not, and they read back — `$applied->outcome->changes` says exactly what a rule did.
 *
 * ### Why comparing by identity is right
 *
 * {@see Field\Definition::with()} clones, so a property no wither touched is the *same instance*
 * in both copies and `!==` is false for it. A property a wither replaced is a new value, and
 * `!==` is true. That holds for objects and scalars alike, and it needs no per-field knowledge —
 * which is the whole reason this works for a field type nobody here has heard of.
 *
 * Derived state is skipped: `constraints` is rebuilt from the properties rather than being one of
 * them, and `name` is what identifies the field rather than something a rule may change.
 */
final readonly class Reconfigure implements Outcome
{
	/** Properties that are not configuration, and so are never part of a difference. */
	private const DERIVED = ['name', 'constraints'];

	/**
	 * @param array<string, mixed> $changes the properties that differ, and what they became
	 */
	private function __construct(
		private FieldScope $scope,
		public array $changes,
	) {
	}

	/**
	 * Reads what an author did to a field by comparing the result against the original.
	 *
	 * @throws InvalidArgumentException if the two are not the same field, or if nothing changed
	 */
	public static function from(Field $authored, Field $modified): self
	{
		if ((string) $authored->name !== (string) $modified->name) {
			throw new InvalidArgumentException(sprintf(
				'A rule outcome must describe one field, but "%s" was compared against "%s".',
				(string) $authored->name,
				(string) $modified->name,
			));
		}

		if ($authored::class !== $modified::class) {
			throw new InvalidArgumentException(sprintf(
				'"%s" is a %s on the schema and a %s in the rule. A rule changes a field\'s '
				. 'configuration; it cannot change what kind of field it is.',
				(string) $authored->name,
				$authored::class,
				$modified::class,
			));
		}

		// Identity, not equality. A wither always clones, so the very same instance means no
		// wither was called — `then($field)` on its own, which evaluates a condition and does
		// nothing with the answer.
		//
		// An empty *difference* is a different thing and is allowed: `else($guardian->makeOptional())`
		// where the author already made it optional restores a state rather than changing one, and
		// saying so explicitly is how the else-branch stays readable next to its then-branch. It
		// also stops being a no-op the moment another rule touches the same field.
		if ($authored === $modified) {
			throw new InvalidArgumentException(sprintf(
				'The rule says what happens to "%s" but was handed the field unchanged. Configure '
				. 'it — then($field->makeRequired()) — or drop it from the rule.',
				(string) $authored->name,
			));
		}

		return new self(FieldScope::of($authored->name), self::difference($authored, $modified));
	}

	public function applyTo(Field $field): Field
	{
		return $field->reconfiguredWith($this->changes);
	}

	public function getScope(): FieldScope
	{
		return $this->scope;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function difference(Field $authored, Field $modified): array
	{
		$before = get_object_vars($authored);
		$after = get_object_vars($modified);
		$changes = [];

		foreach ($after as $property => $value) {
			if (in_array($property, self::DERIVED, true)) {
				continue;
			}

			if (!array_key_exists($property, $before) || $before[$property] !== $value) {
				$changes[$property] = $value;
			}
		}

		return $changes;
	}
}
