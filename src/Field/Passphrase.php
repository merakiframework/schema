<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedPassphrase = SerializedField&object{
 * 	type: 'passphrase',
 * 	entropy: int,
 * 	method: string,
 * 	dictionary: string
 * }
 * @extends AtomicField<string|null, SerializedPassphrase>
 */
final class Passphrase extends AtomicField
{
	private const DEFAULT_ENTROPY = [
		'standard' => [
			'complacent' => 35,
			'weak' => 48,
			'moderate' => 72,
			'strong' => 128,
			'paranoid' => 160,
		],
	];

	private const ALLOWED_METHODS = [
		'standard',
	];

	private const ALLOWED_DICTIONARIES = [
		'none',
	];

	public function __construct(
		Property\Name $name,
		public int $entropy = 72,
		public string $method = 'standard',
		public string $dictionary = 'none',
	) {
		parent::__construct(new Property\Type('passphrase', $this->validateType(...)), $name);

		if ($entropy < 1) {
			throw new InvalidArgumentException('Entropy must be a positive integer.');
		}

		if (!in_array($method, self::ALLOWED_METHODS, true)) {
			throw new InvalidArgumentException("Invalid method: $method. Allowed methods are: " . implode(', ', self::ALLOWED_METHODS));
		}

		if (!in_array($dictionary, self::ALLOWED_DICTIONARIES, true)) {
			throw new InvalidArgumentException("Invalid dictionary: $dictionary. Allowed dictionaries are: " . implode(', ', self::ALLOWED_DICTIONARIES));
		}
	}

	/**
	 * Create a passphrase with a "complacent" policy.
	 *
	 * Intended for accounts where security is not a concern — throwaway or low-value services,
	 * where the user doesn’t mind if the account gets compromised.
	 *
	 * Accepts short, simple passwords such as all-lowercase letters.
	 */
	public static function complacent(
		Property\Name $name,
		string $method = 'standard',
		string $dictionary = 'none',
	): self {
		return new self(
			$name,
			self::getDefaultEntropy('complacent', $method),
			$method,
			$dictionary,
		);
	}

	/**
	 * Create a passphrase with a "weak" policy.
	 *
	 * Suitable for low-security applications or temporary access where minimal protection is acceptable.
	 *
	 * Allows short or modest-strength passwords but still requires some diversity.
	 */
	public static function weak(
		Property\Name $name,
		string $method = 'standard',
		string $dictionary = 'none',
	): self {
		return new self(
			$name,
			self::getDefaultEntropy('weak', $method),
			$method,
			$dictionary,
		);
	}

	/**
	 * Create a passphrase with a "moderate" policy.
	 *
	 * A balanced policy suitable for most personal applications and general web accounts.
	 *
	 * Offers reasonable protection against brute-force or dictionary attacks.
	 */
	public static function moderate(
		Property\Name $name,
		string $method = 'standard',
		string $dictionary = 'none',
	): self {
		return new self(
			$name,
			self::getDefaultEntropy('moderate', $method),
			$method,
			$dictionary,
		);
	}

	/**
	 * Create a passphrase with a "strong" policy.
	 *
	 * Intended for sensitive applications like banking, health records, or workplace tools.
	 *
	 * Encourages longer, more complex passwords that resist both brute-force and common attacks.
	 */
	public static function strong(
		Property\Name $name,
		string $method = 'standard',
		string $dictionary = 'none',
	): self {
		return new self(
			$name,
			self::getDefaultEntropy('strong', $method),
			$method,
			$dictionary,
		);
	}

	/**
	 * Create a passphrase with a "paranoid" policy.
	 *
	 * Designed for mission-critical systems where compromise would have severe consequences
	 * (e.g., infrastructure controls, admin accounts for critical services).
	 *
	 * Requires extremely high-entropy passphrases resistant to nearly all known attacks.
	 */
	public static function paranoid(
		Property\Name $name,
		string $method = 'standard',
		string $dictionary = 'none',
	): self {
		return new self(
			$name,
			self::getDefaultEntropy('paranoid', $method),
			$method,
			$dictionary,
		);
	}

	protected function validateType(mixed $value): bool
	{
		return is_string($value);
	}

	public function getConstraints(): array
	{
		return [
			'entropy' => $this->validateEntropy(...),
			'dictionary' => $this->validateDictionary(...),
		];
	}

	private function validateEntropy(string $value): ?bool
	{
		$actualEntropy = match ($this->method) {
			'standard' => $this->calculateEntropyUsingStandardMethod($value),
		};

		return $actualEntropy >= $this->entropy;
	}

	private function calculateEntropyUsingStandardMethod(string $value): int
	{
		$poolSize = $this->estimateCharacterPoolSize($value);

		return (int)round(log($poolSize, 2) * mb_strlen($value));
	}

	private function estimateCharacterPoolSize(string $value): int
	{
		$size = 0;

		if (preg_match('/\p{Lu}/u', $value) === 1) {
			$size += 26;
		}

		if (preg_match('/\p{Ll}/u', $value) === 1) {
			$size += 26;
		}

		if (preg_match('/\p{Nd}/u', $value) === 1) {
			$size += 10;
		}

		if (preg_match('/[\p{P}\p{S}\p{Zs}]/u', $value) === 1) {
			$size += 32;
		}

		if (preg_match('/[^\x00-\x7F]/', $value) === 1) {
			$size += 1000;
		}

		return max($size, 1);
	}

	private function validateDictionary(string $value): ?bool
	{
		if ($this->dictionary === 'none') {
			return null;
		}

		return !match($this->dictionary) {
			'custom' => $this->isInCustomDictionary($value),
		};
	}

	private function isInCustomDictionary(string $value): bool
	{
		return true; // Placeholder for actual dictionary check logic
	}

	private static function getDefaultEntropy(string $level, string $method): int
	{
		return self::DEFAULT_ENTROPY[$method][$level]
			?? throw new InvalidArgumentException("No default entropy defined for method: $method and level: $level");
	}

	/**
	 * @return SerializedPassphrase
	 */
	public function serialize(): object
	{
		return (object)[
			'type' => $this->type->value,
			'name' => $this->name->value,
			'optional' => $this->optional,
			'value' => $this->defaultValue->unwrap(),
			'fields' => [],
			'entropy' => $this->entropy,
			'method' => $this->method,
			'dictionary' => $this->dictionary,
		];
	}

	/**
	 * @param SerializedPassphrase $serialized
	 */
	public static function deserialize(object $serialized, Field\Factory $fieldFactory): static
	{
		if ($serialized->type !== 'passphrase') {
			throw new InvalidArgumentException('Invalid serialized data for Passphrase.');
		}

		return (new self(
			new Property\Name($serialized->name),
			$serialized->entropy,
			$serialized->method,
			$serialized->dictionary,
		))->prefill($serialized->value);
	}
}
