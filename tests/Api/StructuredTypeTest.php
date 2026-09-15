<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use Meraki\Schema\FieldName;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

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
			'line1' => '1 Denham St',
			'locality' => 'Rockhampton',
			'administrative_area' => 'QLD',
			'postal_code' => '4700',
			'country' => 'AU',
		]);
	}

	/**
	 * The same address with no street — an area rather than a place. It still names a country,
	 * because that is never optional; what makes this an area is the missing `line1`.
	 */
	private static function area(): object
	{
		return (object) [
			'locality' => 'Rockhampton',
			'administrative_area' => 'QLD',
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
		$this->assertSame('1 Denham St', $resolved->value->line1);
	}

	/**
	 * An array is refused where a record belongs, which is what lets a collection key its rows.
	 */
	#[Test]
	public function a_structured_field_refuses_a_list(): void
	{
		$address = new Field\Address(new FieldName('billing'), ['AU']);

		$this->assertTrue($address->validate(['line1' => '1 Denham St'])->shape->wasUnreadable());
	}

	#[Test]
	public function a_structured_field_accepts_its_own_value_object(): void
	{
		$address = new Field\Address(new FieldName('billing'), ['AU']);
		$value = new Field\Address\Value(
			line1: '1 Denham St',
			locality: 'Rockhampton',
			administrativeArea: 'QLD',
			postalCode: '4700',
			countryCode: 'AU',
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
		$schema = new Facade('checkout');
		$schema->add($schema->createAddressField('invoice_address', ['AU'])->allowOnlyPhysical());

		$failed = $schema->validate((object) [
			'invoice_address' => self::auAddress(['line1' => 'PO Box 42']),
		])->forField('invoice_address');

		$this->assertNotNull($failed->forConstraint('line1Visitable'));
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

		$resolved = $file->resolve((object) [
			'name' => 'cv.pdf',
			'type' => 'application/pdf',
			'size' => 1024,
			'tmp_name' => '/tmp/php1234',
			'error' => 0,
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
		$schema = new Facade('application');
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
		$schema = new Facade('schedule');
		$schema->add(
			$schema->createCollectionField(
				'sessions',
				$schema->createDateTimeField('starts_at'),
				$schema->createDateTimeField('ends_at'),
			)->minCountOf(1),
		);

		$result = $schema->validate((object) [
			'sessions' => [
				(object) ['starts_at' => '2026-01-01T09:00', 'ends_at' => '2026-01-01T10:00'],
			],
		]);

		$this->assertFalse($result->anyFailed());
	}

	/**
	 * An address requires a street *by default*, and giving that up is the explicit call.
	 *
	 * This used to read the other way round — not specific by default, with `mustBeSpecific()` to
	 * tighten it. The baseline rule settled it: a field with no configuration is already correct
	 * and configuration narrows from there, so the default is the stricter reading and
	 * `allowWithoutStreet()` is what widens it.
	 */
	#[Test]
	public function an_address_requires_a_street_by_default(): void
	{
		$address = new Field\Address(new FieldName('billing'), ['AU']);

		$failed = $address->validate(self::area())->forConstraint('specific');

		$this->assertTrue($failed->failed());
		$this->assertSame('line1', $failed->part);
	}

	#[Test]
	public function an_address_may_name_only_an_area(): void
	{
		// For a service area or a catchment, where the region *is* the answer rather than an
		// incomplete version of one.
		$address = (new Field\Address(new FieldName('service_area'), ['AU']))->allowWithoutStreet();

		$this->assertFalse($address->validate(self::area())->anyFailed());
	}

	#[Test]
	public function deliverability_and_granularity_are_independent(): void
	{
		// Two dials, not one enum. What the address is *for* is separate from how much of it
		// is required: a PO box is a perfectly specific address you cannot visit.
		$visitable = (new Field\Address(new FieldName('pickup'), ['AU']))->allowOnlyPhysical();

		$failed = $visitable->validate(self::auAddress(['line1' => 'PO Box 42']));

		$this->assertTrue($failed->forConstraint('line1Visitable')->failed());
		$this->assertFalse($failed->forConstraint('specific')->failed());
	}

	#[Test]
	public function a_mailable_address_that_needs_no_street_is_rejected_where_it_is_declared(): void
	{
		// You cannot post to a suburb. A combination with no meaning is refused at definition
		// time, as the baseline floors are — there is no input that could satisfy it, so there
		// is nothing to report per request.
		$this->expectException(\InvalidArgumentException::class);

		(new Field\Address(new FieldName('billing'), ['AU']))
			->allowWithoutStreet()
			->allowOnlyMailable();
	}

	#[Test]
	public function the_same_contradiction_is_caught_from_the_other_side(): void
	{
		// Either call can be the second, so the guard is on both withers rather than one.
		$this->expectException(\InvalidArgumentException::class);

		(new Field\Address(new FieldName('billing'), ['AU']))
			->allowOnlyMailable()
			->allowWithoutStreet();
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
			'line1' => '1 Denham St',
			'locality' => 'Rockhampton',
			'administrative_area' => 'QLD',
			'postal_code' => '4700',
		]);

		$this->assertTrue($resolved->shape->wasUnreadable());

		// And with one, it is stored as the code whatever spelling arrived.
		$this->assertSame('AU', $address->validate(self::auAddress(['country' => 'Australia']))->value->countryCode);
	}
}
