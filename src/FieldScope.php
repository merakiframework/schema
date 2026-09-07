<?php
declare(strict_types=1);

namespace Meraki\Schema;

/**
 * Names a field itself — `#/fields/nickname`.
 *
 * This is what a rule outcome acts on: making a field optional, requiring it, discarding
 * its input. Those outcomes used to accept any scope and throw at request time if it
 * turned out to point at a value or a property ("Require can only be applied to fields").
 * Taking this type instead moves that to the signature, so the mistake cannot be written.
 */
final readonly class FieldScope extends Scope
{
	/**
	 * @param Property\Name|string $field
	 */
	public static function of(Property\Name|string $field): self
	{
		return new self($field instanceof Property\Name ? $field : new Property\Name($field));
	}

	public function __toString(): string
	{
		return $this->prefix();
	}
}
