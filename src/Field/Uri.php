<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\InvalidUriException;

/**
 * @extends AtomicField<string|null>
 */
final class Uri extends AtomicField
{
	public int $min = 0;

	public int $max = PHP_INT_MAX;

	/**
	 * Schemes this field will accept, lower-cased. Empty means any: a URI is not always a
	 * web link, and a caller who needs it to be says so with {@see self::allowSchemes()}.
	 *
	 * @var array<string>
	 */
	public array $allowedSchemes = [];

	public function __construct(
		Property\Name $name,
	) {
		parent::__construct($name);
	}

	public function minLengthOf(int $minChars): self
	{
		if ($minChars < 0) {
			throw new InvalidArgumentException('Minimum length must be a positive integer.');
		}

		if ($minChars > $this->max) {
			throw new InvalidArgumentException('Minimum length cannot be greater than maximum length.');
		}

		$this->min = $minChars;

		return $this;
	}

	public function maxLengthOf(int $maxChars): self
	{
		if ($maxChars < 0) {
			throw new InvalidArgumentException('Maximum length must be a positive integer.');
		}

		if ($maxChars < $this->min) {
			throw new InvalidArgumentException('Maximum length cannot be less than minimum length.');
		}

		if ($maxChars > PHP_INT_MAX) {
			throw new InvalidArgumentException('Maximum length cannot exceed PHP_INT_MAX.');
		}

		$this->max = $maxChars;

		return $this;
	}

	protected function cast(mixed $value): string
	{
		return $value;
	}

	/**
	 * Restricts the field to the given schemes. Anything rendered back into a page or
	 * followed as a redirect should declare one, so that `javascript:` and `data:` cannot
	 * reach it.
	 *
	 * Called with no arguments, the restriction is lifted.
	 */
	public function allowSchemes(string ...$schemes): self
	{
		$this->allowedSchemes = array_values(array_unique(array_map(strtolower(...), $schemes)));

		return $this;
	}

	public function validateValue(mixed $value): bool
	{
		return $this->parse($value) !== null;
	}

	/**
	 * Parses with PHP's own RFC 3986 implementation rather than a pattern of our own: the
	 * grammar is a matter of public record, and the previous pattern had every group
	 * optional, so it accepted any string at all.
	 */
	private function parse(mixed $value): ?Rfc3986Uri
	{
		if (!is_string($value) || $value === '') {
			return null;
		}

		try {
			return new Rfc3986Uri($value);
		} catch (InvalidUriException) {
			return null;
		}
	}

	protected function getConstraints(): array
	{
		return [
			'min' => $this->validateMin(...),
			'max' => $this->validateMax(...),
			'scheme' => $this->validateScheme(...),
		];
	}

	private function validateScheme(mixed $value): ?bool
	{
		if ($this->allowedSchemes === []) {
			return null;
		}

		$scheme = $this->parse($value)?->getScheme();

		// A relative reference has no scheme, so it cannot satisfy an allowlist.
		return $scheme !== null && in_array(strtolower($scheme), $this->allowedSchemes, true);
	}

	private function validateMin(mixed $value): bool
	{
		return mb_strlen($value) >= $this->min;
	}

	private function validateMax(mixed $value): bool
	{
		return mb_strlen($value) <= $this->max;
	}
}
