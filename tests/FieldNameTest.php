<?php
declare(strict_types=1);

namespace Meraki\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

#[Group('field')]
#[CoversClass(FieldName::class)]
final class FieldNameTest extends TestCase
{
	#[Test]
	#[DataProvider('usableNames')]
	public function it_accepts_a_name_that_can_identify_a_field(string $name): void
	{
		$this->assertSame($name, (string) new FieldName($name));
	}

	/** @return array<string, array{string}> */
	public static function usableNames(): array
	{
		return [
			'a word' => ['username'],
			'snake case' => ['email_address'],
			'kebab case' => ['email-address'],
			'digits after the first character' => ['line1'],
			'a leading underscore' => ['_internal'],
			'a single letter' => ['a'],
			'camel case' => ['firstName'],
		];
	}

	#[Test]
	#[DataProvider('unusableNames')]
	public function it_refuses_a_name_that_cannot(string $name): void
	{
		$this->expectException(InvalidArgumentException::class);

		new FieldName($name);
	}

	/** @return array<string, array{string}> */
	public static function unusableNames(): array
	{
		return [
			'nothing' => [''],
			'only whitespace' => ['  '],
			'a leading digit' => ['1line'],
			// Scope-path syntax, which a name must not be mistakable for.
			'a slash' => ['a/b'],
			'a hash' => ['#a'],
			// A dot used to join a composite to its sub-fields. Composites are gone, and with them
			// any reason for a name to contain one.
			'a dot' => ['price.amount'],
			'a space' => ['first name'],
			'punctuation' => ['name!'],
		];
	}

	#[Test]
	public function two_names_spelled_the_same_are_the_same_name(): void
	{
		$this->assertTrue((new FieldName('username'))->equals(new FieldName('username')));
		$this->assertFalse((new FieldName('username'))->equals(new FieldName('user_name')));
	}

	/**
	 * A name is a wire key, so identity is exact.
	 *
	 * This used to fold case, and the intent was sound — a schema holding both `email` and
	 * `Email` leaves a reader guessing which one a message or a scope path meant. What made it
	 * wrong was that nothing else folded: a payload is keyed exactly, `forField()` matches
	 * exactly, and the outcome bucket in `Definition::against()` is keyed exactly. One comparison
	 * disagreeing with all of them was silent in both directions — a collection template holding
	 * `Name` and `name` built fine and threw on every request, and `thenIgnore('Detail')`
	 * against `detail` passed every check and never applied.
	 *
	 * The intent did not go away; it moved to {@see FieldName::collidesWith()}, which is asked
	 * once where a schema is written. See `two_names_that_differ_only_by_case_cannot_share_a_schema`.
	 */
	#[Test]
	public function case_distinguishes_two_names(): void
	{
		$this->assertFalse((new FieldName('email'))->equals(new FieldName('EMAIL')));
		$this->assertTrue((new FieldName('phoneNumber'))->equals(new FieldName('phoneNumber')));
	}

	#[Test]
	public function two_names_differing_only_by_case_collide(): void
	{
		// Not the same name, but too alike to sit on one schema.
		$this->assertTrue((new FieldName('email'))->collidesWith(new FieldName('EMAIL')));
		$this->assertTrue((new FieldName('email'))->collidesWith(new FieldName('email')));
		$this->assertFalse((new FieldName('email'))->collidesWith(new FieldName('e_mail')));
	}

	#[Test]
	public function only_another_name_can_be_compared_to_one(): void
	{
		// Typed rather than mixed, so comparing a name to a bare string is a mistake the language
		// catches instead of one that quietly answers false.
		$this->expectException(\TypeError::class);

		/** @phpstan-ignore argument.type */
		(new FieldName('username'))->equals('username');
	}

	#[Test]
	public function it_reads_back_as_what_it_was_given(): void
	{
		// Which is the only way to read it: the value is private, so stringifying is the interface.
		$this->assertSame('username', (string) new FieldName('username'));
		$this->assertSame('billingAddress', (string) new FieldName('billingAddress'));
	}

	#[Test]
	public function it_is_sealed(): void
	{
		// A field is readonly only as deep as the objects it holds, so a name that could be
		// written to after the fact would reopen what sealing the definition closes.
		$this->assertTrue((new \ReflectionClass(FieldName::class))->isReadOnly());
	}
}
