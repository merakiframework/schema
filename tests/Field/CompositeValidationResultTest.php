<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\Composite as CompositeField;
use Meraki\Schema\Field\Factory as FieldFactory;
use Meraki\Schema\Field\ValidationResult as FieldValidationResult;
use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\ValidationResult;
use Meraki\Schema\AggregatedValidationResultTestCase;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\Property;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

#[Group('validation')]
#[CoversClass(CompositeValidationResult::class)]
class CompositeValidationResultTest extends AggregatedValidationResultTestCase
{
	public function createSubject(ValidationResult ...$results): CompositeValidationResult
	{
		return new CompositeValidationResult($this->mockComposite(), ...$results);
	}

	public function createPassedResult(): FieldValidationResult
	{
		return new FieldValidationResult(
			$this->mockField(),
			ConstraintValidationResult::pass('type')
		);
	}

	public function createFailedResult(): FieldValidationResult
	{
		return new FieldValidationResult(
			$this->mockField(),
			ConstraintValidationResult::fail('type')
		);
	}

	public function createSkippedResult(): FieldValidationResult
	{
		return new FieldValidationResult(
			$this->mockField(),
			ConstraintValidationResult::skip('type')
		);
	}

	public function createPendingResult(): FieldValidationResult
	{
		return new FieldValidationResult(
			$this->mockField(),
			new ConstraintValidationResult(ValidationStatus::Pending, 'type')
		);
	}

	#[Test]
	public function composite_field_is_cloned_correctly(): void
	{
		$passedResult = $this->createPassedResult();
		$failedResult = $this->createFailedResult();
		$composite = $this->mockComposite();
		$compositeResult = new CompositeValidationResult($composite, $passedResult, $failedResult);
		$filtered = $compositeResult->getPassed(); // Should clone and return only passed results

		$this->assertNotSame($compositeResult, $filtered, 'Filtered result must be a new instance.');
		$this->assertNotSame($compositeResult->composite, $filtered->composite, 'Composite object must be cloned.');
		$this->assertEquals($compositeResult->composite->name, $filtered->composite->name, 'Composite name must be preserved.');
		$this->assertCount(1, $filtered->results, 'Only one passed result should remain.');
	}

	private function mockComposite(): CompositeField
	{
		return new class extends CompositeField {
			public function __construct() {
				parent::__construct(new Property\Type('mock', $this->validateMockType(...)), new Property\Name('mock'));
			}

			public function getConstraints(): array {
				return [];
			}

			private function validateMockType(): bool {
				return true;
			}

			public function serialize(): object {
				return (object)[
					'type' => 'mock',
					'name' => 'mock',
					'optional' => false,
					'value' => null,
					'fields' => [],
				];
			}

			public static function deserialize(object $serialized, FieldFactory $fieldFactory): static {
				if ($serialized->type !== 'mock') {
					throw new \InvalidArgumentException('Invalid type for Mock field: ' . $serialized->type);
				}
				return new self();
			}
		};
	}

	private function mockField(): Field
	{
		return $this->createMock(Field::class);
	}
}
