<?php
declare(strict_types=1);

namespace Meraki\CodeStyle\Fixer;

use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

/**
 * A compound ternary condition is parenthesised.
 *
 * `$a === 1 || $a === 2 ? x : y` becomes `($a === 1 || $a === 2) ? x : y`.
 *
 * The parentheses are for the reader, not the parser. `&&` and `||` already bind tighter than
 * `?:`, so this changes nothing about how PHP evaluates the expression — which is exactly why
 * it is worth writing down. A reader should not have to know the precedence table to see where
 * the condition ends.
 *
 * ## Only `&&` and `||`, deliberately
 *
 * A condition that is a single comparison is left alone: `$x === null ? a : b` stays as it is.
 * `===` binds tighter than `?:` just as `||` does, so a rule covering every comparison would be
 * more consistent — and it was considered and rejected. The ambiguity worth spending
 * parentheses on is *where a multi-clause condition ends*; one comparison already reads as one
 * unit. Applying it to every comparison would have touched 66 further sites to no benefit.
 *
 * The counts, measured across src/ and tests/: 5 conditions use `&&` or `||` (2 of them already
 * parenthesised), 66 are a single comparison, 34 are a bare value or call.
 *
 * ## Why this is not risky
 *
 * The parentheses are a semantic no-op for `&&` and `||`. They would NOT be for `and`, `or` and
 * `xor`, which bind *looser* than `?:` — there, `$a and $b ? 1 : 2` means `$a and ($b ? 1 : 2)`
 * and wrapping the condition would change the meaning. This fixer never triggers on those
 * tokens, and the risky config's `logical_operators` rule reports the day one is written
 * (there are currently zero in src/, tests/, tools/, examples/ and bin/).
 */
final class ParenthesizedTernaryConditionFixer implements FixerInterface
{
	/**
	 * Tokens a ternary condition can legitimately end with.
	 *
	 * An allow-list rather than a deny-list, because the thing being excluded is every nullable
	 * type hint in the codebase: `?int` is always preceded by `(`, `,`, `:`, a visibility
	 * modifier, `readonly`, `static`, or an asymmetric-visibility modifier. None of those can
	 * end an expression, so listing what can is both shorter and safer.
	 */
	private const CONDITION_CAN_END_WITH = [
		T_VARIABLE,
		T_STRING,
		T_CONSTANT_ENCAPSED_STRING,
		T_LNUMBER,
		T_DNUMBER,
		T_CLASS,
	];

	public function getName(): string
	{
		return 'Meraki/parenthesized_ternary_condition';
	}

	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'A ternary condition containing `&&` or `||` must be wrapped in parentheses.',
			[new CodeSample("<?php\n\$a = \$b === 1 || \$b === 2 ? 3 : null;\n")],
		);
	}

	public function getPriority(): int
	{
		return 0;
	}

	public function isCandidate(Tokens $tokens): bool
	{
		return $tokens->isAnyTokenKindsFound([T_BOOLEAN_AND, T_BOOLEAN_OR]);
	}

	public function isRisky(): bool
	{
		return false;
	}

	public function supports(SplFileInfo $file): bool
	{
		return true;
	}

	public function fix(SplFileInfo $file, Tokens $tokens): void
	{
		// Backwards, so an insertion never invalidates an index still to be visited.
		for ($index = count($tokens) - 1; $index >= 0; $index--) {
			if (!$tokens[$index]->equals('?')) {
				continue;
			}

			$bounds = $this->conditionBounds($tokens, $index);

			if ($bounds === null) {
				continue;
			}

			[$start, $end] = $bounds;

			$tokens->insertAt($end + 1, new Token(')'));
			$tokens->insertAt($start, new Token('('));
		}
	}

	/**
	 * The first and last token of the condition, or null if this `?` should not be touched.
	 *
	 * @return array{int, int}|null
	 */
	private function conditionBounds(Tokens $tokens, int $questionMark): ?array
	{
		$end = $tokens->getPrevMeaningfulToken($questionMark);

		if ($end === null) {
			return null;
		}

		$last = $tokens[$end];

		// `)` and `]` close an expression; the named kinds are values. Anything else — a comma,
		// a colon, a modifier — means this `?` introduces a nullable type, not a ternary.
		if (!$last->equalsAny([')', ']']) && !$last->isGivenKind(self::CONDITION_CAN_END_WITH)) {
			return null;
		}

		$start = null;
		$sawLogicalOperator = false;

		for ($index = $end; $index >= 0; $index--) {
			$token = $tokens[$index];

			// PHP 8 makes an unparenthesised nested ternary a fatal error, so a real nested one
			// is already bracketed and the walk meets `(` first. Reaching a bare `?` or `:`
			// means this is a shape not worth guessing about.
			if ($token->equalsAny(['?', ':'])) {
				return null;
			}

			$block = Tokens::detectBlockType($token);

			if ($block !== null && $block['isStart'] === false) {
				$index = $tokens->findBlockStart($block['type'], $index);

				continue;
			}

			if ($this->isBoundary($token)) {
				$start = $index + 1;

				break;
			}

			if ($token->isGivenKind([T_BOOLEAN_AND, T_BOOLEAN_OR])) {
				$sawLogicalOperator = true;
			}
		}

		$start ??= 0;

		// The rule is about compound conditions. A bare `$x ? a : b` reads fine unaided.
		if (!$sawLogicalOperator) {
			return null;
		}

		// Move past whitespace or comments the boundary scan stepped over.
		$start = $tokens->getNextMeaningfulToken($start - 1);

		if ($start === null || $start > $end) {
			return null;
		}

		if ($this->alreadyWrapped($tokens, $start, $end)) {
			return null;
		}

		return [$start, $end];
	}

	private function isBoundary(Token $token): bool
	{
		if ($token->equalsAny(['(', '[', '{', ';', ',', '='])) {
			return true;
		}

		return $token->isGivenKind([
			T_OPEN_TAG,
			T_RETURN,
			T_ECHO,
			T_PRINT,
			T_THROW,
			T_YIELD,
			T_YIELD_FROM,
			T_CASE,
			T_DEFAULT,
			T_DOUBLE_ARROW,
			T_COALESCE,
			T_PLUS_EQUAL,
			T_MINUS_EQUAL,
			T_MUL_EQUAL,
			T_DIV_EQUAL,
			T_MOD_EQUAL,
			T_POW_EQUAL,
			T_CONCAT_EQUAL,
			T_AND_EQUAL,
			T_OR_EQUAL,
			T_XOR_EQUAL,
			T_SL_EQUAL,
			T_SR_EQUAL,
			T_COALESCE_EQUAL,
		]);
	}

	private function alreadyWrapped(Tokens $tokens, int $start, int $end): bool
	{
		if (!$tokens[$start]->equals('(')) {
			return false;
		}

		return $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $start) === $end;
	}
}
