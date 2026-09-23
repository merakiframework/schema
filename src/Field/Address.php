<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Field\Address\Type;
use Meraki\Schema\Field\Address\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;
use CommerceGuys\Addressing\AddressFormat\AddressFormat;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use InvalidArgumentException;

/**
 * A postal or street address, held as one {@see Value} — the way {@see File} holds a
 * `File\Value`.
 *
 * It used to be a bag of eight sub-fields registered in the schema's namespace, which meant every
 * constraint it emitted was named by joining strings: renaming `billing` to `invoice_address`
 * changed `billing.postal_code.format` into `invoice_address.postal_code.format` and broke every
 * message provider matching on it. Now the field owns its whole value, the constraint names are
 * fixed, and each failure names the *part* it was about separately.
 *
 * Validation is of *shape*, not existence. A postcode matching `\d{4}` is a well-formed Australian
 * postcode, not necessarily a real one, and a postcode never implies a state — Queensland is 4xxx
 * *and* 9xxx, and the ACT's 2600-2618 sits inside New South Wales' 2xxx. Confirming an address
 * exists needs a licensed service.
 *
 * ### The country is always submitted
 *
 * The same pairing {@see Money} makes between an amount and its currency, for the same reason: a
 * postcode means nothing on its own. An address without a country is a shape failure, not a vague
 * address.
 *
 * It may be written as a name or a code, in any case — `AU`, `au` and `Australia` are one country
 * — and is stored as the code. Requiring it deleted the rule that filled the country in when the
 * allow-list happened to hold exactly one, which was a rule that changed shape depending on how
 * many countries were listed.
 *
 * ### Two dials, not one enum
 *
 * What an address is *for* and how much of it is required are separate questions, and conflating
 * them was the mistake. Both start unrestricted and are narrowed:
 *
 * - {@see self::allowOnlyMailable()} and {@see self::allowOnlyPhysical()} say what it must be
 *   capable of. Neither called means either purpose is acceptable; both called means it must manage
 *   both. A PO box is mailable and not visitable; a service area covering a suburb is visitable and
 *   not mailable.
 * - {@see self::allowWithoutStreet()} drops the street requirement. An address names a street by
 *   default — anything less is the exception, and the author says so.
 *
 * @extends AtomicField<array<string, mixed>|Value|null>
 */
