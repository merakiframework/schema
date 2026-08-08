<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Facade;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

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
	public function region_aware_fields_inherit_the_schemas_countries(): void
	{
		$schema = (new Facade('test'))->for('AU');

		$this->assertSame(['AU'], $schema->addAddressField('billing')->allowed);
		$this->assertSame(['AU'], $schema->addPhoneNumberField('mobile')->allowed);
	}

	#[Test]
	public function it_normalises_the_schemas_countries(): void
	{
		$schema = (new Facade('test'))->for('au', 'AU', 'nz');

		$this->assertSame(['AU', 'NZ'], $schema->addAddressField('billing')->allowed);
	}

	#[Test]
	public function an_explicit_country_list_overrides_the_schemas(): void
	{
		$schema = (new Facade('test'))->for('AU');

		$this->assertSame(['NZ'], $schema->addAddressField('shipping', ['NZ'])->allowed);
		$this->assertSame(['NZ'], $schema->addPhoneNumberField('mobile', ['NZ'])->allowed);
	}

	/** An explicit empty list is a choice — free-form — not an absent argument. */
	#[Test]
	public function an_explicit_empty_country_list_opts_out_of_the_schemas(): void
	{
		$schema = (new Facade('test'))->for('AU');

		$this->assertSame([], $schema->addAddressField('anywhere', [])->allowed);
		$this->assertSame([], $schema->addPhoneNumberField('international', [])->allowed);
	}

	#[Test]
	public function only_fields_added_after_the_call_inherit(): void
	{
		$schema = new Facade('test');
		$before = $schema->addAddressField('before');

		$schema->for('AU');

		$this->assertSame([], $before->allowed);
		$this->assertSame(['AU'], $schema->addAddressField('after')->allowed);
	}

	/**
	 * Currency does not follow from a region — a country may use several, and the euro
	 * spans twenty — so money is deliberately left alone.
	 */
	#[Test]
	public function it_does_not_affect_money_fields(): void
	{
		$schema = (new Facade('test'))->for('AU');

		$this->assertSame(['NZD'], $schema->addMoneyField('price', ['NZD' => 2])->allowed);
	}
}
