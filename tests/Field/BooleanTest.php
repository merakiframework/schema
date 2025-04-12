<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;


use Meraki\Schema\Field\Boolean;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(Boolean::class)]
final class BooleanTest extends FieldTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$boolean = $this->createField();

		$this->assertInstanceOf(Boolean::class, $boolean);
	}

	public function createField(): Boolean
	{
		return new Boolean(new Attribute\Name('boolean'));
	}

	public function usesConstraints(): bool
	{
		return false;
	}

	public function getExpectedType(): string
	{
		return 'boolean';
	}

	public function getValidValue(): bool
	{
		return true;
	}

	public function getInvalidValue(): string
	{
		return '';
	}

	public function createValidConstraint(): Constraint
	{
		return new Attribute\Max('PT1H');
	}

	public function createInvalidConstraint(): Constraint
	{
		return new Attribute\Max('PT30M');
	}
}
