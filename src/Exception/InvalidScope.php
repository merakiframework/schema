<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use InvalidArgumentException;
use Throwable;

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
}
