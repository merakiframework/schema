<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Field;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldScope;
use Meraki\Schema\Rule;
use LogicException;

/**
 * A rule being written: one condition, and the outcomes for each way it can go.
 *
 * A rule is a **value**. It is built here, held in a variable if that is useful, and added to a
 * schema explicitly — which is what `whenAllMatch(Closure)` made impossible, since a rule built
 * by a configurator could only ever exist inline.
 *
 * ### Why the else-branch lives on the same object
 *
 * `elseMakeOptional()` is not sugar for a second rule. Writing the inverse by hand means
 * two conditions that are supposed to be opposites, with nothing checking that they stay so: a
 * change to one is silently a change in meaning. Here there is one condition, read once, and
 * the branches cannot disagree.
 *
 * ### Why composition takes conditions and not rules
 *
 * {@see \Meraki\Schema\Facade::allOf()} accepts drafts that carry no outcomes yet. If it took
 * finished rules there would be several sets of outcomes in play and no answer to which of them
 * should fire — so a draft with outcomes is refused there rather than guessed at.
 */
final class Draft
{
	/** @var list<Outcome> */
	private array $outcomes = [];

	/** @var list<Outcome> */
	private array $else = [];

	public function __construct(public readonly Condition $condition)
	{
	}

	/**
	 * Requires the field, whatever its author declared.
	 */
	public function thenRequire(Field|FieldName|string $field): self
	{
		return $this->then(new Outcome\MakeRequired(self::pathTo($field)));
	}

	/**
	 * Makes the field optional, whatever its author declared.
	 */
	public function thenMakeOptional(Field|FieldName|string $field): self
	{
		return $this->then(new Outcome\MakeOptional(self::pathTo($field)));
	}

	/**
	 * Discards whatever was submitted for the field, so it validates as though nothing was.
	 */
	public function thenIgnore(Field|FieldName|string $field): self
	{
		return $this->then(new Outcome\Ignore(self::pathTo($field)));
	}

	public function elseRequire(Field|FieldName|string $field): self
	{
		return $this->else(new Outcome\MakeRequired(self::pathTo($field)));
	}

	public function elseMakeOptional(Field|FieldName|string $field): self
	{
		return $this->else(new Outcome\MakeOptional(self::pathTo($field)));
	}

	public function elseIgnore(Field|FieldName|string $field): self
	{
		return $this->else(new Outcome\Ignore(self::pathTo($field)));
	}

	/**
	 * Attaches an outcome of a kind the named verbs do not cover.
	 *
	 * Hands back a copy, like every wither on a field. It used to write to `$this` and return
	 * itself, which made two fluent idioms with opposite meanings in one library — and quietly
	 * broke the thing {@see \Meraki\Schema\Facade::allOf()} invites you to do:
	 *
	 *     $base = $schema->when($x)->equals('go');
	 *     $a = $base->thenRequire('f1');
	 *     $b = $base->thenRequire('f2');   // $a and $b were one rule carrying both outcomes
	 */
	public function then(Outcome ...$outcomes): self
	{
		$forked = clone $this;
		$forked->outcomes = [...$this->outcomes, ...$outcomes];

		return $forked;
	}

	/**
	 * As {@see self::then()}, for when the condition does not hold. Also hands back a copy.
	 */
	public function else(Outcome ...$outcomes): self
	{
		$forked = clone $this;
		$forked->else = [...$this->else, ...$outcomes];

		return $forked;
	}

	/**
	 * Whether anything has been attached yet — what tells a composed condition from a rule.
	 */
	public function hasOutcomes(): bool
	{
		return $this->outcomes !== [] || $this->else !== [];
	}

	/**
	 * The finished rule.
	 *
	 * @throws LogicException if no outcome was ever attached, which is a rule that would
	 *         evaluate its condition and then do nothing with the answer
	 */
	public function build(): Rule
	{
		if (!$this->hasOutcomes()) {
			throw new LogicException(
				'A rule must say what happens: attach an outcome with then…() or otherwise…() '
				. 'before adding it to a schema.',
			);
		}

		return new Rule(
			$this->condition instanceof ConditionGroup
				? $this->condition
				: new Condition\AllOf($this->condition),
			$this->outcomes,
			$this->else,
		);
	}

	/**
	 * An outcome addresses a field, so a field object is reduced to the path naming it.
	 *
	 * Taking the object and not just the name is worth the overload: it is checked at authoring
	 * time that the thing being required exists, rather than a mistyped string surviving as far
	 * as {@see \Meraki\Schema\Facade::addRule()}.
	 */
	private static function pathTo(Field|FieldName|string $field): string
	{
		$name = match (true) {
			$field instanceof Field => $field->name,
			default => $field,
		};

		return (string) FieldScope::of($name instanceof FieldName ? $name : new FieldName((string) $name));
	}
}
