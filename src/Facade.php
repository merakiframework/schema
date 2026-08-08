<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Closure;
use InvalidArgumentException;
use Meraki\Schema\Field;
use Meraki\Schema\ScopeTarget;
use Meraki\Schema\Field\Atomic;
use Meraki\Schema\Property;
use Meraki\Schema\Rule;
use Meraki\Schema\SchemaValidator;
use Meraki\Schema\SchemaValidationResult;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Builder;

final class Facade implements ScopeTarget
{
	public readonly Property\Name $name;

	/**
	 * Baseline (author-configured) optional state per field name, captured the
	 * first time a field is seen by applyRules() so rule outcomes can be reset
	 * before each re-application.
	 *
	 * @var array<string, bool>
	 */
	private array $baselineOptional = [];

	/**
	 * Default countries for region-aware fields added *after* {@see self::for()} is
	 * called, as ISO 3166-1 alpha-2 codes. Empty means each field decides for itself.
	 *
	 * @var array<string>
	 */
	private array $defaultCountries = [];

	public function __construct(
		string $name,
		public Field\Set $fields = new Field\Set(),
		public Rule\Set $rules = new Rule\Set(),
	) {
		$this->name = new Property\Name($name);
	}

	/**
	 * Declares the countries this schema is for, so region-aware fields need not repeat
	 * them:
	 *
	 *     $schema = (new Facade('checkout'))->for('AU');
	 *     $schema->addAddressField('billing');           // restricted to AU
	 *     $schema->addPhoneNumberField('mobile');        // ditto
	 *     $schema->addAddressField('shipping', ['NZ']);  // an explicit list still wins
	 *     $schema->addAddressField('other', []);         // and an explicit [] means free-form
	 *
	 * Applies to {@see Field\Address} and {@see Field\PhoneNumber} — the fields whose
	 * rules are jurisdictional. Deliberately not to {@see Field\Money}: currency does not
	 * follow from a region (a country may use several, and the euro spans twenty).
	 *
	 * Only fields added afterwards, and only via the typed `addXField()` helpers, inherit
	 * it; a field built by hand and passed to {@see self::addField()} does not.
	 *
	 * @param string ...$countries ISO 3166-1 alpha-2 region codes
	 */
	public function for(string ...$countries): self
	{
		$this->defaultCountries = array_values(array_unique(array_map(strtoupper(...), $countries)));

		return $this;
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
	 * @template T of Field
	 * @param T $field
	 * @param Closure<T>|null $configurator
	 * @return T|self
	 */
	public function addField(Field $field, ?Closure $configurator = null): self|Field
	{
		$field->schema = $this;

		if ($configurator !== null) {
			$configurator($field);
			$this->fields = $this->fields->add($field);

			return $this;
		}

		$this->fields = $this->fields->add($field);

		return $field;
	}

	/**
	 * @param array<string>|null $allowedCountries ISO 3166-1 alpha-2 region codes; null
	 *        inherits the schema's own (see {@see self::for()}), [] means free-form.
	 */
	public function addAddressField(string $name, ?array $allowedCountries = null, ?Closure $configurator = null): self|Field\Address
	{
		return $this->addField(
			new Field\Address(new Property\Name($name), $allowedCountries ?? $this->defaultCountries),
			$configurator,
		);
	}

	public function addBooleanField(string $name, ?Closure $configurator = null): self|Field\Boolean
	{
		return $this->addField(new Field\Boolean(new Property\Name($name)), $configurator);
	}

	public function addCreditCardField(string $name, ?Closure $configurator = null): self|Field\CreditCard
	{
		return $this->addField(new Field\CreditCard(new Property\Name($name)), $configurator);
	}

	/**
	 * Adds a repeatable collection field. The $template callback configures one
	 * item's fields on the supplied builder (a Facade), e.g.
	 *   $schema->addCollectionField('lessons', fn($item) => $item->addDateField('date'))
	 *           ->minItems(1);
	 *
	 * @param callable(self): void $template
	 */
	public function addCollectionField(string $name, callable $template, ?Closure $configurator = null): self|Field\Collection
	{
		$item = new self('item');
		$template($item);

		return $this->addField(
			new Field\Collection(new Property\Name($name), ...$item->fields->__toArray()),
			$configurator,
		);
	}

	public function addDateField(string $name, ?Closure $configurator = null): self|Field\Date
	{
		return $this->addField(new Field\Date(new Property\Name($name)), $configurator);
	}

	public function addDateTimeField(string $name, ?Closure $configurator = null): self|Field\DateTime
	{
		return $this->addField(new Field\DateTime(new Property\Name($name)), $configurator);
	}

	public function addDurationField(string $name, ?Closure $configurator = null): self|Field\Duration
	{
		return $this->addField(new Field\Duration(new Property\Name($name)), $configurator);
	}

	public function addEmailAddressField(string $name, ?Closure $configurator = null): self|Field\EmailAddress
	{
		return $this->addField(new Field\EmailAddress(new Property\Name($name)), $configurator);
	}

	public function addEnumField(string $name, array $options, ?Closure $configurator = null): self|Field\Enum
	{
		return $this->addField(new Field\Enum(new Property\Name($name), $options), $configurator);
	}

	public function addFileField(string $name, ?Closure $configurator = null): self|Field\File
	{
		return $this->addField(new Field\File(new Property\Name($name)), $configurator);
	}

	/**
	 * @param array<string, integer> $allowedCurrencies
	 */
	public function addMoneyField(string $name, array $allowedCurrencies, ?Closure $configurator = null): self|Field\Money
	{
		return $this->addField(new Field\Money(new Property\Name($name), $allowedCurrencies), $configurator);
	}

	public function addNameField(string $name, ?Closure $configurator = null): self|Field\Name
	{
		return $this->addField(new Field\Name(new Property\Name($name)), $configurator);
	}

	public function addNumberField(string $name, ?Closure $configurator = null): self|Field\Number
	{
		return $this->addField(new Field\Number(new Property\Name($name)), $configurator);
	}

	public function addPassphraseField(string $name, ?Closure $configurator = null): self|Field\Passphrase
	{
		return $this->addField(new Field\Passphrase(new Property\Name($name)), $configurator);
	}

	public function addPasswordField(string $name, ?Closure $configurator = null): self|Field\Password
	{
		return $this->addField(new Field\Password(new Property\Name($name)), $configurator);
	}

	/**
	 * @param array<string>|null $allowedCountries ISO 3166-1 alpha-2 region codes; null
	 *        inherits the schema's own (see {@see self::for()}), [] means international-only.
	 */
	public function addPhoneNumberField(string $name, ?array $allowedCountries = null, ?Closure $configurator = null): self|Field\PhoneNumber
	{
		return $this->addField(
			new Field\PhoneNumber(new Property\Name($name), $allowedCountries ?? $this->defaultCountries),
			$configurator,
		);
	}

	public function addTextField(string $name, ?Closure $configurator = null): self|Field\Text
	{
		return $this->addField(new Field\Text(new Property\Name($name)), $configurator);
	}

	public function addTimeField(string $name, ?Closure $configurator = null): self|Field\Time
	{
		return $this->addField(new Field\Time(new Property\Name($name)), $configurator);
	}

	public function addUriField(string $name, ?Closure $configurator = null): self|Field\Uri
	{
		return $this->addField(new Field\Uri(new Property\Name($name)), $configurator);
	}

	public function addUuidField(string $name, ?Closure $configurator = null): self|Field\Uuid
	{
		return $this->addField(new Field\Uuid(new Property\Name($name)), $configurator);
	}

	/**
	 * @param non-empty-array<Field> $fields
	 */
	public function addVariantField(string $name, array $fields, ?Closure $configurator = null): self|Field\Variant
	{
		return $this->addField(new Field\Variant(new Property\Name($name), ...$fields), $configurator);
	}

	public function input(array|object $data): self
	{
		$data = $this->extractData($data);

		// input data
		foreach ($this->fields as $field) {
			$field->input($data[(string) $field->name] ?? null);
		}

		$this->applyRules($data);

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

	public function applyRules(array|object|null $data = null): self
	{
		// Reset each field to its baseline optionality before (re-)applying rules
		// so an outcome from a previous run does not persist when its condition
		// no longer holds. The first time a field is seen is treated as baseline.
		foreach ($this->fields as $field) {
			$name = (string) $field->name;

			// No field ignores input at baseline; rules re-apply ignore each run.
			$field->acceptInput();

			if (array_key_exists($name, $this->baselineOptional)) {
				$this->baselineOptional[$name] ? $field->makeOptional() : $field->require();
			} else {
				$this->baselineOptional[$name] = $field->optional;
			}
		}

		$this->rules->apply($this->extractData($data), $this);

		return $this;
	}

	public function validate(array|object $data): SchemaValidationResult
	{
		$this->input($data);

		$results = array_map(
			fn(Field $field): AggregatedValidationResult => $field->validate(),
			$this->fields->__toArray()
		);

		return new SchemaValidationResult(...$results);
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

		$this->rules = $this->rules->add($rule);

		return $this;
	}

	public function traverse(Scope $scope): ScopeResolutionResult
	{
		// If this scope points directly to the schema root
		if ($scope->isRoot()) {
			return new ScopeResolutionResult($this, $this);
		}

		$scope->rewind();
		$first = $scope->currentAsSnakeCase();

		if ($first === 'fields') {
			$scope->next();
			$fieldName = $scope->currentAsSnakeCase();

			if ($fieldName === null) {
				throw new InvalidArgumentException("Expected field name after 'fields'");
			}

			$field = $this->fields->getByName($fieldName);

			return $field->traverse($scope);
		}

		throw new InvalidArgumentException("Unsupported path segment '{$first}' at root");
	}
}
