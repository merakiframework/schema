<?php
declare(strict_types=1);

namespace Meraki\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

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
#[CoversClass(PartScope::class)]
#[CoversClass(Scope\SchemaField::class)]
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

	/**
	 * Four tails at four namespaces. The table *is* the grammar: a scope is a locator saying where
	 * to look and a tail saying what to read once there, and the tail is the same four shapes
	 * wherever it is rooted — which is what makes a row's field addressable exactly like a
	 * top-level one.
	 */
	public static function paths(): array
	{
		return [
			// A field the schema holds.
			'a field' => ['#/fields/username', FieldScope::class],
			'a definition property' => ['#/fields/age/min', PropertyScope::class],
			'a submitted value' => ['#/fields/username/value', ValueScope::class],
			'a part of a value' => ['#/fields/billing/value/country', PartScope::class],

			// One named row of a collection.
			'a row field' => ['#/fields/attendees/value/alice/email', FieldScope::class],
			'a row property' => ['#/fields/attendees/value/alice/email/minLength', PropertyScope::class],
			'a row value' => ['#/fields/attendees/value/alice/email/value', ValueScope::class],
			'a part of a row value' => ['#/fields/attendees/value/alice/addr/value/country', PartScope::class],

			// Every row, as a column.
			'a column field' => ['#/fields/attendees/value/*/email', FieldScope::class],
			'a column property' => ['#/fields/attendees/value/*/email/minLength', PropertyScope::class],
			'a column value' => ['#/fields/attendees/value/*/email/value', ValueScope::class],
			'a part of a column value' => ['#/fields/attendees/value/*/addr/value/country', PartScope::class],

			// The template, row-agnostic.
			'a template field' => ['#/fields/attendees/template/email', FieldScope::class],
			'a template property' => ['#/fields/attendees/template/email/minLength', PropertyScope::class],
			'a template value' => ['#/fields/attendees/template/email/value', ValueScope::class],
			'a part of a template value' => ['#/fields/attendees/template/addr/value/country', PartScope::class],

			'optionality' => ['#/fields/nickname/optional', PropertyScope::class],
			'a camelCase name' => ['#/fields/contactMethod/value', ValueScope::class],
			// `template` is a public property of a collection, so three segments keep meaning what
			// they always did — the whole list. The marker only takes over once a field follows it.
			'the template list itself' => ['#/fields/attendees/template', PropertyScope::class],
		];
	}

	#[Test]
	#[DataProvider('locators')]
	public function it_reads_where_the_tail_is_rooted(string $path, string $expected): void
	{
		$this->assertInstanceOf($expected, Scope::parse($path)->in);
	}

	public static function locators(): array
	{
		return [
			'a schema field' => ['#/fields/username/value', Scope\SchemaField::class],
			'a row' => ['#/fields/attendees/value/alice/email', Scope\Row::class],
			'a column' => ['#/fields/attendees/value/*/email', Scope\Column::class],
			'a template field' => ['#/fields/attendees/template/email', Scope\Template::class],
			'the template list itself' => ['#/fields/attendees/template', Scope\SchemaField::class],
		];
	}

	#[Test]
	public function the_field_a_scope_is_about_is_the_one_the_schema_holds(): void
	{
		// Not the template field. Two places read this — grouping outcomes by field, and looking
		// the field up to resolve against — and neither has any interest in locators.
		$scope = Scope::parse('#/fields/attendees/value/alice/email/value');

		$this->assertSame('attendees', (string) $scope->field);
		$this->assertSame('email', (string) $scope->in->addresses());
	}

	#[Test]
	public function a_row_field_and_its_value_are_different_scopes(): void
	{
		// The discriminator the whole grammar turns on. Without the trailing `value`,
		// `…/alice/email` would have to mean the value, leaving a row field's *definition*
		// unaddressable — and adding the segment later would silently change what every stored
		// scope resolves to.
		$field = Scope::parse('#/fields/attendees/value/alice/email');
		$value = Scope::parse('#/fields/attendees/value/alice/email/value');

		$this->assertInstanceOf(FieldScope::class, $field);
		$this->assertInstanceOf(ValueScope::class, $value);
		$this->assertFalse($field->equals($value));
	}

	#[Test]
	public function a_row_is_addressed_by_name_and_a_column_by_the_wildcard(): void
	{
		$row = Scope::parse('#/fields/attendees/value/alice/email');
		$column = Scope::parse('#/fields/attendees/value/*/email');

		$this->assertInstanceOf(Scope\Row::class, $row->in);
		$this->assertSame('alice', $row->in->row);
		$this->assertInstanceOf(Scope\Column::class, $column->in);
		$this->assertFalse($row->equals($column));
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
			'a name that cannot identify a field' => ['#/fields/not a name/value'],
			// A part of a value is still as deep as this goes — inside a collection as well as
			// outside one.
			'too deep under a row' => ['#/fields/lines/value/first/sku/value/country/extra'],
			'a part hung off a row property' => ['#/fields/lines/value/first/sku/min/country'],
			// A row is named the way a field is, so the same spellings are refused.
			'a row that is not a name' => ['#/fields/lines/value/not a row/sku'],
			'a row named with a leading digit' => ['#/fields/lines/value/1st/sku'],
			'a template field that is not a name' => ['#/fields/lines/template/not a field'],
			'a wildcard where a field goes' => ['#/fields/lines/value/first/*'],
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
	#[Test]
	public function one_factory_reaches_a_value_and_one_of_its_parts(): void
	{
		// A part is always inside a value and has nowhere else to hang from, so there is one way in
		// rather than two classes to know about. PartScope::of() still exists for building one
		// directly; this is the spelling to reach for.
		$this->assertSame('#/fields/billing/value', (string) ValueScope::of('billing'));
		$this->assertSame('#/fields/billing/value/country', (string) ValueScope::of('billing', 'country'));
	}

	#[Test]
	public function the_two_are_siblings_rather_than_a_subtype_pair(): void
	{
		// The factory hands back whichever the arguments describe, and the classes stay distinct —
		// a part is *located* inside a value but is not *a kind of* value. Making it one would flip
		// every `instanceof ValueScope` in the rule engine to include parts, and each is there to
		// exclude them: a part resolves to whatever the value put in it, not to a ParsedValue.
		$whole = ValueScope::of('billing');
		$part = ValueScope::of('billing', 'country');

		$this->assertInstanceOf(ValueScope::class, $whole);
		$this->assertInstanceOf(PartScope::class, $part);
		$this->assertNotInstanceOf(ValueScope::class, $part);
		$this->assertSame('country', $part->part);
	}

	#[Test]
	public function a_part_is_named_by_its_case_rather_than_a_string(): void
	{
		// The case is what code holds — a misspelled one does not compile — and the wire name is
		// what a stored scope holds. Both build the same scope.
		$this->assertSame('#/fields/billing/value/country', (string) PartScope::of('billing', Field\Address\Part::Country));
		$this->assertEquals(PartScope::of('billing', 'country'), ValueScope::of('billing', Field\Address\Part::Country));
		$this->assertSame('postal_code', PartScope::of('billing', Field\Address\Part::PostalCode)->part);
	}

	#[Test]
	public function a_part_reached_either_way_is_the_same_scope(): void
	{
		$this->assertEquals(PartScope::of('billing', 'country'), ValueScope::of('billing', 'country'));
	}

}
