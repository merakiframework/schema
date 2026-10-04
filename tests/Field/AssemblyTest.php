<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AtomicField;
use Meraki\Schema\Definition;
use Meraki\Schema\Exception\BrokenInputContract;
use Meraki\Schema\Exception\InconsistentInput;
use Meraki\Schema\Exception\InvalidDefault;
use Meraki\Schema\Exception\InvalidRule;
use Meraki\Schema\Field\Fixture\Span;
use Meraki\Schema\FieldName;
use Meraki\Schema\PartScope;
use Meraki\Schema\PrefillPolicy;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Scope;
use Meraki\Schema\ScopeResolver;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\ValueScope;
use Meraki\Schema\ValueSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A value made of parts is assembled before it is judged.
 *
 * Tested against {@see Span}, a fixture with two essential parts, an optional one, and a single
 * constraint. A shipped field could move under a test like this; the fixture cannot.
 */
#[Group('field')]
#[CoversClass(AtomicField::class)]
#[CoversClass(Definition::class)]
#[CoversTrait(\Meraki\Schema\Field\Definition::class)]
#[CoversClass(ShapeValidationResult::class)]
#[CoversClass(ScopeResolver::class)]
#[CoversClass(ValueClass::class)]
final class AssemblyTest extends TestCase
{
	#[Test]
	public function a_whole_value_reaches_the_constraints(): void
	{
		$result = Span::named('window')->maxWidthOf(5)->validate((object) ['from' => 1, 'to' => 10]);

		$this->assertTrue($result->shape->passed());
		$this->assertEquals(new Span\Value(1, 10), $result->value);
		$this->assertTrue($result->forConstraint(Span\Check::MaxWidth)?->failed());
	}

	#[Test]
	public function parts_that_make_no_value_are_judged_by_no_constraint(): void
	{
		$result = Span::named('window')->maxWidthOf(5)->validate((object) ['from' => 1]);

		$this->assertTrue($result->wasIncomplete());
		$this->assertSame(ValidationStatus::Failed, $result->status);
		$this->assertNull($result->value);
		$this->assertTrue($result->forConstraint(Span\Check::MaxWidth)?->skipped());
	}

	#[Test]
	public function every_part_in_the_way_is_reported_at_once_in_reading_order(): void
	{
		$result = Span::named('window')->validate((object) ['label' => 5, 'to' => 'ten']);

		$this->assertSame(
			[Span\Check::FromRequired, Span\Check::ToFormat, Span\Check::LabelFormat],
			array_map(static fn(Violation $violation): Check => $violation->code, iterator_to_array($result->violations)),
		);
		$this->assertSame([Span\Part::From], $result->missingParts);
	}

	#[Test]
	public function a_part_supplied_and_unreadable_is_wrong_rather_than_missing(): void
	{
		$result = Span::named('window')->validate((object) ['from' => 'one', 'to' => 2]);

		$this->assertSame([], $result->missingParts);
		$this->assertSame(Span\Check::FromFormat, $result->forPart(Span\Part::From)->first()?->code);
	}

	#[Test]
	public function the_parts_have_to_agree_with_each_other(): void
	{
		$result = Span::named('window')->validate((object) ['from' => 9, 'to' => 1]);

		$this->assertTrue($result->wasIncomplete());
		$this->assertSame([], $result->missingParts);
		$this->assertSame(Span\Check::InOrder, $result->forPart(Span\Part::To)->first()?->code);
	}

	#[Test]
	public function nothing_is_said_about_the_whole_value_when_its_parts_have_their_own(): void
	{
		$result = Span::named('window')->validate((object) ['from' => 1]);

		$this->assertTrue($result->violations->forWholeValue()->isEmpty());
		$this->assertSame([Span\Part::To], $result->violations->parts);
	}

	#[Test]
	public function a_part_that_is_not_essential_may_be_left_out(): void
	{
		$result = Span::named('window')->validate((object) ['from' => 1, 'to' => 2]);

		$this->assertSame(ValidationStatus::Passed, $result->status);
		$this->assertSame([Span\Part::From, Span\Part::To], Span::named('window')->essentialParts);
	}

	#[Test]
	public function an_optional_field_needs_every_essential_part_once_anything_is_sent(): void
	{
		$span = Span::named('window')->makeOptional();

		$this->assertTrue($span->validate(null)->shape->skipped());
		$this->assertTrue($span->validate((object) ['from' => 1])->wasIncomplete());
	}

	/**
	 * A form that renders a box per part and was left alone submits every part empty. That is
	 * nothing submitted, not an attempt: it was unreadable, so a prefill lost to it and an
	 * optional field failed for being left alone.
	 */
	#[Test]
	#[DataProvider('nothingInIt')]
	public function a_record_with_nothing_in_it_was_not_submitted(object $given): void
	{
		$span = Span::named('window');

		$this->assertTrue($span->treatsAsAbsent($given));
		$this->assertTrue($span->validate($given)->wasMissing());
		$this->assertFalse($span->validate($given)->wasIncomplete());
		$this->assertTrue($span->makeOptional()->validate($given)->shape->skipped());
		$this->assertNull($span->resolvedInputFor($given));
	}

