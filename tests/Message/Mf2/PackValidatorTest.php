<?php
declare(strict_types=1);

namespace Meraki\Schema\Message\Mf2;

use Meraki\Schema\Message\Vocabulary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What stands in for a compiler when a package contains no code.
 *
 * A language pack is `.mfr` files and nothing else, which is what lets a Rust or JavaScript
 * implementation read the same wording. The cost is that nothing about it can be type-checked, so
 * this reads the vocabulary off the library's own classes and checks the pack against it.
 */
#[Group('messages')]
#[CoversClass(PackValidator::class)]
#[CoversClass(Vocabulary::class)]
final class PackValidatorTest extends TestCase
{
	private const PACKS = __DIR__ . '/../../fixtures/lang';

	private PackValidator $validator;

	protected function setUp(): void
	{
		$this->validator = new PackValidator();
	}

	#[Test]
	public function a_sound_pack_has_nothing_wrong_with_it(): void
	{
		$this->assertSame([], $this->validator->check(self::PACKS . '/basic'));
	}

	#[Test]
	public function it_refuses_a_locale_that_disagrees_with_the_file_name(): void
	{
		// The file would never be loaded: lookup goes by name, so a @locale saying something else
		// makes the whole file unreachable.
		$problems = $this->validator->check(self::PACKS . '/mismatched');

		$this->assertCount(1, $problems);
		$this->assertStringContainsString('@locale', $problems[0]);
	}

	#[Test]
	public function it_refuses_a_variant_with_no_base(): void
	{
		// `fr_CA.mfr` claims by its name to be a variation of `fr`. With no `fr.mfr` the claim is
		// false, and anybody asking for plain French gets nothing.
		$problems = $this->validator->check(self::PACKS . '/orphan');

		$this->assertCount(1, $problems);
		$this->assertStringContainsString('fr.mfr', $problems[0]);
	}

	#[Test]
	public function a_directory_with_no_resources_is_not_a_pack(): void
	{
		$this->assertNotSame([], $this->validator->check(__DIR__));
	}

	#[Test]
	public function a_directory_that_does_not_exist_says_so(): void
	{
		$this->assertNotSame([], $this->validator->check(self::PACKS . '/no-such-pack'));
	}

	#[Test]
	public function it_catches_a_key_the_library_never_asks_for(): void
	{
		// A typo, or wording for a constraint that was renamed two releases ago. Both are invisible
		// at runtime, because an unknown key is simply never looked up.
		$problems = $this->check("@locale = en\nminLenght = Use at least {\$bound} characters.");

		$this->assertCount(1, $problems);
		$this->assertStringContainsString('minLenght', $problems[0]);
	}

	#[Test]
	public function it_catches_a_variable_nothing_will_fill(): void
	{
		// The mistake a translator is most likely to make, and the one that is hardest to notice:
		// the sentence reads perfectly right up until it runs.
		$problems = $this->check("@locale = en\nminLength = Use at least {\$minimum} characters.");

		$this->assertCount(1, $problems);
		$this->assertStringContainsString('$minimum', $problems[0]);
	}

	#[Test]
	public function it_catches_a_variable_used_where_that_key_has_none(): void
	{
		// `kind.*` and `part.*` are the raw material a message is assembled from, so nothing is
		// available to them yet.
		$problems = $this->check("@locale = en\nkind.Text = a {\$kind}");

		$this->assertCount(1, $problems);
		$this->assertStringContainsString('$kind', $problems[0]);
	}

	#[Test]
	public function it_catches_a_message_reaching_for_an_unimplemented_feature(): void
	{
		// The check that keeps the swap to a real MF2 implementation safe. A pack reaching for
		// plurals fails a build on the day it is written rather than a form months later.
		$problems = $this->check("@locale = en\nminLength = .match {\$bound} * {{too short}}");

		$this->assertCount(1, $problems);
		$this->assertStringContainsString('selection', $problems[0]);
	}

	#[Test]
	public function a_shape_message_may_not_name_a_bound(): void
	{
		// Nothing was read, so there is no constraint and no limit to interpolate.
		$problems = $this->check("@locale = en\nshape.missing = Required, at least {\$bound}.");

		$this->assertCount(1, $problems);
		$this->assertStringContainsString('$bound', $problems[0]);
	}

	#[Test]
	public function coverage_is_reported_rather_than_refused(): void
	{
		// A pack covering ninety constraints out of a hundred is worth shipping. The ten it misses
		// leave a consumer with no sentence, which is documented behaviour rather than a failure.
		$missing = $this->validator->missing(self::PACKS . '/basic');

		$this->assertSame([], $this->validator->check(self::PACKS . '/basic'));
		$this->assertArrayHasKey('en', $missing);
		$this->assertContains('minStrength', $missing['en']);
	}

	#[Test]
	public function a_variant_inherits_coverage_from_its_base(): void
	{
		$missing = $this->validator->missing(self::PACKS . '/basic');

		$this->assertArrayHasKey('en-AU', $missing);
		$this->assertNotContains('minLength', $missing['en-AU']);
	}

	#[Test]
	public function the_vocabulary_is_read_from_the_classes_rather_than_kept_as_a_list(): void
	{
		// So it cannot go stale. A constraint added to a field is a key a pack may define the same
		// day, with nothing here to update.
		$this->assertContains('minLength', Vocabulary::constraintNames());
		$this->assertContains('Address', Vocabulary::kinds());
		$this->assertContains('postal_code', Vocabulary::partNames());
		$this->assertContains('Address.postal_code.postalCodeFormat', Vocabulary::keys());
	}

	#[Test]
	public function the_ladder_a_translator_may_write_is_the_ladder_the_translator_reads(): void
	{
		// Every rung Mf2Translator tries has to be a key the validator accepts, or a pack doing the
		// right thing would fail its own build.
		foreach ([
			'Address.postal_code.postalCodeFormat',
			'Address.postalCodeFormat',
			'postal_code.postalCodeFormat',
			'postalCodeFormat',
			'Text.shape.missing',
			'shape.missing',
		] as $key) {
			$this->assertNotNull(Vocabulary::variablesFor($key), $key);
		}
	}

	/** @return list<string> */
	private function check(string $source): array
	{
		$directory = sys_get_temp_dir() . '/meraki-pack-' . bin2hex(random_bytes(6));

		mkdir($directory);
		file_put_contents($directory . '/en.mfr', $source);

		try {
			return $this->validator->check($directory);
		} finally {
			unlink($directory . '/en.mfr');
			rmdir($directory);
		}
	}
}
