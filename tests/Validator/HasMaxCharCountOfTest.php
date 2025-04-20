<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\Validator;
use Meraki\Schema\ValidatorName;
use Meraki\Schema\ValidatorTestCase;
use Meraki\Schema\Validator\HasMaxCharCountOf;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('validation')]
#[CoversClass(HasMaxCharCountOf::class)]
final class HasMaxCharCountOfTest extends ValidatorTestCase
{
	public function createValidator(): Validator
	{
		return new HasMaxCharCountOf(5);
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$validator = $this->createValidator();

		$name = $validator->name;

		$this->assertTrue($name->equals(new ValidatorName('max')));
	}
}
