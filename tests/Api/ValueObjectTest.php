<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Field;
use Meraki\Schema\Field\Comparable;
use Meraki\Schema\Field\ParsedValue;
use Meraki\Schema\FieldName;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Every field parses to a value object this library defines.
 *
 * ### Why this is a spec test and not a unit test
 *
 * The rule is only worth anything if it holds for *all* of them. One field returning a bare
 * `string`, or a third party's class, puts back the branch every consumer was able to delete —
 * and it would not fail any test that field owns, because that field would still validate
 * correctly. The failure surfaces somewhere else entirely, as a collection calling two identical
 * rows distinct or a rule that never fires.
 *
 * It is also the guard on a contract the compiler can only half-enforce. `parse(): ?ParsedValue`
 * stops a scalar or an array being returned, and cannot stop a field declaring the return type and
 * then handing back somebody else's `ParsedValue` — `Brick\Math\BigDecimal` could implement the
 * interface tomorrow and type-check perfectly while putting the library straight back to comparing
 * values by a layout somebody else controls. This asserts the part the signature cannot.
 *
 * @see Field\Definition::parse()
 */
#[Group('field')]
#[Group('api-2.0')]
#[CoversNothing]
final class ValueObjectTest extends TestCase
{
	/**
	 * Each field, with something it should be able to read.
	 *
	 * Kept explicit rather than globbed, for the reason {@see SealedFieldTest::fields()} gives: a
	 * field that stops being loadable should fail here rather than quietly leave the sweep.
	 *
	 * @return iterable<string, array{class-string<Field>, mixed}>
	 */
	public static function fieldsAndAValueTheyAccept(): iterable
	{
		yield 'Address' => [Field\Address::class, (object) ['line1' => '1 Test St', 'locality' => 'Sydney', 'administrative_area' => 'NSW', 'postal_code' => '2000', 'country' => 'AU']];
		yield 'Boolean' => [Field\Boolean::class, true];
		yield 'Collection' => [Field\Collection::class, [(object) ['item' => 'a']]];
		yield 'CreditCard' => [Field\CreditCard::class, (object) ['number' => '4014 1828 2909 8807', 'expiry' => '2029-07', 'name' => 'K Miller', 'security_code' => '936']];
		yield 'Date' => [Field\Date::class, '2030-01-01'];
		yield 'DateTime' => [Field\DateTime::class, '2030-01-01T09:30'];
		yield 'Duration' => [Field\Duration::class, 'PT1H'];
		yield 'EmailAddress' => [Field\EmailAddress::class, 'a@example.test'];
		yield 'Enum' => [Field\Enum::class, 'a'];
		yield 'File' => [Field\File::class, (object) ['name' => 'a.txt', 'type' => 'text/plain', 'size' => 10, 'tmp_name' => '/tmp/a', 'error' => 0]];
		yield 'Money' => [Field\Money::class, (object) ['currency' => 'AUD', 'amount' => '12.50']];
		yield 'Name' => [Field\Name::class, 'Kim Miller'];
		yield 'Number' => [Field\Number::class, '18'];
		yield 'Password' => [Field\Password::class, 'correct horse battery staple'];
		yield 'PhoneNumber' => [Field\PhoneNumber::class, (object) ['number' => '0411 222 333', 'country' => 'AU']];
		yield 'Text' => [Field\Text::class, 'hello'];
		yield 'Time' => [Field\Time::class, '09:30'];
		yield 'Uri' => [Field\Uri::class, 'https://example.test'];
		yield 'Uuid' => [Field\Uuid::class, '3f2504e0-4f89-41d3-9a0c-0305e82c3301'];
	}

	#[Test]
	#[DataProvider('fieldsAndAValueTheyAccept')]
	public function its_parsed_value_knows_its_own_equality(string $class, mixed $accepted): void
	{
		$value = self::construct($class)->resolvedValueFor($accepted);

		$this->assertInstanceOf(
			ParsedValue::class,
			$value,
			$class . '::parse() must return a value object, so nothing downstream has to ask what kind of thing it holds.',
		);
	}

	/**
	 * A value object belongs to the field that produced it.
	 *
	 * Returning a third party's class is the failure this whole change exists to prevent, and it
	 * is one the signature permits: `Brick\Math\BigDecimal` could implement `ParsedValue` tomorrow
	 * and type-check perfectly while putting the library back where it started — comparing values
	 * by a layout somebody else controls.
	 */
	#[Test]
	#[DataProvider('fieldsAndAValueTheyAccept')]
	public function its_value_object_is_one_this_library_owns(string $class, mixed $accepted): void
	{
		$value = self::construct($class)->resolvedValueFor($accepted);

		$this->assertStringStartsWith(
			'Meraki\\Schema\\Field\\',
			$value::class,
			'A parsed value must be a class this library defines, not one it happens to depend on.',
		);
	}

	/**
	 * Equality has to be reflexive, or nothing built on it means anything.
	 */
	#[Test]
	#[DataProvider('fieldsAndAValueTheyAccept')]
	public function a_value_equals_itself_when_read_twice(string $class, mixed $accepted): void
	{
		$field = self::construct($class);
		$first = $field->resolvedValueFor($accepted);
		$second = $field->resolvedValueFor($accepted);

		$this->assertNotSame($first, $second, 'Two reads should produce two objects, or this proves nothing.');
		$this->assertTrue($first->equals($second), 'A value read twice from the same input must be equal to itself.');
	}

