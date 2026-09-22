<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Matcher;

use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Draft;

/**
 * The two questions a value with a string form can answer.
 *
 * Offered only by a matcher whose field parses to a {@see \Stringable} — email address, name,
 * phone number, text, URI, UUID, and the temporal and numeric types.
 *
 * The absences are the interesting part. {@see \Meraki\Schema\Field\Password\Value} and
 * {@see \Meraki\Schema\Field\CreditCard\Value} have no `__toString()` **on purpose**, so their
 * matchers do not carry this trait and `$password->when()->contains('a')` does not compile. A
 * rule reading the text of a secret should be hard to write by accident, and this is that
 * decision arriving where an author can see it.
 */
trait AsksText
{
	/**
	 * Holds when the value's text contains the given text. Case-sensitive; use
	 * {@see self::matches()} with an `i` flag when it should not be.
	 */
	public function contains(string $needle): Draft
	{
		return new Draft(new Condition\Contains($this->scope, $needle));
	}

	/**
	 * Holds when the value's text matches the given pattern — a PCRE with its delimiters, the
	 * same thing `Text::matching()` takes.
	 */
	public function matches(string $pattern): Draft
	{
		return new Draft(new Condition\Matches($this->scope, $pattern));
	}
}
