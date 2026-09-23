<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Address;
use Meraki\Schema\Field\Address\Type;
use Meraki\Schema\Field\Address\Value;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(Address::class)]
#[CoversClass(Value::class)]
#[CoversClass(Type::class)]
final class AddressTest extends FieldTestCase
{
	public function createField(): Address
	{
		return new Address(new FieldName('billing'));
	}

	private function australian(): Address
	{
		return new Address(new FieldName('billing'), ['AU']);
	}

	/** @return array<string, string> */
	private static function rockhampton(string ...$without): array
	{
		return array_diff_key([
			'line1' => '1 Denham St',
			'locality' => 'Rockhampton',
			'administrative_area' => 'QLD',
			'postal_code' => '4700',
			'country' => 'AU',
		], array_flip($without));
	}

	// ── one field, one value ───────────────────────────────────────────────────────────────

	#[Test]
	public function it_holds_its_whole_value_rather_than_a_bag_of_sub_fields(): void
	{
		$this->assertFalse(property_exists($this->australian(), 'fields'));
	}

	#[Test]
	public function it_accepts_the_array_a_form_submits(): void
	{
		$resolved = $this->australian()->resolve((object) self::rockhampton());

		// process() converts, so the value object is there before any verdict is.
		$this->assertInstanceOf(Value::class, $resolved->value);
		$this->assertSame('1 Denham St', $resolved->value->line1);
	}

	#[Test]
	public function it_accepts_its_own_value_object(): void
	{
		$value = Value::of(
			line1: '1 Denham St',
			locality: 'Rockhampton',
			administrativeArea: 'QLD',
			postalCode: '4700',
			country: 'AU',
		);

		$this->assertEquals($value, $this->australian()->resolve($value)->value);
	}

	#[Test]
	#[DataProvider('notAnAddress')]
	public function it_rejects_what_cannot_be_read_as_an_address(mixed $given): void
	{
		$this->assertShapeFailed($this->australian()->validate((object) $given));
	}

	/** @return array<string, array{mixed}> */
	public static function notAnAddress(): array
	{
		return [
			'a string' => ['1 Denham St, Rockhampton'],
			'a number' => [4700],
			'nothing at all' => [null],
			'an array with no known parts' => [['street' => '1 Denham St']],
		];
	}

	#[Test]
	public function parts_submitted_as_empty_strings_are_an_address_that_is_wrong(): void
	{
		// Not the same as an absent one. `''` was submitted on purpose — a JSON client saying
		// `"line1": ""` said something — so the shape stands and the parts are judged. An untouched
		// HTML form sending the same thing is the port's to strip, not this field's to guess at.
		$resolved = $this->australian()->validate((object)['line1' => '', 'locality' => '  ', 'country' => 'AU']);

		$this->assertShapePassed($resolved);
		$this->assertConstraintValidationResultFailed('specific', $resolved);
	}

	#[Test]
	public function a_constraint_name_carries_no_trace_of_the_field_name(): void
	{
		// The point of owning the whole value. A name used to embed the field it came from, so
		// renaming `billing` changed every constraint it emitted and broke every message provider
		// matching on them.
		$field = new Address(new FieldName('invoice_address'), ['AU']);

		$names = implode(',', $field->validate((object) self::rockhampton())->constraintNames);

		$this->assertStringNotContainsString('invoice_address', $names);
		$this->assertStringNotContainsString('.', $names);
	}

	#[Test]
	public function each_constraint_names_the_part_it_is_about(): void
	{
		$expected = [
			'allowedCountries' => 'country',
			'postalCodeFormat' => 'postal_code',
			'administrativeArea' => 'administrative_area',
			'line1Visitable' => 'line1',
			'specific' => 'line1',
		];

		foreach ($this->australian()->constraints as $constraint) {
			$this->assertSame($expected[$constraint->name], $constraint->part, $constraint->name);
		}
	}

	// ── a street by default; an area on request ───────────────────────────────────────────

	#[Test]
	public function an_address_names_a_street_by_default(): void
	{
		// An address is a place, and a suburb with a postcode is a region that contains places.
		$failed = $this->australian()->validate((object) self::rockhampton(without: 'line1'))->forConstraint('specific');

		$this->assertTrue($failed->failed());
		$this->assertSame('line1', $failed->part);
	}

