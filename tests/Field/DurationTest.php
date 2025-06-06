<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Duration;
use Meraki\Schema\Property\Name;
use Meraki\Schema\FieldTestCase;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('field')]
#[CoversClass(Duration::class)]
final class DurationTest extends FieldTestCase
{
	public function createField(): Duration
	{
		return new Duration(new Name('duration'));
	}

	#[Test]
	public function it_has_the_correct_type(): void
	{
		$type = $this->createField();

		$this->assertSame('duration', $type->type->value);
	}

	#[Test]
	public function default_min_constraint_is_zero(): void
	{
		$type = $this->createField();

		$this->assertSame('PT0S', $type->min->toISOString());
	}

	#[Test]
	public function default_max_constraint_is_one_day(): void
	{
		$type = $this->createField();

		$this->assertSame('PT24H', $type->max->toISOString());
	}

	#[Test]
	public function default_step_constraint_is_one_minute(): void
	{
		$type = $this->createField();

		$this->assertSame('PT1M', $type->step->toISOString());
	}

	#[Test]
	#[DataProvider('validDurations')]
	public function it_validates_valid_durations(string $duration): void
	{
		$type = $this->createField()
			->input($duration);

		$result = $type->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
	}

	public static function validDurations(): array
	{
		return [
			'zero nanoseconds' => ['PT0.0S'],
			'positive nanoseconds' => ['PT0.000000001S'],
			'zero seconds' => ['PT0S'],
			'positive seconds' => ['PT15S'],
			'zero minutes' => ['PT0M'],
			'positive minutes' => ['PT15M'],
			'zero hours' => ['PT0H'],
			'positive hours' => ['PT15H'],
			'zero days' => ['P0D'],
			'positive days' => ['P15D'],
			'seconds and minutes' => ['PT15M30S'],
			'seconds and hours' => ['PT15H30S'],
			'minutes and hours' => ['PT15H30M'],
			'seconds and days' => ['P15DT15S'],
			'minutes and days' => ['P15DT15M'],
			'hours and days' => ['P15DT15H'],
		];
	}

	#[Test]
	#[DataProvider('invalidDurations')]
	public function it_does_not_validate_invalid_durations(mixed $duration): void
	{
		$type = $this->createField()
			->input($duration);

		$result = $type->validate();

		$this->assertConstraintValidationResultFailed('type', $result);
	}

	public static function invalidDurations(): array
	{
		return [
			'empty string' => [''],
			'null' => [null],
			'boolean true' => [true],
			'boolean false' => [false],
			'integer' => [123],
			'float' => [123.45],
			'array' => [['PT1H']],
			'object' => [(object) ['duration' => 'PT1H']],
			'no duration' => ['PT'],
			'years and months' => ['P1Y2M1D'],
			'days in time component' => ['PT1H2M3S4D'],
			'period and duration mixed together' => ['P1Y2M3DT4H5M6S'],
			'invalid format 3' => ['PT1H2M3S4Y'],
		];
	}

	#[Test]
	#[DataProvider('minConstraintExpectations')]
	public function min_constraint_meets_expectations(string $min, string $input, ValidationStatus $status): void
	{
		$type = $this->createField()
			->minOf($min)
			->input($input);

		$result = $type->validate();

		$this->assertConstraintValidationResultHasStatusOf($status, 'min', $result);
	}

	public static function minConstraintExpectations(): array
	{
		return [
			'input before min duration (hours)' => ['PT2H', 'PT1H', ValidationStatus::Failed],
			'input at min duration (hours)' => ['PT1H', 'PT1H', ValidationStatus::Passed],
			'input after min duration (hours)' => ['PT1H', 'PT4H', ValidationStatus::Passed],
			'input before min duration (days)' => ['P2D', 'P1D', ValidationStatus::Failed],
			'input at min duration (days)' => ['P1D', 'P1D', ValidationStatus::Passed],
			'input after min duration (days)' => ['P1D', 'P2D', ValidationStatus::Passed],
			'input before min duration (seconds)' => ['PT1S', 'PT0.5S', ValidationStatus::Failed],
			'input at min duration (seconds)' => ['PT1S', 'PT1S', ValidationStatus::Passed],
			'input after min duration (seconds)' => ['PT1S', 'PT2S', ValidationStatus::Passed],
			'input before min duration (minutes)' => ['PT1M', 'PT30S', ValidationStatus::Failed],
			'input at min duration (minutes)' => ['PT1M', 'PT1M', ValidationStatus::Passed],
			'input after min duration (minutes)' => ['PT1M', 'PT2M', ValidationStatus::Passed],
			'input before min duration (nanoseconds)' => ['PT1.0S', 'PT0.5S', ValidationStatus::Failed],
			'input at min duration (nanoseconds)' => ['PT1.0S', 'PT1.0S', ValidationStatus::Passed],
			'input after min duration (nanoseconds)' => ['PT1.0S', 'PT2.0S', ValidationStatus::Passed],
			'input before min duration (mixed)' => ['P1D', 'PT23H', ValidationStatus::Failed],
			'input at min duration (mixed)' => ['PT60S', 'PT1M', ValidationStatus::Passed],
			'input after min duration (mixed)' => ['PT60M', 'PT3H', ValidationStatus::Passed],
		];
	}

