<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AtomicField;
use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\Field;
use Meraki\Schema\Field\Collection\Item;
use Meraki\Schema\Field\Collection\Result;
use Meraki\Schema\Field\Collection\Value;
use Meraki\Schema\FieldName;
use Meraki\Schema\PrefillPolicy;
use Meraki\Schema\ResolvedField;
use Meraki\Schema\Rule;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Rule\Quantifier;
use Meraki\Schema\Scope;
use Meraki\Schema\ValueScope;
use Meraki\Schema\ValueSource;

/**
 * A repeatable list of items, each item a group of fields — the *template*.
 *
 * Every row carries a **name**, and resolves to a record keyed by the template's field names — so
 * a schedule of sessions is submitted and comes back as
 * `['opening' => ['starts_at' => …, 'ends_at' => …], …]`. `minCount`/`maxCount` bound the list;
 * every row is checked against every template field.
 *
 * The name is what makes a row addressable: `#/fields/sessions/value/opening/starts_at` means the
 * same row on every request, which a position could never promise.
 *
 * This is what `Composite` was kept for. A composite was a field made of other fields with
 * exactly one of each, which is a collection whose length happens to be one — so it earned its
 * own type only by being less general.
 *
 * ### Blank rows are the port's business
 *
 * A repeatable section is usually rendered with a spare row ready to fill in, and it arrives empty.
 * This used to offer `dropBlankItems()` to discard those, and it is gone: whatever was submitted is
 * taken as intentional, here as everywhere else — see docs/CODING-STYLE.md.
 *
 * The reason is not only consistency. The core could never do the job properly, because "blank"
 * is a question about the medium. An untouched file input arrives as an array with
 * `error = UPLOAD_ERR_NO_FILE`, which is not blank by any structural test; a hidden row index or
 * a `_destroy` flag is always present and never blank, so a renderer adding one silently switched
 * the feature off. `meraki/schema-html` knows about both. A JSON client has no such problem — it
 * sends the rows it means — which is the clearest sign the concern belongs to a port and not here.
 *
 * ### Why this implements `Field` rather than extending `AtomicField`
 *
 * It shares everything about *being* a field — the configuration, the copy-on-change rule, the
 * conversion hooks — and takes that from {@see Definition}. What it cannot share is the
 * *lifecycle*: {@see AtomicField} resolves one value and returns one verdict per constraint,
 * while this resolves a list and returns a verdict per constraint **and per item**. Inheriting
 * the atomic lifecycle only to replace it would have bought a misleading name.
 *
 * @implements Field<array<string, object>>
 */
