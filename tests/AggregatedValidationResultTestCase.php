<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\ValidationResult;
use Meraki\Schema\ValidationResultTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('validation')]
#[CoversClass(AggregatedValidationResult::class)]
abstract class AggregatedValidationResultTestCase extends ValidationResultTestCase
{
	/**
	 * @inheritDoc
	 */
	abstract public function createSubject(ValidationResult ...$results): AggregatedValidationResult;

	/**
	 * A "passed" result which can be used by the subject.
	 */
	abstract public function createPassedResult(): ValidationResult;

	/**
	 * A "failed" result which can be used by the subject.
	 */
	abstract public function createFailedResult(): ValidationResult;

	/**
	 * A "skipped" result which can be used by the subject.
	 */
	abstract public function createSkippedResult(): ValidationResult;

	/**
	 * A "pending" result which can be used by the subject.
	 */
	abstract public function createPendingResult(): ValidationResult;

	#[Test]
	public function it_can_be_constructed_with_results(): void
	{
		$pass = $this->createPassedResult();
		$fail = $this->createFailedResult();

		$sut = $this->createSubject($pass, $fail);

		$this->assertCount(2, $sut);
		$this->assertTrue($sut->contains($pass));
		$this->assertTrue($sut->contains($fail));
	}

	#[Test]
	public function it_can_add_results(): void
	{
		$pass = $this->createPassedResult();
		$sut = $this->createSubject();
		$sut = $sut->add($pass);

		$this->assertCount(1, $sut);
		$this->assertTrue($sut->contains($pass));
	}

	#[Test]
	public function it_can_remove_results(): void
	{
		$pass = $this->createPassedResult();
		$sut = $this->createSubject($pass);
		$sut = $sut->remove($pass);

		$this->assertCount(0, $sut);
		$this->assertFalse($sut->contains($pass));
	}

	#[Test]
	public function it_can_merge_with_another(): void
	{
		$r1 = $this->createPassedResult();
		$r2 = $this->createFailedResult();
		$s1 = $this->createSubject($r1);
		$s2 = $this->createSubject($r2);

		$merged = $s1->merge($s2);

		$this->assertCount(2, $merged);
		$this->assertTrue($merged->contains($r1));
		$this->assertTrue($merged->contains($r2));
	}

	#[Test]
	public function it_can_get_failed(): void
	{
		$pass = $this->createPassedResult();
		$fail = $this->createFailedResult();
		$sut = $this->createSubject($pass, $fail);

		$onlyFailed = $sut->getFailed();

		$this->assertCount(1, $onlyFailed);
		$this->assertTrue($onlyFailed->contains($fail));
		$this->assertFalse($onlyFailed->contains($pass));
	}

	#[Test]
	public function it_reports_all_passed_correctly(): void
	{
		$pass1 = $this->createPassedResult();
		$pass2 = $this->createPassedResult();

		$sut = $this->createSubject($pass1, $pass2);

		$this->assertTrue($sut->allPassed());
	}

	#[Test]
	public function it_reports_any_failed_correctly(): void
	{
		$pass = $this->createPassedResult();
		$fail = $this->createFailedResult();

		$sut = $this->createSubject($pass, $fail);

		$this->assertTrue($sut->anyFailed());
	}

	#[Test]
	public function it_returns_first_and_last(): void
	{
		$first = $this->createPassedResult();
		$second = $this->createFailedResult();

		$sut = $this->createSubject($first, $second);

		$this->assertSame($first, $sut->getFirst());
		$this->assertSame($second, $sut->getLast());
	}

	#[Test]
	public function it_knows_if_it_is_empty(): void
	{
		$sutIsEmpty = $this->createSubject();

		$this->assertTrue($sutIsEmpty->isEmpty());
		$this->assertFalse($sutIsEmpty->isNotEmpty());

		$sutIsNotEmpty = $this->createSubject($this->createPassedResult());

		$this->assertFalse($sutIsNotEmpty->isEmpty());
		$this->assertTrue($sutIsNotEmpty->isNotEmpty());
	}
}
