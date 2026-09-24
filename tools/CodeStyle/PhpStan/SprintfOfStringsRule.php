<?php
declare(strict_types=1);

namespace Meraki\CodeStyle\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * `sprintf()` where every argument is already a string should be interpolation.
 *
 * `sprintf('Field "%s" is missing', $name)` says the same thing as `"Field {$name} is missing"`
 * with a format string, an argument list and a mapping between them for the reader to hold.
 * Where the values are strings and the placeholders are plain `%s`, the ceremony buys nothing.
 *
 * sprintf keeps its place for everything else: mixed types (`%d`, `%01.2f`), padding and width,
 * positional arguments, and values built by a call — `implode(', ', $names)` reads better as an
 * argument than wedged into a string.
 *
 * ## Why this is a PHPStan rule and not a fixer
 *
 * The deciding question is "is this argument a string", and no token-level tool can answer it:
 * `$this->name` might be a `string` or a `FieldName`. PHPStan already knows. It also means this
 * reports rather than rewrites, which is the right shape — the rewrite is mechanical but the
 * judgement about whether a given message reads better either way is not.
 *
 * @implements Rule<FuncCall>
 */
final class SprintfOfStringsRule implements Rule
{
	/**
	 * Characters that mean the string cannot move into double quotes unchanged.
	 *
	 * A `"` would need escaping, a `$` or `{` would start an interpolation that was never
	 * intended, and a backslash changes meaning between quote styles. This is the guard that
	 * correctly leaves `sprintf('A field named "%s" already exists.', $name)` alone: the
	 * double-quoted form needs two escapes and reads worse.
	 */
	private const CANNOT_BE_DOUBLE_QUOTED = '\\$"{';

	public function getNodeType(): string
	{
		return FuncCall::class;
	}

	/**
	 * @return list<\PHPStan\Rules\IdentifierRuleError>
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		if (!$node->name instanceof Name || $node->name->toLowerString() !== 'sprintf') {
			return [];
		}

		$args = $node->getArgs();

		if (count($args) < 2) {
			return [];
		}

		foreach ($args as $arg) {
			// A named or spread argument cannot be matched up with a placeholder by position.
			if ($arg->name !== null || $arg->unpack) {
				return [];
			}
		}

		$format = $args[0]->value;

		if (!$format instanceof String_) {
			return [];
		}

		if (!$this->formatIsPlainPlaceholders($format->value, count($args) - 1)) {
			return [];
		}

		foreach (array_slice($args, 1) as $arg) {
			if (!$this->isInterpolatable($arg->value)) {
				return [];
			}

			if (!$scope->getType($arg->value)->isString()->yes()) {
				return [];
			}
		}

		return [
			RuleErrorBuilder::message(
				'Every argument to this sprintf() is already a string, so interpolation says it '
				. 'with less ceremony.',
			)
				->identifier('meraki.sprintfOfStrings')
				->tip('Write the values into the string directly: "… {$name} …".')
				->build(),
		];
	}

	/**
	 * Whether the format uses nothing but `%s` and `%%`, exactly $expected times.
	 *
	 * Anything else — a width, a flag, a precision, a positional `%1$s`, or any conversion that
	 * is not `s` — is doing work that interpolation cannot do, so the call stays as it is.
	 */
	private function formatIsPlainPlaceholders(string $format, int $expected): bool
	{
		if (strpbrk($format, self::CANNOT_BE_DOUBLE_QUOTED) !== false) {
			return false;
		}

		$found = 0;
		$length = strlen($format);

		for ($i = 0; $i < $length; $i++) {
			if ($format[$i] !== '%') {
				continue;
			}

			$next = $format[$i + 1] ?? '';

			if ($next === '%') {
				$i++;

				continue;
			}

			if ($next !== 's') {
				return false;
			}

			$found++;
			$i++;
		}

		return $found === $expected;
	}

	/**
	 * Whether this expression can sit inside `{...}` unchanged.
	 *
	 * PHP's complex interpolation syntax accepts a variable, a property read and a method call,
	 * but the value has to read as a lookup rather than a computation. A call with arguments is
	 * excluded on purpose, and that is what implements the carve-out for building a string from
	 * other strings — `implode(', ', $names)` is never reported, with no special case for it.
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
