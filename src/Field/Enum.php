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
 * A closed set of options, one of which was chosen.
 *
 * ### The cases are strings, and only strings
 *
 * They used to be any scalar, provided all of them were the same one. That looked more
 * general and was a trap: **an HTML form submits `"2"`, never `2`**, and membership is
 * decided strictly, so an enum of integers was unreadable for every form submission there
 * has ever been. It worked only for a JSON client that had sent a real integer, which is a
 * narrow enough audience to be a surprise rather than a feature.
 *
 * Nothing was lost by closing it:
 *
 * - **Booleans** are a {@see Boolean} field, which is a two-case enum with a name — and it
 *   has `mustBeAccepted()` and a matcher that suits yes-or-no.
 * - **Regular numeric sequences** are a {@see Number} field with `minValueOf()`,
 *   `maxValueOf()` and `inIncrementsOf()`, which says "every multiple of five from ten to
 *   fifty" in a way a list of cases cannot.
 * - **Irregular ones** — 2.71, 3.14 — are a list of *labels* that happen to look numeric,
 *   and are better carried as strings anyway: `'3.14'` round-trips through a form, JSON and
 *   a database column unchanged, where `3.14` does not.
 *
 * It also makes the value cleanly {@see \Stringable}: `(string) $result->value` is the
 * chosen case, with no rendering decision to make about how a `false` should look.
 *
 * @extends AtomicField<string|null>
 */
final readonly class Enum extends AtomicField
{
	public function __construct(
		public FieldName $name,
		/** @var list<non-empty-string> the options, in the order a renderer should offer them */
		public array $cases,
	) {
		parent::__construct();

		$this->validateCases($this->cases);
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * @param array<mixed> $cases
	 * @throws \InvalidArgumentException if the list is empty, or holds anything but non-empty strings
	 */
	private function validateCases(array $cases): void
	{
		if ($cases === []) {
			throw new \InvalidArgumentException('An enum with no cases accepts nothing, so there is nothing it could be for.');
		}

		foreach ($cases as $case) {
			if (!is_string($case)) {
				throw new \InvalidArgumentException(sprintf(
					'An enum case must be a string, and %s is not. A form submits "2" rather than 2, '
					. 'so a non-string case could never match one. Use Boolean for yes-or-no, Number '
					. 'with a step for a regular sequence, or strings for anything else.',
					get_debug_type($case),
				));
			}

			// A select's placeholder option submits the empty string, so a case spelled that way
			// would be chosen by everybody who chose nothing.
			if ($case === '') {
				throw new \InvalidArgumentException(
					'An empty string cannot be a case: it is what a form submits when nothing was chosen.',
				);
			}
		}
	}

	/**
	 * What a rule may ask about this field: the case list is the vocabulary, and `isIn` asks about
	 * it directly. The text questions come with the chosen case reading back as a string — useful
	 * for a prefixed code, redundant beside `equals` for most enums. No order: a list of options
	 * is not a ranking, and declaring one would be inventing a meaning the author never gave.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Text
	{
		return new Matcher\Text(ValueScope::of($this->name));
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

		assert(is_string($value));

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
