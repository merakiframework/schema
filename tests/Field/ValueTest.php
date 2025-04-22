<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Value;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('field')]
#[CoversClass(Value::class)]
final class ValueTest extends TestCase
{
	#[Test]
	public function it_sets_the_value_correctly(): void
	{
		$expectedValue = 'test value';

		$actualValue = Value::of($expectedValue);

		$this->assertSame($expectedValue, $actualValue->unwrap());
	}

	#[Test]
	public function can_tell_if_value_is_provided(): void
	{
		$providedValue = Value::of('test value');
		$notProvidedValue = Value::of(null);

		$this->assertTrue($providedValue->provided());
		$this->assertFalse($notProvidedValue->provided());
	}

	#[Test]
	public function can_tell_if_value_is_not_provided(): void
	{
		$providedValue = Value::of('test value');
		$notProvidedValue = Value::of(null);

		$this->assertFalse($providedValue->notProvided());
		$this->assertTrue($notProvidedValue->notProvided());
	}
}
