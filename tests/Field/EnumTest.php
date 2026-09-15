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


	#[Test]
	public function it_does_not_allow_invalid_values(): void
	{
		$field = $this->createField();

		$result = $field->validate('GBP');

		$this->assertShapeFailed($result);
	}


	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->defaultValue);
	}
}
