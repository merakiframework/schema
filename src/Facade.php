<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Closure;
use InvalidArgumentException;
use Meraki\Schema\Field;
use Meraki\Schema\Field\Atomic;
use Meraki\Schema\Property;
use Meraki\Schema\Rule;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\Rule\AppliedOutcome;
use Meraki\Schema\SchemaValidationResult;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Builder;

final class Facade
{
	public readonly Property\Name $name;

	public function __construct(
		string $name,
		public Field\Set $fields = new Field\Set(),
		public Rule\Set $rules = new Rule\Set(),
	) {
		$this->name = new Property\Name($name);
	}

	private static function extractDefaultValues(self $schema): array
	{
		$data = [];

		foreach ($schema->fields as $field) {
			$data[(string)$field->name] = $field->defaultValue->unwrap();
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
			// A dot separates a composite from its sub-fields, so a top-level field carrying
			// one would be indistinguishable from an addr.line1 or price.amount belonging to
			// some composite. Sub-fields get their dotted names from Composite, never here.
			if (str_contains((string) $field->name, Property\Name::PREFIX_SEPARATOR)) {
				throw new InvalidArgumentException(sprintf(
					'"%s" cannot be a top-level field name: "%s" is reserved for the sub-fields of a composite.',
					(string) $field->name,
					Property\Name::PREFIX_SEPARATOR,
				));
			}

			$field->schema = $this;
			$this->fields = $this->fields->add($field);
		}

		return $this;
	}

	public function prefill(array|object $data): self
	{
		$data = $this->extractData($data);

		foreach ($this->fields as $field) {
			$field->prefill($data[(string) $field->name] ?? null);
		}

		return $this;
	}


	/**
	 * Resolves this schema against one request's data, without checking anything.
	 *
	 * Every field comes back {@see ValidationStatus::Pending}, which is what a form being
	 * rendered for the first time actually is. Nothing is written to this schema, so the
	 * same instance can resolve two requests at once without them meeting.
	 */
	public function resolve(array|object $data): SchemaValidationResult
	{
		return $this->against($data, static fn(Field $field, mixed $given, array $outcomes): AggregatedValidationResult
			=> $field->resolve($given, $outcomes));
	}

	/**
	 * Resolves and checks. Stores nothing on this schema.
	 */
	public function validate(array|object $data): SchemaValidationResult
	{
		return $this->against($data, static fn(Field $field, mixed $given, array $outcomes): AggregatedValidationResult
			=> $field->validate($given, $outcomes));
	}

	/**
	 * Runs one request against a private copy of this schema.
	 *
	 * Rules still work by changing fields, so they are given copies to change: the
	 * authored definition is never touched, and two requests cannot interfere. A field no
	 * rule altered is reported against the *authored* object rather than its copy, so
	 * identity holds for the common case and only differs where something really did
	 * change it.
	 *
	 * @param callable(Field, mixed, list<AppliedOutcome>): AggregatedValidationResult $each
	 */
	private function against(array|object $data, callable $each): SchemaValidationResult
	{
		$given = $this->extractData($data);
		$working = $this->copyForRequest();

		// Conditions resolve values from $given via ScopeResolver, so nothing is staged
		// onto the copies: they carry the definition only, and rules change that.
		$applied = $working->rules->apply($given, $working);

		/** @var array<string, list<AppliedOutcome>> $byField */
		$byField = [];

		foreach ($applied as $outcome) {
			$byField[self::fieldNameIn($outcome->outcome->getScope())][] = $outcome;
		}

		$results = [];

		foreach ($working->fields as $field) {
			$name = (string) $field->name;
			$outcomes = $byField[$name] ?? [];
			$effective = $outcomes === [] ? $this->fields->getByName($name) : $field;

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

			$value = $ignored ? null : ($given[$name] ?? null);

			$results[] = $each($effective, $value, $outcomes);
		}

		return new SchemaValidationResult(...$results);
	}

	/**
	 * A copy whose fields can be changed without touching this schema's.
	 */
	private function copyForRequest(): self
	{
		$copy = new self((string) $this->name, new Field\Set(), $this->rules);

		foreach ($this->fields as $field) {
			$copy->fields = $copy->fields->add(clone $field);
		}

		return $copy;
	}

	/**
	 * The field a scope points at. Every scope names one, so this no longer has to pick
	 * segments apart and hope.
	 */
	private static function fieldNameIn(Scope $scope): string
	{
		return (string) $scope->field;
	}

	private function extractData(array|object|null $data): array
	{
		if ($data === null) {
			return self::extractDefaultValues($this);
		}

		if (is_array($data)) {
			return $data;
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
	public function whenAllMatch(Closure $configurator): self
	{
		$this->addRule($configurator(Builder::whenAllOf()));

		return $this;
	}

	public function whenAnyMatch(Closure $configurator): self
	{
		$this->addRule($configurator(Builder::whenAnyOf()));

		return $this;
	}

	public function addRule(Rule $rule): self
	{
		if ($rule instanceof Builder) {
			$rule = $rule->build();
		}

		$this->assertScopesAreAddressable($rule);

		$this->rules = $this->rules->add($rule);

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
