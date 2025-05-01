<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Boolean;
use Meraki\Schema\Field\Atomic;
use Meraki\Schema\Property\Name;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('field')]
#[CoversClass(Boolean::class)]
final class BooleanTest extends FieldTestCase
{
	public function createField(): Boolean
	{
		return new Boolean(new Name('test'));
	}

	#[Test]
	public function it_has_the_correct_type(): void
	{
		$field = $this->createField();

		$this->assertSame('boolean', $field->type->value);
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$field = $this->createField();

		$this->assertSame('test', $field->name->value);
	}

	#[Test]
	public function it_is_an_atomic_field(): void
	{
		$field = $this->createField();

		$this->assertInstanceOf(Atomic::class, $field);
	}

	#[Test]
	#[DataProvider('validBooleanValues')]
	public function it_only_allows_boolean_values(mixed $booleanValue): void
	{
		$trueResult = $this->createField()->input($booleanValue)->validate();
		$this->assertConstraintValidationResultPassed('type', $trueResult);
	}

	public static function validBooleanValues(): array
	{
		return [
			'`true` boolean type' => [true],
			'`false` boolean type' => [false],
		];
	}
}
