<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use InvalidArgumentException;
use Meraki\Schema\Exception;

/**
 * A field configured in a way it could not hold.
 *
 * Raised by a wither, at the line that called it, before the schema is ever validated against
 * anything. That is deliberate and it is the same argument as {@see InvalidRule}'s: a field whose
 * limits contradict each other does not fail loudly on a request, it accepts nothing or rejects
 * nothing, and either looks exactly like a form nobody filled in correctly.
 *
 * Almost every one of these is a *coherence* failure rather than a type failure — a minimum past
 * its own maximum, a step finer than the precision it steps through, a bound in a currency the
 * field does not take. PHP's type system cannot state those, so they are stated here.
 *
 * The first four are shared: they take the noun the field uses for what it measures — "length",
 * "file size", "count" — so a Text and a Collection refuse the same mistake in the same words.
 */
final class InvalidConfiguration extends InvalidArgumentException implements Exception
{
	// ── the shape every measured field has ──────────────────────────────────

	public static function minimumIsNegative(string $what): self
	{
		return new self(sprintf('A minimum %s cannot be negative.', $what));
	}

	public static function maximumIsNegative(string $what): self
	{
		return new self(sprintf('A maximum %s cannot be negative.', $what));
	}

	public static function minimumExceedsMaximum(string $what): self
	{
		return new self(sprintf('A minimum %s cannot exceed the maximum.', $what));
	}

	public static function maximumIsBelowMinimum(string $what): self
	{
		return new self(sprintf('A maximum %s cannot be less than the minimum.', $what));
	}

	// ── a limit that could not do its job ───────────────────────────────────
	//
	// A limit that rejects nothing is not harmless: it reads as a rule and is not one, so the
	// field looks guarded to the next person to open the file. Each of these names the thing the
	// author probably wanted instead.

	public static function minimumCountWouldRejectNothing(): self
	{
		return new self(
			'A minimum count below one cannot reject anything; use makeOptional() to allow an empty collection.',
		);
	}

	public static function maximumCountWouldAcceptNothing(): self
	{
		return new self('A maximum count of zero would accept nothing; make the field optional instead.');
	}

	public static function maximumLengthWouldAcceptNothing(): self
	{
		return new self('A maximum length must be at least one character.');
	}

	public static function minimumLengthIsBelowWhatIsWellFormed(int $shortest): self
	{
		return new self(sprintf(
			'A minimum length below %d cannot reject anything: no shorter address is well-formed.',
			$shortest,
		));
	}

	public static function maximumLengthIsAboveWhatCanBeDelivered(int $longest): self
	{
		return new self(sprintf(
			'A maximum length above %d would accept addresses that cannot be delivered.',
			$longest,
		));
	}

	/**
	 * Configuration narrows what a field accepts; it cannot widen it. A password field's floor is
	 * the one thing an author may not lower.
	 */
	public static function minimumLengthIsBelowTheBaseline(int $given, int $baseline): self
	{
		return new self(sprintf(
			'A minimum length of %d is below the baseline of %d characters, which is the floor '
			. 'in NIST SP 800-63B. Configuration narrows what a field accepts; it cannot widen it.',
			$given,
			$baseline,
		));
	}

	public static function minimumLengthWouldRejectNothing(): self
	{
		return new self(
			'A minimum length of zero cannot reject anything; use makeOptional() to allow the field to be left out.',
		);
	}

	// ── password composition ────────────────────────────────────────────────

	public static function characterCountIsNegative(string $property): self
	{
		return new self(sprintf('A character count cannot be negative (%s).', $property));
	}

	public static function compositionDemandsMoreThanTheMaximumLength(int $demanded, int $maxLength): self
	{
		return new self(sprintf(
			'Composition rules demand at least %d characters, which a maximum length of %d cannot hold.',
			$demanded,
			$maxLength,
		));
	}

	// ── lists of accepted things ────────────────────────────────────────────

	/**
	 * @param string $what the noun the field uses: "scheme", "media type", "domain"
	 */
	public static function listMemberIsEmpty(string $what): self
	{
		return new self(sprintf('A %s cannot be empty.', $what));
	}

	public static function regionIsNotSupported(string $country): self
	{
		return new self(sprintf('Country \'%s\' is not a supported region.', $country));
	}

	public static function uuidVersionDoesNotExist(int $version): self
	{
		return new self(sprintf(
			'There is no UUID version %d. Versions run 1 to 8, with 0 for the nil UUID and -1 for the max.',
			$version,
		));
	}

	// ── money ───────────────────────────────────────────────────────────────
	//
	// Money's limits are per currency, so each of these names one: a field that takes AUD and JPY
	// holds two minimums, two maximums and two scales, and a message that did not say which would
	// send the author to the wrong line.

	public static function minimumAmountExceedsMaximum(string $amount, string $currency): self
	{
		return new self(sprintf('A minimum of %s %s cannot exceed its maximum.', $amount, $currency));
	}

