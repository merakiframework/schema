<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;


use Meraki\Schema\Field\Duration;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(Duration::class)]
final class DurationTest extends FieldTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$duration = $this->createField();

		$this->assertInstanceOf(Duration::class, $duration);
	}

	#[Test]
	public function an_empty_string_fails_type_validation(): void
	{
		$duration = $this->createField()->input('');

		$this->assertTrue($duration->validationResult->failed());
		$this->assertValidationFailedForConstraint($duration, Attribute\Type::class);
	}

	#[Test]
	public function it_validates_against_min_constraint_pass(): void
	{
		$duration = $this->createField()
			->minOf('PT1H')
			->input('PT2H');

		$this->assertTrue($duration->validationResult->passed());
		$this->assertValidationPassedForConstraint($duration, Attribute\Min::class);
	}

	#[Test]
	public function it_validates_against_min_constraint_fail(): void
	{
		$duration = $this->createField()
			->minOf('PT1H')
			->input('PT30M');

		$this->assertTrue($duration->validationResult->failed());
		$this->assertValidationFailedForConstraint($duration, Attribute\Min::class);
	}

	#[Test]
	public function it_validates_against_max_constraint_pass(): void
	{
		$duration = $this->createField()
			->maxOf('PT1H')
			->input('PT30M');

		$this->assertTrue($duration->validationResult->passed());
		$this->assertValidationPassedForConstraint($duration, Attribute\Max::class);
	}

	#[Test]
	public function it_validates_against_max_constraint_fail(): void
	{
		$duration = $this->createField()
			->maxOf('PT1H')
			->input('PT2H');

		$this->assertTrue($duration->validationResult->failed());
		$this->assertValidationFailedForConstraint($duration, Attribute\Max::class);
	}

	#[Test]
	public function it_validates_against_step_constraint_pass(): void
	{
		$duration = $this->createField()
			->inIncrementsOf('PT1H')
			->input('PT4H');

		$this->assertTrue($duration->validationResult->passed());
		$this->assertValidationPassedForConstraint($duration, Attribute\Step::class);
	}

	#[Test]
	public function it_validates_against_step_constraint_fail(): void
	{
		$duration = $this->createField()
			->inIncrementsOf('PT30M')
			->input('PT1H15M');

		$this->assertTrue($duration->validationResult->failed());
		$this->assertValidationFailedForConstraint($duration, Attribute\Step::class);
	}

	public function createField(): Duration
	{
		return new Duration(new Attribute\Name('duration'));
	}

	public function usesConstraints(): bool
	{
		return true;
	}

	public function getExpectedType(): string
	{
		return 'duration';
	}

	public function getValidValue(): string
	{
		return 'PT1H';
	}

	public function getInvalidValue(): string
	{
		return '1 hour';
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
