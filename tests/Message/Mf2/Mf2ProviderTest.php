<?php
declare(strict_types=1);

namespace Meraki\Schema\Message\Mf2;

use InvalidArgumentException;
use Meraki\Schema\Message\Silence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Finding the right wording for the language a request asked for.
 *
 * A pack is a directory of `.mfr` files and nothing else — no PHP, no autoloader — so getting one
 * onto disk is Composer's job and this only ever sees a path. That is also what makes this testable
 * against a fixture folder, which a provider that fetched from a repository could not be.
 */
#[Group('messages')]
#[CoversClass(Mf2Provider::class)]
#[CoversClass(Mf2Translator::class)]
#[CoversClass(Silence::class)]
final class Mf2ProviderTest extends TestCase
{
	private const PACKS = __DIR__ . '/../../fixtures/lang';

	#[Test]
	public function it_lists_every_locale_a_pack_offers(): void
	{
		$this->assertSame(['en', 'en-AU'], Mf2Provider::fromDirectory(self::PACKS . '/basic')->locales);
	}

	/** @return iterable<string, array{string}> */
	public static function spellings(): iterable
	{
		// A request carries `en-AU` from Accept-Language and a file is conventionally named
		// `en_AU.mfr`. Nobody should have to know which of the two this library happened to pick.
		yield 'hyphen' => ['en-AU'];
		yield 'underscore' => ['en_AU'];
		yield 'lower case' => ['en-au'];
		yield 'upper case' => ['EN-AU'];
		yield 'surrounded by space' => [' en-AU '];
	}

	#[Test]
	#[DataProvider('spellings')]
	public function a_tag_is_matched_however_it_is_spelled(string $tag): void
	{
		$this->assertTrue(Mf2Provider::fromDirectory(self::PACKS . '/basic')->supports($tag));
	}

	#[Test]
	public function a_variant_inherits_everything_it_does_not_override(): void
	{
		$resource = Mf2Provider::fromDirectory(self::PACKS . '/basic')->merged('en-AU');

		$this->assertNotNull($resource);
		$this->assertSame('postcode', $resource->get('part.postal_code'));
		$this->assertSame('country', $resource->get('part.country'));
	}

	#[Test]
	public function a_variant_is_resolved_even_when_the_request_asks_for_the_base(): void
	{
		$resource = Mf2Provider::fromDirectory(self::PACKS . '/basic')->merged('en');

		$this->assertNotNull($resource);
		$this->assertSame('postal code', $resource->get('part.postal_code'));
	}

	#[Test]
	public function a_language_nobody_has_is_silence_rather_than_an_error(): void
	{
		// The load-bearing one. Validation is language-independent, so a missing translation must
		// never be able to change what a verdict is — and an exception here would do exactly that.
		$provider = Mf2Provider::fromDirectory(self::PACKS . '/basic');

		$this->assertFalse($provider->supports('de-AT'));
		$this->assertInstanceOf(Silence::class, $provider->forLocale('de-AT'));
	}

	#[Test]
	public function silence_keeps_the_tag_that_was_asked_for(): void
	{
		$this->assertSame('de-AT', Mf2Provider::fromDirectory(self::PACKS . '/basic')->forLocale('de-AT')->locale);
	}

	#[Test]
	public function a_translator_reports_the_tag_it_actually_found(): void
	{
		// Not the one that was asked for: a request for `en-NZ` served by a pack with only `en`
		// should be able to say so.
		$provider = Mf2Provider::fromDirectory(self::PACKS . '/basic');

		$this->assertSame('en-AU', $provider->forLocale('en-AU')->locale);
		$this->assertSame('en', $provider->forLocale('en-NZ')->locale);
	}

	#[Test]
	public function a_later_pack_overrides_an_earlier_one_entry_by_entry(): void
	{
		// So an application can change one sentence in a published pack without forking it.
		$provider = Mf2Provider::fromDirectory(self::PACKS . '/basic')->withPack(self::PACKS . '/override');
		$resource = $provider->merged('en');

		$this->assertNotNull($resource);
		$this->assertSame('Too short. We need {$bound}.', $resource->get('minLength'));
		$this->assertSame('Use no more than {$bound} characters.', $resource->get('maxLength'));
	}

	#[Test]
	public function adding_a_pack_leaves_the_provider_it_came_from_alone(): void
	{
		$original = Mf2Provider::fromDirectory(self::PACKS . '/basic');
		$original->withPack(self::PACKS . '/override');

		$this->assertSame('Use at least {$bound} characters.', $original->merged('en')?->get('minLength'));
	}

	#[Test]
	public function a_directory_that_is_not_there_is_refused_where_it_is_registered(): void
	{
		// At wiring time, where somebody can fix it, rather than as empty messages on a request.
		$this->expectException(InvalidArgumentException::class);

		Mf2Provider::fromDirectory(self::PACKS . '/no-such-pack');
	}

	#[Test]
	public function a_package_that_is_not_installed_says_how_to_install_it(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/composer require/');

		Mf2Provider::fromPackage('meraki/no-such-language-pack');
	}

	#[Test]
	public function a_provider_with_no_packs_supports_nothing(): void
	{
		$this->assertSame([], Mf2Provider::empty()->locales);
		$this->assertFalse(Mf2Provider::empty()->supports('en'));
	}
}
