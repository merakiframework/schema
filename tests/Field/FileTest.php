<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\File;
use Meraki\Schema\Field\File\Value;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(File::class)]
#[CoversClass(Value::class)]
final class FileTest extends FieldTestCase
{
	public function createField(): File
	{
		return new File(new FieldName('file'));
	}

	#[Test]
	public function it_accepts_the_record_an_upload_arrives_as(): void
	{
		$result = $this->createField()->validate((object)['name' => 'a.png', 'type' => 'image/png', 'size' => 123]);

		$this->assertSame(ValidationStatus::Passed, $result->status);
		$this->assertInstanceOf(Value::class, $result->value);
	}

	#[Test]
	public function it_accepts_a_value_object(): void
	{
		$result = $this->createField()->validate(new Value('a.png', 'image/png', 123));

		$this->assertSame(ValidationStatus::Passed, $result->status);
		$this->assertSame('a.png', $result->value->name);
	}

	#[Test]
	#[DataProvider('unusableInput')]
	public function it_rejects_what_cannot_be_read_as_a_file(mixed $given): void
	{
		// process() passes unusable input through untouched, so it fails the shape check
		// alongside everything else rather than throwing while a definition is built.
		$this->assertShapeFailed($this->createField()->validate($given));
	}

	/** @return array<string, array{mixed}> */
	public static function unusableInput(): array
	{
		return [
			'a string' => ['not-a-file'],
			'a number' => [42],
			'a list of files' => [[['name' => 'a.txt', 'type' => 'text/plain', 'size' => 1]]],
			'missing its name' => [['type' => 'text/plain', 'size' => 1]],
			'missing its type' => [['name' => 'a.txt', 'size' => 1]],
			'missing its size' => [['name' => 'a.txt', 'type' => 'text/plain']],
			'a null name' => [['name' => null, 'type' => 'text/plain', 'size' => 1]],
			'a non-numeric size' => [['name' => 'a.txt', 'type' => 'text/plain', 'size' => 'big']],
		];
	}

	#[Test]
	public function several_files_is_a_collection_of_file_fields(): void
	{
		// A field holds one value. The plural lives in Collection, which is the thing that
		// knows about many — so File has no count of its own.
		$field = $this->createField();

		$this->assertFalse(method_exists($field, 'minCountOf'));
		$this->assertFalse(method_exists($field, 'atLeast'));
	}

	#[Test]
	public function it_fails_when_the_file_is_smaller_than_the_minimum(): void
	{
		$field = $this->createField()->minSizeOf(1000);

		$result = $field->validate((object)['name' => 'a.txt', 'type' => 'text/plain', 'size' => 999]);

		$this->assertConstraintValidationResultFailed('minSize', $result);
		$this->assertSame(1000, $result->forConstraint('minSize')->bound);
	}

	#[Test]
	public function it_fails_when_the_file_is_larger_than_the_maximum(): void
	{
		$field = $this->createField()->maxSizeOf(1000);

		$result = $field->validate((object)['name' => 'a.txt', 'type' => 'text/plain', 'size' => 1001]);

		$this->assertConstraintValidationResultFailed('maxSize', $result);
		$this->assertSame(1000, $result->forConstraint('maxSize')->bound);
	}

	#[Test]
	public function a_size_with_no_ceiling_is_not_checked(): void
	{
		$result = $this->createField()->validate((object)['name' => 'a.txt', 'type' => 'text/plain', 'size' => PHP_INT_MAX]);

		$this->assertConstraintValidationResultSkipped('maxSize', $result);
	}

	#[Test]
	public function it_fails_when_the_type_is_not_among_those_allowed(): void
	{
		$field = $this->createField()->allowDocuments();

		$result = $field->validate((object)['name' => 'video.mp4', 'type' => 'video/mp4', 'size' => 1000]);

		$this->assertConstraintValidationResultFailed('allowedTypes', $result);
	}

	#[Test]
	public function it_fails_when_the_type_is_explicitly_disallowed(): void
	{
		$field = $this->createField()->disallowScripts();

		$result = $field->validate((object)['name' => 'evil.php', 'type' => 'application/x-php', 'size' => 10]);

		$this->assertConstraintValidationResultFailed('disallowedTypes', $result);
	}

	#[Test]
	public function types_that_were_never_restricted_are_not_checked(): void
	{
		$result = $this->createField()->validate((object)['name' => 'a.txt', 'type' => 'text/plain', 'size' => 1]);

		$this->assertConstraintValidationResultSkipped('allowedTypes', $result);
		$this->assertConstraintValidationResultSkipped('disallowedTypes', $result);
	}

	#[Test]
	public function allowing_types_accumulates(): void
	{
		$field = $this->createField()->allowImages()->allowTypes('application/pdf');

		$this->assertContains('image/png', $field->allowedTypes);
		$this->assertContains('application/pdf', $field->allowedTypes);
	}

