<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Password;
use Meraki\Schema\Field\Password\Strength;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldTestCase;
use Meraki\Schema\ResolvedField;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(Password::class)]
#[CoversClass(Strength::class)]
#[CoversClass(Password\Result::class)]
final class PasswordTest extends FieldTestCase
{
	public function createField(): Password
	{
		return new Password(new FieldName('password'));
	}

	// ── baselines ──────────────────────────────────────────────────────────────────────────

	#[Test]
	public function it_requires_eight_characters_out_of_the_box(): void
	{
		$field = $this->createField();

		$this->assertSame(8, $field->minLength);
		$this->assertConstraintValidationResultFailed('minLength', $field->validate('shrt'));
		$this->assertConstraintValidationResultPassed('minLength', $field->validate('longenough'));
	}

	#[Test]
	public function hashing_is_not_this_fields_concern(): void
	{
		// bcrypt's 72-byte truncation is real, and it is the hashing layer's to handle — by
		// pre-hashing, or by using Argon2id, which has no ceiling worth stating. Expressing it as
		// a validation rule put it in the wrong layer and asked the author to tell the field
		// something the field could not otherwise know.
		$field = $this->createField();

		$this->assertFalse(property_exists($field, 'maxBytes'));
		$this->assertFalse(method_exists($field, 'maxBytesOf'));
		$this->assertNotContains('maxBytes', $field->constraints->names);
		$this->assertConstraintValidationResultPassed('minLength', $field->validate(str_repeat('a', 200)));
	}

	#[Test]
	public function composition_rules_are_off_by_default(): void
	{
		// Current guidance discourages them, so they are available rather than imposed.
		$result = $this->createField()->validate('all lowercase letters');

		foreach ([
			'minUppercaseChars', 'minLowercaseChars', 'minDigits', 'minSymbols',
			'maxLength', 'minStrength',
		] as $constraint) {
			$this->assertConstraintValidationResultSkipped($constraint, $result);
		}
	}

	// ── length, in characters ──────────────────────────────────────────────────────────────

	#[Test]
	#[DataProvider('lengthsByScript')]
	public function a_limit_means_the_same_thing_whatever_the_script(string $value, string $expected): void
	{
		// NIST SP 800-63B asks for exactly this: each Unicode code point counts as one character.
		// A byte-denominated limit would admit 64 Latin characters but only 21 CJK ones, so the
		// same written policy would mean different things for different users.
		$field = $this->createField()->maxLengthOf(64);

		$this->assertConstraintValidationResultHasStatusOf(
			constant('Meraki\Schema\ValidationStatus::' . $expected),
			'maxLength',
			$field->validate($value),
		);
	}

	/** @return array<string, array{string, string}> */
	public static function lengthsByScript(): array
	{
		return [
			'64 ASCII characters' => [str_repeat('a', 64), 'Passed'],
			'64 CJK characters, though they are 192 bytes' => [str_repeat('漢', 64), 'Passed'],
			'64 emoji, though they are 256 bytes' => [str_repeat('😀', 64), 'Passed'],
			'80 ASCII characters' => [str_repeat('a', 80), 'Failed'],
			'80 CJK characters' => [str_repeat('漢', 80), 'Failed'],
		];
	}

	#[Test]
	public function lengths_are_counted_in_code_points(): void
	{
		// mb_strlen, consistently with every other field.
		$field = $this->createField()->minLengthOf(10)->maxLengthOf(10);

		$this->assertConstraintValidationResultPassed('minLength', $field->validate(str_repeat('漢', 10)));
		$this->assertConstraintValidationResultPassed('maxLength', $field->validate(str_repeat('漢', 10)));
	}

	// ── strength ───────────────────────────────────────────────────────────────────────────

	#[Test]
	public function a_strength_floor_accepts_anything_stronger(): void
	{
		$field = $this->createField()->minStrengthOf(Strength::Moderate);

		$this->assertConstraintValidationResultPassed('minStrength', $field->validate('correct horse battery staple'));
	}

	#[Test]
	public function a_strength_floor_rejects_what_falls_short(): void
	{
		$field = $this->createField()->minStrengthOf(Strength::Strong);

		$this->assertConstraintValidationResultFailed('minStrength', $field->validate('password'));
	}

	#[Test]
	public function a_long_passphrase_beats_a_short_mangled_word(): void
	{
		// The point of absorbing Passphrase: length buys more than substitution does. Read off the
		// result rather than by calling the estimator, because the measurement of *this request's*
		// input is what a consumer actually has.
		$field = $this->createField();

		$this->assertGreaterThan(
			$field->validate('Tr0ub4dor&3')->entropy,
			$field->validate('correct horse battery staple')->entropy,
		);
	}

