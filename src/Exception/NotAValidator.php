<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use Meraki\Schema\Validator;
use Meraki\Schema\Validator\Dependent;
use InvalidArgumentException;

final class NotAValidator extends InvalidArgumentException implements Exception
{
	public function __construct(Dependent $validator, string $dependency)
	{
		$fqcn = $validator::class;
		$validatorFqcn = Validator::class;
		parent::__construct("Validator {$fqcn} declares a dependency on {$dependency}, but the dependency does not implement {$validatorFqcn}.");
	}
}
