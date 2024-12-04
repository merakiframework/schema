<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Attribute\Version;
use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;

class Uuid extends Field
{
	private const TYPE_PATTERN = '/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}|00000000-0000-0000-0000-000000000000|ffffffff-ffff-ffff-ffff-ffffffffffff)$/i';

	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		$this->registerConstraint(Attribute\Version::class, self::getValidatorForVersion());

		parent::__construct(new Attribute\Type('uuid'), $name, ...$attributes);

		$this->attributes = $this->attributes->add(new Version());
	}

	public function accept(int $version): self
	{
		/**  @var Attribute\Version $attr */
		$attr = $this->attributes->getByName('version');

		$attr->add($version);

		return $this;
	}

	public static function getSupportedAttributes(): array
	{
		return [
			Attribute\Version::class,
		];
	}

	protected function isCorrectType(mixed $value): bool
	{
		return is_string($value) && preg_match(self::TYPE_PATTERN, $value) === 1;
	}

	private static function getValidatorForVersion(): Validator
	{
		return new class implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				$uuid = $field->value;
				$expectedVersions = $constraint->value;

				return count($expectedVersions) === 0 || in_array(hexdec($uuid[14]), $expectedVersions, true);
			}
		};
	}
}
