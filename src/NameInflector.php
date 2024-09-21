<?php
declare(strict_types=1);

namespace Meraki\Form;

final class NameInflector
{
	private static array $cache = [];

	public function inflectOn(string $fqcn): string
	{
		if (!isset(self::$cache[$fqcn])) {
			$shortName = (new \ReflectionClass($fqcn))->getShortName();

			// Convert the short name to a more human-readable format
			self::$cache[$fqcn] = $this->toSnakeCase($shortName);
		}

		return self::$cache[$fqcn];
	}

	private function toSnakeCase(string $name): string
	{
		// A common convention in PHP is to use trailing underscores when a name conflicts with a reserved keyword.
		// Remove the trailing underscore as it is not necessary in the schema.
		$name = rtrim($name, '_');
		$snakeCase = preg_replace('/(?<!^)[A-Z]/', '_$0', $name);

		return strtolower($snakeCase);
	}
}
