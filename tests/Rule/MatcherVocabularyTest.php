<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use InvalidArgumentException;
use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use Meraki\Schema\Message\Vocabulary;
use Meraki\Schema\Rule\Condition\Contains;
use Meraki\Schema\Rule\Condition\Emptiness;
use Meraki\Schema\Rule\Condition\IsAtLeast;
use Meraki\Schema\Rule\Condition\IsAtMost;
use Meraki\Schema\Rule\Condition\IsBetween;
use Meraki\Schema\Rule\Condition\IsEmpty;
use Meraki\Schema\Rule\Condition\IsGreaterThan;
use Meraki\Schema\Rule\Condition\IsIn;
use Meraki\Schema\Rule\Condition\IsLessThan;
use Meraki\Schema\Rule\Condition\IsNotEmpty;
use Meraki\Schema\Rule\Condition\Matches;
use Meraki\Schema\Rule\Condition\Ordered;
use Meraki\Schema\Rule\Condition\Textual;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use Stringable;

/**
 * The ten matchers beyond `equals` and `notEquals`.
 *
 * Most of this file is boundaries, because a matcher that is off by one at its edge is the kind of
 * defect nobody notices for a year: the rule fires most of the time, and the one input it gets
 * wrong is the one input nobody tries. Every inclusive matcher is tested *at* its bound, and every
 * exclusive one is tested at the same place for the opposite answer.
 *
 * The rest is refusals. A rule that can never hold raises no error while looking like a working
 * rule, which is the defect `Condition\Comparison` was written to remove — so each new matcher
 * that can be written meaninglessly is refused where it is written.
 */
#[Group('rule')]
#[CoversClass(Ordered::class)]
#[CoversClass(IsAtLeast::class)]
#[CoversClass(IsGreaterThan::class)]
#[CoversClass(IsAtMost::class)]
#[CoversClass(IsLessThan::class)]
#[CoversClass(IsBetween::class)]
#[CoversClass(IsIn::class)]
#[CoversClass(Emptiness::class)]
#[CoversClass(IsEmpty::class)]
#[CoversClass(IsNotEmpty::class)]
#[CoversClass(Textual::class)]
#[CoversClass(Contains::class)]
#[CoversClass(Matches::class)]
#[CoversClass(Matcher\Basic::class)]
#[CoversClass(Matcher\Ordered::class)]
#[CoversClass(Matcher\Text::class)]
#[CoversClass(Matcher\OrderedText::class)]
final class MatcherVocabularyTest extends TestCase
{
	/**
	 * A schema whose `flag` field is optional until a rule says otherwise, so "did the condition
	 * hold" is one readable assertion rather than an inspection of applied outcomes.
	 */
	private function schema(): Facade
	{
		$schema = new Facade('vocabulary');

		return $schema->add(
			$schema->createNumberField('age')->makeOptional(),
			$schema->createDateField('starts')->makeOptional(),
			$schema->createMoneyField('price', ['AUD', 'USD'])->makeOptional(),
			$schema->createTextField('notes')->makeOptional(),
			$schema->createEnumField('country', ['AU', 'NZ', 'US'])->makeOptional(),
			$schema->createTextField('flag')->makeOptional(),
		);
	}

	/** Whether the rule fired, read off the effective definition it produced. */
	private function fired(Facade $schema, array $data): bool
	{
		return $schema->validate((object) $data)->forField('flag')->field->optional === false;
	}

	/** @return array<string, array{string, mixed, string, bool}> */
	public static function bounds(): array
	{
		return [
			// Inclusive at the bound; exclusive one step either side of it. The middle column of
			// each pair is the case that separates the two matchers, and the only one worth
			// arguing about.
			'isAtLeast below' => ['isAtLeast', 18, '17', false],
			'isAtLeast at' => ['isAtLeast', 18, '18', true],
			'isAtLeast above' => ['isAtLeast', 18, '19', true],

			'isGreaterThan below' => ['isGreaterThan', 18, '17', false],
			'isGreaterThan at' => ['isGreaterThan', 18, '18', false],
			'isGreaterThan above' => ['isGreaterThan', 18, '19', true],

			'isAtMost below' => ['isAtMost', 18, '17', true],
			'isAtMost at' => ['isAtMost', 18, '18', true],
			'isAtMost above' => ['isAtMost', 18, '19', false],

			'isLessThan below' => ['isLessThan', 18, '17', true],
			'isLessThan at' => ['isLessThan', 18, '18', false],
			'isLessThan above' => ['isLessThan', 18, '19', false],
		];
	}

