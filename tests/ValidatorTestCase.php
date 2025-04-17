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
	public function depends_on_returns_list_of_validator_class_names(): void
	{
		$validator = $this->createValidator();
		$fqcn = $validator::class;
		$dependencies = $validator->dependsOn();

		if ($dependencies === []) {
			$this->markTestSkipped("Validator '{$fqcn}' has no dependencies.");
		}

		foreach ($dependencies as $dependency) {
			$this->assertTrue(
				is_subclass_of($dependency, Validator::class),
				"'{$dependency}' is not a subclass of Validator"
			);
		}
	}

	protected function createFieldMock(): Field
	{
		return $this->createMock(Field::class);
	}
}
