<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Definition;
use Meraki\Schema\Exception\UnknownField;
use Meraki\Schema\Field;
use Meraki\Schema\ResolvedField;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A field finds its own result, so reading one never starts with a string.
 *
 * `forField('billing')` can only promise the base result type, because PHP cannot narrow a return by
 * the value of an argument, and a misspelled name answers `null` exactly as a field that is not there
 * would. The field object has neither problem: there is no name to misspell, and a field with a
 * result of its own narrows the return type — the same move `$field->when()` makes for rules.
 */
#[CoversNothing]
#[Group('api-2.0')]
final class ResultInTest extends TestCase
{
	#[Test]
	public function a_field_finds_its_own_result_without_a_name(): void
	{
		$schema = new Definition('signup');
		$schema->add($username = $schema->createTextField('username')->minLengthOf(3));

		$result = $username->resultIn($schema->validate((object) ['username' => 'ab']));

		$this->assertSame('username', (string) $result->field->name);
		$this->assertSame(Field\Text\Check::MinLength, $result->violations->first()?->code);
	}

	#[Test]
	public function a_field_configured_after_it_was_added_still_finds_its_result(): void
	{
		// Fields are immutable, so the variable an author holds is often a configured copy of the
		// one the schema holds. Looking up by the field's own name is what makes either work.
		$schema = new Definition('signup');
		$username = $schema->createTextField('username');
		$schema->add($username->minLengthOf(3));

		$this->assertTrue($username->resultIn($schema->validate((object) ['username' => 'ab']))->anyFailed());
	}

	#[Test]
	public function a_field_from_another_schema_has_no_result_here(): void
	{
		$schema = new Definition('signup');
		$schema->add($schema->createTextField('username'));
		$stranger = (new Definition('other'))->createTextField('nickname');

		$this->expectException(UnknownField::class);
		$this->expectExceptionMessage('There is no result for "nickname" here.');

		$stranger->resultIn($schema->validate((object) ['username' => 'kim']));
	}

	#[Test]
	public function a_field_with_a_result_of_its_own_hands_back_that_kind(): void
	{
		$schema = new Definition('signup');
		$schema->add($secret = $schema->createPasswordField('secret'));

		// Typed by PHP, not by a docblock: the entropy is one hop away.
		$this->assertIsInt($secret->resultIn($schema->validate((object) ['secret' => 'correct horse battery staple']))->entropy);
	}

	#[Test]
	public function a_field_with_a_result_of_its_own_refuses_a_stranger_under_its_name(): void
	{
		// Another schema's `secret` that is not a password: handing its result back would be
		// answering for a stranger.
		$other = new Definition('other');
		$other->add($other->createTextField('secret'));
		$secret = (new Definition('signup'))->createPasswordField('secret');

		$this->expectException(UnknownField::class);

		$secret->resultIn($other->validate((object) ['secret' => 'x']));
	}

	#[Test]
	public function a_collection_hands_back_its_rows_and_a_template_field_finds_itself_in_one(): void
	{
		$schema = new Definition('order');
		$sku = $schema->createTextField('sku')->minLengthOf(3);
		$schema->add($lines = $schema->createCollectionField('lines', $sku));

		$result = $lines->resultIn($schema->validate((object) ['lines' => ['first' => (object) ['sku' => 'ab']]]));
		$row = $result->itemAt('first');

		$this->assertNotNull($row);
		$this->assertInstanceOf(ResolvedField::class, $sku->resultIn($row));
		$this->assertTrue($sku->resultIn($row)->anyFailed());
	}
}
