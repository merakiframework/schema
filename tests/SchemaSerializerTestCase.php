<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\SchemaSerializer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

abstract class SchemaSerializerTestCase extends TestCase
{

	#[Test]
	public function it_exists(): void
	{
		$serializer = $this->createSerializer();

		$this->assertInstanceOf(SchemaSerializer::class, $serializer);
	}

	abstract public function createSerializer(): SchemaSerializer;
}
