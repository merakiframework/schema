<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AtomicField;
use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\Field\Address\Precision;
use Meraki\Schema\Field\Address\Requirements;
use Meraki\Schema\Field\Address\Value;
use Meraki\Schema\FieldName;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\ValueScope;
use Closure;

/**
 * A postal or street address, held as one {@see Value} — the way {@see File} holds a
 * `File\Value`.
 *
 * Two dials, and they are independent:
 *
 * - {@see self::minPrecisionOf()} says how far down the address hierarchy the field demands.
 *   The floor filters the *country's* own required parts, so the field never invents a
 *   requirement — it only declines to inherit one. Because a shallower floor still accepts a
 *   deeper value, "an address or an area" is one field rather than a union.
 * - {@see self::mustBeVisitable()} says the address must name a place a person can attend,
 *   which rules out a post-office box. A content question about one part, not a depth one.
 *
 * They replaced a four-case `Type` enum borrowed from HL7 FHIR, where `postal | physical | both`
 * is descriptive metadata about an address someone already holds rather than a demand on a
 * submitter — which is why `Postal` had nothing to do at request time, and why no third case sat
 * comfortably beside the other two. Mailable, physical, both and either were four words for one
 * bit.
 *
 * What a country requires is read from its own published format, not guessed: 206 countries, of
 * which only 75 require a postcode and only 44 a subdivision, and 11 — Japan and Hong Kong among
 * them — do not require a locality. A check for a part the submitted country does not ask for
 * *skips*; it does not quietly pass.
 *
 * Everything country-driven is unanswerable until a country is submitted, and a field allows any
 * of them by default, so {@see self::requirementsFor()} is how a port asks in advance.
 *
 * ### An address is whole before it is judged
 *
 * Whether what arrived is an address at all — a country, and parts that country's own format has a
 * place for and can read — is decided by {@see Address\Input} before any constraint runs. No
 * configuration changes it: a postcode that does not match Australia's pattern is not an
 * Australian postcode on any field. The constraints below ask only what this field accepts —
 * which countries, how much of the address, and whether it must be somewhere a person can go —
 * and each is handed a {@see Value} with a country.
 *
 * @extends AtomicField<array<string, mixed>|Value|null>
 */
