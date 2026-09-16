<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Facade;
use Meraki\Schema\Rule;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Draft;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Rule\Outcome\Ignore;
use Meraki\Schema\Rule\Outcome\MakeOptional;
use Meraki\Schema\Rule\Outcome\MakeRequired;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * One field's value deciding whether another is required — the thing rules are actually for.
 *
 * These scenarios came from `PairWithTest`, which exercised them through `Field::pairWith()`:
 * a field method that checked the schema for a duplicate name, added the paired field to it,
 * and registered rules — every one of them a schema operation wearing a field's clothes. It
 * needed a back-reference from field to schema to do any of it, and that back-reference was
 * defect B8.
 *
 * The scenarios were worth keeping and the mechanism was not, so they are written against the
 * matcher vocabulary instead. Two of the original tests are not here: one asserted that pairing
 * under a name already taken throws, which is `Field\SetTest`'s to make and it still does; the
 * other asserted that pairing before the field was added throws, which was a fact about
 * `pairWith()`'s own precondition and has nothing left to be true of.
 */
#[Group('rule')]
#[CoversClass(Facade::class)]
#[CoversClass(Draft::class)]
#[CoversClass(Matcher::class)]
#[CoversClass(Rule::class)]
#[CoversClass(Condition\Equals::class)]
#[CoversClass(Condition\NotEquals::class)]
#[CoversClass(Ignore::class)]
#[CoversClass(MakeOptional::class)]
#[CoversClass(MakeRequired::class)]
final class ConditionalFieldsTest extends TestCase
{


	// ── one condition, two branches ───────────────────────────────────────────────────────

	/**
	 * A contact form where picking a method decides which detail is wanted.
	 *
	 * Each unchosen branch is made optional *and* ignored: optional alone would still fail on
	 * a stale value the submitter left behind in a field the form stopped showing.
	 */
	private function contactSchema(): Facade
	{
		$schema = new Facade('contact');

		$method = $schema->createEnumField('contact_method', ['email', 'phone']);
		$email = $schema->createEmailAddressField('email_address');
		$phone = $schema->createPhoneNumberField('phone_number');

		$schema->add($method, $email, $phone);

		$schema->addRules(
			$schema->when($method)->notEquals('email')->thenMakeOptional($email)->thenIgnore($email),
			$schema->when($method)->notEquals('phone')->thenMakeOptional($phone)->thenIgnore($phone),
		);

		return $schema;
	}

	#[Test]
	public function choosing_email_keeps_email_required_and_ignores_phone(): void
	{
		$result = $this->contactSchema()->validate((object)[
			'contact_method' => 'email',
			'email_address' => 'alice@example.com',
			// phone_number omitted — the rule must have made it optional
		]);

		$this->assertFalse($result->anyFailed());
	}

	#[Test]
	public function choosing_phone_ignores_a_submitted_email_so_it_does_not_fail(): void
	{
		$result = $this->contactSchema()->validate((object)[
			'contact_method' => 'phone',
			'phone_number' => (object)['number' => '0411 222 333', 'country' => 'AU'],
			'email_address' => 'not-an-email',	// would fail on its own; it is ignored
		]);

		$this->assertFalse($result->anyFailed());
	}

	#[Test]
	public function the_chosen_branch_is_still_required(): void
	{
		$result = $this->contactSchema()->validate((object)['contact_method' => 'email']);

		$this->assertTrue($result->anyFailed());
	}

	#[Test]
	public function a_camel_case_field_name_is_targeted_verbatim(): void
	{
		// Names are matched exactly, with no case conversion anywhere along the path.
		$schema = new Facade('contact');
		$method = $schema->createEnumField('contactMethod', ['email', 'phone']);
		$email = $schema->createEmailAddressField('emailAddress');

		$schema->add($method, $email);
		$schema->addRule(
			$schema->when($method)->notEquals('email')->thenMakeOptional($email)->thenIgnore($email),
		);

		$this->assertFalse($schema->validate((object)['contactMethod' => 'phone', 'emailAddress' => 'bad'])->anyFailed());
		$this->assertTrue($schema->validate((object)['contactMethod' => 'email'])->anyFailed());
	}

	// ── conditions combined ───────────────────────────────────────────────────────────────

	#[Test]
	public function every_condition_must_hold_for_an_all_of_rule(): void
	{
		$schema = new Facade('booking');

		$whoFor = $schema->createEnumField('who_for', ['myself', 'someone_else']);
		$whoManages = $schema->createEnumField('who_manages', ['organiser', 'participant'])->makeOptional();
		$email = $schema->createEmailAddressField('participant_email')->makeOptional();

		$schema->add($whoFor, $whoManages, $email);

		// An email is wanted only when booking for someone else *and* that person manages
		// their own lessons.
		$schema->addRule(
			$schema->allOf(
				$schema->when($whoFor)->equals('someone_else'),
				$schema->when($whoManages)->equals('participant'),
			)->thenRequire($email),
		);

		$this->assertTrue($schema->validate((object)['who_for' => 'someone_else', 'who_manages' => 'participant'])->anyFailed());
		$this->assertFalse($schema->validate((object)['who_for' => 'someone_else', 'who_manages' => 'organiser'])->anyFailed());
		$this->assertFalse($schema->validate((object)['who_for' => 'myself'])->anyFailed());
	}

