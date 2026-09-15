<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Collection\Value as CollectionValue;
use Meraki\Schema\Field\Number\Value as NumberValue;
use Brick\Math\BigDecimal;
use Meraki\Schema\Field\Collection;
use Meraki\Schema\Field\Collection\Item;
use Meraki\Schema\Field\Collection\Result;
use Meraki\Schema\Field\Money;
use Meraki\Schema\Field\Number;
use Meraki\Schema\Field\Text;
use Meraki\Schema\Field\EmailAddress;
use Meraki\Schema\Field\Address;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldResult;
use Meraki\Schema\ValidationStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(Collection::class)]
#[CoversClass(Item::class)]
#[CoversClass(Result::class)]
final class CollectionTest extends TestCase
{
	/** A line item: a name and a quantity of at least ten. */
	private function lines(): Collection
	{
		return new Collection(
			new FieldName('lines'),
			new Text(new FieldName('sku')),
			(new Number(new FieldName('qty')))->minValueOf(10),
		);
	}

	/** @return list<array<string, mixed>> */
	private static function items(int ...$quantities): array
	{
		return array_map(static fn(int $qty): array => ['sku' => 'A1', 'qty' => $qty], $quantities);
	}

	// ── the list itself ────────────────────────────────────────────────────────────────────

	// ── an array is a list; an object is a record ──────────────────────────────────────────

	#[Test]
	public function a_record_is_not_a_list(): void
	{
		// The rule the whole input model turns on. A Money-shaped payload handed to a collection
		// used to be read as a one-item list of its parts; now it is refused, because an object
		// means "one thing with named parts" everywhere.
		$this->assertShapeFailed($this->lines()->validate((object)['sku' => 'A1', 'qty' => 50]));
	}

	#[Test]
	public function rows_may_be_named_instead_of_numbered(): void
	{
		$result = $this->lines()->validate([
			'first' => (object)['sku' => 'A1', 'qty' => 50],
			'second' => (object)['sku' => 'B2', 'qty' => 10],
		]);

		$this->assertShapePassed($result);
		$this->assertSame(['first', 'second'], array_values(array_map(static fn(Item $i): string|int => $i->key, $result->items)));

		// And the name is how a row is addressed, so a failure can be reported against something
		// a person recognises rather than an ordinal.
		$this->assertSame('B2', $result->itemAt('second')->forField('sku')->value->text);
	}

	#[Test]
	public function a_plain_list_still_numbers_itself(): void
	{
		$result = $this->lines()->validate([(object)['sku' => 'A1', 'qty' => 50]]);

		$this->assertSame([0], array_values(array_map(static fn(Item $i): string|int => $i->key, $result->items)));
		$this->assertSame('A1', $result->itemAt(0)->forField('sku')->value->text);
	}

	#[Test]
	public function keys_must_all_be_of_one_kind(): void
	{
		// Half-named is refused rather than repaired. PHP numbers whatever was not named, so the
		// unnamed rows end up keyed *around* the named ones and which row `0` means depends on
		// how many names came before it — a failure reported against a key that moves is worse
		// than no answer.
		$this->assertShapeFailed($this->lines()->validate([
			'first' => (object)['sku' => 'A1', 'qty' => 50],
			(object)['sku' => 'B2', 'qty' => 10],
		]));
	}

	#[Test]
	public function a_row_that_is_not_a_record_fails_on_its_own_terms(): void
	{
		// Kept in place rather than dropped, so the list does not silently shorten and the
		// position of everything after it does not shift.
		$result = $this->lines()->validate([
			(object)['sku' => 'A1', 'qty' => 50],
			'not-a-row',
		]);

		$this->assertShapePassed($result);
		$this->assertCount(2, $result->items);
		$this->assertTrue($result->itemAt(1)->anyFailed());
	}

