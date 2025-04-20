<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;


use Meraki\Schema\Exception;
use Meraki\Schema\ExceptionTestCase;
use Meraki\Schema\Exception\ValidatorNotFound;
use Meraki\Schema\Validator\Dependent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use InvalidArgumentException;

#[Group('exception')]
#[CoversClass(ValidatorNotFound::class)]
final class ValidatorNotFoundTest extends ExceptionTestCase
{
	public function createException(): Exception
	{
		return new ValidatorNotFound($this->mockDependentValidator(), 'foo');
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
		$validator = $this->mockDependentValidator();
		$fqcnOfValidator = $validator::class;
		$dependency = 'foo';

		$exception = new ValidatorNotFound($validator, $dependency);

		$this->assertSame(
			"Validator {$fqcnOfValidator} declares a dependency on {$dependency}, but the dependency could not be found.",
			$exception->getMessage()
		);
	}

	protected function mockDependentValidator(): Dependent
	{
		return $this->createMock(Dependent::class);
	}
}
