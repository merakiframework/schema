<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Exception\BrokenInputContract;
use Meraki\Schema\Field;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\Violation;

/**
 * An address as it was submitted, whether or not it is one yet: each part as read, and what stops
 * them making a {@see Value}.
 *
 * Twelve things decide whether this is an address at all — whether it names a country, and
 * whether each part is one *that country's* published format has a place for and can read:
 *
 * | Code | The part | Wrong when |
 * | --- | --- | --- |
 * | `countryRequired` | country | it was not sent, or sent as `null` |
 * | `knownCountry` | country | it was sent and names no country ISO 3166-1 knows |
 * | `streetFormat` | street | it was sent and is not a list of lines holding text |
 * | `streetLineLimit` | street | it runs to more lines than the country's format has |
 * | `dependentLocalityFormat` | dependent locality | it was sent and holds no text |
 * | `dependentLocalityUsed` | dependent locality | the country's format has no place for one |
 * | `localityFormat` | locality | it was sent and holds no text |
 * | `localityUsed` | locality | the country's format has no place for one |
 * | `knownSubdivision` | subdivision | it holds no text, or is not one of the country's |
 * | `subdivisionUsed` | subdivision | the country's format has no place for one |
 * | `postalCodeFormat` | postal code | it holds no text, or does not match the country's pattern |
 * | `postalCodeUsed` | postal code | the country has no postcodes |
 *
 * None of them reads the field's configuration: a country's format is reference data, the same for
 * every field there will ever be. That is what makes them assembly rather than constraints. How
 * much of an address a field *demands* — `streetRequired` and the rest, through its precision
 * floor — and which countries it takes are the field's to say once there is an address.
 *
 * ### The country first
 *
 * Every check after the country's own is read from that country's format, so without a country
 * none of them can be asked, and each part is kept as written. One mistake, one message: the
 * country is what is in the way. The exceptions are a part that is not text at all, which is wrong
 * in any country, and the street's line limit, which every country sets at three.
 *
 * ### Canonicalised where a standard says two spellings are one place
 *
 * The country is stored as its ISO 3166-1 alpha-2 code whichever spelling arrived — `AU` for
 * `Australia` or `AUS` — and the subdivision as its full ISO 3166-2 code — `AU-QLD` for `QLD`,
 * `qld` or `Queensland` — once there is a country to resolve it against. Nothing else is trimmed,
 * collapsed or repaired: there is no standard normal form for an address line, so any rule would
 * be a presentation decision, which is the port's to make.
 */
