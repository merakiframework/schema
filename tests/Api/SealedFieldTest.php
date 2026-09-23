<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Field\Constraint;
use Meraki\Schema\FieldName;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * The invariants that make a definition safe to share across requests.
 *
 * These are cheap to state and expensive to discover by hand: every one of them fails either
 * at class-load time or on a property read that may not happen until production. Asserting
 * them here means a field that breaks one is caught by the suite rather than by a request.
 *
 * @see AtomicField
 */
#[Group('field')]
#[CoversNothing]
final class SealedFieldTest extends TestCase
{
	/**
	 * Every field, by class name. Kept explicit rather than globbed so a field that stops
	 * being loadable shows up as a failure instead of silently dropping out of the sweep.
	 *
	 * @return iterable<string, array{class-string}>
	 */
	public static function fields(): iterable
	{
		$converted = [
			'Address',
			'Boolean',
			'Collection',
			'CreditCard',
			'Date',
			'DateTime',
			'Duration',
			'EmailAddress',
			'Enum',
			'File',
			'Money',
			'Name',
			'Number',
			'Password',
			'PhoneNumber',
			'Text',
			'Time',
			'Uri',
			'Uuid',
		];

		foreach ($converted as $short) {
			yield $short => ['Meraki\\Schema\\Field\\' . $short];
		}
	}

	#[Test]
	#[DataProvider('fields')]
	public function it_is_a_field(string $class): void
	{
		$this->assertTrue(is_a($class, Field::class, true), "{$class} is not a Field.");
	}

	#[Test]
	#[DataProvider('fields')]
	public function it_is_sealed(string $class): void
	{
		// readonly is inherited both ways, so this is what stops a field being written to
		// during one request and leaking into the next.
		$this->assertTrue((new ReflectionClass($class))->isReadOnly(), "{$class} is not readonly.");
	}

	#[Test]
	#[DataProvider('fields')]
	public function it_is_final(string $class): void
	{
		$this->assertTrue((new ReflectionClass($class))->isFinal(), "{$class} is not final.");
	}

	/**
	 * `private(set)` is redundant inside a readonly class and actively harmful: it puts the
	 * property out of reach of {@see AtomicField::with()}, which runs in the parent's scope,
	 * so every wither touching it would fail at runtime.
	 */
	#[Test]
	#[DataProvider('fields')]
	public function no_property_narrows_its_set_scope(string $class): void
	{
		foreach ((new ReflectionClass($class))->getProperties() as $property) {
			$this->assertFalse(
				$property->isPrivateSet(),
				"{$class}::\${$property->getName()} is private(set); plain public is already sealed.",
			);
		}
	}

	/**
	 * A field that defines a constructor and forgets `parent::__construct()` leaves
	 * `$optional` and `$defaultValue` uninitialised, which throws on first read rather than
	 * at load — so it can reach production untouched.
	 *
	 * PHP 8.6's readonly property defaults remove the need for the call entirely; until then
	 * this is the guard.
	 */
	#[Test]
	#[DataProvider('fields')]
	public function its_inherited_state_is_initialised(string $class): void
	{
		$field = self::construct($class);

		$this->assertIsBool($field->optional, "{$class} did not initialise \$optional.");
		$this->assertNull($field->defaultValue, "{$class} starts with a default value.");
	}

	/**
	 * $constraints is derived state that has to be *kept*, which is the one hazard of storing it
	 * rather than deriving it on read — and a `readonly` class leaves no choice, since PHP refuses
	 * property hooks there, virtual ones included.
	 *
	 * Two things can go wrong, and neither is visible to static analysis: a constructor that never
	 * assigns it, and a wither that does not rebuild it. Both are caught here, across every field,
	 * which is why phpstan.neon may ignore the uninitialised-property report it cannot resolve.
	 */
	#[Test]
	#[DataProvider('fields')]
	public function its_constraints_are_built_by_the_constructor(string $class): void
	{
		$field = self::construct($class);

		$this->assertInstanceOf(
			Constraint\Set::class,
			$field->constraints,
			"{$class} did not assign \$constraints; the last line of its constructor should.",
		);
	}

	#[Test]
	#[DataProvider('fields')]
	public function a_wither_rebuilds_its_constraints(string $class): void
	{
		// Otherwise a field would keep reporting the bounds it had before the wither ran.
		$field = self::construct($class);
		$optional = $field->makeOptional();

		$this->assertNotSame(
			$field->constraints,
			$optional->constraints,
			"{$class} carried its old Constraint\Set through a wither instead of rebuilding it.",
		);

		$this->assertSame(
			$field->constraints->names,
			$optional->constraints->names,
			"{$class} changed which constraints it reports just by becoming optional.",
		);
	}

