<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\ScopeTarget;

final class ScopeResolutionResult
{
	public function __construct(
		public readonly ScopeTarget $target,
		public readonly mixed $value
	) {}
}
