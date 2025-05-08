<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Secret;

use Meraki\Schema\Field\Secret\Policy;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('field')]
#[CoversClass(Secret::class)]
abstract class PolicyTestCase extends TestCase
{
	abstract public function createPolicy(): Policy;

	#[Test]
	public function it_has_a_name(): void
	{
		$policy = $this->createPolicy();

		$this->assertObjectHasProperty('name', $policy);
		$this->assertIsString($policy->name);
	}

	#[Test]
	public function it_is_stringable(): void
	{
		$policy = $this->createPolicy();

		$this->assertIsString((string)$policy);
	}
}
