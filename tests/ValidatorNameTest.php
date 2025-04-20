<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Validator;
use Meraki\Schema\ValidatorName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[Group('validation')]
#[CoversClass(ValidatorName::class)]
final class ValidatorNameTest extends TestCase
{
	#[Test]
	#[DataProvider('invalidNames')]
	public function throws_on_invalid_name(string $name): void
	{
		$this->expectException(\InvalidArgumentException::class);

		new ValidatorName($name);
	}

	#[Test]
	#[DataProvider('namesThatCanBeNormalized')]
	public function can_normalize_name(string $unnormalizedName, string $expectedNormalizedName): void
	{
		$name = ValidatorName::normalize($unnormalizedName);

		self::assertSame($expectedNormalizedName, (string)$name);
	}

	#[Test]
	public function can_compare_names(): void
	{
		$name1 = new ValidatorName('name');
		$name2 = new ValidatorName('name');

		self::assertTrue($name1->equals($name2));
		self::assertFalse($name1->equals(new ValidatorName('other')));
	}

	public static function namesThatCanBeNormalized(): array
	{
		return [
			'lowercase' => ['name', 'name'],
			'uppercase' => ['NAME', 'name'],
			'mixed case' => ['NaMe', 'name'],
			'with numbers' => ['Foo123', 'foo123'],
			'with underscores' => ['foo_bar', 'foo_bar'],
		];
	}

	public static function invalidNames(): array
	{
		return [
			'empty' => [''],
			'starts with number' => ['1name'],
			'starts with underscore' => ['_name'],
			'contains uppercase letter' => ['Name'],
			'contains special character' => ['name!'],
			'contains space' => ['name name'],
			'contains hyphen' => ['name-name'],
			'contains dot' => ['name.name'],
			'contains slash' => ['name/name'],
			'contains backslash' => ['name\\name'],
			'contains colon' => ['name:name'],
		];
	}
}
