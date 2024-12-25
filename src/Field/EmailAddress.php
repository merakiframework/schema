<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;

class EmailAddress extends Field
{
	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('email_address'), $name, ...$attributes);

		$this->registerConstraints([
			Attribute\Min::class => new Validator\CheckMinCharCount(),
			Attribute\Max::class => new Validator\CheckMaxCharCount(),
		]);
	}

	public function minLengthOf(int $minChars): self
	{
		$this->attributes = $this->attributes->set(new Attribute\Min($minChars));

		return $this;
	}

	public function maxLengthOf(int $maxChars): self
	{
		$this->attributes = $this->attributes->set(new Attribute\Max($maxChars));

		return $this;
	}

	public static function getSupportedAttributes(): array
	{
		return [
			Attribute\Min::class,
			Attribute\Max::class,
		];
	}
}