	#[Test]
	public function an_area_is_an_address_once_the_author_says_so(): void
	{
		// For a service area or a catchment, where the region *is* the answer rather than an
		// incomplete version of one.
		$resolved = $this->australian()->allowWithoutStreet()->validate((object) self::rockhampton(without: 'line1'));

		$this->assertFalse($resolved->anyFailed());
		$this->assertConstraintValidationResultSkipped('specific', $resolved);
	}

	// ── what it is for, and what it must be capable of ────────────────────────────────────

	#[Test]
	public function it_accepts_either_purpose_by_default(): void
	{
		$this->assertSame(Type::Either, $this->australian()->type);
	}

	#[Test]
	#[DataProvider('restrictions')]
	public function the_two_restrictions_narrow_rather_than_replace(array $calls, Type $expected): void
	{
		// Asking for both in either order lands on Both, rather than the second call undoing the
		// first. Which is the reason they are two withers and not one enum setter.
		$field = $this->australian();

		foreach ($calls as $call) {
			$field = $field->{$call}();
		}

		$this->assertSame($expected, $field->type);
	}

	/** @return array<string, array{list<string>, Type}> */
	public static function restrictions(): array
	{
		return [
			'neither' => [[], Type::Either],
			'mailable' => [['allowOnlyMailable'], Type::Postal],
			'physical' => [['allowOnlyPhysical'], Type::Physical],
			'mailable then physical' => [['allowOnlyMailable', 'allowOnlyPhysical'], Type::Both],
			'physical then mailable' => [['allowOnlyPhysical', 'allowOnlyMailable'], Type::Both],
			'mailable twice' => [['allowOnlyMailable', 'allowOnlyMailable'], Type::Postal],
		];
	}

	#[Test]
	public function a_po_box_is_an_address_until_somewhere_visitable_is_asked_for(): void
	{
		$poBox = ['line1' => 'PO Box 42'] + self::rockhampton(without: 'line1');

		$this->assertConstraintValidationResultSkipped('line1Visitable', $this->australian()->validate((object) $poBox));
		$this->assertConstraintValidationResultFailed(
			'line1Visitable',
			$this->australian()->allowOnlyPhysical()->validate((object) $poBox),
		);
	}

	#[Test]
	#[DataProvider('poBoxForms')]
	public function it_recognises_the_usual_po_box_forms(string $line1): void
	{
		$field = $this->australian()->allowOnlyPhysical();

		$this->assertConstraintValidationResultFailed(
			'line1Visitable',
			$field->validate((object) (['line1' => $line1] + self::rockhampton(without: 'line1'))),
		);
	}

	/** @return array<string, array{string}> */
	public static function poBoxForms(): array
	{
		return [
			'PO Box' => ['PO Box 42'],
			'P.O. Box' => ['P.O. Box 42'],
			'post office box' => ['Post Office Box 42'],
			'GPO Box' => ['GPO Box 42'],
			'locked bag' => ['Locked Bag 42'],
			'private bag' => ['Private Bag 42'],
			'rural route' => ['RR 3'],
		];
	}

	#[Test]
	public function a_street_that_merely_looks_like_a_box_is_not_one(): void
	{
		// The rural forms need a number so a street genuinely named this cannot trip them.
		$field = $this->australian()->allowOnlyPhysical();

		$this->assertConstraintValidationResultPassed(
			'line1Visitable',
			$field->validate((object) (['line1' => 'Rrunway Close'] + self::rockhampton(without: 'line1'))),
		);
	}

	#[Test]
	public function what_an_address_is_for_is_independent_of_how_much_of_it_is_required(): void
	{
		// A PO box is deliverable and not visitable, and a service area covering a suburb is
		// visitable and not deliverable. Conflating the two was the mistake.
		$poBox = ['line1' => 'PO Box 42'] + self::rockhampton(without: 'line1');

		$resolved = $this->australian()->allowOnlyPhysical()->validate((object) $poBox);

		$this->assertTrue($resolved->forConstraint('line1Visitable')->failed());
		$this->assertFalse($resolved->forConstraint('specific')->failed(), 'a PO box is still a street line');
	}

	#[Test]
	#[DataProvider('incoherentPairs')]
	public function a_mailable_address_that_needs_no_street_is_refused_where_it_is_declared(array $calls): void
	{
		// You cannot post to a suburb. Refused at definition time because no input could satisfy it,
		// so there would be nothing to report per request — and guarded on both withers, because
		// either call can be the second one.
		$this->expectException(InvalidArgumentException::class);

		$field = $this->australian();

		foreach ($calls as $call) {
			$field = $field->{$call}();
		}
	}

