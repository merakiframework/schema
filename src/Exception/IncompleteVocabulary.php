<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use LogicException;
use Meraki\Schema\Exception;

/**
 * A field type the vocabulary cannot report on.
 *
 * `Message\Vocabulary` builds one of every field so it can list the keys a language pack has to
 * supply. It builds them from the name alone, which works for almost every field and cannot work
 * for one whose constructor demands more — a `Collection` needs a template, and there is no
 * template that can be guessed.
 *
 * So the vocabulary refuses rather than skipping. Skipping would leave that field's keys off the
 * list, `schema-lang missing` would report a complete pack, and the field would render with no
 * message in production.
 */
final class IncompleteVocabulary extends LogicException implements Exception
{
	public static function fieldNeedsMoreThanAName(string $kind, string $vocabulary): self
	{
		return new self(sprintf(
			'Field\\%s needs more than a name to build, so %s cannot report what it says. Add it '
			. 'to build() with the smallest arguments that satisfy it.',
			$kind,
			$vocabulary,
		));
	}
}
