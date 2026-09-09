<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * `Address`, `Money` and `CreditCard` become one field holding one value object, the way
 * `File` already holds a `File\Value`. No sub-fields, so nothing is registered in the
 * schema's namespace and no name is ever built by joining strings.
 */
#[Group('api-2.0')]
final class StructuredTypeTest extends TestCase
{
	#[Test]
	public function a_structured_field_accepts_an_array(): void
	{
		$address = new Field\Address(new Property\Name('billing'), ['AU']);

		$resolved = $address->resolve([
			'line1' => '1 Denham St',
			'locality' => 'Rockhampton',
			'administrative_area' => 'QLD',
			'postal_code' => '4700',
			'country_code' => 'AU',
		]);

		$this->assertInstanceOf(Field\Address\Value::class, $resolved->transformed);
		$this->assertSame('1 Denham St', $resolved->transformed->line1);
	}

	#[Test]
	public function a_structured_field_accepts_its_own_value_object(): void
	{
		$address = new Field\Address(new Property\Name('billing'), ['AU']);
		$value = new Field\Address\Value(
			line1: '1 Denham St',
			locality: 'Rockhampton',
			administrativeArea: 'QLD',
			postalCode: '4700',
			countryCode: 'AU',
		);

		$this->assertEquals($value, $address->resolve($value)->transformed);
	}

	#[Test]
	public function a_structured_field_has_no_sub_fields(): void
	{
		$address = new Field\Address(new Property\Name('billing'), ['AU']);

		$this->assertFalse(property_exists($address, 'fields'));
	}

	#[Test]
	public function a_constraint_name_does_not_carry_the_field_name(): void
	{
		// The important half. Today a name embeds the field it came from, so renaming
		// `billing` to `invoice_address` changes every constraint it emits and every
		// message provider matching on them.
		$schema = new Facade('checkout');
		$schema->add($schema->createAddressField('invoice_address', ['AU']));

		$failed = $schema->validate(['invoice_address' => [
			'line1' => 'PO Box 42',
			'locality' => 'Rockhampton',
			'postal_code' => '4700',
			'country_code' => 'AU',
		]])->get('invoice_address');

		$this->assertNotNull($failed->get('line1Visitable'));
		$this->assertStringNotContainsString('invoice_address', implode(',', $failed->constraintNames()));
	}

	#[Test]
	public function a_money_field_carries_currency_and_amount_in_one_value(): void
	{
		$money = new Field\Money(new Property\Name('cost'), ['AUD' => 2]);

		$resolved = $money->resolve(['currency' => 'AUD', 'amount' => '12.50']);

		$this->assertInstanceOf(Field\Money\Value::class, $resolved->transformed);
		$this->assertSame('AUD', $resolved->transformed->currency);
	}

	#[Test]
	public function a_file_field_holds_exactly_one_file(): void
	{
		// Several files is a collection of file fields, not a field that is itself plural.
		$file = new Field\File(new Property\Name('resume'));

		$this->assertInstanceOf(
			Field\File\Value::class,
			$file->resolve(['name' => 'cv.pdf', 'type' => 'application/pdf', 'size' => 1024])->transformed,
		);

		$this->assertFalse(method_exists($file, 'minCountOf'));
	}

	#[Test]
	public function several_values_is_a_collection(): void
	{
		$schema = new Facade('application');
		$schema->add($schema->createCollectionField(
			'attachments',
			fn(Facade $item) => $item->add($item->createFileField('file')),
		)->minCountOf(1));

		$result = $schema->validate(['attachments' => []]);

		$this->assertTrue($result->get('attachments')->get('minCount')->failed());
	}

	#[Test]
	public function a_collection_item_may_have_several_fields(): void
	{
		// What Composite was kept for. Collection already holds a template of several
		// fields and validates each item against all of them.
		$schema = new Facade('schedule');
		$schema->add($schema->createCollectionField('sessions', function (Facade $item): void {
			$item->add($item->createDateTimeField('starts_at'));
			$item->add($item->createDateTimeField('ends_at'));
		})->minCountOf(1));

		$result = $schema->validate([
			'sessions' => [
				['starts_at' => '2026-01-01T09:00', 'ends_at' => '2026-01-01T10:00'],
			],
		]);

		$this->assertFalse($result->anyFailed());
	}

	#[Test]
	public function an_address_is_not_specific_by_default(): void
	{
		// A suburb, a state and a postcode is an address — just a vague one. Requiring a
		// street is a separate decision from what the address is for.
		$address = new Field\Address(new Property\Name('service_area'), ['AU']);

		$resolved = $address->validate([
			'locality' => 'Rockhampton',
			'administrative_area' => 'QLD',
			'postal_code' => '4700',
		]);

		$this->assertFalse($resolved->anyFailed());
	}

	#[Test]
	public function a_specific_address_requires_a_street(): void
	{
		$address = (new Field\Address(new Property\Name('billing'), ['AU']))->mustBeSpecific();

		$failed = $address->validate([
			'locality' => 'Rockhampton',
			'administrative_area' => 'QLD',
			'postal_code' => '4700',
		])->get('specific');

		$this->assertTrue($failed->failed());
		$this->assertSame('line1', $failed->part);
	}

	#[Test]
	public function deliverability_and_granularity_are_independent(): void
	{
		// Two dials, not one enum. Type says what the address is *for*; mustBeSpecific()
		// says how much of it is required.
		$visitable = (new Field\Address(new Property\Name('pickup'), ['AU']))
			->ofType(Field\Address\Type::Physical)
			->mustBeSpecific();

		$failed = $visitable->validate([
			'line1' => 'PO Box 42',
			'locality' => 'Rockhampton',
			'administrative_area' => 'QLD',
			'postal_code' => '4700',
		]);

		$this->assertTrue($failed->get('line1Visitable')->failed());
		$this->assertFalse($failed->get('specific')->failed());
	}

	#[Test]
	public function a_postal_address_that_is_not_specific_is_rejected_where_it_is_declared(): void
	{
		// You cannot post to a suburb. A combination with no meaning is refused at
		// definition time, as the baseline floors are.
		$this->expectException(\InvalidArgumentException::class);

		(new Field\Address(new Property\Name('billing'), ['AU']))->ofType(Field\Address\Type::Postal);
	}

	#[Test]
	public function a_determined_country_need_not_be_supplied(): void
	{
		// One allowed country settles it, so the author's allow-list answers for the user.
		$address = new Field\Address(new Property\Name('billing'), ['AU']);

		$resolved = $address->validate([
			'locality' => 'Rockhampton',
			'administrative_area' => 'QLD',
			'postal_code' => '4700',
		]);

		$this->assertSame('AU', $resolved->transformed->countryCode);
	}
}
