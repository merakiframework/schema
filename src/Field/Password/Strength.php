<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Password;

/**
 * How hard a secret is to guess, as a floor the author asks for.
 *
 * A tier rather than a bit count, because the number is only meaningful relative to the
 * estimator producing it. {@see \Meraki\Schema\Field\Password::entropyOf()} uses zxcvbn, which
 * reports *guess-resistance* — how many attempts an attacker needs given known passwords,
 * words, names, keyboard patterns and repetition — and that lands on a very different scale
 * from the naive `log2(alphabet) × length` arithmetic. A tier says what the author meant and
 * leaves the scale an implementation detail.
 *
 * There is deliberately no tier meaning "no requirement". A field with no strength floor says
 * so by not asking, which is the same way every other constraint is left unset.
 */
enum Strength: string
{
	/** Beats the obvious: dictionary words, keyboard walks, a repeated character. */
	case Weak = 'weak';

	/** Resists casual offline attack. */
	case Moderate = 'moderate';

	/** The sensible default for anything worth protecting. */
	case Strong = 'strong';

	/** Passphrase territory; deliberate overkill for most things. */
	case Paranoid = 'paranoid';

	/** Realistically only reachable with a generated secret. */
	case Cryptographic = 'cryptographic';

	/**
	 * This tier expressed as bits of guess-resistance.
	 *
	 * Calibrated against zxcvbn's output rather than chosen for roundness. Measured on this
	 * implementation, the highest tier each of these reaches:
	 *
	 * | Secret | Bits | Tier |
	 * | --- | --- | --- |
	 * | `password` | 1.6 | none |
	 * | `hunter2` | 13.0 | none |
	 * | 40 × `x` | 8.9 | none |
	 * | `abcdefghijklmnopqrst` | 6.3 | none |
	 * | `P@ssw0rd123` | 13.9 | none |
	 * | `Tr0ub4dor&3` | 36.5 | `Moderate` |
	 * | `J#9vK@2mQ!7xZ&4p` | 53.2 | `Strong` |
	 * | `correct horse battery staple` | 67.5 | `Paranoid` |
	 * | 32 random hex characters | 104.6 | `Cryptographic` |
	 *
	 * The two worth noting: `Tr0ub4dor&3` sits *below* `Strong`, which is the point — it looks
	 * strong and is not — and the four-word passphrase beats it by 31 bits.
	 *
	 * These numbers are not comparable to bit counts from a different estimator. Changing the
	 * estimator means recalibrating this table, not keeping it.
	 */
	public function asBits(): int
	{
		return match ($this) {
			self::Weak => 20,
			self::Moderate => 30,
			self::Strong => 40,
			self::Paranoid => 60,
			self::Cryptographic => 90,
		};
	}

	/**
	 * Whether this tier is at least as demanding as the other.
	 */
	public function meets(self $floor): bool
	{
		return $this->asBits() >= $floor->asBits();
	}
}
