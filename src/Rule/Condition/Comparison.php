<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Comparison\Values;
use Meraki\Schema\Field;
use Meraki\Schema\FieldResult;
use Meraki\Schema\PartScope;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Scoped;
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
 *     $schema->when(ValueScope::of('shipping', 'country'))
 *         ->equals(ValueScope::of('billing', 'country'))
 *
 * Both sides go through the same {@see ScopeResolver}, so both are read the same way and a parsed
 * value is compared against a parsed value. Nothing is parsed *into* a field in that case — there
 * is no literal to read — and the two sides need not be the same kind of field: comparing a
 * `postal_code` to a `line1` is allowed, and answers false.
 *
 * ### More than one expectation
 *
 * `equals` compares against one value, `isBetween` against two and `isIn` against a list. They are
 * the same question asked of a different number of operands, so {@see self::expectations()} is what
 * a subclass widens — and the readability check, the scope collection and the parsing all follow
 * from it rather than being restated three times.
 */
abstract class Comparison implements Condition, Scoped
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
	final protected function pointsAtTheExpectedValue(array $data, Field\Set $fields): bool
	{
		$resolver = new ScopeResolver($fields, $data);

		return $this->pointsAt($this->expected, $resolver->resolve($this->scope), $fields, $resolver);
	}

	/**
	 * Whether what the scope resolved to is this one candidate, both sides read the same way.
	 *
	 * Separate from {@see self::pointsAtTheExpectedValue()} because {@see IsIn} asks it of each
	 * of several candidates against one resolved value. Everything that makes a comparison
	 * correct — the parse, the canonicalisation, the list fold — lives here, so a subclass
	 * looping over operands cannot accidentally get a simpler comparison than `equals` does.
	 * That is how `isIn` came to miss both fixes that `equals` had.
	 *
	 * @param mixed $resolved what the scope points at, already resolved once by the caller
	 */
	final protected function pointsAt(mixed $candidate, mixed $resolved, Field\Set $fields, ScopeResolver $resolver): bool
	{
		$expected = $this->expectationAgainst($candidate, $fields, $resolver);

		// A part held as a list asks the question of each entry — the same "any" fold
		// {@see Textual::textLinesAt()} makes, for the same reason.
		if (is_array($resolved)) {
			foreach ($resolved as $one) {
				if (Values::same($one, $expected)) {
					return true;
				}
			}

			return false;
		}

		return Values::same($resolved, $expected);
	}

	/**
	 * The expectation, read the way the thing it is being compared against was read.
	 *
	 * A value canonicalises what it is given: an address stores `AU-QLD` whichever of `QLD`,
	 * `qld`, `AU-QLD` or `Queensland` was submitted, and `AU` for `Australia`. The *stored* side
	 * went through that and the *expectation* did not, so `equals('QLD')` compared `AU-QLD`
	 * against `QLD` and was false for every request there would ever be — accepted at authoring,
	 * silently dead, and written in the very spelling the field accepts as input.
	 *
	 * Asked of the value rather than resolved here, because only the value knows what it did:
	 * a subdivision needs its country to resolve, and the submitted value is the only thing that
	 * has one. That is also why this happens at match time rather than when the rule is written —
	 * with several countries allowed there is no single subdivision list to resolve against.
	 */
	private function expectationAgainst(mixed $candidate, Field\Set $fields, ScopeResolver $resolver): mixed
	{
		$expected = $this->readExpectation($candidate, $fields, $resolver);

		if (!$this->scope instanceof PartScope) {
			return $expected;
		}

		$owner = $resolver->resolve(new ValueScope($this->scope->in));
		$part = $this->partNamedBy($this->scope, $fields);

		return ($owner instanceof Field\HasParts && $part !== null)
			? $owner->canonicalPartValue($part, $expected)
			: $expected;
	}

	/**
	 * The part a scope names, as the field it points into declares it — or null when there is no
	 * such field, which the guards have already refused by the time a request arrives.
	 */
	private function partNamedBy(PartScope $scope, Field\Set $fields): ?Field\Part
	{
		$field = (new ScopeResolver($fields))->fieldFor($scope);

		foreach ($field === null ? [] : $field->parts as $part) {
			if ($part->value === $scope->part) {
				return $part;
			}
		}

		return null;
	}

	/**
	 * Why this comparison could not hold for any input there will ever be, or null if it could.
	 *
	 * Checked where the rule is added rather than where it fires. A dead rule raises nothing, which
	 * makes it indistinguishable from one whose condition simply never held —
	 * `when('age')->equals('eighteen')` on a number field is the shape of it.
	 *
	 * Returns a sentence rather than a boolean because the *reasons* differ and a caller cannot
	 * infer which applied: an unreadable expectation and a field with no order are different
	 * mistakes needing different corrections, and "the expectation is unreadable" is simply wrong
	 * about the second. {@see Ordered} adds its own.
	 *
	 * Silent about anything this does not parse, which is exactly the set it has no opinion about:
	 * a definition property, `null`, and the other half of a cross-field comparison — where "can
	 * this field hold that value" is the wrong question, because the answer is whatever the request
	 * supplies.
	 */
	public function whyItCouldNeverHold(Field\Set $fields): ?string
	{
		// Not this check's business. Definition::addRule() reports an unaddressable scope itself, and
		// with a better message than anything here would be.
		$field = (new ScopeResolver($fields))->fieldFor($this->scope);

		if ($field === null) {
			return null;
		}

		foreach ($this->expectations() as $expectation) {
			if (!$this->wouldBeParsed($expectation)) {
				continue;
			}

			// The field is asked rather than inferred from. `resolvedValueFor()` hands back what it
			// was given when it cannot parse it, so "came back unchanged" cannot tell an unreadable
			// value from one a field parses to itself — which every text-shaped field does.
			//
			// Only the *shape* is consulted. Whether the expectation satisfies the field's
			// constraints is not this check's question: `equals('ab')` against a field with a
			// three-character minimum is a perfectly sensible rule, because the point of the rule
			// may well be to react to input that is going to fail.
			$result = $field->validate($expectation);

			if ($result instanceof FieldResult && $result->shape->wasUnreadable()) {
				return sprintf(
					'The rule compares "%s" against %s, which that field cannot hold — so the '
					. 'comparison could never be true and the rule would never fire.',
					(string) $this->scope,
					self::describe($expectation),
				);
			}
		}

		return null;
	}

	/**
	 * Every scope this mentions, on either side.
	 *
	 * {@see \Meraki\Schema\Definition::addRule()} checks every one, so an expectation naming a field
	 * that does not exist is refused where the rule is written rather than resolving to `null` on
	 * every request afterwards.
	 *
	 * @return list<Scope>
	 */
	public function getScopes(): array
	{
		$scopes = [$this->scope];

		foreach ($this->expectations() as $expectation) {
			if ($expectation instanceof Scope) {
				$scopes[] = $expectation;
			}
		}

		return $scopes;
	}

	/**
	 * Everything this compares the scope against.
	 *
	 * One for `equals`, two for `isBetween`, a list for `isIn`. Widened by a subclass so that
	 * parsing, the readability check and scope collection all follow from one declaration.
	 *
	 * @return list<mixed>
	 */
	protected function expectations(): array
	{
		return [$this->expected];
	}

	/**
	 * One expectation, read the way the field would have read it.
	 */
	final protected function readExpectation(mixed $expectation, Field\Set $fields, ScopeResolver $resolver): mixed
	{
		// The other side is somewhere else in the same request. Resolved through the same resolver,
		// so both sides are read the same way and a parsed value is compared against a parsed
		// value — which is what makes `when($shipping)->equals($billing)` mean what it reads as.
		if ($expectation instanceof Scope) {
			return $resolver->resolve($expectation);
		}

		if (!$this->wouldBeParsed($expectation)) {
			return $expectation;
		}

		$field = (new ScopeResolver($fields))->fieldFor($this->scope);

		return $field === null ? $expectation : $field->resolvedValueFor($expectation);
	}

	/**
	 * A value as it should read in a message: what was written, not just its type.
	 *
	 * "cannot hold string" leaves the author hunting for which string. `cannot hold 'eighteen'`
	 * points straight at it.
	 */
	final protected static function describe(mixed $value): string
	{
		return match (true) {
			is_string($value) => "'{$value}'",
			is_scalar($value) => var_export($value, true),
			default => get_debug_type($value),
		};
	}

	final protected function wouldBeParsed(mixed $expectation): bool
	{
		return $this->scope instanceof ValueScope
			&& $expectation !== null
			&& !$expectation instanceof Scope;
	}

	/**
	 * The same question, asked about somewhere else. See {@see Scoped::about()}.
	 */
	public function about(Scope $scope): static
	{
		return clone($this, ['scope' => $scope]);
	}
}
