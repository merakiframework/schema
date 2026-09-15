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
}
