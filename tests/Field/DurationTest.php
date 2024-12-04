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
		$duration = $this->createFieldWithNoValueAndNoDefaultValue();

		$this->assertInstanceOf(Duration::class, $duration);
	}

	#[Test]
	public function it_validates_against_min_constraint_pass(): void
	{
		$duration = $this->createField()
			->minOf('PT1H')
			->input('PT2H');

		$this->assertTrue($duration->validate()->constraintValidationResults->allPassed());
	}

	#[Test]
	public function it_validates_against_min_constraint_fail(): void
	{
		$duration = $this->createField()
			->minOf('PT1H')
			->input('PT30M');

		$this->assertTrue($duration->validate()->constraintValidationResults->allFailed());
	}

	#[Test]
	public function it_validates_against_max_constraint_pass(): void
	{
		$duration = $this->createField()
			->maxOf('PT1H')
			->input('PT30M');

		$this->assertTrue($duration->validate()->constraintValidationResults->allPassed());
	}

	#[Test]
	public function it_validates_against_max_constraint_fail(): void
	{
		$duration = $this->createField()
			->maxOf('PT1H')
			->input('PT2H');

		$this->assertTrue($duration->validate()->constraintValidationResults->allFailed());
	}

	#[Test]
	public function it_validates_against_step_constraint_pass(): void
	{
		$duration = $this->createField()
			->inIncrementsOf('PT1H')
			->input('PT4H');

		$this->assertTrue($duration->validate()->constraintValidationResults->allPassed());
	}

	#[Test]
	public function it_validates_against_step_constraint_fail(): void
	{
		$duration = $this->createField()
			->inIncrementsOf('PT30M')
			->input('PT1H15M');

		$this->assertTrue($duration->validate()->constraintValidationResults->allFailed());
	}

	public function createField(): Duration
	{
		return new Duration(new Attribute\Name('duration'));
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

	public function createValidConstraintForValidValue(): ?Constraint
	{
		return new Attribute\Max('PT1H');
	}

	public function createInvalidConstraintForValidValue(): ?Constraint
	{
		return new Attribute\Max('PT30M');
	}
}
