<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\Validator;
use Meraki\Schema\ValidatorName;
use Meraki\Schema\ValidatorTestCase;
use Meraki\Schema\Validator\MatchesRegex;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('validation')]
#[CoversClass(MatchesRegex::class)]
final class MatchesRegexTest extends ValidatorTestCase
{
	public function createValidator(): Validator
	{
		return new MatchesRegex('/^test$/');
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$validator = $this->createValidator();

		$name = $validator->name;

		$this->assertTrue($name->equals(new ValidatorName('pattern')));
	}
}
