<?php
declare(strict_types=1);

namespace Meraki\Form\Deserializer;

use Meraki\Form\Schema;
use Meraki\Form\SchemaDeserializer;
use Meraki\Form\Deserializer\Json;
use Meraki\Form\Constraint\Factory as ConstraintFactory;
use Meraki\Form\Field\Factory as FieldFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Json::class)]
final class JsonTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$json = $this->createDeserializer();

		$this->assertInstanceOf(Json::class, $json);
	}

	#[Test]
	public function it_is_a_schema_deserializer(): void
	{
		$json = $this->createDeserializer();

		$this->assertInstanceOf(SchemaDeserializer::class, $json);
	}

	#[Test]
	public function it_deserializes_an_empty_schema(): void
	{
		$json = $this->createDeserializer();
		$jsonString = '{}';

		$schema = $json->deserialize($jsonString);

		$this->assertInstanceOf(Schema::class, $schema);
	}

	#[Test]
	#[DataProvider('invalidSchemas')]
	public function it_cannot_deserialize_anything_other_than_a_json_object(string $jsonString, string $errorMessage): void
	{
		$json = $this->createDeserializer();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage($errorMessage);

		$json->deserialize($jsonString);
	}

	#[Test]
	public function it_throws_error_when_trying_to_deserialize_nothing(): void
	{
		$json = $this->createDeserializer();

		$this->expectException(\JsonException::class);
		$this->expectExceptionMessage('Syntax error');

		$json->deserialize('');
	}

	private function createDeserializer(): SchemaDeserializer
	{
		return new Json(new FieldFactory(), ConstraintFactory::useBundled());
	}

	public static function invalidSchemas(): array
	{
		return [
			'array' => ['[]', 'Expected a JSON object: got array.'],
			'string' => ['"string"', 'Expected a JSON object: got string.'],
			'number - integer' => ['1', 'Expected a JSON object: got integer.'],
			'number - float' => ['1.0', 'Expected a JSON object: got double.'],
			'literal - null' => ['null', 'Expected a JSON object: got NULL.'],
			'literal - true' => ['true', 'Expected a JSON object: got boolean.'],
			'literal - false' => ['false', 'Expected a JSON object: got boolean.'],
		];
	}
}
