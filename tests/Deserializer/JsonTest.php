<?php
declare(strict_types=1);

namespace Meraki\Schema\Deserializer;

use Meraki\Schema\Deserialization\Deserializer;
use Meraki\Schema\Deserialization\JsonDeserializer;
use Meraki\Schema\SchemaDeserializerTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JsonDeserializer::class)]
final class JsonTest extends SchemaDeserializerTestCase
{
	public function createDeserializer(): Deserializer
	{
		return new JsonDeserializer();
	}
}
