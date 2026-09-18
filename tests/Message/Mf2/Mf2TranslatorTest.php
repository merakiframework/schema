<?php
declare(strict_types=1);

namespace Meraki\Schema\Message\Mf2;

use Meraki\Schema\Field;
use Meraki\Schema\FieldName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Turning a verdict into a sentence: which key, in what order, and what a message may name.
 *
 * The part of the MF2 implementation that survives. {@see Formatter} goes when a real
 * implementation arrives; this is about *this library's* vocabulary and would be needed whatever
 * rendered the strings.
 */
#[Group('messages')]
#[CoversClass(Mf2Translator::class)]
#[CoversClass(Resource::class)]
final class Mf2TranslatorTest extends TestCase
{
	private static function translator(string $source): Mf2Translator
	{
		return new Mf2Translator('en', Resource::parse("@locale = en\n" . $source), new Formatter());
	}

	private static function text(string $name = 'bio'): Field\Text
	{
		return new Field\Text(new FieldName($name));
	}

	#[Test]
	public function it_renders_a_constraint_under_its_generic_name(): void
	{
		$field = self::text()->minLengthOf(10);
		$translator = self::translator('minLength = Use at least {$bound} characters.');

		$this->assertSame(
			'Use at least 10 characters.',
			$translator->forConstraint($field, $field->validate('short')->forConstraint('minLength')),
		);
	}

	#[Test]
	public function a_field_specific_key_wins_over_the_generic_one(): void
	{
		// The whole reason the ladder exists: "use at least 12 characters" is poor advice for a
		// password even though it is the same constraint.
		$field = (new Field\Password(new FieldName('secret')))->minLengthOf(12);
		$translator = self::translator(
			"minLength = Use at least {\$bound} characters.\n"
			. 'Password.minLength = Choose a passphrase of at least {$bound} characters.',
		);

		$this->assertSame(
			'Choose a passphrase of at least 12 characters.',
			$translator->forConstraint($field, $field->validate('short')->forConstraint('minLength')),
		);
	}

	#[Test]
	public function a_part_specific_key_wins_over_the_field_specific_one(): void
	{
		$field = new Field\Address(new FieldName('billing'), ['AU']);
		$result = $field->validate(self::rockhampton(postcode: '99'))->forConstraint('postalCodeFormat');

		$translator = self::translator(
			"postalCodeFormat = generic\n"
			. "Address.postalCodeFormat = by field\n"
			. 'Address.postal_code.postalCodeFormat = by part',
		);

		$this->assertSame('by part', $translator->forConstraint($field, $result));
	}

	#[Test]
	public function a_key_nobody_wrote_is_null_rather_than_a_gap(): void
	{
		$field = self::text()->minLengthOf(10);

		$this->assertNull(
			self::translator('maxLength = x')
				->forConstraint($field, $field->validate('short')->forConstraint('minLength')),
		);
	}

	#[Test]
	public function the_kind_arrives_translated(): void
	{
		// A message never sees "EmailAddress". The library hands over words that are already in the
		// language, which is what lets the formatter stay as small as it is.
		$field = new Field\EmailAddress(new FieldName('email'));
		$translator = self::translator(
			"kind.EmailAddress = email address\n"
			. 'shape.unreadable = That is not a valid {$kind}.',
		);

		$this->assertSame(
			'That is not a valid email address.',
			$translator->forShape($field, $field->validate('nope')->shape),
		);
	}

	#[Test]
	public function an_untranslated_kind_falls_back_to_its_class_name(): void
	{
		$field = self::text();

		$this->assertSame(
			'That is not a Text.',
			self::translator('shape.unreadable = That is not a {$kind}.')
				->forShape($field, Field\ShapeValidationResult::unreadable()),
		);
	}

