<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Facade;
use Meraki\Schema\Property;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('field')]
#[CoversClass(Property\Name::class)]
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

		new Property\Name($name);
	}

	#[Test]
	#[DataProvider('usableNames')]
	public function a_usable_name_is_accepted(string $name): void
	{
		$this->assertSame($name, (string) new Property\Name($name));
	}

	#[Test]
	public function a_name_must_be_a_string(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Property\Name(123);
	}

	#[Test]
	public function a_top_level_field_cannot_carry_the_composite_separator(): void
	{
		// It would be indistinguishable from a sub-field of some composite.
		$schema = new Facade('checkout');
		$schema->addMoneyField('price', ['AUD' => 2]);

		$this->expectException(InvalidArgumentException::class);

		$schema->addTextField('price.amount');
	}

	#[Test]
	public function a_composite_still_names_its_sub_fields_with_the_separator(): void
	{
		$schema = new Facade('checkout');
		$schema->addAddressField('billing', ['AU']);

		$names = $schema->fields->getByName('billing')->fields->listFieldNames();

		$this->assertContains('billing.line1', $names);
		$this->assertContains('billing.postal_code', $names);
	}

	#[Test]
	public function a_duplicate_field_name_is_rejected(): void
	{
		$schema = new Facade('signup');
		$schema->addTextField('email');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('A field named "email" already exists.');

		$schema->addEmailAddressField('email');
	}

	#[Test]
	public function a_duplicate_is_rejected_rather_than_silently_dropped(): void
	{
		// The definition used to vanish with no error, leaving a schema that quietly
		// validated something other than what was written.
		$schema = new Facade('signup');
		$schema->addTextField('email');

		try {
			$schema->addEmailAddressField('email');
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