	/** @return array<string, array{list<string>}> */
	public static function incoherentPairs(): array
	{
		return [
			'no street, then mailable' => [['allowWithoutStreet', 'allowOnlyMailable']],
			'mailable, then no street' => [['allowOnlyMailable', 'allowWithoutStreet']],
			'both purposes, then no street' => [['allowOnlyPhysical', 'allowOnlyMailable', 'allowWithoutStreet']],
		];
	}

	#[Test]
	public function a_mailable_address_needs_no_ceremony_now_that_a_street_is_the_default(): void
	{
		$this->assertSame(Type::Postal, $this->australian()->allowOnlyMailable()->type);
	}

	#[Test]
	public function an_area_may_still_be_somewhere_you_can_go(): void
	{
		// Visitable and vague is coherent — a catchment you can drive into.
		$field = $this->australian()->allowOnlyPhysical()->allowWithoutStreet();

		$this->assertSame(Type::Physical, $field->type);
		$this->assertFalse($field->mustBeSpecific);
	}

	// ── countries ─────────────────────────────────────────────────────────────────────────

	#[Test]
	public function it_allows_any_country_by_default(): void
	{
		$this->assertSame([], $this->createField()->allowedCountries);
	}

	#[Test]
	public function a_country_is_accepted_in_any_case_and_stored_upper_cased(): void
	{
		$this->assertSame(['AU', 'NZ'], (new Address(new FieldName('a'), ['au']))->allowCountries('nz')->allowedCountries);
	}

	#[Test]
	public function an_allowed_country_may_be_written_out_in_full(): void
	{
		// So that an author can write what they mean. Stored as the code either way, since that is
		// what selects the per-country rules.
		$field = (new Address(new FieldName('a'), ['new zealand']))->allowCountries('Australia');

		$this->assertSame(['NZ', 'AU'], $field->allowedCountries);
	}

	#[Test]
	#[DataProvider('countriesSubmitted')]
	public function a_submitted_country_may_be_named_or_coded(string $given): void
	{
		// A form offering a country dropdown should not have to map the label back to a code before
		// submitting. Unambiguous to accept both: no two countries share a name, and no name
		// collides with a code.
		$resolved = $this->australian()->validate((object) (['country' => $given] + self::rockhampton(without: 'country')));

		$this->assertFalse($resolved->anyFailed(), "country '{$given}'");
		$this->assertSame('AU', $resolved->value->countryCode, 'canonicalised to the code');
	}

	/** @return array<string, array{string}> */
	public static function countriesSubmitted(): array
	{
		return [
			'the code' => ['AU'],
			'the code in lower case' => ['au'],
			'the name' => ['Australia'],
			'the name shouted' => ['AUSTRALIA'],
			'the name in lower case' => ['australia'],
		];
	}

	#[Test]
	public function a_country_with_stray_spacing_is_not_a_country(): void
	{
		// Case is a distinction ISO 3166 says is not one; surrounding whitespace is not a
		// distinction at all, it is damage — and repairing it is the port's job. So this reports
		// rather than being quietly fixed.
		$resolved = $this->australian()->validate((object) (['country' => '  Australia  '] + self::rockhampton(without: 'country')));

		$this->assertConstraintValidationResultFailed('allowedCountries', $resolved);
	}

	#[Test]
	public function a_country_that_is_neither_is_reported_as_it_was_written(): void
	{
		// Left exactly as it came: rewriting it would lose what was actually typed, and guessing at
		// a near-miss is not this field's business.
		$resolved = $this->australian()->validate((object) (['country' => 'Oz'] + self::rockhampton(without: 'country')));

		$this->assertConstraintValidationResultFailed('allowedCountries', $resolved);
		$this->assertSame('Oz', $resolved->value->countryCode);
	}

	#[Test]
	public function an_unknown_country_is_refused_where_it_is_written(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Address(new FieldName('a'), ['ZZ']);
	}

	#[Test]
	public function it_reports_a_country_that_is_not_allowed(): void
	{
		$outside = ['country' => 'NZ'] + self::rockhampton(without: 'country');

		$failed = $this->australian()->validate((object) $outside)->forConstraint('allowedCountries');

		$this->assertTrue($failed->failed());
		$this->assertSame('country', $failed->part);
		$this->assertSame(['AU'], $failed->bound);
	}