	/** @return array<string, array{object}> */
	public static function nothingInIt(): array
	{
		return [
			'no keys' => [(object) []],
			'every part null' => [(object) ['from' => null, 'to' => null, 'label' => null]],
			'one part null' => [(object) ['label' => null]],
		];
	}

	#[Test]
	public function a_record_with_anything_in_it_was_submitted(): void
	{
		$span = Span::named('window');

		// A part sent as '' or [] was sent: somebody decided to send it.
		$this->assertFalse($span->treatsAsAbsent((object) ['label' => '']));
		$this->assertFalse($span->treatsAsAbsent((object) ['from' => 1]));
		$this->assertFalse($span->treatsAsAbsent('not a record'));
		$this->assertTrue($span->treatsAsAbsent(null));

		// A key the value does not have is never nothing, however empty: it is the port that is
		// wrong, so the record is read and a shipped field refuses it for that, rather than letting
		// it pass for a form left alone.
		$this->assertFalse($span->treatsAsAbsent((object) ['till' => null]));
		$this->expectException(BrokenInputContract::class);

		(new Definition('s'))->createMoneyField('price', ['AUD'])->validate((object) ['ammount' => null]);
	}

	#[Test]
	public function an_empty_record_is_something_to_a_field_without_parts(): void
	{
		$note = (new Definition('s'))->createTextField('note');

		$this->assertFalse($note->treatsAsAbsent((object) []));
		$this->assertTrue($note->validate((object) [])->wasUnreadable());
	}

	#[Test]
	public function the_default_stands_in_for_a_record_with_nothing_in_it(): void
	{
		$result = Span::named('window')->defaultsTo((object) ['from' => 1, 'to' => 2])->validate((object) ['to' => null]);

		$this->assertSame(ValueSource::Default, $result->source);
		$this->assertEquals(new Span\Value(1, 2), $result->value);
	}

	#[Test]
	public function a_prefill_stands_in_for_a_record_with_nothing_in_it(): void
	{
		$schema = new Definition('booking');
		$schema->add(Span::named('window'));

		$result = $schema->validate(
			(object) ['window' => (object) ['from' => null, 'to' => null]],
			(object) ['window' => (object) ['from' => 3, 'to' => 4]],
		)->forField('window');

		$this->assertSame(ValueSource::Prefilled, $result?->source);
		$this->assertEquals(new Span\Value(3, 4), $result?->value);
	}

	#[Test]
	public function what_was_sent_is_still_what_a_result_echoes(): void
	{
		$schema = new Definition('booking');
		$schema->add(Span::named('window'));

		$sent = (object) ['from' => null];
		$result = $schema->validate((object) ['window' => $sent])->forField('window');

		$this->assertSame($sent, $result?->given);
		$this->assertSame(ValueSource::None, $result?->source);
		$this->assertTrue($result?->wasMissing());
	}

	#[Test]
	public function a_default_with_nothing_in_it_is_refused_where_it_is_written(): void
	{
		$this->expectException(InvalidDefault::class);
		$this->expectExceptionMessage('The default for "window" is a record with no part in it');

		Span::named('window')->defaultsTo((object) ['from' => null]);
	}

	#[Test]
	public function resolving_assembles_without_judging(): void
	{
		$result = Span::named('window')->resolve((object) ['from' => 1]);

		$this->assertSame(ValidationStatus::Pending, $result->status);
		$this->assertNull($result->value);
		$this->assertFalse($result->wasIncomplete());
	}

	#[Test]
	public function the_parts_as_read_are_there_to_ask_for(): void
	{
		$span = Span::named('window');

		$input = $span->resolvedInputFor((object) ['from' => 1, 'label' => 'Night']);

		$this->assertInstanceOf(Span\Input::class, $input);
		$this->assertSame(['from' => 1, 'to' => null, 'label' => 'night'], $input->parts());
		$this->assertNull($span->resolvedValueFor((object) ['from' => 1]));
		$this->assertNull($span->resolvedInputFor(null));
		$this->assertNull((new Definition('s'))->createTextField('note')->resolvedInputFor('hello'));
	}

	#[Test]
	public function a_trusted_prefill_must_still_make_a_value(): void
	{
		$schema = new Definition('booking');
		$schema->add(Span::named('window')->maxWidthOf(1));

		$incomplete = $schema->validate(null, (object) ['window' => (object) ['from' => 1]], PrefillPolicy::Trusted)->forField('window');
		$tooWide = $schema->validate(null, (object) ['window' => (object) ['from' => 1, 'to' => 9]], PrefillPolicy::Trusted)->forField('window');

		// Trust waives what the field accepts, never what counts as a value.
		$this->assertTrue($incomplete?->wasIncomplete());
		$this->assertSame(ValidationStatus::Passed, $tooWide?->status);
		$this->assertTrue($tooWide?->constraints->allSkipped());
	}