	#[Test]
	public function its_value_is_the_rows_as_the_template_resolved_them(): void
	{
		// `$value` means the same thing here as on every other field: what was actually
		// validated, not what arrived. So a row is a record object — the shape its input had —
		// and each of its values has been through the template field's own parse().
		//
		// It used to be the submitted input reshaped to the template's key names: rows as
		// associative arrays, values untouched. That made a collection the one field whose
		// `$value` was raw, and the one place an array meant "named parts".
		$result = $this->lines()->validate([(object)['sku' => 'A1', 'qty' => 50, 'ignored' => 'x']]);

		// The value is a Collection\Value, not a bare array — every field parses to a value object
		// this library defines, and a collection is not the exception it looks like it should be.
		$this->assertInstanceOf(CollectionValue::class, $result->value);
		$this->assertCount(1, $result->value);

		$row = $result->value->rows[0];

		$this->assertIsObject($row);
		$this->assertSame('A1', $row->sku->text);

		// Every leaf is a value object, including the ones whose underlying type is a scalar —
		// which is what lets `unique` compare two rows without knowing what any field holds.
		$this->assertInstanceOf(NumberValue::class, $row->qty);
		$this->assertTrue($row->qty->number->isEqualTo(50));

		// Keyed by the template, so a field the template does not have is not carried along.
		$this->assertObjectNotHasProperty('ignored', $row);
	}

	#[Test]
	public function its_value_agrees_with_its_item_results(): void
	{
		// The same question asked two ways, which used to give two answers: `$value` held the raw
		// submission while the item results held the parsed one. A port reading either must see
		// the same thing.
		$result = $this->lines()->validate([(object)['sku' => 'A1', 'qty' => 50]]);

		$this->assertEquals($result->value->rows[0]->qty, $result->itemAt(0)->forField('qty')->value);
	}

	#[Test]
	public function an_item_still_reports_what_was_submitted(): void
	{
		// Resolving the rows must not reach `$given`. A rejected form is re-rendered from it, and
		// showing somebody a coerced value in place of what they typed is how a correction turns
		// into a second mistake.
		$result = $this->lines()->validate([(object)['sku' => 'A1', 'qty' => '0050']]);

		$this->assertSame('0050', $result->itemAt(0)->forField('qty')->given);
	}

	#[Test]
	public function a_blank_row_is_validated_like_any_other_item(): void
	{
		// Nobody here knows whether a blank row was an "add another" placeholder the UI drew or an
		// author who meant to fill it in and did not. Guessing is the renderer's job, so the
		// default is to validate what arrived — consistent with fields being required until told
		// otherwise.
		$result = $this->lines()->validate([
			(object)['sku' => 'A1', 'qty' => 50],
			(object)['sku' => '', 'qty' => null],
		]);

		$this->assertCount(2, $result->items);
		$this->assertSame(ValidationStatus::Failed, $result->itemAt(1)->status);
	}

	#[Test]
	public function a_blank_row_is_validated_like_any_other(): void
	{
		// Whatever was submitted is taken as intentional. A spare row a renderer drew is *its*
		// artefact to strip, and it is the only thing that can tell — an untouched file input
		// arrives as an array with UPLOAD_ERR_NO_FILE, and a hidden row index is never blank at
		// all. The core could only ever have approximated it.
		$result = $this->lines()->validate([
			(object)['sku' => 'A1', 'qty' => 50],
			(object)['sku' => '', 'qty' => null],
		]);

		$this->assertCount(2, $result->items, 'nothing is discarded');
		$this->assertTrue($result->anyFailed());
		$this->assertSame(ValidationStatus::Failed, $result->itemAt(1)->status);
	}

	#[Test]
	public function there_is_no_way_to_ask_for_blank_rows_to_be_dropped(): void
	{
		// It existed, and went with the decision that input means what it says. Named here so the
		// removal is deliberate rather than something that quietly came back.
		$this->assertFalse(method_exists($this->lines(), 'dropBlankItems'));
		$this->assertFalse(property_exists($this->lines(), 'dropsBlankItems'));
	}

	#[Test]
	public function it_reports_a_count_below_the_minimum(): void
	{
		$result = $this->lines()->minCountOf(2)->validate(self::items(50));

		$this->assertConstraintFailed('minCount', $result);
		$this->assertSame(2, $result->forConstraint('minCount')->bound);
	}

	#[Test]
	public function it_reports_a_count_above_the_maximum(): void
	{
		$result = $this->lines()->maxCountOf(1)->validate(self::items(50, 60));

		$this->assertConstraintFailed('maxCount', $result);
	}

