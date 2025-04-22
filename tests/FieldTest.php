<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('field')]
#[CoversClass(Field::class)]
final class FieldTest extends TestCase
{
	#[Test]
	public function it_has_a_type(): void
	{
		$fieldType = $this->mockFieldType();
		$field = new Field($fieldType, new Field\Name('test'));

		$this->assertSame($fieldType, $field->type);
	}

	#[Test]
	public function it_has_a_name(): void
	{
		$name = new Field\Name('test');
		$field = new Field($this->mockFieldType(), $name);

		$this->assertSame($name, $field->name);
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
	public function it_is_required_by_default(): void
	{
		$field = $this->createField();

		$this->assertFalse($field->optional);
	}

	#[Test]
	public function it_can_be_made_optional(): void
	{
		$field = $this->createField()
			->makeOptional();

		$this->assertTrue($field->optional);
	}

	private function createField(string $name = 'test'): Field
	{
		return new Field($this->mockFieldType(), new Field\Name($name));
	}

	private function mockFieldType(): Field\Type
	{
		return new Field\Type\EmailAddress();
	}
}
