<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Facade;
use Meraki\Schema\Field\BuildsFields;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * A schema builds its own fields, the same way it builds its own rules.
 *
 * These were `FactoryTest`, covering a separate `Field\Factory` object. The builders moved onto
 * the schema and the factory is gone: it had no state worth being its own object for — only the
 * country list below, which belongs to the schema being authored anyway — and two entry points
 * for one job is what docs/API.md exists to remove.
 *
 * What they cover is unchanged, and is still about *building* rather than registering: which
 * countries a newly built region-aware field inherits.
 */
#[Group('field')]
#[CoversTrait(BuildsFields::class)]
final class BuildsFieldsTest extends TestCase
{
	#[Test]
	public function region_aware_fields_inherit_the_schemas_countries(): void
	{
		$schema = (new Facade('booking'))->for('AU');

		$this->assertSame(['AU'], $schema->createAddressField('billing')->allowedCountries);
		$this->assertSame(['AU'], $schema->createPhoneNumberField('mobile')->allowedCountries);
	}

	#[Test]
	public function it_normalises_the_countries(): void
	{
		$schema = (new Facade('booking'))->for('au', 'AU', ' nz ');

		$this->assertSame(['AU', 'NZ'], $schema->createAddressField('billing')->allowedCountries);
	}

	#[Test]
	public function an_explicit_country_list_overrides_the_schemas(): void
	{
		$schema = (new Facade('booking'))->for('AU');

		$this->assertSame(['NZ'], $schema->createAddressField('shipping', ['NZ'])->allowedCountries);
		$this->assertSame(['NZ'], $schema->createPhoneNumberField('mobile', ['NZ'])->allowedCountries);
	}

	/** An explicit empty list is a choice — free-form — not an absent argument. */
	#[Test]
	public function an_explicit_empty_country_list_opts_out(): void
	{
		$schema = (new Facade('booking'))->for('AU');

		$this->assertSame([], $schema->createAddressField('anywhere', [])->allowedCountries);
		$this->assertSame([], $schema->createPhoneNumberField('international', [])->allowedCountries);
	}

	#[Test]
	public function only_fields_built_after_the_call_inherit(): void
	{
		// `for()` is authoring configuration, not a retroactive setting: a field is immutable, so
		// one already built cannot acquire a country list it was not given.
		$schema = new Facade('booking');
		$before = $schema->createAddressField('before');

		$schema->for('AU');

		$this->assertSame([], $before->allowedCountries);
		$this->assertSame(['AU'], $schema->createAddressField('after')->allowedCountries);
	}

	/**
	 * Currency does not follow from a region — a country may use several, and the euro spans
	 * twenty — so money is deliberately left alone.
	 */
	#[Test]
	public function it_does_not_affect_money_fields(): void
	{
		$schema = (new Facade('booking'))->for('AU');

		// A map rather than a list: the scale a currency uses is part of allowing it.
		$this->assertSame(['NZD' => 2], $schema->createMoneyField('price', ['NZD' => 2])->allowedCurrencies);
	}

	#[Test]
	public function a_collection_template_inherits_the_same_countries(): void
	{
		// Nothing special makes this work: the template fields are built by the schema like any
		// other, and simply handed over.
		$schema = (new Facade('booking'))->for('AU');

		$sessions = $schema->createCollectionField('sessions', $schema->createAddressField('venue'));

		$this->assertSame(['AU'], $sessions->template[0]->allowedCountries);
	}

	#[Test]
	public function building_a_field_does_not_register_it(): void
	{
		// The two were one call until fields were sealed, and then adding-and-configuring left
		// the schema holding the unconfigured original.
		$schema = new Facade('signup');

		$schema->createTextField('username');

		$this->assertCount(0, $schema->fields);
	}
}