	#[Test]
	public function nothing_submitted_means_nothing_measured(): void
	{
		$field = $this->createField();

		$this->assertNull($field->validate(null)->entropy);
		$this->assertNull($field->resolve(null)->entropy, 'nor when a form is merely being rendered');
	}

	#[Test]
	public function the_measurement_survives_a_failed_verdict(): void
	{
		// A secret that failed maxLength still has a strength worth showing, and a form redrawing
		// the field wants its meter either way. A skipped constraint carries no measurement, so
		// reading it off the constraint result could not work.
		$resolved = $this->createField()->maxLengthOf(10)->validate('correct horse battery staple');

		$this->assertTrue($resolved->anyFailed());
		$this->assertGreaterThan(0, $resolved->entropy);
	}

	#[Test]
	public function the_measurement_is_taken_once_and_only_when_asked(): void
	{
		// zxcvbn's dictionaries cost ~18ms and ~10MB, so a field with no strength requirement must
		// not pay for them merely by being validated.
		$resolved = $this->createField()->validate('correct horse battery staple');

		$this->assertSame($resolved->entropy, $resolved->entropy);
	}

	#[Test]
	public function it_is_still_a_resolved_field_in_every_other_respect(): void
	{
		$resolved = $this->createField()->minStrengthOf(Strength::Strong)->validate('correct horse battery staple');

		$this->assertInstanceOf(ResolvedField::class, $resolved);
		$this->assertSame('correct horse battery staple', $resolved->value->secret);
		$this->assertConstraintValidationResultPassed('minStrength', $resolved);
	}

	#[Test]
	public function the_tiers_are_ordered(): void
	{
		$this->assertTrue(Strength::Cryptographic->meets(Strength::Strong));
		$this->assertTrue(Strength::Strong->meets(Strength::Strong));
		$this->assertFalse(Strength::Weak->meets(Strength::Strong));
	}

	#[Test]
	public function there_is_no_tier_meaning_no_requirement(): void
	{
		// A field with no strength floor says so by not asking, like every other constraint.
		$values = array_map(static fn(Strength $s): string => $s->value, Strength::cases());

		$this->assertNotContains('none', $values);
		$this->assertNotContains('common', $values);
	}

	#[Test]
	#[DataProvider('longButGuessable')]
	public function length_alone_does_not_make_a_secret_strong(string $secret): void
	{
		// The naive `log2(alphabet) × length` estimator this replaced scored forty of the same
		// letter at 188 bits — key-equivalent — because it counted characters present and never
		// looked at the arrangement. Each of these is long and trivially guessable.
		$field = $this->createField()->minStrengthOf(Strength::Weak);

		$this->assertConstraintValidationResultFailed('minStrength', $field->validate($secret));
	}

	/** @return array<string, array{string}> */
	public static function longButGuessable(): array
	{
		return [
			'one character, forty times' => [str_repeat('x', 40)],
			'one character, twenty times' => [str_repeat('a', 20)],
			'the alphabet in order' => ['abcdefghijklmnopqrst'],
			'a word with the usual substitutions' => ['P@ssw0rd123'],
			'a keyboard walk' => ['qwertyuiop'],
		];
	}

	#[Test]
	public function a_secret_that_looks_strong_but_is_not_falls_below_strong(): void
	{
		// The canonical example, and the reason the tiers were recalibrated to zxcvbn's scale
		// rather than carried over: 37 bits of guess-resistance against a 40-bit floor.
		$field = $this->createField()->minStrengthOf(Strength::Strong);

		$this->assertConstraintValidationResultFailed('minStrength', $field->validate('Tr0ub4dor&3'));
		$this->assertConstraintValidationResultPassed('minStrength', $field->validate('J#9vK@2mQ!7xZ&4p'));
	}

	// ── composition ────────────────────────────────────────────────────────────────────────

	#[Test]
	#[DataProvider('compositionRules')]
	public function each_composition_bound_reports_under_its_own_name(
		string $method,
		int $count,
		string $constraint,
		string $passing,
		string $failing,
	): void {
		// The old Range shape put both ends behind one constraint name, so a failure could not
		// say whether the floor or the ceiling was missed.
		$field = $this->createField()->{$method}($count);

		$this->assertConstraintValidationResultPassed($constraint, $field->validate($passing));
		$this->assertConstraintValidationResultFailed($constraint, $field->validate($failing));
	}

	/** @return array<string, array{string, int, string, string, string}> */
	public static function compositionRules(): array
	{
		return [
			'minimum uppercase' => ['minNumberOfUppercaseChars', 2, 'minUppercaseChars', 'ABcdefgh', 'Abcdefgh'],
			'minimum lowercase' => ['minNumberOfLowercaseChars', 2, 'minLowercaseChars', 'ABCDEFgh', 'ABCDEFGh'],
			'minimum digits' => ['minNumberOfDigits', 2, 'minDigits', 'abcdef12', 'abcdefg1'],
			'minimum symbols' => ['minNumberOfSymbols', 2, 'minSymbols', 'abcdef!?', 'abcdefg!'],
		];
	}

