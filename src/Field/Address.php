<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Address\Type;
use Meraki\Schema\Field\Composite as CompositeField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\AddressFormat;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use InvalidArgumentException;

/**
 * A postal/street address, validated against Google's libaddressinput data via
 * `commerceguys/addressing`.
 *
 * With no allowed countries it is free-form: every part is a plain text field and
 * nothing but `line1` is required. Once one or more countries are allowed, that
 * country's rules apply — its postal code pattern, its required parts, and its list of
 * subdivisions — mirroring how {@see PhoneNumber} treats its own country whitelist.
 *
 * Validation here is of *shape*, not existence. A postcode matching `\d{4}` is a
 * well-formed Australian postcode, not necessarily a real one, and a postcode never
 * implies a state (Queensland is 4xxx *and* 9xxx; the ACT's 2600-2618 sits inside New
 * South Wales' 2xxx). Verifying an address actually exists needs a licensed service.
 *
 * @extends CompositeField<array|null>
 *
 * @property-read Field\Text $organization
 * @property-read Field\Text $line1
 * @property-read Field\Text $line2
 * @property-read Field\Text $dependentLocality
 * @property-read Field\Text $locality
 * @property-read Field\Text|Field\Enum $administrativeArea
 * @property-read Field\Text $postalCode
 * @property-read Field\Text|Field\Enum $countryCode
 */
final class Address extends CompositeField
{
	/**
	 * Our sub-field names, in render order, mapped to the libaddressinput field they
	 * correspond to. The street lines are `line1`/`line2` rather than upstream's
	 * `addressLine1`/`addressLine2` so they read as `address.line1` rather than
	 * stuttering as `address.address_line1`.
	 *
	 * `country_code` has no upstream counterpart — it is what selects the format.
	 */
	private const FIELDS = [
		'organization' => AddressField::ORGANIZATION,
		'line1' => AddressField::ADDRESS_LINE1,
		'line2' => AddressField::ADDRESS_LINE2,
		'dependent_locality' => AddressField::DEPENDENT_LOCALITY,
		'locality' => AddressField::LOCALITY,
		'administrative_area' => AddressField::ADMINISTRATIVE_AREA,
		'postal_code' => AddressField::POSTAL_CODE,
		'country_code' => null,
	];

	/**
	 * Post-office boxes and bag services: mailable, but not places you can go. Anchored
	 * to the start of the line, and the rural forms require a number so that a street
	 * genuinely named "Rrunway" or similar cannot trip them.
	 */
	private const PO_BOX_PATTERN = '/^\s*(?:p\.?\s*o\.?\s*box|post\s+office\s+box|g\.?p\.?o\.?\s*box|locked\s+bag|private\s+bag|(?:rsd|rmb|hc|rr)\s*\d)/i';

	/**
	 * Allowed countries as ISO 3166-1 alpha-2 codes, upper-cased. Empty means free-form:
	 * any input is accepted and only `line1` is required.
	 *
	 * @var array<string>
	 */
	public array $allowed = [];

	public Type $type = Type::Either;

	/** @param array<string> $allowedCountries */
	public function __construct(Property\Name $name, array $allowedCountries = [])
	{
		parent::__construct(
			$name,
			new Field\Text(new Property\Name('organization')),
			new Field\Text(new Property\Name('line1')),
			new Field\Text(new Property\Name('line2')),
			new Field\Text(new Property\Name('dependent_locality')),
			new Field\Text(new Property\Name('locality')),
			new Field\Text(new Property\Name('administrative_area')),
			new Field\Text(new Property\Name('postal_code')),
			new Field\Text(new Property\Name('country_code')),
		);

		$this->applyWhitelist();
		$this->allow(...$allowedCountries);
	}

	/**
	 * Restricts the address to the given countries, as ISO 3166-1 alpha-2 codes.
	 *
	 * Repeated calls accumulate. Call this before prefilling: it rebuilds the country and
	 * administrative-area sub-fields (a closed set of options becomes a
	 * {@see Field\Enum}), carrying any existing values across.
	 */
	public function allow(string ...$countries): self
	{
		$supported = self::countries()->getList();

		foreach ($countries as $country) {
			$country = strtoupper($country);

			if (!isset($supported[$country])) {
				throw new InvalidArgumentException("Country '{$country}' is not a supported region.");
			}

			if (!in_array($country, $this->allowed, true)) {
				$this->allowed[] = $country;
			}
		}

		$this->applyWhitelist();

		return $this;
	}

	public function ofType(Type $type): self
	{
		$this->type = $type;

		return $this;
	}

	/**
	 * The local names of sub-fields whose value the whitelist has already decided, and
	 * which therefore need no input — a single allowed country determines `country_code`.
	 *
	 * Such fields are prefilled with that value, so it is still present in the submitted
	 * and serialized address. Rendering them as hidden inputs is up to the UI.
	 *
	 * @return array<string>
	 */
	public function determined(): array
	{
		$determined = [];

		foreach (self::FIELDS as $local => $_) {
			$field = $this->subField($local);

			if ($field instanceof Field\Enum && count($field->oneOf) === 1) {
				$determined[] = $local;
			}
		}

		return $determined;
	}

