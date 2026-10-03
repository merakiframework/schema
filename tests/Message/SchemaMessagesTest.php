<?php
declare(strict_types=1);

namespace Meraki\Schema\Message;

use Meraki\Schema\Definition;
use Meraki\Schema\Field;
use Meraki\Schema\FieldResult;
use Meraki\Schema\Message;
use Meraki\Schema\Message\Mf2\Mf2Provider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

/**
 * Messages as a schema actually uses them: a provider registered once, a language per request.
 *
 * The arrangement is the claim. A definition is the same in every language — the same data passes
 * or fails identically — so the *provider* is a source registered with the schema and the *locale*
 * is part of the request. One schema serves every reader, and a language that is missing can never
 * change an outcome.
 */
#[Group('messages')]
#[CoversNothing]
final class SchemaMessagesTest extends TestCase
{
	private const PACK = __DIR__ . '/../fixtures/lang/basic';

	private function schema(): Definition
	{
		$schema = new Definition('signup');

		return $schema->add(
			$schema->createTextField('username')->minLengthOf(3),
			$schema->createAddressField('billing', ['AU']),
		);
	}

	private static function pack(): Mf2Provider
	{
		return Mf2Provider::fromDirectory(self::PACK);
	}

	private static function payload(string $username = 'ab', string $postcode = '99'): object
	{
		return (object) [
			'username' => $username,
			'billing' => (object) [
				'street' => ['12 Denham Street'],
				'locality' => 'Rockhampton',
				'postal_code' => $postcode,
				'country' => 'AU',
			],
		];
	}

	#[Test]
	public function a_request_names_its_own_language(): void
	{
		$result = $this->schema()->validate(self::payload(), locale: 'en', messages: self::pack());

		$this->assertSame('Use at least 3 characters.', $result->forField('username')?->violations->first()?->message);
	}

	#[Test]
	public function one_schema_serves_two_languages(): void
	{
		// The reason the locale is not fixed at construction. An application serving English and
		// Australian English should not have to define the same form twice.
		$schema = $this->schema();

		$english = $schema->validate(self::payload(), locale: 'en', messages: self::pack())->forField('billing');
		$australian = $schema->validate(self::payload(), locale: 'en-AU', messages: self::pack())->forField('billing');

		$this->assertInstanceOf(FieldResult::class, $english);
		$this->assertInstanceOf(FieldResult::class, $australian);

		$this->assertSame(
			'That is not a valid postal code for the country you chose.',
			$english->forPart(Field\Address\Part::PostalCode)->first()?->message,
		);
		$this->assertSame(
			'That is not a valid postcode for the country you chose.',
			$australian->forPart(Field\Address\Part::PostalCode)->first()?->message,
		);
	}

	#[Test]
	public function a_variant_changes_a_word_without_restating_the_sentence(): void
	{
		// The whole argument for a specificity ladder plus translated parts: en_AU.mfr redefines
		// `part.postal_code` and every message that mentions one follows.
		$provider = Mf2Provider::fromDirectory(self::PACK);

		$this->assertNull($provider->merged('en-AU')?->get('Address.postal_code.postalCodeFormat'));
	}

	#[Test]
	public function a_language_the_pack_does_not_have_leaves_every_verdict_alone(): void
	{
		// The property everything else rests on. Nothing about wording may change what was decided.
		$schema = $this->schema();

		$known = $schema->validate(self::payload(), locale: 'en', messages: self::pack());
		$unknown = $schema->validate(self::payload(), locale: 'de-AT', messages: self::pack());

		$this->assertSame($known->status, $unknown->status);
		$this->assertSame(
			$known->forField('billing')?->constraintNames,
			$unknown->forField('billing')?->constraintNames,
		);
		$this->assertSame([], $unknown->forField('username')?->violations->messages);
		$this->assertFalse($unknown->forField('username')?->violations->isEmpty(), 'still reported, only unworded');
	}

	#[Test]
	public function a_request_with_no_provider_works_exactly_as_it_did(): void
	{
		$result = $this->schema()->validate(self::payload(), locale: 'en');

		$this->assertTrue($result->anyFailed());
		$this->assertSame([], $result->forField('username')?->violations->messages);
	}

