<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\File;

final class Metadata
{
	public function __construct(
		public readonly string $name,
		public readonly string $type,
		public readonly int $size,
		public readonly string $source,
	) {
	}
}
