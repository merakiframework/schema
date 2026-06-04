<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Composite as CompositeField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * A field representing an address.
 *
 * @todo Maybe add line1 (person) and line2 (company) fields
 *
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedAddress = SerializedField&object{
 * 	type: 'address',
 * 	value: array|null,
 * }
 * @extends CompositeField<array|null, SerializedAddress>
 *
 * @property-read Field\Text $street
 * @property-read Field\Text $city
 * @property-read Field\Text $state
 * @property-read Field\Text $postalCode
 * @property-read Field\Text $country
 */
final class Address extends CompositeField
{
	public function __construct(Property\Name $name)
	{
		parent::__construct(
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
}