	/**
	 * The stored set must equal one built now, or something changed a property without going
	 * through with().
	 */
	#[Test]
	#[DataProvider('fields')]
	public function its_stored_constraints_match_a_freshly_built_set(string $class): void
	{
		$field = self::construct($class);

		// No setAccessible(): a no-op since 8.1 and deprecated in 8.5.
		$build = new ReflectionMethod($field, 'defineConstraints');

		$this->assertSame(
			self::shapeOf($field->constraints),
			self::shapeOf($build->invoke($field)),
			"{$class} holds a stale Constraint\Set.",
		);
	}

	/**
	 * Names, parts and bounds — everything a consumer can see. Not the whole object, because a
	 * Constraint holds a Closure and two closures are never equal.
	 *
	 * @return list<array{string, string|null, string}>
	 */
	private static function shapeOf(Constraint\Set $set): array
	{
		$shape = [];

		foreach ($set as $constraint) {
			$bound = $constraint->bound;
			$shape[] = [$constraint->name, $constraint->part, is_array($bound) ? implode('|', $bound) : var_export($bound, true)];
		}

		return $shape;
	}
	/**
	 * The shape is not a constraint, but every aggregate predicate still has to account for it.
	 *
	 * This is the trap the split could have fallen into: pull the shape out of the results and
	 * `anyFailed()` narrows to constraints only, so unreadable input reports shape failed, every
	 * constraint skipped, and `anyFailed() === false` — every existing "is this ok?" check silently
	 * inverting. Keeping it among the results is what prevents it, and this is what proves it,
	 * across every field.
	 */
	#[Test]
	#[DataProvider('fields')]
	public function unreadable_input_fails_the_field_and_not_just_the_shape(string $class): void
	{
		// A stream: not a string, a number, an array or an object, so no field can read it.
		// (An stdClass would not do — Collection reads one as a JSON object.)
		$resolved = self::construct($class)->validate(fopen('php://memory', 'rb'));

		$this->assertTrue($resolved->shape->failed(), "{$class} read a stream as a value.");
		$this->assertTrue($resolved->anyFailed(), "{$class} reported an unreadable value as fine.");
		$this->assertFalse($resolved->allPassed(), $class);
		$this->assertSame(ValidationStatus::Failed, $resolved->status, $class);

		// And failed the right way. Something *was* submitted, so reporting this as `missing`
		// would tell a form to print "this is required" about a field the user had filled in.
		$this->assertTrue($resolved->shape->wasUnreadable(), "{$class} called a submitted value missing.");
		$this->assertFalse($resolved->shape->wasMissing(), $class);
	}

	#[Test]
	#[DataProvider('fields')]
	public function the_shape_is_not_reported_as_a_constraint(string $class): void
	{
		// It used to be, under the name `type` — which also meant no field could have a real
		// constraint called that.
		// A stream: not a string, a number, an array or an object, so no field can read it.
		// (An stdClass would not do — Collection reads one as a JSON object.)
		$resolved = self::construct($class)->validate(fopen('php://memory', 'rb'));

		$this->assertNotContains('type', $resolved->constraintNames, $class);
		$this->assertNull($resolved->forConstraint('type'), $class);
	}
	#[Test]
	#[DataProvider('fields')]
	public function it_is_required_until_told_otherwise(string $class): void
	{
		$this->assertFalse(self::construct($class)->optional);
	}

	#[Test]
	#[DataProvider('fields')]
	public function making_it_optional_leaves_the_original_alone(string $class): void
	{
		$field = self::construct($class);
		$optional = $field->makeOptional();

		$this->assertNotSame($field, $optional, 'A wither must hand back a copy.');
		$this->assertTrue($optional->optional);
		$this->assertFalse($field->optional, 'The original was modified.');
	}

	/**
	 * Builds a field with whatever its constructor asks for, so the sweep does not need to
	 * know each one's signature.
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
				// A Collection's variadic template. One field is enough to build a valid one.
				$name === Field::class => new Field\Text(new FieldName('item')),
				$name === 'array' => ['a', 'b'],
				$name === 'string' => 'test',
				$name === 'int' => 1,
				$name === 'bool' => false,
				default => self::fail(sprintf(
					'%s::__construct() wants a %s; teach %s how to supply one.',
					$class,
					$name ?: 'value',
					self::class,
				)),
			};
		}

		return new $class(...$arguments);
	}
}
