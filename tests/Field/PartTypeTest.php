<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Comparison\Order;
use Meraki\Schema\Exception\IncomparableValues;
use Brick\DateTime\LocalDate;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The types a part holds when a rule has to compare it: money's amount, a card's expiry and a
 * file's size. Each belongs to its own field, so each is tested on its own terms.
 */
#[Group('field')]
#[CoversClass(Money\Amount::class)]
#[CoversClass(CreditCard\Expiry::class)]
#[CoversClass(File\Size::class)]
final class PartTypeTest extends TestCase
{
	#[Test]
	public function an_amount_is_a_number_whatever_its_scale(): void
	{
		$twelveFifty = new Money\Amount(BigDecimal::of('12.50'));

		$this->assertTrue($twelveFifty->equals(new Money\Amount(BigDecimal::of('12.5'))));
		$this->assertFalse($twelveFifty->equals(new Money\Amount(BigDecimal::of('12.51'))));
		$this->assertSame(Order::Greater, $twelveFifty->compareTo(new Money\Amount(BigDecimal::of('9.99'))));
		$this->assertSame(Order::Equal, $twelveFifty->compareTo(new Money\Amount(BigDecimal::of('12.5'))));
		$this->assertSame('12.50', (string) $twelveFifty);
	}

	#[Test]
	public function an_expiry_is_ordered_by_day(): void
	{
		$september = new CreditCard\Expiry(LocalDate::of(2026, 9, 30));

		$this->assertTrue($september->equals(new CreditCard\Expiry(LocalDate::of(2026, 9, 30))));
		$this->assertSame(Order::Less, $september->compareTo(new CreditCard\Expiry(LocalDate::of(2026, 10, 31))));
		$this->assertSame('2026-09-30', (string) $september);
	}

	#[Test]
	public function a_size_is_a_count_of_bytes(): void
	{
		$size = new File\Size(2048);

		$this->assertTrue($size->equals(new File\Size(2048)));
		$this->assertSame(Order::Greater, $size->compareTo(new File\Size(1024)));
		$this->assertSame('2048', (string) $size);
	}

	#[Test]
	public function a_negative_size_is_not_one(): void
	{
		$this->expectException(MalformedValue::class);

		new File\Size(-1);
	}

	#[Test]
	public function none_of_them_is_the_same_as_another_kind(): void
	{
		$amount = new Money\Amount(BigDecimal::of('2048'));
		$size = new File\Size(2048);

		// Equality never raises: a different kind is simply not equal.
		$this->assertFalse($amount->equals($size));
		$this->assertFalse($size->equals($amount));
	}

	#[Test]
	public function none_of_them_is_ordered_against_another_kind(): void
	{
		$this->expectException(IncomparableValues::class);
		$this->expectExceptionMessage('An amount can only be ordered against another amount.');

		new Money\Amount(BigDecimal::of('1'))->compareTo(new File\Size(1));
	}

	#[Test]
	public function an_expiry_is_not_ordered_against_a_size(): void
	{
		$this->expectException(IncomparableValues::class);

		new CreditCard\Expiry(LocalDate::of(2026, 9, 30))->compareTo(new File\Size(1));
	}

	#[Test]
	public function a_size_is_not_ordered_against_an_amount(): void
	{
		$this->expectException(IncomparableValues::class);

		new File\Size(1)->compareTo(new Money\Amount(BigDecimal::of('1')));
	}
}