	protected function getConstraints(): array
	{
		$constraints = [
			$this->constraintName('line1', 'visitable') => $this->validateNotAPoBox(...),
			$this->constraintName('postal_code', 'format') => $this->validatePostalCode(...),
			$this->constraintName('administrative_area', 'allowed') => $this->validateAdministrativeArea(...),
			$this->constraintName('country_code', 'allowed') => $this->validateCountry(...),
		];

		// Required-ness varies by the country actually chosen, so it cannot be expressed
		// by the static `optional` flag alone once several countries are allowed.
		foreach (self::FIELDS as $local => $_) {
			$constraints[$this->constraintName($local, 'required')] = fn(array $value): ?bool
				=> $this->validateRequired($local, $value);
		}

		return $constraints;
	}

	private function validateNotAPoBox(array $value): ?bool
	{
		if (!$this->type->requiresVisitableLocation()) {
			return null;
		}

		$line1 = $this->valueOf('line1', $value);

		if (!is_string($line1) || $line1 === '') {
			return null;
		}

		return preg_match(self::PO_BOX_PATTERN, $line1) !== 1;
	}

	private function validatePostalCode(array $value): ?bool
	{
		$format = $this->formatFor($value);
		$pattern = $format?->getPostalCodePattern();
		$postalCode = $this->valueOf('postal_code', $value);

		if ($pattern === null || !is_string($postalCode) || $postalCode === '') {
			return null;
		}

		return preg_match('~^(?:' . $pattern . ')$~', $postalCode) === 1;
	}

	private function validateAdministrativeArea(array $value): ?bool
	{
		$country = $this->resolvedCountry($value);
		$area = $this->valueOf('administrative_area', $value);

		if ($country === null || !is_string($area) || $area === '') {
			return null;
		}

		$subdivisions = self::subdivisions()->getList([$country]);

		// A country with no subdivisions on file (e.g. Singapore) constrains nothing.
		if ($subdivisions === []) {
			return null;
		}

		return isset($subdivisions[$area]);
	}

	private function validateCountry(array $value): ?bool
	{
		if ($this->allowed === []) {
			return null;
		}

		$country = $this->valueOf('country_code', $value);

		if (!is_string($country) || $country === '') {
			return null;
		}

		return in_array(strtoupper($country), $this->allowed, true);
	}

	/**
	 * Whether a part the resolved country insists on was actually supplied. Skipped
	 * whenever the country is unknown, since there is then no rule to apply.
	 */
	private function validateRequired(string $local, array $value): ?bool
	{
		$format = $this->formatFor($value);

		if ($format === null) {
			return null;
		}

		if (!in_array($local, self::localNamesFor($format->getRequiredFields()), true)) {
			return null;
		}

		$supplied = $this->valueOf($local, $value);

		return is_string($supplied) && trim($supplied) !== '';
	}

	/**
	 * Rebuilds the whitelist-dependent sub-fields and re-derives which parts are
	 * required, so that {@see self::allow()} and the constructor share one code path.
	 */
	private function applyWhitelist(): void
	{
		$this->replaceSubField($this->buildCountryField());
		$this->replaceSubField($this->buildAdministrativeAreaField());
		$this->applyRequiredness();

		// Re-run what we hold back through process() and down onto the sub-fields, so a
		// country newly determined by the whitelist reaches both the composite's own
		// value and the rebuilt country field.
		$this->prefill($this->toLocalKeys($this->defaultValue->unwrap()));

		if ($this->inputGiven) {
			$this->input($this->toLocalKeys($this->value->unwrap()));
		}
	}

	/**
	 * Normalises the country to upper case, and keeps a determined country present even
	 * when the caller omits it — otherwise an address restricted to one country would
	 * serialize without the country it is restricted to.
	 *
	 * @param array|null $value
	 */
	protected function process($value): Property\Value
	{
		$value = parent::process($value)->unwrap();

		// Unusable input is passed through untouched, so there is no country to settle.
		// validate() reports it as a shape failure on the composite.
		if (!is_array($value)) {
			return new Property\Value($value);
		}

		$key = (string) (new Property\Name('country_code'))->prefixWith($this->name);
		$country = $value[$key] ?? null;

		if (is_string($country) && $country !== '') {
			$value[$key] = strtoupper($country);
		} elseif (count($this->allowed) === 1) {
			$value[$key] = $this->allowed[0];
		}

		return new Property\Value($value);
	}

	/**
	 * Re-keys a composite value from full prefixed names back to the local names that
	 * {@see Composite::process()} expects.
	 *
	 * @return array<string, mixed>
	 */
	private function toLocalKeys(array $value): array
	{
		$local = [];

		foreach ($this->fields as $field) {
			$local[(string) $field->name->removePrefix()] = $value[(string) $field->name] ?? null;
		}

		return $local;
	}

