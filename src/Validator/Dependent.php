<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\Validator;

interface Dependent extends Validator
{
	/**
	 * Returns a list of other validators that this validator depends on.
	 *
	 * @template T of Validator
	 * @return list<class-string<T>> A list of fully qualified class names of dependent validators.
	 */
	public function dependsOn(): array;
}
