<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

use Meraki\Form\Constraint;
use Meraki\Form\Constraint\Max;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Max::class)]
final class MaxTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$max = new Max(5);

		$this->assertInstanceOf(Max::class, $max);
	}

	#[Test]
	public function it_is_a_constraint(): void
	{
		$max = new Max(5);

		$this->assertInstanceOf(Constraint::class, $max);
	}

	#[Test]
	public function can_get_value(): void
	{
		$max = new Max(5);

		$this->assertEquals(5, $max->value);
	}

	#[Test]
	public function it_fails_if_value_is_over_max(): void
	{
		$max = new Max(5);

		$result = $max->validate(6);

		$this->assertTrue($result->failed());
		$this->assertEquals('Expected value to be less than or equal to 5.', $result->reason);
	}

	#[Test]
	public function it_passes_if_value_is_equal_to_max(): void
	{
		$max = new Max(5);

		$result = $max->validate(5);

		$this->assertTrue($result->passed());
	}

	#[Test]
	public function it_passes_if_value_is_less_than_max(): void
	{
		$max = new Max(5);

		$result = $max->validate(4);

		$this->assertTrue($result->passed());
	}
}
