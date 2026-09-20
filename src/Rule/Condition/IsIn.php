<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use InvalidArgumentException;
use Meraki\Schema\Comparison\Values;
use Meraki\Schema\Facade;
use Meraki\Schema\Scope;
use Meraki\Schema\ScopeResolver;

/**
 * Holds when the value is any one of those given.
 *
 * `equals` widened from one operand to several, and nothing more:
 *
 *     $schema->when($country)->isIn(['AU', 'NZ'])->thenRequire($gstNumber);
 *
 * Every candidate is read the way {@see Comparison} describes — through the field the value came
 * from — so a list of date strings is compared as dates, and any one of them may be a
 * {@see Scope} naming another field.
 *
 * ### Why not `anyOf()` of several `equals`
 *
 * Because that is a different shape on the wire and a worse one in the source. `anyOf` is for
 * combining *unrelated* questions; this is one question with a set of answers, and writing it as
 * five conditions means five places to edit a list. It also serialises as one condition carrying
 * a list, which is what a client-side translation wants to read.
 *
 * ### An empty list is refused
 *
 * `isIn([])` can never hold, so it is a rule that does nothing while looking like a rule that does
 * something — exactly the failure {@see Comparison} was written to remove. Refused where it is
 * written rather than silently never firing.
 */
final class IsIn extends Comparison
{
	/**
	 * @param list<mixed> $candidates
	 * @throws InvalidArgumentException if the list is empty
	 */
	public function __construct(Scope|string $target, array $candidates)
	{
		if ($candidates === []) {
			throw new InvalidArgumentException(
				'isIn() was given no values to match against, so the rule could never fire. '
				. 'Give it at least one, or remove the rule.',
			);
		}

		parent::__construct($target, array_values($candidates));
	}

	/**
	 * Every value this accepts, under a name that says what they are.
	 *
	 * @var list<mixed>
	 */
	public array $candidates {
		get {
			assert(is_array($this->expected));

			return array_values($this->expected);
		}
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function matches(array $data, Facade $schema): bool
	{
		$resolver = new ScopeResolver($schema, $data);
		$value = $resolver->resolve($this->scope);

		foreach ($this->candidates as $candidate) {
			if (Values::same($value, $this->readExpectation($candidate, $schema, $resolver))) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Each candidate separately, so the readability check and the scope collection see through the
	 * list rather than at it — the field is asked whether it can hold `'AU'`, never whether it can
	 * hold an array.
	 *
	 * @return list<mixed>
	 */
	protected function expectations(): array
	{
		return $this->candidates;
	}
}
