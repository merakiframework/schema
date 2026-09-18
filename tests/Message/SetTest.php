<?php
declare(strict_types=1);

namespace Meraki\Schema\Message;

use InvalidArgumentException;
use Meraki\Schema\Field;
use Meraki\Schema\FieldName;
use Meraki\Schema\Message\Mf2\Formatter;
use Meraki\Schema\Message\Mf2\Mf2Translator;
use Meraki\Schema\Message\Mf2\Resource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The one integration point: `$result->messages`.
 *
 * Two shapes, and which one you get follows from the *field* rather than from what happened to
 * fail. That is the property most of this file is about — a consumer that checks the type once
 * should not break on a request that failed differently.
 */
#[Group('messages')]
#[CoversClass(Set::class)]
#[CoversClass(FlatSet::class)]
#[CoversClass(PartedSet::class)]
#[CoversClass(Field\ValueClass::class)]
final class SetTest extends TestCase
{
	private static function translator(string $source): Mf2Translator
	{
		return new Mf2Translator('en', Resource::parse("@locale = en\n" . $source), new Formatter());
	}

	#[Test]
	public function a_field_holding_one_value_reports_flat(): void
	{
		$field = (new Field\Text(new FieldName('bio')))->minLengthOf(10);

		$this->assertInstanceOf(FlatSet::class, $field->validate('short')->messages);
	}

	#[Test]
	public function a_field_whose_value_has_parts_reports_parted(): void
	{
		$field = new Field\Address(new FieldName('billing'), ['AU']);

		$this->assertInstanceOf(PartedSet::class, $field->validate(null)->messages);
	}

	#[Test]
	public function the_shape_is_decided_by_the_field_even_when_nothing_failed(): void
	{
		// The load-bearing one. Deciding it from the results would mean an address that happened to
		// fail only on the whole value came back flat, and the consumer that checked the type once
		// would break on the request that failed the other way.
		$field = new Field\Address(new FieldName('billing'), ['AU']);
		$passing = $field->validate((object) [
			'line1' => '12 Denham Street',
			'locality' => 'Rockhampton',
			'postal_code' => '4700',
			'country' => 'AU',
		]);

		$this->assertFalse($passing->anyFailed());
		$this->assertInstanceOf(PartedSet::class, $passing->messages);
		$this->assertTrue($passing->messages->isEmpty());
	}

	#[Test]
	public function a_field_with_no_provider_has_an_empty_set_rather_than_null(): void
	{
		// So reading `$result->messages` never needs a guard. Validating with no messages at all is
		// a supported way to use this library, so "nothing to say" travels the ordinary path.
		$messages = (new Field\Text(new FieldName('bio')))->minLengthOf(10)->validate('short')->messages;

		$this->assertTrue($messages->isEmpty());
		$this->assertCount(0, $messages);
		$this->assertNull($messages->first);
		$this->assertSame([], $messages->all);
	}

	#[Test]
	public function only_failures_produce_messages(): void
	{
		// A constraint that passed has nothing to report and one that was skipped never ran.
		$field = (new Field\Text(new FieldName('bio')))->minLengthOf(2);
		$translator = self::translator('minLength = Use at least {$bound} characters.');

		$this->assertTrue($field->validate('long enough')->withMessagesFrom($translator)->messages->isEmpty());
	}

	#[Test]
	public function the_shape_is_reported_before_the_constraints(): void
	{
		// "That is not a valid card number" comes before anything the number would have been
		// checked against.
		$field = new Field\EmailAddress(new FieldName('email'));
		$translator = self::translator("shape.unreadable = malformed\nminLength = too short");

		$this->assertSame('malformed', $field->validate('nope')->withMessagesFrom($translator)->messages->first);
	}

	#[Test]
	public function parts_are_reported_in_the_order_the_value_declares_them(): void
	{
		// Not in the order the constraints happened to run, which is not something a reader should
		// be able to notice.
		$field = new Field\Address(new FieldName('billing'), ['AU']);
		$result = $field->validate((object) [
			'line1' => '12 Denham Street',
			'locality' => 'Rockhampton',
			'administrative_area' => 'ZZ',
			'postal_code' => '99',
			'country' => 'AU',
		])->withMessagesFrom(self::translator(
			"postalCodeFormat = bad postcode\n"
			. 'administrativeArea = bad state',
		));

		$messages = $result->messages;

		$this->assertInstanceOf(PartedSet::class, $messages);
		$this->assertSame(['administrative_area', 'postal_code'], $messages->parts);
		$this->assertSame(['bad state', 'bad postcode'], $messages->all);
	}

	#[Test]
	public function a_part_with_nothing_wrong_is_absent_from_parts_but_still_askable(): void
	{
		$field = new Field\Address(new FieldName('billing'), ['AU']);
		$result = $field->validate((object) [
			'line1' => '12 Denham Street',
			'locality' => 'Rockhampton',
			'postal_code' => '99',
			'country' => 'AU',
		])->withMessagesFrom(self::translator('postalCodeFormat = bad postcode'));

		$messages = $result->messages;

		$this->assertInstanceOf(PartedSet::class, $messages);
		$this->assertSame(['postal_code'], $messages->parts);
		$this->assertTrue($messages->forPart('locality')->isEmpty());
	}

	#[Test]
	public function asking_about_a_part_that_does_not_exist_is_refused(): void
	{
		// "No messages" is a legitimate answer for a part that is fine, so a typo that returned it
		// would be invisible forever — the same reason a scope refuses a mistyped part.
		$field = new Field\Address(new FieldName('billing'), ['AU']);
		$messages = $field->validate(null)->messages;

		$this->assertInstanceOf(PartedSet::class, $messages);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/postal_code/');

		$messages->forPart('postcode');
	}

	#[Test]
	public function a_failure_about_the_whole_value_is_not_filed_under_a_part(): void
	{
		$field = new Field\Address(new FieldName('billing'), ['AU']);
		$result = $field->validate(null)->withMessagesFrom(self::translator('shape.missing = required'));

		$messages = $result->messages;

		$this->assertInstanceOf(PartedSet::class, $messages);
		$this->assertSame(['required'], $messages->whole->all);
		$this->assertSame([], $messages->parts);
	}

	#[Test]
	public function a_set_can_be_iterated_and_counted(): void
	{
		$field = (new Field\Password(new FieldName('secret')))->minLengthOf(12)->minNumberOfDigits(2);
		$result = $field->validate('short')->withMessagesFrom(self::translator(
			"minLength = too short\n"
			. 'minDigits = more digits',
		));

		$this->assertCount(2, $result->messages);
		$this->assertSame(['too short', 'more digits'], iterator_to_array($result->messages));
	}
}
