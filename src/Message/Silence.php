<?php
declare(strict_types=1);

namespace Meraki\Schema\Message;

use Meraki\Schema\Field;

/**
 * A translator with nothing to say.
 *
 * What a {@see Provider} answers with for a language it does not have, and what a schema built
 * without a provider uses throughout. Every result still carries a {@see Set}; it is simply empty.
 *
 * This is the shape of the guarantee rather than a convenience. Validating with no messages is a
 * supported way to use this library — it is how it worked before messages existed, and how a field
 * validated on its own still works — so "no wording" has to be an ordinary answer travelling the
 * ordinary path. Making it `null` instead would put a branch in front of every read of
 * `$result->messages`, and the one that got missed would be a fatal error on a page whose only job
 * was to tell somebody their postcode was wrong.
 */
final readonly class Silence implements Translator
{
	private function __construct(public string $locale)
	{
	}

	/**
	 * Silence in a named language — the answer to a request for wording that does not exist.
	 *
	 * It keeps the tag that was asked for, so a consumer inspecting `$translator->locale` sees
	 * what it wanted rather than an empty string it has to interpret.
	 */
	public static function for(string $locale): self
	{
		return new self($locale);
	}

	/**
	 * Silence in no particular language, for a schema that was never given a provider.
	 */
	public static function always(): self
	{
		return new self('');
	}

	public function forShape(Field $field, Field\ShapeValidationResult $shape): ?string
	{
		return null;
	}

	public function forConstraint(Field $field, Field\ConstraintValidationResult $constraint): ?string
	{
		return null;
	}
}
