<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\AttributeTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Version::class)]
final class VersionTest extends AttributeTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$this->assertTrue(class_exists(Version::class));
	}

	#[Test]
	public function allows_any_integer_by_default(): void
	{
		$version = new Version();

		$this->assertTrue($version->isAllowed(4));
	}

	#[Test]
	public function it_allows_a_single_integer(): void
	{
		$version = new Version([1]);

		$this->assertEquals([1], $version->value);
		$this->assertTrue($version->isAllowed(1));
	}

	#[Test]
	public function it_allows_multiple_integers(): void
	{
		$version = new Version([1, 2, 3]);

		$this->assertEquals([1, 2, 3], $version->value);
		$this->assertTrue($version->isAllowed(1));
		$this->assertTrue($version->isAllowed(2));
		$this->assertTrue($version->isAllowed(3));
	}

	#[Test]
	public function it_can_add_a_single_integer(): void
	{
		$version = new Version([1]);
		$version->add(2);

		$this->assertEquals([1, 2], $version->value);
		$this->assertTrue($version->isAllowed(1));
		$this->assertTrue($version->isAllowed(2));
	}

	#[Test]
	public function it_can_add_multiple_integers(): void
	{
		$version = new Version([1]);
		$version->add(2, 3);

		$this->assertEquals([1, 2, 3], $version->value);
		$this->assertTrue($version->isAllowed(1));
		$this->assertTrue($version->isAllowed(2));
		$this->assertTrue($version->isAllowed(3));
	}

	#[Test]
	public function it_does_not_add_duplicate_integers(): void
	{
		$version = new Version([1, 2]);
		$version->add(2, 3);

		$this->assertCount(3, $version->value);
		$this->assertEquals([1, 2, 3], $version->value);
	}

	#[Test]
	public function it_does_not_allow_a_value_not_in_the_list(): void
	{
		$version = new Version([1, 2, 3]);

		$this->assertFalse($version->isAllowed(4));
	}

	#[Test]
	public function allows_any_string_by_default(): void
	{
		$version = new Version();

		$this->assertTrue($version->isAllowed('1.2'));
	}

	#[Test]
	public function it_allows_a_single_string(): void
	{
		$version = new Version(['1.2']);

		$this->assertEquals(['1.2'], $version->value);
		$this->assertTrue($version->isAllowed('1.2'));
	}

	#[Test]
	public function it_allows_multiple_strings(): void
	{
		$version = new Version(['1.2', '2.3', '3.4']);

		$this->assertEquals(['1.2', '2.3', '3.4'], $version->value);
		$this->assertTrue($version->isAllowed('1.2'));
		$this->assertTrue($version->isAllowed('2.3'));
		$this->assertTrue($version->isAllowed('3.4'));
	}

	#[Test]
	public function it_can_add_a_single_string(): void
	{
		$version = new Version(['1.2']);
		$version->add('2.3');

		$this->assertEquals(['1.2', '2.3'], $version->value);
		$this->assertTrue($version->isAllowed('1.2'));
		$this->assertTrue($version->isAllowed('2.3'));
	}

	#[Test]
	public function it_can_add_multiple_strings(): void
	{
		$version = new Version(['1.2']);
		$version->add('2.3', '3.4');

		$this->assertEquals(['1.2', '2.3', '3.4'], $version->value);
		$this->assertTrue($version->isAllowed('1.2'));
		$this->assertTrue($version->isAllowed('2.3'));
		$this->assertTrue($version->isAllowed('3.4'));
	}

	#[Test]
	public function it_does_not_add_duplicate_strings(): void
	{
		$version = new Version(['1.2', '2.3']);
		$version->add('2.3', '3.4');

		$this->assertCount(3, $version->value);
		$this->assertEquals(['1.2', '2.3', '3.4'], $version->value);
	}

	#[Test]
	public function it_does_not_allow_a_value_not_in_the_list_of_strings(): void
	{
		$version = new Version(['1.2', '2.3', '3.4']);

		$this->assertFalse($version->isAllowed('4.5'));
	}

	public function getExpectedName(): string
	{
		return 'version';
	}

	public function getExpectedValue(): mixed
	{
		return [1];
	}

	public function createAttribute(): Attribute
	{
		return new Version([1]);
	}
}
