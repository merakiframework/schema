<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Boolean;
use Meraki\Schema\FieldName;
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
		return new Boolean(new FieldName('test'));
	}
	#[Test]
	public function it_has_the_correct_name(): void
	{
		$field = $this->createField();

		$this->assertSame('test', (string) $field->name);
	}

	#[Test]
	#[DataProvider('validBooleanValues')]
	public function it_only_allows_boolean_values(mixed $booleanValue): void
	{
		$trueResult = $this->createField()->validate($booleanValue);
		$this->assertShapePassed($trueResult);
	}

	public static function validBooleanValues(): array
	{
		return [
			'`true` boolean type' => [true],
			'`false` boolean type' => [false],
		];
	}


	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->defaultValue);
	}

	#[Test]
	public function requiring_acceptance_makes_the_field_required(): void
	{
		$field = $this->createField()->makeOptional()->mustBeAccepted();

		$this->assertFalse($field->optional);
		$this->assertTrue($field->requiresAcceptance);
	}

	#[Test]
	public function acceptance_passes_when_the_value_is_true(): void
	{
		$result = $this->createField()->mustBeAccepted()->validate(true);

		$this->assertConstraintValidationResultPassed('accepted', $result);
	}

	#[Test]
	public function acceptance_fails_when_the_value_is_false(): void
	{
		$result = $this->createField()->mustBeAccepted()->validate(false);

		$this->assertConstraintValidationResultFailed('accepted', $result);
	}
}
