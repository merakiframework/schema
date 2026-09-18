<?php
declare(strict_types=1);

namespace Meraki\Schema\Message\Mf2;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The subset of MF2 the language packs are written in.
 *
 * Most of this file is about what the formatter *refuses*, which is the point. This class is meant
 * to be deleted the day a real MF2 implementation for PHP exists, and that swap is only safe if
 * everything the packs contain renders identically under both. Leniency is the thing that would
 * break it: a message quietly tolerated now is a message that fails later, on somebody's form, in
 * a language nobody on the team reads.
 */
#[Group('messages')]
#[CoversClass(Formatter::class)]
#[CoversClass(BadMessage::class)]
final class FormatterTest extends TestCase
{
	private Formatter $formatter;

	protected function setUp(): void
	{
		$this->formatter = new Formatter();
	}

	#[Test]
	public function it_expands_a_variable(): void
	{
		$this->assertSame(
			'Use at least 8 characters.',
			$this->formatter->format('Use at least {$bound} characters.', ['bound' => '8']),
		);
	}

	#[Test]
	public function it_expands_the_same_variable_more_than_once(): void
	{
		$this->assertSame(
			'a and a',
			$this->formatter->format('{$x} and {$x}', ['x' => 'a']),
		);
	}

	#[Test]
	public function a_message_with_no_placeholders_comes_back_as_it_went_in(): void
	{
		$this->assertSame('This card has expired.', $this->formatter->format('This card has expired.', []));
	}

	/** @return iterable<string, array{string, string}> */
	public static function escapes(): iterable
	{
		$backslash = chr(92);

		yield 'brace open' => [$backslash . '{', '{'];
		yield 'brace close' => [$backslash . '}', '}'];
		yield 'pipe' => [$backslash . '|', '|'];
		yield 'backslash' => [$backslash . $backslash, $backslash];
	}

	#[Test]
	#[DataProvider('escapes')]
	public function it_resolves_the_four_escapes(string $message, string $expected): void
	{
		$this->assertSame($expected, $this->formatter->format($message, []));
	}

	#[Test]
	public function a_literal_carries_whitespace_the_resource_format_would_trim(): void
	{
		// The reason literals are in the subset at all. `list.separator` is ", " including the
		// space, and a `key = value` file cannot express a trailing one.
		$this->assertSame('a, b', $this->formatter->format('a{|, |}b', []));
	}

	#[Test]
	public function a_literal_may_contain_an_escaped_brace(): void
	{
		// The case a naive search for the next "}" gets wrong: it would cut the placeholder at the
		// escaped brace and read the rest of the message as text.
		$this->assertSame('a}b', $this->formatter->format('{|a' . chr(92) . '}b|}', []));
	}

	/** @return iterable<string, array{string}> */
	public static function unsupported(): iterable
	{
		yield 'selection' => ['.match {$n} 1 {{one}} * {{other}}'];
		yield 'a local declaration' => ['.local $x = {$y} {{hello {$x}}}'];
		yield 'an input declaration' => ['.input {$x} {{hello}}'];
		yield 'a quoted pattern' => ['{{hello}}'];
		yield 'a function call' => ['{$count :number}'];
		yield 'a bare function' => ['{:datetime}'];
		yield 'markup open' => ['{#bold}x{/bold}'];
		yield 'an attribute' => ['{@locale}'];
		yield 'an empty placeholder' => ['{}'];
	}

	#[Test]
	#[DataProvider('unsupported')]
	public function it_refuses_what_it_does_not_implement(string $message): void
	{
		$this->expectException(BadMessage::class);

		$this->formatter->assertSupported($message);
	}

	/** @return iterable<string, array{string}> */
	public static function malformed(): iterable
	{
		yield 'an unclosed brace' => ['{unclosed'];
		yield 'a stray closing brace' => ['oops }'];
		yield 'a bad escape' => ['a ' . chr(92) . 'q b'];
		yield 'an unclosed literal' => ['{|never closed}'];
		yield 'an unescaped pipe in a literal' => ['{|a|b|}'];
		yield 'a bare dollar' => ['{$}'];
		yield 'a name starting with a digit' => ['{$1st}'];
	}

	#[Test]
	#[DataProvider('malformed')]
	public function it_refuses_what_is_not_valid(string $message): void
	{
		$this->expectException(BadMessage::class);

		$this->formatter->assertSupported($message);
	}

	#[Test]
	public function it_refuses_a_variable_nothing_supplies(): void
	{
		// The mistake a translator is most likely to make and least likely to notice, because the
		// sentence reads perfectly right up until it runs.
		$this->expectException(BadMessage::class);
		$this->expectExceptionMessageMatches('/\$minimum/');

		$this->formatter->format('{$minimum}', ['bound' => '8']);
	}

	#[Test]
	public function refusing_says_what_is_available(): void
	{
		try {
			$this->formatter->format('{$minimum}', ['bound' => '8', 'kind' => 'text']);
		} catch (BadMessage $e) {
			$this->assertStringContainsString('$bound', $e->getMessage());
			$this->assertStringContainsString('$kind', $e->getMessage());

			return;
		}

		$this->fail('Expected a BadMessage.');
	}

	#[Test]
	public function refusing_a_feature_names_it(): void
	{
		// So the person who has to fix it — a translator, who did not choose this limitation —
		// is told what to stop using rather than that something is "invalid".
		try {
			$this->formatter->assertSupported('{$count :number}');
		} catch (BadMessage $e) {
			$this->assertStringContainsString('functions', $e->getMessage());

			return;
		}

		$this->fail('Expected a BadMessage.');
	}

	#[Test]
	public function it_reports_the_variables_a_message_names(): void
	{
		$this->assertSame(
			['kind', 'bound'],
			$this->formatter->variablesIn('A {$kind} of at least {$bound}, says the {$kind}.'),
		);
	}

	#[Test]
	public function a_literal_names_no_variables(): void
	{
		$this->assertSame([], $this->formatter->variablesIn('{|, |}'));
	}

	#[Test]
	public function checking_and_rendering_agree_about_what_is_allowed(): void
	{
		// Both walk the same scanner, on purpose. A checker written separately would drift from
		// the renderer and the drift would be silent, which is the failure mode a pack's build
		// exists to catch.
		foreach ([...self::unsupported(), ...self::malformed()] as [$message]) {
			$refusedByCheck = false;
			$refusedByFormat = false;

			try {
				$this->formatter->assertSupported($message);
			} catch (BadMessage) {
				$refusedByCheck = true;
			}

			try {
				$this->formatter->format($message, []);
			} catch (BadMessage) {
				$refusedByFormat = true;
			}

			$this->assertSame($refusedByCheck, $refusedByFormat, $message);
		}
	}
}
