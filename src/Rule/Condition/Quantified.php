<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Exception\InvalidRule;
use Meraki\Schema\Field;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Quantifier;
use Meraki\Schema\Rule\Scoped;
use Meraki\Schema\Scope;
use Meraki\Schema\ScopeResolver;

/**
 * One ordinary condition, asked of every row of a collection, and folded into a single answer.
 *
 *     $lines->whereAny('sku')->equals('HAZMAT')->then($declaration->makeRequired());
 *     $lines->whereEvery('type')->equals('digital')->then($shipping->makeOptional());
 *
 * ### Why a wrapper rather than a matcher that knows about lists
 *
 * A column scope resolves to one value per row, so a bare comparison against it compares a *list*
 * to a scalar and is false for every request there will ever be — a rule that is accepted, never
 * fires, and says nothing about why. Rather than teach twelve matchers what a list is, the inner
 * condition is re-rooted at each row through {@see Scope::rootedAt()} and asked exactly as it would
 * be of a top-level field. So a quantified `isGreaterThan` is the same `isGreaterThan`, and a
 * matcher added later is quantifiable for free.
 *
 * ### The quantifier is not part of the address
 *
 * `#/fields/lines/value/*​/sku/value` names the same values whether one row or all of them must
 * answer yes, so the quantifier serialises on the condition — `any_equals`, `every_equals` — and
 * the scope format is untouched by it. Which also means quantifiers could have arrived later
 * without changing what a stored scope means.
 */
final class Quantified implements Condition
{
	/** The rows being ranged over. Held because it is what every step below needs. */
	public readonly Scope\Column $column;

	/**
	 * @throws InvalidRule if the inner condition does not ask about a column
	 */
	public function __construct(
		public readonly Quantifier $quantifier,
		public readonly Condition&Scoped $of,
	) {
		$in = $this->of->scope->in;

		if (!$in instanceof Scope\Column) {
			throw InvalidRule::quantifiesSomethingThatIsNotAColumn((string) $this->of->scope);
		}

		$this->column = $in;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function matches(array $data, Field\Set $fields): bool
	{
		$resolver = new ScopeResolver($fields, $data);

		// Checked even when there are no rows, so a column naming a field the template does not
		// have still fails rather than quietly answering the empty-collection case.
		$resolver->fieldFor($this->of->scope);

		$rows = $resolver->rowNamesIn($this->column->field);

		if ($rows === []) {
			return $this->quantifier->ofNothing();
		}

		foreach ($rows as $row) {
			$matched = $this->at($row)->matches($data, $fields);

			if ($this->quantifier->settledBy($matched)) {
				return $matched;
			}
		}

		// Nothing settled it, so every row agreed with whatever the quantifier was not looking for.
		return !$this->quantifier->settledBy(true);
	}

	/**
	 * Every scope this mentions — the inner condition's own, including the other half of a
	 * cross-field comparison, so {@see \Meraki\Schema\Facade::addRule()} still checks them all.
	 *
	 * @return list<Scope>
	 */
	public function getScopes(): array
	{
		return array_values($this->of->getScopes());
	}

	/**
	 * The same condition, asked of one row.
	 *
	 * A clone rather than a rebuilt condition, because the operands differ by subclass — one for
	 * `equals`, two for `isBetween`, a list for `isIn` — and only the scope is changing.
	 */
	private function at(string $row): Condition
	{
		return $this->of->about(
			$this->of->scope->rootedAt(
				new Scope\Row($this->column->field, $row, $this->column->addresses()),
			),
		);
	}
}
