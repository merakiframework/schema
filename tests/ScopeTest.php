<?php
declare(strict_types=1);

namespace Meraki\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A scope is a value now, not a cursor, and it knows what kind of thing it points at.
 * These cover reading one from its string form and writing it back; what a scope *means*
 * is {@see ScopeResolverTest}.
 */
#[Group('scope')]
#[CoversClass(Scope::class)]
#[CoversClass(FieldScope::class)]
#[CoversClass(ValueScope::class)]
#[CoversClass(PropertyScope::class)]
final class ScopeTest extends TestCase
{
	#[Test]
	#[DataProvider('paths')]
	public function it_reads_the_kind_of_scope_a_path_describes(string $path, string $expected): void
	{
		$this->assertInstanceOf($expected, Scope::parse($path));
	}

	#[Test]
	#[DataProvider('paths')]
	public function a_scope_writes_back_the_path_it_was_read_from(string $path): void
	{
		// The string form is the wire format meraki/schema-json reads and writes, so it has
		// to survive the round trip exactly.
		$this->assertSame($path, (string) Scope::parse($path));
	}

	public static function paths(): array
	{
		return [
			'a field' => ['#/fields/username', FieldScope::class],
			'a submitted value' => ['#/fields/username/value', ValueScope::class],
			'a definition property' => ['#/fields/age/min', PropertyScope::class],
			'optionality' => ['#/fields/nickname/optional', PropertyScope::class],
			'a camelCase name' => ['#/fields/contactMethod/value', ValueScope::class],
		];
	}

	#[Test]
	#[DataProvider('unaddressablePaths')]
	public function it_rejects_a_path_it_cannot_address(string $path): void
	{
		$this->expectException(InvalidArgumentException::class);

		Scope::parse($path);
	}

	public static function unaddressablePaths(): array
	{
		return [
			'no fragment marker' => ['fields/username'],
			'an unknown collection' => ['#/things/username'],
			'no field name' => ['#/fields/'],
			'nothing at all' => ['#/'],
			// The old parser ignored trailing segments, so "#/fields/x/min/typo" quietly
			// resolved as "min" — a mistake that behaved like a working scope.
			'trailing junk after a property' => ['#/fields/username/min/typo'],
			// A part belongs to a value, so it goes under `value`. Anywhere else is a typo.
			'a part hung off a property' => ['#/fields/username/min/country'],
			'a fifth segment' => ['#/fields/billing/value/country/extra'],
			'a name that cannot identify a field' => ['#/fields/not a name/value'],
			// Sub-fields were addressed this way while a composite registered `cost.amount`
			// alongside `cost`. A structured field owns its whole value now, so there is no
			// such field to name — and FieldName refuses a dot outright, which is what makes
			// this a parse error rather than a lookup that finds nothing.
			'a dotted sub-field name' => ['#/fields/cost.amount/value'],
		];
	}

	#[Test]
	public function a_property_scope_cannot_be_built_for_a_value(): void
	{
		// Otherwise there would be two objects claiming the same path, and only one of them
		// reads from the request.
		$this->expectException(InvalidArgumentException::class);

		PropertyScope::of('username', 'value');
	}

	#[Test]
	public function scopes_of_the_same_kind_and_path_are_equal(): void
	{
		$this->assertTrue(ValueScope::of('username')->equals(ValueScope::of('username')));
		$this->assertFalse(ValueScope::of('username')->equals(ValueScope::of('nickname')));
	}

	#[Test]
	public function a_field_scope_and_a_value_scope_are_never_equal(): void
	{
		// They stringify differently, but the kind is what an outcome dispatches on.
		$this->assertFalse(FieldScope::of('username')->equals(ValueScope::of('username')));
	}

	#[Test]
	public function a_scope_names_the_field_it_belongs_to(): void
	{
		$this->assertSame('username', (string) PropertyScope::of('username', 'min')->field);
	}
}