	public static function maximumAmountIsBelowMinimum(string $amount, string $currency): self
	{
		return new self(sprintf('A maximum of %s %s cannot be less than its minimum.', $amount, $currency));
	}

	public static function currencyIsNotAllowed(string $currency): self
	{
		return new self(sprintf(
			'\'%s\' is not one of this field\'s currencies; allow it before giving it a bound.',
			$currency,
		));
	}

	public static function amountIsNotANumber(string $amount): self
	{
		return new self(sprintf('\'%s\' is not a number.', $amount));
	}

	public static function amountHasMoreDecimalsThanTheCurrencyTakes(
		string $amount,
		string $currency,
		int $scale,
	): self {
		return new self(sprintf(
			'%s cannot be held in %s, which this field takes to %d decimal place(s).',
			$amount,
			$currency,
			$scale,
		));
	}

	public static function currencyCodeIsNotAString(string $given): self
	{
		return new self(sprintf('A currency code must be a string, %s given.', $given));
	}

	public static function currencyCodeIsNotThreeLetters(string $currency): self
	{
		return new self(sprintf(
			'\'%s\' is not an ISO 4217 currency code; three letters were expected.',
			$currency,
		));
	}

	public static function currencyIsNotKnown(string $currency): self
	{
		return new self(sprintf('\'%s\' is not a known ISO 4217 currency.', $currency));
	}

	public static function currencyScaleIsNotAWholeNumber(string $currency, string $given): self
	{
		return new self(sprintf(
			'%s\'s scale must be a whole number of decimal places, %s given.',
			$currency,
			$given,
		));
	}

	public static function currencyScaleIsNegative(string $currency, int $places): self
	{
		return new self(sprintf('%s cannot have %d decimal places.', $currency, $places));
	}

	// ── numbers ─────────────────────────────────────────────────────────────

	public static function scaleIsNegative(): self
	{
		return new self('A scale cannot be negative: it is a count of decimal places.');
	}

	public static function precisionIsBelowOneDigit(): self
	{
		return new self('Precision must be at least one significant digit.');
	}

	// ── steps through time ──────────────────────────────────────────────────
	//
	// A step finer than the precision it steps through can never land on a value the field holds,
	// so every submission would fail the step check with nothing an author could type to satisfy it.

	public static function stepIsFinerThanMinutePrecision(): self
	{
		return new self('Cannot step in seconds or nanoseconds when precision is in minutes.');
	}

	public static function stepIsFinerThanSecondPrecision(): self
	{
		return new self('Cannot step in nanoseconds when precision is in seconds.');
	}

	// ── collection templates ────────────────────────────────────────────────

	public static function templateIsEmpty(): self
	{
		return new self('A collection needs at least one field in its template.');
	}

	public static function templateAlreadyHasAField(string $name): self
	{
		return new self(sprintf('The template already has a field named \'%s\'.', $name));
	}

	public static function templateFieldResolvesToMoreThanOneValue(string $field, string $got): self
	{
		return new self(sprintf(
			'"%s" cannot be a collection template field: it resolves to %s rather than one value.',
			$field,
			$got,
		));
	}

	public static function templateFieldValidatesToMoreThanOneValue(string $field, string $got): self
	{
		return new self(sprintf(
			'"%s" cannot be a collection template field: it validates to %s rather than one value.',
			$field,
			$got,
		));
	}

	// ── enums ───────────────────────────────────────────────────────────────

	public static function enumHasNoCases(): self
	{
		return new self('An enum with no cases accepts nothing, so there is nothing it could be for.');
	}

	public static function enumCaseIsNotAString(string $given): self
	{
		return new self(sprintf(
			'An enum case must be a string, and %s is not. A form submits "2" rather than 2, '
			. 'so a non-string case could never match one. Use Boolean for yes-or-no, Number '
			. 'with a step for a regular sequence, or strings for anything else.',
			$given,
		));
	}

	/**
	 * A select's placeholder option submits the empty string, so a case spelled that way would be
	 * chosen by everybody who chose nothing.
	 */
	public static function enumCaseIsAnEmptyString(): self
	{
		return new self(
			'An empty string cannot be a case: it is what a form submits when nothing was chosen.',
		);
	}

	// ── the rest ────────────────────────────────────────────────────────────

	public static function patternIsNotAValidRegex(string $pattern): self
	{
		return new self(sprintf(
			'mustMatch() was given %s, which is not a valid pattern. It takes a PCRE with its '
			. 'delimiters — "/^INV-/" rather than "^INV-".',
			var_export($pattern, true),
		));
	}

	public static function addressTypeMustNameAStreet(string $type): self
	{
		return new self(sprintf(
			'A %s address must name a street, so it cannot also be allowed without one.',
			$type,
		));
	}
}