	#[Test]
	public function asking_for_no_language_asks_for_no_messages(): void
	{
		$result = $this->schema()->validate(self::payload(), messages: self::pack());

		$this->assertTrue($result->anyFailed());
		$this->assertSame([], $result->forField('username')?->violations->messages);
	}

	#[Test]
	public function a_field_validated_on_its_own_has_no_provider_and_so_no_messages(): void
	{
		// Stated rather than worked around. A field is a definition, and a definition that knew
		// about languages could not be serialised the same way twice.
		$schema = $this->schema();
		$field = $schema->fields->getByName('username');

		$this->assertSame([], $field->validate('ab')->violations->messages);
	}

	#[Test]
	public function every_row_of_a_collection_carries_its_own_messages(): void
	{
		// The failure this guards against is a form where the outer errors are translated and the
		// inner ones are blank.
		$schema = new Definition('order');
		$schema->add($schema->createCollectionField(
			'lines',
			$schema->createTextField('sku')->minLengthOf(3),
		));

		$result = $schema->validate(
			(object) ['lines' => ['too_short' => ['sku' => 'ab'], 'long_enough' => ['sku' => 'abc']]],
			locale: 'en',
			messages: self::pack(),
		);
		$lines = $result->forField('lines');

		$this->assertInstanceOf(Field\Collection\Result::class, $lines);
		$this->assertSame(
			'Use at least 3 characters.',
			$lines->itemAt('too_short')?->forField('sku')?->violations->first()?->message,
		);
		$this->assertTrue($lines->itemAt('long_enough')?->forField('sku')?->violations->isEmpty());
	}

	#[Test]
	public function a_collections_rows_agree_with_its_own_results(): void
	{
		// Rebuilding the items has to replace the copies held in `$results` too, or the same row
		// read two ways would carry sentences only once.
		$schema = new Definition('order');
		$schema->add($schema->createCollectionField(
			'lines',
			$schema->createTextField('sku')->minLengthOf(3),
		));

		$lines = $schema->validate((object) ['lines' => [['sku' => 'ab']]], locale: 'en', messages: self::pack())
			->forField('lines');

		$this->assertInstanceOf(Field\Collection\Result::class, $lines);

		foreach ($lines->results as $held) {
			if ($held instanceof Field\Collection\Item) {
				$this->assertSame($lines->itemAt($held->key), $held);
			}
		}

		$this->assertTrue($lines->anyFailed());
	}

	#[Test]
	public function messages_are_rendered_once_rather_than_held_as_a_translator(): void
	{
		// What a result says is fixed at the moment it was judged. Two reads cannot disagree
		// because somebody edited a pack in between.
		//
		// Asserted as the thing the name says — every violation holds a sentence, and nothing held
		// could produce a different one later — rather than by reading twice and comparing, which
		// could not fail: a set re-rendering from a held translator would compare equal too.
		$result = $this->schema()->validate(self::payload(), locale: 'en', messages: self::pack())->forField('username');

		$this->assertInstanceOf(FieldResult::class, $result);
		$this->assertNotSame([], $result->violations->messages);

		foreach ($result->violations as $violation) {
			$this->assertIsString($violation->message);

			foreach (self::everythingHeldBy($violation) as $held) {
				$this->assertNotInstanceOf(Message\Translator::class, $held);
				$this->assertNotInstanceOf(Message\Provider::class, $held);
			}
		}

		foreach (self::everythingHeldBy($result->violations) as $held) {
			$this->assertNotInstanceOf(Message\Translator::class, $held);
			$this->assertNotInstanceOf(Message\Provider::class, $held);
		}
	}

	/**
	 * Everything an object holds, one level in, so a test can assert what is *not* there.
	 *
	 * Flattens one level rather than recursing: a translator kept anywhere a sentence could come
	 * from would be at one of these two depths.
	 *
	 * @return list<mixed>
	 */
	private static function everythingHeldBy(object $object): array
	{
		$held = [];

		foreach ((new ReflectionObject($object))->getProperties() as $property) {
			if ($property->isVirtual()) {
				continue;
			}

			$value = $property->getValue($object);
			$held[] = $value;

			foreach (is_array($value) ? $value : [] as $inner) {
				$held[] = $inner;
			}
		}

		return $held;
	}
}
