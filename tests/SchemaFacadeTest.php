<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Facade;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;

#[CoversClass(Facade::class)]
final class SchemaFacadeTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$schema = new Facade('test');

		$this->assertInstanceOf(Facade::class, $schema);
	}

	#[Test]
	public function cloning_copies_the_facade_and_nothing_it_holds(): void
	{
		// `Facade::against()` runs every request against `clone $this`, which is only affordable
		// because the copy shares the definition rather than duplicating it: a `__clone()` that
		// deep-copied would rebuild every field on every request. Shallow is also what makes the
		// copy *safe* — a rule reassigns `$fields` on the copy, and reassigning a property the
		// original also points at cannot reach the original.
		//
		// Api\ClockTest asserts the clock and country defaults survive a clone, and
		// LongLivedProcessTest asserts a clone's validation leaves the original untouched. Neither
		// would notice a deep copy. This does.
		$messages = new class implements Message\Provider {
			public function supports(string $locale): bool
			{
				return false;
			}

			public function forLocale(string $locale): Message\Translator
			{
				return Message\Silence::for($locale);
			}
		};

		// Two fields and a rule, because rules are the only thing that writes to `$fields`.
		$schema = new Facade('signup', messages: $messages);
		$schema->add($schema->createTextField('username')->minLengthOf(3));
		$schema->add($schema->createTextField('nickname')->makeOptional());
		$schema->addRule(
			$schema->when('username')->equals('admin')->then($schema->fields->getByName('nickname')->makeRequired()),
		);

		$clone = clone $schema;

		$this->assertNotSame($schema, $clone);

		// Every property is the same instance, not an equal one.
		$this->assertSame($schema->name, $clone->name);
		$this->assertSame($schema->fields, $clone->fields);
		$this->assertSame($schema->rules, $clone->rules);
		$this->assertSame($schema->messages, $clone->messages);

		// And the contents of the sets, not merely the sets. `assertSame` on arrays is `===`, which
		// compares objects by identity, so this is one assertion per field and per rule.
		$this->assertSame(iterator_to_array($schema->fields), iterator_to_array($clone->fields));
		$this->assertSame(iterator_to_array($schema->rules), iterator_to_array($clone->rules));

		// Reflection because the clock is `protected` on Field\BuildsFields — the extension point
		// that lets a third-party field inherit it. Same reading as Api\ClockTest.
		$clock = new ReflectionProperty(Facade::class, 'clock');

		$this->assertSame($clock->getValue($schema), $clock->getValue($clone));
	}
}
