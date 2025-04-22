<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\Type;
use Meraki\Schema\Validator\CheckType;

final class Text implements Type
{
	public string $name = 'text';

	public function accepts(mixed $value): bool
	{
		return is_string($value);
	}

	public function getValidator(): CheckType
	{
		return new CheckType($this);
	}
}


// class Text extends Field
// {
// 	public function __construct(Attribute\Name $name, Attribute ...$attributes)
// 	{
// 		$this->registerConstraints([
// 			Attribute\Min::class => new Validator\CheckMinCharCount(),
// 			Attribute\Max::class => new Validator\CheckMaxCharCount(),
// 			Attribute\Pattern::class => new Validator\MatchesRegex(),
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

// 	public function matches(string $regex): self
// 	{
// 		$this->attributes = $this->attributes->set(new Attribute\Pattern($regex));

// 		return $this;
// 	}

// 	public static function getSupportedAttributes(): array
// 	{
// 		return [
// 			Attribute\Min::class,
// 			Attribute\Max::class,
// 			Attribute\Pattern::class,
// 		];
// 	}
// }
