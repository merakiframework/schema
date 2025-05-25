<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\Composite as CompositeField;
use Meraki\Schema\Property;

/**
 * A field representing an address.
 *
 * @todo Maybe add line1 (person) and line2 (company) fields
 *
 * @property-read Field\Text $street
 * @property-read Field\Text $city
 * @property-read Field\Text $state
 * @property-read Field\Text $postalCode
 * @property-read Field\Enum $country
 */
final class Address extends CompositeField
{
	private const DEFAULT_FIELD_NAMES = [
		'street',
		'city',
		'state',
		'postal_code',
		'country',
	];

	public function __construct(
		Property\Name $name,
	) {
		parent::__construct(
			new Property\Type('address', $this->validateType(...)),
			$name,
			new Field\Text(new Property\Name('street')),
			new Field\Text(new Property\Name('city')),
			new Field\Text(new Property\Name('state')),
			new Field\Text(new Property\Name('postal_code')),
			new Field\Text(new Property\Name('country')),
		);

		$default = [];

		foreach (self::DEFAULT_FIELD_NAMES as $fieldName) {
			$fieldName = (new Property\Name($fieldName))->prefixWith($name)->__toString();
			$default[$fieldName] = null;
		}

		$this->defaultValue = new Property\Value($default);
		$this->value = new Property\Value($default);
	}

	protected function getConstraints(): array
	{
		return [];
	}
}
