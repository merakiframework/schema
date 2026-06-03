<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\File\Metadata;
use Meraki\Schema\Field\AtomicMultiValue as AtomicMultiValueField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * @psalm-type FileMetadata = array{
 *	name: string,
 *	type: string,
 *	size: int,
 * }
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedFile = SerializedField&object{
 * 	type: 'file',
 * 	value: list<FileMetadata>|null,
 * 	min_count: int,
 * 	max_count: int,
 * 	min_size: int,
 * 	max_size: int,
 * 	allowed_types: list<string>,
 * 	disallowed_types: list<string>
 * }
 * Input may be a single file (a FileMetadata array or a Metadata instance) or a
 * list of either.
 * @extends AtomicMultiValueField<list<FileMetadata|Metadata>|FileMetadata|Metadata|null, SerializedFile>
 */
final class File extends AtomicMultiValueField
{
	public const UNLIMITED = -1;

	public int $minCount = 1;

	public int $maxCount = self::UNLIMITED;

	public int $minSize = 0; // in bytes

	public int $maxSize = self::UNLIMITED; // in bytes

	/**
	 * @var list<string>
	 */
	public array $allowedTypes = [];

	/**
	 * @var list<string>
	 */
	public array $disallowedTypes = [];

	public function __construct(
		Property\Name $name,
	) {
		parent::__construct(new Property\Type('file', $this->validateType(...)), $name);
	}

	public function atLeast(int $minFiles): self
	{
		if ($minFiles < 1) {
			throw new InvalidArgumentException('Minimum count must be greater than or equal to 1.');
		}

		if ($this->maxCount !== self::UNLIMITED && $minFiles > $this->maxCount) {
			throw new InvalidArgumentException('Minimum count cannot be greater than maximum count.');
		}

		$this->minCount = $minFiles;

		return $this;
	}

	public function atMost(int $maxFiles): self
	{
		if ($maxFiles !== self::UNLIMITED && $maxFiles < 1) {
			throw new InvalidArgumentException('Maximum count must be at least 1 or higher.');
		}

		if ($maxFiles !== self::UNLIMITED && $maxFiles < $this->minCount) {
			throw new InvalidArgumentException('Maximum count cannot be less than minimum count.');
		}

		$this->maxCount = $maxFiles;

		return $this;
	}

	public function minFileSizeOf(int $bytes): self
	{
		if ($bytes < 0) {
			throw new InvalidArgumentException('Minimum file size must be non-negative.');
		}

		$this->minSize = $bytes;

		return $this;
	}

	public function maxFileSizeOf(int $bytes): self
	{
		if ($bytes < 0 && $bytes !== self::UNLIMITED) {
			throw new InvalidArgumentException('Maximum file size must be non-negative or unlimited.');
		}

		$this->maxSize = $bytes;

		return $this;
	}

	public function allowTypes(string ...$types): self
	{
		foreach ($types as $additionalType) {
			if (!in_array($additionalType, $this->allowedTypes, true)) {
				$this->allowedTypes[] = $additionalType;
			}
		}

		return $this;
	}

	public function disallowTypes(string ...$types): self
	{
		foreach ($types as $additionalType) {
			if (!in_array($additionalType, $this->disallowedTypes, true)) {
				$this->disallowedTypes[] = $additionalType;
			}
		}

		return $this;
	}

	public function allowImages(array $additionalImageTypes = []): self
	{
		return $this->allowTypes(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/svg+xml',
		);
	}

	public function allowVideos(array $additionalVideoTypes = []): self
	{
		return $this->allowTypes(
			'video/mp4',
			'video/webm',
			'video/ogg',
			'video/quicktime',
		);
	}

	public function disallowScripts(array $additionalScriptTypes = []): self
	{
		return $this->disallowTypes(
			'application/x-javascript',
			'application/javascript',
			'text/javascript',
			'application/x-php',
			'text/html',
			'application/x-sh',
		);
	}

	public function allowDocuments(array $additionalDocumentTypes = []): self
	{
		return $this->allowTypes(
			'application/pdf',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.ms-powerpoint',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'text/plain',
			'text/csv',
			'application/rtf',
		);
	}

	/**
	 * @return Metadata[]
	 */
	protected function cast(mixed $value): array
	{
		// A single file may be given as a Metadata instance or an associative
		// metadata array; multiple files as a list of either (or a mix).
		if ($value instanceof Metadata || (is_array($value) && !array_is_list($value))) {
			$value = [$value];
		}

		if (!is_array($value)) {
			throw new InvalidArgumentException('Expected file metadata (an array or a Metadata instance) or a list thereof.');
		}

		return array_map($this->toMetadata(...), $value);
	}

