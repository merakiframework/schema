<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Definition;
use Meraki\Schema\Field;
use Meraki\Schema\FieldName;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `Address`, `Money` and `CreditCard` are one field holding one value object, the way `File`
 * already held a `File\Value`. No sub-fields, so nothing is registered in the schema's namespace
 * and no name is ever built by joining strings.
 */
#[CoversNothing]
#[Group('api-2.0')]
final class StructuredTypeTest extends TestCase
{
	/**
	 * A record arrives as an object. An array is a list, and a structured field refuses one.
	 *
	 * @return array<string, string>
	 */
	private static function auAddress(array $overrides = []): object
	{
		return (object) ($overrides + [
			'street' => ['1 Denham St'],
			'locality' => 'Rockhampton',
			'subdivision' => 'QLD',
			'postal_code' => '4700',
			'country' => 'AU',
		]);
	}

	/**
	 * The same address with no street — an area rather than a place. It still names a country,
	 * because that is never optional; what makes this an area is the missing street.
	 */
	private static function area(): object
	{
		return (object) [
			'locality' => 'Rockhampton',
			'subdivision' => 'QLD',
			'postal_code' => '4700',
			'country' => 'AU',
		];
	}

	#[Test]
	public function a_structured_field_accepts_a_record(): void
	{
		$address = new Field\Address(new FieldName('billing'), ['AU']);

		$resolved = $address->resolve(self::auAddress());

		$this->assertInstanceOf(Field\Address\Value::class, $resolved->value);
		$this->assertSame(['1 Denham St'], $resolved->value->street);
	}

	/**
	 * An array is refused where a record belongs, which is what lets a collection key its rows.
	 */
	#[Test]
	public function a_structured_field_refuses_a_list(): void
	{
		$address = new Field\Address(new FieldName('billing'), ['AU']);

		$this->assertTrue($address->validate(['street' => ['1 Denham St']])->shape->wasUnreadable());
	}

	#[Test]
	public function a_structured_field_accepts_its_own_value_object(): void
	{
		$address = new Field\Address(new FieldName('billing'), ['AU']);
		$value = Field\Address\Value::of(
			street: ['1 Denham St'],
			locality: 'Rockhampton',
			subdivision: 'QLD',
			postalCode: '4700',
			country: 'AU',
		);

		$this->assertEquals($value, $address->resolve($value)->value);
	}

	#[Test]
	public function a_structured_field_has_no_sub_fields(): void
	{
		$address = new Field\Address(new FieldName('billing'), ['AU']);

		$this->assertFalse(property_exists($address, 'fields'));
		$this->assertFalse(method_exists($address, 'getFields'));
	}

	#[Test]
	public function a_constraint_name_does_not_carry_the_field_name(): void
	{
		// The important half. A name used to embed the field it came from, so renaming
		// `billing` to `invoice_address` changed every constraint it emitted and every
		// message provider matching on them.
		$schema = new Definition('checkout');
		$schema->add($schema->createAddressField('invoice_address', ['AU'])->mustBeVisitable());

		$failed = $schema->validate((object) [
			'invoice_address' => self::auAddress(['street' => ['PO Box 42']]),
		])->forField('invoice_address');

		$this->assertNotNull($failed->forConstraint('streetVisitable'));
		$this->assertStringNotContainsString('invoice_address', implode(',', $failed->constraintNames));
	}

	#[Test]
	public function a_money_field_carries_currency_and_amount_in_one_value(): void
	{
		$money = new Field\Money(new FieldName('cost'), ['AUD' => 2]);

		$resolved = $money->resolve((object) ['currency' => 'AUD', 'amount' => '12.50']);

		$this->assertInstanceOf(Field\Money\Value::class, $resolved->value);
		$this->assertSame('AUD', $resolved->value->currency);
	}

	#[Test]
	public function a_file_field_holds_exactly_one_file(): void
	{
		// Several files is a collection of file fields, not a field that is itself plural.
		$file = new Field\File(new FieldName('resume'));

		// A name, a claimed type and a reported size. Not a $_FILES entry: `tmp_name` and
		// `error` are one language's web SAPI describing how it received an upload, and a port
		// takes the three it needs out of that rather than handing the whole thing over.
		$resolved = $file->resolve((object) [
			'name' => 'cv.pdf',
			'type' => 'application/pdf',
			'size' => 1024,
		]);

		$this->assertInstanceOf(Field\File\Value::class, $resolved->value);
		$this->assertFalse(method_exists($file, 'minCountOf'));
	}

