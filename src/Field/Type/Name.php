<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\Type;
use Meraki\Schema\Validator\CheckType;

/**
 * A "name" field is used to represent a person's full name.
 *
 * It does not make any assumptions about the structure of a name.
 * There are, however, some sane restrictions. A name can:

 *  - not be empty
 * 	- contain unicode letters, spaces, apostrophes, periods, commas, and dashes
 * 	- consist of one or more "words" separated by spaces
 * 	- each "word" must be at least one character long
 *  - should use Roman Numerals to represent numbers (e.g. John Doe IV)
 *
 * @see https://www.w3.org/International/questions/qa-personal-names
 * @see https://shinesolutions.com/2018/01/08/falsehoods-programmers-believe-about-names-with-examples/
 */
final class Name implements Type
{
	private const PATTERN = "/^(?![\ \.\,\'\-]+$)[\p{L}\.\,\'\ \-]+$/u";

	public string $name = 'name';

	public function accepts(mixed $value): bool
	{
		return is_string($value) && preg_match(self::PATTERN, $value) === 1;
	}

	public function getValidator(): CheckType
	{
		return new CheckType($this);
	}
}


// class Name extends Field
// {
// 	public function __construct(Attribute\Name $name, Attribute ...$attributes)
// 	{
// 		$this->registerConstraints([
// 			Attribute\Min::class => new Validator\CheckMinCharCount(),
// 			Attribute\Max::class => new Validator\CheckMaxCharCount(),
// 		]);
// 	}

// 	public function minLengthOf(int $minChars): self
// 	{
// 		$this->attributes = $this->attributes->set(new Attribute\Min($minChars));

// 		return $this;
// 	}

// 	public function maxLengthOf(int $maxChars): self
// 	{
// 		$this->attributes = $this->attributes->set(new Attribute\Max($maxChars));

// 		return $this;
// 	}

// 	public static function getSupportedAttributes(): array
// 	{
// 		return [
// 			Attribute\Min::class,
// 			Attribute\Max::class,
// 		];
// 	}
// }
