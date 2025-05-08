<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Secret;

use Meraki\Schema\Field\Secret\Policy;


final class PassphrasePolicy implements Policy
{
	public readonly string $name = 'passphrase';

	public function __construct(
		public readonly int $entropy = 72,
	) {
	}

	public static function parse(string $policy): self
	{

	}

	public function matches(string $value): bool
	{
		return $this->calculateEntropy($value) >= $this->entropy;
	}

	private function calculateEntropy(string $value): int
	{
		// Naive estimation: log2(pool) * length
		$poolSize = $this->estimateCharacterPoolSize($value);

		return (int)round(log($poolSize, 2) * mb_strlen($value));
	}

	private function estimateCharacterPoolSize(string $value): int
	{
		$hasUpper = (bool) preg_match('/\p{Lu}/u', $value);
		$hasLower = (bool) preg_match('/\p{Ll}/u', $value);
		$hasDigits = (bool) preg_match('/\p{Nd}/u', $value);
		$hasSymbols = (bool) preg_match('/[\p{P}\p{S}]/u', $value);
		$hasUnicode = (bool) preg_match('/[^\x00-\x7F]/', $value);

		$size = 0;

		if ($hasUpper) {
			$size += 26;
		}

		if ($hasLower) {
			$size += 26;
		}

		if ($hasDigits) {
			$size += 10;
		}

		if ($hasSymbols) {
			$size += 32;
		}

		if ($hasUnicode) {
			$size += 1000;	// Arbitrary boost for Unicode variety
		}

		return max($size, 1);
	}

	public function __toString(): string
	{

	}
}
