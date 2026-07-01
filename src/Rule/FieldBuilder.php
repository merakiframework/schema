<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Field;
use Meraki\Schema\Rule;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Outcome;
use LogicException;

/**
 * A field-aware, type-safe facade for authoring rules (used by {@see Field::pairWith()}).
 * Unlike {@see Builder}, conditions/outcomes are expressed against actual Field objects
 * — scopes are derived from their names (`#/fields/{name}/value`, `#/fields/{name}`) —
 * and it produces the same declarative {@see Rule} objects, so pairings still serialize.
 */
final class FieldBuilder
{
	private ?Field $subject = null;

	/** @var list<Condition> */
	private array $conditions = [];

	/** Combine the conditions with AllOf ('all') or AnyOf ('any'). */
	private string $combine = 'all';

	/** @var list<Outcome> */
	private array $outcomes = [];

	public function when(Field $field): self
	{
		$this->subject = $field;
		return $this;
	}

	/** Add another condition (on $field) combined with AND. */
	public function andWhen(Field $field): self
	{
		$this->subject = $field;
		$this->combine = 'all';
		return $this;
	}

	/** Add another condition (on $field) combined with OR. */
	public function orWhen(Field $field): self
	{
		$this->subject = $field;
		$this->combine = 'any';
		return $this;
	}

	public function equals(mixed $value): self
	{
		$this->conditions[] = new Condition\Equals($this->valueScope(), $value);
		return $this;
	}

	public function notEquals(mixed $value): self
	{
		$this->conditions[] = new Condition\NotEquals($this->valueScope(), $value);
		return $this;
	}

	public function thenRequire(Field $field): self
	{
		$this->outcomes[] = new Outcome\_Require($this->fieldScope($field));
		return $this;
	}

	public function thenMakeOptional(Field $field): self
	{
		$this->outcomes[] = new Outcome\MakeOptional($this->fieldScope($field));
		return $this;
	}

	public function thenIgnore(Field $field): self
	{
		$this->outcomes[] = new Outcome\Ignore($this->fieldScope($field));
		return $this;
	}

	/**
	 * @return list<Rule>
	 */
	public function rules(): array
	{
		if ($this->conditions === [] || $this->outcomes === []) {
			return [];
		}

		$group = $this->combine === 'any'
			? new Condition\AnyOf(...$this->conditions)
			: new Condition\AllOf(...$this->conditions);

		return [new Rule($group, $this->outcomes)];
	}

	private function valueScope(): string
	{
		if ($this->subject === null) {
			throw new LogicException('Call when() before equals()/notEquals().');
		}

		return '#/fields/' . $this->subject->name . '/value';
	}

	private function fieldScope(Field $field): string
	{
		return '#/fields/' . $field->name;
	}
}
