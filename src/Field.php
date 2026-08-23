<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Property;
use Meraki\Schema\ScopeTarget;
use Meraki\Schema\AggregatedValidationResult;
use Meraki\Schema\Field\ValidationResult;
use Meraki\Schema\Field\CompositeValidationResult;
use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\Rule\FieldBuilder;
use Closure;
use InvalidArgumentException;
use LogicException;

/**
 * @template AcceptedType of mixed
 */
abstract class Field implements ScopeTarget
{
	/**
	 * The name of the field.
	 *
	 * This is used to identify the field in the schema and
	 * should be unique within a schema.
	 *
	 * External code should not modify this property
	 */
	public Property\Name $name;

	/**
	 * The input value of the field.
	 *
	 * This property is always set to a Property\Value instance,
	 * and should not be relied on for determining if input was given.
	 * Use the `inputGiven` property to check if input was provided.
	 *
	 * External code should not modify this property
	 */
	public Property\Value $value;

	/**
	 * The default value of the field.
	 *
	 * This property is used when no input was given.
	 *
	 * External code should not modify this property
	 */
	public Property\Value $defaultValue;

	/**
	 * The resolved value of the field.
	 *
	 * This is the value that will be used for validation and
	 * further processing. It is either the input value if provided,
	 * or the default value if no input was given. This value always
	 * reflects the value that will be used for validation at any
	 * point in a field's lifecycle. For example, if a field has a
	 * default value given, and no input value given yet, then this
	 * property will contain the default value.
	 *
	 * External code should not modify this property
	 */
	public Property\Value $resolvedValue;

	/**
	 * Indicates whether input has been given for this field.
	 *
	 * External code should not modify this property
	 */
	public bool $inputGiven;

	/**
	 * Indicates whether this field requires input.
	 *
	 * External code should not modify this property
	 */
	public bool $optional;

	/**
	 * When true, any submitted input is treated as not provided (the field validates
	 * as empty). Set by the `ignore` rule outcome / {@see self::ignoreInput()}.
	 */
	public private(set) bool $inputIgnored = false;

	/**
	 * The schema this field belongs to, set when added via {@see Facade::addField()}.
	 * Enables {@see self::pairWith()} to add a paired field and register rules.
	 */
	public ?Facade $schema = null;

	/**
	 * Properties that exist for internal wiring rather than as part of the field's
	 * addressable API. {@see self::$schema} points back at the field's owner, so a
	 * scope stepping into it would climb to the root and walk the path forever.
	 *
	 * Everything else public on a field stays addressable: a field's public properties
	 * are its API, and '#/fields/x/min' or '#/fields/x/optional' are valid targets.
	 */
	private const NOT_ADDRESSABLE = ['schema'];

	public function __construct(
		Property\Name $name,
	) {
		$this->name = $name;
		$this->value = $this->process(null);
		$this->defaultValue = $this->process(null);
		$this->inputGiven = false;
		$this->optional = false;

		$this->resolveValue();
	}

	/**
	 * Renames the field to a new name.
	 *
	 * @param Property\Name $name The new name for the field.
	 */
	public function rename(Property\Name $name): static
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * Marks the field as optional, meaning it can be left empty
	 * without causing a validation error.
	 */
	public function makeOptional(): static
	{
		$this->optional = true;

		return $this;
	}

	public function require(): static
	{
		$this->optional = false;

		return $this;
	}

	/**
	 * Discards any submitted input: the field resolves as empty and validates as
	 * not-provided (pair with {@see self::makeOptional()} to skip it entirely).
	 */
	public function ignoreInput(): static
	{
		$this->inputIgnored = true;
		$this->value = $this->process(null);
		$this->inputGiven = false;

		$this->resolveValue();

		return $this;
	}

	public function acceptInput(): static
	{
		$this->inputIgnored = false;

		return $this;
	}

