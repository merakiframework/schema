<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\AggregatedValidationResult;
use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use Meraki\Schema\Rule;
use Error;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A schema cannot be changed from outside it, and neither can a result.
 *
 * ### The claim this is here to make true
 *
 * "One schema is built once and safely serves every request that follows" is the whole point of
 * 2.0, and it was enforced everywhere except the two places that actually hold a schema's contents.
 * Every *field* was sealed by the language — `readonly` is inherited both ways, so nothing below
 * `AtomicField` could be written to whether or not its author thought about it. The **set** holding
 * those fields was a plain mutable object behind a plain public property.
 *
 * That mattered because {@see Facade::copyForRequest()} deliberately hands every concurrent request
 * the *same* `Field\Set` instance, on the documented grounds that every way of changing one returns
 * a new set. That was true of `add()` and `replace()` and untrue of `mutableAdd()` — so a single
 * caller reaching in changed a definition every in-flight request was reading.
 *
 * Nothing in the library ever did it. The point is that nothing *can*, which is a different and
 * much stronger statement, and the only one worth making about concurrency.
 *
 * These assert the runtime half. The compile-time half is the visibility itself.
 */
#[Group('api-2.0')]
#[CoversNothing]
final class SealedSchemaTest extends TestCase
{
	private function schema(): Facade
	{
		$schema = new Facade('sealed');
		$schema->add($schema->createTextField('username'));

		return $schema;
	}

	#[Test]
	public function its_field_set_cannot_be_replaced_from_outside(): void
	{
		$schema = $this->schema();

		$this->expectException(Error::class);
		$this->expectExceptionMessage('Cannot modify private(set) property');

		// @phpstan-ignore property.readOnlyByPhpDocAssignNotInScope, assign.propertyReadOnly
		$schema->fields = new Field\Set();
	}

	#[Test]
	public function its_rule_set_cannot_be_replaced_from_outside(): void
	{
		$schema = $this->schema();

		$this->expectException(Error::class);
		$this->expectExceptionMessage('Cannot modify private(set) property');

		// @phpstan-ignore property.readOnlyByPhpDocAssignNotInScope, assign.propertyReadOnly
		$schema->rules = new Rule\Set();
	}

	#[Test]
	public function a_field_set_cannot_be_grown_in_place(): void
	{
		$schema = $this->schema();

		$this->expectException(Error::class);
		$this->expectExceptionMessage('Call to private method');

		// @phpstan-ignore method.notFound
		$schema->fields->mutableAdd($schema->createTextField('injected'));
	}

	#[Test]
	public function a_rule_set_cannot_be_grown_in_place(): void
	{
		$schema = $this->schema();

		$this->expectException(Error::class);
		$this->expectExceptionMessage('Call to private method');

		// @phpstan-ignore method.notFound
		$schema->rules->mutableAdd(
			$schema->when('username')->equals('admin')->thenMakeOptional('username')->build(),
		);
	}

	/**
	 * Adding through the public door still works, and still leaves the set it was handed alone.
	 */
	#[Test]
	public function adding_a_field_hands_back_a_new_set(): void
	{
		$schema = $this->schema();
		$original = $schema->fields;

		$schema->add($schema->createTextField('nickname'));

		$this->assertCount(1, $original, 'The set the schema used to hold was changed underneath it.');
		$this->assertCount(2, $schema->fields);
		$this->assertNotSame($original, $schema->fields);
	}

	/**
	 * A result is the record of what happened to one request, and nothing outside gets to revise it.
	 */
	#[Test]
	public function a_results_verdicts_cannot_be_rewritten(): void
	{
		$resolved = $this->schema()->validate((object) ['username' => 'kim'])->forField('username');

		$this->assertInstanceOf(AggregatedValidationResult::class, $resolved);

		$this->expectException(Error::class);
		$this->expectExceptionMessage('Cannot modify private(set) property');

		// @phpstan-ignore property.readOnlyByPhpDocAssignNotInScope, assign.propertyReadOnly
		$resolved->results = [];
	}

	/**
	 * A schema nobody put a field in cannot be validated, and says so.
	 *
	 * Refused rather than answered, because there is no honest answer. An empty result reports
	 * `allPassed()` as true — vacuously, nothing failed — and `status` as `Pending` — nothing was
	 * judged. Both are defensible and they contradict each other, so a caller gets whichever one
	 * they happened to ask for.
	 *
	 * A `LogicException` rather than a failed result: no input could make it right, so there is
	 * nothing to report to a user. It is a mistake in the code.
	 */
	#[Test]
	public function a_schema_with_no_fields_cannot_be_validated(): void
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('has no fields');

		(new Facade('nothing'))->validate((object) []);
	}

	#[Test]
	public function a_schema_with_no_fields_cannot_be_resolved_either(): void
	{
		// Resolving is the same question asked without checking, so it gets the same answer.
		$this->expectException(LogicException::class);

		(new Facade('nothing'))->resolve();
	}

	/**
	 * Building one is still fine — a schema is empty for as long as it takes to add the first
	 * field, and that is the ordinary way to write one.
	 */
	#[Test]
	public function an_empty_schema_may_still_be_built(): void
	{
		$schema = new Facade('nothing');

		$this->assertCount(0, $schema->fields);

		$schema->add($schema->createTextField('username'));

		$this->assertFalse($schema->validate((object) ['username' => 'kim'])->anyFailed());
	}

	/**
	 * Two requests through one schema do not meet, which is the reason for all of the above.
	 */
	#[Test]
	public function two_requests_through_one_schema_do_not_meet(): void
	{
		$schema = $this->schema();

		$kim = $schema->validate((object) ['username' => 'kim']);
		$sam = $schema->validate((object) ['username' => 'sam']);

		$this->assertSame('kim', $kim->forField('username')->value->text);
		$this->assertSame('sam', $sam->forField('username')->value->text);
		$this->assertCount(1, $schema->fields);
	}
}
