<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Exception\CheckTypeValidatorIsRequired;
use Meraki\Schema\Exception\CircularDependenciesFound;
use Meraki\Schema\Exception\NotAValidator;
use Meraki\Schema\Exception\ValidatorNotFound;
use Meraki\Schema\Field\Validator as FieldValidator;
use Meraki\Schema\Field\Type as FieldType;
use Meraki\Schema\Field\Name as FieldName;
use Meraki\Schema\Field\ValidationResult as FieldValidationResult;
use Meraki\Schema\Validator;
use Meraki\Schema\ValidatorName;
use Meraki\Schema\Field;
use Meraki\Schema\Validator\ValidationResult as ValidatorValidationResult;
use Meraki\Schema\Validator\CheckType;
use Meraki\Schema\Validator\Dependent;
use Meraki\Schema\Field\Type\EmailAddress as EmailAddressFieldType;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[Group('validation')]
#[CoversClass(FieldValidator::class)]
final class ValidatorTest extends TestCase
{
	#[Test]
	public function throws_if_checktype_validator_not_provided(): void
	{
		$this->expectExceptionObject(new CheckTypeValidatorIsRequired());

		$fieldValidator = new FieldValidator();
	}

	#[Test]
	public function it_skips_all_validators_if_field_is_optional_and_value_is_null(): void
	{
		$checkTypeValidator = $this->createCheckTypeValidator();
		$alwaysPassesIndependentValidator = new AlwaysPassesIndependentValidator();
		$alwaysFailsIndependentValidator = new AlwaysFailsIndependentValidator();
		$testField = $this->createTestField()->makeOptional()->input(null);
		$fieldValidator = new FieldValidator($checkTypeValidator, $alwaysPassesIndependentValidator, $alwaysFailsIndependentValidator);

		$results = $fieldValidator->validate($testField);

		$this->assertValidatorWasSkipped($results, $checkTypeValidator);
		$this->assertValidatorWasSkipped($results, $alwaysPassesIndependentValidator);
		$this->assertValidatorWasSkipped($results, $alwaysFailsIndependentValidator);
	}

	#[Test]
	public function it_skips_all_other_validators_if_type_check_validator_fails(): void
	{
		$checkTypeValidator = $this->createCheckTypeValidator(false);
		$alwaysPassesIndependentValidator = new AlwaysPassesIndependentValidator();
		$alwaysFailsIndependentValidator = new AlwaysFailsIndependentValidator();
		$testField = $this->createTestField()->input('foobar');
		$fieldValidator = new FieldValidator($checkTypeValidator, $alwaysPassesIndependentValidator, $alwaysFailsIndependentValidator);

		$results = $fieldValidator->validate($testField);

		$this->assertValidatorWasFailed($results, $checkTypeValidator);
		$this->assertValidatorWasSkipped($results, $alwaysPassesIndependentValidator);
		$this->assertValidatorWasSkipped($results, $alwaysFailsIndependentValidator);
	}

	#[Test]
	public function it_validates_non_dependent_validators(): void
	{
		$checkTypeValidator = $this->createCheckTypeValidator(true);
		$alwaysPassesIndependentValidator = new AlwaysPassesIndependentValidator();
		$alwaysFailsIndependentValidator = new AlwaysFailsIndependentValidator();
		$testField = $this->createTestField()->input('foobar');
		$fieldValidator = new FieldValidator($checkTypeValidator, $alwaysPassesIndependentValidator, $alwaysFailsIndependentValidator);

		$results = $fieldValidator->validate($testField);

		$this->assertValidatorWasPassed($results, $checkTypeValidator);
		$this->assertValidatorWasPassed($results, $alwaysPassesIndependentValidator);
		$this->assertValidatorWasFailed($results, $alwaysFailsIndependentValidator);
	}

	#[Test]
	public function dependent_validators_still_run_even_if_no_dependencies_defined(): void
	{
		$checkTypeValidator = $this->createCheckTypeValidator(true);
		$alwaysPassesIndependentValidator = new AlwaysPassesIndependentValidator();
		$noDependenciesValidator = new NoDependenciesValidator();
		$testField = $this->createTestField()->input('foobar');
		$fieldValidator = new FieldValidator($checkTypeValidator, $alwaysPassesIndependentValidator, $noDependenciesValidator);

		$results = $fieldValidator->validate($testField);

		$this->assertValidatorWasPassed($results, $checkTypeValidator);
		$this->assertValidatorWasPassed($results, $alwaysPassesIndependentValidator);
		$this->assertValidatorWasPassed($results, $noDependenciesValidator);
	}

