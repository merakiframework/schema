<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\ParsedValue;
use CommerceGuys\Addressing\Country\CountryRepository;

/**
 * One postal or street address, held whole.
 *
 * Six parts. The country is never null: an address without one describes no place, so a form that
 * has not reached the country yet is an {@see Input} that has not made a value, and is reported
 * part by part. Every other part is optional here, because *how much* of an address is required is
 * the field's business — see {@see Precision} — not this object's. Each part that is present is one
 * the country's own format has a place for, and reads.
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
 * and a database column uses. {@see Input} and {@see self::toArray()} are the seam.
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
final readonly class Value implements ParsedValue
{
	/**
	 * Part names as they appear in submitted data, mapped to the property holding them.
	 *
	 * Keyed by each {@see Part}'s value, which is the part list itself; {@see Input::parts()} and
	 * {@see self::toArray()} both read this rather than repeating it.
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
	 * Built by {@see Input} from what was submitted, or by {@see self::of()} by hand. The
	 * constructor still guards the one part no address is without, so a value made any other way
	 * cannot hold a country spelled two ways.
	 *
	 * @param string $countryCode the ISO 3166-1 alpha-2 code
	 * @param list<string> $street the lines below the locality, in the order they were written —
	 *        empty when there are none
	 * @param string|null $dependentLocality a neighbourhood or townland, where a country uses one
	 * @param string|null $locality the place the post routes to: a city, town, suburb or post town
	 * @param string|null $subdivision the full ISO 3166-2 code — `AU-QLD`, not `QLD` — where the
	 *        country publishes a list, and as written where it does not
	 * @throws MalformedValue if the country is not an ISO 3166-1 alpha-2 code
	 */
	public function __construct(
		public string $countryCode,
		public array $street = [],
		public ?string $dependentLocality = null,
		public ?string $locality = null,
		public ?string $subdivision = null,
		public ?string $postalCode = null,
	) {
		if (self::codeFor($countryCode) !== $countryCode) {
			throw MalformedValue::of(self::class, "\"{$countryCode}\" is not an ISO 3166-1 alpha-2 code");
		}
	}

	/**
	 * The readable way to write one by hand — a rule's bound, a test.
	 *
	 * Read the way a form's address is, through {@see Input}, so there is one place where what an
	 * address *is* gets decided: `of(country: 'Australia', subdivision: 'qld')` is `AU`, `AU-QLD`.
	 * A null part is left out rather than sent as null, which is the same thing the ports are asked
	 * to do.
	 *
	 * @param list<string>|null $street
	 * @throws MalformedValue if what it is given does not make an address — no country, or a part
	 *         the country cannot read
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

		$input = new Input((object) array_filter($parts, static fn(mixed $part): bool => $part !== null));

		return $input->value ?? throw MalformedValue::of(self::class, sprintf(
			'it does not make an address: %s',
			implode(', ', array_map(static fn(Field\Violation $violation): string => $violation->name, $input->violations)),
		));
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

	/**
	 * The snake_cased form, every part present even when absent, so a consumer can rely on the
	 * shape rather than testing for keys.
	 *
	 * An absent street is `null` here rather than `[]`, so that what this emits is something
	 * {@see Input} reads back: a *submitted* empty list is wrong, and a value that could not be
	 * read back from its own serialisation would break every persist-and-reload path.
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
}
