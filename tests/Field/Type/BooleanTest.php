<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\Type\Boolean;
use Meraki\Schema\Validator\CheckType;
use Meraki\Schema\Field\TypeTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('field')]
#[CoversClass(Boolean::class)]
final class BooleanTest extends TypeTestCase
{
	public function createType(): Boolean
	{
		return new Boolean();
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$type = $this->createType();

		$this->assertSame('boolean', $type->name);
	}

	#[Test]
	public function it_only_allows_boolean_values(): void
	{
		$type = $this->createType();

		$this->assertTrue($type->accepts(true));
		$this->assertTrue($type->accepts(false));
		$this->assertFalse($type->accepts(1));
		$this->assertFalse($type->accepts(0));
		$this->assertFalse($type->accepts('true'));
		$this->assertFalse($type->accepts('false'));
	}

	#[Test]
	public function it_returns_a_boolean_validator(): void
	{
		$type = $this->createType();

		$validator = $type->getValidator();

		$this->assertInstanceOf(CheckType::class, $validator);
		$this->assertSame($type, $validator->type);
	}
}
