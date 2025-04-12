<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\CompositeField;
use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Validator;

class Money extends CompositeField
{
	public function __construct(Attribute\Name $name, Attribute\OneOf $acceptedCurrencies, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('money'), $name);

		$this->fields->add(
			(new Field\Number(new Attribute\Name('amount')))
				->inIncrementsOf(1)
		);
		$this->fields->add(
			new Field\Enum(
				new Attribute\Name('currency'),
				$acceptedCurrencies
			)
		);
	}

	public static function getSupportedAttributes(): array
	{
		return Attribute\Set::ALLOW_ANY;
	}
}
