<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Facade;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('field')]
#[CoversClass(FieldName::class)]
#[CoversClass(Facade::class)]
final class FieldNamingTest extends TestCase
{


	/** @return array<string, array{string}> */
	public static function unusableNames(): array
	{
		return [
			'empty'              => [''],
			'scope separator'    => ['a/b'],
			'scope root marker'  => ['a#b'],
			'whitespace'         => ['first name'],
			'leading digit'      => ['1abc'],
			'leading separator'  => ['.foo'],
			'trailing separator' => ['foo.'],
			'empty segment'      => ['a..b'],
		];
	}

	/** @return array<string, array{string}> */
	public static function usableNames(): array
	{
		return [
			'snake_case'     => ['first_name'],
			'camelCase'      => ['contactMethod'],
			'kebab-case'     => ['create-person'],
			'leading _'      => ['_internal'],
			'trailing digit' => ['line1'],
		];
	}

	#[Test]
	#[DataProvider('unusableNames')]
	public function an_unusable_name_is_rejected(string $name): void
	{
		$this->expectException(InvalidArgumentException::class);

		new FieldName($name);
	}

	#[Test]
	#[DataProvider('usableNames')]
	public function a_usable_name_is_accepted(string $name): void
	{
		$this->assertSame($name, (string) new FieldName($name));
	}


	#[Test]
	public function a_field_name_cannot_contain_a_dot(): void
	{
		// A dot used to join a composite to its parts — `price` registered `price.amount`
		// beside itself — so a top-level name carrying one was ambiguous and was rejected for
		// that reason. A structured field owns its whole value now and registers nothing
		// beside itself, so there is no ambiguity left.
		//
		// The dot stays refused anyway, and it is worth saying why: `#/fields/price.amount` is
		// still a scope path somebody may have written against 1.x, and a name that could
		// swallow one would make a stale path silently address a real field instead of
		// failing. Refusing it keeps that an error.
		$this->expectException(InvalidArgumentException::class);

		new FieldName('price.amount');
	}

	#[Test]
	public function a_duplicate_field_name_is_rejected(): void
	{
		$schema = new Facade('signup');
		$schema->add($schema->createTextField('email'));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('A field named "email" already exists.');

		$schema->add($schema->createEmailAddressField('email'));
	}

	#[Test]
	public function a_duplicate_is_rejected_rather_than_silently_dropped(): void
	{
		// The definition used to vanish with no error, leaving a schema that quietly
		// validated something other than what was written.
		$schema = new Facade('signup');
		$schema->add($schema->createTextField('email'));

		try {
			$schema->add($schema->createEmailAddressField('email'));
		} catch (InvalidArgumentException) {
			// expected
		}

		$this->assertCount(1, $schema->fields);
		$this->assertInstanceOf(Field\Text::class, $schema->fields->getByName('email'));
	}

	#[Test]
	public function the_schema_itself_is_named_by_the_same_rules(): void
	{
		$this->assertSame('create-person', (string) (new Facade('create-person'))->name);

		$this->expectException(InvalidArgumentException::class);

		new Facade('');
	}
}
