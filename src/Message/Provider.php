<?php
declare(strict_types=1);

namespace Meraki\Schema\Message;

/**
 * Where wording comes from, in whatever language a request asked for.
 *
 * The schema does not know how messages are stored, written or formatted. It knows that something
 * can turn a verdict into a sentence, and that whether one exists must never change what a verdict
 * *is*. Validation is language-independent — the same data passes or fails identically in every
 * locale — so a provider is asked only after the judging is done.
 *
 * A provider serves *many* languages, which is why locale is a parameter here rather than
 * something fixed when the provider is built. One provider is registered on the schema and the
 * locale arrives per request:
 *
 *     $schema = new Facade('signup', messages: $provider);
 *     $result = $schema->validate($data, locale: 'en-AU');
 *
 * {@see self::forLocale()} resolves once per request, and the {@see Translator} it returns is what
 * every field result is handed. A hundred-field form does one lookup rather than a hundred.
 *
 * ### Nothing here may fail because a language is missing
 *
 * An unknown locale answers with {@see Silence}, not an exception. The moment a missing
 * translation can alter an outcome, "validation is language-independent" stops being true, and a
 * German user starts seeing a different set of errors from an English one. {@see self::supports()}
 * is there for a caller who wants to refuse an unsupported language *up front*, which is a
 * decision about the request rather than about the data.
 */
interface Provider
{
	/**
	 * Whether this provider has wording for a language.
	 *
	 * Offered so an application can reject an unsupported `Accept-Language` before it validates
	 * anything, rather than discovering it in an empty message set afterwards.
	 */
	public function supports(string $locale): bool;

	/**
	 * The wording for one language, resolved once and reused for every field in the request.
	 *
	 * Returns {@see Silence} for a language this provider does not have, never null and never an
	 * exception — see the note above.
	 */
	public function forLocale(string $locale): Translator;
}