	#[Test]
	public function it_validates_dependent_validators_if_dependency_validators_pass(): void
	{
		$checkTypeValidator = $this->createCheckTypeValidator(true);
		$alwaysPassesIndependentValidator = new AlwaysPassesIndependentValidator();
		$alwaysPassesDependentValidator = new AlwaysPassesDependentValidator($alwaysPassesIndependentValidator);
		$alwaysFailsDependentValidator = new AlwaysFailsDependentValidator($alwaysPassesIndependentValidator);
		$testField = $this->createTestField()->input('foobar');
		$fieldValidator = new FieldValidator($checkTypeValidator, $alwaysPassesIndependentValidator, $alwaysPassesDependentValidator, $alwaysFailsDependentValidator);

		$results = $fieldValidator->validate($testField);

		$this->assertValidatorWasPassed($results, $checkTypeValidator);
		$this->assertValidatorWasPassed($results, $alwaysPassesIndependentValidator);
		$this->assertValidatorWasPassed($results, $alwaysPassesDependentValidator);
		$this->assertValidatorWasFailed($results, $alwaysFailsDependentValidator);
	}

	#[Test]
	public function it_skips_dependent_validators_if_dependency_validators_fail(): void
	{
		$checkTypeValidator = $this->createCheckTypeValidator(true);
		$alwaysFailsIndependentValidator = new AlwaysFailsIndependentValidator();
		$alwaysPassesDependentValidator = new AlwaysPassesDependentValidator($alwaysFailsIndependentValidator);
		$alwaysFailsDependentValidator = new AlwaysFailsDependentValidator($alwaysFailsIndependentValidator);
		$testField = $this->createTestField()->input('foobar');
		$fieldValidator = new FieldValidator($checkTypeValidator, $alwaysFailsIndependentValidator, $alwaysPassesDependentValidator, $alwaysFailsDependentValidator);

		$results = $fieldValidator->validate($testField);

		$this->assertValidatorWasPassed($results, $checkTypeValidator);
		$this->assertValidatorWasFailed($results, $alwaysFailsIndependentValidator);
		$this->assertValidatorWasSkipped($results, $alwaysPassesDependentValidator);
		$this->assertValidatorWasSkipped($results, $alwaysFailsDependentValidator);
	}

	#[Test]
	public function it_throws_when_adding_validators_whose_dependencies_do_not_exist(): void
	{
		$checkTypeValidator = $this->createCheckTypeValidator(true);
		$nonExistentValidator = new DependsOnNonExistentValidator();

		$this->expectExceptionObject(new ValidatorNotFound($nonExistentValidator, 'non.existent.Validator'));

		$fieldValidator = new FieldValidator($checkTypeValidator, $nonExistentValidator);
	}

	#[Test]
	public function it_throws_when_adding_validators_whose_dependencies_are_not_validators(): void
	{
		$checkTypeValidator = $this->createCheckTypeValidator(true);
		$dependencyNotAValidatorValidator = new DependencyNotAValidatorValidator();

		$this->expectExceptionObject(new NotAValidator($dependencyNotAValidatorValidator, get_class(new \stdClass())));

		$fieldValidator = new FieldValidator($checkTypeValidator, $dependencyNotAValidatorValidator);
	}

	#[Test]
	public function it_throws_when_resolving_validators_with_circular_dependencies(): void
	{
		$checkTypeValidator = $this->createCheckTypeValidator(true);
		$aValidator = new AValidator();
		$bValidator = new BValidator();
		$testField = $this->createTestField()->input('foobar');
		$fieldValidator = new FieldValidator($checkTypeValidator, $aValidator, $bValidator);

		$this->expectExceptionObject(new CircularDependenciesFound([$aValidator::class, $bValidator::class, $aValidator::class]));

		$results = $fieldValidator->validate($testField);
	}

	#[Test]
	public function it_throws_when_resolving_validators_that_are_self_dependent(): void
	{
		$checkTypeValidator = $this->createCheckTypeValidator(true);
		$selfDependentValidator = new SelfDependentValidator();
		$testField = $this->createTestField()->input('foobar');
		$fieldValidator = new FieldValidator($checkTypeValidator, $selfDependentValidator);

		$this->expectExceptionObject(new CircularDependenciesFound([SelfDependentValidator::class, SelfDependentValidator::class]));

		$results = $fieldValidator->validate($testField);
	}

	// --- Custom Asserters ---

