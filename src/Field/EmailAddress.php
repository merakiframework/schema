<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\AtomicMultiValue as AtomicMultiValueField;
use Meraki\Schema\Field\EmailAddress\Format;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * @extends Serialized<array|string|null>
 * @property-read string $format
 * @property-read int $min
 * @property-read int $max
 * @property-read string[] $allowedDomains
 * @property-read string[] $disallowedDomains
 * @internal
 */
interface SerializedEmailAddress extends Serialized
{
}

/**
 * Represents an email address field.
 *
 * Validates the email address format according to the HTML specification,
 * which is a subset (and saner version) of the format specified in RFC 5322.
 *
 * @extends AtomicMultiValueField<array|string|null, SerializedEmailAddress>
 * @see https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address
 */
final class EmailAddress extends AtomicMultiValueField
{
	/** @readonly */
	public int $min;

	/** @readonly */
	public int $max;

	/** @readonly */
	public array $allowedDomains;

	/** @readonly */
	public array $disallowedDomains;

	public function __construct(
		Property\Name $name,
		public Format $format = Format::Basic,
	) {
		parent::__construct(new Property\Type('email_address', $this->validateType(...)), $name);

		$this->min = $this->format->getAllowableMinLengthTotal();
		$this->max = $this->format->getAllowableMaxLengthTotal();
		$this->allowedDomains = [];
		$this->disallowedDomains = [];
	}

	public function minLengthOf(int $minChars): self
	{
		$allowableMinLength = $this->format->getAllowableMinLengthTotal();

		if ($minChars < $allowableMinLength) {
			throw new InvalidArgumentException(sprintf('Minimum length must be greater than %d.', $allowableMinLength));
		}

		if ($minChars > $this->max) {
			throw new InvalidArgumentException('Minimum length cannot be greater than maximum length.');
		}

		$this->min = $minChars;

		return $this;
	}

	public function maxLengthOf(int $maxChars): self
	{
		$allowableMaxLength = $this->format->getAllowableMaxLengthTotal();

		if ($maxChars > $allowableMaxLength) {
			throw new InvalidArgumentException(sprintf('Maximum length must be less than %d.', $allowableMaxLength));
		}

		if ($maxChars < 1) {
			throw new InvalidArgumentException('Maximum length must be a positive integer.');
		}

		if ($maxChars < $this->min) {
			throw new InvalidArgumentException('Maximum length cannot be less than minimum length.');
		}

		$this->max = $maxChars;

		return $this;
	}

	public function allowDomain(string ...$domains): self
	{
		$this->allowedDomains = array_merge($this->allowedDomains, $domains);

		return $this;
	}

	public function disallowDomain(string ...$domains): self
	{
		$this->disallowedDomains = array_merge($this->disallowedDomains, $domains);

		return $this;
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

	protected function validateType(mixed $value): bool
	{
		$value = $this->cast($value);

		if (!is_array($value)) {
			return false;
		}

		if (count($value) === 0) {
			return false;
		}

		foreach ($value as $emailAddress) {
			if (!$this->format->validate($emailAddress)) {
				return false;
			}
		}

		return true;
	}

	protected function getConstraints(): array
	{
		return [
			'min' => $this->validateMin(...),
			'max' => $this->validateMax(...),
			'allowed_domains' => $this->validateAllowedDomains(...),
			'disallowed_domains' => $this->validateDisallowedDomains(...),
		];
	}

	private function validateMin(mixed $value): bool
	{
		return mb_strlen($value) >= $this->min;
	}

	private function validateMax(mixed $value): bool
	{
		return mb_strlen($value) <= $this->max;
	}

	private function validateAllowedDomains(mixed $value): bool
	{
		if (empty($this->allowedDomains)) {
			return true;
		}

		foreach ($this->allowedDomains as $domain) {
			if (self::matchesDomainPattern($value, $domain)) {
				return true;
			}
		}

		return false;
	}

	private function validateDisallowedDomains(mixed $value): bool
	{
		if (empty($this->disallowedDomains)) {
			return true;
		}

		foreach ($this->disallowedDomains as $domain) {
			if (self::matchesDomainPattern($value, $domain)) {
				return false;
			}
		}

		return true;
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

	public function serialize(): SerializedEmailAddress
	{
		return new class(
			type: $this->type->value,
			name: $this->name->value,
			format: $this->format->value,
			optional: $this->optional,
			value: $this->defaultValue->unwrap(),
			min: $this->min,
			max: $this->max,
			allowedDomains: $this->allowedDomains,
			disallowedDomains: $this->disallowedDomains
		) implements SerializedEmailAddress {
			public function __construct(
				public readonly string $type,
				public readonly string $name,
				public readonly bool $optional,
				public readonly array|string|null $value,
				public readonly string $format,
				public readonly int $min,
				public readonly int $max,
				public readonly array $allowedDomains,
				public readonly array $disallowedDomains
			) {}
			public function getConstraints(): array
			{
				return ['min', 'max', 'allowed_domains', 'disallowed_domains'];
			}
		};
	}

	/**
	 * @param SerializedEmailAddress $serialized
	 */
	public static function deserialize(Serialized $serialized): static
	{
		if ($serialized->type !== 'email_address' || !($serialized instanceof SerializedEmailAddress)) {
			throw new InvalidArgumentException('Invalid serialized data for EmailAddress.');
		}

		$emailField = new self(
			new Property\Name($serialized->name),
			Format::from($serialized->format)
		);

		$emailField->optional = $serialized->optional;

		return $emailField->minLengthOf($serialized->min)
			->maxLengthOf($serialized->max)
			->allowDomain(...$serialized->allowedDomains)
			->disallowDomain(...$serialized->disallowedDomains)
			->prefill($serialized->value);
	}
}
