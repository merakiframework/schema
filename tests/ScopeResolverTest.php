<?php
declare(strict_types=1);

namespace Meraki\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * A rule condition asks what a field was given. That used to be answered by reading a
 * value staged onto the field, so a condition only worked once the request had been
 * written into the schema — which is what made a shared schema unsafe.
 *
 * These tests hold the replacement in place: the value comes from the request, the
 * definition is only read, and the two kinds of target keep the meanings they had.
 */
#[Group('long-lived')]
#[CoversClass(ScopeResolver::class)]
final class ScopeResolverTest extends TestCase
{
	private function schema(): Definition
	{
		$schema = new Definition('signup');
		$schema->add($schema->createTextField('username')->minLengthOf(3));
		$schema->add($schema->createTextField('nickname')->defaultsTo('anonymous'));

		return $schema;
	}

	#[Test]
	public function a_value_comes_from_the_request(): void
	{
		$schema = $this->schema();

		$resolved = (new ScopeResolver($schema->fields, ['username' => 'alice']))
			->resolve(ValueScope::of('username'));

		$this->assertSame('alice', $resolved->text);
	}

	#[Test]
	public function a_value_absent_from_the_request_falls_back_to_the_authored_default(): void
	{
		$schema = $this->schema();

		$resolved = (new ScopeResolver($schema->fields, []))
			->resolve(ValueScope::of('nickname'));

		$this->assertSame('anonymous', $resolved->text);
	}

	#[Test]
	public function two_requests_resolve_the_same_scope_to_their_own_values(): void
	{
		$schema = $this->schema();
		$scope = ValueScope::of('username');

		$alice = (new ScopeResolver($schema->fields, ['username' => 'alice']))->resolve($scope);
		$mallory = (new ScopeResolver($schema->fields, ['username' => 'mallory']))->resolve($scope);

		$this->assertSame('alice', $alice->text);
		$this->assertSame('mallory', $mallory->text);
	}

	#[Test]
	public function a_definition_property_is_read_from_the_schema(): void
	{
		// Not everything a scope can address depends on the request: `min` is what the
		// author wrote, and no request changes it.
		$schema = $this->schema();

		$resolved = (new ScopeResolver($schema->fields, ['username' => 'alice']))
			->resolve(PropertyScope::of('username', 'minLength'));

		$this->assertSame(3, $resolved);
	}

	#[Test]
	public function resolving_leaves_the_schema_untouched(): void
	{
		$schema = $this->schema();
		$before = print_r($schema, true);

		$resolver = new ScopeResolver($schema->fields, ['username' => 'alice', 'nickname' => 'al']);
		$resolver->resolve(ValueScope::of('username'));
		$resolver->resolve(PropertyScope::of('username', 'minLength'));
		$resolver->resolve(ValueScope::of('nickname'));

		$this->assertSame($before, print_r($schema, true));
	}

	#[Test]
	public function a_field_scope_resolves_to_the_field_itself(): void
	{
		$schema = new Definition('signup');
		$field = $schema->createTextField('username');
		$schema->add($field);

		$resolved = (new ScopeResolver($schema->fields))->resolve(FieldScope::of('username'));

		$this->assertSame($field, $resolved);
	}

	#[Test]
	public function optionality_is_addressable(): void
	{
		$schema = new Definition('signup');
		$schema->add($schema->createTextField('nickname')->makeOptional());

		$this->assertTrue((new ScopeResolver($schema->fields))->resolve(PropertyScope::of('nickname', 'optional')));
	}

	#[Test]
	public function a_fields_public_configuration_stays_addressable(): void
	{
		// A field's public properties are its API; only the back-reference is excluded.
		$schema = new Definition('signup');
		$schema->add($schema->createTextField('username')->minLengthOf(3)->maxLengthOf(20));

		$resolver = new ScopeResolver($schema->fields);

		$this->assertSame(3, $resolver->resolve(PropertyScope::of('username', 'minLength')));
		$this->assertSame(20, $resolver->resolve(PropertyScope::of('username', 'maxLength')));
	}

