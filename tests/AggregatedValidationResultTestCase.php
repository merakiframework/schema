<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\AggregatedValidationResult;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('validation')]
#[CoversClass(AggregatedValidationResult::class)]
abstract class AggregatedValidationResultTestCase extends TestCase
{
	abstract public function createAggregatedValidationResult(): AggregatedValidationResult;
}
