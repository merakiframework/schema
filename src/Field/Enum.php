<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Enum\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;

/**
 * @template T of scalar
 * @extends AtomicField<string|null>
 */
final readonly class Enum extends AtomicField
{
	public function __construct(
		public FieldName $name,
		/** @param list<T> $cases*/
		public array $cases,
	) {
		parent::__construct();

		$this->validateCases($this->cases);
		$this->constraints = $this->defineConstraints();
	}

	private function validateCases(array $cases): void
	{
		if (empty($cases)) {
			throw new \InvalidArgumentException('Enum cases cannot be empty.');
		}

		$type = null;

		foreach ($cases as $case) {
			if (!is_scalar($case)) {
				throw new \InvalidArgumentException('Enum cases must be scalar values.');
			}

			if ($type === null) {
				$type = gettype($case);
			} elseif (gettype($case) !== $type) {
				throw new \InvalidArgumentException('Enum cases must be of the same type.');
			}
		}
	}

	/**
	 * Reads any scalar. Whether it is one of *this* field's cases is a constraint, not the shape.
	 *
	 * The distinction matters to whoever has to write the message. Shape failure means "that is
	 * not the kind of thing this field holds" and has nothing to interpolate; `allowedCases` means
	 * "that is not one of these" and carries the list. This field used to decide membership here,
	 * so a rejected case came back as *unreadable* with no constraint name and no bound, and a
	 * renderer wanting "must be one of: free, pro, team" had to reach past the result to the field
	 * — exactly the pattern the constraint rewrite exists to end.
	 *
	 * It is the one field where membership *is* the whole point, and it was the one field that
	 * could not express it.
	 */
	protected function parse(mixed $value): ?Value
	{
		return is_scalar($value) ? new Value($value) : null;
	}

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			// The bound is the list itself, which is what a message interpolates. Compare
			// {@see Uuid}'s `allowedVersions`, which is the same shape of question.
			new Constraint('allowedCases', $this->isAnAllowedCase(...), array_map(strval(...), $this->cases)),
		);
	}

	private function isAnAllowedCase(Value $parsed): bool
	{
		// Strict, including type. A field's cases are all of one type — see validateCases() — so
		// `1` and `'1'` are never both cases, and treating them as the same would only hide a
		// mistake somewhere upstream.
		return in_array($parsed->case, $this->cases, true);
	}
}
