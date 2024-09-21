<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

use Meraki\Form\Constraint;
use Meraki\Form\Constraint\Pattern;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Pattern::class)]
final class PatternTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$pattern = new Pattern('/[a-z]+/');

		$this->assertInstanceOf(Pattern::class, $pattern);
	}

	#[Test]
	public function it_is_a_constraint(): void
	{
		$pattern = new Pattern('/[a-z]+/');

		$this->assertInstanceOf(Constraint::class, $pattern);
	}

	#[Test]
	public function can_get_value(): void
	{
		$pattern = new Pattern('/[a-z]+/');

		$this->assertEquals('/[a-z]+/', $pattern->value);
	}

	#[Test]
	public function it_fails_if_value_does_not_match_pattern(): void
	{
		$pattern = new Pattern('/[a-z]+/');

		$result = $pattern->validate('123');

		$this->assertTrue($result->failed());
		$this->assertEquals('Expected value to match pattern: /[a-z]+/', $result->reason);
	}

	#[Test]
	public function it_passes_if_value_matches_pattern(): void
	{
		$pattern = new Pattern('/[a-z]+/');

		$result = $pattern->validate('abc');

		$this->assertTrue($result->passed());
	}
}