	public function assertValidatorWasSkipped(FieldValidationResult $result, Validator $validator): void
	{
		$fqcn = $validator::class;
		$this->assertEquals(
			1,
			$result->getSkipped()
				->filter(fn(ValidatorValidationResult $r): bool => $r->validator === $validator)
				->count(),
			"Validator '{$fqcn}' was not skipped."
		);
	}

	public function assertValidatorWasPassed(FieldValidationResult $result, Validator $validator): void
	{
		$fqcn = $validator::class;
		$this->assertEquals(
			1,
			$result->getPassed()
				->filter(fn(ValidatorValidationResult $r): bool => $r->validator === $validator)
				->count(),
			"Validator '{$fqcn}' was not passed."
		);
	}

	public function assertValidatorWasFailed(FieldValidationResult $result, Validator $validator): void
	{
		$fqcn = $validator::class;
		$this->assertEquals(
			1,
			$result->getFailed()
				->filter(fn(ValidatorValidationResult $r): bool => $r->validator === $validator)
				->count(),
			"Validator '{$fqcn}' was not failed."
		);
	}

	// --- Creators/Factories ---

	protected function createTestField(): Field
	{
		return new Field(new EmailAddressFieldType(), new FieldName('test_field'));
	}

	private function createCheckTypeValidator(bool $returnValue = true): Validator
	{
		$fakeFieldType = new class($returnValue) implements FieldType {
			public readonly string $name;
			public function __construct(private bool $shouldAccept) { $this->name = 'type_mock'; }
			public function accepts(mixed $value): bool { return $this->shouldAccept; }
			public function getValidator(): CheckType { return new CheckType($this); }
		};

		return new CheckType($fakeFieldType);
	}
}

final class AValidator implements Dependent {
	public readonly ValidatorName $name;
	public function __construct() { $this->name = new ValidatorName('a'); }
	public function validate(Field $field): bool { return true; }
	public function dependsOn(): array { return [BValidator::class]; }
}

final class BValidator implements Dependent {
	public readonly ValidatorName $name;
	public function __construct() { $this->name = new ValidatorName('b'); }
	public function validate(Field $field): bool { return true; }
	public function dependsOn(): array { return [AValidator::class]; }
}

final class SelfDependentValidator implements Dependent {
	public readonly ValidatorName $name;
	public function __construct() { $this->name = new ValidatorName('self_dependent'); }
	public function validate(Field $field): bool { return true; }
	public function dependsOn(): array { return [SelfDependentValidator::class]; }
}

final class AlwaysFailsIndependentValidator implements Validator {
	public readonly ValidatorName $name;
	public function __construct() { $this->name = new ValidatorName('always_fails_independent'); }
	public function validate(Field $field): bool { return false; }
}

final class AlwaysPassesIndependentValidator implements Validator {
	public readonly ValidatorName $name;
	public function __construct() { $this->name = new ValidatorName('always_passes_independent'); }
	public function validate(Field $field): bool { return true; }
}

final class AlwaysFailsDependentValidator implements Dependent {
	public readonly ValidatorName $name;
	public function __construct(private Validator $dependency) { $this->name = new ValidatorName('always_fails_dependent'); }
	public function validate(Field $field): bool { return false; }
	public function dependsOn(): array { return [ $this->dependency::class ]; }
}

final class AlwaysPassesDependentValidator implements Dependent {
	public readonly ValidatorName $name;
	public function __construct(private Validator $dependency) { $this->name = new ValidatorName('always_passes_dependent'); }
	public function validate(Field $field): bool { return true; }
	public function dependsOn(): array { return [ $this->dependency::class ]; }
}

final class NoDependenciesValidator implements Dependent {
	public readonly ValidatorName $name;
	public function __construct() { $this->name = new ValidatorName('no_dependencies'); }
	public function validate(Field $field): bool { return true; }
	public function dependsOn(): array { return []; }
}

final class DependsOnNonExistentValidator implements Dependent {
	public readonly ValidatorName $name;
	public function __construct() { $this->name = new ValidatorName('depends_on_non_existent'); }
	public function validate(Field $field): bool { return true; }
	public function dependsOn(): array { return ['non.existent.Validator']; }
}

final class DependencyNotAValidatorValidator implements Dependent {
	public readonly ValidatorName $name;
	public function __construct() { $this->name = new ValidatorName('dependency_not_a_validator'); }
	public function validate(Field $field): bool { return true; }
	public function dependsOn(): array { return [get_class(new \stdClass())]; }
}