	#[Test]
	public function a_count_is_not_checked_when_the_value_was_never_a_list(): void
	{
		// Nothing to count, so the count constraints are skipped rather than failed — the real
		// problem is reported once, against the shape.
		$result = $this->lines()->validate('not a list');

		$this->assertShapeFailed($result);
		$this->assertConstraintSkipped('minCount', $result);
		$this->assertConstraintSkipped('maxCount', $result);
	}

	// ── nothing submitted, however it arrives ─────────────────────────────────────────────

	#[Test]
	#[DataProvider('nothingSubmitted')]
	public function nothing_submitted_reports_the_minimum_rather_than_requiredness(mixed $given): void
	{
		// For a collection, "required" and "needs at least one item" are the same statement, so
		// there is one message for it: "add at least one line", not "this field is required".
		// An empty list and no list at all are that same statement, so they get the same answer.
		$result = $this->lines()->validate($given);

		$this->assertShapePassed($result);
		$this->assertConstraintFailed('minCount', $result);
	}

	/** @return array<string, array{mixed}> */
	public static function nothingSubmitted(): array
	{
		return [
			'an empty list' => [[]],
			'no list at all' => [null],
		];
	}

	#[Test]
	#[DataProvider('nothingSubmitted')]
	public function an_optional_collection_may_hold_nothing(mixed $given): void
	{
		// Which is the whole of what optional means here: `type` still passes, because an empty
		// list is a perfectly good list.
		$result = $this->lines()->makeOptional()->validate($given);

		$this->assertShapePassed($result);
		$this->assertConstraintSkipped('minCount', $result);
		$this->assertFalse($result->anyFailed());
	}

	#[Test]
	public function optional_still_means_that_many_once_there_are_any(): void
	{
		// "No referees, or three of them" — the case that stops optionality and the minimum being
		// folded into one knob.
		$field = $this->lines()->minCountOf(3)->makeOptional();

		$this->assertConstraintSkipped('minCount', $field->validate([]));
		$this->assertConstraintFailed('minCount', $field->validate(self::items(50)));
		$this->assertConstraintPassed('minCount', $field->validate(self::items(50, 60, 70)));
	}

	#[Test]
	public function a_minimum_of_zero_is_refused_as_meaningless(): void
	{
		// It would describe a collection that cannot be failed. Saying an empty one is acceptable
		// is what makeOptional() is for.
		$this->expectException(InvalidArgumentException::class);

		$this->lines()->minCountOf(0);
	}

	// ── a list is a set unless told otherwise ─────────────────────────────────────────────

	#[Test]
	public function the_same_item_twice_is_refused_by_default(): void
	{
		// A list of things is usually a set of things: the same guest invited twice, or the same
		// line claimed twice, is a mistake far more often than it is intent.
		$result = $this->lines()->validate(self::items(50, 50));

		$this->assertConstraintFailed('unique', $result);
	}

	#[Test]
	public function items_that_differ_anywhere_are_not_duplicates(): void
	{
		$this->assertConstraintPassed('unique', $this->lines()->validate(self::items(50, 60)));
		$this->assertConstraintPassed('unique', $this->lines()->validate([
			(object)['sku' => 'A1', 'qty' => 50],
			(object)['sku' => 'B2', 'qty' => 50],
		]));
	}

	#[Test]
	public function there_is_nothing_to_repeat_below_two_items(): void
	{
		// Skipped rather than passed, so it reads the same way as the counts do when they have
		// nothing to speak to.
		$this->assertConstraintSkipped('unique', $this->lines()->validate(self::items(50)));
		$this->assertConstraintSkipped('unique', $this->lines()->validate([]));
	}

	#[Test]
	public function duplicates_are_allowed_once_the_author_says_so(): void
	{
		// Invoice lines and timesheet entries both repeat legitimately.
		$field = $this->lines()->allowDuplicates();

		$this->assertConstraintSkipped('unique', $field->validate(self::items(50, 50)));
		$this->assertTrue($field->allowsDuplicates);
		$this->assertFalse($this->lines()->allowsDuplicates, 'the original was modified');
	}

