<?php
declare(strict_types=1);

namespace Meraki\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
			'billing' => (object)['street' => ['1 Queen St'], 'locality' => 'Brisbane', 'subdivision' => 'QLD', 'postal_code' => '4000', 'country' => 'AU'],
			'card'    => (object)['name' => 'Jane Doe', 'number' => '4111111111111111', 'expiry' => '2030-01', 'security_code' => '123'],
			'items'   => ['only' => (object)['sku' => 'ABC']],
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

	/**
	 * Every record-shaped field answers the same three questions the same way.
	 *
	 * The invariant: a **required** part that is absent, or present and `null`, fails that
	 * part's `*Required` constraint and names the part, so a form can mark the box. A part that
	 * was *sent* and holds nothing is `unreadable` instead — `''` was a decision somebody made,
	 * and reading it as absence would let whitespace satisfy a requiredness check.
	 *
	 * Only `Address` honoured this. `Money`, `CreditCard` and `PhoneNumber` collapsed all three
	 * cases into `unreadable`, so "you left the amount out" and "the amount is gibberish" were
	 * one verdict with no part on it — which is why `schema-html` ended up collapsing blank
	 * records to null before handing them over.
	 *
	 * One provider across all four, because the point is that they agree: a fifth record field
	 * that disagrees fails here rather than being discovered by a port.
	 *
	 * @param array<string, mixed> $complete
	 */
	#[Test]
	#[DataProvider('recordFields')]
	public function a_required_part_that_is_absent_names_itself(
		callable $make,
		array $complete,
		string $part,
		string $constraint,
	): void {
		$schema = new Facade('s');
		$schema->add($make($schema));

		$without = $complete;
		unset($without[$part]);

		foreach (['absent' => $without, 'null' => [$part => null] + $complete] as $how => $given) {
			$result = $schema->validate((object) ['f' => (object) $given])->forField('f');
			$failed = $result->forConstraint($constraint);

			$this->assertTrue($result->shape->passed(), "{$constraint}: {$how} should still be readable");
			$this->assertTrue($failed->failed(), "{$constraint}: {$how} should fail");
			$this->assertSame($part, $failed->part, "{$constraint}: {$how} should name the part");
		}

		// Sent and holding nothing is the other case, and it is a shape failure.
		$blank = $schema->validate((object) ['f' => (object) ([$part => ''] + $complete)])->forField('f');

		$this->assertTrue($blank->wasUnreadable(), "{$constraint}: blank should be unreadable");
	}

	/** @return iterable<string, array{callable, array<string, mixed>, string, string}> */
	public static function recordFields(): iterable
	{
		yield 'Money' => [
			static fn(Facade $s): Field => $s->createMoneyField('f', ['AUD' => 2]),
			['currency' => 'AUD', 'amount' => '10.00'],
			'amount',
			'amountRequired',
		];

		yield 'Address' => [
			static fn(Facade $s): Field => $s->createAddressField('f', ['AU']),
			['street' => ['1 Main St'], 'locality' => 'Bne', 'subdivision' => 'QLD', 'postal_code' => '4000', 'country' => 'AU'],
			'locality',
			'localityRequired',
		];

		yield 'CreditCard' => [
			static fn(Facade $s): Field => $s->createCreditCardField('f'),
			['number' => '4111111111111111', 'expiry' => '2030-01', 'name' => 'A B'],
			'expiry',
			'expiryRequired',
		];

		yield 'PhoneNumber' => [
			static fn(Facade $s): Field => $s->createPhoneNumberField('f', ['AU']),
			['number' => '0411222333', 'country' => 'AU'],
			'number',
			'numberRequired',
		];
	}

	/**
	 * And the qualifier that makes it an invariant rather than a rule of thumb: none of it
	 * applies to a field nobody has to fill in.
	 */
	#[Test]
	#[DataProvider('recordFields')]
	public function an_optional_field_is_skipped_rather_than_missing(callable $make): void
	{
		$schema = new Facade('s');
		$schema->add($make($schema)->makeOptional());

		foreach ([(object) [], (object) ['f' => null]] as $payload) {
			$this->assertTrue($schema->validate($payload)->forField('f')->shape->skipped());
		}
	}
}
