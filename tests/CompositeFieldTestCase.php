<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\CompositeField;
use Meraki\Schema\Field;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(CompositeField::class)]
abstract class CompositeFieldTestCase extends FieldTestCase
{
	#[Test]
	public function it_is_a_composite_field(): void
	{
		$this->assertInstanceOf(Field::class, $this->createField());
	}

	// #[Test]
	// public function it_is_a_field(): void
	// {
	// 	$compositeField = new CompositeField(
	// 		new Attribute\Type('address'),
	// 		new Attribute\Name('address'),
	// 	);

	// 	$this->assertInstanceOf(Field::class, $compositeField);
	// }

	#[Test]
	public function naming_a_composite_field_will_prefix_all_its_sub_fields(): void
	{
		$street = new Field\Text(new Attribute\Name('street'));
		$suburb = new Field\Text(new Attribute\Name('suburb'));
		$postCode = new Field\Text(new Attribute\Name('post_code'));

		$address = (new CompositeField(new Attribute\Type('address'), new Attribute\Name('address_')))
			->add($street, $suburb, $postCode);

		$this->assertEquals('address_street', $street->name->value);
		$this->assertEquals('address_suburb', $suburb->name->value);
		$this->assertEquals('address_post_code', $postCode->name->value);
	}
}
