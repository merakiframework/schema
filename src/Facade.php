<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Brick\DateTime\Clock;
use Brick\DateTime\Clock\SystemClock;
use InvalidArgumentException;
use Meraki\Schema\Field;
use Meraki\Schema\Rule;
use Meraki\Schema\PrefillPolicy;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\ValueSource;
use Meraki\Schema\Rule\AppliedOutcome;
use Meraki\Schema\SchemaValidationResult;
use Meraki\Schema\Rule\Condition;

final class Facade
{
	use Field\BuildsFields;

	public readonly FieldName $name;

	/**
	 * @param Field\Set $fields readable by anyone, writable only by this class. A schema is built
	 *        once and then read by every request that follows, so "the definition cannot change
	 *        underneath a request" has to be enforced rather than intended — and it was not: the
	 *        property was public, the set was mutable, and {@see self::copyForRequest()} shares
	 *        the very same instance across concurrent requests.
	 * @param Rule\Set $rules the same, for the same reason.
	 * @param Clock|null $clock where *now* comes from for every field this schema builds. A
	 *        source of the instant, never an instant: {@see SystemClock} is stateless and safe to
	 *        share, whereas reading the time once into a property would start giving one request's
	 *        answer to the next.
	 */
	public function __construct(
		string $name,
		public private(set) Field\Set $fields = new Field\Set(),
		public private(set) Rule\Set $rules = new Rule\Set(),
		?Clock $clock = null,
	) {
		$this->name = new FieldName($name);
		$this->clock = $clock ?? new SystemClock();
	}

	private static function extractDefaultValues(self $schema): array
	{
		$data = [];

		foreach ($schema->fields as $field) {
			$data[(string) $field->name] = $field->defaultValue;
		}

		return $data;
	}

	/**
	 * Registers a field. Build it with {@see \Meraki\Schema\Field\Factory} and finish
	 * configuring it first — a field is sealed, so anything done to it afterwards produces a
	 * copy this schema does not hold.
	 *
	 *     $schema->add($fields->createTextField('username')->minLengthOf(3));
	 */
	public function add(Field ...$fields): self
	{
		foreach ($fields as $field) {
			// No dotted-name guard any more: a structured field owns its whole value rather than
			// registering sub-fields here, so there are no joined names to distinguish — and
			// FieldName refuses a dot outright.
			//
			// And no back-pointer to this schema. A field is sealed, so it could not hold one, and
			// the capability that needed it went with it.
			$this->fields = $this->fields->add($field);
		}

		return $this;
	}



	/**
	 * Resolves this schema against one request's data, without checking anything.
	 *
	 * Every field comes back {@see ValidationStatus::Pending}, which is what a form being
	 * rendered for the first time actually is. Nothing is written to this schema, so the
	 * same instance can resolve two requests at once without them meeting.
	 *
	 * @param object|null $prefilledWith values looked up for this one user
	 */
	public function resolve(
		?object $data = null,
		?object $prefilledWith = null,
		PrefillPolicy $policy = PrefillPolicy::Checked,
	): SchemaValidationResult {
		return $this->against(
			$data,
			$prefilledWith,
			static fn(Field $f, mixed $v, array $o, ValueSource $s): AggregatedValidationResult
				=> $f->resolve($v, $o, $s),
		);
	}

	/**
	 * Resolves and checks. Stores nothing on this schema.
	 *
	 * ### Prefills are per request, and that is the whole point
	 *
	 * An authored default is a constant the schema's author typed: it lives on the definition,
	 * serialises with it, and is the same for everyone. A **prefill** is one user's data —
	 * their saved address, their stored email — and it arrives here, is used for this request,
	 * and is never written anywhere.
	 *
	 * That separation is what makes "a serialised schema can never contain user data" true by
	 * construction rather than by discipline. It replaced a `prefill()` method that wrote the
	 * values onto the fields, which meant a schema shared across requests handed one user's
	 * details to the next — defect B9, and the last instance of that shape anywhere in the core.
	 *
	 * Precedence is submitted, then prefilled, then the authored default. What came back is on
	 * {@see ResolvedField::$source}, so a form can mark a prefilled field differently from one
	 * the user typed into.
	 *
	 * @param object|null $prefilledWith values looked up for this one user
	 * @param PrefillPolicy $policy whether a surviving prefill still has to satisfy its field
	 */
	public function validate(
		?object $data = null,
		?object $prefilledWith = null,
		PrefillPolicy $policy = PrefillPolicy::Checked,
	): SchemaValidationResult {
		return $this->against(
			$data,
			$prefilledWith,
			static fn(Field $f, mixed $v, array $o, ValueSource $s): AggregatedValidationResult
				=> $f->validate($v, $o, $s, $policy),
		);
	}

