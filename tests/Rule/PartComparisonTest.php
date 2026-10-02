<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Definition;
use Meraki\Schema\FieldName;
use Meraki\Schema\PartScope;
use Meraki\Schema\Rule\Condition\Comparison;
use Meraki\Schema\ValueScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * A rule can compare two fields, and can compare one part of each.
 *
 * Two capabilities that arrived together because neither is much use alone. Comparing whole values
 * needs the expectation to be a scope rather than a literal; comparing *parts* needs a scope that
 * can name one. Between them they cover the questions a checkout form actually asks — "is the
 * shipping address the billing address", and the weaker but more useful "are they at least in the
 * same country".
 */
#[Group('rule')]
#[CoversClass(Comparison::class)]
#[CoversClass(PartScope::class)]
final class PartComparisonTest extends TestCase
{
	private const AU = [
		'street' => ['1 Denham St'],
		'locality' => 'Rockhampton',
		'subdivision' => 'QLD',
		'postal_code' => '4700',
		'country' => 'AU',
	];

	private const NZ = [
		'street' => ['1 Queen St'],
		'locality' => 'Auckland',
		'subdivision' => 'AUK',
		'postal_code' => '1010',
		'country' => 'NZ',
	];

	private function schema(): Definition
	{
		$schema = new Definition('checkout');
		$schema->add(
			$schema->createAddressField('billing', ['AU', 'NZ']),
			$schema->createAddressField('shipping', ['AU', 'NZ']),
			$schema->createTextField('note'),
		);

		return $schema;
	}

	private function fired(Definition $schema, array $billing, array $shipping): bool
	{
		return $schema
			->validate((object) ['billing' => (object) $billing, 'shipping' => (object) $shipping])
			->forField('note')
			->wasAlteredByRule();
	}