	#[Test]
	public function duplicates_are_judged_on_the_resolved_value(): void
	{
		// Each field is the authority on what was actually entered, so a conversion counts: an
		// address given as a country name and one given as its code are the same address.
		$sites = new Collection(
			new FieldName('sites'),
			new Address(new FieldName('site'), ['AU']),
		);

		$parts = ['line1' => '1 Denham St', 'locality' => 'Rockhampton', 'postal_code' => '4700'];

		$this->assertConstraintFailed('unique', $sites->validate([
			(object)['site' => (object) ($parts + ['country' => 'AU'])],
			(object)['site' => (object) ($parts + ['country' => 'Australia'])],
		]));
	}

	#[Test]
	public function it_is_exactly_as_good_as_the_fields_in_the_template(): void
	{
		// Comparison is on the resolved value, so a field's canonicalisation counts. EmailAddress
		// lower-cases the domain because DNS says two spellings are one host, and that makes these
		// one guest rather than two — without this constraint knowing anything about email.
		$guests = new Collection(
			new FieldName('guests'),
			new EmailAddress(new FieldName('email')),
		);

		$this->assertConstraintFailed('unique', $guests->validate([
			(object)['email' => 'alice@example.test'],
			(object)['email' => 'alice@EXAMPLE.test'],
		]));
	}

	#[Test]
	public function a_value_object_decides_what_counts_as_the_same_value(): void
	{
		// Money is the one value object where structural comparison is wrong, so it implements
		// ParsedValue and this asks it rather than guessing. `12.50` and `12.5` are one line item.
		$lines = new Collection(
			new FieldName('lines'),
			new Money(new FieldName('price'), ['AUD']),
		);

		$this->assertConstraintFailed('unique', $lines->validate([
			(object)['price' => (object)['currency' => 'AUD', 'amount' => '12.50']],
			(object)['price' => (object)['currency' => 'AUD', 'amount' => '12.5']],
		]));

		$this->assertConstraintPassed('unique', $lines->validate([
			(object)['price' => (object)['currency' => 'AUD', 'amount' => '12.50']],
			(object)['price' => (object)['currency' => 'AUD', 'amount' => '9.99']],
		]));
	}

	#[Test]
	public function a_distinction_the_standard_keeps_is_kept_here_too(): void
	{
		// RFC 5321 permits a mailbox to be case-sensitive, so the local part is left alone and
		// these stay two people. Canonicalising it would be guessing about someone's mail server.
		$guests = new Collection(
			new FieldName('guests'),
			new EmailAddress(new FieldName('email')),
		);

		$this->assertConstraintPassed('unique', $guests->validate([
			(object)['email' => 'Alice@example.test'],
			(object)['email' => 'alice@example.test'],
		]));
	}
	// ── which item failed ─────────────────────────────────────────────────────────────────

	#[Test]
	public function a_failure_says_which_item_it_was(): void
	{
		// The documented defect this fixes: results used to be flattened into one list, so you
		// could tell that *an* item had failed but never which.
		$result = $this->lines()->validate(self::items(50, 1, 80));

		$this->assertSame(ValidationStatus::Passed, $result->itemAt(0)->status);
		$this->assertSame(ValidationStatus::Failed, $result->itemAt(1)->status);
		$this->assertSame(ValidationStatus::Passed, $result->itemAt(2)->status);
	}

	#[Test]
	public function it_names_the_constraint_the_item_failed(): void
	{
		$result = $this->lines()->validate(self::items(50, 1));

		$this->assertSame(
			ValidationStatus::Failed,
			$result->itemAt(1)->forField('qty')->forConstraint('minValue')->status,
		);
	}

	#[Test]
	public function it_lists_only_the_failed_items(): void
	{
		$result = $this->lines()->validate(self::items(50, 1, 80, 2));

		$this->assertSame([1, 3], array_map(static fn(Item $i): int => $i->key, $result->failedItems));
	}

	#[Test]
	public function an_item_carries_the_position_it_arrived_at(): void
	{
		$result = $this->lines()->validate(self::items(50, 60));

		$this->assertSame(0, $result->itemAt(0)->key);
		$this->assertSame(1, $result->itemAt(1)->key);
		$this->assertNull($result->itemAt(2));
	}

