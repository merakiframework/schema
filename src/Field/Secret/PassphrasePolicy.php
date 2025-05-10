<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Secret;

use Meraki\Schema\Field\Secret\Policy;
use InvalidArgumentException;

final class PassphrasePolicy implements Policy
{
	private const DEFAULT_ENTROPY = [
		'standard' => [
			'complacent' => 35,
			'weak' => 48,
			'moderate' => 72,
			'strong' => 128,
			'paranoid' => 256,
		],
	];

	private const SCHEMA = [
		'entropy' => ['type' => 'int'],
		'method' => ['type' => 'string', 'default' => 'standard'],
		'dictionary' => ['type' => 'string', 'default' => 'none'],
	];

	public readonly string $name;

	public function __construct(
		public readonly int $entropy,
		public readonly string $method,
		public readonly string $dictionary = 'none',
		private ?PolicyParser $parser = null,
	) {
		$this->name = 'passphrase_policy';
		$this->parser = $parser ?? new PolicyParser(self::SCHEMA, [
			'entropy' => $entropy,
			'method' => $method,
			'dictionary' => $dictionary,
		]);

		if ($entropy < 1) {
			throw new InvalidArgumentException('Entropy must be a positive integer.');
		}
	}

	public static function parse(string $spec): self
	{
		$parser = PolicyParser::parse($spec, self::SCHEMA);

		return new self(
			entropy: $parser->get('entropy'),
			method: $parser->get('method'),
			dictionary: $parser->get('dictionary'),
			parser: $parser,
		);
	}

	public function matches(string $value): bool
	{
		if ($this->isInDictionary($value)) {
			return false;
		}

		return $this->calculateEntropy($value) >= $this->entropy;
	}

	private function isInDictionary(string $value): bool
	{
		return match ($this->dictionary) {
			'none' => false,
			default => throw new InvalidArgumentException('Unknown dictionary: ' . $this->dictionary),
		};
	}

	private function calculateEntropy(string $value): int
	{
		return match ($this->method) {
			'standard' => $this->calculateEntropyUsingStandardMethod($value),
			default => throw new InvalidArgumentException('Unknown method: ' . $this->method),
		};
	}

	private function calculateEntropyUsingStandardMethod(string $value): int
	{
		$poolSize = $this->estimateCharacterPoolSize($value);

		return (int)round(log($poolSize, 2) * mb_strlen($value));
	}

	private function estimateCharacterPoolSize(string $value): int
	{
		$size = 0;

		if (preg_match('/\p{Lu}/u', $value) === 1) { $size += 26; }
		if (preg_match('/\p{Ll}/u', $value) === 1) { $size += 26; }
		if (preg_match('/\p{Nd}/u', $value) === 1) { $size += 10; }
		if (preg_match('/[\p{P}\p{S}]/u', $value) === 1) {$size += 32; }
		if (preg_match('/[^\x00-\x7F]/', $value) === 1) { $size += 1000; }

		return max($size, 1);
	}

	public function __toString(): string
	{
		return (string)$this->parser;
	}

	/**
	 * Create a "complacent" passphrase policy.
	 *
	 * Intended for accounts where security is not a concern — throwaway or low-value services,
	 * where the user doesn’t mind if the account gets compromised.
	 *
	 * Accepts short, simple passwords such as all-lowercase letters.
	 */
	public static function complacent(string $method = 'standard', string $dictionary = 'none'): self
	{
		return new self(self::getDefaultEntropy('complacent', $method), $method, $dictionary);
	}

	/**
	 * Create a "weak" passphrase policy.
	 *
	 * Suitable for low-security applications or temporary access where minimal protection is acceptable.
	 *
	 * Allows short or modest-strength passwords but still requires some diversity.
	 */
	public static function weak(string $method = 'standard', string $dictionary = 'none'): self
	{
		return new self(self::getDefaultEntropy('weak', $method), $method, $dictionary);
	}

	/**
	 * Create a "moderate" passphrase policy.
	 *
	 * A balanced policy suitable for most personal applications and general web accounts.
	 *
	 * Offers reasonable protection against brute-force or dictionary attacks.
	 */
	public static function moderate(string $method = 'standard', string $dictionary = 'none'): self
	{
		return new self(self::getDefaultEntropy('moderate', $method), $method, $dictionary);
	}

	/**
	 * Create a "strong" passphrase policy.
	 *
	 * Intended for sensitive applications like banking, health records, or workplace tools.
	 *
	 * Encourages longer, more complex passwords that resist both brute-force and common attacks.
	 */
	public static function strong(string $method = 'standard', string $dictionary = 'none'): self
	{
		return new self(self::getDefaultEntropy('strong', 'method'), $method, $dictionary);
	}

	/**
	 * Create a "paranoid" passphrase policy.
	 *
	 * Designed for mission-critical systems where compromise would have severe consequences
	 * (e.g., infrastructure controls, admin accounts for critical services).
	 *
	 * Requires extremely high-entropy passphrases resistant to nearly all known attacks.
	 */
	public static function paranoid(string $method = 'standard', string $dictionary = 'none'): self
	{
		return new self(self::getDefaultEntropy('paranoid', $method), $method, $dictionary);
	}

	private static function getDefaultEntropy(string $level, string $method): int
	{
		return self::DEFAULT_ENTROPY[$method][$level]
			?? throw new InvalidArgumentException("No default entropy defined for method: $method and level: $level");
	}
}
