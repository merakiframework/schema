<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

use Meraki\Form\Constraint;
use Meraki\Form\Constraint\Min;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Min::class)]
final class MinTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$min = new Min(5);

		$this->assertInstanceOf(Min::class, $min);
	}

	#[Test]
	public function it_is_a_constraint(): void
	{
		$min = new Min(5);

		$this->assertInstanceOf(Constraint::class, $min);
	}

	#[Test]
	public function can_get_value(): void
	{
		$min = new Min(5);

		$this->assertEquals(5, $min->value);
	}

	#[Test]
	public function it_validates_that_value_is_greater_than_or_equal_to_min(): void
	{
		$min = new Min(5);

		$result = $min->validate(4);

		$this->assertTrue($result->failed());
		$this->assertEquals('Expected a value greater than or equal to 5.', $result->reason);
	}

	#[Test]
	public function it_validates_if_value_is_equal_to_min(): void
	{
		$min = new Min(5);

		$result = $min->validate(5);

		$this->assertTrue($result->passed());
	}

	#[Test]
	public function it_validates_that_length_of_string_is_greater_than_or_equal_to_min(): void
	{
		$min = new Min(5);

		$result = $min->validate('four');

		$this->assertTrue($result->failed());
		$this->assertEquals('Expected value to be at least 5 characters long.', $result->reason);
	}

	#[Test]
	public function it_validates_if_length_of_string_is_equal_to_min(): void
	{
		$min = new Min(5);

		$result = $min->validate('three');

		$this->assertTrue($result->passed());
	}
}