	#[Test]
	public function a_country_is_not_checked_when_any_is_allowed(): void
	{
		$this->assertConstraintValidationResultSkipped(
			'allowedCountries',
			$this->createField()->validate((object) self::rockhampton()),
		);
	}

	#[Test]
	public function an_address_without_a_country_never_described_a_place(): void
	{
		// The same pairing Money makes with a currency. It used to be filled in when the allow-list
		// happened to hold exactly one country — a rule that changed shape depending on how many
		// were listed, and that is gone.
		$resolved = $this->australian()->validate((object) self::rockhampton(without: 'country'));

		$this->assertShapeFailed($resolved);
		$this->assertConstraintValidationResultSkipped('allowedCountries', $resolved);
	}

	#[Test]
	public function an_empty_country_is_no_country(): void
	{
		$resolved = $this->australian()->validate((object) (['country' => ''] + self::rockhampton(without: 'country')));

		$this->assertShapeFailed($resolved);
	}

	#[Test]
	public function several_allowed_countries_leave_the_country_to_be_submitted(): void
	{
		$field = $this->australian()->allowCountries('NZ');

		$this->assertNull($field->validate((object) self::rockhampton(without: 'country'))->value->countryCode);
	}

	// ── postcodes ─────────────────────────────────────────────────────────────────────────

	#[Test]
	public function it_reports_a_postcode_the_country_does_not_use(): void
	{
		$failed = $this->australian()
			->validate((object) (['postal_code' => '99'] + self::rockhampton(without: 'postal_code')))
			->forConstraint('postalCodeFormat');

		$this->assertTrue($failed->failed());
		$this->assertSame('postal_code', $failed->part);
	}

	#[Test]
	public function an_unrestricted_field_checks_the_postcode_against_the_country_submitted(): void
	{
		// This used to be impossible. A free-form address got no postcode check at all, because
		// there was no country to derive a rule from — now there always is, and reading the one
		// the submitter wrote is not guessing.
		$this->assertConstraintValidationResultFailed(
			'postalCodeFormat',
			$this->createField()->validate((object) (['postal_code' => '99'] + self::rockhampton(without: 'postal_code'))),
		);
	}

	#[Test]
	public function a_postcode_failure_reports_the_pattern_that_applied(): void
	{
		// The pattern is per country, so it is only knowable once the address names one — which it
		// now always does. Otherwise a message could say a postcode was wrong without saying what
		// would have been right.
		$field = new Address(new FieldName('billing'), ['AU', 'NZ']);

		$failed = $field->validate((object)[
			'line1' => '1 Denham St',
			'locality' => 'Rockhampton',
			'postal_code' => '99',
			'country' => 'AU',
		])->forConstraint('postalCodeFormat');

		$this->assertTrue($failed->failed());
		$this->assertSame('\d{4}', $failed->bound);
	}

	#[Test]
	public function a_country_outside_the_allowed_list_reports_once_rather_than_twice(): void
	{
		// Deriving a postcode rule from a country that was already rejected would turn one mistake
		// into two failures.
		$resolved = $this->australian()
			->validate((object) ['country' => 'NZ', 'postal_code' => '99', 'locality' => 'Rockhampton', 'line1' => '1 Queen St']);

		$this->assertConstraintValidationResultFailed('allowedCountries', $resolved);
		$this->assertConstraintValidationResultSkipped('postalCodeFormat', $resolved);
	}

	#[Test]
	public function it_validates_against_whichever_allowed_country_was_submitted(): void
	{
		$field = $this->australian()->allowCountries('NZ');

		// 4700 is an Australian postcode; New Zealand's are four digits too, so use one that is not.
		$this->assertConstraintValidationResultPassed(
			'postalCodeFormat',
			$field->validate((object) self::rockhampton()),
		);
		$this->assertConstraintValidationResultFailed(
			'postalCodeFormat',
			$field->validate((object) ['country' => 'NZ', 'postal_code' => '470000', 'locality' => 'Auckland', 'line1' => '1 Queen St']),
		);
	}

	// ── subdivisions ──────────────────────────────────────────────────────────────────────

