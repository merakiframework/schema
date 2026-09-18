<?php
declare(strict_types=1);

namespace Meraki\Schema\Message\Mf2;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The container a pack is written in: `key = message`, `#` for a comment, `@` for metadata.
 *
 * Line-based and unforgiving, because the resource format is the one part of this that is ahead of
 * a finished specification. Accepting only what is certain means a pack written today cannot come
 * to depend on something that moves.
 */
#[Group('messages')]
#[CoversClass(Resource::class)]
#[CoversClass(BadResource::class)]
final class ResourceTest extends TestCase
{
	#[Test]
	public function it_reads_entries_and_metadata(): void
	{
		$resource = Resource::parse(<<<'MFR'
			# A comment, ignored.
			@locale = en

			minLength = Use at least {$bound} characters.
			maxLength = Use no more than {$bound} characters.
			MFR);

		$this->assertSame('en', $resource->locale);
		$this->assertSame('Use at least {$bound} characters.', $resource->get('minLength'));
		$this->assertSame('Use no more than {$bound} characters.', $resource->get('maxLength'));
	}

	#[Test]
	public function a_key_nobody_wrote_is_null_rather_than_an_error(): void
	{
		// A pack covering ninety constraints out of a hundred is a useful pack. The ten it misses
		// leave a consumer with no sentence, which is the documented behaviour.
		$this->assertNull(Resource::parse("@locale = en\nminLength = x")->get('maxLength'));
	}

	#[Test]
	public function a_message_may_contain_an_equals_sign(): void
	{
		// Split on the *first* one: the rest belongs to the sentence.
		$this->assertSame('a = b', Resource::parse("@locale = en\nk = a = b")->get('k'));
	}

	#[Test]
	public function it_refuses_a_file_with_no_locale(): void
	{
		// Without it a file cannot say what language it is in, and the file name is a claim rather
		// than a statement.
		$this->expectException(BadResource::class);
		$this->expectExceptionMessageMatches('/@locale/');

		Resource::parse('minLength = x');
	}

	#[Test]
	public function it_refuses_a_key_defined_twice(): void
	{
		// One of the two lines does nothing and which one is invisible. Overriding a message is
		// what a variant file is for, and that is visible because it is a different file.
		$this->expectException(BadResource::class);

		Resource::parse("@locale = en\nminLength = a\nminLength = b");
	}

	#[Test]
	public function it_refuses_a_line_that_is_not_a_key_and_a_message(): void
	{
		$this->expectException(BadResource::class);

		Resource::parse("@locale = en\nthis is just prose");
	}

	#[Test]
	public function refusing_names_the_line(): void
	{
		try {
			Resource::parse("@locale = en\n\nminLength = a\nbroken", 'en.mfr');
		} catch (BadResource $e) {
			$this->assertSame(4, $e->sourceLine);
			$this->assertStringContainsString('en.mfr:4', $e->getMessage());

			return;
		}

		$this->fail('Expected a BadResource.');
	}

	#[Test]
	public function merging_lays_one_resource_over_another(): void
	{
		// How a variant works: `en_AU` is `en` plus the handful of things Australia says
		// differently, and everything it does not mention it inherits.
		$base = Resource::parse("@locale = en\npart.postal_code = postal code\npart.country = country");
		$variant = Resource::parse("@locale = en_AU\npart.postal_code = postcode");

		$merged = $base->mergedWith($variant);

		$this->assertSame('postcode', $merged->get('part.postal_code'));
		$this->assertSame('country', $merged->get('part.country'));
		$this->assertSame('en_AU', $merged->locale);
	}

	#[Test]
	public function merging_does_not_change_either_side(): void
	{
		$base = Resource::parse("@locale = en\nk = base");
		$base->mergedWith(Resource::parse("@locale = en_AU\nk = variant"));

		$this->assertSame('base', $base->get('k'));
	}

	#[Test]
	public function a_missing_file_is_refused_by_name(): void
	{
		$this->expectException(BadResource::class);

		Resource::fromFile(__DIR__ . '/no-such-pack/en.mfr');
	}
}
