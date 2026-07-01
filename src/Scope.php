<?php

declare(strict_types=1);

namespace Meraki\Schema;

use Countable;
use Iterator;
use OutOfBoundsException;
use Stringable;

final class Scope implements Stringable, Countable, Iterator
{
	public readonly string $path;

	private int $position;

	/** @var string[] Segments are stored verbatim — names are matched exactly. */
	public readonly array $segments;

	public function __construct(string $path, int $position = 0)
	{
		$path = rtrim($path, '/');

		if (!preg_match('/^#\//', $path)) {
			throw new \InvalidArgumentException("Invalid path '$path'. Must start with '#/'.");
		}

		// Names are NOT normalised: a consumer may name fields however they like and a
		// scope must reference them exactly (case-sensitive).
		$this->path = $path;
		$this->position = $position;
		$this->segments = explode('/', substr($path, 2)); // remove leading '#/'

		$this->assertPositionInBounds();
	}

	public function get(int $index): string
	{
		$this->assertPositionInBounds($index);

		return $this->segments[$index];
	}

	/**
	 * returns the segments as snake case
	 */
	public function current(): ?string
	{
		return $this->currentAsSnakeCase();
	}

	public function key(): mixed
	{
		return $this->position;
	}

	public function next(): void
	{
		$this->position++;
	}

	public function rewind(): void
	{
		$this->position = 0;
	}

	public function isAbsolute(): bool
	{
		return str_starts_with($this->path, '#/');
	}

	public function isRoot(): bool
	{
		return $this->path === '#/';
	}

	/** @deprecated names are matched verbatim now; this returns the exact segment. */
	public function currentAsCamelCase(): ?string
	{
		return $this->segments[$this->position] ?? null;
	}

	public function currentAsSnakeCase(): ?string
	{
		return $this->segments[$this->position] ?? null;
	}

	public function valid(): bool
	{
		return isset($this->segments[$this->position]);
	}

	/** @return string[] */
	public function remaining(): array
	{
		return $this->remainingAsSnakeCase();
	}

	/**
	 * @deprecated names are matched verbatim now; this returns the exact segments.
	 * @return string[]
	 */
	public function remainingAsCamelCase(): array
	{
		return array_slice($this->segments, $this->position);
	}

	/** @return string[] */
	public function remainingAsSnakeCase(): array
	{
		return array_slice($this->segments, $this->position);
	}

	public function hasRemainingSegments(): bool
	{
		return count($this->remainingAsSnakeCase()) > 0;
	}

	public function count(): int
	{
		return count($this->segments);
	}

	public function __toString(): string
	{
		return $this->path;
	}

	public function resolve(Facade $schema): mixed
	{
		if ($this->isRoot()) {
			return $schema;
		}

		$currentSegment = $this->currentAsSnakeCase();

		if ($currentSegment === null) {
			throw new OutOfBoundsException("No current segment at position {$this->position} in scope path '{$this->path}'");
		}

		return $schema->traverse($this);
	}

	private function assertPositionInBounds(int $index = -1): void
	{
		if ($index === -1) {
			$index = $this->position;
		}

		if ($index < 0 || $index >= count($this->segments)) {
			throw new OutOfBoundsException("Index $index is out of bounds for scope path '{$this->path}'");
		}
	}
}
