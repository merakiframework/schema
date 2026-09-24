<?php
declare(strict_types=1);

namespace Meraki\CodeStyle\Fixer;

use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

/**
 * `declare(strict_types=1);` sits on line 2, with no blank line above it.
 *
 * PSR-12 asks for a blank line between the open tag and `declare`. Every file in src/ and
 * tests/ does the opposite, and 'blank_line_after_opening_tag' => false only stops the rule
 * inserting one — there is no built-in rule that removes an existing one. Hence this.
 *
 * Scope is deliberately narrow: it only ever touches the gap between `<?php` and a `declare`
 * that immediately follows it. A blank line after the open tag in any other file is left alone.
 */
final class NoBlankLineAfterOpeningTagFixer implements FixerInterface
{
	public function getName(): string
	{
		return 'Meraki/no_blank_line_after_opening_tag';
	}

	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'There must be no blank line between the open tag and a following `declare`.',
			[new CodeSample("<?php\n\ndeclare(strict_types=1);\n")],
		);
	}

	/**
	 * Runs late, so it cleans up after anything that moved the `declare` — `declare_strict_types`
	 * inserts one and brings its own whitespace.
	 */
	public function getPriority(): int
	{
		return -50;
	}

	public function isCandidate(Tokens $tokens): bool
	{
		return $tokens->isTokenKindFound(T_DECLARE);
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
		// Not index 0: bin/schema-lang opens with a shebang, which tokenises as T_INLINE_HTML.
		$open = $tokens->getNextTokenOfKind(-1, [[T_OPEN_TAG]]);

		if ($open === null) {
			return;
		}

		$next = $tokens->getNextMeaningfulToken($open);

		if ($next === null || !$tokens[$next]->isGivenKind(T_DECLARE)) {
			return;
		}

		// T_OPEN_TAG carries its own trailing newline, so anything left here is the blank line.
		$gap = $open + 1;

		if ($gap >= $next || !$tokens[$gap]->isWhitespace()) {
			return;
		}

		if (preg_match('/^\R+$/', $tokens[$gap]->getContent()) !== 1) {
			return;
		}

		$tokens->clearAt($gap);
		$tokens->clearEmptyTokens();
	}
}
