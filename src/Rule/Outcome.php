<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Facade;
use Meraki\Schema\Scope;

/**
 * @phpstan-type SerializedOutcome = object{
 * 	action: string,
 * }
 * @template T of SerializedOutcome
 */
interface Outcome
{
	public function apply(Facade $schema): void;

	public function getScope(): Scope;
}
