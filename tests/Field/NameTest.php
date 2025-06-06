<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;


use Meraki\Schema\Field\Name;
use Meraki\Schema\Property\Name as FieldName;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(Name::class)]
final class NameTest extends FieldTestCase
{
	public function createField(): Name
	{
		return new Name(new FieldName('name'));
	}

	#[Test]
	#[DataProvider('validNames')]
	public function it_validates_valid_names(string $name): void
	{
		$type = $this->createField()
			->input($name);

		$result = $type->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
	}

	public static function validNames(): array
	{
		return [
			'just first name' => ['John'],
			'first and last name' => ['John Doe'],
			'first, middle, and last name' => ['John Michael Doe'],
			'with hyphen' => ['John-Michael Doe'],
			'with apostrophe' => ['John O\'Doe'],
			'with period' => ['John M. Doe'],
			'with comma' => ['John, Doe'],
			'with numerals' => ['John Doe III'],
		];
	}

	#[Test]
	public function it_has_no_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->value->unwrap());
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->defaultValue->unwrap());
	}

	#[Test]
	public function it_serializes_and_deserializes(): void
	{
		$sut = $this->createField()
			->minLengthOf(5)
			->maxLengthOf(128)
			->prefill('John Doe');

		$serialized = $sut->serialize();

		$this->assertEquals('name', $serialized->type);
		$this->assertEquals('name', $serialized->name);
		$this->assertFalse($serialized->optional);
		$this->assertEquals(5, $serialized->min);
		$this->assertEquals(128, $serialized->max);
		$this->assertEquals('John Doe', $serialized->value);

		$deserialized = Name::deserialize($serialized);

		$this->assertEquals('name', $deserialized->type->value);
		$this->assertEquals('name', $deserialized->name->value);
		$this->assertFalse($deserialized->optional);
		$this->assertEquals(5, $deserialized->min);
		$this->assertEquals(128, $deserialized->max);
		$this->assertEquals('John Doe', $deserialized->defaultValue->unwrap());
	}
}
