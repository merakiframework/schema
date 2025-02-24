<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;


use Meraki\Schema\Field\Name;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, DataProvider};

#[CoversClass(Name::class)]
final class NameTest extends FieldTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$name = $this->createField();

		$this->assertInstanceOf(Name::class, $name);
	}

	#[Test]
	#[DataProvider('validNames')]
	public function it_validates_valid_names(string $name): void
	{
		$field = $this->createField()
			->input($name);

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Type::class);
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

	public function createField(): Name
	{
		return new Name(new Attribute\Name('full_name'));
	}

	public function getExpectedType(): string
	{
		return 'name';
	}

	public function getValidValue(): mixed
	{
		return 'John Doe';
	}

	public function getInvalidValue(): mixed
	{
		return '123';
	}

	public function usesConstraints(): bool
	{
		return true;
	}

	public function createInvalidConstraint(): Constraint
	{
		return new Attribute\Max(2);
	}

	public function createValidConstraint(): Constraint
	{
		return new Attribute\Min(3);
	}
}
