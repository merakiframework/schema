<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field\ConstraintValidationResult;
use Brick\DateTime\Instant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Runs {@see AggregatedValidationResultTestCase} against a real aggregate.
 *
 * That case existed with no concrete subclass, so its fourteen tests were collected by nobody
 * and had never run — including the two covering `getFirst()`/`getLast()` after a filter, which
 * is exactly the defect that survived underneath them. An abstract test case with no host is
 * indistinguishable from no test at all, and looks like coverage.
 *
 * {@see SchemaValidationResult} is the aggregate chosen to host it because it adds the least of
 * its own: one instant, and `forField()`. {@see ResolvedField} would drag a field, a value, a
 * source and a uniqueness check into every inherited assertion.
 */
#[Group('validation')]
#[CoversClass(SchemaValidationResult::class)]
#[CoversClass(AggregatedValidationResult::class)]
final class SchemaValidationResultTest extends AggregatedValidationResultTestCase
{
	private const AT = 1789000000;

	public function createSubject(ValidationResult ...$results): SchemaValidationResult
	{
		return new SchemaValidationResult(Instant::of(self::AT), ...$results);
	}

	/**
	 * A fresh instance per call, deliberately: the aggregate compares and removes by identity,
	 * so two results that happen to be equal must still be two results.
	 */
	public function createPassedResult(): ConstraintValidationResult
	{
		return ConstraintValidationResult::pass('passed' . self::$seq++);
	}

	public function createFailedResult(): ConstraintValidationResult
	{
		return ConstraintValidationResult::fail('failed' . self::$seq++);
	}

	public function createSkippedResult(): ConstraintValidationResult
	{
		return ConstraintValidationResult::skip('skipped' . self::$seq++);
	}

	public function createPendingResult(): ConstraintValidationResult
	{
		return new ConstraintValidationResult(ValidationStatus::Pending, 'pending' . self::$seq++);
	}

	/** Keeps constraint names distinct, so nothing here depends on two results being tellable apart. */
	private static int $seq = 0;

	#[Test]
	public function it_carries_the_instant_the_request_was_judged_at(): void
	{
		$this->assertTrue(Instant::of(self::AT)->isEqualTo($this->createSubject()->evaluatedAt));
	}
}
