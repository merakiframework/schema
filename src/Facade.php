<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Brick\DateTime\Clock;
use Brick\DateTime\Clock\SystemClock;
use Meraki\Schema\Exception\InvalidRule;
use Meraki\Schema\Exception\InvalidScope;
use Meraki\Schema\Exception\NothingToValidate;
use Meraki\Schema\Exception\UnknownField;
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
	 * @param Clock|null $clock where *now* comes from for every field this schema builds. A
	 *        source of the instant, never an instant: {@see SystemClock} is stateless and safe to
	 *        share, whereas reading the time once into a property would start giving one request's
	 *        answer to the next.
	 * @param Message\Provider|null $messages where wording comes from, in whatever language a
	 *        request asks for. Optional, and the schema works exactly as it did without one, every
	 *        result simply carries an empty {@see Message\Set}.
	 */
	public function __construct(
		string $name,
		public private(set) Field\Set $fields = new Field\Set(),
		public private(set) Rule\Set $rules = new Rule\Set(),
		?Clock $clock = null,
		public private(set) ?Message\Provider $messages = null,
	) {
		$this->name = new FieldName($name);
		$this->clock = $clock ?? new SystemClock();
	}

	/**
	 * Returns the authored default values for every field on this schema, under its name.
	 * @return array<string, mixed>
	 */
	private static function extractDefaultValues(self $schema): array
	{
		$data = [];

		foreach ($schema->fields as $field) {
			$data[(string) $field->name] = $field->defaultValue;
		}

		return $data;
	}

	public function add(Field ...$fields): self
	{
		$this->fields = $this->fields->add(...$fields);

		return $this;
	}

	/**
	 * Resolve this schema against request data, without validating anything.
	 *
	 * Every field will come back with {@see ValidationStatus::Pending}.
	 *
	 * @throws NothingToValidate If there are no fields on this schema
	 * @param object|null $prefilledWith values looked up for this one user
	 */
	public function resolve(?object $data = null, ?object $prefilledWith = null, PrefillPolicy $policy = PrefillPolicy::Checked): SchemaValidationResult
	{
		return $this->against(
			$data,
			$prefilledWith,
			static fn(Field $f, mixed $v, array $o, ValueSource $s): AggregatedValidationResult => $f->resolve($v, $o, $s),
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
	 * ### The language is part of the request, not part of the schema
	 *
	 * A definition is the same in every language — the same data passes or fails identically — so
	 * the locale arrives here rather than being fixed when the schema is built:
	 *
	 *     $result = $schema->validate($data, locale: 'en-AU');
	 *     $result->forField('billing')->messages->forPart('postal_code')->first;
	 *
	 * Which means one schema serves every reader. It also means a missing language can never change
	 * an outcome: an unsupported tag, or none at all, leaves every result carrying an empty
	 * {@see Message\Set} and every verdict exactly as it was.
	 *
	 * @throws NothingToValidate If there are no fields on this schema
	 * @param object|null $prefilledWith values looked up for this one user
	 * @param PrefillPolicy $policy whether a surviving prefill still has to satisfy its field
	 * @param string|null $locale what language to report failures in, as a BCP 47 tag. Ignored when
	 *        the schema was built without a {@see Message\Provider}.
	 */
	public function validate(
		?object $data = null,
		?object $prefilledWith = null,
		PrefillPolicy $policy = PrefillPolicy::Checked,
		?string $locale = null,
	): SchemaValidationResult {
		return $this->against(
			$data,
			$prefilledWith,
			static fn(Field $f, mixed $v, array $o, ValueSource $s): AggregatedValidationResult => $f->validate($v, $o, $s, $policy),
			$locale,
		);
	}

	/**
	 * Run a request against a private copy of this schema.
	 *
	 * @throws NothingToValidate If there are no fields on this schema
	 * @param callable(Field, mixed, list<AppliedOutcome>, ValueSource): AggregatedValidationResult $each
	 * @param string|null $locale the language to report failures in, or null for none
	 */
	private function against(
		?object $data,
		?object $prefilledWith,
		callable $each,
		?string $locale = null,
	): SchemaValidationResult {
		if ($this->fields->isEmpty()) {
			throw NothingToValidate::theSchemaHasNoFields((string) $this->name);
		}

		$given = $this->extractData($data);
		$prefilled = $prefilledWith === null ? [] : $this->extractData($prefilledWith);
		$working = clone $this;

		// Conditions resolve values from $given via ScopeResolver, so nothing is staged
		// onto the copies: they carry the definition only, and rules change that.
		$applied = $working->applyRules($given);

		/** @var array<string, list<AppliedOutcome>> $byField */
		$byField = [];

		foreach ($applied as $outcome) {
			$byField[self::fieldNameIn($outcome->outcome->getScope())][] = $outcome;
		}

		// prevent N lookups in the loop below, and make sure every field has an entry even if no rule touched it
		$translator = ($locale === null || $this->messages === null) ? null : $this->messages->forLocale($locale);
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
			$result = $each($field, $value, $outcomes, $source);

			// After the verdict, never before. Nothing about a language may change what was
			// decided, and doing it here rather than inside the field is what keeps that true —
			// a field has no provider and cannot acquire one.
			$results[] = ($translator !== null && $result instanceof FieldResult) ? $result->withMessagesFrom($translator) : $result;
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
	 * @return array<string, mixed> one entry per field on the schema, under its name
	 */
	private function extractData(object|null $data): array
	{
		if ($data === null) {
			return self::extractDefaultValues($this);
		}

		$publicVars = get_object_vars($data);
		$extracted = [];

		foreach ($this->fields as $field) {
			$name = (string) $field->name;
			$extracted[$name] = array_key_exists($name, $publicVars) ? $publicVars[$name] : null;
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
	 *             ->elseMakeOptional($logBookTime)
	 *     );
	 */
	public function when(Field|FieldName|Scope|string $subject): Rule\Matcher\OrderedText
	{
		// Every verb, because a string or a part scope cannot be resolved to a type here. A field
		// handed in by value could be asked for its own matcher, but the return type could not
		// narrow to match — PHP has no way to vary a return by argument — so it would read as
		// typed and not be. One honest answer beats two that look alike.
		return new Rule\Matcher\OrderedText(self::scopeFor($subject));
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
	 * @throws InvalidRule if one of them is a finished rule rather than a condition
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
					throw InvalidRule::combinesFinishedRules();
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
			// Against this schema, because an outcome is the *difference* between the field an
			// author configured and the one registered here — and only this side has the latter.
			$rule = $rule->buildAgainst($this->fields);
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
	 * @throws InvalidRule naming the field and the value it cannot hold
	 */
	private function assertExpectationsAreReadable(Rule $rule): void
	{
		foreach (self::comparisonsIn($rule->condition) as $comparison) {
			// The condition writes its own sentence, because the reasons differ and this cannot
			// tell which applied. An unreadable expectation and a field with no order are
			// different mistakes needing different corrections, and one message describing both
			// would be wrong about at least one of them.
			$why = $comparison->whyItCouldNeverHold($this);

			if ($why !== null) {
				throw InvalidRule::because($why);
			}
		}
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
	 * @throws InvalidRule naming the rule's bad scope
	 */
	private function assertScopesAreAddressable(Rule $rule): void
	{
		$resolver = new ScopeResolver($this);

		$scopes = [
			...$rule->condition->getScopes(),
			...array_map(static fn(Rule\Outcome $o): Scope => $o->getScope(), $rule->outcomes),
			...array_map(static fn(Rule\Outcome $o): Scope => $o->getScope(), $rule->else),
		];

		foreach ($scopes as $scope) {
			try {
				$resolver->resolve($scope);
			} catch (InvalidScope | UnknownField $e) {
				throw InvalidRule::addressesSomethingTheSchemaCannot((string) $scope, $e);
			}
		}
	}
}
