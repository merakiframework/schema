<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Facade;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Facade::class)]
final class SchemaFacadeTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$schema = new Facade('test');

		$this->assertInstanceOf(Facade::class, $schema);
	}
}
