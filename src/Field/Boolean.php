<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Field\Boolean\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;

/**
 * A true/false answer.
 *
 * Takes a PHP `bool` and nothing else. A checkbox that submits `"on"`, a select that submits
 * `"1"`, a JSON body that sends `"true"` — those are all shapes of a *medium*, and converting
 * them is the job of whatever read the request. A schema describes the value, not the wire.
 *
 * @extends AtomicField<bool|null>
 */
final readonly class Boolean extends AtomicField
{
	/** Set by {@see self::mustBeAccepted()}. */
	public bool $requiresAcceptance;

	public function __construct(
		public FieldName $name,
	) {
		parent::__construct();

		$this->requiresAcceptance = self::initially(false);
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * What a rule may ask about this field: yes and no are the whole vocabulary — equals says everything there is to say.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Basic
	{
		return new Matcher\Basic(ValueScope::of($this->name));
	}

	/**
	 * Requires the field to be present and `true` (i.e. "I agree to the terms" checkbox).
	 *
	 * Both halves are needed: making it required alone would accept an explicit `false`, and
	 * the constraint alone would accept the field being left out.
	 */
	public function mustBeAccepted(): static
	{
		return $this->with([
			'requiresAcceptance' => true,
			'optional' => false,
		]);
	}

	protected function parse(mixed $value): Value
	{
		if ($value instanceof Value) {
			return $value;
		}

		// The wrapper is what keeps `false` distinct from "unreadable" now. It used to rely on
		// the `?? $raw` upstream, with a comment explaining the hazard; an object is never falsy,
		// so there is no longer a hazard to explain.
		if (!is_bool($value)) {
			throw MalformedValue::of(Value::class, 'yes or no is submitted as a boolean');
		}

		return new Value($value);
	}

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('accepted', $this->wasAccepted(...), $this->requiresAcceptance),
		);
	}

	private function wasAccepted(Value $parsed): ?bool
	{
		// Nothing was asked unless acceptance was required, so nothing is checked.
		return $this->requiresAcceptance ? $parsed->answer === true : null;
	}
}
