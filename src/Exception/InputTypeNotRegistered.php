<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use RuntimeException;
use Throwable;

final class InputTypeNotRegistered extends RuntimeException implements Exception
{
	public function __construct(
		public readonly string $field,
		public readonly string $expectedType,
		?Throwable $previous = null
	) {
		$msg = sprintf(
			'Type "%s" for field "%s" is not registered.',
			$field,
			$expectedType
		);

		parent::__construct($msg, 0, $previous);
	}
}
