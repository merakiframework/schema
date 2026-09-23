<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Field\Password\Strength;
use ZxcvbnPhp\Zxcvbn;
use Meraki\Schema\Field\Password\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;
use Meraki\Schema\PrefillPolicy;
use Meraki\Schema\ValueSource;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * A memorized secret — a password or a passphrase, which are the same field.
 *
 * Most people do not know the difference, current guidance treats both as one thing, and the
 * only real distinction was ever *how strength gets measured*. So there is one field, and
 * {@see self::minStrengthOf()} is where that judgement lives.
 *
 * Three kinds of limit, which are not interchangeable:
 *
 * - **Strength** — {@see Strength}, a tier the secret must reach. A weak password is a
 *   well-formed string that failed a judgement, not a malformed one, so it is a constraint.
 * - **Length** — in characters, counted with `mb_strlen()` like every other field, so these are
 *   code points rather than bytes or grapheme clusters.
 * Minimum counts per character class are available and **off by default**, because current
 * guidance discourages composition rules: they shrink the search space an attacker has to cover
 * while pushing users toward predictable substitutions. There are deliberately no *maximums* —
 * see below.
 *
 * ### Hashing is not this field's concern
 *
 * A password field answers "is this an acceptable secret", which is a question about the string
 * and the policy. What a hashing algorithm can consume is a different question, asked by a
 * different layer, and this field does not ask it.
 *
 * Worth knowing anyway, because it bites people: **bcrypt silently truncates at 72 bytes**, so a
 * hash of 72 `a`s verifies a string of 80 `a`s. Two secrets sharing a 72-byte prefix become the
 * same secret, and the tail a user typed protects nothing while appearing to. PHP's
 * `PASSWORD_DEFAULT` is still bcrypt as of 8.5, so the obvious call has this property.
 *
 * That belongs where the hashing happens. The usual fix is to pre-hash — `base64_encode(hash('sha256',
 * $secret, true))` before bcrypt — which removes the ceiling without rejecting anything the user
 * typed; Argon2id has no ceiling worth stating and is the current recommendation. Either way it is
 * a decision about infrastructure, and expressing it as a validation rule put it in the wrong
 * layer and asked the author to tell the field something the field could not otherwise know.
 *
 * @extends AtomicField<string|null>
 */