	private function buildCountryField(): Field
	{
		$name = new Property\Name('country_code');

		return $this->allowed === []
			? new Field\Text($name)
			: new Field\Enum($name, array_values($this->allowed));
	}

	/**
	 * A closed set of subdivisions only exists for a single allowed country: with several,
	 * which are valid depends on the country chosen, so it stays free text and is checked
	 * server-side by {@see self::validateAdministrativeArea()}.
	 */
	private function buildAdministrativeAreaField(): Field
	{
		$name = new Property\Name('administrative_area');

		if (count($this->allowed) !== 1) {
			return new Field\Text($name);
		}

		$subdivisions = self::subdivisions()->getList([$this->allowed[0]]);

		return $subdivisions === []
			? new Field\Text($name)
			: new Field\Enum($name, array_keys($subdivisions));
	}

	/**
	 * Marks each part optional unless every allowed country requires it. Taking the
	 * intersection means the static flag (which drives a UI's `required` marker) never
	 * over-promises when countries disagree; {@see self::validateRequired()} then applies
	 * the chosen country's actual rule at validation time.
	 */
	private function applyRequiredness(): void
	{
		$required = ['line1'];

		foreach ($this->allowed as $index => $country) {
			$countryRequires = self::localNamesFor(self::formats()->get($country)->getRequiredFields());

			$required = $index === 0 ? $countryRequires : array_intersect($required, $countryRequires);
		}

		// The country is implied by the whitelist rather than supplied by the format.
		if ($this->allowed !== []) {
			$required[] = 'country_code';
		}

		foreach (self::FIELDS as $local => $_) {
			$field = $this->subField($local);

			in_array($local, $required, true) ? $field->require() : $field->makeOptional();
		}
	}

	/**
	 * Swaps a sub-field for one of a different type, keeping its position. Values are not
	 * carried across here — {@see self::applyWhitelist()} re-pushes them immediately
	 * afterwards, which also gives `process()` a chance to fill in a determined country.
	 */
	private function replaceSubField(Field $replacement): void
	{
		$replacement->rename($replacement->name->prefixWith($this->name));

		$fields = [];

		foreach ($this->fields as $existing) {
			$fields[] = (string) $existing->name === (string) $replacement->name ? $replacement : $existing;
		}

		$this->fields = new Field\Set(...$fields);
	}

	private function subField(string $local): Field
	{
		$name = (new Property\Name($local))->prefixWith($this->name);
		$field = $this->fields->findByName($name);

		if ($field === null) {
			throw new InvalidArgumentException("Address has no '{$local}' field.");
		}

		return $field;
	}

	private function constraintName(string $local, string $constraint): string
	{
		return (string) (new Property\Name($local))->prefixWith($this->name) . '.' . $constraint;
	}

	/** Reads a sub-field out of a composite value array, which is keyed by full name. */
	private function valueOf(string $local, array $value): mixed
	{
		return $value[(string) (new Property\Name($local))->prefixWith($this->name)] ?? null;
	}

	/**
	 * The country whose rules apply: the one supplied, or the only allowed one. Null when
	 * it cannot be pinned down, which makes the country-specific constraints skip.
	 */
	private function resolvedCountry(array $value): ?string
	{
		// Free-form means free-form: a country typed into an unrestricted address is data,
		// not a rule to start enforcing a postcode format with.
		if ($this->allowed === []) {
			return null;
		}

		$supplied = $this->valueOf('country_code', $value);

		if (is_string($supplied) && $supplied !== '') {
			$supplied = strtoupper($supplied);

			// A country that is unrecognised, or recognised but not allowed, is the
			// country field's problem to report. Deriving postcode and subdivision rules
			// from it as well would turn one mistake into three failures.
			if (!isset(self::countries()->getList()[$supplied])) {
				return null;
			}

			if ($this->allowed !== [] && !in_array($supplied, $this->allowed, true)) {
				return null;
			}

			return $supplied;
		}

		return count($this->allowed) === 1 ? $this->allowed[0] : null;
	}

	private function formatFor(array $value): ?AddressFormat
	{
		$country = $this->resolvedCountry($value);

		return $country === null ? null : self::formats()->get($country);
	}

	/**
	 * Translates libaddressinput field names into ours, dropping the ones we do not model
	 * — the person-name parts (which belong on a separate name field), `addressLine3` and
	 * `sortingCode`.
	 *
	 * @param array<string> $addressFields
	 * @return array<string>
	 */
	private static function localNamesFor(array $addressFields): array
	{
		$locals = [];

		foreach (self::FIELDS as $local => $addressField) {
			if ($addressField !== null && in_array($addressField, $addressFields, true)) {
				$locals[] = $local;
			}
		}

		return $locals;
	}

	private static function formats(): AddressFormatRepository
	{
		static $repository = null;

		return $repository ??= new AddressFormatRepository();
	}

	private static function subdivisions(): SubdivisionRepository
	{
		static $repository = null;

		return $repository ??= new SubdivisionRepository();
	}

	private static function countries(): CountryRepository
	{
		static $repository = null;

		return $repository ??= new CountryRepository();
	}
}
