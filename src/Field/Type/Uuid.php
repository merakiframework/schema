<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\Type;
use Meraki\Schema\Validator\CheckType;

final class Uuid implements Type
{
	private const PATTERN = '/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}|00000000-0000-0000-0000-000000000000|ffffffff-ffff-ffff-ffff-ffffffffffff)$/i';

	public string $name = 'uuid';

	public function accepts(mixed $value): bool
	{
		return is_string($value) && preg_match(self::PATTERN, $value) === 1;
	}

	public function getValidator(): CheckType
	{
		return new CheckType($this);
	}
}

// class Uuid extends Field
// {
//

// 	public function __construct(Attribute\Name $name, Attribute ...$attributes)
// 	{

// 		$this->registerConstraint(Attribute\Version::class, self::getValidatorForVersion());

// 		$this->attributes = $this->attributes->add(new Version());
// 	}

// 	public function accept(int $version): self
// 	{
// 		/**  @var Attribute\Version $attr */
// 		$attr = $this->attributes->getByName('version');

// 		$attr->add($version);

// 		return $this;
// 	}

// 	public static function getSupportedAttributes(): array
// 	{
// 		return [
// 			Attribute\Version::class,
// 		];
// 	}

// 	private static function getValidatorForVersion(): Validator
// 	{
// 		return new class implements Validator {
// 			public function validate(Attribute&Constraint $constraint, Field $field): bool
// 			{
// 				$uuid = $field->value;
// 				$expectedVersions = $constraint->value;

// 				return count($expectedVersions) === 0 || in_array(hexdec($uuid[14]), $expectedVersions, true);
// 			}
// 		};
// 	}
// }