	#[Test]
	public function it_reports_a_subdivision_the_country_does_not_have(): void
	{
		$failed = $this->australian()
			->validate((object) (['administrative_area' => 'XX'] + self::rockhampton(without: 'administrative_area')))
			->forConstraint('administrativeArea');

		$this->assertTrue($failed->failed());
		$this->assertSame('administrative_area', $failed->part);
	}

	#[Test]
	public function it_reports_a_subdivision_belonging_to_a_different_allowed_country(): void
	{
		// CA is a US state and not an Australian one, so which country was submitted decides.
		$field = $this->australian()->allowCountries('US');

		$this->assertConstraintValidationResultFailed(
			'administrativeArea',
			$field->validate((object) ['country' => 'AU', 'administrative_area' => 'CA', 'postal_code' => '4700']),
		);
		$this->assertConstraintValidationResultPassed(
			'administrativeArea',
			$field->validate((object) ['country' => 'US', 'administrative_area' => 'CA', 'postal_code' => '90210']),
		);
	}

	#[Test]
	public function a_subdivision_needs_a_country_to_be_checked_against(): void
	{
		$this->assertConstraintValidationResultSkipped(
			'administrativeArea',
			$this->createField()->validate((object) ['administrative_area' => 'XX', 'locality' => 'Somewhere']),
		);
	}

	// ── the value object ──────────────────────────────────────────────────────────────────

	#[Test]
	public function a_part_is_kept_exactly_as_it_was_submitted(): void
	{
		// Neither trimmed nor collapsed to null. An absent part is null; a blank one is blank, and
		// the difference is information the field has no business discarding.
		$value = new Value((object) ['line1' => '  ', 'locality' => 'Rockhampton', 'postal_code' => ' 4700 ', 'country' => 'AU']);

		$this->assertSame('  ', $value->line1);
		$this->assertSame(' 4700 ', $value->postalCode);
		$this->assertNull($value->organization, 'absent is still null');
		$this->assertFalse($value->isEmpty(), 'a blank part is a part');
	}

	#[Test]
	public function a_part_is_addressed_by_the_name_submitted_data_uses(): void
	{
		// Which is the vocabulary a constraint's `part` reports in.
		$value = new Value((object) self::rockhampton());

		$this->assertSame('QLD', $value->partNamed('administrative_area'));
		$this->assertNull($value->partNamed('not_a_part'));
	}

	#[Test]
	public function it_round_trips_through_an_array(): void
	{
		$value = new Value((object) self::rockhampton());

		$this->assertSame(self::rockhampton(), array_filter($value->toArray(), static fn(?string $p): bool => $p !== null));
	}

	#[Test]
	public function an_address_that_names_no_country_describes_no_place(): void
	{
		// `4700` is Rockhampton in Australia and something else elsewhere, so the pairing is what
		// makes the rest mean anything — the same pairing money makes with a currency. It used to
		// be checked by the field; it is a fact about an address, so it is the value's now.
		$this->expectException(MalformedValue::class);

		Value::of(line1: '1 Denham St', locality: 'Rockhampton');
	}

	#[Test]
	public function an_address_with_nothing_in_it_is_absent_rather_than_vague(): void
	{
		// Stronger than it used to be. This asserted that an empty address *reported* itself
		// empty; now there is no empty address to ask, because the invariant moved into the
		// constructor along with the country pairing it belongs with.
		$this->assertFalse(Value::of(locality: 'Rockhampton', country: 'AU')->isEmpty());

		$this->expectException(MalformedValue::class);

		Value::of();
	}

	#[Test]
	public function it_is_read_part_by_part_rather_than_printed(): void
	{
		// No __toString(): the order and punctuation an address takes is per-country, so a single
		// line assembled here would be wrong in most of the world.
		$value = new Value((object) self::rockhampton());

		$this->assertNotInstanceOf(\Stringable::class, $value);
		$this->assertFalse(method_exists($value, '__toString'));
		$this->assertSame(self::rockhampton(), array_filter(
			$value->toArray(),
			static fn(?string $part): bool => $part !== null,
		));
	}

	#[Test]
	public function configuring_it_leaves_the_original_alone(): void
	{
		$field = $this->australian();
		$relaxed = $field->allowWithoutStreet()->allowCountries('NZ');

		$this->assertNotSame($field, $relaxed);
		$this->assertTrue($field->mustBeSpecific);
		$this->assertSame(['AU'], $field->allowedCountries);
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$this->assertNull($this->createField()->defaultValue);
	}
}
