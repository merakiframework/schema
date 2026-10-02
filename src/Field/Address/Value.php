<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Exception\BrokenInputContract;
use Meraki\Schema\Field\HasParts;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\ParsedValue;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;

/**
 * One postal or street address, held whole.
 *
 * Six parts, and every one but the country is optional here: this object holds whatever was
 * submitted, including a half-filled form on its way to being reported as invalid. *How much*
 * of it is required is the field's business — see {@see Precision} — not this object's.
 *
 * The names come from three places, and each earns its keep:
 *
 *  - `street` is one part holding a list of lines, which is what WHATWG's `street-address`
 *    autofill token describes: "a street address … can be multiple lines of text … should not
 *    include the city name, ZIP or postal code, or country name". A list rather than delimited
 *    text because nothing here normalises, so a delimited string would carry a separator whose
 *    spelling nobody agrees on — HTML submits CRLF, JSON sends LF, and {@see self::equals()} is
 *    exact.
 *  - `subdivision` is ISO 3166-2's word, and the codes it holds are ISO 3166-2's codes.
 *  - the rest follow Google's libaddressinput, because that is where the per-country rules come
 *    from.
 *
 * The addressee is not here. `organization`, `given_name` and `family_name` identify who is at
 * a place rather than the place, and an address is the place.
 *
 * Properties are camelCase and array keys are snake_case, which is the convention a form sends
 * and a database column uses. The constructor and {@see self::toArray()} are the seam.
 *
 * Nothing is trimmed, collapsed or repaired. Unlike E.164 or the WHATWG email grammar there is
 * no standard normal form for an address line to normalise towards, so any rule would be a
 * presentation decision — the port's to make. The one thing canonicalised is a value drawn from
 * a registered list: the country and the subdivision, which have codes precisely so that two
 * spellings of one place become one.
 *
 * There is deliberately no `__toString()`, because the order and punctuation an address takes is
 * per-country (libaddressinput's business) and rendering is the UI's. {@see self::toArray()} is
 * how you get at the parts.
 */
