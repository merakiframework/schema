<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule\OutcomeGroup;
use Meraki\Schema\Rule\Outcome;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(OutcomeGroup::class)]
final class OutcomeGroupTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$this->assertTrue(class_exists(OutcomeGroup::class));
	}

	#[Test]
	public function it_has_no_outcomes_by_default(): void
	{
		$outcomes = new OutcomeGroup();

		$this->assertEmpty($outcomes->__toArray());
		$this->assertCount(0, $outcomes);
		$this->assertTrue($outcomes->isEmpty());
	}

	#[Test]
	public function it_can_have_outcomes_added(): void
	{
		$require = Outcome::require('#/fields/username');
		$outcomes = new OutcomeGroup();

		$outcomes = $outcomes->add($require);

		$this->assertTrue($outcomes->contains($require));
	}

	#[Test]
	public function adding_outcome_increases_count(): void
	{
		$require = Outcome::require('#/fields/username');
		$outcomes = new OutcomeGroup();

		$outcomes = $outcomes->add($require);

		$this->assertCount(1, $outcomes);
		$this->assertFalse($outcomes->isEmpty());
	}

	#[Test]
	public function outcomes_can_be_added_during_construction(): void
	{
		$require = Outcome::require('#/fields/username');
		$outcomes = new OutcomeGroup($require);

		$this->assertTrue($outcomes->contains($require));
	}

	#[Test]
	public function it_can_have_multiple_outcomes_added(): void
	{
		$outcome1 = Outcome::require('#/fields/username');
		$outcome2 = Outcome::set('#/fields/username/optional', false);
		$outcomes = new OutcomeGroup();

		$outcomes = $outcomes->add($outcome1);
		$outcomes = $outcomes->add($outcome2);

		$this->assertTrue($outcomes->contains($outcome1));
		$this->assertTrue($outcomes->contains($outcome2));
		$this->assertCount(2, $outcomes);
	}

	#[Test]
	public function it_can_get_first_outcome(): void
	{
		$outcome = Outcome::require('#/fields/username');
		$outcomes = new OutcomeGroup($outcome);

		$this->assertSame($outcome, $outcomes->first());
	}

	#[Test]
	public function it_returns_nothing_if_no_outcomes(): void
	{
		$outcomes = new OutcomeGroup();

		$this->assertNull($outcomes->first());
	}

	#[Test]
	public function it_can_have_outcomes_removed(): void
	{
		$outcome1 = Outcome::require('#/fields/username');
		$outcome2 = Outcome::set('#/fields/username/optional', false);
		$outcomes = new OutcomeGroup($outcome1, $outcome2);

		$outcomes = $outcomes->remove($outcome1);

		$this->assertFalse($outcomes->contains($outcome1));
		$this->assertTrue($outcomes->contains($outcome2));
		$this->assertCount(1, $outcomes);
	}

	#[Test]
	public function adding_outcomes_is_immutable(): void
	{
		$outcome = Outcome::require('#/fields/username');
		$outcomes1 = new OutcomeGroup();

		$outcomes2 = $outcomes1->add($outcome);

		$this->assertNotSame($outcomes1, $outcomes2);
		$this->assertFalse($outcomes1->contains($outcome));
		$this->assertTrue($outcomes2->contains($outcome));
	}

	#[Test]
	public function removing_outcomes_is_immutable(): void
	{
		$outcome = Outcome::require('#/fields/username');
		$outcomes1 = new OutcomeGroup($outcome);

		$outcomes2 = $outcomes1->remove($outcome);

		$this->assertNotSame($outcomes1, $outcomes2);
		$this->assertTrue($outcomes1->contains($outcome));
		$this->assertFalse($outcomes2->contains($outcome));
	}
}
