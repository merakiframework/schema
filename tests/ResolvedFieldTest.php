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
		return (new Field\Text(new Property\Name($name)))->minLengthOf(3);
	}

	private function resolved(mixed $given, mixed $value, ConstraintValidationResult ...$results): ResolvedField
	{
		return new ResolvedField($this->field(), $given, $value, [], ...$results);
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
		$this->assertSame(ValidationStatus::Failed, $field->get('min')?->status);
		$this->assertSame(ValidationStatus::Passed, $field->get('type')?->status);
		$this->assertNull($field->get('nonexistent'));
	}

	#[Test]
	public function a_constraint_cannot_be_reported_twice(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->resolved('ab', 'ab', ConstraintValidationResult::pass('min'), ConstraintValidationResult::fail('min'));
	}

	#[Test]
	public function transformed_is_the_typed_value_once_it_passes(): void
	{
		$field = $this->resolved('abc', 'abc', ConstraintValidationResult::pass('type'), ConstraintValidationResult::pass('min'));

		$this->assertSame('abc', $field->transformed);
	}

	#[Test]
	public function transformed_is_null_when_the_field_was_skipped(): void
	{
		// Nothing was supplied and nothing was required, so there is legitimately no value.
		$field = $this->resolved(null, null, ConstraintValidationResult::skip('type'), ConstraintValidationResult::skip('min'));

		$this->assertNull($field->transformed);
	}

	#[Test]
	public function transformed_throws_when_the_field_failed(): void
	{
		// Returning null here would hide the difference between absent and wrong.
		$field = $this->resolved('ab', 'ab', ConstraintValidationResult::pass('type'), ConstraintValidationResult::fail('min'));

		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('"username" failed validation (min)');

		$field->transformed;
	}

	#[Test]
	public function transformed_throws_before_validation_has_run(): void
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('has not been validated');

		$this->resolved('abc', 'abc')->transformed;
	}

	#[Test]
	public function it_records_which_rules_altered_the_field(): void
	{
		$rule = new Rule(new Rule\Condition\AllOf(), []);
		$outcome = new Outcome\MakeOptional('#/fields/username');
		$field = new ResolvedField($this->field(), null, null, [new AppliedOutcome($rule, $outcome)]);

		$this->assertTrue($field->wasAlteredByRule());
		$this->assertTrue($field->appliedOutcomes[0]->is(Outcome\MakeOptional::class));
		$this->assertFalse($field->appliedOutcomes[0]->is(Outcome\_Require::class));
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
		$resolved = new ResolvedField($field, 'ab', 'ab', [], ConstraintValidationResult::pass('type'), ConstraintValidationResult::fail('min'));

		$failed = $resolved->getFailed();

		$this->assertSame($field, $failed->field);
		$this->assertSame('ab', $failed->given);
		$this->assertCount(1, $failed);
	}
}
