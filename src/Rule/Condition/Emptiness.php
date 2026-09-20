<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Countable;
use Meraki\Schema\Facade;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Scope;
use Meraki\Schema\ScopeResolver;
use Stringable;

/**
 * A condition about whether a scope points at anything at all.
 *
 * The one comparison with no operand, which is why it does not extend {@see Comparison}: there is
 * no expectation to read through a field, nothing for the readability check to look at, and no
 * second scope. Modelling it as `equals(null)` was the alternative and it is not the same
 * question — `null` as an expectation means "the field's authored default", deliberately, so
 * `equals(null)` on a field with a default asks something else entirely.
 *
 * ### What counts as empty
 *
 * Three things, and each is empty in the only sense that value has:
 *
 * | Resolved value | Empty when |
 * | --- | --- |
 * | `null` | always — nothing was submitted, or nothing readable was |
 * | a {@see \Countable} value, such as a collection | it holds no rows |
 * | a {@see \Stringable} value, or a part that resolved to a string | the string is `''` |
 *
 * Anything else is not empty. A boolean `false` is a submitted answer, not an absent one, and a
 * number `0` is a quantity — treating either as empty is the mistake `empty()` makes in PHP and
 * the reason this does not use it.
 */
abstract class Emptiness implements Condition
{
	public readonly Scope $scope;

	/**
	 * The scope in its string form, which is what `meraki/schema-json` writes to disk.
	 */
	public string $target {
		get => (string) $this->scope;
	}

	public function __construct(Scope|string $target)
	{
		$this->scope = $target instanceof Scope ? $target : Scope::parse($target);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	final protected function pointsAtNothing(array $data, Facade $schema): bool
	{
		$value = (new ScopeResolver($schema, $data))->resolve($this->scope);

		return match (true) {
			$value === null => true,
			$value instanceof Countable => count($value) === 0,
			$value instanceof Stringable, is_string($value) => (string) $value === '',
			default => false,
		};
	}

	/**
	 * @return array<Scope>
	 */
	public function getScopes(): array
	{
		return [$this->scope];
	}
}
