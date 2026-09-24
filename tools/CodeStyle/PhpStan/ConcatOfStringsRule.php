<?php
declare(strict_types=1);

namespace Meraki\CodeStyle\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Gluing a value into text with `.` should be interpolation.
 *
 * `$this->prefix() . '/' . $this->property` reads as three things joined;
 * `"{$this->prefix()}/{$this->property}"` reads as the path it is. The dot is doing
 * punctuation's job, and the reader has to reassemble the result in their head.
 *
 * ## What this deliberately does not report
 *
 * **Leading-dot continuation.** A long message wrapped across lines —
 *
 *     'A rule must say what happens: attach an outcome with then() or else() before '
 *     . 'adding it to a schema.'
 *
 * — is line wrapping, not concatenation. It produces one unbroken string, which is the whole
 * point, and rewriting it as a heredoc would put real newlines into an exception message. Any
 * chain spanning more than one line is skipped.
 *
 * **Anything a double-quoted string cannot hold unchanged.** `Field::class . '\\' . $kind`
 * cannot interpolate a class constant at all; `'~^(?:' . $pattern . ')$~'` is a regex whose
 * closing `$~` would have to be escaped; `'$' . implode(', $', $available)` puts a literal `$`
 * against a brace. Each is excluded by a guard rather than a special case.
 *
 * **`.=`** — `AssignOp\Concat` is a different node, and the five sites in Message\Mf2\Formatter
 * are a character-by-character parser loop rather than concatenation-as-glue.
 *
 * ## A known limitation: two-operand chains are not reported
 *
 * Concatenation is left-associative, so `$a . '/' . $b` parses as `Concat(Concat($a, '/'), $b)`
 * and this rule is called for both nodes. Only the outer one can see the whole chain — checking
 * the inner one in isolation is how an earlier version of this rule reported the regex above,
 * having validated `'~^(?:' . $pattern` and never seen `')$~'`.
 *
 * Reporting only where the left operand is itself a `Concat` fixes that: it fires once, at the
 * outermost node, with every operand in view. The cost is that a two-operand chain never
 * matches, because its single node is locally indistinguishable from the base of a longer one
 * and PHPStan exposes no parent pointer to tell them apart. Erring towards silence is the right
 * side to err on for a rule whose whole job is advisory.
 *
 * @implements Rule<Concat>
 */
final class ConcatOfStringsRule implements Rule
{
	/** @see SprintfOfStringsRule::CANNOT_BE_DOUBLE_QUOTED */
	private const CANNOT_BE_DOUBLE_QUOTED = '\\$"{';

	public function getNodeType(): string
	{
		return Concat::class;
	}

	/**
	 * @return list<\PHPStan\Rules\IdentifierRuleError>
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		// The outermost node of the chain, and the only one that can see all of it.
		if (!$node->left instanceof Concat) {
			return [];
		}

		$operands = $this->flatten($node);

		// More than one line means this is a wrapped string literal, which is house style.
		if ($node->getStartLine() !== $node->getEndLine()) {
			return [];
		}

		$literals = 0;
		$values = 0;

		foreach ($operands as $operand) {
			if ($operand instanceof String_) {
				if (strpbrk($operand->value, self::CANNOT_BE_DOUBLE_QUOTED) !== false) {
					return [];
				}

				$literals++;

				continue;
			}

			if (!$this->isInterpolatable($operand)) {
				return [];
			}

			if (!$scope->getType($operand)->isString()->yes()) {
				return [];
			}

			$values++;
		}

		// `'a' . 'b'` is a different smell, and `$a . $b` has no punctuation to speak of. This is
		// about a value glued into text.
		if ($literals === 0 || $values === 0) {
			return [];
		}

		return [
			RuleErrorBuilder::message(
				'This joins strings with `.`, which interpolation says directly.',
			)
				->identifier('meraki.concatOfStrings')
				->tip('Write the values into the string: "…{$value}…".')
				->build(),
		];
	}

	/**
	 * Every operand of the chain, left to right.
	 *
	 * @return list<Node\Expr>
	 */
	private function flatten(Concat $node): array
	{
		$left = $node->left instanceof Concat ? $this->flatten($node->left) : [$node->left];
		$right = $node->right instanceof Concat ? $this->flatten($node->right) : [$node->right];

		return array_merge($left, $right);
	}

	/**
	 * Whether this expression can sit inside `{...}` unchanged.
	 *
	 * Same shape as {@see SprintfOfStringsRule::isInterpolatable()}: a lookup, never a
	 * computation. A call with arguments is excluded, which is what keeps
	 * `implode(', ', $names)` out of the report.
	 */
	private function isInterpolatable(Node $expr): bool
	{
		if ($expr instanceof Variable) {
			return is_string($expr->name);
		}

		if ($expr instanceof PropertyFetch) {
			return $expr->name instanceof Identifier && $this->isInterpolatable($expr->var);
		}

		if ($expr instanceof MethodCall) {
			return $expr->name instanceof Identifier
				&& $expr->getArgs() === []
				&& $this->isInterpolatable($expr->var);
		}

		return false;
	}
}