	/**
	 * `compareTo()` and `equals()` must agree, since a value that sorts equal to another without
	 * being equal to it has no coherent reading.
	 */
	#[Test]
	#[DataProvider('fieldsAndAValueTheyAccept')]
	public function an_ordered_value_agrees_with_its_own_equality(string $class, mixed $accepted): void
	{
		$field = self::construct($class);
		$first = $field->resolvedValueFor($accepted);

		if (!$first instanceof Comparable) {
			$this->expectNotToPerformAssertions();

			return;
		}

		$second = $field->resolvedValueFor($accepted);

		$this->assertSame(0, $first->compareTo($second));
		$this->assertTrue($first->equals($second));
	}

	/**
	 * Ordering across kinds is not a question with an answer, so it raises rather than guessing.
	 */
	#[Test]
	public function ordering_two_different_kinds_of_value_is_refused(): void
	{
		$number = (new Field\Number(new FieldName('n')))->resolvedValueFor('1');
		$date = (new Field\Date(new FieldName('d')))->resolvedValueFor('2030-01-01');

		$this->assertInstanceOf(Comparable::class, $number);
		$this->assertInstanceOf(Comparable::class, $date);

		$this->expectException(\InvalidArgumentException::class);

		$number->compareTo($date);
	}

	/**
	 * Two values of different kinds are never equal, and asking must not raise — unlike ordering,
	 * this is a question with an obvious answer.
	 */
	#[Test]
	public function values_of_different_kinds_are_never_equal(): void
	{
		$text = (new Field\Text(new FieldName('t')))->resolvedValueFor('1');
		$number = (new Field\Number(new FieldName('n')))->resolvedValueFor('1');

		$this->assertFalse($text->equals($number));
		$this->assertFalse($number->equals($text));
	}

	/**
	 * Every field's result carries the same members, whatever shape the field's value has.
	 *
	 * A collection used to be the exception: its result wrapped a `ResolvedField` privately and
	 * re-exposed a chosen few members through `get` hooks, so `given`, `source` and `evaluatedAt`
	 * were unreachable and `value` did not even appear in a `var_dump()`. A caller could not write
	 * one piece of code that read any field's result, which is the whole point of having one.
	 */
	#[Test]
	#[DataProvider('fieldsAndAValueTheyAccept')]
	public function every_result_carries_the_same_members(string $class, mixed $accepted): void
	{
		$resolved = self::construct($class)->validate($accepted);

		foreach (['field', 'given', 'value', 'source', 'evaluatedAt', 'appliedOutcomes', 'shape'] as $member) {
			$this->assertTrue(
				property_exists($resolved, $member),
				$class . "'s result does not carry \$" . $member . '.',
			);
		}

		$this->assertInstanceOf(Field\ShapeValidationResult::class, $resolved->shape);
		$this->assertSame($accepted, $resolved->given);
	}

	/**
	 * A row that failed makes the collection fail, and the schema with it.
	 *
	 * The two axes are separate — `minCount` is about the list, a bad quantity is about row 2 —
	 * but a caller asking "did this field pass" must be told about both, or a form reports success
	 * on a request it rejected.
	 */
	#[Test]
	public function a_failing_row_fails_the_collection_and_the_schema(): void
	{
		$schema = new \Meraki\Schema\Facade('invoice');
		$schema->add($schema->createCollectionField('lines', $schema->createNumberField('qty')));

		$result = $schema->validate((object) ['lines' => [(object) ['qty' => 'not a number']]]);
		$collection = $result->forField('lines');

		$this->assertTrue($collection->anyFailed());
		$this->assertFalse($result->allPassed());

		// And the collection's own verdicts are untouched by the row's.
		$this->assertTrue($collection->forConstraint('minCount')->passed());
		$this->assertCount(1, $collection->failedItems);
	}

	/**
	 * A secret and a card number must not be printable: stringifying is how they end up in a log.
	 *
	 * @see Field\Password\Value
	 * @see Field\CreditCard\Value
	 */
	#[Test]
	#[DataProvider('valuesThatMustNotBePrintable')]
	public function a_sensitive_value_has_no_string_form(string $valueClass): void
	{
		$this->assertFalse(
			method_exists($valueClass, '__toString'),
			$valueClass . ' must not be stringable — that is how a secret reaches a log or a template.',
		);
	}

	/** @return iterable<string, array{class-string}> */
	public static function valuesThatMustNotBePrintable(): iterable
	{
		yield 'Password' => [Field\Password\Value::class];
		yield 'CreditCard' => [Field\CreditCard\Value::class];
	}

	/**
	 * Builds a field with whatever its constructor asks for, so the sweep does not need to know
	 * each one's signature. Mirrors {@see SealedFieldTest::construct()}.
	 */
	private static function construct(string $class): Field
	{
		$constructor = (new ReflectionClass($class))->getConstructor();
		$arguments = [];

		foreach ($constructor?->getParameters() ?? [] as $parameter) {
			if ($parameter->isDefaultValueAvailable()) {
				continue;
			}

			$type = $parameter->getType();
			$name = $type instanceof ReflectionNamedType ? $type->getName() : '';

			$arguments[] = match (true) {
				$name === FieldName::class => new FieldName('test'),
				$name === Field::class => new Field\Text(new FieldName('item')),
				$name === 'array' => ['a', 'b'],
				$name === 'string' => 'test',
				$name === 'int' => 1,
				default => null,
			};
		}

		return new $class(...$arguments);
	}
}