	#[Test]
	public function an_item_field_is_addressed_by_its_template_name(): void
	{
		// Not `lines.0.sku`: an item has no idea which collection it belongs to, and a template
		// field is named once rather than once per item.
		$result = $this->lines()->validate(self::items(50));

		$this->assertNotNull($result->itemAt(0)->forField('sku'));
		$this->assertNull($result->itemAt(0)->forField('lines.0.sku'));
	}

	#[Test]
	public function the_collections_own_verdicts_and_its_items_both_count_towards_the_whole(): void
	{
		$passing = $this->lines()->minCountOf(1)->validate(self::items(50));
		$badItem = $this->lines()->minCountOf(1)->validate(self::items(1));

		$this->assertFalse($passing->anyFailed());
		$this->assertTrue($badItem->anyFailed(), 'an item failure must reach the collection result');
	}

	// ── the template is never written to ──────────────────────────────────────────────────

	#[Test]
	public function validating_many_items_leaves_the_template_untouched(): void
	{
		// The path this replaced fed each item in with input(), so after validating a list the
		// template held the last item's values.
		$field = $this->lines();
		$before = $field->template;

		$field->validate(self::items(50, 60, 70));

		$this->assertSame($before, $field->template);
	}

	#[Test]
	public function resolving_does_not_check_anything(): void
	{
		$result = $this->lines()->resolve(self::items(1));

		$this->assertSame(ValidationStatus::Pending, $result->status);
	}

	// ── construction and configuration ────────────────────────────────────────────────────

	#[Test]
	public function it_needs_a_template(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Collection(new FieldName('empty'));
	}

	#[Test]
	public function it_refuses_two_template_fields_with_the_same_name(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Collection(new FieldName('dupe'), new Text(new FieldName('a')), new Text(new FieldName('a')));
	}

	#[Test]
	#[DataProvider('impossibleCounts')]
	public function an_impossible_count_is_refused_where_it_is_written(callable $attempt): void
	{
		$this->expectException(InvalidArgumentException::class);

		$attempt($this->lines());
	}

	/** @return array<string, array{callable}> */
	public static function impossibleCounts(): array
	{
		return [
			'a maximum of zero' => [fn(Collection $c): Collection => $c->maxCountOf(0)],
			'a minimum above the maximum' => [fn(Collection $c): Collection => $c->maxCountOf(2)->minCountOf(5)],
			'a maximum below the minimum' => [fn(Collection $c): Collection => $c->minCountOf(5)->maxCountOf(2)],
		];
	}

	#[Test]
	public function configuring_it_leaves_the_original_alone(): void
	{
		$field = $this->lines();
		$bounded = $field->minCountOf(2);

		$this->assertNotSame($field, $bounded);
		$this->assertSame(1, $field->minCount, 'a collection holds at least one item unless told otherwise');
		$this->assertSame(2, $bounded->minCount);
	}

	#[Test]
	public function it_is_sealed(): void
	{
		$this->expectException(\Error::class);

		/** @phpstan-ignore assign.propertyReadOnly */
		$this->lines()->minCount = 9;
	}

	// ── it is a field like any other ───────────────────────────────────────────────────────

	#[Test]
	public function its_result_is_findable_by_field_name(): void
	{
		// Which is all SchemaValidationResult::get() needs of it — no knowledge of the shape.
		$result = $this->lines()->validate(self::items(50));

		$this->assertInstanceOf(FieldResult::class, $result);
		$this->assertSame('lines', (string) $result->field->name);
	}

	private function assertShapePassed(Result $result): void
	{
		$this->assertTrue($result->shape->passed());
	}

	private function assertShapeFailed(Result $result): void
	{
		$this->assertTrue($result->shape->failed());

		// A collection never reports `missing`: absence became an empty list before the shape was
		// judged, so anything failing here is something that arrived and was not a list.
		$this->assertTrue($result->shape->wasUnreadable());
	}

	private function assertConstraintPassed(string $name, Result $result): void
	{
		$this->assertSame(ValidationStatus::Passed, $result->forConstraint($name)->status, $name);
	}

	private function assertConstraintFailed(string $name, Result $result): void
	{
		$this->assertSame(ValidationStatus::Failed, $result->forConstraint($name)->status, $name);
	}

	private function assertConstraintSkipped(string $name, Result $result): void
	{
		$this->assertSame(ValidationStatus::Skipped, $result->forConstraint($name)->status, $name);
	}
}