	#[Test]
	public function two_whole_values_can_be_compared(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('shipping'))->equals(ValueScope::of('billing'))->then($schema->fields->getByName('note')->makeRequired()),
		);

		$this->assertTrue($this->fired($schema, self::AU, self::AU));
		$this->assertFalse($this->fired($schema, self::AU, ['street' => ['2 Denham St']] + self::AU));
		$this->assertFalse($this->fired($schema, self::AU, self::NZ));
	}

	#[Test]
	public function one_part_of_each_can_be_compared(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('shipping', 'country'))
				->equals(ValueScope::of('billing', 'country'))
				->then($schema->fields->getByName('note')->makeRequired()),
		);

		$this->assertTrue($this->fired($schema, self::AU, self::AU));

		// The weaker question, and the point of having it: a different street in the same country
		// is a different address and the same country.
		$this->assertTrue($this->fired($schema, self::AU, ['street' => ['2 Denham St']] + self::AU));
		$this->assertFalse($this->fired($schema, self::AU, self::NZ));
	}

	/**
	 * Nothing requires the two sides to be the same part, or even the same kind of field. The
	 * comparison is between two values, and two values that are not alike answer false.
	 */
	#[Test]
	public function two_different_parts_may_be_compared(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('shipping', 'postal_code'))
				->equals(ValueScope::of('billing', 'locality'))
				->then($schema->fields->getByName('note')->makeRequired()),
		);

		$this->assertFalse($this->fired($schema, self::AU, self::AU));
		$this->assertTrue($this->fired($schema, ['locality' => '4700'] + self::AU, self::AU));
	}

	/**
	 * A part nobody submitted is absent, not an error — so two absent parts are equal, which is
	 * the honest answer to "are these the same" when neither exists.
	 */
	#[Test]
	public function an_absent_part_resolves_to_nothing(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('shipping', 'dependent_locality'))
				->equals(ValueScope::of('billing', 'dependent_locality'))
				->then($schema->fields->getByName('note')->makeRequired()),
		);

		$this->assertTrue($this->fired($schema, self::AU, self::AU));
	}

	/**
	 * A part whose emptiness is a list rather than a string.
	 *
	 * `street` is the first part held as a list, and emptiness was decided by a match on `null`,
	 * `Countable` and `Stringable|string` — a plain PHP array is none of those, so it fell to
	 * the default and an absent street reported as *not* empty. A latent hole for any
	 * array-valued part, which `street` is simply the first to stand in.
	 */
	#[Test]
	public function an_absent_street_is_empty(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('billing', 'street'))
				->isEmpty()
				->then($schema->fields->getByName('note')->makeRequired()),
		);

		$withoutStreet = array_diff_key(self::AU, ['street' => null]);

		$this->assertTrue($this->fired($schema, $withoutStreet, self::AU));
		$this->assertFalse($this->fired($schema, self::AU, self::AU));
	}

	#[Test]
	public function a_part_may_be_compared_against_a_literal(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('billing', 'country'))->equals('AU')->then($schema->fields->getByName('note')->makeRequired()),
		);

		$this->assertTrue($this->fired($schema, self::AU, self::AU));
		$this->assertFalse($this->fired($schema, self::NZ, self::NZ));
	}

	#[Test]
	public function a_mistyped_part_is_refused_where_the_rule_is_written(): void
	{
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('has no part "ctry"');

		$schema->addRule($schema->when(ValueScope::of('billing', 'ctry'))->equals('AU')->then($schema->fields->getByName('note')->makeRequired()));
	}

	#[Test]
	public function a_part_of_a_field_that_has_none_is_refused(): void
	{
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('holds one value rather than named parts');

		$schema->addRule($schema->when(ValueScope::of('note', 'country'))->equals('AU')->then($schema->fields->getByName('note')->makeRequired()));
	}

	/**
	 * The expectation is checked too, not just the subject.
	 */
	#[Test]
	public function a_bad_part_on_the_expectation_is_refused(): void
	{
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('has no part "ctry"');

		$schema->addRule(
			$schema->when(ValueScope::of('billing', 'country'))
				->equals(ValueScope::of('shipping', 'ctry'))
				->then($schema->fields->getByName('note')->makeRequired()),
		);
	}

	#[Test]
	public function notequals_is_the_same_comparison_negated(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('shipping', 'country'))
				->notEquals(ValueScope::of('billing', 'country'))
				->then($schema->fields->getByName('note')->makeRequired()),
		);

		$this->assertFalse($this->fired($schema, self::AU, self::AU));
		$this->assertTrue($this->fired($schema, self::AU, self::NZ));
	}

	// ── a part the value canonicalised ─────────────────────────────────────────────────────

	/**
	 * A rule is written in the spelling a submitter uses, and must still match.
	 *
	 * An address stores `AU-QLD` whichever of `QLD`, `qld`, `AU-QLD` or `Queensland` arrived, and
	 * `AU` for `Australia`. The stored side went through that and the expectation did not, so
	 * `equals('QLD')` compared `AU-QLD` against `QLD` — false for every request there would ever
	 * be. Accepted at authoring, silently dead, and written in the very spelling the field
	 * accepts as input, which is what made it so easy to write.
	 *
	 * @param list<string> $spellings every way of naming the thing the address actually holds
	 */
	#[Test]
	#[DataProvider('spellingsOfOnePlace')]
	public function a_rule_matches_whichever_spelling_it_was_written_with(string $part, array $spellings): void
	{
		foreach ($spellings as $spelling) {
			$schema = $this->schema();
			$note = $schema->fields->getByName(new FieldName('note'));

			$schema->addRule(
				$schema->when(PartScope::of('billing', $part))->equals($spelling)->then($note->makeOptional()),
			);

			$this->assertTrue($this->fired($schema, self::AU, self::AU), "{$part} = {$spelling}");
		}
	}

	/** @return iterable<string, array{string, list<string>}> */
	public static function spellingsOfOnePlace(): iterable
	{
		yield 'a subdivision' => ['subdivision', ['QLD', 'qld', 'AU-QLD', 'au-qld', 'Queensland']];
		yield 'a country' => ['country', ['AU', 'au', 'Australia']];
	}

	#[Test]
	public function canonicalising_the_expectation_does_not_make_everything_match(): void
	{
		// The fix must not turn the comparison into "resolves to something" — a different place
		// still has to answer no.
		$schema = $this->schema();
		$note = $schema->fields->getByName(new FieldName('note'));

		$schema->addRule(
			$schema->when(PartScope::of('billing', 'subdivision'))->equals('NSW')->then($note->makeOptional()),
		);

		$this->assertFalse($this->fired($schema, self::AU, self::AU));
	}

	// ── a part held as a list ──────────────────────────────────────────────────────────────

	/**
	 * A textual question of a list asks it of each entry.
	 *
	 * `street` is up to three lines, so resolving it gave an array, and every scalar verb
	 * compared an array against a string and answered no. Six of the seven verbs were dead; only
	 * `isEmpty` worked, and only because it had been taught about lists already.
	 */
	#[Test]
	#[DataProvider('questionsAboutAList')]
	public function a_question_about_a_list_part_is_asked_of_each_entry(string $verb, mixed $argument, bool $expected): void
	{
		$schema = $this->schema();
		$note = $schema->fields->getByName(new FieldName('note'));
		$twoLines = ['street' => ['Level 3', 'PO Box 42']] + self::AU;

		$schema->addRule(
			$schema->when(PartScope::of('billing', 'street'))->{$verb}($argument)->then($note->makeOptional()),
		);

		$this->assertSame($expected, $this->fired($schema, $twoLines, self::AU));
	}

	/** @return iterable<string, array{string, mixed, bool}> */
	public static function questionsAboutAList(): iterable
	{
		yield 'matches the second line' => ['matches', '/^PO Box/i', true];
		yield 'matches no line' => ['matches', '/^Unit/', false];
		yield 'contains, in the second line' => ['contains', 'PO Box', true];
		yield 'contains, in no line' => ['contains', 'Penthouse', false];
		yield 'equals the first line' => ['equals', 'Level 3', true];
		yield 'equals no line' => ['equals', 'Level 4', false];
		yield 'isIn, one candidate matching' => ['isIn', ['Level 3', 'nowhere'], true];
		yield 'isIn, none matching' => ['isIn', ['nowhere', 'nothing'], false];
	}

	#[Test]
	public function an_ordered_question_about_a_list_part_is_refused_where_it_is_written(): void
	{
		// A list has no order, so there is nothing for isAtLeast to rank. Refused rather than
		// left to never fire, and the message names the verbs a list does answer.
		$schema = $this->schema();
		$note = $schema->fields->getByName(new FieldName('note'));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/holds a list of entries, which has no order/');

		$schema->addRule(
			$schema->when(PartScope::of('billing', 'street'))->isAtLeast(3)->then($note->makeOptional()),
		);
	}

	#[Test]
	public function an_ordered_question_about_an_ordinary_part_is_still_allowed(): void
	{
		// The refusal is about the list, not about parts. A postcode is one string.
		$schema = $this->schema();
		$note = $schema->fields->getByName(new FieldName('note'));

		$schema->addRule(
			$schema->when(PartScope::of('billing', 'postal_code'))->isAtLeast('1000')->then($note->makeOptional()),
		);

		$this->assertCount(1, $schema->rules);
	}
}
