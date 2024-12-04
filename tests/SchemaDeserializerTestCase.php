<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\SchemaDeserializer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

abstract class SchemaDeserializerTestCase extends TestCase
{

	#[Test]
	public function it_exists(): void
	{
		$deserializer = $this->createDeserializer();

		$this->assertInstanceOf(SchemaDeserializer::class, $deserializer);
	}

	#[Test]
	public function it_is_a_schema_deserializer(): void
	{
		$json = $this->createDeserializer();

		$this->assertInstanceOf(SchemaDeserializer::class, $json);
	}

	#[Test]
	public function it_throws_exception_if_schema_has_no_name(): void
	{
		$json = $this->createDeserializer();
		$jsonString = '{}';

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Schema must have a name.');

		$schema = $json->deserialize($jsonString);
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

	public static function invalidSchemas(): array
	{
		return [
			'array' => ['[]', 'Expected an object: got array.'],
			'string' => ['"string"', 'Expected an object: got string.'],
			'number - integer' => ['1', 'Expected an object: got integer.'],
			'number - float' => ['1.0', 'Expected an object: got double.'],
			'literal - null' => ['null', 'Expected an object: got NULL.'],
			'literal - true' => ['true', 'Expected an object: got boolean.'],
			'literal - false' => ['false', 'Expected an object: got boolean.'],
		];
	}

	abstract public function createDeserializer(): SchemaDeserializer;
}
