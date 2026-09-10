<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Constraint;
use Meraki\Schema\Field\EmailAddress\Format;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * Represents an email address field.
 *
 * Validates the email address format according to the HTML specification,
 * which is a subset (and saner version) of the format specified in RFC 5322.
 *
 * @extends Field<string|null>
 * @see https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address
 */
final class EmailAddress extends Field
{
	public private(set) int $minLength;

	public private(set) ?int $maxLength;

	public array $allowedDomains;

	public array $disallowedDomains;

	public function __construct(
		public readonly Property\Name $name,
		public Format $format = Format::Basic,
	) {

		$this->minLength = $this->format->getAllowableMinLengthTotal();
		$this->maxLength = $this->format->getAllowableMaxLengthTotal();
		$this->allowedDomains = [];
		$this->disallowedDomains = [];
	}

	public function minLengthOf(int $minChars): self
	{
		$allowableMinLength = $this->format->getAllowableMinLengthTotal();

		if ($minChars < $allowableMinLength) {
			throw new InvalidArgumentException(sprintf('Minimum length must be greater than %d.', $allowableMinLength));
		}

		if ($this->maxLength !== null && $minChars > $this->maxLength) {
			throw new InvalidArgumentException('Minimum length cannot be greater than maximum length.');
		}

		return clone($this, ['minLength' => $minChars]);
	}

	public function maxLengthOf(?int $maxChars): self
	{
		if ($maxChars === null) {
			return clone($this, ['maxLength' => null]);
		}

		$allowableMaxLength = $this->format->getAllowableMaxLengthTotal();

		if ($maxChars > $allowableMaxLength) {
			throw new InvalidArgumentException(sprintf('Maximum length must be less than %d.', $allowableMaxLength));
		}

		if ($maxChars < 1) {
			throw new InvalidArgumentException('Maximum length must be a positive integer.');
		}

		if ($maxChars < $this->minLength) {
			throw new InvalidArgumentException('Maximum length cannot be less than minimum length.');
		}

		return clone($this, ['maxLength' => $maxChars]);
	}

	public function allowDomain(string ...$domains): self
	{
		// If no domains are provided, it means any domain is allowed, so we clear the allowedDomains list.
		if (empty($domains)) {
			return clone($this, ['allowedDomains' => []]);
		}

		return clone($this, ['allowedDomains' => array_merge($this->allowedDomains, $domains)]);
	}

	public function disallowDomain(string ...$domains): self
	{
		// If no domains are provided, it means no domains are disallowed, so we clear the disallowedDomains list.
		if (empty($domains)) {
			return clone($this, ['disallowedDomains' => []]);
		}

		return clone($this, ['disallowedDomains' => array_merge($this->disallowedDomains, $domains)]);
	}

	protected function parseValue(string $value): array
	{
		/**
		 * Split the string by commas, but ignore commas inside double quotes.
		 * Allow empty segments between commas.
		 */
		$matches = preg_split('/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/', $value);

		// Flatten matches into a single-level array
		$results = array_map('trim', $matches);

		return $results;
	}

	protected function cast(mixed $value): mixed
	{
		if (is_string($value)) {
			return $this->parseValue($value);
		}

		return $value;
	}

	public function validateValue(mixed $value): bool
	{
		return is_string($value) || $value === null;
	}

	public function constraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('minLength', $this->meetsMinimumLength(...), $this->minLength),
			new Constraint('maxLength', $this->meetsMaximumLength(...), $this->maxLength),
			new Constraint('allowedDomains', $this->validateAllowedDomains(...), $this->allowedDomains),
			new Constraint('disallowedDomains', $this->validateDisallowedDomains(...), $this->disallowedDomains),
		);
	}

	private function meetsMinimumLength(string $value): bool
	{
		return mb_strlen($value) >= $this->minLength;
	}

	private function meetsMaximumLength(string $value): ?bool
	{
		if ($this->maxLength === null) {
			return null;
		}

		return mb_strlen($value) <= $this->maxLength;
	}

	private function validateAllowedDomains(string $value): bool
	{
		if (empty($this->allowedDomains)) {
			return true;
		}

		return array_filter($this->allowedDomains, fn($domain) => self::matchesDomainPattern($value, $domain)) !== [];
	}

	private function validateDisallowedDomains(string $value): bool
	{
		if (empty($this->disallowedDomains)) {
			return true;
		}

		return array_filter($this->disallowedDomains, fn($domain) => self::matchesDomainPattern($value, $domain)) === [];
	}

	private static function matchesDomainPattern(string $email, string $pattern): bool
	{
		$atPos = strrpos($email, '@');

		if ($atPos === false) {
			return false;
		}

		$domain = substr($email, $atPos + 1); // get domain part only

		// Escape dots and convert '*' into a wildcard regex
		$escapedPattern = preg_quote($pattern, '/');
		$regex = '/^' . str_replace('\*', '[^.]+', $escapedPattern) . '$/i';

		return (bool)preg_match($regex, $domain);
	}
}
