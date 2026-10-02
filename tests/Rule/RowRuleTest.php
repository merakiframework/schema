<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Definition;
use Meraki\Schema\Field;
use Meraki\Schema\PropertyScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * A rule can apply to each row of a collection on its own.
 *
 * The question a repeatable section actually asks, and the one a schema rule cannot: "if *this*
 * attendee is a child, require *this* attendee's guardian" is about one row at a time, where a rule
 * naming the collection could only ever speak about the list as a whole.
 *
 * It needs no new scope vocabulary, which is the part worth noticing. A row rule's field set *is*
 * the template, so `$age->when()` produces the ordinary `#/fields/age/value` and inside a row that
 * is exactly what it means — {@see Application} runs it unchanged.
 */
#[Group('rule')]
#[CoversClass(Application::class)]
#[CoversClass(Field\Collection::class)]
final class RowRuleTest extends TestCase
{
	/** @return array{Definition, Field\Collection} */
	private function workshop(): array
	{
		$schema = new Definition('workshop');
		$age = $schema->createNumberField('age');
		$guardian = $schema->createTextField('guardian')->makeOptional();

		$attendees = $schema->createCollectionField('attendees', $age, $guardian)
			->forEachRow($age->when()->isLessThan(18)->then($guardian->makeRequired()));

		$schema->add($attendees);

		return [$schema, $attendees];
	}

	private function threeAttendees(): object
	{
		return (object) ['attendees' => [
			'child' => (object) ['age' => '9', 'guardian' => null],
			'adult' => (object) ['age' => '34', 'guardian' => null],
			'teen' => (object) ['age' => '15', 'guardian' => 'Sam Okafor'],
		]];
	}

	#[Test]
	public function a_row_rule_changes_only_the_row_it_matched(): void
	{
		[$schema] = $this->workshop();
		$rows = $schema->validate($this->threeAttendees())->forField('attendees');

		$this->assertFalse($rows->itemAt('child')->forField('guardian')->field->optional, 'a child needs one');
		$this->assertTrue($rows->itemAt('adult')->forField('guardian')->field->optional, 'an adult does not');
		$this->assertFalse($rows->itemAt('teen')->forField('guardian')->field->optional, 'a teenager does');
	}

	#[Test]
	public function the_row_that_matched_and_did_not_answer_is_the_one_that_fails(): void
	{
		[$schema] = $this->workshop();
		$rows = $schema->validate($this->threeAttendees())->forField('attendees');

		$this->assertTrue($rows->itemAt('child')->forField('guardian')->anyFailed(), 'required and empty');
		$this->assertFalse($rows->itemAt('adult')->forField('guardian')->anyFailed(), 'optional and empty');
		$this->assertFalse($rows->itemAt('teen')->forField('guardian')->anyFailed(), 'required and answered');

		// And the failure reaches the collection, and the schema with it.
		$this->assertTrue($rows->anyFailed());
	}

	#[Test]
	public function the_template_is_never_written_to(): void
	{
		// Each row folds over its *own copy*, which is what has always let one template validate
		// every row. If a rule could reach the shared template, the first child in a list would
		// make a guardian required for everybody after them.
		[$schema, $attendees] = $this->workshop();

		$schema->validate($this->threeAttendees());

		$guardian = $attendees->template[1];

		$this->assertSame('guardian', (string) $guardian->name);
		$this->assertTrue($guardian->optional, 'the authored template still says optional');
	}

	#[Test]
	public function a_row_rule_records_what_it_did_to_that_row(): void
	{
		[$schema] = $this->workshop();
		$rows = $schema->validate($this->threeAttendees())->forField('attendees');

		$this->assertCount(1, $rows->itemAt('child')->forField('guardian')->appliedOutcomes);
		$this->assertSame([], $rows->itemAt('adult')->forField('guardian')->appliedOutcomes);
	}

	#[Test]
	public function a_row_rule_may_not_reach_outside_the_template(): void
	{
		// It runs against a copy of the template and nothing else, so a field outside it could
		// never resolve — and would fail on a user's request rather than where the rule was written.
		$schema = new Definition('workshop');
		$age = $schema->createNumberField('age');
		$guardian = $schema->createTextField('guardian')->makeOptional();
		$outside = $schema->createTextField('note');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/has no field \'note\' in its template/');

		$schema->createCollectionField('attendees', $age, $guardian)
			->forEachRow($outside->when()->equals('x')->then($guardian->makeRequired()));
	}

	/**
	 * A row rule is held to everything a schema rule is held to.
	 *
	 * It was not. `addRule()` ran two authoring checks and `forEachRow()` ran neither, so a row
	 * rule could be written two ways a schema rule could not — and both failed quietly, which is
	 * the whole reason those checks exist. They are shared now; see {@see Guards}.
	 */
	#[Test]
	public function a_row_rule_cannot_compare_against_a_value_the_field_could_never_hold(): void
	{
		// Dead on arrival: a number is never the string 'eighteen', so the rule could not fire,
		// and a rule that never fires looks exactly like one whose condition never held.
		$schema = new Definition('workshop');
		$age = $schema->createNumberField('age');
		$guardian = $schema->createTextField('guardian')->makeOptional();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/could never be true/');

		$schema->createCollectionField('attendees', $age, $guardian)
			->forEachRow($age->when()->equals('eighteen')->then($guardian->makeRequired()));
	}

	#[Test]
	public function a_row_rule_cannot_address_a_property_nothing_has(): void
	{
		// This used to be accepted and then throw InvalidScope on whichever request first
		// reached it — a 500 for the submitter, from a typo the author made.
		$schema = new Definition('workshop');
		$age = $schema->createNumberField('age');
		$guardian = $schema->createTextField('guardian')->makeOptional();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/cannot address/');

		$schema->createCollectionField('attendees', $age, $guardian)->forEachRow(
			$schema->when(PropertyScope::of('age', 'nonesuch'))->equals(1)->then($guardian->makeRequired()),
		);
	}

	#[Test]
	public function adding_a_row_rule_leaves_the_original_collection_alone(): void
	{
		// A wither, like every other configuration method on a field.
		$schema = new Definition('workshop');
		$age = $schema->createNumberField('age');
		$guardian = $schema->createTextField('guardian')->makeOptional();

		$plain = $schema->createCollectionField('attendees', $age, $guardian);
		$ruled = $plain->forEachRow($age->when()->isLessThan(18)->then($guardian->makeRequired()));

		$this->assertCount(0, $plain->rowRules);
		$this->assertCount(1, $ruled->rowRules);
	}

	#[Test]
	public function a_row_rule_can_ignore_a_field_in_one_row_only(): void
	{
		// Ignoring is about a request rather than a definition, so it has to be a per-row fact too.
		$schema = new Definition('order');
		$kind = $schema->createTextField('kind');
		$address = $schema->createTextField('address')->makeOptional();

		$lines = $schema->createCollectionField('lines', $kind, $address)
			->forEachRow($kind->when()->equals('digital')->thenIgnore($address));

		$schema->add($lines);

		$rows = $schema->validate((object) ['lines' => [
			'download' => (object) ['kind' => 'digital', 'address' => '1 Test St'],
			'parcel' => (object) ['kind' => 'physical', 'address' => '2 Test St'],
		]])->forField('lines');

		$this->assertNull($rows->itemAt('download')->forField('address')->value);
		$this->assertSame('2 Test St', (string) $rows->itemAt('parcel')->forField('address')->value);
	}
}