	/**
	 * Declare a relationship with another field. The paired field is added to this
	 * field's schema (a duplicate name throws). The configurator runs immediately,
	 * bound so `$this` is this field, and uses the supplied {@see FieldBuilder} to
	 * capture declarative rules (which therefore serialize like any other rule).
	 *
	 * @param Closure(FieldBuilder, Field, Facade): void $configurator
	 */
	public function pairWith(Field $paired, Closure $configurator): static
	{
		if ($this->schema === null) {
			throw new LogicException('pairWith() requires the owner field to be added to a schema first.');
		}

		if ($this->schema->fields->findByName($paired->name) !== null) {
			throw new InvalidArgumentException("A field named '{$paired->name}' already exists.");
		}

		$this->schema->addField($paired);

		$builder = new FieldBuilder();
		$configurator->call($this, $builder, $paired, $this->schema);

		foreach ($builder->rules() as $rule) {
			$this->schema->addRule($rule);
		}

		return $this;
	}

	/**
	 * Sets the input value for the field.
	 *
	 * @param AcceptedType|null $value
	 */
	public function input($value): static
	{
		$this->inputGiven = true;
		$this->value = $this->process($value);

		$this->resolveValue();

		return $this;
	}

	/**
	 * Sets the default value for the field, which will be used when
	 * no input has been given.
	 *
	 * @param AcceptedType|null $value
	 */
	public function prefill($value): static
	{
		$this->defaultValue = $this->process($value);

		$this->resolveValue();

		return $this;
	}

	public function equals(mixed $other): bool
	{
		return $other instanceof static && $this->name->equals($other->name);
	}

	public function traverse(Scope $scope): ScopeResolutionResult
	{
		$name = (string)$this->name;

		// Verify we're on this field
		if ($scope->currentAsSnakeCase() !== $name) {
			throw new InvalidArgumentException(
				"Unknown field name in scope: expected '{$this->name}', got '{$scope->current()}'"
			);
		}

		$scope->next();

		$propertyNameAsSnakeCase = $scope->currentAsSnakeCase();
		$propertyName = $scope->currentAsCamelCase();

		// Scope was pointing at field only
		if ($propertyName === null) {
			return new ScopeResolutionResult($this, $this);
		}

		if (!property_exists($this, $propertyName)) {
			throw new InvalidArgumentException("No property '{$propertyNameAsSnakeCase} ($propertyName)' on field '{$this->name}'");
		}

		if (in_array($propertyName, self::NOT_ADDRESSABLE, true)) {
			throw new InvalidArgumentException(
				"Property '{$propertyNameAsSnakeCase}' on field '{$this->name}' is internal "
				. "wiring, not part of the field's addressable API."
			);
		}

		$property = $this->{$propertyName};

		// value always resolves to resolved value
		if ($propertyName === 'value') {
			$property = $this->resolvedValue;
		}

		return new ScopeResolutionResult($this, $property);
	}

	/**
	 * Resolves the value of the field based on the input given.
	 *
	 * If an input value has been provided, it will be used as the
	 * resolved value. Otherwise, the default value will be used.
	 */
	protected function resolveValue(): void
	{
		$this->resolvedValue = $this->valueProvided($this->value) ? $this->value : $this->defaultValue;
	}

	/**
	 * Checks if the value given is considered as "input provided".
	 *
	 * Defaults to checking if the value is not null.
	 */
	protected function valueProvided(Property\Value $value): bool
	{
		return $value->unwrap() !== null;
	}

	/**
	 * Whether this field has a value to validate: a resolved value that this field's
	 * own {@see self::valueProvided()} accepts, and input that has not been ignored.
	 *
	 * Callers outside the field (notably {@see Composite::validate()}, deciding whether
	 * an optional sub-field was filled in) must use this rather than `valueProvided()`,
	 * which is protected and therefore resolves to the *caller's* implementation.
	 */
	/**
	 * Whether this field regards the given value as one it was actually handed, as opposed
	 * to nothing at all. The value-taking counterpart to {@see self::hasValue()}, for
	 * callers that hold a resolved value rather than reading one off the field.
	 *
	 * @param AcceptedType|null $value
	 */
	public function accepts(mixed $value): bool
	{
		return $this->valueProvided(new Property\Value($value));
	}

