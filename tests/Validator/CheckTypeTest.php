<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\Field\Type as FieldType;
use Meraki\Schema\Validator;
use Meraki\Schema\ValidatorName;
use Meraki\Schema\ValidatorTestCase;
use Meraki\Schema\Validator\CheckType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('validation')]
#[CoversClass(CheckType::class)]
final class CheckTypeTest extends ValidatorTestCase
{
	public function createValidator(): CheckType
	{
		return new CheckType($this->mockFieldType('text', true));
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$validator = $this->createValidator();

		$name = $validator->name;

		$this->assertTrue($name->equals(new ValidatorName('type')));
	}

	public function mockFieldType(string $name, bool $acceptValue): FieldType
	{
		return new class ($name, $acceptValue) implements FieldType {
			public function __construct(public readonly string $name, private bool $acceptValue) {}
			public function accepts(mixed $value): bool { return $this->acceptValue; }
			public function getValidator(): CheckType { return new CheckType($this); }
		};
	}
}
