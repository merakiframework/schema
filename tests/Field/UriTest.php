<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Uri;
use Meraki\Schema\Property;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(Uri::class)]
final class UriTest extends FieldTestCase
{
	public function createSubject(): Uri
	{
		return new Uri(new Property\Name('test'));
	}

	public function createField(): Uri
	{
		return $this->createSubject();
	}

	#[Test]
	#[DataProvider('validAbsoluteUris')]
	public function it_validates_valid_absolute_urls(string $uri): void
	{
		$sut = $this->createSubject();

		$result = $sut->validate($uri);

		$this->assertConstraintValidationResultPassed('type', $result);
	}

	#[Test]
	#[DataProvider('invalidAbsoluteUris')]
	public function it_does_not_validate_invalid_absolute_urls(mixed $uri): void
	{
		$sut = $this->createSubject();

		$result = $sut->validate($uri);

		$this->assertConstraintValidationResultFailed('type', $result);
	}

	#[Test]
	public function min_constraint_passes_when_met(): void
	{
		$sut = $this->createSubject()
			
			->minLengthOf(12);

		$result = $sut->validate('https://example.com');

		$this->assertConstraintValidationResultPassed('minLength', $result);
	}

	#[Test]
	public function min_constraint_fails_when_not_met(): void
	{
		$sut = $this->createSubject()
			
			->minLengthOf(30);

		$result = $sut->validate('https://example.com');

		$this->assertConstraintValidationResultFailed('minLength', $result);
	}

	#[Test]
	public function max_constraint_passes_when_met(): void
	{
		$sut = $this->createSubject()
			
			->maxLengthOf(20);

		$result = $sut->validate('https://example.com');

		$this->assertConstraintValidationResultPassed('maxLength', $result);
	}

	#[Test]
	public function max_constraint_fails_when_not_met(): void
	{
		$sut = $this->createSubject()
			
			->maxLengthOf(12);

		$result = $sut->validate('https://example.com');

		$this->assertConstraintValidationResultFailed('maxLength', $result);
	}

	public static function validAbsoluteUris(): array
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

	public static function invalidAbsoluteUris(): array
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


	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$sut = $this->createSubject();

		$this->assertNull($sut->defaultValue->unwrap());
	}
}
