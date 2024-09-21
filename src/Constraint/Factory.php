<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

use Meraki\Form\Constraint;
use Meraki\Form\NameInflector;

final class Factory
{
	private array $classes = [];

	private NameInflector $nameInflector;

	public function __construct(string $constraint, string ...$constraints)
	{
		$this->nameInflector = new NameInflector();

		$this->register($constraint, ...$constraints);
	}

	public static function useBundled(): self
	{
		return new self(
			Constraint\Max::class,
			Constraint\Min::class,
			Constraint\Required::class,
			// Constraint\Pattern::class,
		);
	}

	public function register(string $constraint, string ...$constraints): void
	{
		foreach ([$constraint, ...$constraints] as $constraint) {
			$name = $this->nameInflector->inflectOn($constraint);

			if ($this->isRegistered($constraint)) {
				throw new \InvalidArgumentException('"'.$name.'" is already registered to '.$this->getClassForName($name).'.');
			}

			$this->classes[$constraint] = $name;
		}
	}

	private function getClassForName(string $name): string
	{
		foreach ($this->classes as $fqcn => $registeredName) {
			if ($registeredName === $name) {
				return $fqcn;
			}
		}

		throw new \InvalidArgumentException('No constraint registered for "'.$name.'".');
	}

	public function isRegistered(string $constraint): bool
	{
		return isset($this->classes[$constraint]);
	}

	public function canCreate(string $name): bool
	{
		foreach ($this->classes as $fqcn => $registeredName) {
			if ($registeredName === $name) {
				return true;
			}
		}

		return false;
	}

	public function create(string $name, mixed $value): Constraint
	{
		foreach ($this->classes as $fqcn => $registeredName) {
			if ($registeredName === $name) {
				return new $fqcn($value);
			}
		}

		throw new \InvalidArgumentException('No constraint registered for "'.$name.'".');
	}
}
