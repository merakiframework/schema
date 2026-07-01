<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use Meraki\Schema\Rule\FieldBuilder;
use Meraki\Schema\Rule\Condition\NotEquals;
use Meraki\Schema\Rule\Outcome\Ignore;
use Meraki\Schema\Scope;
use Meraki\Schema\Property\Name;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('rule')]
#[CoversClass(Field::class)]
#[CoversClass(Facade::class)]
#[CoversClass(FieldBuilder::class)]
#[CoversClass(NotEquals::class)]
#[CoversClass(Ignore::class)]
#[CoversClass(Scope::class)]
final class PairWithTest extends TestCase
{
	#[Test]
	public function scopes_resolve_camel_case_field_names_verbatim(): void
	{
		// Field names are matched exactly — camelCase names must be targetable.
		$schema = new Facade('contact');
		$schema->addEnumField('contactMethod', ['email', 'phone'])
			->pairWith(
				new Field\EmailAddress(new Name('emailAddress')),
				function (FieldBuilder $rule, Field\EmailAddress $email): void {
					$rule->when($this)->notEquals('email')->thenMakeOptional($email)->thenIgnore($email);
				}
			);

		// phone chosen → camelCase emailAddress is ignored (a bad value does not fail)
		$this->assertFalse($schema->validate(['contactMethod' => 'phone', 'emailAddress' => 'bad'])->anyFailed());
		// email chosen → it is required again
		$this->assertTrue($schema->validate(['contactMethod' => 'email'])->anyFailed());
	}

	private function contactSchema(): Facade
	{
		$schema = new Facade('contact');

		$schema->addEnumField('contact_method', ['email', 'phone'])
			->pairWith(
				new Field\EmailAddress(new Name('email_address')),
				function (FieldBuilder $rule, Field\EmailAddress $email): void {
					// $this === the contact_method enum field.
					$rule->when($this)->notEquals('email')->thenMakeOptional($email)->thenIgnore($email);
				}
			)
			->pairWith(
				new Field\PhoneNumber(new Name('phone_number')),
				function (FieldBuilder $rule, Field\PhoneNumber $phone): void {
					$rule->when($this)->notEquals('phone')->thenMakeOptional($phone)->thenIgnore($phone);
				}
			);

		return $schema;
	}

	#[Test]
	public function it_auto_adds_the_paired_fields(): void
	{
		$schema = $this->contactSchema();

		$this->assertNotNull($schema->fields->findByName('email_address'));
		$this->assertNotNull($schema->fields->findByName('phone_number'));
	}

	#[Test]
	public function choosing_email_keeps_email_required_and_ignores_phone(): void
	{
		$result = $this->contactSchema()->validate([
			'contact_method' => 'email',
			'email_address' => 'alice@example.com',
			// phone_number omitted — must be optional + ignored
		]);

		$this->assertFalse($result->anyFailed());
	}

	#[Test]
	public function choosing_phone_ignores_a_submitted_email_so_it_does_not_fail(): void
	{
		$result = $this->contactSchema()->validate([
			'contact_method' => 'phone',
			'phone_number' => '+12015550123',
			'email_address' => 'not-an-email', // would normally fail, but it's ignored
		]);

		$this->assertFalse($result->anyFailed());
	}

	#[Test]
	public function the_chosen_branch_is_still_required(): void
	{
		$result = $this->contactSchema()->validate([
			'contact_method' => 'email', // but no email supplied
		]);

		$this->assertTrue($result->anyFailed());
	}

	#[Test]
	public function pairwith_supports_compound_and_conditions(): void
	{
		$schema = new Facade('booking');

		$whoManages = (new Field\Enum(new Name('who_manages'), ['organiser', 'participant']))->makeOptional();
		$whoFor = $schema->addEnumField('who_for', ['myself', 'someone_else']);
		$whoFor->pairWith($whoManages, function (FieldBuilder $rule, Field\Enum $wm): void {
			$rule->when($this)->notEquals('someone_else')->thenMakeOptional($wm)->thenIgnore($wm);
		});

		$email = (new Field\EmailAddress(new Name('participant_email')))->makeOptional();
		$whoFor->pairWith($email, function (FieldBuilder $rule, Field\EmailAddress $e) use ($whoManages): void {
			// required only when booking for someone else AND they manage their own lessons
			$rule->when($this)->equals('someone_else')->andWhen($whoManages)->equals('participant')->thenRequire($e);
		});

		// someone else + participant + no email -> fails
		$this->assertTrue($schema->validate(['who_for' => 'someone_else', 'who_manages' => 'participant'])->anyFailed());
		// someone else + organiser -> email not required -> passes
		$this->assertFalse($schema->validate(['who_for' => 'someone_else', 'who_manages' => 'organiser'])->anyFailed());
		// myself -> passes
		$this->assertFalse($schema->validate(['who_for' => 'myself'])->anyFailed());
	}

	#[Test]
	public function pairing_with_a_duplicate_name_throws(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		$schema = new Facade('contact');
		$schema->addEmailAddressField('email_address');
		$schema->addEnumField('contact_method', ['email', 'phone'])
			->pairWith(new Field\EmailAddress(new Name('email_address')), function (FieldBuilder $rule, Field\EmailAddress $f): void {});
	}

	#[Test]
	public function pairing_before_being_added_to_a_schema_throws(): void
	{
		$this->expectException(\LogicException::class);

		$enum = new Field\Enum(new Name('contact_method'), ['email', 'phone']);
		$enum->pairWith(new Field\EmailAddress(new Name('email_address')), function (FieldBuilder $rule, Field\EmailAddress $f): void {});
	}
}
