<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Serialization\JsonSerializer;
use Meraki\Schema\Serialization\Serializer;
use Meraki\Schema\SchemaSerializerTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JsonSerializer::class)]
final class JsonTest extends SchemaSerializerTestCase
{
	public function createSerializer(): Serializer
	{
		return new JsonSerializer();
	}
}
