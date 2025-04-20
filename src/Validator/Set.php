<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\Validator;
use Meraki\Schema\Validator\CheckType;
use Meraki\Schema\Validator\Dependent;
use Meraki\Schema\Exception;
use RuntimeException;

/**
 * @internal
 */
final class Set
{
	private ?CheckType $typeValidator = null;

	/** @var list<Validator> */
	private array $baseValidators = [];

	/** @var list<Dependent> */
	private array $dependentValidators = [];

	/** @var array<class-string<Validator>, Validator> */
	private array $allValidatorsByClass = [];

	/**
	 * @throws Exception\ValidatorNotFound if the validator class does not exist
	 * @throws Exception\NotAValidator if the validator class does not implement Validator
	 */
	public function __construct(Validator ...$validators)
	{
		foreach ($validators as $validator) {
			$class = $validator::class;
			$this->allValidatorsByClass[$class] = $validator;

			if ($validator instanceof CheckType) {
				$this->typeValidator = $validator;
			} elseif ($validator instanceof Dependent) {
				$this->dependentValidators[] = $validator;
				$this->validateDependencies($validator);
			} else {
				$this->baseValidators[] = $validator;
			}
		}
	}

	public function hasTypeValidator(): bool
	{
		return isset($this->typeValidator);
	}

	/**
	 * @throws Exception\CheckTypeValidatorIsRequired if CheckType validator is not set
	 */
	public function assertCheckTypeValidatorExists(): void
	{
		if (!$this->hasTypeValidator()) {
			throw new Exception\CheckTypeValidatorIsRequired();
		}
	}

	/**
	 * @throws Exception\CheckTypeValidatorIsRequired if CheckType validator is not set
	 */
	public function getTypeValidator(): CheckType
	{
		$this->assertCheckTypeValidatorExists();

		return $this->typeValidator;
	}

	/** @return list<Validator> */
	public function baseValidators(): array
	{
		return $this->baseValidators;
	}

	/** @return list<Dependent> */
	public function dependentValidators(): array
	{
		return $this->dependentValidators;
	}

	/** @return list<Validator> */
	public function allExceptTypeValidator(): array
	{
		return array_merge($this->baseValidators, $this->dependentValidators);
	}

	/** @return list<Validator> */
	public function all(): array
	{
		return array_merge(
			$this->typeValidator ? [$this->typeValidator] : [],
			$this->baseValidators,
			$this->dependentValidators
		);
	}

	/**
	 * Returns a topologically sorted list of dependent validators.
	 *
	 * @throws RuntimeException if a circular dependency cycle is found.
	 * @return list<Dependent>
	 */
	public function sortDependentValidatorsByDependencies(): array
	{
		$sorted = [];
		$tempMark = [];
		$visited = [];

		$visit = function (Dependent $validator, array $path) use (&$visit, &$sorted, &$tempMark, &$visited): void {
			$class = $validator::class;

			if (isset($visited[$class])) {
				return;
			}

			if (isset($tempMark[$class])) {
				// Cycle detected — extract the cycle path
				$cycleStartIndex = array_search($class, $path, true);
				$cyclePath = array_slice($path, $cycleStartIndex);
				$cyclePath[] = $class; // to close the loop like [A, B, A]

				throw new Exception\CircularDependenciesFound($cyclePath);
			}

			$tempMark[$class] = true;
			$path[] = $class;

			foreach ($validator->dependsOn() as $dependency) {
				$depValidator = $this->allValidatorsByClass[$dependency];

				if ($depValidator instanceof Dependent) {
					$visit($depValidator, $path);
				}
			}

			unset($tempMark[$class]);
			$visited[$class] = true;
			$sorted[] = $validator;
		};

		foreach ($this->dependentValidators as $validator) {
			$visit($validator, []);
		}

		return $sorted;
	}

	private function validateDependencies(Dependent $validator): void
	{
		foreach ($validator->dependsOn() as $class) {
			if (!class_exists($class)) {
				throw new Exception\ValidatorNotFound($validator, $class);
			}

			if (!is_a($class, Validator::class, true)) {
				throw new Exception\NotAValidator($validator, $class);
			}
		}
	}
}
