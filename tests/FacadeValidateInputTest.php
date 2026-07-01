<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Facade;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\SchemaValidationResult;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('facade')]
#[Group('validation')]
#[CoversClass(Facade::class)]
#[CoversClass(SchemaValidationResult::class)]
final class FacadeValidateInputTest extends TestCase
{
	#[Test]
	public function it_reads_input_from_plain_public_properties(): void
	{
		$schema = $this->createPersonSchema();

		$input = new \stdClass();
		$input->name = 'John Smith';

		$result = $schema->validate($input);

		$this->assertFalse($result->anyFailed(), 'Valid input must not produce failures');
		$this->assertTrue($result->allPassed());
	}

	#[Test]
	public function it_reads_input_from_objects_exposing_values_via_magic_get(): void
	{
		$schema = $this->createPersonSchema();

		// A value object that exposes its data through __get() but, crucially,
		// does NOT define __isset(). get_object_vars() and isset()/?? based
		// reads both fail to see this data; only direct property access works.
		$input = new class(['name' => 'John Smith']) {
			public function __construct(private array $data)
			{
			}

			public function __get(string $name): mixed
			{
				return $this->data[$name] ?? null;
			}
		};

		$result = $schema->validate($input);

		$this->assertFalse($result->anyFailed(), 'Magic-accessor input must not produce failures');
		$this->assertTrue($result->allPassed());
	}

	#[Test]
	public function it_falls_back_to_null_for_absent_object_properties(): void
	{
		$schema = $this->createPersonSchema();

		// "name" is required but absent on the object -> should fail cleanly
		// (treated as null), not raise a warning about an undefined property.
		$result = $schema->validate(new \stdClass());

		$this->assertTrue($result->anyFailed());
	}

	#[Test]
	public function it_passes_when_some_fields_pass_and_optional_fields_are_skipped(): void
	{
		// Mirrors the real-world schema: a required field plus an omitted
		// optional one. This produces a mix of passed + skipped results, which
		// SchemaValidationResult must report as Passed rather than crashing.
		$schema = new Facade('create-person');
		$schema->addNameField('name')->minLengthOf(1)->maxLengthOf(255);
		$schema->addDateField('dateOfBirth')->from('1900-01-01')->to('2010-01-01')->makeOptional();

		$input = new class(['name' => 'John Smith']) {
			public function __construct(private array $data)
			{
			}

			public function __get(string $name): mixed
			{
				return $this->data[$name] ?? null;
			}
		};

		$result = $schema->validate($input);

		$this->assertFalse($result->anyFailed());
		$this->assertSame(ValidationStatus::Passed, $result->status);
	}

	#[Test]
	public function it_resets_conditional_rule_effects_between_validations(): void
	{
		$schema = new Facade('contact');
		$schema->addBooleanField('has_phone');
		$schema->addTextField('phone')->makeOptional();
		$schema->whenAllMatch(
			fn($rule) => $rule->whenEquals('#/fields/has_phone/value', true)->thenRequire('#/fields/phone')
		);

		// condition holds -> phone becomes required -> missing phone fails
		$first = $schema->validate(['has_phone' => true, 'phone' => null]);
		$this->assertTrue($first->anyFailed());

		// condition no longer holds -> phone must revert to optional -> no failure
		$second = $schema->validate(['has_phone' => false, 'phone' => null]);
		$this->assertFalse($second->anyFailed());
	}

	#[Test]
	public function declarative_rule_can_ignore_a_field_when_a_not_equals_condition_holds(): void
	{
		$schema = new Facade('booking');
		$schema->addEnumField('vehicle', ['school', 'own']);
		$schema->addEnumField('transmission', ['automatic', 'manual'])->makeOptional();
		// transmission only matters for a school vehicle: otherwise make it optional AND ignore
		// its input, so a stale/invalid value never fails.
		$schema->whenAllMatch(
			fn($rule) => $rule->whenNotEquals('#/fields/vehicle/value', 'school')
				->thenMakeOptional('#/fields/transmission')
				->thenIgnore('#/fields/transmission')
		);

		// own vehicle -> an invalid transmission is ignored (does not fail)
		$this->assertFalse($schema->validate(['vehicle' => 'own', 'transmission' => 'bogus'])->anyFailed());
		// school vehicle -> transmission is validated again
		$this->assertTrue($schema->validate(['vehicle' => 'school', 'transmission' => 'bogus'])->anyFailed());
	}

	private function createPersonSchema(): Facade
	{
		$schema = new Facade('create-person');
		$schema->addNameField('name')->minLengthOf(1)->maxLengthOf(255);

		return $schema;
	}
}
