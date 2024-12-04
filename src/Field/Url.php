<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;

class Url extends Field
{
	private const TYPE_PATTERN = '~^
		(?:([^:/\?\#]+):)?	# scheme
		(?://([^/\?\#]*))?	# authority
		([^\?\#]*)			# path
		(?:\?([^\#]*))?		# query
		(?:\#(.*))?			# fragment
	$~x';

	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('url'), $name, ...$attributes);

		$this->registerConstraints([
			Attribute\Min::class => $this->getValidatorForMin(),
			Attribute\Max::class => $this->getValidatorForMax(),
		]);
	}

	public function minLengthOf(int $value): self
	{
		$this->attributes = $this->attributes->add(new Attribute\Min($value));

		return $this;
	}

	public function maxLengthOf(int $value): self
	{
		$this->attributes = $this->attributes->add(new Attribute\Max($value));

		return $this;
	}

	public static function getSupportedAttributes(): array
	{
		return [
			Attribute\Min::class,
			Attribute\Max::class,
		];
	}

	protected function isCorrectType(mixed $value): bool
	{
		return is_string($value) && preg_match(self::TYPE_PATTERN, $value) === 1;
	}

	private function getValidatorForMin(): Validator
	{
		return new class implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				return mb_strlen($field->value) >= $constraint->value;
			}
		};
	}

	private function getValidatorForMax(): Validator
	{
		return new class implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				return mb_strlen($field->value) <= $constraint->value;
			}
		};
	}
}
