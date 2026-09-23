<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Field\Uri\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;

/**
 * @extends AtomicField<string|null>
 * @todo allow for use of different uri/url standards (e.g. whatwg)
 * @todo allow for specifying the "kind" of URI (e.g. "IRI" or "URI" or "URL" or "URN".)
 * @todo allow for specifying the "type" of URI, (e.g. "absolute" or "relative" or "network-path" or "scheme-relative".)
 */
final readonly class Uri extends AtomicField
{
	/** @var non-negative-int */
	public int $minLength;

	/** @var non-negative-int|null `null` means no limit. */
	public ?int $maxLength;

	/**
	 * Schemes this field will accept, lower-cased. Empty means any: a URI is not always a
	 * web link, and a caller who needs it to be says so with {@see self::allowSchemes()}.
	 *
	 * @var list<non-empty-string>
	 */
	public array $allowedSchemes;

	public function __construct(
		public FieldName $name,
	) {
		parent::__construct();

		$this->minLength = self::initially(0);
		$this->maxLength = self::initially(null);
		$this->allowedSchemes = self::initially([]);

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * @param non-negative-int $minChars
	 * @throws InvalidConfiguration if negative, or above the maximum
	 */
	public function minLengthOf(int $minChars): static
	{
		if ($minChars < 0) {
			throw InvalidConfiguration::minimumIsNegative('length');
		}

		if ($this->maxLength !== null && $minChars > $this->maxLength) {
			throw InvalidConfiguration::minimumExceedsMaximum('length');
		}

		return $this->with(['minLength' => $minChars]);
	}

	/**
	 * @param non-negative-int|null $maxChars `null` removes the limit
	 * @throws InvalidConfiguration if negative, or below the minimum
	 */
	public function maxLengthOf(?int $maxChars): static
	{
		if ($maxChars === null) {
			return $this->with(['maxLength' => null]);
		}

		if ($maxChars < 0) {
			throw InvalidConfiguration::maximumIsNegative('length');
		}

		if ($maxChars < $this->minLength) {
			throw InvalidConfiguration::maximumIsBelowMinimum('length');
		}

		return $this->with(['maxLength' => $maxChars]);
	}

	/**
	 * Restricts the field to the given schemes. Anything rendered back into a page or
	 * followed as a redirect should declare one, so that `javascript:` and `data:` cannot
	 * reach it.
	 *
	 * Accumulates, like every other `allow*()`. Lifting the restriction is
	 * {@see self::clearAllowedSchemes()}.
	 *
	 * @param non-empty-string $scheme
	 * @param non-empty-string ...$schemes
	 * @throws InvalidConfiguration if a scheme is empty
	 */
	public function allowSchemes(string $scheme, string ...$schemes): static
	{
		$allowed = $this->allowedSchemes;

		foreach ([$scheme, ...$schemes] as $candidate) {
			if ($candidate === '') {
				throw InvalidConfiguration::listMemberIsEmpty('scheme');
			}

			$candidate = strtolower($candidate);

			if (!in_array($candidate, $allowed, true)) {
				$allowed[] = $candidate;
			}
		}

		return $this->with(['allowedSchemes' => $allowed]);
	}

	/**
	 * Accepts any scheme again. Worth being deliberate about: this is what lets `javascript:`
	 * and `data:` back in.
	 */
	public function clearAllowedSchemes(): static
	{
		return $this->with(['allowedSchemes' => []]);
	}

	/**
	 * What a rule may ask about this field: a URI is matched and searched, never ranked.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Text
	{
		return new Matcher\Text(ValueScope::of($this->name));
	}

	/**
	 * The submitted string, once it is known to be a URI — not the parsed object.
	 *
	 * The parse is the check, and then it is thrown away, because `minLength` and `maxLength`
	 * measure the string the author actually typed: stringifying a parsed URI can hand back a
	 * canonicalised form of a different length. A consumer wanting the object builds it from this
	 * string, which is one line and is their choice of representation rather than ours.
	 */
	protected function parse(mixed $value): Value
	{
		if ($value instanceof Value) {
			return $value;
		}

		if (!is_string($value)) {
			throw MalformedValue::of(Value::class, 'a URI is submitted as a string');
		}

		return new Value($value);
	}

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('minLength', $this->meetsMinimumLength(...), $this->minLength),
			new Constraint('maxLength', $this->meetsMaximumLength(...), $this->maxLength),
			new Constraint('allowedSchemes', $this->isAnAllowedScheme(...), $this->allowedSchemes),
		);
	}

	private function meetsMinimumLength(Value $parsed): bool
	{
		$value = $parsed->uri;

		return mb_strlen($value) >= $this->minLength;
	}

	private function meetsMaximumLength(Value $parsed): ?bool
	{
		$value = $parsed->uri;

		return $this->maxLength === null ? null : mb_strlen($value) <= $this->maxLength;
	}

	private function isAnAllowedScheme(Value $parsed): ?bool
	{
		// No list means nothing was asked, so nothing was checked.
		if ($this->allowedSchemes === []) {
			return null;
		}

		// Already parsed and already lower-cased, by the value that had to parse it anyway to
		// know it was a URI at all.
		// A relative reference has no scheme, so it cannot satisfy an allowlist.
		return $parsed->scheme !== null && in_array($parsed->scheme, $this->allowedSchemes, true);
	}
}
