<?php
declare(strict_types=1);

namespace Meraki\Schema\Property;

use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * A "name" attribute.
 *
 * The name attribute is used to specify the name of a field.
 */
final class Name extends Property
{
	public const PREFIX_SEPARATOR = '.';
	public string $prefix = '';

	/**
	 * A single name segment. Composite sub-fields are named by joining segments with
	 * {@see self::PREFIX_SEPARATOR}, so a name may contain dots, but each segment either
	 * side of one must satisfy this on its own.
	 *
	 * Deliberately strict: '/' and '#' are scope-path syntax, '.' separates a composite
	 * from its sub-fields, and an empty name is addressable by nothing.
	 */
	private const SEGMENT_PATTERN = '/^[A-Za-z_][A-Za-z0-9_-]*$/';

	/**
	 * @throws InvalidArgumentException if the name cannot identify a field
	 */
	private static function assertValid(mixed $value): void
	{
		if (!is_string($value)) {
			throw new InvalidArgumentException(sprintf(
				'A name must be a string, %s given.',
				get_debug_type($value),
			));
		}

		if ($value === '') {
			throw new InvalidArgumentException('A name cannot be empty.');
		}

		foreach (explode(self::PREFIX_SEPARATOR, $value) as $segment) {
			if (preg_match(self::SEGMENT_PATTERN, $segment) !== 1) {
				throw new InvalidArgumentException(sprintf(
					'"%s" is not a usable name: each part must start with a letter or '
					. 'underscore and contain only letters, digits, underscores and hyphens.',
					$value,
				));
			}
		}
	}

	public function __construct(mixed $value)
	{
		self::assertValid($value);

		parent::__construct('name', $value);
	}

	public function prefixWith(string|self $prefix): self
	{
		if ($prefix instanceof self) {
			$prefix = $prefix->value;
		}

		$self = new self($prefix . self::PREFIX_SEPARATOR . $this->value);
		$self->prefix = $prefix;

		return $self;
	}

	public function removePrefix(): self
	{
		if ($this->prefix === '') {
			return $this;
		}

		$self = new self(substr($this->value, strlen($this->prefix . self::PREFIX_SEPARATOR)));
		$self->prefix = '';

		return $self;
	}

	public function __toString(): string
	{
		return $this->value;
	}
}
