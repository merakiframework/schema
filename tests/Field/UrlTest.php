<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Url;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, DataProvider};

#[CoversClass(Url::class)]
final class UrlTest extends FieldTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$url = $this->createField();

		$this->assertInstanceOf(Url::class, $url);
	}

	#[Test]
	#[DataProvider('validAbsoluteUrls')]
	public function it_validates_valid_absolute_urls(string $url): void
	{
		$field = $this->createField();
		$field->input($url);

		$result = $field->validate()->valueValidationResult;

		$this->assertTrue($result->passed());
	}

	#[Test]
	#[DataProvider('invalidAbsoluteUrls')]
	public function it_does_not_validate_invalid_absolute_urls(mixed $url): void
	{
		$field = $this->createField();
		$field->input($url);

		$result = $field->validate()->valueValidationResult;

		$this->assertTrue($result->failed());
	}

	#[Test]
	public function min_constraint_passes_when_met(): void
	{
		$field = $this->createField();
		$field->input('https://example.com');

		$field->minLengthOf(1);

		$result = $field->validate()->constraintValidationResults;

		$this->assertTrue($result->allPassed());
	}

	#[Test]
	public function max_constraint_fails_when_not_met(): void
	{
		$field = $this->createField();
		$field->input('https://example.com');

		$field->maxLengthOf(5);

		$result = $field->validate()->constraintValidationResults;

		$this->assertTrue($result->failed());
	}

	public function createField(): Url
	{
		return new Url(new Attribute\Name('website'));
	}

	public function getExpectedType(): string
	{
		return 'url';
	}

	public function getValidValue(): mixed
	{
		return 'https://example.com';
	}

	public function getInvalidValue(): mixed
	{
		return false;
	}

	public function createValidConstraintForValidValue(): ?Constraint
	{
		return new Attribute\Min(1);
	}

	public function createInvalidConstraintForValidValue(): ?Constraint
	{
		return new Attribute\Max(5);
	}

	public static function validAbsoluteUrls(): array
	{
		return [
			'just the domain' => ['https://example.com'],
			'with port' => ['https://example.com:8080'],
			'with path' => ['https://example.com/path/to/resource'],
			'with query string' => ['https://example.com?query=string'],
			'with fragment' => ['https://example.com#fragment'],
			'with query string and fragment' => ['https://example.com?query=string#fragment'],
			'with port, path, query string, and fragment' => ['https://example.com:8080/path/to/resource?query=string#fragment'],
			'with user info (username only)' => ['https://user@example.com'],
			'with user info (username and password)' => ['https://user:abc123@example.com'],
		];
	}

	public static function invalidAbsoluteUrls(): array
	{
		return [
			// 'no scheme' => ['example.com'],
			// 'no authority' => ['https:/path/to/resource'],
			// 'no scheme, authority, query string, or fragment' => [''],
			'no scheme, authority, path, query string, or fragment (null)' => [null],
			'no scheme, authority, path, query string, or fragment (boolean)' => [false],
			'no scheme, authority, path, query string, or fragment (signed integer)' => [0],
			'no scheme, authority, path, query string, or fragment (array)' => [[]],
			'no scheme, authority, path, query string, or fragment (object)' => [new \stdClass()],
		];
	}
}
