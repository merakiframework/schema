<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Set;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('field')]
#[CoversClass(Set::class)]
final class SetTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$set = new Set();

		$this->assertInstanceOf(Set::class, $set);
	}

	#[Test]
	public function a_new_instance_is_empty(): void
	{
		$set = new Set();

		$this->assertCount(0, $set);
		$this->assertEmpty($set);
		$this->assertEquals([], $set->__toArray());
		$this->assertTrue($set->isEmpty());
	}
}
