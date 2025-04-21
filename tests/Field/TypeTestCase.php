<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Type;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('field')]
#[CoversClass(Type::class)]
abstract class TypeTestCase extends TestCase
{
	abstract protected function createType(): Type;

	#[Test]
	public function it_is_a_field_type(): void
	{
		$type = $this->createType();

		$this->assertInstanceOf(Type::class, $type);
	}
}
