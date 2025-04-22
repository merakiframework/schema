<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\AggregatedValidationResult;
use Meraki\Schema\ValidationResultTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('validation')]
#[CoversClass(AggregatedValidationResult::class)]
abstract class AggregatedValidationResultTestCase extends ValidationResultTestCase
{
	abstract public function createValidationResult(): AggregatedValidationResult;

	#[Test]
	public function it_is_an_aggregated_validation_result(): void
	{
		$result = $this->createValidationResult();

		$this->assertInstanceOf(AggregatedValidationResult::class, $result);
	}
}
