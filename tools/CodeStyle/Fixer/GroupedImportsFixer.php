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
 * Imports are ordered nearest-first, in one unbroken block.
 *
 *   1. this project            Meraki\Schema\*
 *   2. other Meraki packages   Meraki\*
 *   3. everything else scoped  Brick\*, PHPUnit\*, Uri\*, …
 *   4. the root namespace      Countable, Stringable, Closure, …
 *
 * Alphabetical within a tier, and no blank lines between tiers: the block reads as one list
 * that happens to run from the code you own to the code you merely use.
 *
 * `ordered_imports` cannot express this. Its `imports_order` distinguishes only class, function
 * and const, and it offers no grouping by namespace prefix — so it sorts the whole block flat.
 * It must stay disabled or it will undo this.
 *
 * ## Why tier 3 mixes PHP's own namespaced classes with third-party packages
 *
 * Because nothing in the token stream distinguishes them. `Uri\Rfc3986\Uri` ships with PHP 8.5
 * and `Brick\DateTime\Clock` comes from composer, and telling them apart would take a
 * hand-maintained list of PHP's namespaced classes that would be wrong every time PHP adds one.
 * Merging the two makes the whole classification mechanical: a name with no separator is in the
 * root namespace, and everything else is placed by prefix.
 */
final class GroupedImportsFixer implements FixerInterface
{
	/**
	 * This project's own namespace. Tier 2 is derived from its first segment, so the two tiers
	 * cannot drift apart.
	 */
	private const PROJECT_NAMESPACE = 'Meraki\Schema';

	/** A single backslash, spelled so it is legible next to the namespaces it splits. */
	private const SEPARATOR = "\x5C";

	public function getName(): string
	{
		return 'Meraki/grouped_imports';
	}

	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Imports are ordered project, same-vendor, third-party, root namespace.',
			[new CodeSample("<?php\nuse Stringable;\nuse Meraki\x5CSchema\x5CFieldName;\n")],
		);
	}

	/**
	 * Late, so the block is already expanded by `single_import_per_statement` and pruned by
	 * `no_unused_imports` before it is sorted. This is the slot `ordered_imports` occupies.
	 */
	public function getPriority(): int
	{
		return -30;
	}

	public function isCandidate(Tokens $tokens): bool
	{
		return $tokens->isTokenKindFound(T_USE);
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
		$imports = $this->collectImports($tokens);

		if ($imports === null || count($imports) < 2) {
			return;
		}

		$sorted = $imports;
		usort($sorted, static function (array $a, array $b): int {
			return [$a['tier'], strtolower($a['name'])] <=> [$b['tier'], strtolower($b['name'])];
		});

		if (array_column($sorted, 'name') === array_column($imports, 'name')) {
			return;
		}

		$first = $imports[0]['start'];
		$last = $imports[count($imports) - 1]['end'];

		$tokens->overrideRange($first, $last, $this->render($sorted));
	}

	/**
	 * Every top-level import, in the order written, or null if the file holds a shape this
	 * fixer will not reorder.
	 *
	 * @return list<array{start: int, end: int, name: string, tier: int, text: string}>|null
	 */
	private function collectImports(Tokens $tokens): ?array
	{
		$imports = [];

		foreach ($tokens as $index => $token) {
			// Top-level imports all precede the first brace in the file.
			if ($token->equals('{')) {
				break;
			}

			if (!$token->isGivenKind(T_USE)) {
				continue;
			}

			$next = $tokens->getNextMeaningfulToken($index);

			if ($next === null) {
				return null;
			}

			// A closure's `use (...)`, which is not an import at all.
			if ($tokens[$next]->equals('(')) {
				continue;
			}

			// `use function`, `use const` and group `use A\{B, C}` are shapes this codebase does
			// not have. Rather than guess at an ordering for them, leave the whole file alone.
			if ($tokens[$next]->isGivenKind([T_FUNCTION, T_CONST])) {
				return null;
			}

			$end = $tokens->getNextTokenOfKind($index, [';']);

			if ($end === null) {
				return null;
			}

			$name = '';

			for ($i = $index + 1; $i < $end; $i++) {
				if ($tokens[$i]->equals('{')) {
					return null;
				}

				if ($tokens[$i]->isGivenKind(T_AS)) {
					break;
				}

				// T_NS_SEPARATOR matters: PHP-CS-Fixer normalises the T_NAME_QUALIFIED that
				// token_get_all() returns into T_STRING + T_NS_SEPARATOR pairs. Collecting only
				// the T_STRINGs drops every backslash, which leaves tierOf() looking at
				// "MerakiSchemaFieldName" — no separator, so everything lands in the root-namespace
				// tier and the grouping silently does nothing.
				if ($tokens[$i]->isGivenKind([T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
					$name .= $tokens[$i]->getContent();
				}
			}

			if ($name === '') {
				return null;
			}

			$imports[] = [
				'start' => $index,
				'end' => $end,
				'name' => $name,
				'tier' => $this->tierOf($name),
				'text' => trim($tokens->generatePartialCode($index, $end)),
			];
		}

		return $imports;
	}

	private function tierOf(string $name): int
	{
		$name = ltrim($name, self::SEPARATOR);

		if (!str_contains($name, self::SEPARATOR)) {
			return 4;
		}

		if (str_starts_with($name, self::PROJECT_NAMESPACE . self::SEPARATOR)) {
			return 1;
		}

		$vendor = explode(self::SEPARATOR, self::PROJECT_NAMESPACE)[0];

		if (str_starts_with($name, $vendor . self::SEPARATOR)) {
			return 2;
		}

		return 3;
	}

	/**
	 * @param list<array{start: int, end: int, name: string, tier: int, text: string}> $imports
	 * @return list<Token>
	 */
	private function render(array $imports): array
	{
		$code = implode("\n", array_column($imports, 'text'));
		$generated = Tokens::fromCode("<?php\n" . $code);
		$items = [];

		foreach ($generated as $index => $token) {
			if ($index === 0) {
				continue;
			}

			$items[] = $token;
		}

		return $items;
	}
}
