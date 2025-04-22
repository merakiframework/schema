<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\ValidationResultMessageProvider;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

#[Group('validation')]
#[CoversClass(ValidationResultMessageProvider::class)]
abstract class ValidationResultMessageProviderTestCase extends TestCase
{
}