	/**
	 * Runs one request against a private copy of this schema.
	 *
	 * Rules change fields by replacing them, so the copy that needs making is the *set*, not
	 * each field in it: the authored definition is never touched, and two requests cannot
	 * interfere. A field no rule altered is therefore still the authored instance — identity
	 * holds for the common case, and differs only where something really did change it.
	 *
	 * @param callable(Field, mixed, list<AppliedOutcome>, ValueSource): AggregatedValidationResult $each
	 */
	private function against(?object $data, ?object $prefilledWith, callable $each): SchemaValidationResult
	{
		$given = $this->extractData($data);
		$prefilled = $prefilledWith === null ? [] : $this->extractData($prefilledWith);
		$working = $this->copyForRequest();

		// Conditions resolve values from $given via ScopeResolver, so nothing is staged
		// onto the copies: they carry the definition only, and rules change that.
		$applied = $working->applyRules($given);

		/** @var array<string, list<AppliedOutcome>> $byField */
		$byField = [];

		foreach ($applied as $outcome) {
			$byField[self::fieldNameIn($outcome->outcome->getScope())][] = $outcome;
		}

		$results = [];

		foreach ($working->fields as $field) {
			$name = (string) $field->name;
			$outcomes = $byField[$name] ?? [];

			// A rule that ignores a field means "treat this as though nothing was sent", so
			// the value never reaches the field. Reading that from the outcomes rather than
			// a flag on the field keeps it a fact about this request.
			$ignored = false;

			foreach ($outcomes as $applied) {
				if ($applied->is(Rule\Outcome\Ignore::class)) {
					$ignored = true;
					break;
				}
			}

			// Submitted beats prefilled, and a rule that ignores the field discards both: the
			// point of ignoring is that nothing was meant for this field on this request, and a
			// prefill standing in would quietly undo that.
			[$value, $source] = match (true) {
				$ignored => [null, ValueSource::None],
				($given[$name] ?? null) !== null => [$given[$name], ValueSource::Submitted],
				($prefilled[$name] ?? null) !== null => [$prefilled[$name], ValueSource::Prefilled],
				default => [null, ValueSource::None],
			};

			// The field settles Default from here: only it knows whether it has one.
			$results[] = $each($field, $value, $outcomes, $source);
		}

		return new SchemaValidationResult($this->clock->getTime(), ...$results);
	}

	/**
	 * Runs every rule in order against this (per-request) copy, and reports what they did.
	 *
	 * The fold lives here rather than on {@see Rule\Set} because this is the only class that may
	 * write `$fields`. That is not a workaround for the visibility — it is the visibility saying
	 * something true: a rule set reaching into a schema it was handed and reassigning its fields
	 * was action at a distance, and the one caller it had is right here.
	 *
	 * Order matters, and is why evaluation and application interleave rather than happening in
	 * two passes: a rule reading `#/fields/x/optional` must see the value as it stands when that
	 * rule runs, including any change an earlier rule made.
	 *
	 * @param array<string, mixed> $given
	 * @return list<AppliedOutcome>
	 */
	private function applyRules(array $given): array
	{
		$applied = [];

		foreach ($this->rules as $rule) {
			foreach ($rule->evaluate($this, $given) as $outcome) {
				$name = $outcome->outcome->getScope()->field;

				// An outcome is an operation, so it is handed the field as it currently stands
				// and what it returns takes that field's place.
				$this->fields = $this->fields->replace(
					$outcome->outcome->applyTo($this->fields->getByName($name)),
				);

				$applied[] = $outcome;
			}
		}

		return $applied;
	}