	#[Test]
	public function several_values_is_a_collection(): void
	{
		// The template is handed over as fields, not built by a callback. A callback was needed
		// while a collection prefixed its template's names and so had to build them itself; it
		// owns its whole value now, exactly as Address and Money do.
		$schema = new Definition('application');
		$schema->add(
			$schema->createCollectionField('attachments', $schema->createFileField('file'))
				->minCountOf(1),
		);

		$result = $schema->validate((object) ['attachments' => []]);

		$this->assertTrue($result->forField('attachments')->forConstraint('minCount')->failed());
	}

	#[Test]
	public function a_collection_item_may_have_several_fields(): void
	{
		// What Composite was kept for. Collection holds a template of several fields and
		// validates each item against all of them.
		$schema = new Definition('schedule');
		$schema->add(
			$schema->createCollectionField(
				'sessions',
				$schema->createDateTimeField('starts_at'),
				$schema->createDateTimeField('ends_at'),
			)->minCountOf(1),
		);

		$result = $schema->validate((object) [
			'sessions' => [
				'opening' => (object) ['starts_at' => '2026-01-01T09:00', 'ends_at' => '2026-01-01T10:00'],
			],
		]);

		$this->assertFalse($result->anyFailed());
	}

	/**
	 * An address requires a street *by default*, and giving that up is the explicit call.
	 *
	 * This used to read the other way round — not specific by default, with `mustBeSpecific()` to
	 * tighten it. The baseline rule settled it: a field with no configuration is already correct
	 * and configuration narrows from there, so the default is the deepest rung of the ladder and
	 * `minPrecisionOf()` is what widens it.
	 */
	#[Test]
	public function an_address_requires_a_street_by_default(): void
	{
		$address = new Field\Address(new FieldName('billing'), ['AU']);

		$failed = $address->validate(self::area())->forConstraint('streetRequired');

		$this->assertTrue($failed->failed());
		$this->assertSame(Field\Address\Part::Street, $failed->part);
	}

	#[Test]
	public function an_address_may_name_only_an_area(): void
	{
		// For a service area or a catchment, where the region *is* the answer rather than an
		// incomplete version of one.
		$address = (new Field\Address(new FieldName('service_area'), ['AU']))
			->minPrecisionOf(Field\Address\Precision::Locality);

		$this->assertFalse($address->validate(self::area())->anyFailed());
	}

	#[Test]
	public function deliverability_and_granularity_are_independent(): void
	{
		// Two dials, not one enum. What the address is *for* is separate from how much of it
		// is required: a PO box is a perfectly specific address you cannot visit.
		$visitable = (new Field\Address(new FieldName('pickup'), ['AU']))->mustBeVisitable();

		$failed = $visitable->validate(self::auAddress(['street' => ['PO Box 42']]));

		$this->assertTrue($failed->forConstraint('streetVisitable')->failed());
		$this->assertFalse($failed->forConstraint('streetRequired')->failed());
	}

	#[Test]
	public function no_combination_of_the_two_dials_contradicts_itself(): void
	{
		// There used to be a pair of tests here asserting that "mailable" and "no street
		// required" threw at definition time. The contradiction was an artefact of one enum
		// spanning two questions — and the claim it rested on was wrong anyway, since a PO box
		// names no street and is perfectly postal. With the dials separated there is nothing
		// left to refuse.
		foreach (Field\Address\Precision::cases() as $floor) {
			foreach ([true, false] as $visitable) {
				$address = (new Field\Address(new FieldName('billing'), ['AU']))->minPrecisionOf($floor);
				$address = $visitable ? $address->mustBeVisitable() : $address;

				$this->assertSame($floor, $address->precision);
				$this->assertSame($visitable, $address->streetVisitable);
			}
		}
	}

	#[Test]
	public function an_area_may_still_refuse_a_post_office_box(): void
	{
		// The fourth combination, which a three-case enum could not say: the street is not
		// required, but if one is given it must name somewhere you can go.
		$address = (new Field\Address(new FieldName('whereabouts'), ['AU']))
			->minPrecisionOf(Field\Address\Precision::Locality)
			->mustBeVisitable();

		$this->assertFalse($address->validate(self::area())->anyFailed());

		$withBox = $address->validate(self::auAddress(['street' => ['PO Box 42']]));

		$this->assertTrue($withBox->forConstraint('streetVisitable')->failed());
		$this->assertTrue($withBox->forConstraint('streetRequired')->skipped());
	}

