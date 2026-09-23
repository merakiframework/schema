<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Field\Enum\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;

/**
 * @template T of scalar
 * @extends AtomicField<string|null>
 */
final readonly class Enum extends AtomicField
{
	public function __construct(
		public FieldName $name,
		/** @param list<T> $cases*/
		public array $cases,
	) {
		parent::__construct();

		$this->validateCases($this->cases);
		$this->constraints = $this->defineConstraints();
	}

	private function validateCases(array $cases): void
	{
		if (empty($cases)) {
			throw new \InvalidArgumentException('Enum cases cannot be empty.');
		}

		$type = null;

		foreach ($cases as $case) {
			if (!is_scalar($case)) {
				throw new \InvalidArgumentException('Enum cases must be scalar values.');
			}

			if ($type === null) {
				$type = gettype($case);
			} elseif (gettype($case) !== $type) {
				throw new \InvalidArgumentException('Enum cases must be of the same type.');
			}
		}
	}

	/**
	 * What a rule may ask about this field: the case list is the vocabulary, and isIn asks about it.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Basic
	{
		return new Matcher\Basic(ValueScope::of($this->name));
	}

	/**
	 * The list of cases **is** the type, so a value outside it is a shape failure.
	 *
	 * Not a constraint, and this was tried the other way round. The argument for a constraint was
	 * that a renderer wants to say "must be one of: free, pro, team" and needs the list to
	 * interpolate — but an enum renderer already reads {@see self::$cases} to draw the options at
	 * all, so it never needed a bound to carry them. What the constraint bought was a second,
	 * parallel answer to a question the shape had already answered.
	 *
	 * Reading it as shape is also what makes it consistent: `Date` reports an unparseable string
	 * as shape rather than as a `format` constraint, for exactly this reason. "That is not one of
	 * these" and "that is not a date" are the same kind of statement — the value is not the kind
	 * of thing this field holds.
	 *
	 * Strict, including type. A field's cases are all of one type — see {@see self::validateCases()}
	 * — so `1` and `'1'` are never both cases, and treating them as one would only hide a mistake
	 * somewhere upstream.
	 */
	protected function parse(mixed $value): Value
	{
		if ($value instanceof Value) {
			return $value;
		}

		// The one shape check that stays on the field, because it is a fact about *this* field
		// rather than about the value — see Enum\Value. Membership is the shape: the list is
		// the type, so being outside it is not a rule being broken, it is not being one of
		// these at all.
		if (!in_array($value, $this->cases, true)) {
			throw MalformedValue::of(Value::class, sprintf(
				'%s is not one of the cases this field offers',
				is_scalar($value) ? var_export($value, true) : get_debug_type($value),
			));
		}

		assert(is_string($value) || is_int($value) || is_float($value) || is_bool($value));

		return new Value($value);
	}

	/**
	 * None. Membership is the shape, and there is nothing else an enum checks.
	 */
	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set();
	}
}
