<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\EmailAddress;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(EmailAddress::class)]
final class EmailAddressTest extends FieldTestCase
{
	public function createField(): EmailAddress
	{
		return new EmailAddress(new FieldName('email_address'));
	}

	// ── shape: the WHATWG grammar ──────────────────────────────────────────────────────────

	#[Test]
	#[DataProvider('wellFormed')]
	public function it_accepts_what_the_whatwg_grammar_accepts(string $address): void
	{
		$this->assertShapePassed($this->createField()->validate($address));
	}

	/** @return array<string, array{string}> */
	public static function wellFormed(): array
	{
		return [
			'the shortest possible address' => ['a@b'],
			'no top-level domain' => ['user@domain'],
			'subdomains' => ['user@mail.example.test'],
			'what looks like an IPv4 address' => ['user@192.168.0.1'],
			'the punctuation the grammar allows' => ["a!#$%&'*+-/=?^_`{|}~@example.test"],
			// Not an oversight: the grammar places no restriction on dots in the local part, and
			// diverging would mean disagreeing with what the browser already submitted.
			'consecutive dots in the local part' => ['jane..doe@example.test'],
			'a leading dot in the local part' => ['.jane@example.test'],
			'a local part at exactly 64 octets' => [str_repeat('a', 64) . '@b.co'],
		];
	}

	#[Test]
	#[DataProvider('malformed')]
	public function it_rejects_what_the_grammar_does_not_describe(string $address): void
	{
		$this->assertShapeFailed($this->createField()->validate($address));
	}

	/** @return array<string, array{string}> */
	public static function malformed(): array
	{
		return [
			'nothing at all' => [''],
			'no at sign' => ['userdomain'],
			'no local part' => ['@domain'],
			'no domain' => ['user@'],
			'two at signs' => ['user@domain@domain'],
			'a space' => ['jane doe@example.test'],
			// The RFC 5322 features the HTML specification deliberately leaves out.
			'a quoted local part' => ['"user"@domain'],
			'an IPv6 literal' => ['user@[::1]'],
			// RFC 5321 caps the local part at 64 octets, which the grammar cannot express.
			'a local part at 65 octets' => [str_repeat('a', 65) . '@b.co'],
		];
	}

	#[Test]
	public function a_comma_separated_list_is_not_an_address(): void
	{
		// A field holds one address; several is a Collection of them. Kept because it asserts what
		// the field *does* with such input, not merely that some older method is gone.
		$result = $this->createField()->validate('a@example.test, b@example.test');

		$this->assertShapeFailed($result);
	}

	// ── length ─────────────────────────────────────────────────────────────────────────────

	#[Test]
	public function its_bounds_default_to_what_the_standard_allows(): void
	{
		$field = $this->createField();

		$this->assertSame(3, $field->minLength, 'a@b is the shortest well-formed address');
		$this->assertSame(254, $field->maxLength, 'RFC 5321 leaves 254 octets for the address');
		$this->assertSame(EmailAddress::SHORTEST, $field->minLength);
		$this->assertSame(EmailAddress::LONGEST, $field->maxLength);
	}

	#[Test]
	public function it_reports_an_address_shorter_than_the_minimum(): void
	{
		$field = $this->createField()->minLengthOf(20);

		$this->assertConstraintValidationResultFailed('minLength', $field->validate('a@b.co'));
		$this->assertConstraintValidationResultPassed('minLength', $field->validate('jane.doe@example.test'));
	}

	#[Test]
	public function it_reports_an_address_longer_than_the_maximum(): void
	{
		$field = $this->createField()->maxLengthOf(20);

		$this->assertConstraintValidationResultFailed('maxLength', $field->validate('jane.doe@example.test'));
		$this->assertConstraintValidationResultPassed('maxLength', $field->validate('a@b.co'));
	}

	#[Test]
	public function characters_and_octets_cannot_disagree_here(): void
	{
		// Every other field has to decide whether a length is code points or bytes. This one does
		// not: the grammar admits only ASCII on both sides of the @, so a well-formed address has
		// one character per octet and the 64-octet local-part limit and the character bounds
		// count the same units. An internationalised address would need the grammar to change.
		$field = $this->createField();

		$this->assertShapeFailed($field->validate('jané@example.test'));
		$this->assertShapeFailed($field->validate('jane@examplé.test'));
	}

