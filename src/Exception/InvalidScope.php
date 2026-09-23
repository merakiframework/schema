<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use InvalidArgumentException;

final class InvalidScope extends InvalidArgumentException implements Exception
{
	public static function prefixIsMissing(string $path): self
	{
		return new self(sprintf('"%s" is not a scope path: it must start with "#/".', $path));
	}

	public static function notAnAddressableElement(string $path, string ...$addressableElements): self
	{
		return new self(sprintf(
			'"%s" does not address anything: the only addressable elements are: "%s".',
			$path,
			implode(', ', $addressableElements)
		));
	}

	public static function fieldNameIsMissing(string $path): self
	{
		return new self(sprintf('"%s" is missing a field name.', $path));
	}

	public static function formatIsIncorrectForTargetingAValuePart(string $path, string $element, string $fieldName, string $valueSegmentName): self
	{
		return new self(sprintf('"%s" addresses a part, which only a value has. Write it as "#/%s/%s/%s/<part>".', $path, $element, $fieldName, $valueSegmentName));
	}

	public static function tooManySegments(string $path): self
	{
		return new self(sprintf('"%s" has more segments than a scope can address. A part of a value is as deep as this goes; collection items are not addressable.', $path));
	}

	public static function propertyIsMissing(): self
	{
		return new self('A property scope must name a property.');
	}

	public static function partIsMissing(): self
	{
		return new self('A part scope must name a part.');
	}

	public static function valueIsNotAProperty(string $path, string $useInstead): self
	{
		return new self(sprintf(
			'"%s" addresses a submitted value, not a definition property. Use %s.',
			$path,
			$useInstead,
		));
	}

	/**
	 * Raised where the rule is *written*, which is the whole reason a part name is checked
	 * against the value class rather than against a value: `null` is a legitimate answer for a
	 * part nobody filled in, so a mistyped one would otherwise resolve to it on every request
	 * forever.
	 */
	public static function fieldHoldsNoParts(string $field, string $part): self
	{
		return new self(sprintf(
			'"%s" holds one value rather than named parts, so it has no "%s" to address.',
			$field,
			$part,
		));
	}

	/**
	 * @param list<string> $parts
	 */
	public static function fieldHasNoSuchPart(string $field, string $part, array $parts): self
	{
		return new self(sprintf(
			'"%s" has no part "%s". It has: %s.',
			$field,
			$part,
			implode(', ', $parts),
		));
	}

	public static function fieldHasNoSuchProperty(string $field, string $property): self
	{
		return new self(sprintf('No property "%s" on field "%s".', $property, $field));
	}

	/**
	 * Asked of a message set rather than of a scope, and here anyway: "no messages" is a
	 * legitimate answer for a part that is fine, so a typo that returned it would be invisible.
	 *
	 * @param list<string> $parts
	 */
	public static function noSuchPartToReport(string $part, array $parts): self
	{
		return new self(sprintf(
			'There is no part "%s" to have messages for. There is: %s.',
			$part,
			implode(', ', $parts),
		));
	}
}