	#[Test]
	public function a_default_whose_parts_make_no_value_is_refused_where_it_is_written(): void
	{
		$this->expectException(InvalidDefault::class);
		$this->expectExceptionMessage('The default for "window" does not make a whole value: "toRequired" on its to.');

		Span::named('window')->defaultsTo((object) ['from' => 1]);
	}

	#[Test]
	public function a_default_is_assembled_before_any_constraint_is_asked_about_it(): void
	{
		$this->expectException(InvalidDefault::class);
		$this->expectExceptionMessage('The default for "window" does not satisfy its own "maxWidth" constraint.');

		Span::named('window')->maxWidthOf(1)->defaultsTo((object) ['from' => 1, 'to' => 5]);
	}

	#[Test]
	public function a_rule_about_one_part_reads_a_half_filled_record(): void
	{
		$schema = new Definition('booking');
		$schema->add(Span::named('window'));
		$schema->add($note = $schema->createTextField('note')->makeOptional());
		$schema->addRule($schema->when(PartScope::of('window', 'from'))->equals(1)->then($note->makeRequired()));

		$result = $schema->validate((object) ['window' => (object) ['from' => 1]]);

		$this->assertTrue($result->forField('note')?->wasMissing());
	}

	#[Test]
	public function a_rule_about_one_part_compares_in_the_terms_the_part_was_stored_in(): void
	{
		$schema = new Definition('booking');
		$schema->add(Span::named('window'));
		$schema->add($note = $schema->createTextField('note')->makeOptional());
		$schema->addRule($schema->when(PartScope::of('window', 'label'))->equals('URGENT')->then($note->makeRequired()));

		// Neither end is there, so there is no value — and the label is still read, and still
		// compared the way the input stored it.
		$result = $schema->validate((object) ['window' => (object) ['label' => 'Urgent']]);

		$this->assertTrue($result->forField('note')?->wasMissing());
	}

	#[Test]
	public function a_rule_about_the_whole_value_sees_nothing_until_the_parts_make_one(): void
	{
		$schema = new Definition('booking');
		$schema->add($window = Span::named('window'));
		$schema->add($note = $schema->createTextField('note')->makeOptional());
		$schema->addRule($window->when()->isNotEmpty()->then($note->makeRequired()));

		$halfFilled = $schema->validate((object) ['window' => (object) ['from' => 1]]);
		$whole = $schema->validate((object) ['window' => (object) ['from' => 1, 'to' => 2]]);

		$this->assertFalse($halfFilled->forField('note')?->anyFailed());
		$this->assertTrue($whole->forField('note')?->wasMissing());
	}

	#[Test]
	public function a_rule_cannot_compare_a_whole_value_against_half_of_one(): void
	{
		$schema = new Definition('booking');
		$schema->add($window = Span::named('window'));
		$schema->add($note = $schema->createTextField('note')->makeOptional());

		$this->expectException(InvalidRule::class);

		$schema->addRule($window->when()->equals((object) ['from' => 1])->then($note->makeRequired()));
	}

	#[Test]
	public function a_part_of_a_row_is_read_from_what_the_row_submitted(): void
	{
		$schema = new Definition('booking');
		$schema->add($schema->createCollectionField('slots', Span::named('window')));
		$resolver = new ScopeResolver($schema->fields, ['slots' => ['first' => (object) ['window' => (object) ['from' => 3]]]]);

		$this->assertSame(3, $resolver->resolve(Scope::parse('#/fields/slots/value/first/window/value/from')));
		$this->assertNull($resolver->resolve(Scope::parse('#/fields/slots/value/first/window/value')));
		$this->assertNull($resolver->resolve(Scope::parse('#/fields/slots/value/second/window/value/from')));
	}

	#[Test]
	public function what_a_field_holds_is_read_through_its_input(): void
	{
		$this->assertSame(Span\Value::class, ValueClass::of(Span::named('window')));
	}

	#[Test]
	public function an_input_that_reports_nothing_and_makes_nothing_is_a_bug_in_its_field(): void
	{
		$this->expectException(InconsistentInput::class);

		$this->brokenField()->validate((object) ['anything' => true]);
	}

	/**
	 * A field whose input says nothing is wrong and makes no value anyway.
	 */
	private function brokenField(): AtomicField
	{
		return new readonly class(new FieldName('broken')) extends AtomicField {
			public function __construct(public FieldName $name)
			{
				parent::__construct();

				$this->constraints = $this->defineConstraints();
			}

			public function when(): Matcher\Basic
			{
				return new Matcher\Basic(ValueScope::of($this->name));
			}

			protected function parse(mixed $value): Input
			{
				return new readonly class([], [], null) implements Input {
					/**
					 * @param list<Violation> $violations
					 * @param list<Part> $missingParts
					 */
					public function __construct(
						public array $violations,
						public array $missingParts,
						public ?ParsedValue $value,
					) {
					}

					public function parts(): array
					{
						return [];
					}

					public function canonicalPartValue(Part $part, mixed $expected): mixed
					{
						return $expected;
					}
				};
			}

			protected function defineConstraints(): Constraint\Set
			{
				return new Constraint\Set();
			}
		};
	}
}