	#[Test]
	#[DataProvider('boundsOutsideTheStandard')]
	public function a_bound_the_standard_does_not_allow_is_refused_where_it_is_written(callable $attempt): void
	{
		$this->expectException(InvalidArgumentException::class);

		$attempt($this->createField());
	}

	/** @return array<string, array{callable}> */
	public static function boundsOutsideTheStandard(): array
	{
		return [
			'a minimum below the shortest address' => [fn(EmailAddress $f): EmailAddress => $f->minLengthOf(2)],
			'a maximum above what can be delivered' => [fn(EmailAddress $f): EmailAddress => $f->maxLengthOf(255)],
			'a minimum above the maximum' => [fn(EmailAddress $f): EmailAddress => $f->maxLengthOf(10)->minLengthOf(20)],
			'a maximum below the minimum' => [fn(EmailAddress $f): EmailAddress => $f->minLengthOf(20)->maxLengthOf(10)],
		];
	}

	// ── domains ────────────────────────────────────────────────────────────────────────────

	#[Test]
	public function domains_are_not_checked_unless_restricted(): void
	{
		$result = $this->createField()->validate('user@anywhere.test');

		$this->assertConstraintValidationResultSkipped('allowedDomains', $result);
		$this->assertConstraintValidationResultSkipped('disallowedDomains', $result);
	}

	#[Test]
	public function an_allowed_domain_must_match_whole(): void
	{
		// example.test does not admit mail.example.test: a caller who wants subdomains says so
		// with a wildcard.
		$field = $this->createField()->allowDomains('example.test');

		$this->assertConstraintValidationResultPassed('allowedDomains', $field->validate('user@example.test'));
		$this->assertConstraintValidationResultFailed('allowedDomains', $field->validate('user@mail.example.test'));
		$this->assertConstraintValidationResultFailed('allowedDomains', $field->validate('user@other.test'));
	}

	#[Test]
	public function a_wildcard_stands_for_exactly_one_label(): void
	{
		$field = $this->createField()->allowDomains('*.example.test');

		$this->assertConstraintValidationResultPassed('allowedDomains', $field->validate('user@mail.example.test'));
		$this->assertConstraintValidationResultFailed('allowedDomains', $field->validate('user@a.b.example.test'));
		$this->assertConstraintValidationResultFailed('allowedDomains', $field->validate('user@example.test'));
	}

	#[Test]
	public function a_domain_matches_regardless_of_case(): void
	{
		$field = $this->createField()->allowDomains('Example.TEST');

		$this->assertConstraintValidationResultPassed('allowedDomains', $field->validate('user@example.test'));
	}

	#[Test]
	public function a_disallowed_domain_is_refused_and_others_are_not(): void
	{
		$field = $this->createField()->disallowDomains('throwaway.test');

		$this->assertConstraintValidationResultFailed('disallowedDomains', $field->validate('user@throwaway.test'));
		$this->assertConstraintValidationResultPassed('disallowedDomains', $field->validate('user@example.test'));
	}

	#[Test]
	public function domain_lists_accumulate(): void
	{
		$field = $this->createField()->allowDomains('a.test')->allowDomains('b.test', 'c.test');

		$this->assertSame(['a.test', 'b.test', 'c.test'], $field->allowedDomains);
	}

	#[Test]
	public function clearing_one_list_leaves_the_other_alone(): void
	{
		$field = $this->createField()->allowDomains('a.test')->disallowDomains('b.test')->clearAllowedDomains();

		$this->assertSame([], $field->allowedDomains);
		$this->assertSame(['b.test'], $field->disallowedDomains);
	}

	#[Test]
	public function an_empty_domain_is_rejected(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->createField()->allowDomains('');
	}

	// ── the field is sealed ────────────────────────────────────────────────────────────────

	#[Test]
	public function configuring_it_leaves_the_original_alone(): void
	{
		$field = $this->createField();
		$restricted = $field->allowDomains('example.test')->minLengthOf(10);

		$this->assertNotSame($field, $restricted);
		$this->assertSame([], $field->allowedDomains);
		$this->assertSame(3, $field->minLength);
	}


	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$this->assertNull($this->createField()->defaultValue);
	}
}
