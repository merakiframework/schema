<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Exception\InvalidFieldName;
use Stringable;

final readonly class FieldName implements Stringable
{

	private const PATTERN = '/^[A-Za-z_][A-Za-z0-9_-]*$/';

	public function __construct(
		private string $value
	) {
		if ($this->value === '') {
			throw InvalidFieldName::isEmpty();
		}

		if (preg_match(self::PATTERN, $this->value) !== 1) {
			throw InvalidFieldName::isNotUsable($this->value);
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