	#[Test]
	#[DataProvider('bounds')]
	public function an_ordered_matcher_holds_on_the_right_side_of_its_bound(
		string $matcher,
		mixed $bound,
		string $submitted,
		bool $expected,
	): void {
		$schema = $this->schema();
		$schema->addRule($schema->when('age')->{$matcher}($bound)->then($schema->fields->getByName('flag')->makeRequired()));

		$this->assertSame($expected, $this->fired($schema, ['age' => $submitted]));
	}

	#[Test]
	public function an_ordered_matcher_compares_in_the_fields_own_terms(): void
	{
		// The whole reason Ordered goes through Comparison's reading: a date field resolves to a
		// LocalDate, and `'2030-06-01'` is a string until the field has read it.
		$schema = $this->schema();
		$schema->addRule($schema->when('starts')->isLessThan('2030-06-01')->then($schema->fields->getByName('flag')->makeRequired()));

		$this->assertTrue($this->fired($schema, ['starts' => '2030-05-31']));
		$this->assertFalse($this->fired($schema, ['starts' => '2030-06-01']));
	}

	#[Test]
	public function an_ordered_matcher_does_not_hold_when_nothing_was_submitted(): void
	{
		// Answerable, and the answer is no. "Is age at least 18" on a request with no age is not
		// an error.
		$schema = $this->schema();
		$schema->addRule($schema->when('age')->isAtLeast(18)->then($schema->fields->getByName('flag')->makeRequired()));

		$this->assertFalse($this->fired($schema, []));
	}

	/** @return array<string, array{string, bool}> */
	public static function betweenBounds(): array
	{
		return [
			'below' => ['17', false],
			'at the floor' => ['18', true],
			'inside' => ['40', true],
			'at the ceiling' => ['65', true],
			'above' => ['66', false],
		];
	}

	#[Test]
	#[DataProvider('betweenBounds')]
	public function is_between_is_inclusive_at_both_ends(string $submitted, bool $expected): void
	{
		$schema = $this->schema();
		$schema->addRule($schema->when('age')->isBetween(18, 65)->then($schema->fields->getByName('flag')->makeRequired()));

		$this->assertSame($expected, $this->fired($schema, ['age' => $submitted]));
	}

	#[Test]
	public function is_between_is_its_two_bounds_rather_than_a_third_rule(): void
	{
		// Stated as a test because it is the argument for the inclusivity above: the range holds
		// one IsAtLeast and one IsAtMost and asks both, so it cannot drift from them.
		$between = new IsBetween('#/fields/age/value', 18, 65);

		$this->assertSame(18, $between->atLeast);
		$this->assertSame(65, $between->atMost);
		$this->assertSame(18, $between->expected);
	}

	#[Test]
	public function is_in_holds_for_any_of_its_candidates(): void
	{
		$schema = $this->schema();
		$schema->addRule($schema->when('country')->isIn(['AU', 'NZ'])->then($schema->fields->getByName('flag')->makeRequired()));

		$this->assertTrue($this->fired($schema, ['country' => 'AU']));
		$this->assertTrue($this->fired($schema, ['country' => 'NZ']));
		$this->assertFalse($this->fired($schema, ['country' => 'US']));
	}

	#[Test]
	public function is_in_reads_every_candidate_through_the_field(): void
	{
		// Each candidate separately, never the list as a whole — so a number field is asked about
		// `18`, not about an array.
		$schema = $this->schema();
		$schema->addRule($schema->when('age')->isIn([18, 21])->then($schema->fields->getByName('flag')->makeRequired()));

		$this->assertTrue($this->fired($schema, ['age' => '21']));
		$this->assertFalse($this->fired($schema, ['age' => '20']));
	}

