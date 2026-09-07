<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\ValidationResult;
use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\ResolvedField;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\Property;
use Meraki\Schema\AggregatedValidationResult;
use InvalidArgumentException;

/**
 * A variant field is a field that can have multiple types. It is used to represent a value that can be one of several different types.
 * For example, a variant field can be used to represent a password type and a passphrase type.
 * A variant field can only contain atomic fields, which are fields that have a single value.
 * A variant field cannot use the same field type more than once.
 * Each field in a variant must have a unique name, and the names are prefixed with the variant's name.
 * The order that fields are added is the order that they are validated.
 * The first field that matches the value is the one that is used. (e.g. if a value can match a password and a passphrase field, but
 * the passphrase field was added first, then the passphrase field result is returned.)
 *
 * @template AcceptedType of mixed
 * @extends Field<AcceptedType|null>
 */
final class Variant extends Field
{
	public Field\Set $fields;

	public function __construct(
		Property\Name $name,
		AtomicField ...$fields
	) {
		parent::__construct($name);

		$this->fields = new Field\Set(...$fields);

		if ($this->fields->containsDuplicateFieldTypes()) {
			throw new InvalidArgumentException('Variant fields cannot contain duplicate field types.');
		}

		$this->rename($name);
	}

	public function rename(Property\Name $name): static
	{
		$this->name = $name;
		$this->fields->prefixNamesWith($name);

		return $this;
	}

	/** @param AcceptedType $value */
	public function prefill($value): static
	{
		parent::prefill($value);

		foreach ($this->fields as $field) {
			try {
				$field->prefill($value);
			} catch (InvalidArgumentException $e) {
				continue;
			}
		}

		return $this;
	}


	/**
	 * A variant resolves to one result. Which alternative it belongs to is only known once
	 * the value has been checked, so resolution alone reports against the variant itself.
	 *
	 * @param list<\Meraki\Schema\Rule\AppliedOutcome> $appliedOutcomes
	 */
	public function resolve(mixed $given, array $appliedOutcomes = []): ResolvedField
	{
		return new ResolvedField($this, $given, $this->resolvedValueFor($given)->unwrap(), $appliedOutcomes);
	}

	/**
	 * Tries each alternative in turn; the first that accepts the value wins.
	 *
	 * The result belongs to the **matching alternative**, because that is the definition
	 * which actually described the value — a caller asking what a `secret` turned out to be
	 * gets `Field\Passphrase`, and the constraint results are that field's. When nothing
	 * matches, the result belongs to the variant and carries the shape failure.
	 *
	 * @param list<\Meraki\Schema\Rule\AppliedOutcome> $appliedOutcomes
	 */
	public function validate(mixed $given, array $appliedOutcomes = []): ResolvedField
	{
		$value = $this->resolvedValueFor($given);
		$resolved = new ResolvedField($this, $given, $value->unwrap(), $appliedOutcomes);

		if (!$this->valueProvided($value)) {
			// Absent input is only acceptable when the variant says so.
			return $resolved->withResults($this->optional
				? ConstraintValidationResult::skip('type')
				: ConstraintValidationResult::fail('type'));
		}

		foreach ($this->fields as $field) {
			$attempt = $field->validate($given, $appliedOutcomes);

			if ($attempt->status === ValidationStatus::Passed) {
				return $attempt;
			}
		}

		// Nothing accepted it. Report that against the variant rather than picking one
		// alternative's failures arbitrarily — none of them is *the* reason.
		return $resolved->withResults(ConstraintValidationResult::fail('type'));
	}

	public function getConstraints(): array
	{
		return [];
	}

	public function __isset(string $name): bool
	{
		$name = self::camelCaseToSnakeCase($name);

		return $this->fields->findByName($this->name->__toString() . $this->name::PREFIX_SEPARATOR . $name) !== null;
	}

	public function __get($name): Field
	{
		$name = self::camelCaseToSnakeCase($name);
		$field = $this->fields->findByName($this->name->__toString() . $this->name::PREFIX_SEPARATOR . $name);

		if ($field) {
			return $field;
		}

		throw new InvalidArgumentException("Field '$name' does not exist.");
	}

	public function validateValue(mixed $value): bool
	{
		return true;
	}

	private static function camelCaseToSnakeCase(string $input): string
	{
		return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
	}
}