final readonly class Input implements Field\Input
{
	/**
	 * The street lines in the order they were written; empty when none were sent, or when what was
	 * sent was not a list of lines.
	 *
	 * @var list<string>
	 */
	public array $street;

	/** A neighbourhood or townland, where a country uses one. */
	public ?string $dependentLocality;

	/** The place the post routes to: a city, town, suburb or post town. */
	public ?string $locality;

	/** The full ISO 3166-2 code once it resolved against the country; as written until it could. */
	public ?string $subdivision;

	public ?string $postalCode;

	/** The ISO 3166-1 alpha-2 code, whichever spelling was submitted; `null` without a country. */
	public ?string $countryCode;

	/** An address, once it names a country and every part sent is one that country can read. */
	public ?Value $value;

	/** @var list<Violation> */
	public array $violations;

	/** @var list<Part> */
	public array $missingParts;

	/**
	 * Takes the record a field takes, so there is one answer to "what is an address here".
	 *
	 * @param object{street?: list<string>, dependent_locality?: string, locality?: string, subdivision?: string, postal_code?: string, country?: string} $address
	 * @throws BrokenInputContract if it carries a key an address does not have
	 * @throws MalformedValue if it has no parts at all
	 */
	public function __construct(object $address)
	{
		$parts = get_object_vars($address);
		$names = array_keys(Value::PARTS);
		$unknown = array_diff(array_keys($parts), $names);

		// Raised, not reported, because the parts were renamed: a port still sending `line1` or
		// `administrative_area` is a port that needs changing, and being told "street is
		// required" names the symptom while hiding the stale key that caused it.
		if ($unknown !== []) {
			throw BrokenInputContract::recordHasKeysItDoesNotAccept(Value::class, array_values($unknown), $names);
		}

		// A record with no parts at all is not a half-filled address; it is not one.
		if (array_filter($parts, static fn(mixed $part): bool => $part !== null) === []) {
			throw MalformedValue::of(Value::class, 'it has no parts at all');
		}

		$violations = [];
		$missing = [];
		$countryCode = null;

		if (($parts['country'] ?? null) === null) {
			$violations[] = new Violation(Check::CountryRequired, true);
			$missing[] = Part::Country;
		} else {
			$countryCode = is_string($parts['country']) ? Value::codeFor($parts['country']) : null;
			$violations = $countryCode === null ? [...$violations, new Violation(Check::KnownCountry)] : $violations;
		}

		// The country's format, which every check below reads. The floor only filters which parts
		// are *required*, and assembly asks nothing about that, so any floor gives the same answer.
		$format = $countryCode === null ? null : Requirements::forCountry($countryCode, Precision::Street);

		[$street, $streetProblems] = self::streetIn($parts['street'] ?? null, $format);
		[$dependentLocality, $dependentLocalityProblem] = self::placeIn($parts['dependent_locality'] ?? null, 'dependent_locality', Check::DependentLocalityFormat, Check::DependentLocalityUsed, $format);
		[$locality, $localityProblem] = self::placeIn($parts['locality'] ?? null, 'locality', Check::LocalityFormat, Check::LocalityUsed, $format);
		[$subdivision, $subdivisionProblem] = self::subdivisionIn($parts['subdivision'] ?? null, $format);
		[$postalCode, $postalCodeProblem] = self::postalCodeIn($parts['postal_code'] ?? null, $subdivision, $format);

		$violations = [
			...$violations,
			...$streetProblems,
			...array_values(array_filter([$dependentLocalityProblem, $localityProblem, $subdivisionProblem, $postalCodeProblem])),
		];

		$this->street = $street;
		$this->dependentLocality = $dependentLocality;
		$this->locality = $locality;
		$this->subdivision = $subdivision;
		$this->postalCode = $postalCode;
		$this->countryCode = $countryCode;
		$this->violations = $violations;
		$this->missingParts = $missing;
		$this->value = ($violations === [] && $countryCode !== null)
			? new Value($countryCode, $street, $dependentLocality, $locality, $subdivision, $postalCode)
			: null;
	}

	/**
	 * The input a whole address would have been read from, so a field handed its own value reads it
	 * the way it reads anything else.
	 */
	public static function of(Value $address): self
	{
		return new self((object) $address->toArray());
	}

	/**
	 * The street lines, and what is wrong with them.
	 *
	 * A list because nothing here joins lines into delimited text — the separator would have to be
	 * CRLF or LF, and HTML and JSON disagree. A submitted empty list is wrong rather than absent: a
	 * port with no street leaves the part out.
	 *
	 * Lines that were read are kept even when there are too many of them, so a rule about the
	 * street still sees what was written.
	 *
	 * @return array{list<string>, list<Violation>}
	 */
	private static function streetIn(mixed $given, ?Requirements $format): array
	{
		if ($given === null) {
			return [[], []];
		}

		if (!is_array($given) || !array_is_list($given) || $given === []
			|| array_filter($given, static fn(mixed $line): bool => !is_string($line) || trim($line) === '') !== []
		) {
			return [[], [new Violation(Check::StreetFormat)]];
		}

		/** @var list<string> $given */
		// Every country uses exactly three lines today, which is why the generic limit stands in
		// until there is a country to ask.
		$limit = $format === null ? Requirements::genericStreetLineLimit() : $format->streetLineLimit;

		return [$given, count($given) > $limit ? [new Violation(Check::StreetLineLimit, $limit)] : []];
	}

	/**
	 * A locality or a dependent locality: text, in a country whose format has a place for it.
	 *
	 * `''` and `'   '` are not "no locality" — they were provided, so the part is not missing; it
	 * simply holds nothing. A port with no value for a part omits it. Text is kept as written,
	 * whatever the country says about it.
	 *
	 * @return array{?string, ?Violation}
	 */
	private static function placeIn(mixed $given, string $part, Check $format, Check $used, ?Requirements $rules): array
	{
		if ($given === null) {
			return [null, null];
		}

		if (!is_string($given) || trim($given) === '') {
			return [null, new Violation($format)];
		}

		return [$given, self::usedIn($part, $used, $rules)];
	}

	/**
	 * The subdivision as ISO 3166-2 writes it once it resolves, and as written until then — while
	 * there is no country to resolve it against, where the country has no list on file (eight use
	 * one without publishing it, and guessing would be worse than saying nothing), and when it is
	 * not one of the country's.
	 *
	 * @return array{?string, ?Violation}
	 */
	private static function subdivisionIn(mixed $given, ?Requirements $rules): array
	{
		if ($given === null) {
			return [null, null];
		}

		if (!is_string($given) || trim($given) === '') {
			return [null, new Violation(Check::KnownSubdivision)];
		}

		$unused = self::usedIn('subdivision', Check::SubdivisionUsed, $rules);

		if ($rules === null || $unused !== null || $rules->subdivisions === []) {
			return [$given, $unused];
		}

		$code = $rules->subdivisionCodeFor($given);

		return $code === null ? [$given, new Violation(Check::KnownSubdivision)] : [$code, null];
	}

	/**
	 * The postcode as written, matched against the pattern the country — or its subdivision —
	 * publishes.
	 *
	 * Shape rather than existence: whether a postcode is assigned is a licensed service's question.
	 *
	 * @return array{?string, ?Violation}
	 */
	private static function postalCodeIn(mixed $given, ?string $subdivision, ?Requirements $rules): array
	{
		if ($given === null) {
			return [null, null];
		}

		if (!is_string($given) || trim($given) === '') {
			return [null, new Violation(Check::PostalCodeFormat)];
		}

		$unused = self::usedIn('postal_code', Check::PostalCodeUsed, $rules);
		$pattern = ($rules === null || $unused !== null) ? null : $rules->postalCodeFormatFor($subdivision);

		if ($pattern === null || preg_match('~^(?:' . $pattern . ')$~', $given) === 1) {
			return [$given, $unused];
		}

		return [$given, new Violation(Check::PostalCodeFormat, $pattern)];
	}

	/**
	 * Whether the country's format has a place for a part that was sent — a state typed for a
	 * country with no states is a mistake worth reporting rather than data to quietly ignore.
	 * Nothing to say without a country.
	 */
	private static function usedIn(string $part, Check $used, ?Requirements $rules): ?Violation
	{
		return ($rules === null || in_array($part, $rules->usedParts, true)) ? null : new Violation($used, false);
	}

	/**
	 * The parts as they are held, for a scope to resolve against.
	 *
	 * An absent street is `[]`, because that is what the property holds and a rule asking
	 * `isEmpty()` of that part should see the real value.
	 *
	 * @return array<string, mixed>
	 */
	public function parts(): array
	{
		$parts = [];

		foreach (Value::PARTS as $key => $property) {
			$parts[$key] = $this->{$property};
		}

		return $parts;
	}

	/**
	 * The two parts this canonicalises, resolved the way a submitted address would be.
	 *
	 * `country` and `subdivision` are stored as codes whichever spelling arrived. A rule compares
	 * against what was *stored*, so an expectation written in a spelling the field happily accepts
	 * as input was false for every request there would ever be: accepted at authoring, silently
	 * dead, and indistinguishable from a condition that simply never held.
	 *
	 * Anything unresolvable is returned unchanged, so it still fails to match — reporting *why*
	 * belongs to `knownSubdivision` and `allowedCountries`, which say it better.
	 */
	public function canonicalPartValue(Field\Part $part, mixed $expected): mixed
	{
		if (!is_string($expected)) {
			return $expected;
		}

		return match ($part) {
			Part::Country => Value::codeFor($expected) ?? $expected,
			// A subdivision only resolves against a country, and a form may be submitted before
			// one is chosen. The stored side is kept as submitted in that case, so the
			// expectation is too, and both are compared as written.
			Part::Subdivision => $this->countryCode === null
				? $expected
				: (Requirements::subdivisionCodeIn($this->countryCode, $expected) ?? $expected),
			default => $expected,
		};
	}
}