	private function toMetadata(mixed $file): Metadata
	{
		if ($file instanceof Metadata) {
			return $file;
		}

		if (!is_array($file)) {
			throw new InvalidArgumentException('Each file must be an associative metadata array or a Metadata instance.');
		}

		$this->assertCorrectStructure($file);

		return new Metadata($file['name'], $file['type'], $file['size']);
	}

	protected function validateType(mixed $value): bool
	{
		try {
			$files = $this->cast($value);

			return count($files) > 0;
		} catch (InvalidArgumentException $e) {
			return false;
		}
	}

	private function assertCorrectStructure(array $value): void
	{
		foreach (['name', 'type', 'size'] as $key) {
			if (!isset($value[$key])) {
				throw new InvalidArgumentException("Missing '$key' key in file array.");
			}
		}

		foreach (['name', 'type'] as $key) {
			if (!is_string($value[$key]) || $value[$key] === '') {
				throw new InvalidArgumentException("Key '$key' must be a string in file array.");
			}
		}

		if (!is_int($value['size'])) {
			throw new InvalidArgumentException("Key 'size' must be an integer in file array.");
		}

		if ($value['size'] < 0) {
			throw new InvalidArgumentException("Key 'size' must be a non-negative integer in file array.");
		}
	}

	protected function getConstraints(): array
	{
		return [
			'min_count' => $this->validateMinCount(...),
			'max_count' => $this->validateMaxCount(...),
			'allowed_types' => $this->validateAllowedTypes(...),
			'disallowed_types' => $this->validateDisallowedTypes(...),
			'min_size' => $this->validateMinSize(...),
			'max_size' => $this->validateMaxSize(...),
		];
	}

	private function validateMinCount(mixed $value): ?bool
	{
		return count($this->cast($value)) >= $this->minCount;
	}

	private function validateMaxCount(mixed $value): ?bool
	{
		return $this->maxCount === self::UNLIMITED || count($this->cast($value)) <= $this->maxCount;
	}

	private function validateAllowedTypes(mixed $value): ?bool
	{
		if (empty($this->allowedTypes)) {
			return true;
		}

		foreach ($this->cast($value) as $file) {
			if (!in_array($file->type, $this->allowedTypes, true)) {
				return false;
			}
		}

		return true;
	}

	private function validateDisallowedTypes(mixed $value): ?bool
	{
		if (empty($this->disallowedTypes)) {
			return true;
		}

		foreach ($this->cast($value) as $file) {
			if (in_array($file->type, $this->disallowedTypes, true)) {
				return false;
			}
		}

		return true;
	}

	private function validateMinSize(mixed $value): ?bool
	{
		foreach ($this->cast($value) as $file) {
			if ($file->size < $this->minSize) {
				return false;
			}
		}
		return true;
	}

	private function validateMaxSize(mixed $value): ?bool
	{
		if ($this->maxSize === self::UNLIMITED) {
			return true;
		}

		foreach ($this->cast($value) as $file) {
			if ($file->size > $this->maxSize) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return SerializedFile
	 */
	public function serialize(): object
	{
		return (object)[
			'type' => $this->type->value,
			'name' => $this->name->value,
			'optional' => $this->optional,
			'value' => $this->defaultValue->unwrap(),
			'fields' => [],
			'min_count' => $this->minCount,
			'max_count' => $this->maxCount,
			'min_size' => $this->minSize,
			'max_size' => $this->maxSize,
			'allowed_types' => $this->allowedTypes,
			'disallowed_types' => $this->disallowedTypes,
		];
	}

	/**
	 * @param SerializedFile $serialized
	 */
	public static function deserialize(object $serialized, Field\Factory $fieldFactory): static
	{
		if ($serialized->type !== 'file') {
			throw new InvalidArgumentException('Invalid serialized data for File.');
		}

		$fileField = new self(new Property\Name($serialized->name));
		$fileField->optional = $serialized->optional;

		return $fileField->atLeast($serialized->min_count)
			->atMost($serialized->max_count)
			->minFileSizeOf($serialized->min_size)
			->maxFileSizeOf($serialized->max_size)
			->allowTypes(...$serialized->allowed_types)
			->disallowTypes(...$serialized->disallowed_types)
			->prefill($serialized->value);
	}
}