	#[Test]
	public function any_condition_holding_is_enough_for_an_any_of_rule(): void
	{
		$schema = new Facade('booking');

		$staff = $schema->createBooleanField('is_staff')->makeOptional();
		$member = $schema->createBooleanField('is_member')->makeOptional();
		$number = $schema->createTextField('membership_number')->makeOptional();

		$schema->add($staff, $member, $number);

		$schema->addRule(
			$schema->anyOf(
				$schema->when($staff)->equals(true),
				$schema->when($member)->equals(true),
			)->thenRequire($number),
		);

		$this->assertTrue($schema->validate((object)['is_staff' => true])->anyFailed());
		$this->assertTrue($schema->validate((object)['is_member' => true])->anyFailed());
		$this->assertFalse($schema->validate((object)['is_staff' => false, 'is_member' => false])->anyFailed());
	}

	#[Test]
	public function combining_rules_that_already_carry_outcomes_is_refused(): void
	{
		// There would be no answer to which set of outcomes fires, so it is refused where it
		// is written rather than resolved by some rule nobody would guess.
		$schema = new Facade('booking');
		$a = $schema->createBooleanField('a');
		$b = $schema->createBooleanField('b');

		$schema->add($a, $b);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/combine conditions, not finished rules/');

		$schema->allOf(
			$schema->when($a)->equals(true)->thenRequire($b),
			$schema->when($b)->equals(true),
		);
	}

	// ── the else-branch ───────────────────────────────────────────────────────────────────

	#[Test]
	public function the_else_branch_fires_when_the_condition_does_not_hold(): void
	{
		// Written as one rule rather than two with hand-inverted conditions, which is the
		// whole point: there is one condition here, so the branches cannot drift apart.
		$schema = new Facade('lesson');

		$hasLogBook = $schema->createBooleanField('has_log_book');
		$completed = $schema->createTimeField('log_book_time_completed')->makeOptional();

		$schema->add($hasLogBook, $completed);
		$schema->addRule(
			$schema->when($hasLogBook)->equals(true)
				->thenRequire($completed)
				->elseMakeOptional($completed),
		);

		$this->assertTrue($schema->validate((object)['has_log_book' => true])->anyFailed());
		$this->assertFalse($schema->validate((object)['has_log_book' => false])->anyFailed());
	}

	#[Test]
	public function an_else_branch_outcome_is_reported_as_applied(): void
	{
		// A consumer asking why a field is optional needs the answer whichever branch gave it,
		// so an else-branch outcome is still something the rule did.
		$schema = new Facade('lesson');

		$hasLogBook = $schema->createBooleanField('has_log_book');
		$completed = $schema->createTimeField('log_book_time_completed');

		$schema->add($hasLogBook, $completed);
		$schema->addRule(
			$schema->when($hasLogBook)->equals(true)
				->thenRequire($completed)
				->elseMakeOptional($completed),
		);

		$applied = $schema->validate((object)['has_log_book' => false])
			->forField('log_book_time_completed')
			->appliedOutcomes;

		$this->assertCount(1, $applied);
		$this->assertTrue($applied[0]->is(MakeOptional::class));
		$this->assertFalse($applied[0]->conditionMatched, 'It came from the else-branch.');
	}

	// ── a rule is a value ─────────────────────────────────────────────────────────────────

	#[Test]
	public function a_rule_can_be_built_before_it_is_added(): void
	{
		// What the closure-configurator form made impossible: a rule that exists as a value,
		// so it can be held, passed around, or built somewhere else entirely.
		$schema = new Facade('signup');
		$username = $schema->createTextField('username');
		$nickname = $schema->createTextField('nickname')->makeOptional();

		$schema->add($username, $nickname);

		$rule = $schema->when($username)->equals('admin')->thenRequire($nickname);

		$this->assertInstanceOf(Draft::class, $rule);
		$this->assertCount(0, $schema->rules, 'Building it must not add it.');

		$schema->addRule($rule);

		$this->assertCount(1, $schema->rules);
	}

	#[Test]
	public function a_rule_that_says_nothing_should_happen_is_refused(): void
	{
		$schema = new Facade('signup');
		$schema->add($schema->createTextField('username'));

		$this->expectException(LogicException::class);
		$this->expectExceptionMessageMatches('/must say what happens/');

		$schema->addRule($schema->when('username')->equals('admin'));
	}

	#[Test]
	public function an_outcome_in_the_else_branch_is_checked_against_the_schema_too(): void
	{
		// The then-branch was always checked. An else-branch naming a field that does not
		// exist has to fail in the same place, or the check has a hole in it.
		$schema = new Facade('signup');
		$schema->add($schema->createTextField('username'));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/nickname/');

		$schema->addRule(
			$schema->when('username')->equals('admin')->elseRequire('nickname'),
		);
	}
}