	#[Test]
	public function there_are_no_per_class_maximums(): void
	{
		// A minimum describes a policy that exists in the world. A maximum shrinks the search space
		// an attacker must cover and tells them something about its shape — "at most two digits" is
		// a gift — so it is not offered at all.
		$field = $this->createField();

		foreach (['Uppercase', 'Lowercase'] as $class) {
			$this->assertFalse(method_exists($field, "maxNumberOf{$class}Chars"), $class);
		}

		$this->assertFalse(method_exists($field, 'maxNumberOfDigits'));
		$this->assertFalse(method_exists($field, 'maxNumberOfSymbols'));

		foreach (['maxUppercaseChars', 'maxLowercaseChars', 'maxDigits', 'maxSymbols'] as $name) {
			$this->assertNotContains($name, $field->constraints->names, $name);
		}
	}

	#[Test]
	#[DataProvider('impossibleCombinations')]
	public function a_policy_that_cannot_be_satisfied_is_refused_where_it_is_written(callable $attempt): void
	{
		// Raising where the author wrote it beats reporting it against somebody's request.
		$this->expectException(InvalidArgumentException::class);

		$attempt($this->createField());
	}

	/** @return array<string, array{callable}> */
	public static function impossibleCombinations(): array
	{
		return [
			'a maximum below the minimum length' => [fn(Password $p): Password => $p->maxLengthOf(4)],
			'a negative count' => [fn(Password $p): Password => $p->minNumberOfDigits(-1)],
			'a zero minimum length' => [fn(Password $p): Password => $p->minLengthOf(0)],
			'a minimum below the baseline' => [fn(Password $p): Password => $p->minLengthOf(4)],
			// The classes are disjoint, so ten of one and ten of another really does need twenty
			// characters — and every order reaches the same contradiction.
			'more required characters than the maximum length can hold' => [
				fn(Password $p): Password => $p->maxLengthOf(15)->minNumberOfUppercaseChars(10)->minNumberOfDigits(10),
			],
			'...with the maximum written last' => [
				fn(Password $p): Password => $p->minNumberOfUppercaseChars(10)->minNumberOfDigits(10)->maxLengthOf(15),
			],
			'...with the maximum written in the middle' => [
				fn(Password $p): Password => $p->minNumberOfDigits(10)->maxLengthOf(15)->minNumberOfUppercaseChars(10),
			],
		];
	}

	#[Test]
	public function composition_minimums_that_do_fit_are_accepted(): void
	{
		$field = $this->createField()->maxLengthOf(20)->minNumberOfUppercaseChars(10)->minNumberOfDigits(10);

		$this->assertSame(10, $field->minUppercaseChars);
		$this->assertSame(10, $field->minDigits);
	}

	#[Test]
	public function lowering_a_minimum_makes_room_again(): void
	{
		// The sum is recomputed rather than accumulated, so replacing a count releases what it held.
		$field = $this->createField()
			->maxLengthOf(15)
			->minNumberOfDigits(10)
			->minNumberOfDigits(2)
			->minNumberOfUppercaseChars(10);

		$this->assertSame(2, $field->minDigits);
		$this->assertSame(10, $field->minUppercaseChars);
	}

	#[Test]
	public function nothing_checks_the_minimums_against_the_minimum_length(): void
	{
		// The mirrored rule would be unsound. The four classes are disjoint but *not* exhaustive:
		// CJK, Hebrew and Thai characters match none of them, because \p{Lu}/\p{Ll} miss the other
		// letter categories. A twelve-character CJK secret counts zero in every class while being
		// twelve characters long.
		$field = $this->createField()->minLengthOf(12);

		$this->assertConstraintValidationResultPassed('minLength', $field->validate(str_repeat('漢', 12)));
		$this->assertConstraintValidationResultSkipped('minUppercaseChars', $field->validate(str_repeat('漢', 12)));
	}

	#[Test]
	public function validating_twice_leaves_the_field_untouched(): void
	{
		// C4 was a private bool the constraints mutated as a side effect, so the verdict
		// depended on evaluation order and survived the request.
		$field = $this->createField()->minNumberOfDigits(2)->minNumberOfSymbols(1);

		$first = $field->validate('abc');
		$second = $field->validate('abc');

		$this->assertSame($first->status, $second->status);
		$this->assertSame(2, $field->minDigits);
		$this->assertSame(1, $field->minSymbols);
	}
}
