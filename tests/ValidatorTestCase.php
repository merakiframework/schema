<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Validator;
use Meraki\Schema\ValidatorName;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[Group('validation')]
#[CoversClass(Validator::class)]
abstract class ValidatorTestCase extends TestCase
{
	/**
	 * @template T of Validator
	 */
	abstract public function createValidator(): Validator;

	#[Test]
	public function is_a_validator(): void
	{
		$validator = $this->createValidator();

		$this->assertInstanceOf(Validator::class, $validator);
	}

	#[Test]
	public function has_a_name(): void
	{
		$validator = $this->createValidator();
		$name = $validator->name;

		$this->assertInstanceOf(ValidatorName::class, $name);
	}

	#[Test]
	abstract public function it_has_the_correct_name(): void;

	protected function mockField(): Field
	{
		return $this->createMock(Field::class);
	}
}