	/**
	 * A copy whose field set can be changed without touching this schema's.
	 *
	 * It starts out sharing the very same {@see Field\Set} instance, which is safe because
	 * every way of changing one returns a new set rather than writing to it: an outcome
	 * assigns `$copy->fields` a replacement, and this schema's own property still points at
	 * the original.
	 *
	 * That was the argument before it was true. `Field\Set::mutableAdd()` was public, so a
	 * caller could change the shared instance in place and every concurrent request would see
	 * it. It is private now, and `$fields` is `private(set)`, so the only writes are the
	 * replacement above — which is what makes sharing the instance safe rather than merely
	 * intended.
	 *
	 * The fields themselves are not copied, and must not be. They are immutable, so a copy
	 * could differ from the original in nothing but identity — and identity is the thing worth
	 * keeping, since it is how a caller recognises the field it authored in the result it gets
	 * back.
	 */
	private function copyForRequest(): self
	{
		return new self((string) $this->name, $this->fields, $this->rules);
	}

	/**
	 * The field a scope points at. Every scope names one, so this no longer has to pick
	 * segments apart and hope.
	 */
	private static function fieldNameIn(Scope $scope): string
	{
		return (string) $scope->field;
	}

	/**
	 * Reads a submitted payload as values by field name.
	 *
	 * **An object, not an array** — the same rule every structured field applies, for the same
	 * reason: a payload is a record of named fields, and an array means a list. Accepting both
	 * here while refusing arrays one level down would have been the inconsistency the rule exists
	 * to remove, and the top level is where a port is most likely to hand over `$_POST` unchanged.
	 *
	 * Converting is the port's job. `json_decode($body)` already gives objects — it is the
	 * `true` second argument that does not.
	 *
	 * @throws InvalidArgumentException if handed an array
	 */
	private function extractData(object|null $data): array
	{
		if ($data === null) {
			return self::extractDefaultValues($this);
		}

		// get_object_vars() only exposes plain public properties: objects that
		// expose their values through __get()/accessors would have every field
		// silently fed null. isset()/?? cannot be used either, as they invoke
		// __isset() (which value objects often omit), so read each declared
		// public property directly and fall back to __get() when present.
		$publicVars = get_object_vars($data);
		$hasMagicGetter = method_exists($data, '__get');
		$extracted = [];

		foreach ($this->fields as $field) {
			$name = (string) $field->name;

			$extracted[$name] = match (true) {
				array_key_exists($name, $publicVars) => $publicVars[$name],
				$hasMagicGetter => $data->{$name},
				default => null,
			};
		}

		return $extracted;
	}
	/**
	 * Starts a rule by naming what it asks about.
	 *
	 * A field is read as the value it was given (`#/fields/x/value`), which is what a rule
	 * almost always means. To ask about the definition instead — "when this field's minimum is
	 * 18" — pass the scope outright: `when(PropertyScope::of('age', 'min'))`.
	 *
	 *     $schema->addRule(
	 *         $schema->when($hasLogBook)->equals(true)
	 *             ->thenRequire($logBookTime)
	 *             ->otherwiseMakeOptional($logBookTime)
	 *     );
	 */
	public function when(Field|FieldName|Scope|string $subject): Rule\Matcher
	{
		return new Rule\Matcher(self::scopeFor($subject));
	}

	/**
	 * One rule that fires when every one of these conditions holds.
	 *
	 * Takes conditions, never rules: outcomes attach to the composed result, so there is
	 * exactly one set of them and no question of whose fire.
	 *
	 *     $schema->allOf(
	 *         $schema->when($whoFor)->equals('someone_else'),
	 *         $schema->when($whoManages)->equals('participant'),
	 *     )->thenRequire($email)
	 */
	public function allOf(Rule\Draft|Rule\Condition ...$conditions): Rule\Draft
	{
		return new Rule\Draft(new Condition\AllOf(...self::conditionsIn($conditions)));
	}

	/**
	 * One rule that fires when any one of these conditions holds.
	 */
	public function anyOf(Rule\Draft|Rule\Condition ...$conditions): Rule\Draft
	{
		return new Rule\Draft(new Condition\AnyOf(...self::conditionsIn($conditions)));
	}

	/**
	 * @param list<Rule\Draft|Rule\Condition> $conditions
	 * @return list<Rule\Condition>
	 * @throws InvalidArgumentException if one of them is a finished rule rather than a condition
	 */
	private static function conditionsIn(array $conditions): array
	{
		return array_map(
			static function (Rule\Draft|Rule\Condition $condition): Rule\Condition {
				if ($condition instanceof Rule\Condition) {
					return $condition;
				}

				// Composing rules that already carry outcomes has no single answer — which
				// set should fire, and under which of the combined conditions? Refusing it
				// keeps "one then/otherwise per rule" true by construction.
				if ($condition->hasOutcomes()) {
					throw new InvalidArgumentException(
						'allOf()/anyOf() combine conditions, not finished rules. Attach the '
						. 'outcomes to the combined rule instead of to the parts.',
					);
				}

				return $condition->condition;
			},
			$conditions,
		);
	}

