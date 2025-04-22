<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\Type;
use Meraki\Schema\Validator\CheckType;

final class Url implements Type
{
	private const PATTERN = '~^
		(?:([^:/\?\#]+):)?	# scheme
		(?://([^/\?\#]*))?	# authority
		([^\?\#]*)			# path
		(?:\?([^\#]*))?		# query
		(?:\#(.*))?			# fragment
	$~x';

	public string $name = 'url';

	public function accepts(mixed $value): bool
	{
		return is_string($value) && preg_match(self::PATTERN, $value) === 1;
	}

	public function getValidator(): CheckType
	{
		return new CheckType($this);
	}
}

// class Url extends Field
// {
// 	public function __construct(Attribute\Name $name, Attribute ...$attributes)
// 	{

// 		$this->registerConstraints([
// 			Attribute\Min::class => $this->getValidatorForMin(),
// 			Attribute\Max::class => $this->getValidatorForMax(),
// 		]);
// 	}

// 	public function minLengthOf(int $value): self
// 	{
// 		$this->attributes = $this->attributes->add(new Attribute\Min($value));

// 		return $this;
// 	}

// 	public function maxLengthOf(int $value): self
// 	{
// 		$this->attributes = $this->attributes->add(new Attribute\Max($value));

// 		return $this;
// 	}

// 	public static function getSupportedAttributes(): array
// 	{
// 		return [
// 			Attribute\Min::class,
// 			Attribute\Max::class,
// 		];
// 	}

// 	private function getValidatorForMin(): Validator
// 	{
// 		return new class implements Validator {
// 			public function validate(Attribute&Constraint $constraint, Field $field): bool
// 			{
// 				return mb_strlen($field->value) >= $constraint->value;
// 			}
// 		};
// 	}

// 	private function getValidatorForMax(): Validator
// 	{
// 		return new class implements Validator {
// 			public function validate(Attribute&Constraint $constraint, Field $field): bool
// 			{
// 				return mb_strlen($field->value) <= $constraint->value;
// 			}
// 		};
// 	}
// }
