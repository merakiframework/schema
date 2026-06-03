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

		$this->assertFalse($result->failed(), 'Valid input must not produce failures');
		$this->assertTrue($result->passed());
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

		$this->assertFalse($result->failed(), 'Magic-accessor input must not produce failures');
		$this->assertTrue($result->passed());
	}

	#[Test]
	public function it_falls_back_to_null_for_absent_object_properties(): void
	{
		$schema = $this->createPersonSchema();

		// "name" is required but absent on the object -> should fail cleanly
		// (treated as null), not raise a warning about an undefined property.
		$result = $schema->validate(new \stdClass());

		$this->assertTrue($result->failed());
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

		$this->assertFalse($result->failed());
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
		$this->assertTrue($first->failed());

		// condition no longer holds -> phone must revert to optional -> no failure
		$second = $schema->validate(['has_phone' => false, 'phone' => null]);
		$this->assertFalse($second->failed());
	}

	private function createPersonSchema(): Facade
	{
		$schema = new Facade('create-person');
		$schema->addNameField('name')->minLengthOf(1)->maxLengthOf(255);

		return $schema;
	}
}
