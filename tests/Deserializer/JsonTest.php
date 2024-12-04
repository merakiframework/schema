<?php
declare(strict_types=1);

namespace Meraki\Schema\Deserializer;

use Meraki\Schema\SchemaDeserializer;
use Meraki\Schema\Deserializer\Json;
use Meraki\Schema\SchemaDeserializerTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Json::class)]
final class JsonTest extends SchemaDeserializerTestCase
{
	public function createDeserializer(): SchemaDeserializer
	{
		return new Json();
	}
}
