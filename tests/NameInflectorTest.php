<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

use Meraki\Form\Constraint;
use Meraki\Form\NameInflector;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NameInflector::class)]
final class NameInflectorTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$inflector = new NameInflector();

		$this->assertInstanceOf(NameInflector::class, $inflector);
	}

	#[Test]
	public function it_inflects_name_of_constraint(): void
	{
		$inflector = new NameInflector();
		$constraint = new Constraint\Required();

		$inflectedName = $inflector->inflectOn($constraint::class);

		$this->assertEquals('required', $inflectedName);
	}

	#[Test]
	public function it_inflects_name_of_constraint_class_with_trailing_underscore(): void
	{
		$inflector = new NameInflector();

		$inflectedName = $inflector->inflectOn(ConstraintFixture_::class);

		$this->assertEquals('constraint_fixture', $inflectedName);
	}
}

final class ConstraintFixture_ implements Constraint
{
	public function hasValueOf(mixed $value): bool
	{
		return true;
	}

	public function equals(Constraint $other): bool
	{
		return $this === $other;
	}

	public function validate(mixed $value): Constraint\ValidationResult
	{
		return Constraint\ValidationResult::pass();
	}
}
