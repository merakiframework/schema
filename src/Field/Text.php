<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;

class Text extends Field
{
	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('text'), $name, ...$attributes);

		$this->registerConstraints([
			Attribute\Min::class => new Validator\CheckMinCharCount(),
			Attribute\Max::class => new Validator\CheckMaxCharCount(),
			Attribute\Pattern::class => new Validator\MatchesRegex(),
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

	public function matches(string $regex): self
	{
		$this->attributes = $this->attributes->set(new Attribute\Pattern($regex));

		return $this;
	}

	public static function getSupportedAttributes(): array
	{
		return [
			Attribute\Min::class,
			Attribute\Max::class,
			Attribute\Pattern::class,
		];
	}

	protected static function getTypeConstraintValidator(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				return is_string($field->value);
			}
		};
	}
}