	#[Test]
	public function the_part_arrives_translated(): void
	{
		$field = new Field\Address(new FieldName('billing'), ['AU']);
		$result = $field->validate(self::rockhampton(postcode: '99'))->forConstraint('postalCodeFormat');

		$translator = self::translator(
			"part.postal_code = postcode\n"
			. 'postalCodeFormat = That is not a valid {$part}.',
		);

		$this->assertSame('That is not a valid postcode.', $translator->forConstraint($field, $result));
	}

	#[Test]
	public function missing_and_unreadable_are_different_sentences(): void
	{
		$field = self::text();
		$translator = self::translator("shape.missing = required\nshape.unreadable = malformed");

		$this->assertSame('required', $translator->forShape($field, Field\ShapeValidationResult::missing()));
		$this->assertSame('malformed', $translator->forShape($field, Field\ShapeValidationResult::unreadable()));
	}

	#[Test]
	public function a_shape_that_did_not_fail_has_nothing_to_say(): void
	{
		$field = self::text();
		$translator = self::translator('shape.missing = required');

		$this->assertNull($translator->forShape($field, Field\ShapeValidationResult::pass()));
	}

	#[Test]
	public function a_list_bound_is_joined_using_the_packs_own_separators(): void
	{
		// MessageFormat 2's default function registry has no :list, so there is nothing a pack
		// could write for this even with a complete implementation.
		$field = (new Field\Uri(new FieldName('site')))->allowSchemes('https', 'http', 'ftp');
		$translator = self::translator(
			"list.separator = {|, |}\n"
			. "list.lastSeparator = {| or |}\n"
			. 'allowedSchemes = Start with {$bound}.',
		);

		$this->assertSame(
			'Start with https, http or ftp.',
			$translator->forConstraint($field, $field->validate('gopher://x')->forConstraint('allowedSchemes')),
		);
	}

	#[Test]
	public function a_two_item_list_uses_only_the_last_separator(): void
	{
		$field = (new Field\Uri(new FieldName('site')))->allowSchemes('https', 'http');
		$translator = self::translator(
			"list.separator = {|, |}\n"
			. "list.lastSeparator = {| or |}\n"
			. 'allowedSchemes = Start with {$bound}.',
		);

		$this->assertSame(
			'Start with https or http.',
			$translator->forConstraint($field, $field->validate('gopher://x')->forConstraint('allowedSchemes')),
		);
	}

	#[Test]
	public function a_boolean_bound_is_worded_by_the_pack(): void
	{
		$field = (new Field\Boolean(new FieldName('terms')))->mustBeAccepted();
		$translator = self::translator("bound.true = yes\n" . 'accepted = Answer {$bound}.');

		$this->assertSame(
			'Answer yes.',
			$translator->forConstraint($field, $field->validate(false)->forConstraint('accepted')),
		);
	}

	#[Test]
	public function a_constraint_with_no_bound_renders_an_empty_one(): void
	{
		// Rather than raising. The message asking for a bound that does not exist is a pack bug,
		// and the place to catch it is the pack's build, not somebody's form.
		$field = new Field\Address(new FieldName('billing'), ['AU']);
		$result = $field->validate(self::rockhampton(area: 'ZZ'))->forConstraint('administrativeArea');
		$translator = self::translator('administrativeArea = Not recognised.{$bound}');

		$this->assertSame('Not recognised.', $translator->forConstraint($field, $result));
	}

	#[Test]
	public function a_message_naming_a_variable_the_library_does_not_supply_raises(): void
	{
		$field = self::text()->minLengthOf(10);

		$this->expectException(BadMessage::class);

		self::translator('minLength = At least {$minimum}.')
			->forConstraint($field, $field->validate('short')->forConstraint('minLength'));
	}

	private static function rockhampton(string $postcode = '4700', ?string $area = null): object
	{
		$address = [
			'line1' => '12 Denham Street',
			'locality' => 'Rockhampton',
			'postal_code' => $postcode,
			'country' => 'AU',
		];

		if ($area !== null) {
			$address['administrative_area'] = $area;
		}

		return (object) $address;
	}
}
