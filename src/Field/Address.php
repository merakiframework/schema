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
	public function __construct(Property\Name $name)
	{
		parent::__construct(
			new Property\Type('address', $this->validateAddressType(...)),
			$name,
			new Field\Text(new Property\Name('street')),
			new Field\Text(new Property\Name('city')),
			new Field\Text(new Property\Name('state')),
			new Field\Text(new Property\Name('postal_code')),
			new Field\Text(new Property\Name('country')),
		);
	}

	protected function getConstraints(): array
	{
		return [];
	}

	private function validateAddressType(mixed $value): bool
	{
		return true;
	}
}
