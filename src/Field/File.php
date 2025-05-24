<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\AtomicMultiValue as AtomicMultiValueField;
use Meraki\Schema\Field\Value\FileMetadata;
use Meraki\Schema\Property;
use InvalidArgumentException;

final class File extends AtomicMultiValueField
{
	public const UNLIMITED = -1;

	public int $minCount = 1;

	public int $maxCount = self::UNLIMITED;

	public int $minSize = 0; // in bytes

	public int $maxSize = self::UNLIMITED; // in bytes

	public array $allowedTypes = [];

	public array $disallowedTypes = [];

	public array $allowedSources = [];

	public array $disallowedSources = [];

	public function __construct(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	) {
		parent::__construct(new Property\Type('file', $this->validateType(...)), $name, $value, $defaultValue, $optional);
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

	public function allowImages(array $additionalImageTypes = []): self
	{
		$this->allowedTypes = array_merge($this->allowedTypes, [
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/svg+xml',
		], $additionalImageTypes);

		return $this;
	}

	public function allowVideos(array $additionalVideoTypes = []): self
	{
		$this->allowedTypes = array_merge($this->allowedTypes, [
			'video/mp4',
			'video/webm',
			'video/ogg',
			'video/quicktime',
		], $additionalVideoTypes);

		return $this;
	}

	public function disallowScripts(array $additionalScriptTypes = []): self
	{
		$this->disallowedTypes = array_merge($this->disallowedTypes, [
			'application/x-javascript',
			'application/javascript',
			'text/javascript',
			'application/x-php',
			'text/html',
			'application/x-sh',
		], $additionalScriptTypes);

		return $this;
	}

	public function allowDocuments(array $additionalDocumentTypes = []): self
	{
		$this->allowedTypes = array_merge($this->allowedTypes, [
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
		], $additionalDocumentTypes);

		return $this;
	}

	/**
	 * @return FileMetadata[]
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
			fn(array $file): FileMetadata => new FileMetadata($file['name'], $file['type'], $file['size'], $file['source']),
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
}