	#[Test]
	public function a_field_has_no_back_reference_to_its_schema(): void
	{
		// Defect B8: a field held its owner, so a scope stepping into `schema` climbed to the
		// root and walked the same path forever. It was guarded with a `NOT_ADDRESSABLE` list
		// naming the property.
		//
		// Both are gone. The field no longer has the property, so there is nothing to guard —
		// which is why this asserts the *absence* rather than the rejection: resolving
		// `#/fields/x/schema` now fails identically to any other unknown property, and a test
		// spelling `schema` would pass just as well against a typo. Re-adding the
		// back-reference is what should break, and only this notices that.
		$field = (new Definition('booking'))->createBooleanField('has_log_book');

		$this->assertFalse(
			property_exists($field, 'schema'),
			'A field must not point back at the schema holding it (defect B8).',
		);
	}

	#[Test]
	public function an_unknown_property_is_rejected(): void
	{
		$schema = new Definition('signup');
		$schema->add($schema->createTextField('username'));

		$this->expectException(InvalidArgumentException::class);

		(new ScopeResolver($schema->fields))->resolve(PropertyScope::of('username', 'nope'));
	}

	#[Test]
	public function an_unknown_field_is_rejected(): void
	{
		$schema = new Definition('signup');
		$schema->add($schema->createTextField('username'));

		$this->expectException(InvalidArgumentException::class);

		(new ScopeResolver($schema->fields))->resolve(ValueScope::of('nope'));
	}

	#[Test]
	public function a_scope_can_be_resolved_more_than_once(): void
	{
		// Rule outcomes build their scope once and resolve it on every validation run. When
		// a scope was a cursor the second pass started from an exhausted one and threw.
		$schema = new Definition('signup');
		$schema->add($schema->createTextField('username')->minLengthOf(3));

		$resolver = new ScopeResolver($schema->fields);
		$scope = PropertyScope::of('username', 'minLength');

		$this->assertSame(3, $resolver->resolve($scope));
		$this->assertSame(3, $resolver->resolve($scope));
		$this->assertSame(3, $resolver->resolve($scope));
	}

	// ── reaching into a collection ────────────────────────────────────────────────────────

	private function order(): Definition
	{
		$schema = new Definition('order');
		$schema->add($schema->createCollectionField(
			'lines',
			$schema->createTextField('sku')->minLengthOf(3),
			$schema->createNumberField('qty'),
		));

		return $schema;
	}

	/** @return array<string, mixed> */
	private static function twoLines(): array
	{
		return ['lines' => [
			'first' => (object) ['sku' => 'A1X', 'qty' => '2'],
			'second' => (object) ['sku' => 'B2Y', 'qty' => '5'],
		]];
	}

	#[Test]
	public function a_named_row_resolves_to_that_rows_value(): void
	{
		$schema = $this->order();
		$resolver = new ScopeResolver($schema->fields, self::twoLines());

		$this->assertSame('A1X', (string) $resolver->resolve(Scope::parse('#/fields/lines/value/first/sku/value')));
		$this->assertSame('B2Y', (string) $resolver->resolve(Scope::parse('#/fields/lines/value/second/sku/value')));
	}

	#[Test]
	public function a_row_that_was_not_submitted_resolves_to_nothing(): void
	{
		// Which rows exist is a fact about a request, and a scope is written long before one
		// arrives — so this is the same answer an unfilled part of an address gives, not an error.
		$schema = $this->order();
		$resolver = new ScopeResolver($schema->fields, self::twoLines());

		$this->assertNull($resolver->resolve(Scope::parse('#/fields/lines/value/third/sku/value')));
	}

	#[Test]
	public function a_row_field_has_a_definition_as_well_as_a_value(): void
	{
		// The whole reason the grammar keeps a trailing `value`. Without it there would be no way
		// to name the left-hand side of this pair.
		$schema = $this->order();
		$resolver = new ScopeResolver($schema->fields, self::twoLines());

		$this->assertSame(3, $resolver->resolve(Scope::parse('#/fields/lines/value/first/sku/minLength')));
		$this->assertSame('A1X', (string) $resolver->resolve(Scope::parse('#/fields/lines/value/first/sku/value')));
	}

