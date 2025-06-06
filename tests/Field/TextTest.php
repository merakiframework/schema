<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Text;
use Meraki\Schema\Property\Name;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('field')]
#[CoversClass(Text::class)]
final class TextTest extends FieldTestCase
{
	public function createField(): Text
	{
		return new Text(new Name('text'));
	}

	#[Test]
	public function min_constraint_passes_when_met(): void
	{
		$type = $this->createField()
			->minLengthOf(4)
			->input('hello');

		$result = $type->validate();

		$this->assertConstraintValidationResultPassed('min', $result);
	}

	#[Test]
	public function min_constraint_fails_when_not_met(): void
	{
		$type = $this->createField()
			->minLengthOf(4)
			->input('abc');

		$result = $type->validate();

		$this->assertConstraintValidationResultFailed('min', $result);
	}

	#[Test]
	public function max_constraint_passes_when_met(): void
	{
		$type = $this->createField()
			->maxLengthOf(4)
			->input('abc');

		$result = $type->validate();

		$this->assertConstraintValidationResultPassed('max', $result);
	}

	#[Test]
	public function max_constraint_fails_when_not_met(): void
	{
		$type = $this->createField()
			->maxLengthOf(4)
			->input('hello');

		$result = $type->validate();

		$this->assertConstraintValidationResultFailed('max', $result);
	}

	#[Test]
	public function pattern_constraint_is_skipped_when_not_set(): void
	{
		$type = $this->createField()
			->input('abc123');

		$result = $type->validate();

		$this->assertConstraintValidationResultSkipped('pattern', $result);
	}

	#[Test]
	public function pattern_constraint_passes_when_met(): void
	{
		$type = $this->createField()
			->matches('/^[a-z]+$/i')
			->input('abc');

		$result = $type->validate();

		$this->assertConstraintValidationResultPassed('pattern', $result);
	}

	#[Test]
	public function pattern_constraint_fails_when_not_met(): void
	{
		$type = $this->createField()
			->matches('/^[a-z]+$/i')
			->input('abc123');

		$result = $type->validate();

		$this->assertConstraintValidationResultFailed('pattern', $result);
	}

	#[Test]
	public function throws_exception_when_pattern_is_invalid(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid regular expression provided.');

		$this->createField()->matches('[');
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
			->makeOptional()
			->matches('/[a-zA-Z_][a-zA-Z0-9_]/')
			->minLengthOf(5)
			->maxLengthOf(24)
			->prefill('doStuff');

		$serialized = $sut->serialize();

		// serializing normalises the phone number
		$this->assertEquals('text', $serialized->type);
		$this->assertEquals('text', $serialized->name);
		$this->assertTrue($serialized->optional);
		$this->assertEquals(5, $serialized->min);
		$this->assertEquals(24, $serialized->max);
		$this->assertEquals('/[a-zA-Z_][a-zA-Z0-9_]/', $serialized->pattern);
		$this->assertEquals('doStuff', $serialized->value);

		$deserialized = Text::deserialize($serialized);

		$this->assertEquals('text', $deserialized->type->value);
		$this->assertEquals('text', $deserialized->name->value);
		$this->assertTrue($deserialized->optional);
		$this->assertEquals(5, $deserialized->min);
		$this->assertEquals(24, $deserialized->max);
		$this->assertEquals('/[a-zA-Z_][a-zA-Z0-9_]/', $deserialized->pattern);
		$this->assertEquals('doStuff', $deserialized->defaultValue->unwrap());
	}
}
