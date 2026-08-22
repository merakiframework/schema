<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Facade;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The field previously accepted any string at all: every group in its pattern was
 * optional, so it collapsed to is_string(). Anything rendered back out of it was an
 * open-redirect or XSS hazard.
 */
#[Group('field')]
#[CoversClass(Uri::class)]
final class UriValidationTest extends TestCase
{
	/** @return array<string, array{string}> */
	public static function notUris(): array
	{
		return [
			'prose'          => ['not a url at all !!'],
			'spaces'         => ['http://example .com'],
			'empty'          => [''],
			'bare backslash' => ["http://example.com/\\"],
			'control char'   => ["http://example.com/\x00"],
		];
	}

	/** @return array<string, array{string}> */
	public static function uris(): array
	{
		return [
			'https'    => ['https://example.com/a?b=1#c'],
			'http'     => ['http://example.com'],
			'urn'      => ['urn:isbn:0451450523'],
			'mailto'   => ['mailto:someone@example.com'],
			'relative' => ['/relative/path'],
			'query'    => ['https://example.com/search?q=a+b'],
		];
	}

	private function schema(): Facade
	{
		$schema = new Facade('link');
		$schema->addUriField('url');

		return $schema;
	}

	#[Test]
	#[DataProvider('notUris')]
	public function a_value_that_is_not_a_uri_fails(string $value): void
	{
		$this->assertTrue($this->schema()->validate(['url' => $value])->anyFailed());
	}

	#[Test]
	#[DataProvider('uris')]
	public function a_well_formed_uri_passes(string $value): void
	{
		$this->assertFalse($this->schema()->validate(['url' => $value])->anyFailed());
	}

	#[Test]
	public function a_scheme_allowlist_can_be_declared(): void
	{
		$schema = new Facade('link');
		$schema->addUriField('url')->allowSchemes('https');

		$this->assertFalse($schema->validate(['url' => 'https://example.com'])->anyFailed());
		$this->assertTrue($schema->validate(['url' => 'http://example.com'])->anyFailed());
	}

	#[Test]
	public function a_scheme_allowlist_shuts_out_the_dangerous_ones(): void
	{
		// The reason the allowlist exists: anything rendered back into a page or followed
		// by a redirect must not be able to carry script or inline content.
		$schema = new Facade('link');
		$schema->addUriField('url')->allowSchemes('http', 'https');

		$this->assertTrue($schema->validate(['url' => 'javascript:alert(document.cookie)'])->anyFailed());
		$this->assertTrue($schema->validate(['url' => 'data:text/html;base64,PHNjcmlwdD4='])->anyFailed());
	}

	#[Test]
	public function without_an_allowlist_any_scheme_is_accepted(): void
	{
		// Deliberate: a URI field is not only ever a web link. Declaring the allowlist is
		// how a caller says otherwise.
		$this->assertFalse($this->schema()->validate(['url' => 'ftp://example.com'])->anyFailed());
	}

	#[Test]
	public function length_constraints_still_apply(): void
	{
		$schema = new Facade('link');
		$schema->addUriField('url')->maxLengthOf(20);

		$this->assertTrue($schema->validate(['url' => 'https://example.com/a/very/long/path'])->anyFailed());
		$this->assertFalse($schema->validate(['url' => 'https://example.com'])->anyFailed());
	}

	#[Test]
	public function a_non_string_fails(): void
	{
		$this->assertTrue($this->schema()->validate(['url' => 123])->anyFailed());
	}
}
