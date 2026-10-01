<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Field;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Scoped;
use Meraki\Schema\Scope;
use Meraki\Schema\ScopeResolver;
use Stringable;

/**
 * A condition asking something about a value's text.
 *
 * {@see Contains} and {@see Matches}. Both need the value *as a string*, which not every value
 * has, so both answer the same way when it does not: the condition does not hold.
 *
 * ### Not every value has text, and two of them refuse to
 *
 * Twelve of the nineteen value types are {@see \Stringable} — number, date, date-time, time,
 * duration, email address, enum, name, phone number, text, URI and UUID — and a
 * {@see \Meraki\Schema\PartScope} resolves to a plain string, so `ValueScope::of('email', 'domain')`
 * reaches one half of an address where the whole one reaches all of it.
 *
 * The absences are the interesting part. {@see \Meraki\Schema\Field\Password\Value} and
 * {@see \Meraki\Schema\Field\CreditCard\Value} have no `__toString()` **on purpose**, so neither
 * can be pattern-matched by a rule. That is not a gap this class should paper over: a rule reading
 * the text of a secret is exactly the thing that should be hard to write by accident, and the
 * consequence of that decision reaching this far is a feature of it.
 *
 * ### Why this is not checked when the rule is written
 *
 * {@see Ordered} refuses a rule against a field with no order, because a field's value class says
 * so without a request. The same check is not available here: a part resolves to whatever the
 * value put in it, and the field's own class says nothing about that — so refusing on the field
 * would reject `ValueScope::of('email', 'domain')`, which works perfectly.
 *
 * What *is* checked where the rule is written is the pattern itself. See {@see Matches}.
 */
abstract class Textual implements Condition, Scoped
{
	public readonly Scope $scope;

	/**
	 * The scope in its string form, which is what `meraki/schema-json` writes to disk.
	 */
	public string $target {
		get => (string) $this->scope;
	}

	public function __construct(Scope|string $target)
	{
		$this->scope = $target instanceof Scope ? $target : Scope::parse($target);
	}

	/**
	 * What the scope points at, as text — or null when it has none.
	 *
	 * @param array<string, mixed> $data
	 */
	final protected function textAt(array $data, Field\Set $fields): ?string
	{
		$value = (new ScopeResolver($fields, $data))->resolve($this->scope);

		return match (true) {
			is_string($value) => $value,
			$value instanceof Stringable => (string) $value,
			default => null,
		};
	}

	/**
	 * What the scope points at, as the lines of text it holds — none, one, or several.
	 *
	 * A part may be a *list*: `Address\Value::$street` is the first, because an address line is
	 * one of up to three and nothing here normalises them into delimited text. Asking a textual
	 * question of one used to resolve the list, fail to read it as a string, and answer `null` —
	 * so `contains('PO Box')` was accepted at authoring and then never fired, which is the
	 * dead-rule failure this library spends most of its guards avoiding.
	 *
	 * The fold is **any line**. That is the only reading of "does the street contain a PO box"
	 * that is both useful and unambiguous, and it is what an author writing it meant.
	 *
	 * Not {@see \Meraki\Schema\Rule\Condition\Quantified}, which asserts its scope's locator is a
	 * `Scope\Column` and folds over `ScopeResolver::rowNamesIn()`. That is collection machinery:
	 * it answers "how many rows match", where this answers "does any line".
	 *
	 * @param array<string, mixed> $data
	 * @return list<string>
	 */
	final protected function textLinesAt(array $data, Field\Set $fields): array
	{
		$value = (new ScopeResolver($fields, $data))->resolve($this->scope);
		$lines = [];

		foreach (is_array($value) ? $value : [$value] as $one) {
			if (is_string($one)) {
				$lines[] = $one;
			} elseif ($one instanceof Stringable) {
				$lines[] = (string) $one;
			}
		}

		return $lines;
	}

	/**
	 * @return list<Scope>
	 */
	public function getScopes(): array
	{
		return [$this->scope];
	}

	/**
	 * The same question, asked about somewhere else. See {@see Scoped::about()}.
	 */
	public function about(Scope $scope): static
	{
		return clone($this, ['scope' => $scope]);
	}
}
