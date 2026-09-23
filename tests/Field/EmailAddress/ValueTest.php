<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\EmailAddress;

use Meraki\Schema\Field;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\FieldName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A value that cannot be built out of something that is not an address.
 *
 * The first field moved to enforcing its own invariant, and the one that shows why it matters.
 * The grammar and the canonicalising used to live in `EmailAddress::parse()`, which left the
 * constructor open — so a value could be built that had skipped the lower-casing its own
 * `equals()` relies on, and compared unequal to the same address parsed by a field.
 */
#[Group('field')]
#[CoversClass(Value::class)]
#[CoversClass(MalformedValue::class)]
final class ValueTest extends TestCase
{
	#[Test]
	public function it_splits_at_the_last_at_sign(): void
	{
		$value = new Value('kim@example.test');

		$this->assertSame('kim', $value->localPart);
		$this->assertSame('example.test', $value->domain);
		$this->assertSame('kim@example.test', (string) $value);
	}

	#[Test]
	public function the_domain_is_lower_cased_and_the_local_part_is_not(): void
	{
		// DNS says two spellings of a host are one host. RFC 5321 leaves the local part's case to
		// the receiving server, so folding it would merge two mailboxes a server may treat as
		// different.
		$value = new Value('Kim@EXAMPLE.TEST');

		$this->assertSame('Kim', $value->localPart);
		$this->assertSame('example.test', $value->domain);
	}

	#[Test]
	public function a_value_built_directly_agrees_with_one_a_field_parsed(): void
	{
		// The defect that moving the invariant fixed. The canonicalising lived in the field, so
		// `new Value('kim', 'EXAMPLE.TEST')` skipped it and compared unequal to the same address —
		// with the value's own equals() giving the wrong answer.
		$field = new Field\EmailAddress(new FieldName('email'));

		$parsed = $field->resolvedValueFor('kim@EXAMPLE.TEST');
		$built = new Value('kim@EXAMPLE.TEST');

		$this->assertNotNull($parsed);
		$this->assertTrue($parsed->equals($built));
		$this->assertTrue($built->equals($parsed));
	}

	#[Test]
	public function it_round_trips_through_its_string_form(): void
	{
		// What lets a field hand its own value back to parse() unchanged.
		$value = new Value('Kim@EXAMPLE.TEST');

		$this->assertTrue($value->equals(new Value((string) $value)));
	}

	/** @return array<string, array{string}> */
	public static function notAddresses(): array
	{
		return [
			'no at sign' => ['kim'],
			'nothing before the at' => ['@example.test'],
			'nothing after the at' => ['kim@'],
			'no dot is fine, no domain is not' => ['kim@'],
			'a space inside' => ['kim doe@example.test'],
			'surrounded by spaces' => [' kim@example.test '],
			'empty' => [''],
			'a local part over 64 octets' => [str_repeat('a', 65) . '@example.test'],
		];
	}

	#[Test]
	#[DataProvider('notAddresses')]
	public function it_refuses_what_is_not_an_address(string $notAnAddress): void
	{
		$this->expectException(MalformedValue::class);

		new Value($notAnAddress);
	}

	#[Test]
	public function refusing_says_what_is_wrong(): void
	{
		try {
			new Value(str_repeat('a', 65) . '@example.test');
		} catch (MalformedValue $e) {
			$this->assertStringContainsString('64', $e->getMessage());
			$this->assertStringContainsString('EmailAddress', $e->getMessage(), 'the kind is named by its class, so a pack owns the words');

			return;
		}

		$this->fail('Expected a MalformedValue.');
	}

	#[Test]
	public function it_says_nothing_about_whether_an_address_is_acceptable(): void
	{
		// Shape, and only shape. A domain the field disallows and an address below its minimum
		// length are both perfectly well-formed addresses — they fail a *constraint*, which
		// reports which check failed and what the limit was. Raising here would report neither.
		$this->assertSame('a@b', (string) new Value('a@b'));
		$this->assertSame('kim@disposable.test', (string) new Value('kim@disposable.test'));
	}

	#[Test]
	public function the_field_reports_a_refusal_rather_than_raising_it(): void
	{
		// The rule that has not changed: parse() never raises, because it runs on input somebody
		// else chose. What moved is where the judgement is made, not who absorbs it.
		$field = new Field\EmailAddress(new FieldName('email'));

		$this->assertNull($field->resolvedValueFor('not an address'));
		$this->assertTrue($field->validate('not an address')->shape->wasUnreadable());
	}

	#[Test]
	public function the_field_accepts_its_own_value_back(): void
	{
		// A value is canonical by construction, so re-reading one can only produce itself. It used
		// to come back null, which made a parsed value unusable as a rule's bound.
		$field = new Field\EmailAddress(new FieldName('email'));
		$once = $field->resolvedValueFor('kim@example.test');

		$this->assertNotNull($once);
		$this->assertSame($once, $field->resolvedValueFor($once));
	}
}