	#[Test]
	public function contains_is_a_substring_test(): void
	{
		$schema = $this->schema();
		$schema->addRule($schema->when('notes')->contains('urgent')->then($schema->fields->getByName('flag')->makeRequired()));

		$this->assertTrue($this->fired($schema, ['notes' => 'nothing urgent here']));
		$this->assertFalse($this->fired($schema, ['notes' => 'all calm']));
	}

	#[Test]
	public function contains_is_case_sensitive(): void
	{
		// Documented rather than incidental: case-insensitivity is `matches` with an `i` flag,
		// not a second matcher or an option nobody would find.
		$schema = $this->schema();
		$schema->addRule($schema->when('notes')->contains('urgent')->then($schema->fields->getByName('flag')->makeRequired()));

		$this->assertFalse($this->fired($schema, ['notes' => 'URGENT']));
	}

	#[Test]
	public function matches_takes_a_pattern_with_its_delimiters(): void
	{
		$schema = $this->schema();
		$schema->addRule($schema->when('notes')->matches('/^INV-/')->then($schema->fields->getByName('flag')->makeRequired()));

		$this->assertTrue($this->fired($schema, ['notes' => 'INV-42']));
		$this->assertFalse($this->fired($schema, ['notes' => 'CR-42']));
	}

	#[Test]
	public function a_text_matcher_does_not_hold_for_a_value_with_no_text(): void
	{
		// Password and CreditCard have no __toString() on purpose, so a rule cannot read the text
		// of a secret. The consequence reaching this far is a feature of that decision.
		$schema = new Facade('secrets');
		$schema->add(
			$schema->createPasswordField('secret')->makeOptional(),
			$schema->createTextField('flag')->makeOptional(),
		);
		$schema->addRule($schema->when('secret')->contains('a')->then($schema->fields->getByName('flag')->makeRequired()));

		$result = $schema->validate((object) ['secret' => 'correct horse battery staple']);

		$this->assertTrue($result->forField('flag')->field->optional);
	}

	#[Test]
	public function is_empty_holds_when_nothing_was_submitted(): void
	{
		$schema = $this->schema();
		$schema->addRule($schema->when('notes')->isEmpty()->then($schema->fields->getByName('flag')->makeRequired()));

		$this->assertTrue($this->fired($schema, []));
		$this->assertFalse($this->fired($schema, ['notes' => 'something']));
	}

	#[Test]
	public function is_not_empty_is_exactly_its_negation(): void
	{
		$schema = $this->schema();
		$schema->addRule($schema->when('notes')->isNotEmpty()->then($schema->fields->getByName('flag')->makeRequired()));

		$this->assertFalse($this->fired($schema, []));
		$this->assertTrue($this->fired($schema, ['notes' => 'something']));
	}

	#[Test]
	public function an_ordered_matcher_on_a_field_with_no_order_is_refused_where_it_is_written(): void
	{
		// `when($username)->isAtLeast(3)` reads plausibly and can never hold. A field's value class
		// says so without a request, so this is caught at addRule() rather than by nothing at all.
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/has no order/');

		$schema->addRule($schema->when('notes')->isAtLeast(3)->then($schema->fields->getByName('flag')->makeRequired()));
	}

	#[Test]
	public function a_bound_the_field_cannot_read_is_refused_where_it_is_written(): void
	{
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches("/'eighteen'/");

		$schema->addRule($schema->when('age')->isAtLeast('eighteen')->then($schema->fields->getByName('flag')->makeRequired()));
	}

	#[Test]
	public function an_unreadable_candidate_anywhere_in_a_list_is_refused(): void
	{
		// The check sees through the list rather than at it, so a typo in the fifth of five
		// candidates is caught as readily as one in the first.
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches("/'twenty'/");

		$schema->addRule($schema->when('age')->isIn([18, 'twenty'])->then($schema->fields->getByName('flag')->makeRequired()));
	}

	#[Test]
	public function an_empty_is_in_is_refused(): void
	{
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/could never fire/');

		$schema->when('country')->isIn([]);
	}

	#[Test]
	public function a_pattern_that_does_not_compile_is_refused_where_it_is_written(): void
	{
		// Otherwise preg_match() returns false, the condition reads that as "did not match", and
		// the rule quietly never fires.
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/not a valid pattern/');

		$schema->when('notes')->matches('^INV-');
	}

