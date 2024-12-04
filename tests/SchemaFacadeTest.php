<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\SchemaFacade;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SchemaFacade::class)]
final class SchemaFacadeTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$schema = new SchemaFacade('test');

		$this->assertInstanceOf(SchemaFacade::class, $schema);
	}
}
