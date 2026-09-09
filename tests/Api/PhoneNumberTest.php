<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Field;
use Meraki\Schema\Property;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * `allow()` used to do two jobs at once: constrain which countries were acceptable, and
 * supply the region a national-format number was parsed against. Those are separated, and
 * the second becomes a stated rule rather than a side effect.
 */
#[Group('api-2.0')]
final class PhoneNumberTest extends TestCase
{
	private function field(string ...$countries): Field\PhoneNumber
	{
		return new Field\PhoneNumber(new Property\Name('phone'), $countries);
	}

	#[Test]
	public function one_allowed_country_parses_a_national_number_against_it(): void
	{
		$resolved = $this->field('AU')->validate('0411 222 333');

		$this->assertFalse($resolved->anyFailed());
		$this->assertSame('+61411222333', $resolved->transformed);
	}

	#[Test]
	public function an_international_number_needs_no_country_at_all(): void
	{
		$resolved = $this->field('AU', 'NZ')->validate('+61411222333');

		$this->assertFalse($resolved->anyFailed());
		$this->assertSame('+61411222333', $resolved->transformed);
	}

	#[Test]
	public function a_national_number_with_several_countries_allowed_is_ambiguous(): void
	{
		// Well-formed input that simply cannot be resolved. A shape failure would say "not a
		// valid phone number", when the useful message is "which country is this from".
		$failed = $this->field('AU', 'NZ')->validate('0411 222 333')->get('unambiguous');

		$this->assertTrue($failed->failed());
	}

	#[Test]
	public function the_ambiguity_constraint_lists_the_countries_to_choose_from(): void
	{
		$failed = $this->field('AU', 'NZ')->validate('0411 222 333')->get('unambiguous');

		$this->assertSame(['AU', 'NZ'], $failed->bound);
	}

	#[Test]
	public function naming_the_country_resolves_an_otherwise_ambiguous_number(): void
	{
		$resolved = $this->field('AU', 'NZ')->validate(['number' => '0411 222 333', 'country' => 'AU']);

		$this->assertFalse($resolved->anyFailed());
		$this->assertSame('+61411222333', $resolved->transformed);
	}

	#[Test]
	public function a_number_from_a_country_that_is_not_allowed_fails(): void
	{
		$this->assertTrue(
			$this->field('AU')->validate('+12015550123')->get('allowedCountries')->failed(),
		);
	}

	#[Test]
	public function it_transforms_to_an_e164_string(): void
	{
		// A value object was considered, so the resolved country could travel with it, and
		// rejected because nothing needs the country. Note it is *not* recoverable from the
		// prefix: +1 covers the US, Canada and some twenty Caribbean nations.
		$this->assertIsString($this->field('AU')->validate('0411 222 333')->transformed);
	}
}
