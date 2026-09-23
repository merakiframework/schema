<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Facade;
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
#[CoversClass(Field\Address::class)]
#[CoversClass(Field\Collection::class)]
#[CoversClass(Field\CreditCard::class)]
#[CoversClass(Field\Money::class)]
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
		$schema->add($schema->createMoneyField('price', ['AUD' => 2]));
		$schema->add($schema->createAddressField('billing', ['AU']));
		$schema->add($schema->createCreditCardField('card'));
		$schema->add($schema->createCollectionField('items', $schema->createTextField('sku')));

		return $schema;
	}

	#[Test]
	#[DataProvider('unusableValues')]
	public function a_money_field_fails_rather_than_raises(mixed $value): void
	{
		$this->assertTrue($this->schema()->validate((object)['price' => $value])->anyFailed());
	}

	#[Test]
	#[DataProvider('unusableValues')]
	public function an_address_field_fails_rather_than_raises(mixed $value): void
	{
		$this->assertTrue($this->schema()->validate((object)['billing' => $value])->anyFailed());
	}

	#[Test]
	#[DataProvider('unusableValues')]
	public function a_credit_card_field_fails_rather_than_raises(mixed $value): void
	{
		$this->assertTrue($this->schema()->validate((object)['card' => $value])->anyFailed());
	}

	#[Test]
	#[DataProvider('unusableValues')]
	public function a_collection_field_fails_rather_than_raises(mixed $value): void
	{
		$this->assertTrue($this->schema()->validate((object)['items' => $value])->anyFailed());
	}

	#[Test]
	public function an_optional_composite_still_fails_on_unusable_input(): void
	{
		// Optional excuses an absent value, never a bad one.
		$schema = new Facade('checkout');
		$schema->add($schema->createMoneyField('price', ['AUD' => 2])->makeOptional());

		$this->assertTrue($schema->validate((object)['price' => 'not-an-array'])->anyFailed());
	}

	#[Test]
	public function an_optional_composite_left_out_is_still_skipped(): void
	{
		$schema = new Facade('checkout');
		$schema->add($schema->createMoneyField('price', ['AUD' => 2])->makeOptional());

		$this->assertFalse($schema->validate((object)[])->anyFailed());
	}

	#[Test]
	public function a_list_where_an_item_is_unusable_fails(): void
	{
		$schema = new Facade('checkout');
		$schema->add($schema->createCollectionField('items', $schema->createTextField('sku')));

		$this->assertTrue($schema->validate((object)['items' => ['not-an-item']])->anyFailed());
	}

	#[Test]
	public function well_formed_input_still_passes(): void
	{
		$result = $this->schema()->validate((object)[
			'price'   => (object)['amount' => '99.95', 'currency' => 'AUD'],
			'billing' => (object)['line1' => '1 Queen St', 'locality' => 'Brisbane', 'administrative_area' => 'QLD', 'postal_code' => '4000', 'country' => 'AU'],
			'card'    => (object)['name' => 'Jane Doe', 'number' => '4111111111111111', 'expiry' => '2030-01', 'security_code' => '123'],
			'items'   => [(object)['sku' => 'ABC']],
		]);

		$this->assertFalse($result->anyFailed());
	}

	#[Test]
	public function an_object_is_still_accepted(): void
	{
		$schema = new Facade('checkout');
		$schema->add($schema->createMoneyField('price', ['AUD' => 2]));

		$this->assertFalse($schema->validate((object)['price' => (object) ['amount' => '99.95', 'currency' => 'AUD']])->anyFailed());
	}

	#[Test]
	public function the_failure_is_reported_against_the_field_itself(): void
	{
		$schema = new Facade('checkout');
		$schema->add($schema->createMoneyField('price', ['AUD' => 2]));

		$resolved = $schema->validate((object)['price' => 'not-an-array'])->forField('price');

		// One field, one failure. This used to walk a tree of sub-field results looking for a
		// constraint named `type`, and assert that `price` failed it while `price.amount` and
		// `price.currency` were skipped — three verdicts for one unreadable value, with the
		// real problem the only one that mattered.
		//
		// A structured field owns its whole value now, so there are no sub-results to bury it
		// under, and readability is no longer a constraint at all: it is the *shape*, which is
		// the precondition every constraint depends on rather than one more rule among them.
		$this->assertTrue($resolved->shape->failed());
		$this->assertNotContains('type', $resolved->constraintNames, '`type` is not a constraint.');

		// Nothing could be checked against a value that could not be read, so every constraint
		// the field does have stands skipped rather than failed.
		foreach ($resolved->constraintNames as $name) {
			$this->assertTrue(
				$resolved->forConstraint($name)->status->skipped(),
				sprintf('"%s" should be skipped when the value is unreadable.', $name),
			);
		}
	}
}
