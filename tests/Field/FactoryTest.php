<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Factory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Building fields is the factory's job; registering them is the schema's. These cover the
 * half that was on `Facade::for()` before the two were separated — which countries a newly
 * built region-aware field defaults to is a question about building one.
 */
#[Group('field')]
#[CoversClass(Factory::class)]
final class FactoryTest extends TestCase
{
	#[Test]
	public function region_aware_fields_inherit_the_factorys_countries(): void
	{
		$fields = (new Factory())->for('AU');

		$this->assertSame(['AU'], $fields->createAddressField('billing')->allowed);
		$this->assertSame(['AU'], $fields->createPhoneNumberField('mobile')->allowed);
	}

	#[Test]
	public function it_normalises_the_countries(): void
	{
		$fields = (new Factory())->for('au', 'AU', 'nz');

		$this->assertSame(['AU', 'NZ'], $fields->createAddressField('billing')->allowed);
	}

	#[Test]
	public function an_explicit_country_list_overrides_the_factorys(): void
	{
		$fields = (new Factory())->for('AU');

		$this->assertSame(['NZ'], $fields->createAddressField('shipping', ['NZ'])->allowed);
		$this->assertSame(['NZ'], $fields->createPhoneNumberField('mobile', ['NZ'])->allowed);
	}

	/** An explicit empty list is a choice — free-form — not an absent argument. */
	#[Test]
	public function an_explicit_empty_country_list_opts_out(): void
	{
		$fields = (new Factory())->for('AU');

		$this->assertSame([], $fields->createAddressField('anywhere', [])->allowed);
		$this->assertSame([], $fields->createPhoneNumberField('international', [])->allowed);
	}

	#[Test]
	public function only_fields_built_after_the_call_inherit(): void
	{
		$fields = new Factory();
		$before = $fields->createAddressField('before');

		$fields->for('AU');

		$this->assertSame([], $before->allowed);
		$this->assertSame(['AU'], $fields->createAddressField('after')->allowed);
	}

	/**
	 * Currency does not follow from a region — a country may use several, and the euro spans
	 * twenty — so money is deliberately left alone.
	 */
	#[Test]
	public function it_does_not_affect_money_fields(): void
	{
		$fields = (new Factory())->for('AU');

		$this->assertSame(['NZD'], $fields->createMoneyField('price', ['NZD' => 2])->allowed);
	}
}
