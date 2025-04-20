<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('exception')]
#[CoversClass(Exception::class)]
abstract class ExceptionTestCase extends TestCase
{
	abstract protected function createException(): Exception;

	#[Test]
	public function it_is_an_exception(): void
	{
		$exception = $this->createException();

		$this->assertInstanceOf(Exception::class, $exception);
	}
}
