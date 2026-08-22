<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Facade;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Form input is attacker-controlled, so a composite handed something that is not a set of
 * sub-field values must report a failure rather than raise. Previously each of these
 * raised an uncaught exception, turning a bad request into a 500.
 */
#[Group('field')]
#[CoversClass(Field\Composite::class)]
#[CoversClass(Field\Collection::class)]
final class MalformedCompositeInputTest extends TestCase
{
	/** @return array<string, array{mixed}> */
	public static function unusableValues(): array
	{
		return [
			'string'  => ['not-an-array'],
			'integer' => [123],
			'float'   => [1.5],
			'boolean' => [true],
		];
	}

	private function schema(): Facade
	{
		$schema = new Facade('checkout');
		$schema->addMoneyField('price', ['AUD' => 2]);
		$schema->addAddressField('billing', ['AU']);
		$schema->addCreditCardField('card');
		$schema->addCollectionField('items', fn(Facade $item): mixed => $item->addTextField('sku'));

		return $schema;
	}

	#[Test]
	#[DataProvider('unusableValues')]
	public function a_money_field_fails_rather_than_raises(mixed $value): void
	{
		$this->assertTrue($this->schema()->validate(['price' => $value])->anyFailed());
	}

	#[Test]
	#[DataProvider('unusableValues')]
	public function an_address_field_fails_rather_than_raises(mixed $value): void
	{
		$this->assertTrue($this->schema()->validate(['billing' => $value])->anyFailed());
	}

	#[Test]
	#[DataProvider('unusableValues')]
	public function a_credit_card_field_fails_rather_than_raises(mixed $value): void
	{
		$this->assertTrue($this->schema()->validate(['card' => $value])->anyFailed());
	}

	#[Test]
	#[DataProvider('unusableValues')]
	public function a_collection_field_fails_rather_than_raises(mixed $value): void
	{
		$this->assertTrue($this->schema()->validate(['items' => $value])->anyFailed());
	}

	#[Test]
	public function an_optional_composite_still_fails_on_unusable_input(): void
	{
		// Optional excuses an absent value, never a bad one.
		$schema = new Facade('checkout');
		$schema->addMoneyField('price', ['AUD' => 2])->makeOptional();

		$this->assertTrue($schema->validate(['price' => 'not-an-array'])->anyFailed());
	}

	#[Test]
	public function an_optional_composite_left_out_is_still_skipped(): void
	{
		$schema = new Facade('checkout');
		$schema->addMoneyField('price', ['AUD' => 2])->makeOptional();

		$this->assertFalse($schema->validate([])->anyFailed());
	}

	#[Test]
	public function a_list_where_an_item_is_unusable_fails(): void
	{
		$schema = new Facade('checkout');
		$schema->addCollectionField('items', fn(Facade $item): mixed => $item->addTextField('sku'));

		$this->assertTrue($schema->validate(['items' => ['not-an-item']])->anyFailed());
	}

	#[Test]
	public function well_formed_input_still_passes(): void
	{
		$result = $this->schema()->validate([
			'price'   => ['amount' => '99.95', 'currency' => 'AUD'],
			'billing' => ['line1' => '1 Queen St', 'locality' => 'Brisbane', 'administrative_area' => 'QLD', 'postal_code' => '4000'],
			'card'    => ['holder' => 'Jane Doe', 'number' => '4111111111111111', 'expiry' => '2030-01', 'security_code' => '123'],
			'items'   => [['sku' => 'ABC']],
		]);

		$this->assertFalse($result->anyFailed());
	}

	#[Test]
	public function an_object_is_still_accepted(): void
	{
		$schema = new Facade('checkout');
		$schema->addMoneyField('price', ['AUD' => 2]);

		$this->assertFalse($schema->validate(['price' => (object) ['amount' => '99.95', 'currency' => 'AUD']])->anyFailed());
	}

	#[Test]
	public function the_failure_is_reported_against_the_composite_itself(): void
	{
		$schema = new Facade('checkout');
		$schema->addMoneyField('price', ['AUD' => 2]);

		$result = $schema->validate(['price' => 'not-an-array']);

		/** @var array<string, string> $byField */
		$byField = [];

		foreach ($result as $composite) {
			foreach ($composite as $fieldResult) {
				foreach ($fieldResult as $constraint) {
					if ($constraint->name === 'type') {
						$byField[(string) $fieldResult->field->name] = $constraint->status->name;
					}
				}
			}
		}

		// The composite owns the failure; its sub-fields are skipped because nothing
		// reached them, so the one real problem is not buried under three faults.
		$this->assertSame('Failed', $byField['price']);
		$this->assertSame('Skipped', $byField['price.amount']);
		$this->assertSame('Skipped', $byField['price.currency']);
	}
}
