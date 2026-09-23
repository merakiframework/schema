<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use InvalidArgumentException;

/**
 * Input that is not the kind of thing a value holds.
 *
 * Thrown by a {@see ParsedValue}'s constructor when it is handed something it cannot be made of.
 * What happens next depends on who asked, and {@see Definition} decides that rather than the
 * field:
 *
 * | Asked by | Answer |
 * | --- | --- |
 * | A request, through {@see Definition::resolvedValueFor()} | caught, reported as `null` — the *shape* failing |
 * | An author, through `defaultsTo()` | allowed through, so the reason reaches the person who can act on it |
 *
 * That split is the whole point of raising rather than returning null. The definition-time check
 * used to say only *"The default for "email" is not a value it can hold"*, because `parse()` had
 * already thrown the reason away — while the constraint branch three lines below it named the
 * constraint that failed. The weaker message was the one whose audience could have used it.
 *
 * ### The message is for a developer, and is deliberately English
 *
 * It never reaches a request. Nothing renders it to a user, because by the time a user is
 * involved it has been caught and turned into an absent value, and what they see comes from a
 * language pack — see docs/MESSAGES.md. So this sits with the other forty-odd
 * `InvalidArgumentException`s in this library rather than with anything translatable: the line is
 * not "no English", it is **does it reach a request**.
 *
 * ### Why a type of its own, and not `InvalidArgumentException`
 *
 * Because it is caught, and a catch is only as good as its aim. `Brick\Money`, `libphonenumber`
 * and `commerceguys/addressing` all throw `InvalidArgumentException`, so catching that would
 * report a genuine bug in one of them as "the user typed something unreadable" — a wrong verdict,
 * an empty message, and nothing in the logs. `Money::parse()` did exactly that until this existed.
 *
 * It still *extends* `InvalidArgumentException`, because that is what it is: a constructor handed
 * an argument it could not use. Only the catching is narrowed.
 *
 * ### What belongs behind it, and what does not
 *
 * **Shape, never constraints.** A value raises this when it is not that kind of thing at all — an
 * email address with no `@`, a UUID that is not a UUID. It must never raise because a value fails
 * a *rule*: a text value is not malformed for being shorter than a field's minimum, and a URI is
 * not malformed for using a scheme the field does not allow.
 *
 * The distinction is the one the whole result surface is built on. A constraint failure reports
 * which check failed and what the limit was; an unreadable value reports neither, because nothing
 * got far enough to be checked. Moving a constraint behind this exception turns a precise answer
 * into a blank one.
 */
final class MalformedValue extends InvalidArgumentException
{
	private function __construct(
		string $message,
		/** The value class that refused it, which is machine-readable where a name would not be. */
		public readonly string $valueClass,
	) {
		parent::__construct($message);
	}

	/**
	 * @param class-string $valueClass what was being built, named by its class rather than in
	 *        words — the words for a field's kind belong to a language pack, and having them here
	 *        too would be two places saying what an email address is called.
	 * @param string $why what is wrong, in terms an author can act on
	 */
	public static function of(string $valueClass, string $why): self
	{
		return new self(sprintf('%s: %s.', self::kindOf($valueClass), $why), $valueClass);
	}

	/**
	 * The kind a value class belongs to — `EmailAddress` for `Field\EmailAddress\Value`.
	 *
	 * The last segment is almost always `Value`, which names nothing, so the one above it is what
	 * a reader needs. Same rule a language pack uses to find `kind.EmailAddress`, which is why the
	 * class is carried rather than a word: one derivation, not two spellings.
	 */
	private static function kindOf(string $class): string
	{
		$segments = explode('\\', $class);
		$last = array_pop($segments) ?? $class;

		return $last === 'Value' && $segments !== [] ? (string) array_pop($segments) : $last;
	}
}
