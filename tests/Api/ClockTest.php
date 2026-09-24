<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use Meraki\Schema\FieldName;
use Brick\DateTime\Clock\FixedClock;
use Brick\DateTime\Instant;
use Brick\DateTime\ZonedDateTime;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * A field that asks "is this in the future" needs *now*, so it holds a clock — a source of
 * the instant, never an instant. That distinction is the whole point: a `SystemClock` is
 * stateless and safe on a shared definition, whereas reading `now` once and storing it would
 * be B7 again.
 */
#[CoversNothing]
#[Group('api-2.0')]
final class ClockTest extends TestCase
{
	private const NOW = '2026-09-09T00:00:00Z';

	private function fixed(string $at = self::NOW): FixedClock
	{
		return new FixedClock(self::instant($at));
	}

	/**
	 * `Instant` has no `parse()`, so the instant comes from a zoned date-time.
	 */
	private static function instant(string $at): Instant
	{
		return ZonedDateTime::parse($at)->getInstant();
	}

	#[Test]
	public function a_schema_declares_the_clock_its_fields_inherit(): void
	{
		// The same shape as Facade::for() — declared once, inherited by fields added after.
		$schema = new Facade('checkout', clock: $this->fixed());
		$schema->add($schema->createCreditCardField('card')->mustExpireInFuture());

		// Instants are compared as instants, not as strings: Brick omits zero seconds, so
		// `2026-09-09T00:00:00Z` prints as `2026-09-09T00:00Z`, and pinning the formatting would
		// assert Brick's choices rather than this library's behaviour.
		$this->assertTrue(
			self::instant(self::NOW)->isEqualTo($schema->resolve((object)['card' => (object)[]])->evaluatedAt),
		);
	}

	/**
	 * The per-request copy carries the clock, rather than quietly building a SystemClock.
	 *
	 * Latent when it was found: the request's instant is read from the original, and a field is a
	 * shared instance that already holds its own clock, so nothing observed the difference. It
	 * stops being latent the moment anything builds a field on the working copy — and it would
	 * have surfaced as a fixed-clock test failing for a reason nobody would connect to
	 * `copyForRequest()`.
	 */
	#[Test]
	public function the_per_request_copy_inherits_the_schemas_clock(): void
	{
		$clock = $this->fixed();
		$schema = (new Facade('checkout', clock: $clock))->for('AU');

		$copy = clone $schema;	// make sure clone is a shallow copy, not a deep one.

		$this->assertSame($clock, (new ReflectionProperty(Facade::class, 'clock'))->getValue($copy));

		// And the country defaults, for the same reason: a field built on the copy must be the
		// field the author would have got from the original.
		$this->assertSame(['AU'], $copy->createAddressField('billing')->allowedCountries);
		$this->assertSame($clock, $copy->createCreditCardField('card')->clock);
	}

	#[Test]
	public function a_field_may_override_the_schemas_clock(): void
	{
		$card = new Field\CreditCard(new FieldName('card'), clock: $this->fixed('2030-01-01T00:00:00Z'));

		$this->assertTrue(
			self::instant('2030-01-01T00:00:00Z')->isEqualTo($card->resolve((object)[])->evaluatedAt),
		);
	}

	#[Test]
	public function the_result_records_the_instant_it_was_judged_against(): void
	{
		// Which makes a verdict reproducible: the same input and the same instant give the
		// same answer, whenever the question is asked again.
		$card = new Field\CreditCard(new FieldName('card'), clock: $this->fixed());

		$resolved = $card->validate((object) [
			'name' => 'K Miller',
			'number' => '4014 1828 2909 8807',
			'expiry' => '2029-07',
			'security_code' => '936',
		]);

		$this->assertTrue(self::instant(self::NOW)->isEqualTo($resolved->evaluatedAt));
	}

	#[Test]
	public function an_expiry_in_the_past_fails_against_the_clock(): void
	{
		$card = (new Field\CreditCard(new FieldName('card'), clock: $this->fixed()))
			->mustExpireInFuture();

		$failed = $card->validate((object) [
			'name' => 'K Miller',
			'number' => '4014 1828 2909 8807',
			'expiry' => '2020-01',
			'security_code' => '936',
		])->forConstraint('expiryInFuture');

		$this->assertTrue($failed->failed());

		// The bound is the instant it was judged from, so a message can say what "expired" was
		// measured against. A string because a bound is what a message interpolates.
		$this->assertSame((string) self::instant(self::NOW), $failed->bound);
	}

	#[Test]
	public function the_same_card_passes_or_fails_purely_by_moving_the_clock(): void
	{
		$submitted = (object) [
			'name' => 'K Miller',
			'number' => '4014 1828 2909 8807',
			'expiry' => '2029-07',
			'security_code' => '936',
		];

		$before = (new Field\CreditCard(new FieldName('card'), clock: $this->fixed('2026-01-01T00:00:00Z')))
			->mustExpireInFuture();

		$after = (new Field\CreditCard(new FieldName('card'), clock: $this->fixed('2031-01-01T00:00:00Z')))
			->mustExpireInFuture();

		$this->assertFalse($before->validate($submitted)->forConstraint('expiryInFuture')->failed());
		$this->assertTrue($after->validate($submitted)->forConstraint('expiryInFuture')->failed());
	}

	#[Test]
	public function a_time_relative_constraint_is_exempt_from_the_definition_time_default_check(): void
	{
		// Defaults are checked when declared, but that cannot hold here: the answer changes
		// with the calendar, so a default valid at boot would fail years later without
		// anything having been edited. The carve-out is deliberate.
		$card = new Field\CreditCard(new FieldName('card'), clock: $this->fixed());

		$card->mustExpireInFuture()->defaultsTo((object) [
			'name' => 'K Miller',
			'number' => '4014 1828 2909 8807',
			'expiry' => '2029-07',
			'security_code' => '936',
		]);

		$this->addToAssertionCount(1);
	}

	#[Test]
	public function a_card_expiring_absurdly_far_out_is_caught_as_a_typo(): void
	{
		// Cards are issued three to five years ahead, so 2099 is a slip rather than a card.
		// A baseline ceiling, not configuration.
		$card = new Field\CreditCard(new FieldName('card'), clock: $this->fixed());

		$failed = $card->validate((object) [
			'name' => 'K Miller',
			'number' => '4014 1828 2909 8807',
			'expiry' => '2099-01',
			'security_code' => '936',
		]);

		$this->assertTrue($failed->forConstraint('expiryWithinReach')->failed());
	}
}
