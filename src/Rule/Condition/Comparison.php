<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use Meraki\Schema\Comparison\Values;
use Meraki\Schema\FieldResult;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Scope;
use Meraki\Schema\ScopeResolver;
use Meraki\Schema\ValueScope;

/**
 * A condition comparing what a scope points at against a value the author wrote.
 *
 * ### The expectation is read the same way the input was
 *
 * This is what the class exists for. A scope pointing at a field's value resolves to the *parsed*
 * value — a `BigDecimal` for a number, a `LocalDate` for a date — because that is what the field
 * decided the input meant. The author, meanwhile, writes the scalar they would have submitted:
 *
 *     $schema->when($age)->equals(18)
 *
 * Comparing those two directly is comparing `BigDecimal` to `int`, which is false for every input
 * there has ever been. The rule did not error; it simply never fired, on every field that parses to
 * an object — twelve of the nineteen. A required field silently stayed optional.
 *
 * So the expectation goes through the same field the value did, and the comparison happens in the
 * field's own terms. `equals(18)` on a number compares two `BigDecimal`s; `equals('2030-01-01')` on
 * a date compares two `LocalDate`s.
 *
 * ### Only a value is parsed
 *
 * A {@see \Meraki\Schema\PropertyScope} names part of the *definition* — `#/fields/age/minValue` —
 * and a definition property is whatever the field declares it to be, not something the field parses.
 * Putting `18` through `Text::parse()` to compare against `Text::$minLength` would turn a working
 * comparison into `null`. So the parse applies to {@see ValueScope} alone, and everything else is
 * compared as it stands.
 *
 * `null` is never parsed either. It is the one expectation that means "nothing", and
 * {@see Field::resolvedValueFor()} answers a null with the field's authored default — so parsing it
 * would quietly turn "when this was left empty" into "when this equals its default".
 *
 * ### The expectation can be another scope
 *
 * Which is what makes a rule able to compare two fields rather than a field and a constant:
 *
 *     // is the whole shipping address the billing address?
 *     $schema->when(ValueScope::of('shipping'))->equals(ValueScope::of('billing'))
 *
 *     // are they at least in the same country?
 *     $schema->when(PartScope::of('shipping', 'country'))
 *         ->equals(PartScope::of('billing', 'country'))
 *
 * Both sides go through the same {@see ScopeResolver}, so both are read the same way and a parsed
 * value is compared against a parsed value. Nothing is parsed *into* a field in that case — there
 * is no literal to read — and the two sides need not be the same kind of field: comparing a
 * `postal_code` to a `line1` is allowed, and answers false.
 */
abstract class Comparison implements Condition
{
	public readonly Scope $scope;

	/**
	 * The scope in its string form, which is what `meraki/schema-json` writes to disk.
	 * Derived rather than stored, so it cannot drift from the scope it describes.
	 */
	public string $target {
		get => (string) $this->scope;
	}

	public function __construct(Scope|string $target, public readonly mixed $expected)
	{
		$this->scope = $target instanceof Scope ? $target : Scope::parse($target);
	}

	/**
	 * Whether what the scope points at is the expected value, both sides read the same way.
	 *
	 * @param array<string, mixed> $data
	 */
	final protected function pointsAtTheExpectedValue(array $data, Facade $schema): bool
	{
		$resolver = new ScopeResolver($schema, $data);

		return Values::same(
			$resolver->resolve($this->scope),
			$this->expectedAsTheFieldWouldReadIt($schema, $resolver),
		);
	}

	/**
	 * Whether the field this compares against can read the expectation at all.
	 *
	 * Checked where the rule is added rather than where it fires. An expectation the field cannot
	 * read compares unequal against every input, so the rule is dead — and a dead rule that throws
	 * no error is indistinguishable from one whose condition simply never held.
	 *
	 * Returns true for anything this does not parse, which is exactly the set it has no opinion
	 * about: a definition property, and `null`.
	 */
	final public function expectationIsReadable(Facade $schema): bool
	{
		// A scope is checked by {@see self::getScopes()} instead: "can this field hold that value"
		// is the wrong question about the other half of a cross-field comparison, where the answer
		// is whatever the request supplies.
		if (!$this->parsesItsExpectation()) {
			return true;
		}

		$field = $schema->fields->findByName($this->scope->field);

		// Not this check's business. Facade::addRule() reports an unaddressable scope itself, and
		// with a better message than "the expectation is unreadable" would be.
		if ($field === null) {
			return true;
		}

		$result = $field->validate($this->expected);

		// The field is asked rather than inferred from. `resolvedValueFor()` hands back what it
		// was given when it cannot parse it, so "came back unchanged" cannot tell an unreadable
		// value from one a field parses to itself — which every text-shaped field does.
		//
		// Only the *shape* is consulted. Whether the expectation satisfies the field's constraints
		// is not this check's question: `equals('ab')` against a field with a three-character
		// minimum is a perfectly sensible rule, because the point of the rule may well be to react
		// to input that is going to fail.
		return !$result instanceof FieldResult || !$result->shape->wasUnreadable();
	}

	/**
	 * Both sides, when both are scopes.
	 *
	 * {@see \Meraki\Schema\Facade::addRule()} checks every scope a rule mentions, so an expectation
	 * naming a field that does not exist is refused where the rule is written rather than resolving
	 * to `null` on every request afterwards.
	 *
	 * @return array<Scope>
	 */
	public function getScopes(): array
	{
		return $this->expected instanceof Scope
			? [$this->scope, $this->expected]
			: [$this->scope];
	}

	private function parsesItsExpectation(): bool
	{
		return $this->scope instanceof ValueScope
			&& $this->expected !== null
			&& !$this->expected instanceof Scope;
	}

	private function expectedAsTheFieldWouldReadIt(Facade $schema, ScopeResolver $resolver): mixed
	{
		// The other side is somewhere else in the same request. Resolved through the same resolver,
		// so both sides are read the same way and a parsed value is compared against a parsed
		// value — which is what makes `when($shipping)->equals($billing)` mean what it reads as.
		if ($this->expected instanceof Scope) {
			return $resolver->resolve($this->expected);
		}

		if (!$this->parsesItsExpectation()) {
			return $this->expected;
		}

		$field = $schema->fields->findByName($this->scope->field);

		return $field === null ? $this->expected : $field->resolvedValueFor($this->expected);
	}
}
