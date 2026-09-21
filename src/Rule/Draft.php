<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use InvalidArgumentException;
use LogicException;
use Meraki\Schema\Field;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldScope;
use Meraki\Schema\Rule;

/**
 * A rule being written: one condition, and what each way it can go does to a field.
 *
 * A rule is a **value**. It is built here, held in a variable if that is useful, and added to a
 * schema explicitly — which is what `whenAllMatch(Closure)` made impossible, since a rule built
 * by a configurator could only ever exist inline.
 *
 * ### An outcome is the field, configured
 *
 *     $schema->addRule(
 *         $parcelWeight->when()->isAtLeast(Weight::of('5.00', 'kg'))
 *             ->then($insurance->makeRequired()->mustBeAccepted()),
 *     );
 *
 * There is no `thenRequire()` or `thenMustBeAccepted()`, and there never can need to be: you
 * configure the field with **its own withers**, and the rule records the difference. So every
 * configuration method a field has is already a rule outcome — including the ones on a field type
 * this library has never heard of.
 *
 * It is type-safe for free, which is the part worth noticing. `$insurance` is a `Boolean`, so
 * `mustBeAccepted()` is on it and `minLengthOf()` is not, and an editor knows both. A
 * `then($field)` that handed back a builder could not manage that — PHP cannot vary a return type
 * by argument, so the builder would expose one fixed set of methods for every kind of field.
 * Putting the field on the *left* is what makes the types flow.
 *
 * Fields are immutable, so `$insurance->makeRequired()` hands back a copy and the schema's own
 * field is untouched. {@see Outcome\Reconfigure} compares the copy against it when the rule is
 * added and keeps only what differs.
 *
 * ### Why the else-branch lives on the same object
 *
 * `else()` is not sugar for a second rule. Writing the inverse by hand means two conditions that
 * are supposed to be opposites, with nothing checking that they stay so: a change to one is
 * silently a change in meaning. Here there is one condition, read once, and the branches cannot
 * disagree.
 *
 * ### Why composition takes conditions and not rules
 *
 * {@see \Meraki\Schema\Facade::allOf()} accepts drafts that carry no outcomes yet. If it took
 * finished rules there would be several sets of outcomes in play and no answer to which of them
 * should fire — so a draft with outcomes is refused there rather than guessed at.
 */
final class Draft
{
	/** @var list<Outcome|Field> a field is a desired state, resolved when the rule is added */
	private array $outcomes = [];

	/** @var list<Outcome|Field> */
	private array $else = [];

	public function __construct(public readonly Condition $condition)
	{
	}

	/**
	 * What these fields should look like when the condition holds.
	 *
	 * Each is a field you have configured — `$insurance->makeRequired()->mustBeAccepted()` — and
	 * what the rule keeps is the difference between it and the one on the schema.
	 *
	 * Hands back a copy, like every wither on a field. It used to write to `$this` and return
	 * itself, which made two fluent idioms with opposite meanings in one library — and quietly
	 * broke the thing {@see \Meraki\Schema\Facade::allOf()} invites you to do:
	 *
	 *     $base = $plan->when()->equals('go');
	 *     $a = $base->then($f1->makeRequired());
	 *     $b = $base->then($f2->makeRequired());   // $a and $b were one rule carrying both
	 */
	public function then(Field|Outcome ...$fields): self
	{
		$forked = clone $this;
		$forked->outcomes = [...$this->outcomes, ...$fields];

		return $forked;
	}

	/**
	 * As {@see self::then()}, for when the condition does not hold. Also hands back a copy.
	 */
	public function else(Field|Outcome ...$fields): self
	{
		$forked = clone $this;
		$forked->else = [...$this->else, ...$fields];

		return $forked;
	}

	/**
	 * Discards whatever was submitted for the field, so it validates as though nothing was.
	 *
	 * The one outcome that is not a configuration change, and so the one that keeps a named verb.
	 * Ignoring is about *this request* — it does not alter what the field is, it decides that the
	 * input never reaches it — and there is no wither for it because a definition has no opinion
	 * about a request it has not seen.
	 */
	public function thenIgnore(Field|FieldName|string $field): self
	{
		return $this->then(new Outcome\Ignore(self::pathTo($field)));
	}

	public function elseIgnore(Field|FieldName|string $field): self
	{
		return $this->else(new Outcome\Ignore(self::pathTo($field)));
	}

	/**
	 * Whether anything has been attached yet — what tells a composed condition from a rule.
	 */
	public function hasOutcomes(): bool
	{
		return $this->outcomes !== [] || $this->else !== [];
	}

	/**
	 * The finished rule, with every desired state resolved against the fields as authored.
	 *
	 * Deferred to here rather than done in {@see self::then()} because a draft has no schema: the
	 * difference between "the field you configured" and "the field on the schema" can only be read
	 * where both are in hand, and that is {@see \Meraki\Schema\Facade::addRule()}.
	 *
	 * @throws InvalidArgumentException if a field named by an outcome is not on the schema
	 * @throws LogicException if no outcome was ever attached
	 */
	public function buildAgainst(Field\Set $authored): Rule
	{
		$this->assertItSaysWhatHappens();

		return new Rule(
			$this->condition instanceof ConditionGroup
				? $this->condition
				: new Condition\AllOf($this->condition),
			self::resolve($this->outcomes, $authored),
			self::resolve($this->else, $authored),
		);
	}

	/**
	 * The finished rule, when every outcome is already an {@see Outcome}.
	 *
	 * @throws LogicException if a desired state is still waiting to be compared against the schema
	 */
	public function build(): Rule
	{
		$this->assertItSaysWhatHappens();

		foreach ([...$this->outcomes, ...$this->else] as $outcome) {
			if ($outcome instanceof Field) {
				throw new LogicException(sprintf(
					'The outcome for "%s" is a field to be compared against the one on the schema, '
					. 'which this draft cannot see. Add the rule with $schema->addRule() instead of '
					. 'building it here.',
					(string) $outcome->name,
				));
			}
		}

		return $this->buildAgainst(new Field\Set());
	}

	/**
	 * @param list<Outcome|Field> $items
	 * @return list<Outcome>
	 */
	private static function resolve(array $items, Field\Set $authored): array
	{
		$outcomes = [];

		foreach ($items as $item) {
			if ($item instanceof Outcome) {
				$outcomes[] = $item;
				continue;
			}

			$original = $authored->findByName($item->name);

			if ($original === null) {
				throw new InvalidArgumentException(sprintf(
					'The rule says what happens to "%s", which is not a field on this schema. Add '
					. 'the field before the rule that acts on it.',
					(string) $item->name,
				));
			}

			$outcomes[] = Outcome\Reconfigure::from($original, $item);
		}

		return $outcomes;
	}

	/**
	 * @throws LogicException if the rule would evaluate its condition and do nothing with the answer
	 */
	private function assertItSaysWhatHappens(): void
	{
		if (!$this->hasOutcomes()) {
			throw new LogicException(
				'A rule must say what happens: attach an outcome with then() or else() before '
				. 'adding it to a schema.',
			);
		}
	}

	private static function pathTo(Field|FieldName|string $field): string
	{
		return (string) FieldScope::of($field instanceof Field ? $field->name : $field);
	}
}