final readonly class Password extends AtomicField
{

	/**
	 * The shortest a secret may be required to be — and therefore also the default, because a
	 * field with no configuration is already at the baseline rather than below it.
	 *
	 * NIST SP 800-63B puts the floor for a user-chosen memorized secret at 8 characters. A field
	 * that let an author ask for four would be offering a way to be wrong: the author gains
	 * nothing a shorter minimum could give them, and anyone signing up gets a worse password.
	 * Configuration narrows from the baseline and never widens it.
	 *
	 * Characters, not bytes — see {@see self::minLengthOf()}. What a hashing algorithm can swallow
	 * is the hashing layer's business, not this field's.
	 */
	public const SHORTEST = 8;

	/** @var positive-int Characters. */
	public int $minLength;

	/** @var positive-int|null Characters; `null` means no maximum. */
	public ?int $maxLength;

	/** `null` means no strength floor was asked for. */
	public ?Strength $minStrength;

	/** @var non-negative-int|null Each `null` means the rule is off, which is the default. */
	public ?int $minUppercaseChars;

	public ?int $minLowercaseChars;

	public ?int $minDigits;

	public ?int $minSymbols;

	public function __construct(
		public FieldName $name,
	) {
		parent::__construct();

		$this->minLength = self::SHORTEST;
		$this->maxLength = null;
		$this->minStrength = null;
		$this->minUppercaseChars = null;
		$this->minLowercaseChars = null;
		$this->minDigits = null;
		$this->minSymbols = null;

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * The strength tier the secret must reach — a floor, so a stronger secret also passes.
	 */
	public function minStrengthOf(Strength $strength): static
	{
		return $this->with(['minStrength' => $strength]);
	}

	/**
	 * Requires the secret to be at least this many *characters*.
	 *
	 * Characters rather than bytes, because that is what somebody types and what NIST SP 800-63B
	 * specifies: `密` is one character and three bytes, and a limit written in bytes would quietly
	 * ask for a third as much from anyone not typing ASCII.
	 *
	 * @param int<self::SHORTEST, max> $characters
	 * @throws InvalidArgumentException if below the baseline, or above the maximum
	 */
	public function minLengthOf(int $characters): static
	{
		if ($characters < self::SHORTEST) {
			throw new InvalidArgumentException(sprintf(
				'A minimum length of %d is below the baseline of %d characters, which is the floor '
				. 'in NIST SP 800-63B. Configuration narrows what a field accepts; it cannot widen it.',
				$characters,
				self::SHORTEST,
			));
		}

		if ($this->maxLength !== null && $characters > $this->maxLength) {
			throw new InvalidArgumentException('A minimum length cannot exceed the maximum.');
		}

		$this->assertCompositionFits($this->minimumsTotal(), $this->maxLength);

		return $this->with(['minLength' => $characters]);
	}

	/**
	 * @param positive-int|null $characters `null` removes the limit
	 * @throws InvalidArgumentException if below one, or below the minimum
	 */
	public function maxLengthOf(?int $characters): static
	{
		if ($characters === null) {
			return $this->with(['maxLength' => null]);
		}

		if ($characters < 1) {
			throw new InvalidArgumentException('A maximum length must be at least one character.');
		}

		if ($characters < $this->minLength) {
			throw new InvalidArgumentException('A maximum length cannot be less than the minimum.');
		}

		$this->assertCompositionFits($this->minimumsTotal(), $characters);

		return $this->with(['maxLength' => $characters]);
	}


	/** @param non-negative-int $count */
	public function minNumberOfUppercaseChars(int $count): static
	{
		return $this->withCount('minUppercaseChars', $count);
	}

	/** @param non-negative-int $count */
	public function minNumberOfLowercaseChars(int $count): static
	{
		return $this->withCount('minLowercaseChars', $count);
	}

	/** @param non-negative-int $count */
	public function minNumberOfDigits(int $count): static
	{
		return $this->withCount('minDigits', $count);
	}

	/** @param non-negative-int $count */
	public function minNumberOfSymbols(int $count): static
	{
		return $this->withCount('minSymbols', $count);
	}

	/**
	 * Resolves to a {@see Password\Result}, which carries the measured entropy alongside the usual
	 * value and verdicts.
	 *
	 * @param list<\Meraki\Schema\Rule\AppliedOutcome> $appliedOutcomes
	 */
	public function resolve(
		mixed $given,
		array $appliedOutcomes = [],
		ValueSource $givenAs = ValueSource::Submitted,
	): Password\Result {
		return new Password\Result(
			$this,
			$given,
			$this->resolvedValueFor($given),
			$appliedOutcomes,
			$this->sourceOf($given, $givenAs),
			$this->evaluatedAt(),
		);
	}

	/**
	 * What a rule may ask about this field: no string form, deliberately — a rule must not be able to read a secret.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Basic
	{
		return new Matcher\Basic(ValueScope::of($this->name));
	}

	/**
	 * @param list<\Meraki\Schema\Rule\AppliedOutcome> $appliedOutcomes
	 */
	public function validate(
		mixed $given,
		array $appliedOutcomes = [],
		ValueSource $givenAs = ValueSource::Submitted,
		PrefillPolicy $policy = PrefillPolicy::Checked,
	): Password\Result {
		$raw = $given ?? $this->defaultValue;

		// Through the lifecycle's reader rather than calling parse() directly: this overrides
		// validate() to report entropy, not to decide what an unreadable secret means.
		$parsed = $raw === null ? null : self::readable($this->parse(...), $raw);
		$source = $this->sourceOf($given, $givenAs);

		return (new Password\Result($this, $given, $parsed ?? $raw, $appliedOutcomes, $source, $this->evaluatedAt()))
			->withResults(...$this->check($raw, $parsed, $source, $policy));
	}

	protected function parse(#[SensitiveParameter] mixed $value): Value
	{
		if ($value instanceof Value) {
			return $value;
		}

		// Any string is a password. Length, composition and strength are all constraints, and
		// putting any of them here would report "unreadable" where the result should say which
		// check failed and what the limit was.
		if (!is_string($value)) {
			throw MalformedValue::of(Value::class, 'a password is submitted as a string');
		}

		return new Value($value);
	}

	/**
	 * Each bound reports under its own name, so a failure says whether the floor or the ceiling
	 * was missed. The old shape put both ends of a range behind one constraint name and could
	 * not tell you which.
	 */
	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('minLength', $this->atLeastCharacters(...), $this->minLength),
			new Constraint('maxLength', $this->atMostCharacters(...), $this->maxLength),
			new Constraint('minStrength', $this->isStrongEnough(...), $this->minStrength?->value),
			new Constraint('minUppercaseChars', $this->atLeast('minUppercaseChars', self::UPPERCASE), $this->minUppercaseChars),
			new Constraint('minLowercaseChars', $this->atLeast('minLowercaseChars', self::LOWERCASE), $this->minLowercaseChars),
			new Constraint('minDigits', $this->atLeast('minDigits', self::DIGIT), $this->minDigits),
			new Constraint('minSymbols', $this->atLeast('minSymbols', self::SYMBOL), $this->minSymbols),
		);
	}

	/**
	 * The bits of guess-resistance in the secret, which is what {@see Strength} is a threshold
	 * on. This is the judgement the absorbed `Passphrase` field existed to make.
	 *
	 * Measured by zxcvbn, which matches the secret against ranked lists of common passwords,
	 * English words, names and surnames, plus keyboard walks, repeats, sequences and date
	 * patterns — so it answers "how many attempts does an attacker need", not "how many
	 * characters from how large an alphabet". That distinction is the whole point:
	 *
	 * | Secret | Naive estimate | zxcvbn |
	 * | --- | --- | --- |
	 * | 40 × `x` | 188 bits | **8.9 bits** |
	 * | `abcdefghijklmnopqrst` | 94 bits | **6.3 bits** |
	 * | `P@ssw0rd123` | 72 bits | **13.9 bits** |
	 * | `correct horse battery staple` | 164 bits | 67.5 bits |
	 *
	 * The naive arithmetic scored forty of the same letter as key-equivalent. A passing
	 * `minStrength` now means what it says.
	 *
	 * Two things to know. The dictionaries are **English-centric**, so a non-English secret is
	 * scored as though its words were unknown and reads as stronger than it is. And a genuinely
	 * random secret is *under*-estimated — 32 random hex characters carry 128 bits and report
	 * 104.6 — because zxcvbn's fallback model assumes a smaller alphabet than the generator used.
	 * Both err in directions that matter less than the repetition blind spot they replace.
	 */
	public function entropyOf(#[SensitiveParameter] string $value): int
	{
		// The dictionaries cost ~18ms and ~10MB to load, so the estimator is built once per
		// process. A function-static rather than a class one because a readonly class may not
		// declare static properties at all — and it is a cache of a stateless object rather
		// than state, so sharing it across requests carries none of the risk that sharing a
		// field's own state would.
		static $zxcvbn = null;

		$zxcvbn ??= new Zxcvbn();

		// zxcvbn reports how many attempts an attacker needs, never fewer than one, so the
		// logarithm is always defined.
		return (int) round(log((float) $zxcvbn->passwordStrength($value)['guesses'], 2));
	}

	private const UPPERCASE = '/\p{Lu}/u';
	private const LOWERCASE = '/\p{Ll}/u';
	private const DIGIT = '/\p{Nd}/u';
	private const SYMBOL = '/[^\p{L}\p{Nd}]/u';


	/**
	 * Sets one minimum, refusing a set of them that cannot all be satisfied at once. Raising where
	 * the author wrote it beats reporting it against somebody's request.
	 *
	 * @param non-negative-int|null $count
	 * @throws InvalidArgumentException
	 */
	private function withCount(string $property, ?int $count): static
	{
		if ($count !== null && $count < 0) {
			throw new InvalidArgumentException("A character count cannot be negative ({$property}).");
		}

		$total = $this->minimumsTotal() - ($this->{$property} ?? 0) + ($count ?? 0);

		$this->assertCompositionFits($total, $this->maxLength);

		return $this->with([$property => $count]);
	}

	/**
	 * How many characters every composition minimum demands between them.
	 *
	 * A plain sum because the four classes are **disjoint**: a character is uppercase, lowercase, a
	 * digit or a symbol, never two at once. So requiring ten of one and ten of another really does
	 * need twenty characters.
	 *
	 * @return non-negative-int
	 */
	private function minimumsTotal(): int
	{
		return ($this->minUppercaseChars ?? 0)
			+ ($this->minLowercaseChars ?? 0)
			+ ($this->minDigits ?? 0)
			+ ($this->minSymbols ?? 0);
	}

	/**
	 * Refuses composition minimums that together exceed the maximum length.
	 *
	 * Checked on every setter that can create the contradiction, because any of them may be the
	 * last to arrive.
	 *
	 * Note there is deliberately no mirrored check against `minLength`. It would have to say "the
	 * classes cannot add up to the minimum", and that is not sound: the four classes are disjoint
	 * but *not* exhaustive — `漢`, `א` and `ก` match none of them, since `\p{Lu}`/`\p{Ll}` miss the
	 * other letter categories. A twelve-character CJK secret counts zero in every class while
	 * being twelve characters long.
	 *
	 * @throws InvalidArgumentException
	 */
	private function assertCompositionFits(int $total, ?int $maxLength): void
	{
		if ($maxLength !== null && $total > $maxLength) {
			throw new InvalidArgumentException(sprintf(
				'Composition rules demand at least %d characters, which a maximum length of %d cannot hold.',
				$total,
				$maxLength,
			));
		}
	}

	private function atLeastCharacters(Value $parsed): bool
	{
		$value = $parsed->secret;

		return mb_strlen($value) >= $this->minLength;
	}

	private function atMostCharacters(Value $parsed): ?bool
	{
		$value = $parsed->secret;

		return $this->maxLength === null ? null : mb_strlen($value) <= $this->maxLength;
	}

	/**
	 * Bytes, not characters — this is the limit the hash imposes, and a multibyte secret
	 * reaches it sooner than its length suggests.
	 */

	private function isStrongEnough(Value $parsed): ?bool
	{
		$value = $parsed->secret;

		if ($this->minStrength === null) {
			return null;
		}

		return $this->entropyOf($value) >= $this->minStrength->asBits();
	}

	/**
	 * @return callable(Value): (bool|null)
	 */
	private function atLeast(string $property, string $pattern): callable
	{
		return fn(Value $parsed): ?bool => $this->{$property} === null
			? null
			: self::countMatching($pattern, $parsed->secret) >= $this->{$property};
	}

	private static function countMatching(string $pattern, string $value): int
	{
		return (int) preg_match_all($pattern, $value);
	}
}
