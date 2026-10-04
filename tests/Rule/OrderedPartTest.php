<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Definition;
use Meraki\Schema\Exception\IncomparableValues;
use Meraki\Schema\Field\CreditCard;
use Meraki\Schema\Field\File;
use Meraki\Schema\Field\Money;
use Meraki\Schema\PartScope;
use Meraki\Schema\Rule\Condition\Comparison;
use Meraki\Schema\Rule\Condition\Ordered;
use Brick\DateTime\LocalDate;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A rule about a part that holds a number or a date compares it as one.
 *
 * Money's amount, a card's expiry and a file's size were a `BigDecimal`, a `LocalDate` and an
 * `int`. A rule about any of them was accepted where it was written and never held: equality asked
 * an object that is not this library's, and the ordered verbs needed a `Comparable` and were handed
 * the expectation as written. Each part now holds a type of its field's own, and the expectation is
 * read into the same type through the input, so both sides are an amount, an expiry or a size.
 */
#[Group('rule')]
#[CoversClass(Comparison::class)]
#[CoversClass(Ordered::class)]
#[CoversClass(Money\Input::class)]
#[CoversClass(Money\Amount::class)]
#[CoversClass(CreditCard\Input::class)]
#[CoversClass(CreditCard\Expiry::class)]
#[CoversClass(File\Input::class)]
#[CoversClass(File\Size::class)]
final class OrderedPartTest extends TestCase
{
	/**
	 * Whether a rule about one part fired: it makes an optional note required.
	 *
	 * @param list<mixed> $arguments
	 */
	private static function fires(string $field, string $part, string $verb, array $arguments, object $submitted): bool
	{
		$schema = new Definition('order');
		$schema->add(
			$schema->createMoneyField('price', ['AUD', 'USD']),
			$schema->createCreditCardField('card'),
			$schema->createFileField('upload'),
			$note = $schema->createTextField('note')->makeOptional(),
		);
		$schema->addRule($schema->when(PartScope::of($field, $part))->{$verb}(...$arguments)->then($note->makeRequired()));

		return $schema->validate((object) [$field => $submitted])->forField('note')?->wasMissing() ?? false;
	}

	/**
	 * @param list<mixed> $arguments
	 */
	#[Test]
	#[DataProvider('anAmount')]
	#[DataProvider('anExpiry')]
	#[DataProvider('aSize')]
	public function the_rule_compares_the_part_in_its_own_terms(string $field, string $part, string $verb, array $arguments, object $submitted, bool $expected): void
	{
		$this->assertSame($expected, self::fires($field, $part, $verb, $arguments, $submitted));
	}

	/** @return iterable<string, array{string, string, string, list<mixed>, object, bool}> */
	public static function anAmount(): iterable
	{
		$aud = static fn(string|int $amount): object => (object) ['currency' => 'AUD', 'amount' => $amount];

		// The defect's own reproducer.
		yield 'at least 10, given 12.50' => ['price', 'amount', 'isAtLeast', [10], $aud('12.50'), true];
		yield 'at least 10, given 9.99' => ['price', 'amount', 'isAtLeast', [10], $aud('9.99'), false];
		yield 'at least "10.00", given 10' => ['price', 'amount', 'isAtLeast', ['10.00'], $aud(10), true];
		yield 'equals "12.50", given 12.5' => ['price', 'amount', 'equals', ['12.50'], $aud('12.5'), true];
		yield 'equals 12.5, given "12.50"' => ['price', 'amount', 'equals', [12.5], $aud('12.50'), true];
		yield 'between 10 and 20, given 15' => ['price', 'amount', 'isBetween', [10, 20], $aud(15), true];
		yield 'between 10 and 20, given 25' => ['price', 'amount', 'isBetween', [10, 20], $aud(25), false];
		yield 'one of 5 and 12.5, given 12.50' => ['price', 'amount', 'isIn', [['5', '12.5']], $aud('12.50'), true];

		// The part is the number, whatever it is a number of.
		yield 'less than 10, given 5 USD' => ['price', 'amount', 'isLessThan', [10], (object) ['currency' => 'USD', 'amount' => '5'], true];

		// Read the way the part was, or not at all: nothing to compare, so it does not hold.
		yield 'at least "ten"' => ['price', 'amount', 'isAtLeast', ['ten'], $aud('12.50'), false];
		yield 'at least 10, given no amount' => ['price', 'amount', 'isAtLeast', [10], (object) ['currency' => 'AUD'], false];
	}

