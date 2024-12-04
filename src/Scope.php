<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\SchemaFacade;

class Scope
{
	public function __construct(
		private string $path
	) {
		// Validate the path to ensure it's a proper reference format
		if (!preg_match('/^#\/|^\//', $this->path)) {
			throw new \InvalidArgumentException('Invalid reference: ' . $this->path);
		}
	}

	public function getPath(): string
	{
		return $this->path;
	}

	/**
	 * Resolve the reference against the given schema.
	 */
	public function resolve(object|array $schema): mixed
	{
		$path = $this->path;

		if ($path === '#') {
			return $schema;
		}

		if (str_starts_with($path, '#/')) {
			return $this->resolveAbsolute($schema);
		}

		throw new \RuntimeException('Referencing by URL is not currently supported: ' . $path);
	}

	public function resolveAbsolute(SchemaFacade $schema): mixed
	{
		$path = $this->path;

		if ($path === '#') {
			return $schema;
		}

		$parts = explode('/', ltrim($path, '#/'));
		$target = $schema;
		$targetIsField = false;


		foreach ($parts as $part) {
			// if ($targetIsField) {
			// 	if ($part === 'value') {
			// 		return $target->attributes->findByName($part)?->defaultsTo($target->value);
			// 	}
			// 	return $target->attributes->findByName($part);
			// }

			if (is_object($target) && isset($target->$part)) {
				$target = $target->$part;
				if ($target instanceof Field) {
					$targetIsField = true;
				}
				continue;
			}

			if (is_array($target) && isset($target[$part])) {
				$target = $target[$part];
				continue;
			}

			throw new \InvalidArgumentException('Invalid reference: ' . $path);
		}

		// var_dump($path, $target);die;

		return $target;
	}

	/**
	 * The same as resolve() but will backtrack up the path until it finds a valid reference.
	 */
	public function resolveWithBackTracking(SchemaFacade $schema): mixed
	{
		$path = $this->path;

		if ($path === '#') {
			return $schema;
		}

		$parts = explode('/', ltrim($path, '#/'));
		$target = $schema;
		$targets = [];

		foreach ($parts as $part) {
			if (is_object($target) && isset($target->$part)) {
				$targets[] = $target->$part;
				$target = $target->$part;
				continue;
			}

			if (is_array($target) && isset($target[$part])) {
				$targets[] = $target[$part];
				$target = $target[$part];
				continue;
			}

			$targets[] = $target;
			break;
		}

		array_pop($targets);

		$target = $targets[count($targets)-1];

		if ($target) {
			return $target;
		}

		throw new \InvalidArgumentException('Invalid reference: ' . $path);
	}

	/**
	 * Return the last segment of the path.
	 */
	public function getLastSegment(): string
	{
		$parts = explode('/', ltrim($this->path, '#/'));

		return array_pop($parts);
	}
}