	/**
	 * The country is always submitted, even when the allow-list holds exactly one.
	 *
	 * This test used to assert the opposite — that one allowed country settled it and the author's
	 * list answered for the user. That rule is gone, because it changed shape depending on how many
	 * countries were listed: an address was complete or incomplete according to a detail of the
	 * field's configuration rather than according to what somebody sent. A country is now the
	 * submitter's to give, the same pairing `Money` makes with a currency.
	 *
	 * @see \Meraki\Schema\Field\AddressTest::an_address_without_a_country_never_described_a_place
	 */
	#[Test]
	public function a_country_is_always_submitted(): void
	{
		$address = new Field\Address(new FieldName('billing'), ['AU']);

		$resolved = $address->validate((object) [
			'street' => ['1 Denham St'],
			'locality' => 'Rockhampton',
			'subdivision' => 'QLD',
			'postal_code' => '4700',
		]);

		// Reported, not refused. Leaving it out used to make the whole address unreadable, on
		// the reasoning that a country gives the rest its meaning — which is true, and is just
		// as true of the currency on `Money`, which has always named the part instead. The
		// case that settles it is a field allowing several countries, where the country is a
		// box somebody fills in rather than one the port supplies.
		$this->assertTrue($resolved->shape->passed());
		$this->assertTrue($resolved->forConstraint('countryRequired')->failed());
		$this->assertSame(Field\Address\Part::Country, $resolved->forConstraint('countryRequired')->part);

		// Everything read from a country's own format has nothing to read, so it skips rather
		// than guessing — one mistake, one message.
		foreach (['streetRequired', 'localityRequired', 'postalCodeFormat', 'knownSubdivision'] as $name) {
			$this->assertTrue($resolved->forConstraint($name)->skipped(), $name);
		}

		// A country that *was* given and is not one stays unreadable: that is an answer
		// nothing can use, not a box left empty.
		$this->assertTrue($address->validate((object) ['locality' => 'X', 'country' => 'Zorbia'])->shape->wasUnreadable());

		// And with one, it is stored as the code whatever spelling arrived.
		$this->assertSame('AU', $address->validate(self::auAddress(['country' => 'Australia']))->value->countryCode);
	}
	/**
	 * Every part a value reports is a key it is **submitted with**.
	 *
	 * `PhoneNumber` reported `country, e164` against an input of `{number, country}`, so
	 * `#/fields/phone/value/e164` resolved against something nobody had sent while
	 * `forPart('number')` raised on the one part a form definitely renders. E.164 is a *reading*
	 * of the pair and stays one — {@see Field\PhoneNumber\Value::toE164()}.
	 *
	 * Every record value declares exactly the keys it accepts, so this reads as an equality in
	 * both directions — and `File` is what used to make it an inequality, back when it tolerated
	 * the `tmp_name` and `error` that one language's web SAPI puts on an upload.
	 */
	#[Test]
	#[DataProvider('recordPayloads')]
	public function every_part_a_value_reports_is_a_key_it_is_submitted_with(Field $field, object $payload): void
	{
		$declared = array_column($field->parts, 'value');
		$undeliverable = array_values(array_diff($declared, array_keys(get_object_vars($payload))));

		$this->assertNotSame([], $declared, $field::class . ' should report parts.');
		$this->assertSame([], $undeliverable, sprintf('%s declares parts nothing can submit.', $field::class));

		// And what a rule reads parts from agrees with the declaration, in the declared order —
		// which is the order violations are read in. That is the input for a field that reads its
		// parts before assembling a value, and the value for one that still reads it in one step.
		$holder = $field->resolvedInputFor($payload) ?? $field->resolve($payload)->value;

		$this->assertInstanceOf(Field\HasParts::class, $holder);
		$this->assertSame($declared, array_keys($holder->parts()));
	}

