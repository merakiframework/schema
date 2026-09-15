<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Brick\DateTime\Clock;
use Meraki\Schema\Field;
use Meraki\Schema\FieldName;

/**
 * Builds fields, for whatever holds them.
 *
 * Used by {@see \Meraki\Schema\Facade}, so a schema builds its own fields the same way it builds
 * its own rules:
 *
 * ```php
 * $schema = new \Meraki\Schema\Facade('signup');
 *
 * $schema->add($schema->createTextField('username')->minLengthOf(3));
 * $schema->addRule($schema->when($username)->equals('admin')->thenRequire($nickname));
 * ```
 *
 * This replaced a separate `Field\Factory` object. Two entry points for one job is what
 * docs/API-REVIEW.md exists to remove, and the factory had no state worth being a separate
 * object for — only the country list below, which belongs to the schema being authored anyway.
 *
 * **Building is not registering.** Every method here hands back a field and does nothing else;
 * {@see \Meraki\Schema\Facade::add()} is what puts it in the schema. The two were one call until
 * fields were sealed, and then `$schema->addTextField('x')->minLengthOf(3)` started adding a
 * field and configuring a *copy* of it, leaving the schema holding the unconfigured original.
 * Separating them makes the order impossible to get wrong: build, finish configuring, then add
 * what you finished with.
 *
 * A trait rather than methods written directly on the Facade, for the same reason
 * {@see Definition} is one: a schema already holds fields, builds rules and validates requests,
 * and nineteen field builders interleaved with that would bury all three.
 */
trait BuildsFields
{
	/**
	 * Countries that region-aware fields default to, as ISO 3166-1 alpha-2 codes.
	 *
	 * @var array<string>
	 */
	private array $defaultCountries = [];

	/**
	 * Where *now* comes from, for the fields that ask.
	 *
	 * Declared once here rather than passed to each field that wants one — the same shape as
	 * {@see self::for()}, and for the same reason: it is a property of the thing being built, not
	 * of each field in it.
	 *
	 * A *source* of the instant, never an instant. See {@see CreditCard} for why that distinction
	 * is the whole point.
	 */
	private Clock $clock;

	/**
	 * Declares the countries this schema is for, so region-aware fields need not repeat them:
	 *
	 *     $schema = (new Facade('booking'))->for('AU');
	 *     $schema->createAddressField('billing');            // restricted to AU
	 *     $schema->createPhoneNumberField('mobile');         // ditto
	 *     $schema->createAddressField('shipping', ['NZ']);   // an explicit list still wins
	 *     $schema->createAddressField('other', []);          // and an explicit [] means free-form
	 *
	 * Applies to {@see Address} and {@see PhoneNumber} — the fields whose rules are
	 * jurisdictional. Deliberately not to {@see Money}: a currency does not follow from a region,
	 * since a country may use several and the euro spans twenty.
	 *
	 * It says which countries are *acceptable*. A submitted address still has to name the one it
	 * is in, the way an amount of money has to name its currency.
	 *
	 * @param string ...$countries ISO 3166-1 alpha-2 region codes
	 */
	public function for(string ...$countries): static
	{
		// Trimmed and upper-cased because these are the author's own strings in their own source,
		// not untrusted input — the one place repair is appropriate. See docs/CODING-STYLE.md.
		$this->defaultCountries = array_values(array_unique(
			array_map(static fn(string $c): string => strtoupper(trim($c)), $countries),
		));

		return $this;
	}

	/**
	 * @param array<string>|null $allowedCountries null inherits this schema's own
	 *        (see {@see self::for()}); [] means free-form.
	 */
	public function createAddressField(string $name, ?array $allowedCountries = null): Address
	{
		return new Address(new FieldName($name), $allowedCountries ?? $this->defaultCountries);
	}

	public function createBooleanField(string $name): Boolean
	{
		return new Boolean(new FieldName($name));
	}

	public function createCreditCardField(string $name): CreditCard
	{
		return new CreditCard(new FieldName($name), $this->clock);
	}

	/**
	 * A repeatable list. The template says what one item is made of, and every item is validated
	 * against all of it:
	 *
	 *     $schema->createCollectionField('attachments', $schema->createFileField('file'));
	 *
	 *     $schema->createCollectionField(
	 *         'sessions',
	 *         $schema->createDateTimeField('starts_at'),
	 *         $schema->createDateTimeField('ends_at'),
	 *     );
	 *
	 * The fields are passed in, not built by a callback the collection invokes. A callback was
	 * needed while a collection *prefixed* its template's names and so had to build them itself;
	 * it owns its whole value now, exactly as {@see Address} and {@see Money} do, so a template
	 * field is an ordinary field and this is an ordinary argument list — the same shape as
	 * {@see self::createEnumField()}, where the cases are handed over rather than produced.
	 *
	 * They inherit {@see self::for()} for free, because whatever built them already had it.
	 *
	 * @param Field ...$template at least one field; every item is checked against all of them
	 */
	public function createCollectionField(string $name, Field ...$template): Collection
	{
		return new Collection(new FieldName($name), ...$template);
	}

	public function createDateField(string $name): Date
	{
		return new Date(new FieldName($name));
	}

	public function createDateTimeField(
		string $name,
		DateTime\TimePrecision $precision = DateTime\TimePrecision::Minutes,
	): DateTime {
		return new DateTime(new FieldName($name), $precision);
	}

	public function createDurationField(string $name): Duration
	{
		return new Duration(new FieldName($name));
	}

	public function createEmailAddressField(string $name): EmailAddress
	{
		return new EmailAddress(new FieldName($name));
	}

	/**
	 * @param list<scalar> $cases the values this field may hold — the list *is* the type
	 */
	public function createEnumField(string $name, array $cases): Enum
	{
		return new Enum(new FieldName($name), $cases);
	}

	public function createFileField(string $name): File
	{
		return new File(new FieldName($name));
	}

	/**
	 * @param array<int|string, string|int> $allowedCurrencies a bare code takes the currency's own
	 *        ISO 4217 scale; a `code => scale` entry overrides it. See {@see Money::allowCurrencies()}
	 */
	public function createMoneyField(string $name, array $allowedCurrencies): Money
	{
		return new Money(new FieldName($name), $allowedCurrencies);
	}

	public function createNameField(string $name): Name
	{
		return new Name(new FieldName($name));
	}

	public function createNumberField(string $name, ?int $scale = null): Number
	{
		return new Number(new FieldName($name), $scale);
	}

	public function createPasswordField(string $name): Password
	{
		return new Password(new FieldName($name));
	}

	/**
	 * @param array<string>|null $allowedCountries null inherits this schema's own
	 *        (see {@see self::for()}); [] means international-only.
	 */
	public function createPhoneNumberField(string $name, ?array $allowedCountries = null): PhoneNumber
	{
		return new PhoneNumber(new FieldName($name), $allowedCountries ?? $this->defaultCountries);
	}

	public function createTextField(string $name): Text
	{
		return new Text(new FieldName($name));
	}

	public function createTimeField(
		string $name,
		Time\Precision $precision = Time\Precision::Minutes,
	): Time {
		return new Time(new FieldName($name), $precision);
	}

	public function createUriField(string $name): Uri
	{
		return new Uri(new FieldName($name));
	}

	public function createUuidField(string $name): Uuid
	{
		return new Uuid(new FieldName($name));
	}
}
