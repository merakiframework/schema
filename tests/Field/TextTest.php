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
			->minLengthOf(4);

		$result = $type->validate('hello');

		$this->assertConstraintValidationResultPassed('min', $result);
	}

	#[Test]
	public function min_constraint_fails_when_not_met(): void
	{
		$type = $this->createField()
			->minLengthOf(4);

		$result = $type->validate('abc');

		$this->assertConstraintValidationResultFailed('min', $result);
	}

	#[Test]
	public function max_constraint_passes_when_met(): void
	{
		$type = $this->createField()
			->maxLengthOf(4);

		$result = $type->validate('abc');

		$this->assertConstraintValidationResultPassed('max', $result);
	}

	#[Test]
	public function max_constraint_fails_when_not_met(): void
	{
		$type = $this->createField()
			->maxLengthOf(4);

		$result = $type->validate('hello');

		$this->assertConstraintValidationResultFailed('max', $result);
	}

	#[Test]
	public function pattern_constraint_is_skipped_when_not_set(): void
	{
		$type = $this->createField();

		$result = $type->validate('abc123');

		$this->assertConstraintValidationResultSkipped('pattern', $result);
	}

	#[Test]
	public function pattern_constraint_passes_when_met(): void
	{
		$type = $this->createField()
			->matches('/^[a-z]+$/i');

		$result = $type->validate('abc');

		$this->assertConstraintValidationResultPassed('pattern', $result);
	}

	#[Test]
	public function pattern_constraint_fails_when_not_met(): void
	{
		$type = $this->createField()
			->matches('/^[a-z]+$/i');

		$result = $type->validate('abc123');

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
}
