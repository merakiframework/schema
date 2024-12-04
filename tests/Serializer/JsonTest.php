<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Serializer\Json;
use Meraki\Schema\SchemaSerializer;
use Meraki\Schema\SchemaSerializerTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Json::class)]
final class JsonTest extends SchemaSerializerTestCase
{
	public function createSerializer(): SchemaSerializer
	{
		return new Json();
	}
}