final readonly class Collection implements Field
{
	use Definition;

	/**
	 * How many items are enough, once there are any. Never below one: for a collection,
	 * "required" and "needs at least one item" are the same statement, so a minimum of zero
	 * would describe a field that cannot be failed.
	 *
	 * Which leaves three knobs that cannot contradict each other:
	 *
	 * | `$optional` | `$minCount` | nothing submitted | one item | three items |
	 * | --- | --- | --- | --- | --- |
	 * | `false` | 1 | `minCount` fails | passes | passes |
	 * | `false` | 3 | `minCount` fails | `minCount` fails | passes |
	 * | `true` | 3 | `minCount` skipped | `minCount` fails | passes |
	 *
	 * So the **shape** means only "that was not a list", `$optional` means only "empty is
	 * acceptable", and this means only "how many is enough" — which is what lets "no referees, or
	 * three" be said at all. There is no `type` constraint; readability is the precondition every
	 * constraint depends on rather than one more rule among them.
	 *
	 * @var positive-int
	 */
	public int $minCount;

	/** @var positive-int|null `null` means no ceiling. */
	public ?int $maxCount;


	/**
	 * Whether the same item may appear twice. **False by default**: a list of things is usually a
	 * *set* of things, and the same guest invited twice or the same skill claimed twice is a
	 * mistake far more often than it is intent.
	 *
	 * Strict by default, like every other guess this library declines to make — an author who
	 * genuinely wants repeats says so with {@see self::allowDuplicates()}.
	 */
	public bool $allowsDuplicates;

	/**
	 * The fields every item is checked against. Never written to — an item's values are passed
	 * *to* these, never staged on them, which is what lets one template validate every item.
	 *
	 * @var list<Field>
	 */
	public array $template;

	/**
	 * Rules applied to each row on its own, against a copy of the template.
	 *
	 * Separate from the schema's rules because they answer a different question and reach a
	 * different place: a schema rule speaks about the list, and one of these speaks about one row.
	 * See {@see self::forEachRow()}.
	 */
	public Rule\Set $rowRules;

	/**
	 * @throws InvalidConfiguration if the template is empty or names a field twice
	 */
	public function __construct(
		public FieldName $name,
		Field ...$template,
	) {
		$this->initialiseDefinition();

		if ($template === []) {
			throw InvalidConfiguration::templateIsEmpty();
		}

		// The same rule a schema's own fields are held to, asked the same way. This used to key
		// an array by the name as written, which is exact where `Field\Set` collided
		// case-insensitively — so a template holding `Name` and `name` was accepted here and
		// then threw `DuplicateFieldName` on every request, when `eachItem()` built a `Set` from
		// it. An authoring mistake that waited for a user to find.
		$seen = [];

		foreach ($template as $field) {
			foreach ($seen as $already) {
				if ($already->collidesWith($field->name)) {
					throw InvalidConfiguration::templateAlreadyHasAField((string) $field->name);
				}
			}

			$seen[] = $field->name;
		}

		$this->template = array_values($template);
		$this->rowRules = self::initially(new Rule\Set());
		$this->minCount = self::initially(1);
		$this->maxCount = self::initially(null);
		$this->allowsDuplicates = self::initially(false);

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * @param positive-int $count
	 * @throws InvalidConfiguration if below one, or above the maximum
	 */
	public function minCountOf(int $count): static
	{
		if ($count < 1) {
			throw InvalidConfiguration::minimumCountWouldRejectNothing();
		}

		if ($this->maxCount !== null && $count > $this->maxCount) {
			throw InvalidConfiguration::minimumExceedsMaximum('count');
		}

		return $this->with(['minCount' => $count]);
	}

	/**
	 * @param positive-int|null $count `null` removes the ceiling
	 * @throws InvalidConfiguration if below one, or below the minimum
	 */
	public function maxCountOf(?int $count): static
	{
		if ($count === null) {
			return $this->with(['maxCount' => null]);
		}

		if ($count < 1) {
			throw InvalidConfiguration::maximumCountWouldAcceptNothing();
		}

		if ($count < $this->minCount) {
			throw InvalidConfiguration::maximumIsBelowMinimum('count');
		}

		return $this->with(['maxCount' => $count]);
	}


	/**
	 * Accepts the same item more than once.
	 *
	 * For a list where repetition carries meaning — line items on an invoice, where two of the
	 * same product at the same price is an ordinary thing to write, or a timesheet with two
	 * identical half-hour entries on the same task.
	 */
	public function allowDuplicates(): static
	{
		return $this->with(['allowsDuplicates' => true]);
	}


	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('minCount', $this->holdsAtLeastTheMinimum(...), $this->minCount),
			new Constraint('maxCount', $this->holdsAtMostTheMaximum(...), $this->maxCount),
			// No bound: "these two rows are the same" has nothing to interpolate.
			new Constraint('unique', $this->holdsNoRepeats(...), null),
		);
	}

	/**
	 * Whether every item is distinct.
	 *
	 * Compared on the *resolved* value of each field rather than the raw row, so each field is the
	 * authority on what was actually entered. Where a field canonicalises, the canonicalisation
	 * counts: an address submitted as `AU` and one submitted as `Australia` are the same address,
	 * because `Address` resolves both to the same {@see Address\Value}, and `alice@example.test`
	 * and `alice@EXAMPLE.test` are one guest, because DNS says two spellings are one host and
	 * `EmailAddress` lower-cases the domain accordingly.
	 *
	 * Which means this is exactly as good as the fields in the template, and no better — a field
	 * that treats two spellings as different makes two rows different here. That is a property of
	 * the field rather than of this comparison, and improving a field improves this for free.
	 *
	 * Nothing is resolved here any more. `$items` is what {@see self::parse()} produced, so every
	 * leaf has already been through its field exactly once; this used to re-resolve all of them
	 * while the item results were resolving the same values alongside it.
	 *
	 * Scalars compare identically and value objects structurally, except where one says otherwise:
	 * a {@see ParsedValue} is asked, which is how a `Money` amount written `12.50` in one row and
	 * `12.5` in another counts as one line item despite `BigDecimal` keeping the scale it was
	 * given.
	 */
	private function holdsNoRepeats(Value $items): ?bool
	{
		if ($this->allowsDuplicates) {
			return null;
		}

		// Nothing to repeat. Said explicitly so an empty or single-item list skips rather than
		// passing, which keeps it consistent with the counts above.
		if ($items->count() < 2) {
			return null;
		}

		// The comparison itself belongs to the list, not to this field: whether two rows are the
		// same row is a question about the rows. All this decides is whether repeats are allowed.
		return !$items->hasRepeats();
	}


	/**
	 * Resolves the list and every item, without checking anything.
	 *
	 * Nothing is written to the template, so the same one resolves every item independently. The
	 * path this replaced fed each item in with `input()`, which left the template holding the
	 * *last* item's values once the loop finished.
	 *
	 * @param list<\Meraki\Schema\Rule\AppliedOutcome> $appliedOutcomes
	 */
	public function resolve(
		mixed $given,
		array $appliedOutcomes = [],
		ValueSource $givenAs = ValueSource::Submitted,
	): Result {
		return new Result(
			$this,
			$given,
			$this->resolvedValueFor($given),
			$appliedOutcomes,
			$this->sourceOf($given, $givenAs),
			$this->evaluatedAt(),
			$this->eachItem($given, static fn(Field $f, mixed $v, array $o): ResolvedField => self::resolvedLeaf($f, $v, $o)),
		);
	}

	/**
	 * Whether this is a list this field can work with: an array whose every key names a row.
	 */
	private static function isUsableList(mixed $value): bool
	{
		return is_array($value) && self::keysAreNames($value);
	}

	/**
	 * Whether every row is named, and named the way everything else in this library is.
	 *
	 * A row key is a **name** — the same shape as a {@see \Meraki\Schema\FieldName}, which is what
	 * it is asked. So `['first' => …, 'second' => …]` is a collection and `[…, …]` is not: a
	 * positional list is refused rather than numbered.
	 *
	 * ### Why positions are gone
	 *
	 * They were addressable by nothing and stable under nothing. A rule naming row `1` meant a
	 * different row the moment anything was inserted above it, so a stored rule silently changed
	 * its mind between requests — which is why collection items used to be unaddressable at all.
	 * A name is chosen by whoever built the payload and means the same thing every time, so
	 * `#/fields/attendees/value/alice/email` is a reference rather than a guess.
	 *
	 * It also removes a hazard rather than policing one. PHP turns the array key `'0'` into `0`,
	 * so a naming scheme permitting leading digits could not tell a name from a position; names
	 * cannot start with a digit, so the question never arises.
	 *
	 * @param array<array-key, mixed> $items the raw submitted rows, whose keys are exactly what is in question
	 */
	private static function keysAreNames(array $items): bool
	{
		foreach (array_keys($items) as $key) {
			if (!is_string($key) || !FieldName::isUsable($key)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param list<\Meraki\Schema\Rule\AppliedOutcome> $appliedOutcomes
	 */
	public function validate(
		mixed $given,
		array $appliedOutcomes = [],
		ValueSource $givenAs = ValueSource::Submitted,
		PrefillPolicy $policy = PrefillPolicy::Checked,
	): Result {
		$items = $this->resolvedValueFor($given);
		$source = $this->sourceOf($given, $givenAs);

		return new Result(
			$this,
			$given,
			$items,
			$appliedOutcomes,
			$source,
			$this->evaluatedAt(),
			$this->eachItem($given, static fn(Field $f, mixed $v, array $o): ResolvedField => self::validatedLeaf($f, $v, $o)),
			...$this->check($items, $source, $policy),
		);
	}


	/**
	 * What a rule may ask about this field: a list is compared and counted, not ranked; isEmpty already reads its length.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Basic
	{
		return new Matcher\Basic(ValueScope::of($this->name));
	}

	/**
	 * Asks about one template field across the rows, where **one row answering yes is enough**.
	 *
	 *     $lines->whereAny('sku')->equals('HAZMAT')->then($declaration->makeRequired());
	 *
	 * @throws InvalidConfiguration if the template has no such field
	 */
	public function whereAny(FieldName|string $field): Matcher
	{
		return $this->acrossRows($field, Quantifier::Any);
	}

	/**
	 * A rule applied to **each row on its own**, asking about that row and changing that row.
	 *
	 *     $age = $schema->createNumberField('age');
	 *     $guardian = $schema->createTextField('guardian')->makeOptional();
	 *
	 *     $attendees = $schema->createCollectionField('attendees', $age, $guardian)
	 *         ->forEachRow($age->when()->isLessThan(18)->then($guardian->makeRequired()));
	 *
	 * The question a repeatable section actually asks, and the one a schema rule cannot: "if *this*
	 * attendee is a child, require *this* attendee's guardian" is about one row at a time, and a
	 * rule naming the collection could only ever speak about the list as a whole.
	 *
	 * ### A row rule's world is the template
	 *
	 * Which is why it needs no new scope vocabulary. `$age->when()` produces the ordinary
	 * `#/fields/age/value`, and inside a row that is exactly what it says — the row's field set
	 * *is* the template, and the row's values are what was submitted. So a row rule is written the
	 * way every other rule is written, and {@see \Meraki\Schema\Rule\Application} runs it unchanged.
	 *
	 * Use {@see self::whereAny()} instead to ask about the rows collectively, and a schema rule to
	 * change something outside the collection — a row rule can only reach its own row.
	 *
	 * @throws InvalidConfiguration if the rule names a field the template does not hold
	 * @throws \Meraki\Schema\Exception\InvalidRule if it addresses something the template has
	 *         not got, or compares against a value the field it names could never hold
	 */
	public function forEachRow(Rule|Rule\Draft ...$rules): static
	{
		$set = $this->rowRules;

		foreach ($rules as $rule) {
			$template = new Set(...$this->template);
			$rule = $rule instanceof Rule\Draft ? $rule->buildAgainst($template) : $rule;

			// This one first: its message names the collection and lists what the template holds,
			// which reads better than the `UnknownField` the general check would raise for the
			// same mistake.
			$this->assertRowRuleStaysInsideTheTemplate($rule);

			// Then everything a schema rule has to pass. A row rule is checked against the
			// template exactly as a schema rule is checked against the schema's fields — these
			// used to be skipped here, so a row rule could be dead on arrival or blow up on a
			// user's request in ways `addRule()` had refused since Step 1.
			Rule\Guards::check($rule, $template);

			$set = $set->add($rule);
		}

		return $this->with(['rowRules' => $set]);
	}

	/**
	 * The same, where **every row has to answer yes**.
	 *
	 *     $lines->whereEvery('kind')->equals('digital')->then($shippingAddress->makeOptional());
	 *
	 * @throws InvalidConfiguration if the template has no such field
	 */
	public function whereEvery(FieldName|string $field): Matcher
	{
		return $this->acrossRows($field, Quantifier::Every);
	}

	/**
	 * The template field's own matcher, bound to the column and quantified.
	 *
	 * Its *own* matcher, so a row field offers exactly the questions its value can answer —
	 * `whereAny('qty')` on a number has `isGreaterThan` and `whereAny('sku')` on text does not,
	 * which is the same promise {@see Field::when()} makes for a top-level field. Built from the
	 * class that field chose rather than from a table here, so a field type this library has never
	 * heard of is quantifiable the moment it picks a matcher.
	 *
	 * @throws InvalidConfiguration if the template has no such field
	 */
	private function acrossRows(FieldName|string $field, Quantifier $how): Matcher
	{
		$name = $field instanceof FieldName ? $field : new FieldName($field);
		$matcher = $this->templateField($name)->when();

		// A declared method, not `new ($matcher::class)($scope, $how)`. That reconstruction
		// relied on a two-argument constructor {@see Matcher} never required: PHP passes extra
		// arguments to a user-defined constructor without complaint, so a third-party matcher
		// taking only a scope silently dropped the quantifier and produced a rule that passed
		// every check and could never fire.
		return $matcher->quantifiedAt(new ValueScope(new Scope\Column($this->name, $name)), $how);
	}

	/**
	 * @throws InvalidConfiguration if the template has no such field
	 */
	private function templateField(FieldName $name): Field
	{
		foreach ($this->template as $field) {
			if ($field->name->equals($name)) {
				return $field;
			}
		}

		throw InvalidConfiguration::templateHasNoSuchField(
			(string) $this->name,
			(string) $name,
			array_map(static fn(Field $f): string => (string) $f->name, $this->template),
		);
	}

	/**
	 * Reads the incoming value as items, each keyed by the template's field names.
	 *
	 * The collection itself is an **array**, because it holds many of something; each item is an
	 * **object**, because it is one record with named parts. That is the rule
	 * {@see Definition::recordIn()} states, and a collection is the reason it is worth having: an
	 * array is no longer ambiguous between "a list" and "a set of named parts", so a string key
	 * here means something.
	 *
	 * **Every row is named.** `['first_night' => …]` names its rows, and the name follows through
	 * to {@see Collection\Item::$key} so a failure can be reported against something a person
	 * recognises — and so a rule can address the row at all. A positional list is not a shorthand
	 * for this; it is refused. See {@see self::keysAreNames()}.
	 */
	protected function parse(mixed $value): Value
	{
		if ($value instanceof Value) {
			return $value;
		}

		// The one shape check that has to stay on the field: a row is read against the *template*,
		// which is this field's configuration and nothing a value could see. What the value owns
		// is what it is made of — a list of rows — and that is settled here first.
		$rows = $this->rowsIn($value, static fn(Field $field, mixed $raw): mixed => $field->resolvedValueFor($raw));

		if ($rows === null) {
			throw MalformedValue::of(Value::class, 'a collection is submitted as rows under names, not a record and not a positional list');
		}

		// A row is a record, so it comes back as an object — the same rule its input obeyed. The
		// one exception is a row that was never a record: it is handed back untouched so it fails
		// on its own terms rather than being quietly reshaped into something it is not.
		return new Value(array_map(
			static fn(mixed $row): mixed => is_array($row) ? (object) $row : $row,
			$rows,
		));
	}

	/**
	 * Rows keyed as they arrived, each holding one value per template field.
	 *
	 * The `$leaf` callback is what the two readings differ by, and they are both needed:
	 *
	 * - **resolved**, for {@see self::parse()} — what the field actually validated, which is what
	 *   `$value` means everywhere else in the library and what the `unique` constraint compares.
	 * - **raw**, for {@see self::eachItem()} — what was submitted, which each item result has to
	 *   report as its own `$given` so a rejected form echoes back what somebody typed rather than
	 *   something coerced.
	 *
	 * Sharing the traversal is what stops them disagreeing about which rows exist.
	 *
	 * @param callable(Field, mixed): mixed $leaf
	 * @return array<string, array<string, mixed>|mixed>|null null when this was not a usable list
	 */
	private function rowsIn(mixed $value, callable $leaf): ?array
	{
		// An object is a record, not a list. This is where a Money-shaped payload handed to a
		// collection is refused rather than read as a one-item list of its parts.
		if (!self::isUsableList($value)) {
			return null;
		}

		$items = [];

		foreach ($value as $key => $rawItem) {
			$raw = self::recordIn($rawItem);

			// An item that is not a record is kept as it came, so it fails on its own terms.
			// Dropping it would silently shorten the list instead.
			if ($raw === null) {
				$items[$key] = $rawItem;

				continue;
			}

			$item = [];

			foreach ($this->template as $field) {
				$item[(string) $field->name] = $leaf($field, $raw[(string) $field->name] ?? null);
			}

			$items[$key] = $item;
		}

		return $items;
	}

	/**
	 * Nothing submitted is an empty list, not an absent value.
	 *
	 * A repeatable section a person left alone and a JSON client sending `[]` say the same thing,
	 * and saying it as "there are no items" lets `minCount` answer with "add at least one" instead
	 * of the blunter "this field is required". The only field that overrides the absence rule, and
	 * it does so here rather than inside parse(), which never sees null.
	 *
	 * This used to be a private copy of {@see Definition::rawFor()} with `?? []` on the end, which
	 * meant the rule had two implementations that disagreed: resolve() and validate() went through
	 * the copy and saw an empty list, while {@see \Meraki\Schema\ScopeResolver} went through
	 * {@see Definition::resolvedValueFor()} and saw `null`. A rule reading
	 * `#/fields/lines/value` and the result for `lines` reported different things about the same
	 * request. It is a hook on the one rule now.
	 */
	protected function absentValue(): mixed
	{
		return [];
	}

	/**
	 * @return list<ConstraintValidationResult|ShapeValidationResult>
	 */
	private function check(
		mixed $items,
		ValueSource $source = ValueSource::Submitted,
		PrefillPolicy $policy = PrefillPolicy::Checked,
	): array {
		// No "nothing was submitted" branch: resolve() and validate() turn that into an empty list
		// before they get here, and an empty list is something the counts can speak to. A required
		// collection therefore reports "add at least one" rather than "this field is required",
		// which is the same fact said usefully.
		// `parse()` hands back a Value or nothing, and when it hands back nothing the lifecycle
		// keeps the submitted input so a form can echo it back — so what arrives here is either the
		// value object or the raw input, and having one is exactly the test that it was readable.
		// No second opinion on the shape, which is what used to let parse() and check() disagree.
		if (!$items instanceof Value) {
			// Nothing to count, so the count constraints are skipped rather than failed.
			//
			// Always `unreadable` rather than `missing`: absence became an empty list further up,
			// so anything reaching here is something that arrived and was not a usable list.
			return [ShapeValidationResult::unreadable(), ...$this->constraints->allSkipped()];
		}

		// A list the application vouches for: the counts are waived, as every other constraint is.
		// Each item is still validated on its own, because trust was placed in the list arriving,
		// not in what a template says each row must contain.
		if ($source === ValueSource::Prefilled && $policy === PrefillPolicy::Trusted) {
			return [ShapeValidationResult::pass(), ...$this->constraints->allSkipped()];
		}

		return [
			ShapeValidationResult::pass(),
			...$this->constraints->against($items),
		];
	}

	/**
	 * @param callable(Field, mixed, list<\Meraki\Schema\Rule\AppliedOutcome>): ResolvedField $each
	 * @return array<string, Item> keyed by row name, so itemAt() is a lookup rather than a scan
	 */
	private function eachItem(mixed $given, callable $each): array
	{
		$results = [];

		// The *raw* rows, deliberately: an item result reports `$given` per field, and that has to
		// be what was submitted. Reading the resolved rows here would echo a coerced value back
		// into a form the submitter is being asked to correct.
		$rows = $this->rowsIn($given, static fn(Field $field, mixed $raw): mixed => $raw) ?? [];

		foreach ($rows as $key => $item) {
			$fields = [];
			$values = is_array($item) ? $item : [];

			// Each row folds over its **own copy** of the template, so a rule that made one row's
			// guardian required has said nothing about the next row. Nothing is written to
			// `$this->template`, which is what has always let one template validate every row.
			[$effective, $applied] = Rule\Application::of(
				$this->rowRules,
				new Set(...$this->template),
				$values,
			);

			foreach ($effective as $field) {
				$name = (string) $field->name;
				$outcomes = Rule\Application::forField($applied, $name);

				// A rule that ignored this field in this row means "treat it as though the row said
				// nothing", exactly as it does for a schema field.
				$value = Rule\Application::ignores($outcomes) ? null : ($values[$name] ?? null);

				$fields[] = $each($field, $value, $outcomes);
			}

			// Keyed by the same key, so itemAt() is a lookup rather than a scan — and so a named
			// row is addressable by its name.
			$results[$key] = new Item($key, ...$fields);
		}

		return $results;
	}

	/**
	 * A row rule may only reach fields the template holds.
	 *
	 * It runs against a copy of the template and nothing else, so a scope or outcome naming
	 * something outside it could never resolve — and would fail on a user's request rather than
	 * where the rule was written. Changing something outside the collection is a schema rule's job.
	 *
	 * @throws InvalidConfiguration naming the field and what the template does hold
	 */
	private function assertRowRuleStaysInsideTheTemplate(Rule $rule): void
	{
		$named = [
			...array_map(static fn(Scope $s): string => (string) $s->field, $rule->condition->getScopes()),
			...array_map(static fn(Rule\Outcome $o): string => (string) $o->getScope()->field, $rule->outcomes),
			...array_map(static fn(Rule\Outcome $o): string => (string) $o->getScope()->field, $rule->else),
		];

		$held = array_map(static fn(Field $f): string => (string) $f->name, $this->template);

		foreach ($named as $name) {
			if (!in_array($name, $held, true)) {
				throw InvalidConfiguration::templateHasNoSuchField((string) $this->name, $name, $held);
			}
		}
	}

	/**
	 * Skipped for an empty collection the author said may be empty — that is the whole of what
	 * `$optional` means here. Once there is anything at all, the minimum applies either way, so
	 * "no referees, or three of them" is a thing that can be asked for.
	 */
	private function holdsAtLeastTheMinimum(Value $items): ?bool
	{
		if ($items->isEmpty() && $this->optional) {
			return null;
		}

		return $items->count() >= $this->minCount;
	}

	private function holdsAtMostTheMaximum(Value $items): ?bool
	{
		return $this->maxCount === null ? null : $items->count() <= $this->maxCount;
	}


	/**
	 * A template field's own result. Flattened to a {@see ResolvedField} because an item is a
	 * list of leaves: a collection of collections is not something a form can render.
	 *
	 * @param list<Rule\AppliedOutcome> $appliedOutcomes what this row's rules did to this field
	 */
	private static function resolvedLeaf(Field $field, mixed $value, array $appliedOutcomes = []): ResolvedField
	{
		$result = $field->resolve($value, $appliedOutcomes);

		return $result instanceof ResolvedField
			? $result
			: throw InvalidConfiguration::templateFieldResolvesToMoreThanOneValue((string) $field->name, $result::class);
	}

	/**
	 * @param list<Rule\AppliedOutcome> $appliedOutcomes what this row's rules did to this field
	 */
	private static function validatedLeaf(Field $field, mixed $value, array $appliedOutcomes = []): ResolvedField
	{
		$result = $field->validate($value, $appliedOutcomes);

		return $result instanceof ResolvedField
			? $result
			: throw InvalidConfiguration::templateFieldValidatesToMoreThanOneValue((string) $field->name, $result::class);
	}
}
