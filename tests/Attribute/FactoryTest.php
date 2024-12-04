<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Attribute\Factory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Factory::class)]
final class FactoryTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$factory = new Factory();

		$this->assertInstanceOf(Factory::class, $factory);
	}

	#[Test]
	#[DataProvider('defaultRegisteredAttributes')]
	public function it_registers_default_attributes(string $expectedName, string $expectedClass): void
	{
		$factory = new Factory();

		$this->assertTrue($factory->isRegistered($expectedName));
	}

	#[Test]
	public function can_override_default_attributes(): void
	{
		$factory = new Factory([
			'pattern' => MyCustomPattern::class
		]);

		$pattern = $factory->create('pattern', '/^[\w-]+$/');

		$this->assertInstanceOf(MyCustomPattern::class, $pattern);
	}

	#[Test]
	public function it_registers_custom_attributes_when_instantiated(): void
	{
		$factory = new Factory([
			'my_custom_pattern' => MyCustomPattern::class
		]);

		$this->assertTrue($factory->isRegistered('my_custom_pattern'));
	}

	#[Test]
	public function it_registers_custom_attributes_using_existing_instance(): void
	{
		$factory = new Factory();

		$factory->register('my_custom_pattern', MyCustomPattern::class);

		$this->assertTrue($factory->isRegistered('my_custom_pattern'));
	}

	#[Test]
	public function it_throws_exception_when_constraint_is_not_registered(): void
	{
		$factory = new Factory();

		$this->expectException(\RuntimeException::class);

		$required = $factory->create('required', true);
	}

	#[Test]
	public function it_creates_attribute_from_name(): void
	{
		$regex = '/^[\w-]+$/';
		$factory = new Factory();

		$pattern = $factory->createFromName('pattern', $regex);

		$this->assertInstanceOf(Attribute\Pattern::class, $pattern);
		$this->assertEquals($regex, $pattern->value);
	}

	#[Test]
	public function it_creates_an_attribute(): void
	{
		$regex = '/^[\w-]+$/';
		$factory = new Factory();

		$pattern = $factory->create('pattern', $regex);

		$this->assertInstanceOf(Attribute\Pattern::class, $pattern);
		$this->assertEquals($regex, $pattern->value);
	}

	#[Test]
	public function can_override_class_creation_logic_using_previously_instantiated_instance(): void
	{
		$regex = '/^[\w-]+$/';
		$factory = new Factory();

		$factory->register('pattern', fn(mixed $value): Attribute => new MyCustomPattern($value));

		$pattern = $factory->create('pattern', $regex);

		$this->assertInstanceOf(MyCustomPattern::class, $pattern);
		$this->assertEquals($regex, $pattern->value);
	}

	#[Test]
	public function can_override_class_creation_logic_when_instantiating(): void
	{
		$regex = '/^[\w-]+$/';
		$factory = new Factory([
			'pattern' => fn(mixed $value): Attribute => new MyCustomPattern($value),
		]);

		$pattern = $factory->create('pattern', $regex);

		$this->assertInstanceOf(MyCustomPattern::class, $pattern);
		$this->assertEquals($regex, $pattern->value);
	}

	public static function defaultRegisteredAttributes(): array
	{
		return [
			'default_value' => ['default_value', Attribute\DefaultValue::class],
			'max' => ['max', Attribute\Max::class],
			'min' => ['min', Attribute\Min::class],
			'name' => ['name', Attribute\Name::class],
			'one_of' => ['one_of', Attribute\OneOf::class],
			'optional' => ['optional', Attribute\Optional::class],
			'pattern' => ['pattern', Attribute\Pattern::class],
			'step' => ['step', Attribute\Step::class],
			'type' => ['type', Attribute\Type::class],
			'value' => ['value', Attribute\Value::class],
			'version' => ['version', Attribute\Version::class],
		];
	}
}

final class MyCustomPattern extends Attribute implements Constraint
{
	public function __construct(string $value)
	{
		parent::__construct('pattern', $value);
	}
}
