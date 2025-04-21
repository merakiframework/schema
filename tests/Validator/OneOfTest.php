<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\ValidatorName;
use Meraki\Schema\ValidatorTestCase;
use Meraki\Schema\Validator\OneOf;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('validation')]
#[CoversClass(OneOf::class)]
final class OneOfTest extends ValidatorTestCase
{
	public function createValidator(): OneOf
	{
		return new OneOf(['foo', 'bar']);
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$validator = $this->createValidator();

		$name = $validator->name;

		$this->assertTrue($name->equals(new ValidatorName('allowed_values')));
	}

	#[Test]
	public function it_contains_the_allowed_values(): void
	{
		$validator = $this->createValidator();

		$this->assertTrue($validator->contains('foo'));
		$this->assertTrue($validator->contains('bar'));
	}

	#[Test]
	public function it_does_not_contain_disallowed_values(): void
	{
		$validator = $this->createValidator();

		$this->assertFalse($validator->contains('baz'));
		$this->assertFalse($validator->contains('qux'));
	}
}
