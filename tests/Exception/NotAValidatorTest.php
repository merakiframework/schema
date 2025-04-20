<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;


use Meraki\Schema\Exception;
use Meraki\Schema\ExceptionTestCase;
use Meraki\Schema\Exception\NotAValidator;
use Meraki\Schema\Validator;
use Meraki\Schema\Validator\Dependent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use InvalidArgumentException;

#[Group('exception')]
#[CoversClass(NotAValidator::class)]
final class NotAValidatorTest extends ExceptionTestCase
{
	public function createException(): Exception
	{
		return new NotAValidator($this->mockDependentValidator(), 'foo');
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
		$fqcnOfValidatorInterface = Validator::class;
		$dependency = 'foo';

		$exception = new NotAValidator($validator, $dependency);

		$this->assertSame(
			"Validator {$fqcnOfValidator} declares a dependency on {$dependency}, but the dependency does not implement {$fqcnOfValidatorInterface}.",
			$exception->getMessage()
		);
	}

	protected function mockDependentValidator(): Dependent
	{
		return $this->createMock(Dependent::class);
	}
}
