<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Scope;
use Meraki\Schema\ScopeResolutionResult;
use InvalidArgumentException;

interface ScopeTarget
{
	/**
	 * Resolve a path relative to this target.
	 * @throws \InvalidArgumentException If the path is invalid.
	 */
	public function traverse(Scope $scope): ScopeResolutionResult;
}