	public function hasValue(): bool
	{
		return !$this->inputIgnored && $this->valueProvided($this->resolvedValue);
	}

	/**
	 * Resolves a submitted value against this field, without checking it.
	 *
	 * This is the seam: the one place a value meets a field. Nothing is written back, so
	 * the field is unchanged and safe to share — resolving the same field concurrently
	 * with different values cannot interfere.
	 *
	 * The result is {@see ValidationStatus::Pending}: a form is rendered before it is
	 * submitted, and that state needs a name.
	 *
	 * @param AcceptedType|null $given exactly what was submitted, or null if nothing was
	 * @param list<Rule\AppliedOutcome> $appliedOutcomes rules that altered this field
	 */
	public function resolveWith(mixed $given, array $appliedOutcomes = []): AggregatedValidationResult
	{
		return new ResolvedField($this, $given, $this->resolvedValueFor($given)->unwrap(), $appliedOutcomes);
	}

	/**
	 * What this field would actually validate, given what was submitted: the submitted
	 * value, or the author's default when nothing usable was.
	 *
	 * @param AcceptedType|null $given
	 */
	final protected function resolvedValueFor(mixed $given): Property\Value
	{
		$submitted = $this->process($given);

		return $this->valueProvided($submitted) ? $submitted : $this->defaultValue;
	}

	/**
	 * Resolves a submitted value and checks it against this field's constraints.
	 *
	 * @param AcceptedType|null $given
	 * @param list<Rule\AppliedOutcome> $appliedOutcomes
	 */
	public function validateWith(mixed $given, array $appliedOutcomes = []): AggregatedValidationResult
	{
		// Built here rather than through resolveWith(), which subclasses widen to return a
		// result per sub-field; this also resolves the value once instead of twice.
		$value = $this->resolvedValueFor($given);

		return (new ResolvedField($this, $given, $value->unwrap(), $appliedOutcomes))
			->withResults(...$this->check($value));
	}

	/**
	 * Evaluates this field's shape and constraints against an already-resolved value.
	 *
	 * Shape first: if there is no usable value, the constraints have nothing to speak to
	 * and are skipped rather than failed, so an error report names the real problem once
	 * instead of once per constraint.
	 *
	 * @return list<ConstraintValidationResult>
	 */
	protected function check(Property\Value $value): array
	{
		$notProvided = !$this->valueProvided($value);

		if ($notProvided) {
			// Absent input is only acceptable when the field says so.
			$shape = $this->optional
				? ConstraintValidationResult::skip('type')
				: ConstraintValidationResult::fail('type');

			return [$shape, ...$this->skipEveryConstraint()];
		}

		if (!$this->validateValue($value->unwrap())) {
			return [ConstraintValidationResult::fail('type'), ...$this->skipEveryConstraint()];
		}

		$results = [ConstraintValidationResult::pass('type')];

		foreach ($this->evaluateConstraints($value) as $name => $passed) {
			$results[] = match ($passed) {
				true => ConstraintValidationResult::pass($name),
				false => ConstraintValidationResult::fail($name),
				default => ConstraintValidationResult::skip($name),
			};
		}

		return $results;
	}

	/**
	 * @return list<ConstraintValidationResult>
	 */
	private function skipEveryConstraint(): array
	{
		return array_map(
			static fn(string $name): ConstraintValidationResult => ConstraintValidationResult::skip($name),
			array_keys($this->getConstraints()),
		);
	}

