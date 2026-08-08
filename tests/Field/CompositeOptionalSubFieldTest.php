<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Property;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers {@see Composite::validate()}'s handling of *optional sub-fields*.
 *
 * No concrete composite exercised this before — every built-in one requires all of its
 * parts — so it is tested here against a purpose-built composite rather than through
 * {@see CompositeTestCase}, which cannot supply valid values generically.
 */
#[Group('field')]
#[CoversClass(Composite::class)]
final class CompositeOptionalSubFieldTest extends TestCase
{
	#[Test]
	public function an_optional_sub_field_left_empty_is_skipped_not_failed(): void
	{
		$sut = $this->createComposite();
		$sut->nickname->makeOptional();

		$result = $sut->input(['name' => 'Ada'])->validate();

		$nickname = $result->get('test.nickname');

		$this->assertNotNull($nickname);
		$this->assertSame(ValidationStatus::Skipped, $nickname->get('type')?->status);
		$this->assertSame(ValidationStatus::Skipped, $nickname->get('min')?->status);
	}

	#[Test]
	public function an_optional_sub_field_that_was_filled_in_still_has_its_constraints_evaluated(): void
	{
		$sut = $this->createComposite();
		$sut->nickname->makeOptional();

		// 'Zed' is 3 characters, below the sub-field's minimum of 5.
		$result = $sut->input(['name' => 'Ada', 'nickname' => 'Zed'])->validate();

		$nickname = $result->get('test.nickname');

		$this->assertNotNull($nickname);
		$this->assertSame(ValidationStatus::Passed, $nickname->get('type')?->status);
		$this->assertSame(ValidationStatus::Failed, $nickname->get('min')?->status);
	}

	#[Test]
	public function a_required_sub_field_left_empty_still_fails(): void
	{
		$sut = $this->createComposite();

		$result = $sut->input(['name' => 'Ada'])->validate();

		$this->assertSame(ValidationStatus::Failed, $result->get('test.nickname')?->get('type')?->status);
	}

	private function createComposite(): Composite
	{
		return new class (new Property\Name('test')) extends Composite {
			public function __construct(Property\Name $name)
			{
				parent::__construct(
					$name,
					(new Field\Text(new Property\Name('name')))->minLengthOf(2),
					(new Field\Text(new Property\Name('nickname')))->minLengthOf(5),
				);
			}

			protected function getConstraints(): array
			{
				return [];
			}
		};
	}
}
