<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\Dependent;
use Meraki\Schema\Validator;
use Meraki\Schema\ValidatorTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[Group('validation')]
#[CoversClass(Dependent::class)]
abstract class DependentTestCase extends ValidatorTestCase
{
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
}
