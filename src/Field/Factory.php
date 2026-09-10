<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Property;

/**
 * Builds fields. It does not register them — that is {@see \Meraki\Schema\Facade::add()}.
 *
 * The two were one call until fields were sealed. `$schema->addTextField('x')->minLengthOf(3)`
 * added a field and then configured a *copy* of it, leaving the schema holding the
 * unconfigured original. Separating them makes the order explicit: build a field, finish
 * configuring it, then add what you finished with.
 *
 * ```php
 * $fields = new Field\Factory();
 * $schema = new Facade('signup');
 *
 * $schema->add($fields->createTextField('username')->minLengthOf(3));
 * ```
 */
final class Factory
{
	/**
	 * Countries that region-aware fields default to, as ISO 3166-1 alpha-2 codes.
	 *
	 * @var array<string>
	 */
	private array $defaultCountries = [];

	/**
	 * Declares the countries this factory builds for, so region-aware fields need not repeat
	 * them:
	 *
	 *     $fields = (new Factory())->for('AU');
	 *     $fields->createAddressField('billing');            // restricted to AU
	 *     $fields->createPhoneNumberField('mobile');         // ditto
	 *     $fields->createAddressField('shipping', ['NZ']);   // an explicit list still wins
	 *     $fields->createAddressField('other', []);          // and an explicit [] means free-form
	 *
	 * Applies to {@see Field\Address} and {@see Field\PhoneNumber} — the fields whose rules
	 * are jurisdictional. Deliberately not to {@see Field\Money}: a currency does not follow
	 * from a region, since a country may use several and the euro spans twenty.
	 *
	 * @param string ...$countries ISO 3166-1 alpha-2 region codes
	 */
	public function for(string ...$countries): self
	{
		$this->defaultCountries = array_values(array_unique(array_map(strtoupper(...), $countries)));

		return $this;
	}

	/**
	 * @param array<string>|null $allowedCountries null inherits this factory's own
	 *        (see {@see self::for()}); [] means free-form.
	 */
	public function createAddressField(string $name, ?array $allowedCountries = null): Field\Address
	{
		return new Field\Address($this->name($name), $allowedCountries ?? $this->defaultCountries);
	}

	public function createBooleanField(string $name): Field\Boolean
	{
		return new Field\Boolean($this->name($name));
	}

	public function createCreditCardField(string $name): Field\CreditCard
	{
		return new Field\CreditCard($this->name($name));
	}

	/**
	 * A repeatable list. The template says what one item is made of, and every item is
	 * validated against all of it:
	 *
	 *     $fields->createCollectionField('sessions', fn(Factory $f): array => [
	 *         $f->createDateTimeField('starts_at'),
	 *         $f->createDateTimeField('ends_at'),
	 *     ]);
	 *
	 * @param callable(self): list<Field> $template
	 */
	public function createCollectionField(string $name, callable $template): Field\Collection
	{
		return new Field\Collection($this->name($name), ...$template($this));
	}

	public function createDateField(string $name): Field\Date
	{
		return new Field\Date($this->name($name));
	}

	public function createDateTimeField(
		string $name,
		DateTime\TimePrecision $precision = DateTime\TimePrecision::Minutes,
	): Field\DateTime {
		return new Field\DateTime($this->name($name), $precision);
	}

	public function createDurationField(string $name): Field\Duration
	{
		return new Field\Duration($this->name($name));
	}

	public function createEmailAddressField(string $name): Field\EmailAddress
	{
		return new Field\EmailAddress($this->name($name));
	}

	/**
	 * @param list<scalar> $cases the values this field may hold — the list *is* the type
	 */
	public function createEnumField(string $name, array $cases): Field\Enum
	{
		return new Field\Enum($this->name($name), $cases);
	}

	public function createFileField(string $name): Field\File
	{
		return new Field\File($this->name($name));
	}

	/**
	 * @param array<string, int> $allowedCurrencies currency code => scale
	 */
	public function createMoneyField(string $name, array $allowedCurrencies): Field\Money
	{
		return new Field\Money($this->name($name), $allowedCurrencies);
	}

	public function createNameField(string $name): Field\Name
	{
		return new Field\Name($this->name($name));
	}

	public function createNumberField(string $name, ?int $scale = null): Field\Number
	{
		return new Field\Number($this->name($name), $scale);
	}

	public function createPassphraseField(string $name): Field\Passphrase
	{
		return new Field\Passphrase($this->name($name));
	}

	public function createPasswordField(string $name): Field\Password
	{
		return new Field\Password($this->name($name));
	}

	/**
	 * @param array<string>|null $allowedCountries null inherits this factory's own
	 *        (see {@see self::for()}); [] means international-only.
	 */
	public function createPhoneNumberField(string $name, ?array $allowedCountries = null): Field\PhoneNumber
	{
		return new Field\PhoneNumber($this->name($name), $allowedCountries ?? $this->defaultCountries);
	}

	public function createPlaceholderField(string $name): Field\Placeholder
	{
		return new Field\Placeholder($this->name($name));
	}

	public function createTextField(string $name): Field\Text
	{
		return new Field\Text($this->name($name));
	}

	public function createTimeField(
		string $name,
		Time\Precision $precision = Time\Precision::Minutes,
	): Field\Time {
		return new Field\Time($this->name($name), $precision);
	}

	public function createUriField(string $name): Field\Uri
	{
		return new Field\Uri($this->name($name));
	}

	public function createUuidField(string $name): Field\Uuid
	{
		return new Field\Uuid($this->name($name));
	}

	/**
	 * @param list<Field> $alternatives
	 */
	public function createVariantField(string $name, array $alternatives): Field\Variant
	{
		return new Field\Variant($this->name($name), ...$alternatives);
	}

	private function name(string $name): Property\Name
	{
		return new Property\Name($name);
	}
}
