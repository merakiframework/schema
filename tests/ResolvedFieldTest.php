<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\Rule\AppliedOutcome;
use Meraki\Schema\Rule\Outcome;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('validation')]
#[CoversClass(ResolvedField::class)]
final class ResolvedFieldTest extends TestCase
{
	private function field(string $name = 'username'): Field
	{
		return (new Field\Text(new FieldName($name)))->minLengthOf(3);
	}

	private function resolved(mixed $given, mixed $value, ConstraintValidationResult ...$results): ResolvedField
	{
		return new ResolvedField($this->field(), $given, $value, [], ValueSource::Submitted, null, ...$results);
	}

	#[Test]
	public function it_keeps_the_submitted_value_apart_from_the_validated_one(): void
	{
		// Re-rendering a rejected form must echo back what was typed, not a default that
		// replaced it.
		$field = new ResolvedField($this->field(), null, 'from-default');

		$this->assertNull($field->given);
		$this->assertSame('from-default', $field->value);
	}

	#[Test]
	public function a_resolved_but_unvalidated_field_is_pending(): void
	{
		// Rendering a form happens before anything is checked; Pending is that state.
		$this->assertSame(ValidationStatus::Pending, $this->resolved('ab', 'ab')->status);
	}

	#[Test]
	public function it_reports_its_own_constraint_outcomes(): void
	{
		$field = $this->resolved('ab', 'ab', ConstraintValidationResult::pass('type'), ConstraintValidationResult::fail('min'));

		$this->assertTrue($field->anyFailed());
		$this->assertSame(ValidationStatus::Failed, $field->status);
		$this->assertSame(ValidationStatus::Failed, $field->forConstraint('min')?->status);
		$this->assertSame(ValidationStatus::Passed, $field->forConstraint('type')?->status);
		$this->assertNull($field->forConstraint('nonexistent'));
	}

	#[Test]
	public function a_constraint_cannot_be_reported_twice(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->resolved('ab', 'ab', ConstraintValidationResult::pass('min'), ConstraintValidationResult::fail('min'));
	}

	#[Test]
	public function the_value_is_what_was_validated(): void
	{
		$field = $this->resolved('abc', 'abc', ConstraintValidationResult::pass('type'), ConstraintValidationResult::pass('min'));

		$this->assertSame('abc', $field->value);
	}

	#[Test]
	public function the_value_is_null_when_nothing_was_supplied(): void
	{
		// Nothing was supplied and nothing was required, so there is legitimately no value.
		$field = $this->resolved(null, null, ConstraintValidationResult::skip('type'), ConstraintValidationResult::skip('min'));

		$this->assertNull($field->value);
	}

	#[Test]
	public function the_value_is_readable_whatever_the_verdict(): void
	{
		// It used to throw on a failure, which forced check-before-read ceremony on every consumer.
		// Now a rejected field still hands back what it was judging, so a form redrawing it has
		// something to show — and $given has the untouched submission besides.
		$failed = $this->resolved('ab', 'ab', ConstraintValidationResult::pass('type'), ConstraintValidationResult::fail('min'));
		$pending = $this->resolved('abc', 'abc');

		$this->assertSame('ab', $failed->value);
		$this->assertSame('abc', $pending->value, 'readable before validation has run, too');
	}

	#[Test]
	public function it_records_which_rules_altered_the_field(): void
	{
		$rule = new Rule(new Rule\Condition\AllOf(), []);
		$outcome = new Outcome\Ignore('#/fields/username');
		$field = new ResolvedField($this->field(), null, null, [new AppliedOutcome($rule, $outcome)]);

		$this->assertTrue($field->wasAlteredByRule());
		$this->assertTrue($field->appliedOutcomes[0]->is(Outcome\Ignore::class));
		$this->assertFalse($field->appliedOutcomes[0]->is(Outcome\Reconfigure::class));
	}

	#[Test]
	public function a_field_the_author_wrote_that_way_was_not_altered_by_a_rule(): void
	{
		$this->assertFalse($this->resolved('abc', 'abc')->wasAlteredByRule());
	}

	#[Test]
	public function results_can_be_attached_after_resolution(): void
	{
		// Resolution and validation are two steps, because a form is rendered before it is
		// submitted.
		$pending = $this->resolved('abc', 'abc');
		$checked = $pending->withResults(ConstraintValidationResult::pass('type'));

		$this->assertSame(ValidationStatus::Pending, $pending->status);
		$this->assertSame(ValidationStatus::Passed, $checked->status);
		$this->assertSame($pending->given, $checked->given);
		$this->assertSame($pending->value, $checked->value);
	}

	#[Test]
	public function filtering_keeps_the_field_and_its_values(): void
	{
		// getFailed() and friends clone; the identity of the field must survive that.
		$field = $this->field();
		$resolved = new ResolvedField($field, 'ab', 'ab', [], ValueSource::Submitted, null, ConstraintValidationResult::pass('type'), ConstraintValidationResult::fail('min'));

		$failed = $resolved->getFailed();

		$this->assertSame($field, $failed->field);
		$this->assertSame('ab', $failed->given);
		$this->assertCount(1, $failed);
	}
}
