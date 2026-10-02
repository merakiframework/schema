<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use InvalidArgumentException;

/**
 * A record arrived carrying a key its value does not declare. Whatever built it is wrong.
 *
 * ### Why this is not a shape failure
 *
 * Every other bad value is absorbed: {@see \Meraki\Schema\Field\MalformedValue} is caught in
 * {@see \Meraki\Schema\Field\Definition::readable()} and reported as a field whose value could
 * not be read, because what arrived came from a submitter and a submitter is allowed to be wrong.
 *
 * A key is not submitter data. In any protocol, the keys are the *vocabulary* — part of the
 * schema's identity rather than part of the request — and something has to map a payload onto
 * them. `ammount` is that mapping being wrong, on every request, for every submitter, until
 * somebody edits a line of code. There is no verdict that can say so.
 *
 * ### The core cannot know whose mistake it is, and must not guess
 *
 * A verdict is already a decision about an audience: it says *this is reportable to whoever
 * submitted*. For a stray key this library has no idea who that is.
 *
 * | What built the record | What the stray key means |
 * | --- | --- |
 * | a form port's mapping layer | its own code is wrong |
 * | a client of a JSON API | a peer implementation is wrong, or has drifted |
 * | a producer on a queue | two deployments disagree about the message |
 * | your own test fixture | the fixture is stale |
 *
 * One of those wants a 500, one a `400` naming the key, one an alert, one a red test. Returning
 * a verdict would be this library answering a question it cannot see — which is exactly the kind
 * of answer a core has no business giving. Raising says *I cannot judge this, it is yours*, and
 * each port maps it to whatever its protocol means by "the caller is wrong".
 *
 * So this escapes. `validate()` and `resolve()` stop, and the trace points at the line that built
 * the record.
 *
 * ### A port maps; it does not forward
 *
 * The obligation this puts on a port, and it is the same one in every protocol: **take the keys
 * you know out of the payload and build the record from them.** Handing over a decoded body
 * wholesale — `json_decode($body)`, `(object) $_POST['billing']`, a deserialised envelope — makes
 * a remote party the co-author of your key vocabulary, and then its typo becomes your exception.
 * That is the bug the exception is pointing at, not the typo.
 *
 * ### It is the type system's job, and the type system does not finish it
 *
 * Each record value's constructor documents its `@param` as an object shape —
 * `object{currency?: string|null, amount?: string|int|float|null}` — and PHPStan checks two of
 * the three ways a record can be wrong, at the call site, before anything runs:
 *
 * | | caught statically |
 * | --- | --- |
 * | a required key missing | **yes** — *"does not have property $country"* |
 * | a declared key of the wrong type | **yes** — *"type string\|null does not accept int"* |
 * | a key nobody declared | **no** |
 *
 * The third is not an oversight in the annotation. An object shape in PHPStan is a floor and not
 * a ceiling: `object{currency: string, ammount: string}` satisfies
 * `object{currency?: string, amount?: string}`, because everything the shape asks for is there.
 * There is no sealed-object syntax to reach for, and `@param object` would be worse — it drops
 * the two checks that do work for the sake of the one that cannot.
 *
 * So this runtime check is not belt-and-braces over static analysis. It is the **only** thing
 * that catches a stray key, which is also the one a rename causes.
 *
 * ### Where the line is, and why it is not further along
 *
 * A key that *is* declared and holds something unusable stays a
 * {@see \Meraki\Schema\Field\MalformedValue}: absorbed, reported, rendered as a message. `['amount' => 'twelve']` is somebody typing badly in a box
 * that was mapped correctly.
 *
 * The temptation is to extend this to types — nobody *types* an array, so surely that is the
 * builder's mistake too. It is not, and the reason is attribution rather than severity. A JSON
 * client chooses the type of what it sends; a form port receives strings and chooses the type
 * itself. Which of them got it wrong depends on the protocol, and this library does not know the
 * protocol. **Where the mistake can be attributed to the builder without knowing the port, it is
 * raised; where it cannot, it is reported.** Keys can; types cannot.
 */
final class BrokenInputContract extends InvalidArgumentException implements Exception
{
	private function __construct(
		string $message,
		/** The value class that refused it, which is machine-readable where a name would not be. */
		public readonly string $valueClass,
		/** @var list<string> the keys it does not accept, so a port can act without parsing English */
		public readonly array $unknownKeys,
	) {
		parent::__construct($message);
	}

	/**
	 * @param class-string $valueClass what was being built
	 * @param list<string> $unknown the keys it does not accept
	 * @param list<string> $accepted every key it does, which is the whole of the contract
	 */
	public static function recordHasKeysItDoesNotAccept(string $valueClass, array $unknown, array $accepted): self
	{
		return new self(
			sprintf(
				'%s does not accept "%s". It is submitted with: "%s". A key it does not declare '
					. 'is a broken contract between this library and whatever built the record, '
					. 'so it is raised rather than reported as an unreadable value.',
				$valueClass,
				implode('", "', $unknown),
				implode('", "', $accepted),
			),
			$valueClass,
			$unknown,
		);
	}
}
