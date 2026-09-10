<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\File;
use Meraki\Schema\Field\File\Value as FileValue;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\Property\Name;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(File::class)]
final class FileTest extends FieldTestCase
{
	public function createField(): File
	{
		return new File(new Name('file'));
	}

	#[Test]
	public function it_accepts_a_single_metadata_value_object(): void
	{
		$field = $this->createField()->allowTypes('image/png');

		$result = $field->validate(new FileValue('a.png', 'image/png', 123));

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertSame(ValidationStatus::Passed, $result->status);
	}

	#[Test]
	public function it_accepts_a_list_of_metadata_value_objects(): void
	{
		$field = $this->createField();

		$this->assertSame(ValidationStatus::Passed, $field->validate([
			new FileValue('a.png', 'image/png', 123),
			new FileValue('b.png', 'image/png', 456),
		])->status);
	}

	#[Test]
	public function it_accepts_a_mix_of_arrays_and_metadata_value_objects(): void
	{
		$field = $this->createField();

		$this->assertSame(ValidationStatus::Passed, $field->validate([
			['name' => 'a.png', 'type' => 'image/png', 'size' => 123],
			new FileValue('b.png', 'image/png', 456),
		])->status);
	}

	#[Test]
	#[DataProvider('fileInputsForValidation')]
	public function it_meets_expectations_for_file_type(mixed $input, ValidationStatus $expectedStatus): void
	{
		$field = new File(new Name('file'));

		$result = $field->validate($input);

		$this->assertConstraintValidationResultHasStatusOf($expectedStatus, 'type', $result);
	}

	public static function fileInputsForValidation(): array
	{
		return [
			'valid file' => [
				[
					'name' => 'file.txt',
					'type' => 'text/plain',
					'size' => 1234,
				],
				ValidationStatus::Passed
			],
			'file with no name' => [
				[
					'name' => '',
					'type' => 'text/plain',
					'size' => 1234,
				],
				ValidationStatus::Failed
			],
			'file with invalid size' => [
				[
					'name' => 'file.txt',
					'type' => 'text/plain',
					'size' => -1234,
				],
				ValidationStatus::Failed
			],
			'file with no type' => [
				[
					'name' => 'file.txt',
					'type' => '',
					'size' => 1234,
				],
				ValidationStatus::Failed
			],
			'empty string' => [
				'',
				ValidationStatus::Failed
			],
			'a string' => [
				'/temp/file.txt',
				ValidationStatus::Failed
			],
			'an integer' => [
				1234,
				ValidationStatus::Failed
			],
			'a float' => [
				1234.56,
				ValidationStatus::Failed
			],
			'a boolean' => [
				true,
				ValidationStatus::Failed
			],
			'a list of files' => [
				[
					[
						'name' => 'file1.txt',
						'type' => 'text/plain',
						'size' => 1234,
					],
					[
						'name' => 'file2.txt',
						'type' => 'text/plain',
						'size' => 5678,
					],
				],
				ValidationStatus::Passed
			],
		];
	}

	#[Test]
	public function it_fails_validation_when_less_than_minimum_file_count(): void
	{
		$field = new File(new Name('upload'));
		$field->atLeast(2);

		$result = $field->validate([
			[
			'name' => 'file1.txt',
			'type' => 'text/plain',
			'size' => 1000,
		]
		]);

		$this->assertConstraintValidationResultFailed('minCount', $result);
	}

	#[Test]
	public function it_fails_validation_when_more_than_maximum_file_count(): void
	{
		$field = new File(new Name('upload'));
		$field->atMost(1);

		$result = $field->validate([
			[
			'name' => 'file1.txt',
			'type' => 'text/plain',
			'size' => 1000,
		],
			[
				'name' => 'file2.txt',
				'type' => 'text/plain',
				'size' => 1500,
			],
		]);

		$this->assertConstraintValidationResultFailed('maxCount', $result);
	}

	#[Test]
	public function it_fails_validation_when_file_type_is_not_allowed(): void
	{
		$field = new File(new Name('upload'));
		$field->allowDocuments();

		$result = $field->validate([
			[
			'name' => 'video.mp4',
			'type' => 'video/mp4',
			'size' => 1000,
		],
		]);

		$this->assertConstraintValidationResultFailed('allowedTypes', $result);
	}

	#[Test]
	public function it_fails_validation_when_file_type_is_explicitly_disallowed(): void
	{
		$field = new File(new Name('upload'));
		$field->disallowScripts();

		$result = $field->validate([
			[
			'name' => 'script.js',
			'type' => 'application/javascript',
			'size' => 800,
		],
		]);

		$this->assertConstraintValidationResultFailed('disallowedTypes', $result);
	}

	#[Test]
	public function it_fails_validation_when_file_is_smaller_than_minSize(): void
	{
		$field = new File(new Name('upload'));
		$field->minFileSizeOf(1024);

		$result = $field->validate([
			[
			'name' => 'tiny.txt',
			'type' => 'text/plain',
			'size' => 512,
		],
		]);

		$this->assertConstraintValidationResultFailed('minSize', $result);
	}

	#[Test]
	public function it_fails_validation_when_file_is_larger_than_maxSize(): void
	{
		$field = new File(new Name('upload'));
		$field->maxFileSizeOf(2048);

		$result = $field->validate([
			[
			'name' => 'large.txt',
			'type' => 'text/plain',
			'size' => 4096,
		],
		]);

		$this->assertConstraintValidationResultFailed('maxSize', $result);
	}


	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->defaultValue->unwrap());
	}
}