	/** @return iterable<string, array{Field, object}> */
	public static function recordPayloads(): iterable
	{
		// Every part, including the optional `dependent_locality` — the question here is which
		// keys a submitter *may* send, not which ones an address is incomplete without.
		yield 'Address' => [
			new Field\Address(new FieldName('f'), ['AU']),
			self::auAddress(['dependent_locality' => 'Frenchville']),
		];
		yield 'Money' => [new Field\Money(new FieldName('f'), ['AUD' => 2]), (object) ['currency' => 'AUD', 'amount' => '12.50']];
		yield 'PhoneNumber' => [new Field\PhoneNumber(new FieldName('f'), ['AU']), (object) ['number' => '0411 222 333', 'country' => 'AU']];
		yield 'CreditCard' => [new Field\CreditCard(new FieldName('f')), (object) [
			'number' => '4111111111111111',
			'expiry' => '2030-01',
			'name' => 'Jane Doe',
			'security_code' => '123',
		]];
		yield 'File' => [new Field\File(new FieldName('f')), (object) [
			'name' => 'cv.pdf',
			'type' => 'application/pdf',
			'size' => 1024,
		]];
	}

	/**
	 * And the other half of the same rule: a field submitted as one string has no parts.
	 *
	 * `EmailAddress` reported `local_part, domain` and took `kim@example.test` — one box. Both
	 * halves are still readable as properties, and a rule about a domain was always written as
	 * `matches('/@example\.test$/')` rather than through a part, so nothing was lost by dropping
	 * them. What they cost was real: the field's messages went into a {@see \Meraki\Schema\Message\PartedSet}
	 * keyed by names no submitter had ever seen, and `#/fields/email/value/domain` resolved.
	 *
	 * Asserted as an inventory over every field rather than as one case, so the next value that
	 * reports a reading instead of an input fails here.
	 */
	#[Test]
	public function only_record_shaped_fields_report_parts(): void
	{
		$reporting = [];

		foreach (SealedFieldTest::fields() as $short => [$class]) {
			if (self::build($class)->parts !== []) {
				$reporting[] = $short;
			}
		}

		$this->assertSame(['Address', 'CreditCard', 'File', 'Money', 'PhoneNumber'], $reporting);
	}

	/**
	 * What a value cannot be without is declared by its part enum, and read off the field.
	 *
	 * A fact about the kind of value rather than configuration, so it is the same on every field of
	 * a kind and no wither changes it. A port reads it to know which inputs are required together.
	 *
	 * @param class-string<Field> $class
	 * @param list<Field\Part> $essential
	 */
	#[Test]
	#[DataProvider('essentialParts')]
	public function a_field_declares_the_parts_its_value_cannot_be_without(string $class, array $essential): void
	{
		$field = self::build($class);

		$this->assertSame($essential, $field->essentialParts);
		$this->assertSame($essential, $field->makeOptional()->essentialParts, 'an optional field still needs them together');
	}

	/** @return iterable<string, array{class-string<Field>, list<Field\Part>}> */
	public static function essentialParts(): iterable
	{
		yield 'Address' => [Field\Address::class, [Field\Address\Part::Country]];
		yield 'CreditCard' => [Field\CreditCard::class, [Field\CreditCard\Part::Number, Field\CreditCard\Part::Expiry, Field\CreditCard\Part::Name]];
		yield 'File' => [Field\File::class, [Field\File\Part::Name, Field\File\Part::Type, Field\File\Part::Size]];
		yield 'Money' => [Field\Money::class, [Field\Money\Part::Currency, Field\Money\Part::Amount]];
		yield 'PhoneNumber' => [Field\PhoneNumber::class, [Field\PhoneNumber\Part::Number, Field\PhoneNumber\Part::Country]];
		yield 'Text' => [Field\Text::class, []];
	}

	/**
	 * A part's name is the case's value and a string: the key input arrives under, and the
	 * `part.*` key a language pack translates.
	 */
	#[Test]
	public function every_part_is_named_by_a_string(): void
	{
		foreach (SealedFieldTest::fields() as [$class]) {
			foreach (self::build($class)->parts as $part) {
				$this->assertIsString($part->value, sprintf('%s::%s', $part::class, $part->name));
			}
		}
	}

	/** @param class-string<Field> $class */
	private static function build(string $class): Field
	{
		$name = new FieldName('f');

		return match ($class) {
			Field\Enum::class => new Field\Enum($name, ['a', 'b']),
			Field\Money::class => new Field\Money($name, ['AUD' => 2]),
			Field\Address::class => new Field\Address($name, ['AU']),
			Field\PhoneNumber::class => new Field\PhoneNumber($name, ['AU']),
			Field\Collection::class => new Field\Collection($name, new Field\Text(new FieldName('item'))),
			default => new $class($name),
		};
	}
}
