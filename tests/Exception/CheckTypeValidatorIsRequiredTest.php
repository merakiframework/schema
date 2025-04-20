<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;


use Meraki\Schema\Exception;
use Meraki\Schema\ExceptionTestCase;
use Meraki\Schema\Exception\CheckTypeValidatorIsRequired;
use Meraki\Schema\Validator\CheckType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use InvalidArgumentException;

#[Group('exception')]
#[CoversClass(CheckTypeValidatorIsRequired::class)]
final class CheckTypeValidatorIsRequiredTest extends ExceptionTestCase
{
	public function createException(): Exception
	{
		return new CheckTypeValidatorIsRequired();
	}

	#[Test]
	public function it_is_an_invalid_argument_exception(): void
	{
		$exception = $this->createException();

		$this->assertInstanceOf(InvalidArgumentException::class, $exception);
	}

	#[Test]
	public function it_generates_a_descriptive_message(): void
	{
		$exception = new CheckTypeValidatorIsRequired();
		$fqcn = CheckType::class;

		$this->assertSame(
			"Validator '{$fqcn}' is required.",
			$exception->getMessage()
		);
	}
}