final readonly class Address extends AtomicField
{
	/**
	 * Post-office boxes and bag services: mailable, but not places you can go. Anchored to the
	 * start of a line, with `/m` so every line of a street is tested rather than only the first
	 * — putting the box on the second line used to slip past. The rural forms require a number
	 * so a street genuinely named "Rrunway" or similar cannot trip them.
	 */
	private const PO_BOX_PATTERN = '/^\s*(?:p\.?\s*o\.?\s*box|post\s+office\s+box|g\.?p\.?o\.?\s*box|locked\s+bag|private\s+bag|(?:rsd|rmb|hc|rr)\s*\d)/im';

	/**
	 * Allowed countries as ISO 3166-1 alpha-2 codes, upper-cased. Empty means free-form: any
	 * country is accepted, and the submitted one's rules are applied.
	 *
	 * @var list<string>
	 */
	public array $allowedCountries;

	/**
	 * How much of the address hierarchy this field demands. **Street by default**: it preserves
	 * the behaviour a field had before the ladder existed, and it fails closed — a shallower
	 * default would quietly stop asking for things.
	 */
	public Precision $precision;

	/**
	 * Whether the street must name somewhere a person can attend. **False by default**: a PO box
	 * is a perfectly good billing address, so refusing one is the narrower claim and the author
	 * makes it.
	 */
	public bool $streetVisitable;

	/**
	 * @param list<string> $allowedCountries
	 * @throws InvalidConfiguration if a country is not one ISO 3166-1 knows
	 */
	public function __construct(
		public FieldName $name,
		array $allowedCountries = [],
	) {
		parent::__construct();

		$this->precision = self::initially(Precision::Street);
		$this->streetVisitable = self::initially(false);
		$this->allowedCountries = self::initially(self::supported([], $allowedCountries));

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * Restricts the address to the given countries, named or coded — `'AU'`, `'au'`, `'AUS'` and
	 * `'Australia'` are the same country. Accumulates, like every other `allow*()`.
	 *
	 * Once one country is allowed, that country's rules become declarable up front. With
	 * several, they only settle once the submitted country says which of them it is —
	 * {@see self::requirementsFor()} is how to ask about each in the meantime.
	 *
	 * @throws InvalidConfiguration if a country is not one ISO 3166-1 knows
	 */
	public function allowCountries(string $country, string ...$countries): static
	{
		return $this->with(['allowedCountries' => self::supported($this->allowedCountries, [$country, ...$countries])]);
	}

	/**
	 * Accepts any country again. The submitted country's rules still apply; what stops is any
	 * rule being knowable before a request.
	 */
	public function clearAllowedCountries(): static
	{
		return $this->with(['allowedCountries' => []]);
	}

	/**
	 * Demands the address be specified at least this far down.
	 *
	 * A service area asks for {@see Precision::Locality}; a tax jurisdiction for
	 * {@see Precision::Subdivision}; "where are you based" for {@see Precision::Country}. Each
	 * still requires whatever that country asks for at or above the floor, so the rule stays the
	 * country's and the floor only decides how much of it to inherit.
	 */
	public function minPrecisionOf(Precision $precision): static
	{
		return $this->with(['precision' => $precision]);
	}

	/**
	 * Demands somewhere a person can physically attend, which rules out a post-office box.
	 *
	 * Independent of depth, so a field may ask for locality precision and still refuse a PO box
	 * if a street is given — a combination the old enum could not express.
	 */
	public function mustBeVisitable(): static
	{
		return $this->with(['streetVisitable' => true]);
	}

	/**
	 * What each of the given countries asks of an address, keyed by the spelling you asked with.
	 *
	 * The one way to read a country-driven bound. There is deliberately no `postalCodeRequired`
	 * property beside it: a fact with two accessors is a fact that can disagree with itself, and
	 * every one of these is unanswerable while more than one country is allowed.
	 *
	 * Spellings resolve through {@see Value::codeFor()}, the same resolver a submitted address
	 * goes through — so the countries you may *ask* about are exactly the countries this field
	 * will *accept*, and it refuses precisely the spellings that would make a value unreadable.
	 * The key is your own string and {@see Requirements::$country} is the canonical code, so one
	 * call also hands back the canonicalisation table a port would otherwise build itself.
	 *
	 * With no arguments it answers for every country the field allows.
	 *
	 * @return array<string, Requirements>
	 * @throws InvalidConfiguration if a country is not one ISO 3166-1 knows, if it is outside
	 *         this field's allow-list, if the same country is asked about twice under any
	 *         spelling, or if no countries are given and the field allows any
	 */
	public function requirementsFor(string ...$countries): array
	{
		if ($countries === []) {
			if ($this->allowedCountries === []) {
				throw InvalidConfiguration::requirementsNeedACountry();
			}

			$countries = $this->allowedCountries;
		}

		$requirements = [];
		$asked = [];

		// Resolved in full before any is returned: a partial map invites a silent gap where a
		// lookup quietly missed, which is worse than losing the answers that did resolve.
		foreach ($countries as $spelling) {
			$code = Value::codeFor($spelling);

			if ($code === null) {
				throw InvalidConfiguration::regionIsNotSupported($spelling);
			}

			if ($this->allowedCountries !== [] && !in_array($code, $this->allowedCountries, true)) {
				throw InvalidConfiguration::countryIsNotAllowedHere($spelling, $this->allowedCountries);
			}

			// Both outcomes of asking twice hide the mistake. One spelling repeated collapses to
			// a single entry, so the result is quietly shorter than the question. Two spellings
			// of one country give two keys holding the same answer, so a caller looping over
			// them does the work twice with nothing to show that it has.
			if (isset($asked[$code])) {
				throw InvalidConfiguration::countryAskedForTwice($code, [$asked[$code], $spelling]);
			}

			$asked[$code] = $spelling;
			$requirements[$spelling] = Requirements::forCountry($code, $this->precision);
		}

		return $requirements;
	}

	/**
	 * What a rule may ask about this field: an address is neither ranked nor read as one string;
	 * its parts are, through PartScope.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Basic
	{
		return new Matcher\Basic(ValueScope::of($this->name));
	}

	/**
	 * The record read part by part. Whether the parts make an address is the input's to say, and
	 * the lifecycle's to report — see {@see Address\Input}.
	 *
	 * @param object|Value $value
	 */
	protected function parse(mixed $value): Address\Input
	{
		if ($value instanceof Value) {
			return Address\Input::of($value);
		}

		// An object is a record; an array is a list. An address has named parts, so it arrives
		// as the former — see Definition::recordIn().
		if (!is_object($value)) {
			throw MalformedValue::of(Value::class, 'an address is submitted as a record of its parts');
		}

		return new Address\Input($value);
	}

	/**
	 * Six constraints, each naming the part it is about rather than embedding this field's name.
	 *
	 * The four `*Required` read {@see Requirements} through this field's precision floor, the same
	 * answer {@see self::requirementsFor()} gives a port, so what a port renders and what this
	 * judges cannot drift apart. Their declared `bound` is only knowable when exactly one country
	 * is allowed, and `boundFor` supplies the one that actually applied.
	 */
	protected function defineConstraints(): Constraint\Set
	{
		$declared = $this->declaredRequirements();

		return new Constraint\Set(
			new Constraint(Address\Check::AllowedCountries, $this->isAnAllowedCountry(...), $this->allowedCountries),
			// No bound: "this must be somewhere you can go" has nothing to interpolate.
			new Constraint(Address\Check::StreetVisitable, $this->isVisitable(...), null),
			new Constraint(
				Address\Check::StreetRequired,
				$this->requires('street'),
				$this->declaredRequirement($declared, 'street'),
				$this->appliedRequirement('street'),
			),
			new Constraint(
				Address\Check::LocalityRequired,
				$this->requires('locality'),
				$this->declaredRequirement($declared, 'locality'),
				$this->appliedRequirement('locality'),
			),
			new Constraint(
				Address\Check::SubdivisionRequired,
				$this->requires('subdivision'),
				$this->declaredRequirement($declared, 'subdivision'),
				$this->appliedRequirement('subdivision'),
			),
			new Constraint(
				Address\Check::PostalCodeRequired,
				$this->requires('postal_code'),
				$this->declaredRequirement($declared, 'postal_code'),
				$this->appliedRequirement('postal_code'),
			),
		);
	}

	protected static function declaredChecks(): array
	{
		return Address\Check::cases();
	}

	protected static function declaredParts(): array
	{
		return Address\Part::cases();
	}

	// ── reading the country's rules ────────────────────────────────────────────────────────

	/**
	 * What the submitted country asks of this field, or null when this field does not accept that
	 * country.
	 *
	 * Null rather than an answer, because `allowedCountries` already reports it and deriving a
	 * second failure from the same mistake turns one error into several.
	 */
	private function rulesFor(Value $address): ?Requirements
	{
		$country = $address->countryCode;

		if ($this->allowedCountries !== [] && !in_array($country, $this->allowedCountries, true)) {
			return null;
		}

		return Requirements::forCountry($country, $this->precision);
	}

	/** The rules that are knowable before a request: only when one country is allowed. */
	private function declaredRequirements(): ?Requirements
	{
		return count($this->allowedCountries) === 1
			? Requirements::forCountry($this->allowedCountries[0], $this->precision)
			: null;
	}

	/**
	 * Whether the part is required, when that is knowable before a request.
	 *
	 * Takes the resolved rules rather than fetching them, because `defineConstraints()` has
	 * already asked and every wither re-runs it.
	 */
	private function declaredRequirement(?Requirements $declared, string $part): ?bool
	{
		return $declared === null ? null : in_array($part, $declared->requiredParts, true);
	}

	/** @return Closure(Value): ?bool */
	private function appliedRequirement(string $part): Closure
	{
		return function (Value $address) use ($part): ?bool {
			$requirements = $this->rulesFor($address);

			return $requirements === null ? null : in_array($part, $requirements->requiredParts, true);
		};
	}

	// ── the checks ─────────────────────────────────────────────────────────────────────────

	private function isAnAllowedCountry(Value $address): ?bool
	{
		return $this->allowedCountries === [] ? null : in_array($address->countryCode, $this->allowedCountries, true);
	}

	/**
	 * Whether the part the submitted country asks for is there.
	 *
	 * Three answers, and the skip is the important one: a country that does not ask for a
	 * postcode should leave `postalCodeRequired` unanswered rather than passing it, so a message
	 * pack never has to explain a check that was never relevant.
	 *
	 * @return Closure(Value): ?bool
	 */
	private function requires(string $part): Closure
	{
		return function (Value $address) use ($part): ?bool {
			$requirements = $this->rulesFor($address);

			if ($requirements === null || !in_array($part, $requirements->requiredParts, true)) {
				return null;
			}

			$value = $address->partNamed($part);

			// A part that was sent and holds nothing never reaches here — assembly reports it —
			// so absent is the only way to fail, and `[]` is how an absent street spells it.
			return $value !== null && $value !== [];
		};
	}

	/**
	 * Whether the street names a place a person can attend.
	 *
	 * Every line is tested, not just the first: a PO box written on the second line is still a
	 * PO box, and reading only line one is how that used to pass.
	 */
	private function isVisitable(Value $address): ?bool
	{
		if (!$this->streetVisitable || $address->street === []) {
			return null;
		}

		foreach ($address->street as $line) {
			if (preg_match(self::PO_BOX_PATTERN, $line) === 1) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param list<string> $existing
	 * @param list<string> $additional
	 * @return list<string>
	 * @throws InvalidConfiguration
	 */
	private static function supported(array $existing, array $additional): array
	{
		foreach ($additional as $country) {
			// Trimmed here and not in codeFor(), because this is the *author's* string written
			// in their own source. A submitted country is untrusted input and is left as it came.
			$code = Value::codeFor(trim($country));

			if ($code === null) {
				throw InvalidConfiguration::regionIsNotSupported($country);
			}

			if (!in_array($code, $existing, true)) {
				$existing[] = $code;
			}
		}

		return $existing;
	}
}
