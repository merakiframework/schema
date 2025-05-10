<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Secret;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use InvalidArgumentException;

#[Group('field')]
#[CoversClass(PolicyParser::class)]
final class PolicyParserTest extends TestCase
{
	#[Test]
	public function it_returns_an_empty_object_if_nothing_to_parse(): void
	{
		$policy = PolicyParser::parse('', ['key' => ['type' => 'string']]);

		$this->assertInstanceOf(PolicyParser::class, $policy);
	}

	#[Test]
	public function it_throws_for_unknown_keys(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Unknown key 'unknown'");

		PolicyParser::parse('unknown:value', [
			'known' => ['type' => 'string'],
		]);
	}

	#[Test]
	public function it_parses_string_and_returns_array(): void
	{
		$schema = [
			'role' => ['type' => 'string'],
			'level' => ['type' => 'int'],
		];

		$parser = PolicyParser::parse('role:admin;level:5', $schema);

		$this->assertEquals('admin', $parser->get('role'));
		$this->assertEquals(5, $parser->get('level'));
		$this->assertEquals(['role' => 'admin', 'level' => 5], $parser->toArray());
	}

	#[Test]
	public function it_uses_defaults_when_missing(): void
	{
		$schema = [
			'required' => ['type' => 'int', 'default' => 1],
			'optional' => ['type' => 'string', 'default' => 'abc'],
		];

		$parser = PolicyParser::parse('', $schema);

		$this->assertEquals(1, $parser->get('required'));
		$this->assertEquals('abc', $parser->get('optional'));
	}

	#[Test]
	public function it_parses_range_with_nulls_and_validates_bounds(): void
	{
		$schema = [
			'limit' => ['type' => 'range', 'default' => [null, null]],
		];

		$parser = PolicyParser::parse('limit:3,9', $schema);

		$this->assertEquals([3, 9], $parser->get('limit'));
	}

	#[Test]
	public function it_serializes_back_to_string(): void
	{
		$schema = [
			'min' => ['type' => 'int'],
			'max' => ['type' => 'int'],
		];

		$parser = PolicyParser::parse('min:5;max:10', $schema);

		$this->assertEquals('min:5;max:10', (string) $parser);
	}

	#[Test]
	public function it_serializes_range_to_string(): void
	{
		$schema = [
			'range' => ['type' => 'range'],
		];

		$parser = PolicyParser::parse('range:1,5', $schema);

		$this->assertEquals('range:1,5', (string) $parser);
	}

	#[Test]
	public function it_parses_list_of_integers(): void
	{
		$schema = [
			'numbers' => ['type' => 'list', 'item_type' => 'int'],
		];

		$parser = PolicyParser::parse('numbers:1,2,3', $schema);

		$this->assertEquals([1, 2, 3], $parser->get('numbers'));
	}

	#[Test]
	public function it_parses_list_of_strings(): void
	{
		$schema = [
			'tags' => ['type' => 'list', 'item_type' => 'string'],
		];

		$parser = PolicyParser::parse('tags:alpha,beta', $schema);

		$this->assertEquals(['alpha', 'beta'], $parser->get('tags'));
	}

	#[Test]
	public function it_throws_for_non_integer_in_int_list(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("List contains non-integer");

		PolicyParser::parse('numbers:1,two,3', [
			'numbers' => ['type' => 'list', 'item_type' => 'int'],
		]);
	}

	#[Test]
	public function it_throws_for_invalid_int(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid integer");

		PolicyParser::parse('amount:abc', [
			'amount' => ['type' => 'int'],
		]);
	}

	#[Test]
	public function it_throws_for_invalid_range_format(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid range");

		PolicyParser::parse('range:abc,2', [
			'range' => ['type' => 'range'],
		]);
	}

	#[Test]
	public function it_throws_for_range_with_min_greater_than_max(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("min (5) cannot be greater than max (2)");

		PolicyParser::parse('range:5,2', [
			'range' => ['type' => 'range'],
		]);
	}

	#[Test]
	public function it_throws_for_unsupported_type(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Unsupported type 'boolean'");

		PolicyParser::parse('flag:true', [
			'flag' => ['type' => 'boolean'],
		]);
	}

	#[Test]
	public function it_throws_for_unsupported_list_item_type(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Unsupported list item type 'boolean'");

		PolicyParser::parse('items:true,false', [
			'items' => ['type' => 'list', 'item_type' => 'boolean'],
		]);
	}

	#[Test]
	public function it_throws_if_getting_unknown_key(): void
	{
		$parser = PolicyParser::parse('foo:bar', [
			'foo' => ['type' => 'string'],
		]);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Key 'not_exist' not found");

		$parser->get('not_exist');
	}
}
