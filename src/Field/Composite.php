<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;

/**
 * @extends Field<null|array>
 */
abstract class Composite extends Field
{
	protected array $fields = [];

	public function input($value): static
	{
		parent::input($value);

		if ($value === null) {
			foreach ($this->fields as $field) {
				$field->input(null);
			}

			return $this;
		}

		foreach ($this->value->unwrap() as $fieldName => $fieldValue) {
			if (isset($this->fields[$fieldName])) {
				$this->fields[$fieldName]->input($fieldValue);
			}
		}

		return $this;
	}
}
