<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use RuntimeException;
use Throwable;

final class InputTypeConversionFailed extends RuntimeException implements Exception
{
	public function __construct(
		public readonly string $field,
		public readonly string $expectedType,
		public readonly mixed $value,
		?Throwable $previous = null
	) {
		$msg = sprintf(
			'Failed to convert field "%s" to type "%s". Value: %s',
			$field,
			$expectedType,
			var_export($value, true)
		);

		parent::__construct($msg, 0, $previous);
	}
}
