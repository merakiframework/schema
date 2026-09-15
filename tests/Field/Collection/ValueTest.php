<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Collection;

use Meraki\Schema\Facade;
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
		$schema = new Facade('invoice');
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
			'first run' => (object) ['sku' => 'A1', 'qty' => '2'],
			'second run' => (object) ['sku' => 'B2', 'qty' => '3'],
		]);

		$this->assertSame(['first run', 'second run'], array_keys($value->rows));
		$this->assertCount(2, $value);
	}

	#[Test]
	public function it_reaches_a_row_and_a_field_by_key(): void
	{
		$value = $this->rowsFor([
			'first run' => (object) ['sku' => 'A1', 'qty' => '2'],
			'second run' => (object) ['sku' => 'B2', 'qty' => '3'],
		]);

		$this->assertSame(['first run', 'second run'], $value->keys());
		$this->assertTrue($value->has('second run'));
		$this->assertFalse($value->has('third run'));

		$this->assertSame('A1', $value->rowAt('first run')->sku->text);
		$this->assertSame('B2', $value->valueOf('second run', 'sku')->text);
	}

	/**
	 * Two ways to be absent, one answer. A caller writing `$value->rows[$k]->sku ?? null` has to
	 * think about both; this does not.
	 */
	#[Test]
	public function a_missing_row_or_field_reads_as_nothing(): void
	{
		$value = $this->rowsFor([(object) ['sku' => 'A1', 'qty' => '2']]);

		$this->assertNull($value->rowAt('nope'));
		$this->assertNull($value->valueOf('nope', 'sku'));
		$this->assertNull($value->valueOf(0, 'not_a_template_field'));
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
		$written = $this->rowsFor([(object) ['sku' => 'A1', 'qty' => '2.0']]);
		$otherwise = $this->rowsFor([(object) ['sku' => 'A1', 'qty' => '2']]);

		$this->assertTrue($written->equals($otherwise));
	}

	#[Test]
	public function a_different_leaf_makes_a_different_list(): void
	{
		$this->assertFalse(
			$this->rowsFor([(object) ['sku' => 'A1', 'qty' => '2']])
				->equals($this->rowsFor([(object) ['sku' => 'A2', 'qty' => '2']])),
		);
	}

	#[Test]
	public function order_counts(): void
	{
		$forwards = $this->rowsFor([(object) ['sku' => 'A1', 'qty' => '1'], (object) ['sku' => 'B2', 'qty' => '1']]);
		$backwards = $this->rowsFor([(object) ['sku' => 'B2', 'qty' => '1'], (object) ['sku' => 'A1', 'qty' => '1']]);

		// Two invoices with the same lines in a different order are not obviously one invoice, and
		// deciding they were would be this object inventing a rule nobody asked for.
		$this->assertFalse($forwards->equals($backwards));
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
			(object) ['sku' => 'A1', 'qty' => '2.0'],
			(object) ['sku' => 'A1', 'qty' => '2'],
		]);

		$this->assertTrue($value->hasRepeats());
	}

	#[Test]
	public function distinct_rows_are_not_repeats(): void
	{
		$value = $this->rowsFor([
			(object) ['sku' => 'A1', 'qty' => '2'],
			(object) ['sku' => 'B2', 'qty' => '2'],
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
		$schema = new Facade('other');
		$text = $schema->createTextField('t')->resolvedValueFor('a');

		$this->assertFalse($this->rowsFor([])->equals($text));
	}
}
