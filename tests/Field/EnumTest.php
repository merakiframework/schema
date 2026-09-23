<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Enum;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('field')]
#[CoversClass(Enum::class)]
final class EnumTest extends FieldTestCase
{
	public function createField(): Enum
	{
		return new Enum(new FieldName('test'), ['AUD', 'USD', 'EUR']);
	}
	#[Test]
	public function it_has_the_correct_name(): void
	{
		$field = $this->createField();

		$this->assertSame('test', (string) $field->name);
	}

	#[Test]
	public function it_only_allows_values_in_the_set(): void
	{
		$field = $this->createField();

		$result = $field->validate('USD');

		$this->assertShapePassed($result);
	}


	/**
	 * The list of cases is the type, so a value outside it is a shape failure.
	 *
	 * Deliberately not a constraint. That was tried, on the argument that a renderer wants the
	 * list to interpolate into "must be one of: AUD, USD" — but an enum renderer reads `$cases`
	 * to draw its options anyway, so the bound carried nothing it did not already have, and the
	 * field ended up answering the same question twice.
	 */
	#[Test]
	public function it_does_not_allow_invalid_values(): void
	{
		$field = $this->createField();

		$result = $field->validate('GBP');

		$this->assertShapeFailed($result);
		$this->assertCount(0, $field->constraints);
	}


	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->defaultValue);
	}
	#[Test]
	public function the_chosen_case_reads_back_as_text(): void
	{
		// So a template can interpolate the value rather than reaching past it. Before this it had
		// no string form at all, which made `(string) $result->forField('theme')->value` a fatal.
		$field = new Enum(new FieldName('theme'), ['light', 'dark']);

		$this->assertSame('dark', (string) $field->validate('dark')->value);
	}

	#[Test]
	public function the_case_is_on_a_property_as_well_as_in_the_string_form(): void
	{
		// The same string either way. Both are kept for the reason Text\Value keeps `$text` beside
		// its own: a consumer compares the property and a template interpolates the object.
		$field = new Enum(new FieldName('theme'), ['light', 'dark']);
		$value = $field->validate('dark')->value;

		$this->assertSame('dark', $value->case);
		$this->assertSame('dark', (string) $value);
	}

	/** @return array<string, array{list<mixed>}> */
	public static function casesThatAreNotStrings(): array
	{
		return [
			'integers' => [[1, 2, 3]],
			'floats' => [[2.71, 3.14]],
			'booleans' => [[true, false]],
			'one bad apple' => [['light', 'dark', 3]],
		];
	}

	#[Test]
	#[DataProvider('casesThatAreNotStrings')]
	public function cases_that_are_not_strings_are_refused_where_they_are_written(array $cases): void
	{
		// A form submits "2" rather than 2, and membership is decided strictly — so an enum of
		// integers was unreadable for every form submission there has ever been. It worked only
		// for a JSON client that had sent a real integer, which made it a surprise rather than a
		// feature. Boolean covers yes-or-no; Number with a step covers a regular sequence.
		$this->expectException(\InvalidArgumentException::class);

		new Enum(new FieldName('choice'), $cases);
	}

	#[Test]
	public function the_empty_string_cannot_be_a_case(): void
	{
		// It is what a select's placeholder option submits when nothing was chosen, so a case
		// spelled that way would be chosen by everybody who chose nothing.
		$this->expectException(\InvalidArgumentException::class);

		new Enum(new FieldName('choice'), ['', 'light']);
	}

}