<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use LogicException;

final class CircularDependenciesFound extends LogicException implements Exception
{
	/**
	 * @param list<class-string> $cycle
	 */
	public function __construct(array $cycle)
	{
		parent::__construct(sprintf('Circular dependency detected: %s.', implode(' → ', $cycle)));
	}
}
