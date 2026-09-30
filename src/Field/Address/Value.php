<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\HasParts;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\ParsedValue;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;

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
	 * @param object $address with any of the keys in {@see self::PARTS}
	 * @throws MalformedValue if a part was sent empty, if it names no usable country, or if a
	 *         required subdivision cannot be resolved
	 */
	public function __construct(object $address)
	{
		$parts = get_object_vars($address);
		$unknown = array_diff(array_keys($parts), array_keys(self::PARTS));

		// Named rather than ignored, because the parts were renamed: a port still sending
		// `line1` or `administrative_area` would otherwise build an address missing the part it
		// thought it had supplied, and be told "street is required" — which names the symptom
		// and hides the stale key that caused it.
		if ($unknown !== []) {
			throw MalformedValue::of(self::class, sprintf(
				'"%s" is not a part of an address. The parts are: "%s"',
				implode('", "', $unknown),
				implode('", "', array_keys(self::PARTS)),
			));
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

		$known = self::subdivisions()->getList([$countryCode]);

		// A country with none on file constrains nothing, whatever its format says it uses.
		// Eight countries are in that position, and guessing at them would be worse.
		if ($known === []) {
			return $subdivision;
		}

		$code = self::subdivisionCodeFor($countryCode, $subdivision, $known);

		if ($code !== null) {
			return "{$countryCode}-{$code}";
		}

		if (self::requiresSubdivision($countryCode)) {
			throw MalformedValue::of(self::class, sprintf('"%s" is not a subdivision of %s', $subdivision, $countryCode));
		}

		return $subdivision;
	}

	/**
	 * The bare ISO 3166-2 code for a subdivision written as a code, a full code or a name.
	 *
	 * Generous in the same way the country is, and safely so: across every country with
	 * subdivisions on file no two share a name, and no name collides with another's code.
	 *
	 * @param array<string|int, string> $known code => name. The key is an `int` wherever a
	 *        country codes its subdivisions numerically — Japan's prefectures are `01` to `47`
	 *        — because PHP converts a numeric string on its way into an array.
	 */
	private static function subdivisionCodeFor(string $countryCode, string $subdivision, array $known): ?string
	{
		$candidate = trim($subdivision);
		$prefix = $countryCode . '-';

		// Only *this* country's prefix comes off: `US-CA` on an Australian address names
		// nothing, and should not quietly become `AU-CA`. Case-insensitively, because five
		// countries — CV, HK, KY, RU and TV — code their subdivisions by name, so the ISO
		// 3166-2 form this library publishes for them is `HK-Kowloon` rather than `HK-KLN`.
		if (mb_strtolower(mb_substr($candidate, 0, mb_strlen($prefix))) === mb_strtolower($prefix)) {
			$candidate = mb_substr($candidate, mb_strlen($prefix));
		}

		$folded = mb_strtolower($candidate);

		// One pass over both spellings rather than an exact-key lookup and then a name scan.
		// The lookup could not match a key that is a name with its own capitalisation, which
		// meant refusing the very code `requirementsFor()` had handed a port to render.
		foreach ($known as $code => $name) {
			$code = (string) $code;

			if (mb_strtolower($code) === $folded || mb_strtolower($name) === $folded) {
				return $code;
			}
		}

		return null;
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

	/** @see self::formats() */
	private static function subdivisions(): SubdivisionRepository
	{
		static $repository = null;

		return $repository ??= new SubdivisionRepository();
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
}
