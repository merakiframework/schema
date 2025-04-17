<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

interface Dependent extends Validator
{
	/**
	 * Returns a list of other validators that this validator depends on.
	 *
	 * @return list<string> A list of fully qualified class names of dependent validators.
	 */
	public function dependsOn(): array;
}
