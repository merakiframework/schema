<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\FieldName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

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

	#[Test]
	public function case_does_not_distinguish_two_names(): void
	{
		// So a schema cannot hold both `email` and `Email` and leave a reader guessing which a
		// message or a scope path meant.
		$this->assertTrue((new FieldName('email'))->equals(new FieldName('EMAIL')));
		$this->assertTrue((new FieldName('phoneNumber'))->equals(new FieldName('phoneNumber')));
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
