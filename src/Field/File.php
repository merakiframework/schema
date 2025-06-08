<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\AtomicMultiValue as AtomicMultiValueField;
use Meraki\Schema\Field\File\Metadata;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * @psalm-type FileMetadata = array{
 *	name: string,
 *	type: string,
 *	size: int,
 *	source: string,
 * }
 * @extends Serialized<list<FileMetadata>|null>
 * @property-read int $minCount
 * @property-read int $maxCount
 * @property-read int $minSize
 * @property-read int $maxSize
 * @property-read list<string> $allowedTypes
 * @property-read list<string> $disallowedTypes
 * @property-read list<string> $allowedSources
 * @property-read list<string> $disallowedSources
 * @internal
 */
interface SerializedFile extends Serialized
{
}

/**
 * @psalm-type FileMetadata = array{
 *	name: string,
 *	type: string,
 *	size: int,
 *	source: string,
 * }
 * @extends AtomicMultiValueField<list<FileMetadata>|null, SerializedFile>
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

	/**
	 * @var list<string>
	 */
	public array $allowedSources = [];

	/**
	 * @var list<string>
	 */
	public array $disallowedSources = [];

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
		if (!is_array($value)) {
			throw new InvalidArgumentException('Expected an array for file input.');
		}

		if (array_is_list($value)) {
			foreach ($value as $file) {
				$this->assertCorrectStructure($file);
			}
		} else {
			$this->assertCorrectStructure($value);
			$value = [$value];
		}

		return array_map(
			fn(array $file): Metadata => new Metadata($file['name'], $file['type'], $file['size'], $file['source']),
			$value,
		);
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
		foreach (['name', 'type', 'size', 'source'] as $key) {
			if (!isset($value[$key])) {
				throw new InvalidArgumentException("Missing '$key' key in file array.");
			}
		}

		foreach (['name', 'type', 'source'] as $key) {
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
			// future: 'allowed_sources' => $this->validateAllowedSources(...),
			// future: 'disallowed_sources' => $this->validateDisallowedSources(...),
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

	public function serialize(): SerializedFile
	{
		return new class(
			type: $this->type->value,
			name: $this->name->value,
			optional: $this->optional,
			value: $this->defaultValue->unwrap(),
			minCount: $this->minCount,
			maxCount: $this->maxCount,
			minSize: $this->minSize,
			maxSize: $this->maxSize,
			allowedTypes: $this->allowedTypes,
			disallowedTypes: $this->disallowedTypes,
			allowedSources: $this->allowedSources,
			disallowedSources: $this->disallowedSources
		) implements SerializedFile {
			public function __construct(
				public readonly string $type,
				public readonly string $name,
				public readonly bool $optional,
				/** @param list<FileMetadata>|null $value */
				public readonly array|null $value,
				public readonly int $minCount,
				public readonly int $maxCount,
				public readonly int $minSize,
				public readonly int $maxSize,
				/** @param list<string> $allowedTypes */
				public readonly array $allowedTypes,
				/** @param list<string> $disallowedTypes */
				public readonly array $disallowedTypes,
				/** @param list<string> $allowedSources */
				public readonly array $allowedSources,
				/** @param list<string> $disallowedSources */
				public readonly array $disallowedSources,
			) {
			}
			public function getConstraints(): array
			{
				return [
					'min_count',
					'max_count',
					'min_size',
					'max_size',
					'allowed_types',
					'disallowed_types',
					'allowed_sources',
					'disallowed_sources',
				];
			}
			public function children(): array
			{
				return [];
			}
		};
	}

	/**
	 * @param SerializedFile $serialized
	 */
	public static function deserialize(Serialized $serialized): static
	{
		if ($serialized->type !== 'file' || !($serialized instanceof SerializedFile)) {
			throw new InvalidArgumentException('Invalid serialized data for File.');
		}

		$fileField = new self(new Property\Name($serialized->name));
		$fileField->optional = $serialized->optional;
		$fileField->allowedSources = $serialized->allowedSources;
		$fileField->disallowedSources = $serialized->disallowedSources;

		return $fileField->atLeast($serialized->minCount)
			->atMost($serialized->maxCount)
			->minFileSizeOf($serialized->minSize)
			->maxFileSizeOf($serialized->maxSize)
			->allowTypes(...$serialized->allowedTypes)
			->disallowTypes(...$serialized->disallowedTypes)
			->prefill($serialized->value);
	}
}
