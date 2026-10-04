<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Exception\InvalidScope;
use Meraki\Schema\Exception\ReadOnlyResult;
use Meraki\Schema\FieldName;
use Meraki\Schema\Message\Mf2\Formatter;
use Meraki\Schema\Message\Mf2\Mf2Translator;
use Meraki\Schema\Message\Mf2\Resource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The one integration point: `$result->violations`.
 *
 * Every failure is here, whichever step found it, each with its code, its part, its bound and —
 * when a language pack had wording — its sentence. Most of this file is about two properties: a
 * consumer never has to go back to the constraints to learn what to say, and the order a field's
 * problems read in never depends on the order its checks happened to run.
 */
#[Group('messages')]
#[CoversClass(Violations::class)]
#[CoversClass(Violation::class)]
final class ViolationsTest extends TestCase
{
	private static function translator(string $source): Mf2Translator
	{
		return new Mf2Translator('en', Resource::parse("@locale = en\n" . $source), new Formatter());
	}

	#[Test]
	public function a_violation_says_what_where_and_how_far_without_a_language_pack(): void
	{
		// Everything a renderer needs to say something, or to choose its own words, with no pack
		// installed at all.
		$violation = (new Text(new FieldName('bio')))->minLengthOf(10)->validate('short')->violations->first();

		$this->assertSame(Text\Check::MinLength, $violation?->code);
		$this->assertSame('minLength', $violation?->name);
		$this->assertNull($violation?->part);
		$this->assertSame(10, $violation?->bound);
		$this->assertNull($violation?->message);
	}

	#[Test]
	public function with_no_provider_every_violation_is_still_reported_and_none_is_worded(): void
	{
		// So reading `$result->violations` never needs a guard, and validating with no wording at
		// all is an ordinary way to use this library rather than a degraded one.
		$violations = (new Text(new FieldName('bio')))->minLengthOf(10)->validate('short')->violations;

		$this->assertCount(1, $violations);
		$this->assertSame([], $violations->messages);
	}

	#[Test]
	public function only_failures_are_violations(): void
	{
		// A constraint that passed has nothing to report, and one that was skipped never ran.
		$field = (new Text(new FieldName('bio')))->minLengthOf(2);
		$result = $field->validate('long enough')->withMessagesFrom(self::translator('minLength = Use at least {$bound} characters.'));

		$this->assertTrue($result->violations->isEmpty());
		$this->assertNull($result->violations->first());
	}

	#[Test]
	public function the_shape_is_reported_before_the_constraints(): void
	{
		// "That is not a valid card number" comes before anything the number would have been
		// checked against.
		$field = new EmailAddress(new FieldName('email'));
		$result = $field->validate('nope')->withMessagesFrom(self::translator("shape.unreadable = malformed\nminLength = too short"));

		$this->assertSame(ShapeProblem::Unreadable, $result->violations->first()?->code);
		$this->assertSame('malformed', $result->violations->first()?->message);
	}

	#[Test]
	public function parts_are_reported_in_the_order_the_value_declares_them(): void
	{
		// Not in the order the checks happened to run, which is not something a reader should be
		// able to notice. Ireland uses a county without requiring one, so a wrong county and a
		// wrong postcode can fail together.
		$field = new Address(new FieldName('billing'), ['IE']);
		$result = $field->validate((object) [
			'street' => ['12 Denham Street'],
			'locality' => 'Carlow',
			'subdivision' => 'ZZ',
			'postal_code' => '99',
			'country' => 'IE',
		])->withMessagesFrom(self::translator(
			"postalCodeFormat = bad postcode\n"
			. 'knownSubdivision = bad state',
		));

		$this->assertSame([Address\Part::Subdivision, Address\Part::PostalCode], $result->violations->parts);
		$this->assertSame(['bad state', 'bad postcode'], $result->violations->messages);
	}

	#[Test]
	public function a_part_with_nothing_wrong_is_absent_from_parts_but_still_askable(): void
	{
		$field = new Address(new FieldName('billing'), ['AU']);
		$result = $field->validate((object) [
			'street' => ['12 Denham Street'],
			'locality' => 'Rockhampton',
			'subdivision' => 'QLD',
			'postal_code' => '99',
			'country' => 'AU',
		]);

		$this->assertSame([Address\Part::PostalCode], $result->violations->parts);
		$this->assertTrue($result->forPart(Address\Part::Locality)->isEmpty());
		$this->assertSame(Address\Check::PostalCodeFormat, $result->forPart(Address\Part::PostalCode)[0]?->code);
	}

	#[Test]
	public function asking_about_a_part_the_value_does_not_have_is_refused(): void
	{
		// "Nothing is wrong" is a legitimate answer for a part that is fine, so a mistake that
		// returned it would be invisible forever. A misspelling cannot get this far — the part is an
		// enum case — but a part of some other kind of value can.
		$result = (new Address(new FieldName('billing'), ['AU']))->validate(null);

		$this->expectException(InvalidScope::class);
		$this->expectExceptionMessageMatches('/Amount.*There is: .*Country/');

		$result->forPart(Money\Part::Amount);
	}

	#[Test]
	public function a_failure_about_the_whole_value_is_not_filed_under_a_part(): void
	{
		$field = new Address(new FieldName('billing'), ['AU']);
		$result = $field->validate(null)->withMessagesFrom(self::translator('shape.missing = required'));

		$this->assertSame(['required'], $result->violations->forWholeValue()->messages);
		$this->assertSame([], $result->violations->parts);
	}

	#[Test]
	public function violations_are_iterated_counted_and_read_by_position(): void
	{
		$field = (new Password(new FieldName('secret')))->minLengthOf(12)->minNumberOfDigits(2);
		$violations = $field->validate('short')->violations;

		$this->assertCount(2, $violations);
		$this->assertSame(
			[Password\Check::MinLength, Password\Check::MinDigits],
			array_map(static fn(Violation $v): Check => $v->code, iterator_to_array($violations)),
		);
		$this->assertSame($violations->first(), $violations[0]);
		$this->assertTrue(isset($violations[1]));
		$this->assertFalse(isset($violations[2]));
		$this->assertNull($violations[2], 'past the end is nothing, the same answer first() gives an empty set');
	}

	#[Test]
	public function violations_cannot_be_written_to(): void
	{
		// A result is a record of what was decided; editing it could only make it say something
		// that was not.
		$violations = (new Text(new FieldName('bio')))->minLengthOf(10)->validate('short')->violations;

		$this->expectException(ReadOnlyResult::class);

		$violations[0] = new Violation(Text\Check::MaxLength);
	}

	#[Test]
	public function violations_cannot_be_removed(): void
	{
		$violations = (new Text(new FieldName('bio')))->minLengthOf(10)->validate('short')->violations;

		$this->expectException(ReadOnlyResult::class);

		unset($violations[0]);
	}
}