	#[Test]
	#[DataProvider('maxConstraintExpectations')]
	public function max_constraint_meets_expectations(string $max, string $input, ValidationStatus $status): void
	{
		$type = $this->createField()
			->maxOf($max)
			->input($input);

		$result = $type->validate();

		$this->assertConstraintValidationResultHasStatusOf($status, 'max', $result);
	}

	public static function maxConstraintExpectations(): array
	{
		return [
			'input before max duration (hours)' => ['PT2H', 'PT1H', ValidationStatus::Passed],
			'input at max duration (hours)' => ['PT1H', 'PT1H', ValidationStatus::Passed],
			'input after max duration (hours)' => ['PT1H', 'PT2H', ValidationStatus::Failed],
			'input before max duration (days)' => ['P2D', 'P1D', ValidationStatus::Passed],
			'input at max duration (days)' => ['P1D', 'P1D', ValidationStatus::Passed],
			'input after max duration (days)' => ['P1D', 'P2D', ValidationStatus::Failed],
			'input before max duration (seconds)' => ['PT2S', 'PT1S', ValidationStatus::Passed],
			'input at max duration (seconds)' => ['PT1S', 'PT1S', ValidationStatus::Passed],
			'input after max duration (seconds)' => ['PT0.5S', 'PT1S', ValidationStatus::Failed],
			'input before max duration (minutes)' => ['PT2M', 'PT1M', ValidationStatus::Passed],
			'input at max duration (minutes)' => ['PT1M', 'PT1M', ValidationStatus::Passed],
			'input after max duration (minutes)' => ['PT30S', 'PT1M', ValidationStatus::Failed],
			'input before max duration (nanoseconds)' => ['PT1.0S', 'PT0.0005S', ValidationStatus::Passed],
			'input at max duration (nanoseconds)' => ['PT0.0006S', 'PT0.0006S', ValidationStatus::Passed],
			'input after max duration (nanoseconds)' => ['PT0.0005S', 'PT0.005S', ValidationStatus::Failed],
			'input before max duration (mixed)' => ['PT1H', 'PT45M', ValidationStatus::Passed],
			'input at max duration (mixed)' => ['P1D', 'PT24H', ValidationStatus::Passed],
			'input after max duration (mixed)' => ['PT29M29S', 'PT1H30M', ValidationStatus::Failed],
		];
	}

	#[Test]
	#[DataProvider('stepConstraintExpectations')]
	public function step_constraint_meets_expectations(string $min, string $step, string $input, ValidationStatus $status): void
	{
		$type = $this->createField()
			->minOf($min)
			->inIncrementsOf($step)
			->input($input);

		$result = $type->validate();

		$this->assertConstraintValidationResultHasStatusOf($status, 'step', $result);
	}

	public static function stepConstraintExpectations(): array
	{
		return [
			'zero step' => ['PT1S', 'PT0S', 'PT2S', ValidationStatus::Failed],
			'min and input are same (seconds)' => ['PT1S', 'PT1S', 'PT1S', ValidationStatus::Passed],
			'min and input are same (mixed)' => ['P1D', 'PT1H', 'PT24H', ValidationStatus::Passed],
			'input is not a multiple of step (mixed)' => ['PT0S', 'PT5M', 'PT7M', ValidationStatus::Failed],
			'input is a multiple of step (mixed)' => ['P1D', 'PT1H', 'P1DT4H', ValidationStatus::Passed],
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
			->minOf('PT1H')
			->maxOf('PT5H')
			->inIncrementsOf('PT30M')
			->prefill('PT2H30M');

		$serialized = $sut->serialize();

		$this->assertEquals('duration', $serialized->type);
		$this->assertEquals('duration', $serialized->name);
		$this->assertFalse($serialized->optional);
		$this->assertEquals('PT1H', $serialized->min);
		$this->assertEquals('PT5H', $serialized->max);
		$this->assertEquals('PT30M', $serialized->step);
		$this->assertEquals('PT2H30M', $serialized->value);

		$deserialized = Duration::deserialize($serialized);

		$this->assertEquals('duration', $deserialized->type->value);
		$this->assertEquals('duration', $deserialized->name->value);
		$this->assertFalse($deserialized->optional);
		$this->assertEquals('PT1H', $deserialized->min->__toString());
		$this->assertEquals('PT5H', $deserialized->max->__toString());
		$this->assertEquals('PT30M', $deserialized->step->__toString());
		$this->assertEquals('PT2H30M', $deserialized->defaultValue->unwrap());
	}
}