final readonly class Address extends AtomicField
{
	/**
	 * Post-office boxes and bag services: mailable, but not places you can go. Anchored to the
	 * start of the line, and the rural forms require a number so a street genuinely named
	 * "Rrunway" or similar cannot trip them.
	 */
	private const PO_BOX_PATTERN = '/^\s*(?:p\.?\s*o\.?\s*box|post\s+office\s+box|g\.?p\.?o\.?\s*box|locked\s+bag|private\s+bag|(?:rsd|rmb|hc|rr)\s*\d)/i';

	/**
	 * Allowed countries as ISO 3166-1 alpha-2 codes, upper-cased. Empty means free-form: any input
	 * is accepted, and no country's rules are applied.
	 *
	 * @var list<string>
	 */
	public array $allowedCountries;

	public Type $type;

	/**
	 * Whether the address must name a street rather than just an area. **True by default**: an
	 * address is a place, and a suburb with a postcode is a region that contains places. A field
	 * that genuinely wants the region says so with {@see self::allowWithoutStreet()}.
	 */
	public bool $mustBeSpecific;

	/**
	 * @param array<string> $allowedCountries
	 * @throws InvalidArgumentException if a country is not one libaddressinput knows
	 */
	public function __construct(
		public FieldName $name,
		array $allowedCountries = [],
	) {
		parent::__construct();

		$this->type = Type::Either;
		$this->mustBeSpecific = true;
		$this->allowedCountries = self::supported([], $allowedCountries);

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * Restricts the address to the given countries, named or coded — `'AU'`, `'au'` and
	 * `'Australia'` are the same country. Accumulates, like every other `allow*()`.
	 *
	 * Once one country is allowed, that country's rules apply — its postcode pattern in
	 * particular. With several, a postcode rule only applies once the submitted country says which
	 * of them it is.
	 *
	 * @throws InvalidArgumentException if a country is not one libaddressinput knows
	 */
	public function allowCountries(string $country, string ...$countries): static
	{
		return $this->with(['allowedCountries' => self::supported($this->allowedCountries, [$country, ...$countries])]);
	}

	/**
	 * Accepts any country again, which also stops any country's postcode rule being applied.
	 */
	public function clearAllowedCountries(): static
	{
		return $this->with(['allowedCountries' => []]);
	}

	/**
	 * Requires somewhere the post can reach. Narrows rather than replaces, so asking for this and
	 * for {@see self::allowOnlyPhysical()} leaves an address that must manage both.
	 *
	 * @throws InvalidArgumentException if the field has been told to accept an address without a
	 *         street, since you cannot post to a suburb
	 */
	public function allowOnlyMailable(): static
	{
		$this->assertStreetAndPostAgree(Type::Postal, $this->mustBeSpecific);

		return $this->with(['type' => $this->type->narrowedToMailable()]);
	}

	/**
	 * Requires somewhere you can physically go, which rules out a PO box. Narrows rather than
	 * replaces.
	 */
	public function allowOnlyPhysical(): static
	{
		return $this->with(['type' => $this->type->narrowedToPhysical()]);
	}

	/**
	 * Accepts an address that names only an area — a suburb with a state and a postcode.
	 *
	 * For a service area or a catchment, where the region *is* the answer rather than an incomplete
	 * version of one.
	 *
	 * @throws InvalidArgumentException if the address must be mailable, since you cannot post to a
	 *         suburb
	 */
	public function allowWithoutStreet(): static
	{
		$this->assertStreetAndPostAgree($this->type, false);

		return $this->with(['mustBeSpecific' => false]);
	}

	/**
	 * Refuses a mailable address that does not require a street.
	 *
	 * Guarded on both withers rather than one, because either call can be the second: narrowing to
	 * mailable after allowing no street reaches the same incoherent pair from the other side.
	 *
	 * Raised where the definition is written rather than reported per request, because it is a
	 * combination with no meaning rather than a value that happens to be wrong — there is no input
	 * that could satisfy it, so there is nothing to report.
	 */
	private function assertStreetAndPostAgree(Type $type, bool $mustBeSpecific): void
	{
		if ($type->requiresDeliverability() && !$mustBeSpecific) {
			throw new InvalidArgumentException(sprintf(
				'A %s address must name a street, so it cannot also be allowed without one.',
				$type->value,
			));
		}
	}

	/**
	 * What a rule may ask about this field: an address is neither ranked nor read as one string; its parts are, through PartScope.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Basic
	{
		return new Matcher\Basic(ValueScope::of($this->name));
	}

	/**
	 * Turns what was submitted into a {@see Value}.
	 *
	 * A country is upper-cased, and **never filled in**. It used to be supplied when the allow-list
	 * happened to hold exactly one country, which made the rule change shape depending on how many
	 * were listed — an address was complete or incomplete according to a detail of the field's
	 * configuration rather than according to what was submitted. A country is now always the
	 * submitter's to give, the same pairing {@see Money} makes with a currency.
	 *
	 * See `Field\AddressTest::an_address_without_a_country_never_described_a_place`.
	 *
	 * @param array<string, mixed>|Value $value
	 */
	protected function parse(mixed $value): Value
	{
		if ($value instanceof Value) {
			return $value;
		}

		// An object is a record; an array is a list. An address has named parts, so it arrives
		// as the former — see Definition::recordIn().
		if (!is_object($value)) {
			throw MalformedValue::of(Value::class, 'an address is submitted as a record of its parts');
		}

		// Emptiness, the country pairing and the code-or-name canonicalising are all the value's
		// now. Each is a fact about an address rather than about this field: no configuration
		// makes an address with no country describe a place.
		return new Value($value);
	}

	/**
	 * Turns a country given by name into its code, so everything downstream has one shape to read.
	 *
	 * A country that is neither a known name nor a known code is left exactly as it came, for
	 * `allowedCountries` to report — rewriting it would lose what the author actually typed, and
	 * guessing at a near-miss is not this field's business.
	 */

	/**
	 * Four constraints, each naming the part it is about rather than embedding this field's name.
	 */
	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('allowedCountries', $this->isAnAllowedCountry(...), $this->allowedCountries, 'country'),
			// The pattern depends on which country was submitted, so the declared bound is only
			// knowable when one country is allowed; `boundFor` supplies the one that applied.
			new Constraint(
				'postalCodeFormat',
				$this->matchesPostalCodeFormat(...),
				$this->postalCodePattern(),
				'postal_code',
				fn(Value $address): ?string => $this->postalCodePatternFor($address),
			),
			// No bound: a country's subdivision list runs to fifty-odd entries for the United
			// States, which no message wants interpolated into it.
			new Constraint('administrativeArea', $this->isAKnownSubdivision(...), null, 'administrative_area'),
			// No bound: "this must be somewhere you can go" has nothing to interpolate.
			new Constraint('line1Visitable', $this->isVisitable(...), null, 'line1'),
			new Constraint('specific', $this->namesAStreet(...), null, 'line1'),
		);
	}

	private function isAnAllowedCountry(Value $address): ?bool
	{
		if ($this->allowedCountries === []) {
			return null;
		}

		// Nothing to judge. Whether a country is *required* is a separate question, and not one
		// this field asks by default.
		if ($address->countryCode === null) {
			return null;
		}

		return in_array($address->countryCode, $this->allowedCountries, true);
	}

	private function matchesPostalCodeFormat(Value $address): ?bool
	{
		$pattern = $this->postalCodePatternFor($address);

		if ($pattern === null || $address->postalCode === null) {
			return null;
		}

		return preg_match('~^(?:' . $pattern . ')$~', $address->postalCode) === 1;
	}

	/**
	 * Whether the state, province or region is one the country actually has.
	 *
	 * Shape rather than existence, like the postcode: `QLD` is a well-formed Queensland, and
	 * whether the street within it exists is a licensed service's question.
	 */
	private function isAKnownSubdivision(Value $address): ?bool
	{
		$country = $this->resolvedCountry($address);

		if ($country === null || $address->administrativeArea === null) {
			return null;
		}

		$subdivisions = self::subdivisions()->getList([$country]);

		// A country with none on file — Singapore, say — constrains nothing.
		return $subdivisions === [] ? null : isset($subdivisions[$address->administrativeArea]);
	}

	private function isVisitable(Value $address): ?bool
	{
		if (!$this->type->requiresVisitableLocation() || $address->line1 === null) {
			return null;
		}

		return preg_match(self::PO_BOX_PATTERN, $address->line1) !== 1;
	}

	private function namesAStreet(Value $address): ?bool
	{
		// `''` counts as no street. It is a *submitted* empty string rather than an absent part —
		// see Value::fromInput() — and either way it does not name a street.
		return $this->mustBeSpecific ? ($address->line1 !== null && $address->line1 !== '') : null;
	}


	/**
	 * The pattern a message can interpolate — only knowable when one country is allowed, since
	 * with several it depends on which the submitted address turns out to be.
	 */
	private function postalCodePattern(): ?string
	{
		return count($this->allowedCountries) === 1
			? self::formats()->get($this->allowedCountries[0])->getPostalCodePattern()
			: null;
	}

	private function postalCodePatternFor(Value $address): ?string
	{
		return $this->formatFor($address)?->getPostalCodePattern();
	}

	private function formatFor(Value $address): ?AddressFormat
	{
		$country = $this->resolvedCountry($address);

		return $country === null ? null : self::formats()->get($country);
	}

	/**
	 * Which country's rules apply, or null when it cannot be pinned down — which makes the
	 * country-specific constraints skip rather than guess.
	 */
	private function resolvedCountry(Value $address): ?string
	{
		// Always present once parse() has accepted the address, so there is nothing to infer. Note
		// this now works for an *unrestricted* field too: the submitter said which country, so
		// checking their postcode against it is reading what they wrote rather than guessing. That
		// used to be impossible, and a free-form address got no postcode check at all.
		$country = $address->countryCode;

		// Already reported by `allowedCountries`. Deriving a postcode rule from it as well would
		// turn one mistake into two failures.
		if ($this->allowedCountries !== [] && !in_array($country, $this->allowedCountries, true)) {
			return null;
		}

		// Present but not a region libaddressinput knows, so there is no format to look up.
		return Value::codeFor($country) === null ? null : $country;
	}

	/**
	 * @param list<string> $existing
	 * @param array<string> $additional
	 * @return list<string>
	 * @throws InvalidArgumentException
	 */
	private static function supported(array $existing, array $additional): array
	{
		foreach ($additional as $country) {
			// Trimmed here and not in codeFor(), because this is the *author's* string written in
			// their own source. A submitted country is untrusted input and is left as it came.
			$code = Value::codeFor(trim($country));

			if ($code === null) {
				throw new InvalidArgumentException("Country '{$country}' is not a supported region.");
			}

			if (!in_array($code, $existing, true)) {
				$existing[] = $code;
			}
		}

		return $existing;
	}

	/**
	 * The ISO code for a country given either way round, or `null` if it is neither.
	 *
	 * Unambiguous: libaddressinput lists 256 countries, no two share a name, and no name collides
	 * with a code — so accepting both costs nothing in clarity. Accepting only the code would mean
	 * a form offering a country dropdown had to map it back before submitting.
	 */
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
}
