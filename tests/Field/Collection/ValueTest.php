<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Collection;

use Meraki\Schema\Definition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The rows of a collection, as one value.
 *
 * Built through a real schema rather than by hand, deliberately: the rows this holds are whatever
 * the template resolved, and a test that constructed them directly would be asserting against its
 * own idea of that rather than the field's.
 */
#[Group('field')]
#[CoversClass(Value::class)]
final class ValueTest extends TestCase
{
	private function rowsFor(array $submitted): Value
	{
		$schema = new Definition('invoice');
		$schema->add($schema->createCollectionField(
			'lines',
			$schema->createTextField('sku'),
			$schema->createNumberField('qty'),
		));

		$value = $schema->validate((object) ['lines' => $submitted])->forField('lines')->value;

		$this->assertInstanceOf(Value::class, $value);

		return $value;
	}

	#[Test]
	public function it_keeps_the_keys_the_rows_arrived_with(): void
	{
		$value = $this->rowsFor([
			'first_run' => (object) ['sku' => 'A1', 'qty' => '2'],
			'second_run' => (object) ['sku' => 'B2', 'qty' => '3'],
		]);

		$this->assertSame(['first_run', 'second_run'], array_keys($value->rows));
		$this->assertCount(2, $value);
	}

	#[Test]
	public function it_reaches_a_row_and_a_field_by_key(): void
	{
		$value = $this->rowsFor([
			'first_run' => (object) ['sku' => 'A1', 'qty' => '2'],
			'second_run' => (object) ['sku' => 'B2', 'qty' => '3'],
		]);

		$this->assertSame(['first_run', 'second_run'], $value->keys());
		$this->assertTrue($value->has('second_run'));
		$this->assertFalse($value->has('third_run'));

		$this->assertSame('A1', $value->rowAt('first_run')->sku->text);
		$this->assertSame('B2', $value->valueOf('second_run', 'sku')->text);
	}

	/**
	 * Two ways to be absent, one answer. A caller writing `$value->rows[$k]->sku ?? null` has to
	 * think about both; this does not.
	 */
	#[Test]
	public function a_missing_row_or_field_reads_as_nothing(): void
	{
		$value = $this->rowsFor(['only' => (object) ['sku' => 'A1', 'qty' => '2']]);

		$this->assertNull($value->rowAt('nope'));
		$this->assertNull($value->valueOf('nope', 'sku'));
		$this->assertNull($value->valueOf('only', 'not_a_template_field'));
	}

	#[Test]
	public function it_reads_one_field_across_every_row(): void
	{
		$value = $this->rowsFor([
			'a' => (object) ['sku' => 'A1', 'qty' => '2'],
			'b' => (object) ['sku' => 'B2', 'qty' => '3'],
		]);

		// Keyed as the rows are, so a named row stays named.
		$this->assertSame(['a', 'b'], array_keys($value->column('sku')));
		$this->assertSame(['A1', 'B2'], array_map(strval(...), array_values($value->column('sku'))));
	}

	#[Test]
	public function it_iterates_rows_under_their_own_keys(): void
	{
		$value = $this->rowsFor(['only' => (object) ['sku' => 'A1', 'qty' => '2']]);
		$seen = [];

		foreach ($value as $key => $row) {
			$seen[$key] = $row->sku->text;
		}

		$this->assertSame(['only' => 'A1'], $seen);
	}

	/**
	 * The whole reason a collection has a value object: equality bottoms out in the leaves, so a
	 * quantity written `2.0` and one written `2` are the same row.
	 */
	#[Test]
	public function two_lists_are_equal_when_every_leaf_is(): void
	{
		$written = $this->rowsFor(['one' => (object) ['sku' => 'A1', 'qty' => '2.0']]);
		$otherWay = $this->rowsFor(['one' => (object) ['sku' => 'A1', 'qty' => '2']]);

		$this->assertTrue($written->equals($otherWay));
	}

	#[Test]
	public function a_different_leaf_makes_a_different_list(): void
	{
		$this->assertFalse(
			$this->rowsFor(['one' => (object) ['sku' => 'A1', 'qty' => '2']])
				->equals($this->rowsFor(['one' => (object) ['sku' => 'A2', 'qty' => '2']])),
		);
	}

	#[Test]
	public function order_does_not_count(): void
	{
		$forwards = $this->rowsFor([
			'deposit' => (object) ['sku' => 'A1', 'qty' => '1'],
			'balance' => (object) ['sku' => 'B2', 'qty' => '1'],
		]);
		$backwards = $this->rowsFor([
			'balance' => (object) ['sku' => 'B2', 'qty' => '1'],
			'deposit' => (object) ['sku' => 'A1', 'qty' => '1'],
		]);

		// A name already says which row is which, so the order they arrived in cannot also say it.
		// This used to assert the opposite, back when a row could be positional and the order was the
		// only identity a row had.
		$this->assertTrue($forwards->equals($backwards));
	}

	#[Test]
	public function differently_named_rows_are_a_different_list(): void
	{
		$this->assertFalse(
			$this->rowsFor(['morning' => (object) ['sku' => 'A1', 'qty' => '1']])
				->equals($this->rowsFor(['evening' => (object) ['sku' => 'A1', 'qty' => '1']])),
		);
	}

	#[Test]
	public function it_finds_a_repeat_written_two_ways(): void
	{
		$value = $this->rowsFor([
			'written' => (object) ['sku' => 'A1', 'qty' => '2.0'],
			'other_way' => (object) ['sku' => 'A1', 'qty' => '2'],
		]);

		$this->assertTrue($value->hasRepeats());
	}

	#[Test]
	public function distinct_rows_are_not_repeats(): void
	{
		$value = $this->rowsFor([
			'first' => (object) ['sku' => 'A1', 'qty' => '2'],
			'second' => (object) ['sku' => 'B2', 'qty' => '2'],
		]);

		$this->assertFalse($value->hasRepeats());
		$this->assertFalse($value->isEmpty());
	}

	#[Test]
	public function an_empty_list_is_empty_and_has_no_repeats(): void
	{
		$value = $this->rowsFor([]);

		$this->assertTrue($value->isEmpty());
		$this->assertCount(0, $value);
		$this->assertFalse($value->hasRepeats());
	}

	#[Test]
	public function it_is_never_equal_to_a_value_of_another_kind(): void
	{
		$schema = new Definition('other');
		$text = $schema->createTextField('t')->resolvedValueFor('a');

		$this->assertFalse($this->rowsFor([])->equals($text));
	}
}
