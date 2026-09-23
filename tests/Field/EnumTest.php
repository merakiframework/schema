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
	public function the_typed_case_is_still_there_to_compare_against(): void
	{
		// `__toString()` renders; `$case` holds the case at its declared type. An enum of integers
		// compares as integers and displays as digits, and neither reading is the other's job.
		$field = new Enum(new FieldName('priority'), [1, 2, 3]);
		$value = $field->validate(2)->value;

		$this->assertSame(2, $value->case);
		$this->assertSame('2', (string) $value);
	}

	#[Test]
	public function a_boolean_case_renders_as_a_word_rather_than_vanishing(): void
	{
		// PHP casts false to the empty string, so a template would render nothing at all and the
		// chosen case would appear to have gone missing. The one scalar whose rendering is spelled
		// out rather than delegated.
		$field = new Enum(new FieldName('answer'), [true, false]);

		$this->assertSame('false', (string) $field->validate(false)->value);
		$this->assertSame('true', (string) $field->validate(true)->value);
	}

}