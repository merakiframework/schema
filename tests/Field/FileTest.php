<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\File;
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
	#[DataProvider('fileInputsForValidation')]
	public function it_meets_expectations_for_file_type(mixed $input, ValidationStatus $expectedStatus): void
	{
		$field = new File(new Name('file'));
		$field->input($input);

		$result = $field->validate();

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
					'source' => 'file:///temp/file.txt',
				],
				ValidationStatus::Passed
			],
			'file with no name' => [
				[
					'name' => '',
					'type' => 'text/plain',
					'size' => 1234,
					'source' => 'file:///temp/file.txt',
				],
				ValidationStatus::Failed
			],
			'file with invalid size' => [
				[
					'name' => 'file.txt',
					'type' => 'text/plain',
					'size' => -1234,
					'source' => 'file:///temp/file.txt',
				],
				ValidationStatus::Failed
			],
			'file with no type' => [
				[
					'name' => 'file.txt',
					'type' => '',
					'size' => 1234,
					'source' => 'file:///temp/file.txt',
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
						'source' => 'file:///temp/file1.txt',
					],
					[
						'name' => 'file2.txt',
						'type' => 'text/plain',
						'size' => 5678,
						'source' => 'file:///temp/file2.txt',
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
		$field->input([
			[
				'name' => 'file1.txt',
				'type' => 'text/plain',
				'size' => 1000,
				'source' => 'file:///tmp/file1.txt',
			]
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('min_count', $result);
	}

	#[Test]
	public function it_fails_validation_when_more_than_maximum_file_count(): void
	{
		$field = new File(new Name('upload'));
		$field->atMost(1);
		$field->input([
			[
				'name' => 'file1.txt',
				'type' => 'text/plain',
				'size' => 1000,
				'source' => 'file:///tmp/file1.txt',
			],
			[
				'name' => 'file2.txt',
				'type' => 'text/plain',
				'size' => 1500,
				'source' => 'file:///tmp/file2.txt',
			],
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('max_count', $result);
	}

	#[Test]
	public function it_fails_validation_when_file_type_is_not_allowed(): void
	{
		$field = new File(new Name('upload'));
		$field->allowDocuments();
		$field->input([
			[
				'name' => 'video.mp4',
				'type' => 'video/mp4',
				'size' => 1000,
				'source' => 'file:///tmp/video.mp4',
			],
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('allowed_types', $result);
	}

	#[Test]
	public function it_fails_validation_when_file_type_is_explicitly_disallowed(): void
	{
		$field = new File(new Name('upload'));
		$field->disallowScripts();
		$field->input([
			[
				'name' => 'script.js',
				'type' => 'application/javascript',
				'size' => 800,
				'source' => 'file:///tmp/script.js',
			],
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('disallowed_types', $result);
	}

	#[Test]
	public function it_fails_validation_when_file_is_smaller_than_min_size(): void
	{
		$field = new File(new Name('upload'));
		$field->minFileSizeOf(1024);
		$field->input([
			[
				'name' => 'tiny.txt',
				'type' => 'text/plain',
				'size' => 512,
				'source' => 'file:///tmp/tiny.txt',
			],
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('min_size', $result);
	}

	#[Test]
	public function it_fails_validation_when_file_is_larger_than_max_size(): void
	{
		$field = new File(new Name('upload'));
		$field->maxFileSizeOf(2048);
		$field->input([
			[
				'name' => 'large.txt',
				'type' => 'text/plain',
				'size' => 4096,
				'source' => 'file:///tmp/large.txt',
			],
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('max_size', $result);
	}

}
