<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\File;

use InvalidArgumentException;

final class Value
{
	public function __construct(
		public readonly string $name,
		public readonly string $type,
		public readonly int $size,
	) {
		if ($size < 0) {
			throw new InvalidArgumentException("File 'size' must be a non-negative integer.");
		}
	}

	public static function fromArray(array $data): self
	{
		foreach (['name', 'type', 'size'] as $key) {
			if (!isset($data[$key])) {
				throw new InvalidArgumentException("Missing '$key' key in file array.");
			}
		}

		return new self(
			name: $data['name'],
			type: $data['type'],
			size: $data['size'],
		);
	}
}
