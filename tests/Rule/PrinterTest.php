<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Attribute;
use Meraki\Schema\Rule;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Rule\OutcomeGroup;
use Meraki\Schema\Rule\Printer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(Printer::class)]
final class PrinterTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$this->assertTrue(class_exists(Printer::class));
	}

	#[Test]
	public function it_pretty_prints_rules_only_one_level_deep(): void
	{
		$rule = Rule::matchAll()
			->when(Condition::create('#/fields/contact_method/value', 'equals', 'phone'))
			->then(Outcome::require('#/fields/phone_number'));

		$prettyFormatted = (new Printer())->print($rule);

		$this->assertEquals(
			file_get_contents(__DIR__ . '/../fixtures/printed_rules_one_level.txt'),
			$prettyFormatted
		);
	}
}
