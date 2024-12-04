<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;


use Meraki\Schema\Field\Name;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(Name::class)]
final class NameTest extends FieldTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$name = $this->createField();

		$this->assertInstanceOf(Name::class, $name);
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

	public function createValidConstraintForValidValue(): ?Constraint
	{
		return new Attribute\Min(3);
	}

	public function createInvalidConstraintForValidValue(): ?Constraint
	{
		return new Attribute\Max(2);
	}
}