	/**
	 * Validates the field against its type and constraints.
	 *
	 * This method checks if the value provided matches the expected type
	 * and evaluates any constraints defined for the field. If the field is
	 * optional and no value is provided, it skips all constraints. The
	 * value validated is always the resolved value.
	 *
	 * @deprecated Superseded by {@see self::validateWith()}, which stores nothing on the
	 *             field. Removed once every caller has moved.
	 * @return AggregatedValidationResult The result of the validation.
	 */
	public function validate(): AggregatedValidationResult
	{
		$value = $this->resolvedValue;
		$valueNotProvided = !$this->valueProvided($value) || $this->inputIgnored;

		if ($this->optional && $valueNotProvided) {
			return $this->skipAllConstraints();
		}

		if ($valueNotProvided) {
			return new ValidationResult($this, ConstraintValidationResult::fail('type'));
		}

		$typeIsValid = $this->validateValue($value->unwrap());

		if ($typeIsValid) {
			$results = [ConstraintValidationResult::pass('type')];

			foreach ($this->evaluateConstraints($value) as $constraintName => $constraintResult) {
				$results[] = match ($constraintResult) {
					true => ConstraintValidationResult::pass($constraintName),
					false => ConstraintValidationResult::fail($constraintName),
					null => ConstraintValidationResult::skip($constraintName),
				};
			}

			return new ValidationResult($this, ...$results);
		}

		$results = [ConstraintValidationResult::fail('type')];

		foreach ($this->getConstraints() as $constraintName => $constraint) {
			$results[] = ConstraintValidationResult::skip($constraintName);
		}

		return new ValidationResult($this, ...$results);
	}

	/**
	 * Evaluates the constraints defined for this field against the provided value.
	 *
	 * This method should be overridden in subclasses to provide specific constraint
	 * evaluation logic. It returns an associative array where keys are constraint names
	 * and values are the results of the evaluation (true, false, or null).
	 *
	 * @return array<string, bool|null>
	 */
	protected function evaluateConstraints(Property\Value $value): array
	{
		$results = [];

		foreach ($this->getConstraints() as $name => $constraint) {
			$results[$name] = call_user_func($constraint, $value->unwrap());
		}

		return $results;
	}

	protected function skipAllConstraints(): ValidationResult
	{
		$constraintValidationResults = array_map(
			fn(string $constraintName): ConstraintValidationResult => ConstraintValidationResult::skip($constraintName),
			array_keys($this->getConstraints()),
		);

		$typeConstraintValidationResult = ConstraintValidationResult::skip('type');

		return new ValidationResult($this, $typeConstraintValidationResult, ...$constraintValidationResults);
	}

	/**
	 * Converts the raw value given into a Property\Value instance.
	 *
	 * This is where you can implement any custom logic to transform the input value
	 * into a format that is suitable for the field. For example, the composite field
	 * will take a single `null` value and convert it into an array of field name to
	 * value mappings, with all values set to `null`.
	 *
	 * @param AcceptedType|null $value
	 */
	protected function process($value): Property\Value
	{
		return new Property\Value($value);
	}

	/**
	 * The value in whatever type this field is really about — a `BigDecimal` for money, a
	 * `LocalDate` for a date, a parsed phone number. Read through
	 * {@see ResolvedField::$transformed}, and only ever called with a value that already
	 * passed validation.
	 *
	 * The default is the identity: a field that has no richer type to offer says so by not
	 * overriding this. Types are filled in per field from 2.1 onwards; the parsing already
	 * happens inside the constraints today and is simply discarded.
	 *
	 * @param AcceptedType $value
	 */
	public function transform(mixed $value): mixed
	{
		return $value;
	}

	/**
	 * Returns an array of constraints that this field should validate against.
	 *
	 * Each constraint is defined as a callable that takes the field's value
	 * and returns true if the constraint is satisfied, false if it fails,
	 * or null if the constraint should be skipped.
	 *
	 * @return array<string, callable(mixed): bool|null>
	 */
	abstract protected function getConstraints(): array;

	/**
	 * Returns true if the given value is a valid instance of this field's type.
	 */
	abstract public function validateValue(mixed $value): bool;
}
