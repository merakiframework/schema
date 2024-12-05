<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Attribute;
use Meraki\Schema\Rule\Outcome;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(Outcome::class)]
final class OutcomeTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$this->assertTrue(class_exists(Outcome::class));
	}

	#[Test]
	public function it_has_an_action(): void
	{
		$action = new Attribute('action', 'require');
		$target = new Attribute('target', '#/fields/username');
		$outcome = new Outcome($action, $target);

		$this->assertSame($action, $outcome->action);
	}

	#[Test]
	public function it_has_a_target(): void
	{
		$action = new Attribute('action', 'require');
		$target = new Attribute('target', '#/fields/username');
		$outcome = new Outcome($action, $target);

		$this->assertSame($target, $outcome->target);
	}

	#[Test]
	public function it_has_other_attributes_that_can_be_accessed(): void
	{
		$action = new Attribute('action', 'set');
		$target = new Attribute('target', '#/fields/username/optional');
		$to = new Attribute('to', false);
		$outcome = new Outcome($action, $target, $to);

		$this->assertSame($to, $outcome->to);
	}
}