	#[Test]
	public function a_column_is_every_rows_value_under_the_row_names(): void
	{
		$schema = $this->order();
		$resolver = new ScopeResolver($schema->fields, self::twoLines());

		$column = $resolver->resolve(Scope::parse('#/fields/lines/value/*/sku/value'));

		$this->assertSame(['first', 'second'], array_keys($column));
		$this->assertSame(['A1X', 'B2Y'], array_map(strval(...), array_values($column)));
	}

	#[Test]
	public function a_column_of_nothing_is_an_empty_list(): void
	{
		$schema = $this->order();

		$this->assertSame([], (new ScopeResolver($schema->fields))->resolve(Scope::parse('#/fields/lines/value/*/sku/value')));
	}

	#[Test]
	public function the_template_is_read_without_naming_a_row(): void
	{
		$schema = $this->order();

		$this->assertSame(3, (new ScopeResolver($schema->fields))->resolve(Scope::parse('#/fields/lines/template/sku/minLength')));
	}

	#[Test]
	public function a_template_value_needs_a_row(): void
	{
		// The definition is row-agnostic; a value is not. Answering "the first row" or "all of
		// them" would be a silent answer to a question nobody asked.
		$schema = $this->order();

		$this->expectException(InvalidArgumentException::class);

		(new ScopeResolver($schema->fields))->resolve(Scope::parse('#/fields/lines/template/sku/value'));
	}

	#[Test]
	public function a_template_field_that_is_not_there_fails_where_the_rule_is_written(): void
	{
		// Resolved with no request at all, which is exactly how Definition::addRule() checks a scope.
		$schema = $this->order();

		$this->expectException(InvalidArgumentException::class);

		(new ScopeResolver($schema->fields))->resolve(Scope::parse('#/fields/lines/value/first/nope/value'));
	}

	#[Test]
	public function a_column_naming_a_field_the_template_lacks_fails_even_with_no_rows(): void
	{
		// Otherwise a typo would answer `[]` on every request, which is indistinguishable from a
		// collection nobody filled in.
		$schema = $this->order();

		$this->expectException(InvalidArgumentException::class);

		(new ScopeResolver($schema->fields))->resolve(Scope::parse('#/fields/lines/value/*/nope/value'));
	}

	#[Test]
	public function only_a_collection_has_rows(): void
	{
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);

		(new ScopeResolver($schema->fields))->resolve(Scope::parse('#/fields/username/value/first/sku/value'));
	}

	#[Test]
	public function a_collections_own_properties_are_still_addressed_as_they_were(): void
	{
		$schema = $this->order();

		$this->assertSame(1, (new ScopeResolver($schema->fields))->resolve(PropertyScope::of('lines', 'minCount')));
	}

	/**
	 * A rule and a result must say the same thing about the same request.
	 *
	 * They did not, for a collection nobody submitted. `Collection` kept a private copy of the
	 * "what stands in when nothing arrived" rule with `?? []` on the end, so resolve() and
	 * validate() saw an empty list while this resolver — which goes through
	 * `Definition::resolvedValueFor()` — saw `null`. Two answers to one question, and a rule
	 * reading the scope got the one that was wrong.
	 */
	#[Test]
	public function an_absent_collection_reads_the_same_through_a_scope_as_through_a_result(): void
	{
		$schema = $this->order();

		$viaScope = (new ScopeResolver($schema->fields))->resolve(ValueScope::of('lines'));
		$viaResult = $schema->validate((object)[])->forField('lines')->value;

		$this->assertInstanceOf(Field\Collection\Value::class, $viaScope);
		$this->assertCount(0, $viaScope);
		$this->assertTrue($viaScope->equals($viaResult));
	}

	/**
	 * And the hook does not leak: absence is still `null` for a field that holds one value.
	 */
	#[Test]
	public function an_absent_atomic_field_still_reads_as_nothing(): void
	{
		$schema = $this->schema();

		$this->assertNull((new ScopeResolver($schema->fields))->resolve(ValueScope::of('username')));
	}
}
