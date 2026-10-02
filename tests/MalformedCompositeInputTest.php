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
#[CoversClass(Field\File::class)]
#[CoversClass(Field\Money::class)]
#[CoversClass(Field\PhoneNumber::class)]
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
	/**
	 * A key the value does not know is **refused**, never dropped.
	 *
	 * Dropping it reports whatever its absence breaks. A payload saying `ammount` was told to
	 * enter an amount; `securty_code` left the security code absent and reported *that*. In both
	 * the one key that was wrong is the only thing nobody is told, and the data somebody meant
	 * to send is gone without a word.
	 *
	 * `Address` has refused since its parts were renamed, because a port still sending `line1`
	 * would otherwise build an address quietly missing a street. The other four ignored, which
	 * is the same bug waiting on a rename that has already happened once — `e164` and
	 * `local_part` stopped being parts in this same batch.
	 *
	 * Both halves are asserted, so this cannot pass by refusing everything.
	 *
	 * @param array<string, mixed> $complete
	 */
	#[Test]
	#[DataProvider('recordsWithAStrayKey')]
	public function a_key_the_value_does_not_know_is_refused(callable $make, array $complete, string $stray): void
	{
		$schema = new Facade('s');
		$schema->add($make($schema));

		$clean = $schema->validate((object) ['f' => (object) $complete])->forField('f');
		$strayed = $schema->validate((object) ['f' => (object) ([$stray => 'x'] + $complete)])->forField('f');

		$this->assertTrue($clean->shape->passed(), 'the payload without the stray key should read');
		$this->assertTrue($strayed->wasUnreadable(), "\"{$stray}\" should be refused");
	}

	/** @return iterable<string, array{callable, array<string, mixed>, string}> */
	public static function recordsWithAStrayKey(): iterable
	{
		// The stray keys are the mistakes that actually happen: a typo, and a part that was
		// renamed out from under a port.
		yield 'Money' => [
			static fn(Facade $s): Field => $s->createMoneyField('f', ['AUD' => 2]),
			['currency' => 'AUD', 'amount' => '10.00'],
			'ammount',
		];

		yield 'Address' => [
			static fn(Facade $s): Field => $s->createAddressField('f', ['AU']),
			['street' => ['1 Main St'], 'locality' => 'Bne', 'subdivision' => 'QLD', 'postal_code' => '4000', 'country' => 'AU'],
			'line1',
		];

		yield 'CreditCard' => [
			static fn(Facade $s): Field => $s->createCreditCardField('f'),
			['number' => '4111111111111111', 'expiry' => '2030-01', 'name' => 'A B'],
			'securty_code',
		];

		yield 'PhoneNumber' => [
			static fn(Facade $s): Field => $s->createPhoneNumberField('f', ['AU']),
			['number' => '0411222333', 'country' => 'AU'],
			'e164',
		];

		yield 'File' => [
			static fn(Facade $s): Field => $s->createFileField('f'),
			['name' => 'cv.pdf', 'type' => 'application/pdf', 'size' => 1024],
			'mime_type',
		];
	}

	/**
	 * The one exception, and it is not one: an upload carries more than its parts.
	 *
	 * `$_FILES` holds a temporary path, an error code and — since PHP 8.1 — the client's full
	 * path, beside the three this library reads. A port handing an entry straight over should
	 * not have to strip them first, so they are accepted and ignored. Accepted-and-ignored is a
	 * different thing from a key nobody declared, and only the second is refused.
	 */
	#[Test]
	public function an_upload_may_carry_the_keys_php_puts_on_it(): void
	{
		$schema = new Facade('s');
		$schema->add($schema->createFileField('f'));

		$result = $schema->validate((object) ['f' => (object) [
			'name' => 'cv.pdf',
			'full_path' => 'documents/cv.pdf',
			'type' => 'application/pdf',
			'tmp_name' => '/tmp/php1234',
			'error' => 0,
			'size' => 1024,
		]])->forField('f');

		$this->assertTrue($result->shape->passed());
	}
}
