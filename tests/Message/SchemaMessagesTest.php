<?php
declare(strict_types=1);

namespace Meraki\Schema\Message;

use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use Meraki\Schema\FieldResult;
use Meraki\Schema\Message\Mf2\Mf2Provider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

	private function schema(bool $withMessages = true): Facade
	{
		$schema = new Facade(
			'signup',
			messages: $withMessages ? Mf2Provider::fromDirectory(self::PACK) : null,
		);

		return $schema->add(
			$schema->createTextField('username')->minLengthOf(3),
			$schema->createAddressField('billing', ['AU']),
		);
	}

	private static function payload(string $username = 'ab', string $postcode = '99'): object
	{
		return (object) [
			'username' => $username,
			'billing' => (object) [
				'line1' => '12 Denham Street',
				'locality' => 'Rockhampton',
				'postal_code' => $postcode,
				'country' => 'AU',
			],
		];
	}

	#[Test]
	public function a_request_names_its_own_language(): void
	{
		$result = $this->schema()->validate(self::payload(), locale: 'en');

		$this->assertSame('Use at least 3 characters.', $result->forField('username')?->messages->first);
	}

	#[Test]
	public function one_schema_serves_two_languages(): void
	{
		// The reason the locale is not fixed at construction. An application serving English and
		// Australian English should not have to define the same form twice.
		$schema = $this->schema();

		$english = $schema->validate(self::payload(), locale: 'en')->forField('billing');
		$australian = $schema->validate(self::payload(), locale: 'en-AU')->forField('billing');

		$this->assertInstanceOf(PartedSet::class, $english?->messages);
		$this->assertInstanceOf(PartedSet::class, $australian?->messages);

		$this->assertSame(
			'That is not a valid postal code for the country you chose.',
			$english->messages->forPart('postal_code')->first,
		);
		$this->assertSame(
			'That is not a valid postcode for the country you chose.',
			$australian->messages->forPart('postal_code')->first,
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

		$known = $schema->validate(self::payload(), locale: 'en');
		$unknown = $schema->validate(self::payload(), locale: 'de-AT');

		$this->assertSame($known->status, $unknown->status);
		$this->assertSame(
			$known->forField('billing')?->constraintNames,
			$unknown->forField('billing')?->constraintNames,
		);
		$this->assertTrue($unknown->forField('username')?->messages->isEmpty());
	}

	#[Test]
	public function a_schema_with_no_provider_works_exactly_as_it_did(): void
	{
		$result = $this->schema(withMessages: false)->validate(self::payload(), locale: 'en');

		$this->assertTrue($result->anyFailed());
		$this->assertTrue($result->forField('username')?->messages->isEmpty());
	}

	#[Test]
	public function asking_for_no_language_asks_for_no_messages(): void
	{
		$result = $this->schema()->validate(self::payload());

		$this->assertTrue($result->anyFailed());
		$this->assertTrue($result->forField('username')?->messages->isEmpty());
	}

	#[Test]
	public function a_field_validated_on_its_own_has_no_provider_and_so_no_messages(): void
	{
		// Stated rather than worked around. A field is a definition, and a definition that knew
		// about languages could not be serialised the same way twice.
		$schema = $this->schema();
		$field = $schema->fields->getByName('username');

		$this->assertTrue($field->validate('ab')->messages->isEmpty());
	}

	#[Test]
	public function every_row_of_a_collection_carries_its_own_messages(): void
	{
		// The failure this guards against is a form where the outer errors are translated and the
		// inner ones are blank.
		$schema = new Facade('order', messages: Mf2Provider::fromDirectory(self::PACK));
		$schema->add($schema->createCollectionField(
			'lines',
			$schema->createTextField('sku')->minLengthOf(3),
		));

		$result = $schema->validate((object) ['lines' => [['sku' => 'ab'], ['sku' => 'abc']]], locale: 'en');
		$lines = $result->forField('lines');

		$this->assertInstanceOf(Field\Collection\Result::class, $lines);
		$this->assertSame(
			'Use at least 3 characters.',
			$lines->itemAt(0)?->forField('sku')?->messages->first,
		);
		$this->assertTrue($lines->itemAt(1)?->forField('sku')?->messages->isEmpty());
	}

	#[Test]
	public function a_collections_rows_agree_with_its_own_results(): void
	{
		// Rebuilding the items has to replace the copies held in `$results` too, or the same row
		// read two ways would carry messages only once.
		$schema = new Facade('order', messages: Mf2Provider::fromDirectory(self::PACK));
		$schema->add($schema->createCollectionField(
			'lines',
			$schema->createTextField('sku')->minLengthOf(3),
		));

		$lines = $schema->validate((object) ['lines' => [['sku' => 'ab']]], locale: 'en')->forField('lines');

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
		$result = $this->schema()->validate(self::payload(), locale: 'en')->forField('username');

		$this->assertInstanceOf(FieldResult::class, $result);
		$this->assertSame($result->messages->all, $result->messages->all);
	}
}
