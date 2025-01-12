<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;

/**
 * A "name" field is used to represent a person's full name.
 *
 * It does not make any assumptions about the structure of a name.
 * There are, however, some sane restrictions. A name can:
 *
 * 	- contain unicode letters, spaces, apostrophes, periods, and dashes
 * 	- consist of one or more "words" separated by spaces
 * 	- each "word" must be at least one character long
 *  - should use Roman Numerals to represent numbers (e.g. John Doe IV)
 *
 * You can override these restrictions by setting the appropriate attributes.
 *
 * @see https://www.w3.org/International/questions/qa-personal-names
 * @see https://shinesolutions.com/2018/01/08/falsehoods-programmers-believe-about-names-with-examples/
 */
class Name extends Field
{
	/**
	 * - allow unicode letters, spaces, apostrophes, periods, and dashes
	 * - allow an empty string
	 * - all "words" must be separated by one or more spaces
	 * - each "word" must be at least one character long
	 * - do not allow just spaces, apostrophes, periods, or dashes, or any combination of them
	 */
	private const TYPE_PATTERN = "/^(?![\ \.\'\-]+$)[\p{L}\.\'\ \-]*$/u";

	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('name'), $name, ...$attributes);

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

	protected static function getTypeConstraintValidator(): Validator
	{
		return new class(self::TYPE_PATTERN) implements Validator {
			public function __construct(private string $pattern) {}
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				return is_string($field->value) && preg_match($this->pattern, $field->value) === 1;
			}
		};
	}
}
