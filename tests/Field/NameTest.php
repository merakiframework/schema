<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Name;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('field')]
#[CoversClass(Name::class)]
final class NameTest extends TestCase
{
	#[Test]
	public function it_sets_the_value_correctly(): void
	{
		$expectedName = 'field_name';

		$actualName = new Name($expectedName);

		$this->assertSame($expectedName, $actualName->value);
	}
}