	#[Test]
	public function disallowing_types_accumulates(): void
	{
		$field = $this->createField()->disallowScripts()->disallowTypes('application/x-msdownload');

		$this->assertContains('application/x-php', $field->disallowedTypes);
		$this->assertContains('application/x-msdownload', $field->disallowedTypes);
	}


	#[Test]
	public function clearing_the_allowed_types_accepts_every_type_again(): void
	{
		$field = $this->createField()->allowImages()->clearAllowedTypes();

		$this->assertSame([], $field->allowedTypes);
		$this->assertConstraintValidationResultSkipped(
			'allowedTypes',
			$field->validate((object)['name' => 'a.mp4', 'type' => 'video/mp4', 'size' => 1]),
		);
	}

	#[Test]
	public function clearing_the_disallowed_types_refuses_nothing_again(): void
	{
		$field = $this->createField()->disallowScripts()->clearDisallowedTypes();

		$this->assertSame([], $field->disallowedTypes);
		$this->assertConstraintValidationResultSkipped(
			'disallowedTypes',
			$field->validate((object)['name' => 'evil.php', 'type' => 'application/x-php', 'size' => 1]),
		);
	}

	#[Test]
	public function clearing_one_list_leaves_the_other_alone(): void
	{
		// One call clearing both would undo a disallowScripts() that was never mentioned.
		$field = $this->createField()->allowDocuments()->disallowScripts()->clearAllowedTypes();

		$this->assertSame([], $field->allowedTypes);
		$this->assertContains('application/x-php', $field->disallowedTypes);
	}

	#[Test]
	public function configuring_a_field_leaves_the_original_alone(): void
	{
		// A field is sealed, so a wither hands back a copy.
		$field = $this->createField();
		$restricted = $field->allowImages();

		$this->assertNotSame($field, $restricted);
		$this->assertSame([], $field->allowedTypes);
	}

	#[Test]
	public function a_minimum_above_the_maximum_is_rejected_where_it_is_declared(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->createField()->maxSizeOf(100)->minSizeOf(200);
	}

	#[Test]
	public function a_negative_size_is_rejected(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->createField()->minSizeOf(-1);
	}

	#[Test]
	public function an_empty_media_type_is_rejected(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->createField()->allowTypes('');
	}

	#[Test]
	public function a_filename_is_read_knowingly_rather_than_printed(): void
	{
		// No __toString(): a filename is attacker-controlled text, and stringifying invites it into
		// a page or a path unescaped.
		$value = new Value('report.pdf', 'application/pdf', 2048);

		$this->assertSame('report.pdf', $value->name);
		$this->assertNotInstanceOf(\Stringable::class, $value);
	}

	#[Test]
	public function an_authored_default_is_parsed_like_any_other_value(): void
	{
		// The default is kept exactly as the author wrote it — the same rule as $given — and is
		// read through parse() when it stands in for a submission. So the field holds the record
		// and the result holds the Value.
		$field = $this->createField()->defaultsTo((object)['name' => 'a.png', 'type' => 'image/png', 'size' => 8]);

		$this->assertIsObject($field->defaultValue);

		$resolved = $field->validate(null);

		$this->assertInstanceOf(Value::class, $resolved->value);
		$this->assertSame('a.png', $resolved->value->name);
	}

	#[Test]
	public function a_default_that_breaks_a_constraint_is_refused_where_it_is_written(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->createField()
			->allowImages()
			->defaultsTo((object)['name' => 'a.pdf', 'type' => 'application/pdf', 'size' => 8]);
	}

	#[Test]
	public function a_later_constraint_that_invalidates_the_default_is_refused_too(): void
	{
		// Configuration arrives in any order, so the failure has to land on whichever call
		// made the default wrong — here the maximum, not the default.
		$this->expectException(InvalidArgumentException::class);

		$this->createField()
			->defaultsTo((object)['name' => 'a.png', 'type' => 'image/png', 'size' => 5000])
			->maxSizeOf(1000);
	}

	#[Test]
	public function a_default_is_used_when_nothing_is_submitted(): void
	{
		$field = $this->createField()->defaultsTo((object)['name' => 'a.png', 'type' => 'image/png', 'size' => 8]);

		$result = $field->validate(null);

		$this->assertSame(ValidationStatus::Passed, $result->status);
		$this->assertSame('a.png', $result->value->name);
	}
	#[Test]
	public function a_size_given_as_a_numeric_string_is_read_as_bytes(): void
	{
		// PHP hands $_FILES sizes back as integers, but a JSON body or a form round-trip
		// can deliver the same number as a string.
		$value = Value::fromInput(['name' => 'a.txt', 'type' => 'text/plain', 'size' => '2048']);

		$this->assertSame(2048, $value->size);
	}
}