	/**
	 * What a rule's subject points at: a field means the value it was given.
	 */
	private static function scopeFor(Field|FieldName|Scope|string $subject): Scope
	{
		return match (true) {
			$subject instanceof Scope => $subject,
			$subject instanceof Field => ValueScope::of($subject->name),
			$subject instanceof FieldName => ValueScope::of($subject),
			default => ValueScope::of(new FieldName($subject)),
		};
	}

	public function addRule(Rule|Rule\Draft $rule): self
	{
		if ($rule instanceof Rule\Draft) {
			$rule = $rule->build();
		}

		$this->assertScopesAreAddressable($rule);
		$this->assertExpectationsAreReadable($rule);

		$this->rules = $this->rules->add($rule);

		return $this;
	}

	/**
	 * Checks that every value a rule compares against is one the field it names could actually hold.
	 *
	 * A comparison the field cannot read is unequal to every input there will ever be, so the rule
	 * is dead — and a dead rule raises nothing, which makes it indistinguishable from one whose
	 * condition simply never held. `when('age')->equals('eighteen')` on a number field is the shape
	 * of it.
	 *
	 * Written where the rule is, like the scope check above and for the same reason.
	 *
	 * @throws InvalidArgumentException naming the field and the value it cannot hold
	 */
	private function assertExpectationsAreReadable(Rule $rule): void
	{
		foreach (self::comparisonsIn($rule->condition) as $comparison) {
			if ($comparison->expectationIsReadable($this)) {
				continue;
			}

			throw new InvalidArgumentException(sprintf(
				'The rule compares "%s" against %s, which that field cannot hold — so the '
				. 'comparison could never be true and the rule would never fire.',
				(string) $comparison->scope,
				self::describe($comparison->expected),
			));
		}
	}

	/**
	 * A value as it should read in a message: what was written, not just its type.
	 *
	 * "cannot hold string" leaves the author hunting for which string. `cannot hold 'eighteen'`
	 * points straight at it.
	 */
	private static function describe(mixed $value): string
	{
		return match (true) {
			is_string($value) => "'" . $value . "'",
			is_scalar($value) => var_export($value, true),
			default => get_debug_type($value),
		};
	}

	/**
	 * Every comparison inside a condition, however deeply it was composed.
	 *
	 * @return list<Condition\Comparison>
	 */
	private static function comparisonsIn(Rule\Condition $condition): array
	{
		if ($condition instanceof Condition\Comparison) {
			return [$condition];
		}

		if (!$condition instanceof Rule\ConditionGroup) {
			return [];
		}

		$found = [];

		foreach ($condition->conditions() as $inner) {
			$found = [...$found, ...self::comparisonsIn($inner)];
		}

		return $found;
	}

	/**
	 * Adds several independent rules. Each is its own rule, with its own condition — this is
	 * not a way of combining them; {@see self::allOf()} is.
	 */
	public function addRules(Rule|Rule\Draft ...$rules): self
	{
		foreach ($rules as $rule) {
			$this->addRule($rule);
		}

		return $this;
	}

	/**
	 * Checks that every scope a rule mentions addresses something this schema really has.
	 *
	 * A scope typo used to surface as a 500 on whichever user request first matched the
	 * rule; here it fails where the rule is written. The cost is an ordering constraint
	 * that did not exist before — a rule can only be added once the fields it names are —
	 * which is the trade the check is worth making.
	 *
	 * @throws InvalidArgumentException naming the rule's bad scope
	 */
	private function assertScopesAreAddressable(Rule $rule): void
	{
		$resolver = new ScopeResolver($this);

		$scopes = [
			...$rule->condition->getScopes(),
			...array_map(static fn(Rule\Outcome $o): Scope => $o->getScope(), $rule->outcomes),
			...array_map(static fn(Rule\Outcome $o): Scope => $o->getScope(), $rule->otherwise),
		];

		foreach ($scopes as $scope) {
			try {
				$resolver->resolve($scope);
			} catch (InvalidArgumentException $e) {
				throw new InvalidArgumentException(sprintf(
					'The rule targets "%s", which this schema cannot address: %s',
					(string) $scope,
					$e->getMessage(),
				), previous: $e);
			}
		}
	}
}
