<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Validator;
use Meraki\Schema\ValidatorTestCase;
use Meraki\Schema\Validator\AlwaysFails;
use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(AlwaysFails::class)]
final class AlwaysFailsTest extends ValidatorTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$validator = $this->createValidator();

		$this->assertInstanceOf(AlwaysFails::class, $validator);
	}

	#[Test]
	public function it_never_passes(): void
	{
		$field = new Field\Text(new Attribute\Name('test'));
		$validator = new AlwaysFails();
		$constraint = new Attribute\Min(1);

		$passes = $validator->validate($constraint, $field);

		$this->assertFalse($passes);
	}

	public function createValidator(): Validator
	{
		return new AlwaysFails();
	}
}