	#[Test]
	public function an_empty_contains_is_refused(): void
	{
		// Every string contains the empty string, so this would hold for every request that
		// submitted anything — a rule that looks conditional and is not.
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/isNotEmpty/');

		$schema->when('notes')->contains('');
	}

	#[Test]
	public function two_amounts_in_different_currencies_raise_rather_than_comparing_falsely(): void
	{
		// The one case an ordered matcher does not answer quietly. Reading it as "does not hold"
		// would make isAtLeast silently false for every request in the wrong currency, which is
		// the dead-rule defect all over again — so Money\Value's refusal is allowed through.
		$schema = $this->schema();
		$schema->addRule(
			$schema->when('price')
				->isAtLeast((object) ['currency' => 'AUD', 'amount' => '10.00'])
				->then($schema->fields->getByName('flag')->makeRequired()),
		);

		$this->assertTrue($this->fired($schema, ['price' => (object) ['currency' => 'AUD', 'amount' => '20.00']]));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/cannot be ordered/');

		$schema->validate((object) ['price' => (object) ['currency' => 'USD', 'amount' => '20.00']]);
	}

	/** @return array<string, array{class-string, list<string>}> */
	public static function vocabularies(): array
	{
		$anything = ['equals', 'notEquals', 'isIn', 'isEmpty', 'isNotEmpty'];
		$order = ['isAtLeast', 'isGreaterThan', 'isAtMost', 'isLessThan', 'isBetween'];
		$text = ['contains', 'matches'];

		return [
			'Basic' => [Matcher\Basic::class, $anything],
			'Ordered' => [Matcher\Ordered::class, [...$anything, ...$order]],
			'Text' => [Matcher\Text::class, [...$anything, ...$text]],
			'OrderedText' => [Matcher\OrderedText::class, [...$anything, ...$order, ...$text]],
		];
	}

	#[Test]
	#[DataProvider('vocabularies')]
	public function a_matcher_offers_exactly_the_questions_it_is_for(string $matcher, array $expected): void
	{
		// Both directions. Missing a verb is the obvious failure; carrying one it should not have is
		// the quiet one, and it is the whole reason there are four of these rather than one.
		$offered = array_values(array_filter(
			get_class_methods($matcher),
			static fn(string $m): bool => $m !== '__construct',
		));

		sort($offered);
		sort($expected);

		$this->assertSame($expected, $offered, $matcher);
	}

	#[Test]
	public function each_field_offers_exactly_the_questions_its_value_can_answer(): void
	{
		// The guard that keeps nineteen one-line declarations honest. A field whose value gains
		// Comparable but whose when() still says Basic would silently offer less than it could,
		// and nothing else in the suite would notice.
		foreach (Vocabulary::fields() as $kind => $field) {
			$valueClass = Field\ValueClass::of($field);

			$ordered = $valueClass !== null && is_a($valueClass, Comparable::class, true);
			$worded = $valueClass !== null && is_a($valueClass, Stringable::class, true);

			$expected = match (true) {
				$ordered && $worded => Matcher\OrderedText::class,
				$ordered => Matcher\Ordered::class,
				$worded => Matcher\Text::class,
				default => Matcher\Basic::class,
			};

			$declared = (new ReflectionMethod($field, 'when'))->getReturnType();

			$this->assertInstanceOf(ReflectionNamedType::class, $declared, $kind);
			$this->assertSame($expected, $declared->getName(), sprintf(
				'%s parses to a value that is %s and %s, so when() should return %s.',
				$kind,
				$ordered ? 'ordered' : 'not ordered',
				$worded ? 'text' : 'not text',
				$expected,
			));
			$this->assertInstanceOf($expected, $field->when(), $kind);
		}
	}

	#[Test]
	public function a_field_named_by_string_gets_every_question(): void
	{
		// Facade::when() cannot resolve a type, so it answers with all twelve and leans on the
		// check that runs when the rule is added. That is the trade for by-name authoring.
		$schema = $this->schema();

		$this->assertInstanceOf(Matcher\OrderedText::class, $schema->when('notes'));
		$this->assertInstanceOf(Matcher\Text::class, $schema->fields->getByName('notes')->when());
	}
}
