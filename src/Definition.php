<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Exception\InvalidRule;
use Meraki\Schema\Exception\NothingToValidate;
use Meraki\Schema\Rule\AppliedOutcome;
use Meraki\Schema\Rule\Condition;
use Brick\DateTime\Clock;
use Brick\DateTime\Clock\SystemClock;

/**
 * A schema: the fields somebody is being asked for, and the rules between them.
 *
 * This is the thing you build once and keep. It holds what the author wrote and nothing else —
 * no submitted value, no verdict, no language. Judging a request hands back a
 * {@see SchemaValidationResult} and leaves this exactly as it was, which is what makes one
 * instance safe to serve every request in a long-running process, concurrently.
 *
 *     $schema = new Definition('signup');
 *     $schema->add($schema->createTextField('username')->minLengthOf(3));
 *
 *     $result = $schema->validate((object) ['username' => 'jo']);
 *
 * ### Why "Definition"
 *
 * It was called `Facade` until this release, which named a pattern rather than a thing — and not
 * even accurately, since a facade simplifies a subsystem you could still use directly, and there
 * is no schema underneath this one. The codebase had already voted against the name: every port
 * wrote `Facade $schema`, the result class was `SchemaValidationResult` rather than
 * `FacadeValidationResult`, and this was the only class in `src/` with no docblock, because a
 * pattern name leaves nothing to explain.
 *
 * `Definition` is the distinction the whole library turns on: a definition is what the author
 * wrote, a result is what one request produced, and nothing per-request may touch the first. The
 * name now says which side of that line this sits on.
 *
 * `Schema` would have stuttered — `Meraki\Schema\Schema` — and the convention here is already a
 * structural class name with a domain variable, as in `Field\Set $fields` and `Rule\Set $rules`.
 * `Definition $schema` is the same shape.
 *
 * Not to be confused with {@see Field\Definition}, which is the trait holding the configuration
 * half of a *field*. Different namespaces, different jobs — and inside `Meraki\Schema\Field`
 * the bare name means the trait, so anything there referring to this class qualifies it.
 */
final class Definition
{
	use Field\BuildsFields;

	public readonly FieldName $name;

	/**
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
	 * Stops rather than reports if a record carries a key its value does not declare: that is a
	 * {@see \Meraki\Schema\Exception\BrokenInputContract}, and it is deliberately not caught.
	 * Not a `@throws` tag because the raise happens inside a value constructor reached through a
	 * closure, which static analysis cannot trace — see that class for what a port does with it.
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
	 * ### The wording is part of the request, not part of the schema
	 *
	 * A definition is the same in every language — the same data passes or fails identically — so
	 * both halves of the wording arrive here rather than being fixed when the schema is built:
	 *
	 *     $result = $schema->validate($data, locale: 'en-AU', messages: $provider);
	 *     $result->forField('billing')->messages->forPart('postal_code')->first;
	 *
	 * The locale always worked this way; the **provider** did not, and sat on the constructor
	 * beside the fields. That made it part of the definition in every way that mattered: a schema
	 * built in a service container was stuck with whichever pack that container had, serialising
	 * dropped it silently, and a caller holding a schema could not swap the wording for one
	 * request without rebuilding the whole thing. Nothing about it was ever a fact about the
	 * *schema*, which is the test this library applies to anything on a definition.
	 *
	 * Which means one schema serves every reader. It also means missing wording can never change
	 * an outcome: no provider, an unsupported tag, or no tag at all leaves every result carrying
	 * an empty {@see Message\Set} and every verdict exactly as it was.
	 *
	 * {@see self::resolve()} takes neither, because it reaches no verdict and only a failure has
	 * anything to say.
	 *
	 * Stops rather than reports on a {@see \Meraki\Schema\Exception\BrokenInputContract}, as
	 * {@see self::resolve()} does and for the same reason.
	 *
	 * @throws NothingToValidate If there are no fields on this schema
	 * @param object|null $prefilledWith values looked up for this one user
	 * @param PrefillPolicy $policy whether a surviving prefill still has to satisfy its field
	 * @param string|null $locale what language to report failures in, as a BCP 47 tag. Ignored
	 *        without a provider to ask.
	 * @param Message\Provider|null $messages where that wording comes from. Optional: without one
	 *        every result carries an empty {@see Message\Set} and every verdict is unchanged.
	 */
	public function validate(
		?object $data = null,
		?object $prefilledWith = null,
		PrefillPolicy $policy = PrefillPolicy::Checked,
		?string $locale = null,
		?Message\Provider $messages = null,
	): SchemaValidationResult {
		return $this->against(
			$data,
			$prefilledWith,
			static fn(Field $f, mixed $v, array $o, ValueSource $s): AggregatedValidationResult => $f->validate($v, $o, $s, $policy),
			$locale,
			$messages,
		);
	}

	/**
	 * Run a request against a private copy of this schema.
	 *
	 * @throws NothingToValidate If there are no fields on this schema
	 * @param callable(Field, mixed, list<AppliedOutcome>, ValueSource): AggregatedValidationResult $each
	 * @param string|null $locale the language to report failures in, or null for none
	 * @param Message\Provider|null $messages where that wording comes from, or null for none
	 */
	private function against(
		?object $data,
		?object $prefilledWith,
		callable $each,
		?string $locale = null,
		?Message\Provider $messages = null,
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

		// prevent N lookups in the loop below and make sure field messages get same translator wording
		$translator = ($locale === null || $messages === null) ? null : $messages->forLocale($locale);
		$results = [];

		foreach ($working->fields as $field) {
			$name = (string) $field->name;
			$outcomes = $byField[$name] ?? [];

			// A rule that ignores a field means "treat this as though nothing was sent", so
			// the value never reaches the field. Read from the outcomes rather than from a flag on
			// the field, which keeps it a fact about this request.
			$ignored = Rule\Application::ignores($outcomes);

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

		// Off the working copy, not this schema: the instant a result records comes from whatever
		// judged it. The same clock today, and reading it here is what keeps that so — a copy
		// that had lost it would answer from a fresh SystemClock, and nothing would say so.
		return new SchemaValidationResult($working->clock->getTime(), ...$results);
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
		[$this->fields, $applied] = Rule\Application::of($this->rules, $this->fields, $given);

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
	 * A null payload is an empty one, and says only that nothing was submitted. Every field
	 * reports absent and settles its own authored default from there, which is where that
	 * decision belongs: seeding the defaults here instead put them in `$given`, where nothing
	 * downstream could tell them from something the user sent. A default was reported as
	 * {@see ValueSource::Submitted}, and a prefill lost to it — inverting the precedence
	 * {@see self::validate()} promises.
	 *
	 * @return array<string, mixed> one entry per field on the schema, under its name
	 */
	private function extractData(object|null $data): array
	{
		$publicVars = $data === null ? [] : get_object_vars($data);
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
	 * 18" — pass the scope outright: `when(PropertyScope::of('age', 'minValue'))`.
	 *
	 *     $schema->addRule(
	 *         $schema->when($hasLogBook)->equals(true)
	 *             ->then($logBookTime->makeRequired())
	 *             ->else($logBookTime->makeOptional())
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
	 *     )->then($email->makeRequired())
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

		Rule\Guards::check($rule, $this->fields);

		$this->rules = $this->rules->add($rule);

		return $this;
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

}
