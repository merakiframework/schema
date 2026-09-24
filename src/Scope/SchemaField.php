<?php
declare(strict_types=1);

namespace Meraki\Schema\Scope;

use Meraki\Schema\FieldName;
use Meraki\Schema\Scope;

/**
 * A field the schema holds — `#/fields/username`.
 *
 * The default, and what every scope written before collections became addressable means. A scope
 * given a bare {@see FieldName} builds one of these, which is why none of the existing call sites
 * had to learn that locators exist.
 */
final readonly class SchemaField implements Locator
{
	public function __construct(public FieldName $field)
	{
	}

	public function addresses(): FieldName
	{
		return $this->field;
	}

	public function __toString(): string
	{
		return '#/' . Scope::FIELD_COLLECTION . '/' . $this->field;
	}
}
