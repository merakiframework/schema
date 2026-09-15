<?php
declare(strict_types=1);

namespace Meraki\Schema;

use InvalidArgumentException;

/**
 * Names part of a field's definition — `#/fields/age/min`, `#/fields/nickname/optional`.
 *
 * Addressing here is deliberately open: a field's public properties *are* its API, so any
 * of them can be targeted rather than a curated list. What the property means is the
 * field's business; this only says which one.
 */
final readonly class PropertyScope extends Scope
{
	public function __construct(FieldName $field, public string $property)
	{
		if ($property === '') {
			throw new InvalidArgumentException('A property scope must name a property.');
		}

		if ($property === ValueScope::SEGMENT) {
			throw new InvalidArgumentException(sprintf(
				'"%s" addresses a submitted value, not a definition property. Use %s.',
				$property,
				ValueScope::class,
			));
		}

		parent::__construct($field);
	}

	/**
	 * @param FieldName|string $field
	 */
	public static function of(FieldName|string $field, string $property): self
	{
		return new self(
			$field instanceof FieldName ? $field : new FieldName($field),
			$property,
		);
	}

	public function __toString(): string
	{
		return $this->prefix() . '/' . $this->property;
	}
}