	/** @return iterable<string, array{string, string, string, list<mixed>, object, bool}> */
	public static function anExpiry(): iterable
	{
		$card = static fn(string $expiry): object => (object) ['number' => '4111111111111111', 'expiry' => $expiry];

		yield 'at least 2027-01, given 2027-03' => ['card', 'expiry', 'isAtLeast', ['2027-01'], $card('2027-03'), true];
		yield 'at least 2027-01, given 2026-12' => ['card', 'expiry', 'isAtLeast', ['2027-01'], $card('2026-12'), false];

		// A month is its last day, on both sides.
		yield 'equals 2026-09, given 2026-09' => ['card', 'expiry', 'equals', ['2026-09'], $card('2026-09'), true];
		yield 'equals 2026-09-30, given 2026-09' => ['card', 'expiry', 'equals', ['2026-09-30'], $card('2026-09'), true];
		yield 'before 2026-09-15, given 2026-09' => ['card', 'expiry', 'isLessThan', ['2026-09-15'], $card('2026-09'), false];
	}

	/** @return iterable<string, array{string, string, string, list<mixed>, object, bool}> */
	public static function aSize(): iterable
	{
		$file = static fn(int|string $size): object => (object) ['name' => 'cv.pdf', 'type' => 'application/pdf', 'size' => $size];

		yield 'at most 1024, given 1000' => ['upload', 'size', 'isAtMost', [1024], $file(1000), true];
		yield 'at most 1024, given 2048' => ['upload', 'size', 'isAtMost', [1024], $file(2048), false];

		// Read as a form posts it, on either side.
		yield 'equals "2048", given 2048' => ['upload', 'size', 'equals', ['2048'], $file(2048), true];
		yield 'equals 2048, given "2048"' => ['upload', 'size', 'equals', [2048], $file('2048'), true];
		yield 'more than 0, given 0' => ['upload', 'size', 'isGreaterThan', [0], $file(0), false];
	}

	#[Test]
	public function a_part_is_compared_against_the_same_part_of_another_field(): void
	{
		$schema = new Definition('order');
		$schema->add(
			$schema->createMoneyField('price', ['AUD']),
			$schema->createMoneyField('budget', ['AUD']),
			$note = $schema->createTextField('note')->makeOptional(),
		);
		$schema->addRule(
			$schema->when(PartScope::of('price', Money\Part::Amount))->isGreaterThan(PartScope::of('budget', Money\Part::Amount))
				->then($note->makeRequired()),
		);

		$over = $schema->validate((object) [
			'price' => (object) ['currency' => 'AUD', 'amount' => '120'],
			'budget' => (object) ['currency' => 'AUD', 'amount' => '100.00'],
		]);
		$under = $schema->validate((object) [
			'price' => (object) ['currency' => 'AUD', 'amount' => '80'],
			'budget' => (object) ['currency' => 'AUD', 'amount' => '100.00'],
		]);

		$this->assertTrue($over->forField('note')?->wasMissing());
		$this->assertFalse($under->forField('note')?->wasMissing());
	}

	#[Test]
	public function an_amount_is_not_ranked_against_something_else(): void
	{
		// Within a kind, as every ordering is: a figure and an expiry have no order between them.
		$schema = new Definition('order');
		$schema->add(
			$schema->createMoneyField('price', ['AUD']),
			$schema->createCreditCardField('card'),
			$note = $schema->createTextField('note')->makeOptional(),
		);
		$schema->addRule(
			$schema->when(PartScope::of('price', Money\Part::Amount))->isAtLeast(PartScope::of('card', CreditCard\Part::Expiry))
				->then($note->makeRequired()),
		);

		$this->expectException(IncomparableValues::class);

		$schema->validate((object) [
			'price' => (object) ['currency' => 'AUD', 'amount' => '1'],
			'card' => (object) ['number' => '4111111111111111', 'expiry' => '2027-01'],
		]);
	}

	#[Test]
	public function a_part_resolves_to_its_own_type(): void
	{
		$fields = new Definition('order');
		$price = $fields->createMoneyField('price', ['AUD']);
		$card = $fields->createCreditCardField('card');
		$upload = $fields->createFileField('upload');

		$amount = $price->resolvedInputFor((object) ['currency' => 'AUD', 'amount' => '12.50'])?->parts()['amount'];
		$expiry = $card->resolvedInputFor((object) ['expiry' => '2026-09'])?->parts()['expiry'];
		$size = $upload->resolvedInputFor((object) ['size' => '2048'])?->parts()['size'];

		$this->assertEquals(new Money\Amount(BigDecimal::of('12.50')), $amount);
		$this->assertEquals(new CreditCard\Expiry(LocalDate::of(2026, 9, 30)), $expiry);
		$this->assertEquals(new File\Size(2048), $size);
	}
}
