<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

use Meraki\Form\Constraint;
use Meraki\Form\Constraint\Factory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Factory::class)]
final class FactoryTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$factory = new Factory(Constraint\Required::class);

		$this->assertInstanceOf(Factory::class, $factory);
	}

	#[Test]
	public function it_registers_constraint(): void
	{
		$factory = new Factory(Constraint\Required::class);

		$factory->register(Constraint\Min::class);

		$this->assertTrue($factory->isRegistered(Constraint\Min::class));
	}

	#[Test]
	public function it_registers_multiple_constraints(): void
	{
		$factory = new Factory(Constraint\Required::class);

		$factory->register(Constraint\Min::class, Constraint\Max::class);

		$this->assertTrue($factory->isRegistered(Constraint\Min::class));
		$this->assertTrue($factory->isRegistered(Constraint\Max::class));
	}

	#[Test]
	public function it_throws_exception_when_registering_same_constraint_twice(): void
	{
		$factory = new Factory(Constraint\Required::class);

		$this->expectException(\InvalidArgumentException::class);

		$factory->register(Constraint\Required::class);
	}

	#[Test]
	public function it_throws_exception_when_constraint_is_not_registered(): void
	{
		$factory = new Factory(Constraint\Required::class);

		$this->expectException(\InvalidArgumentException::class);

		$min = $factory->create('min', 5);
	}
}
