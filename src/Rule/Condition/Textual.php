<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Facade;
use Meraki\Schema\Rule\Condition;
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
abstract class Textual implements Condition
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
	final protected function textAt(array $data, Facade $schema): ?string
	{
		$value = (new ScopeResolver($schema, $data))->resolve($this->scope);

		return match (true) {
			is_string($value) => $value,
			$value instanceof Stringable => (string) $value,
			default => null,
		};
	}

	/**
	 * @return array<Scope>
	 */
	public function getScopes(): array
	{
		return [$this->scope];
	}
}
