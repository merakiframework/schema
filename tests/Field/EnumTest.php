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
	 * A value outside the cases is a *constraint* failure, not a shape failure.
	 *
	 * The distinction is the whole reason this field has a constraint at all. Shape failure means
	 * "that is not the kind of thing this field holds" and has nothing to interpolate; membership
	 * means "that is not one of these" and carries the list, so a renderer can say
	 * "must be one of: AUD, USD" without reaching past the result to the field.
	 */
	#[Test]
	public function a_value_outside_the_cases_fails_the_membership_constraint(): void
	{
		$field = $this->createField();

		$result = $field->validate('GBP');

		$this->assertShapePassed($result);
		$this->assertConstraintValidationResultFailed('allowedCases', $result);
	}

	#[Test]
	public function the_membership_failure_carries_the_cases_as_its_bound(): void
	{
		$result = $this->createField()->validate('GBP');

		$this->assertSame(
			array_map(strval(...), $this->createField()->cases),
			$result->forConstraint('allowedCases')->bound,
		);
	}

	/**
	 * Something that is not a scalar at all cannot be a case, so that really is the shape.
	 */
	#[Test]
	public function a_value_that_could_never_be_a_case_fails_the_shape(): void
	{
		$this->assertShapeFailed($this->createField()->validate((object)['not' => 'scalar']));
	}


	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->defaultValue);
	}
}
