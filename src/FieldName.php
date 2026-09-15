<?php
declare(strict_types=1);

namespace Meraki\Schema;

use InvalidArgumentException;
use Stringable;

final readonly class FieldName implements Stringable
{

	private const PATTERN = '/^[A-Za-z_][A-Za-z0-9_-]*$/';

	public function __construct(
		private string $value
	) {
		if ($this->value === '') {
			throw new InvalidArgumentException('A name cannot be empty.');
		}

		if (preg_match(self::PATTERN, $this->value) !== 1) {
			throw new InvalidArgumentException(sprintf(
				'"%s" is not a usable name: each part must start with a letter or '
				. 'underscore and contain only letters, digits, underscores and hyphens.',
				$this->value,
			));
		}
	}

	public function equals(self $other): bool
	{
		return strcasecmp($this->value, $other->value) === 0;
	}

	public function __toString(): string
	{
		return $this->value;
	}
}
