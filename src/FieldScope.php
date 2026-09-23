<?php
declare(strict_types=1);

namespace Meraki\Schema;

/**
 * Names a field itself — `#/fields/nickname`.
 *
 * This is what a rule outcome can act on: i.e. making a field optional, requiring it, discarding its input, etc...
 */
final readonly class FieldScope extends Scope
{
	/**
	 * @param FieldName|string $field
	 */
	public static function of(FieldName|string $field): self
	{
		return new self($field instanceof FieldName ? $field : new FieldName($field));
	}

	public function __toString(): string
	{
		return $this->prefix();
	}
}