final readonly class Value implements ParsedValue, HasParts
{
	/**
	 * Part names as they appear in submitted data, mapped to the property holding them.
	 *
	 * The one source of truth: {@see self::partNames()}, {@see self::parts()},
	 * {@see self::toArray()} and {@see self::isEmpty()} all read it rather than repeating it.
	 *
	 * @var array<string, string>
	 */
	public const PARTS = [
		'street' => 'street',
		'dependent_locality' => 'dependentLocality',
		'locality' => 'locality',
		'subdivision' => 'subdivision',
		'postal_code' => 'postalCode',
		// `country` rather than `country_code`, because a code or a name may be submitted and
		// the constructor canonicalises it to the code this property holds.
		'country' => 'countryCode',
	];

	/**
	 * The lines below the locality, in the order they were written.
	 *
	 * Empty means the part was not submitted. A submitted empty list is refused, so a value that
	 * exists and holds `[]` can only mean "absent" — which is what lets a requiredness check ask
	 * one question instead of two.
	 *
	 * @var list<string>
	 */
	public array $street;

	/** A neighbourhood or townland, where a country uses one. */
	public ?string $dependentLocality;

	/** The place the post routes to: a city, town, suburb or post town. */
	public ?string $locality;

	/** The full ISO 3166-2 code — `AU-QLD`, not `QLD` — once canonicalised. */
	public ?string $subdivision;

	public ?string $postalCode;

	/** The ISO 3166-1 alpha-2 code, whichever spelling was submitted. */
	public ?string $countryCode;

	/**
	 * Takes the record a field takes, so there is one answer to "what is an address here".
	 *
	 * Three things it refuses, and all three are about whether this is an address at all rather
	 * than whether it is an acceptable one. A constraint judges an address that could be read;
	 * these are the cases where there is nothing left to judge.
	 *
	 * - **A part that was sent and holds nothing.** `''` and `'   '` are not "no locality" —
	 *   they were provided, so the part is not missing; it simply cannot be read. Answering
	 *   "absent" would let whitespace satisfy a requiredness check, and answering "present"
	 *   would let it satisfy one too. A port with no value for a part omits it.
	 * - **A country that names no country.** A postcode means nothing without one — `4700` is
	 *   Rockhampton in Australia and something else elsewhere — and the postcode pattern, the
	 *   subdivision list and the required set are all selected *by* the country. Keeping an
	 *   unrecognised one and letting every check skip is how an alpha-3 code used to pass
	 *   entirely unvalidated.
	 * - **A subdivision the country requires and does not have.** ISO 3166-2 is a closed list,
	 *   so a value outside it is not a subdivision of that country. It is also the part the
	 *   rest of the address leans on: 36 subdivisions across CN and CO carry their own
	 *   postcode pattern, overriding the country's, and both countries require a
	 *   subdivision. (Those patterns are not read yet — the postcode is still judged
	 *   against the country's — so today this is the rule the data warrants rather than
	 *   one the code has come to depend on.) Where a
	 *   country uses a subdivision without requiring one, an unrecognised value is kept for
	 *   `knownSubdivision` to report, because nothing downstream depends on it there.
	 *
	 * @param object{street?: list<string>, dependent_locality?: string, locality?: string, subdivision?: string, postal_code?: string, country?: string} $address
	 * @throws BrokenInputContract if it carries a key an address does not have
	 * @throws MalformedValue if a part was sent empty, if it names no usable country, or if a
	 *         required subdivision cannot be resolved
	 */
	public function __construct(object $address)
	{
		$parts = get_object_vars($address);
		$unknown = array_diff(array_keys($parts), array_keys(self::PARTS));

		// Raised, not reported, because the parts were renamed: a port still sending `line1` or
		// `administrative_area` is a port that needs changing, and being told "street is
		// required" names the symptom while hiding the stale key that caused it. This used to
		// be absorbed as a shape failure, which said the submitter had got something wrong.
		if ($unknown !== []) {
			throw BrokenInputContract::recordHasKeysItDoesNotAccept(self::class, array_values($unknown), array_keys(self::PARTS));
		}

		// Total about the *type* of the optional parts, as it always was: a non-string is read
		// as absent rather than refused. `street` is the exception, because a list is the whole
		// point of it — silently reading a delimited string as "no street" would lose an
		// address the submitter did give.
		$read = static function (string $key) use ($parts): ?string {
			$value = $parts[$key] ?? null;

			if (!is_string($value)) {
				return null;
			}

			if (trim($value) === '') {
				throw MalformedValue::of(self::class, "its {$key} was given but holds nothing");
			}

			return $value;
		};

		$street = self::readStreet($parts);
		$dependentLocality = $read('dependent_locality');
		$locality = $read('locality');
		$postalCode = $read('postal_code');
		$subdivision = $read('subdivision');
		$country = $read('country');

		if ($street === [] && $dependentLocality === null && $locality === null
			&& $postalCode === null && $subdivision === null && $country === null) {
			throw MalformedValue::of(self::class, 'it has no parts at all');
		}

		if ($country === null) {
			throw MalformedValue::of(self::class, 'it names no country, and an address without one describes no place');
		}

		$countryCode = self::codeFor($country);

		if ($countryCode === null) {
			throw MalformedValue::of(self::class, sprintf('"%s" is not a country ISO 3166-1 knows', $country));
		}

		$this->street = $street;
		$this->dependentLocality = $dependentLocality;
		$this->locality = $locality;
		$this->postalCode = $postalCode;
		$this->countryCode = $countryCode;
		$this->subdivision = self::resolveSubdivision($countryCode, $subdivision);
	}

	/**
	 * The street lines, or `[]` when the part was not submitted.
	 *
	 * @param array<string, mixed> $parts
	 * @return list<string>
	 * @throws MalformedValue if it is not a list of lines, or holds a line with nothing in it
	 */
	private static function readStreet(array $parts): array
	{
		$street = $parts['street'] ?? null;

		if ($street === null) {
			return [];
		}

		if (!is_array($street) || !array_is_list($street)) {
			throw MalformedValue::of(self::class, 'its street is not a list of lines');
		}

		if ($street === []) {
			throw MalformedValue::of(self::class, 'its street was given as an empty list; leave the part out instead');
		}

		foreach ($street as $line) {
			if (!is_string($line)) {
				throw MalformedValue::of(self::class, 'its street holds a line that is not text');
			}

			if (trim($line) === '') {
				throw MalformedValue::of(self::class, 'its street holds a line with nothing in it');
			}
		}

		return $street;
	}

	/**
	 * The subdivision as ISO 3166-2 writes it, or the value verbatim where it cannot be checked.
	 *
	 * @throws MalformedValue if the country requires a subdivision and this is not one of its
	 */
	private static function resolveSubdivision(string $countryCode, ?string $subdivision): ?string
	{
		if ($subdivision === null) {
			return null;
		}

		// A country with none on file constrains nothing, whatever its format says it uses.
		// Eight countries are in that position, and guessing at them would be worse.
		if (Requirements::subdivisionsIn($countryCode) === []) {
			return $subdivision;
		}

		// One resolver, shared with Requirements: two answers to "which subdivision is this"
		// would eventually disagree, and the port reads its options from the same place.
		$code = Requirements::subdivisionCodeIn($countryCode, $subdivision);

		if ($code !== null) {
			return $code;
		}

		if (self::requiresSubdivision($countryCode)) {
			throw MalformedValue::of(self::class, sprintf('"%s" is not a subdivision of %s', $subdivision, $countryCode));
		}

		return $subdivision;
	}

	/**
	 * Whether a country's own format says an address there needs a subdivision.
	 *
	 * The country's requirement, not the field's: whether a subdivision carries its own postcode
	 * pattern is a fact about the country, and does not change because an author asked for less
	 * depth.
	 */
	private static function requiresSubdivision(string $countryCode): bool
	{
		return in_array('administrativeArea', self::formats()->get($countryCode)->getRequiredFields(), true);
	}

	/**
	 * The readable way to write one by hand — a rule's bound, a test.
	 *
	 * A convenience over the constructor rather than a second way in: it builds the record a
	 * form would submit and hands it over, so the invariant is enforced in one place. A null
	 * part is left out rather than sent as null, which is the same thing the ports are asked to
	 * do.
	 *
	 * @param list<string>|null $street
	 * @throws MalformedValue on the same three refusals the constructor makes
	 */
	public static function of(
		?array $street = null,
		?string $locality = null,
		?string $subdivision = null,
		?string $postalCode = null,
		?string $country = null,
		?string $dependentLocality = null,
	): self {
		$parts = [
			'street' => $street,
			'dependent_locality' => $dependentLocality,
			'locality' => $locality,
			'subdivision' => $subdivision,
			'postal_code' => $postalCode,
			'country' => $country,
		];

		return new self((object) array_filter($parts, static fn(mixed $part): bool => $part !== null));
	}

	/**
	 * Exact on every part, because both sides are already canonical: the country is a code by
	 * the time it gets here, and so is the subdivision, whichever spelling was submitted.
	 *
	 * Street lines compare in order, because order is the only thing distinguishing
	 * "Level 3, 7 Cunningham St" from an address that is not that.
	 *
	 * What it does *not* do is decide that two differently-written street lines are the same
	 * place. That is an address-normalisation problem, it is locale-specific and genuinely hard,
	 * and guessing at it would silently merge two distinct addresses.
	 */
	public function equals(Equality $other): bool
	{
		return $other instanceof self
			&& $this->street === $other->street
			&& $this->dependentLocality === $other->dependentLocality
			&& $this->locality === $other->locality
			&& $this->subdivision === $other->subdivision
			&& $this->postalCode === $other->postalCode
			&& $this->countryCode === $other->countryCode;
	}

	/**
	 * The ISO 3166-1 code for a country written as an alpha-2 code, an alpha-3 code or a name.
	 *
	 * Public because the *field* needs the same answer when it checks an author's allow-list and
	 * when it answers `requirementsFor()`, and two implementations of "is this a country" would
	 * eventually disagree. That shared answer is the guarantee that a country a port may ask
	 * about is exactly a country this value will accept.
	 *
	 * Numeric-3 is deliberately absent. It is rare in addresses, and its leading zeros do not
	 * survive a JSON producer that sends `036` as a number.
	 */
	public static function codeFor(string $country): ?string
	{
		$candidate = strtoupper(trim($country));

		if ($candidate === '') {
			return null;
		}

		return self::countryIndex()[$candidate] ?? null;
	}

	/**
	 * Every spelling of every country, upper-cased, mapped to its alpha-2 code.
	 *
	 * Built once. Measured across the 256 territories CLDR knows: 254 carry an alpha-3 (`IC` and
	 * `EA` are exceptional reservations that have none) and no two share one, so the map is
	 * unambiguous.
	 *
	 * @return array<string, string>
	 */
	private static function countryIndex(): array
	{
		static $index = null;

		if ($index !== null) {
			return $index;
		}

		$index = [];

		foreach ((new CountryRepository())->getAll() as $code => $country) {
			$index[strtoupper($code)] = $code;

			$threeLetter = $country->getThreeLetterCode();

			if ($threeLetter !== null && $threeLetter !== '') {
				$index[strtoupper($threeLetter)] = $code;
			}

			$index[mb_strtoupper($country->getName())] = $code;
		}

		return $index;
	}

	/** Memoised: building the format list is not free and it never changes within a request. */
	private static function formats(): AddressFormatRepository
	{
		static $repository = null;

		return $repository ??= new AddressFormatRepository();
	}

	/**
	 * The snake_cased form, every part present even when absent, so a consumer can rely on the
	 * shape rather than testing for keys.
	 *
	 * An absent street is `null` here rather than `[]`, so that what this emits is something
	 * the constructor accepts: a *submitted* empty list is refused, and a value that could not
	 * be read back from its own serialisation would break every persist-and-reload path.
	 * {@see self::parts()} keeps the raw list, because a scope asking whether a street is empty
	 * wants the property, not its wire form.
	 *
	 * @return array<string, string|list<string>|null>
	 */
	public function toArray(): array
	{
		$parts = [];

		foreach (self::PARTS as $key => $property) {
			$part = $this->{$property};
			$parts[$key] = $part === [] ? null : $part;
		}

		return $parts;
	}

	/**
	 * Whether any part was supplied at all. An address with nothing in it is not a vague
	 * address; it is an absent one.
	 */
	public function isEmpty(): bool
	{
		foreach (self::PARTS as $property) {
			$part = $this->{$property};

			if ($part !== null && $part !== []) {
				return false;
			}
		}

		return true;
	}

	/**
	 * A part by the name submitted data uses — `postal_code`, not `postalCode`.
	 *
	 * Which is what the constraints address parts by, so a failure can say *which* part it was
	 * about in the same vocabulary the author wrote.
	 *
	 * @return string|list<string>|null
	 */
	public function partNamed(string $key): string|array|null
	{
		$property = self::PARTS[$key] ?? null;

		return $property === null ? null : $this->{$property};
	}

	/**
	 * The six parts, named as they arrive. `country` rather than `countryCode`, because that is
	 * the key submitted input uses and the name the `allowedCountries` constraint reports under.
	 *
	 * @return list<string>
	 */
	public static function partNames(): array
	{
		return array_keys(self::PARTS);
	}

	/**
	 * The parts as they are held, for a scope to resolve against.
	 *
	 * Unlike {@see self::toArray()} an absent street stays `[]`, because that is what the
	 * property holds and a rule asking `isEmpty()` of it should see the real value.
	 *
	 * @return array<string, mixed>
	 */
	public function parts(): array
	{
		$parts = [];

		foreach (self::PARTS as $key => $property) {
			$parts[$key] = $this->{$property};
		}

		return $parts;
	}

	/**
	 * The two parts this canonicalises, resolved the way a submitted address would be.
	 *
	 * `country` and `subdivision` are stored as codes whichever spelling arrived — `AU` for
	 * `Australia`, `AU-QLD` for any of `QLD`, `qld`, `AU-QLD` or `Queensland`. A rule compares
	 * against what was *stored*, so an expectation written in a spelling the field happily
	 * accepts as input was false for every request there would ever be: accepted at authoring,
	 * silently dead, and indistinguishable from a condition that simply never held.
	 *
	 * Resolved here rather than by the comparison because a subdivision needs its country, and
	 * this value is the only thing that has one.
	 *
	 * Anything unresolvable is returned unchanged, so it still fails to match — reporting *why*
	 * belongs to `knownSubdivision` and `allowedCountries`, which say it better.
	 */
	public function canonicalPartValue(string $part, mixed $expected): mixed
	{
		if (!is_string($expected)) {
			return $expected;
		}

		// The country is always there to resolve a subdivision against: the constructor refuses
		// an address without one, for the reason it gives — a postcode, a subdivision list and a
		// required set are all selected *by* the country.
		return match ($part) {
			'country' => self::codeFor($expected) ?? $expected,
			'subdivision' => Requirements::subdivisionCodeIn($this->countryCode, $expected) ?? $expected,
			default => $expected,
		};
	}

	/**
	 * An address line is one of up to three, and nothing here joins them into delimited text —
	 * a separator would have to be either CRLF or LF, and HTML and JSON disagree.
	 */
	public static function listParts(): array
	{
		return ['street'];
	}
}
