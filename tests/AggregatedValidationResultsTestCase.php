<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\AggregatedValidationResults;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(AggregatedValidationResults::class)]
abstract class AggregatedValidationResultsTestCase extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$this->assertTrue(class_exists(AggregatedValidationResults::class));
	}

	#[Test]
	public function it_can_be_constructed_with_no_results(): void
	{
		$results = $this->createAggregatedValidationResults();

		$this->assertCount(0, $results);
		$this->assertTrue($results->isEmpty());
		$this->assertCount(0, $results->getIterator());
		$this->assertCount(0, $results->getPasses());
		$this->assertCount(0, $results->getFailures());
		$this->assertCount(0, $results->getSkipped());
	}

	#[Test]
	public function when_there_are_no_results_verify_passes(): void
	{
		$results = $this->createAggregatedValidationResults();

		$this->assertFalse($results->allPassed());
		$this->assertFalse($results->anyPassed());
	}

	#[Test]
	public function when_there_are_no_results_verify_skipped(): void
	{
		$results = $this->createAggregatedValidationResults();

		$this->assertFalse($results->allSkipped());
		$this->assertFalse($results->anySkipped());
	}

	#[Test]
	public function when_there_are_no_results_verify_failures(): void
	{
		$results = $this->createAggregatedValidationResults();

		$this->assertFalse($results->anyFailed());
		$this->assertFalse($results->allFailed());
	}

	abstract public function createAggregatedValidationResults(): AggregatedValidationResults;
}
