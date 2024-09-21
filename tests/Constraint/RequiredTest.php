<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

use Meraki\Form\Constraint;
use Meraki\Form\Constraint\Required;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Required::class)]
final class RequiredTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$required = new Required();

		$this->assertInstanceOf(Required::class, $required);
	}

	#[Test]
	public function it_is_a_constraint(): void
	{
		$required = new Required();

		$this->assertInstanceOf(Constraint::class, $required);
	}

	#[Test]
	public function it_creates_a_new_instance_with_default_value(): void
	{
		$required = new Required();

		$this->assertTrue($required->value);
	}

	#[Test]
	public function it_creates_a_new_instance_with_custom_value(): void
	{
		$required = new Required(false);

		$this->assertFalse($required->value);
	}

	#[Test]
	public function it_validates_that_value_is_not_null(): void
	{
		$required = new Required();

		$result = $required->validate(null);

		$this->assertTrue($result->failed());
		$this->assertEquals('Expected a value to be provided.', $result->reason);
	}

	#[Test]
	public function it_validates_that_value_is_not_empty_string(): void
	{
		$required = new Required();

		$result = $required->validate('');

		$this->assertTrue($result->failed());
		$this->assertEquals('Expected a value to be provided.', $result->reason);
	}

	#[Test]
	public function it_passes_validation_when_value_is_not_null(): void
	{
		$required = new Required();

		$result = $required->validate('value');

		$this->assertTrue($result->passed());
	}

	#[Test]
	public function it_passes_validation_when_value_is_not_empty_string(): void
	{
		$required = new Required();

		$result = $required->validate(' ');

		$this->assertTrue($result->passed());
	}
}
