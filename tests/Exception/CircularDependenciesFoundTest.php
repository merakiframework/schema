<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;


use Meraki\Schema\Exception;
use Meraki\Schema\ExceptionTestCase;
use Meraki\Schema\Exception\CircularDependenciesFound;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use LogicException;

#[Group('exception')]
#[CoversClass(CircularDependenciesFound::class)]
final class CircularDependenciesFoundTest extends ExceptionTestCase
{
	public function createException(): Exception
	{
		return new CircularDependenciesFound(['A', 'A']);
	}

	#[Test]
	public function it_is_a_logic_exception(): void
	{
		$exception = $this->createException();

		$this->assertInstanceOf(LogicException::class, $exception);
	}

	#[Test]
	public function it_generates_a_descriptive_message_for_self_referencing_cycles(): void
	{
		/** @var LogicException&Exception $exception */
		$exception = $this->createException();

		$this->assertSame(
			"Circular dependency detected: A → A.",
			$exception->getMessage()
		);
	}

	#[Test]
	public function it_generates_a_descriptive_message_for_multiple_cycles(): void
	{
		$exception = new CircularDependenciesFound(['A', 'B', 'C', 'A']);

		$this->assertSame(
			"Circular dependency detected: A → B → C → A.",
			$exception->getMessage()
		);
	}
}
