<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use Meraki\Schema\Validator\Dependent;
use InvalidArgumentException;

final class ValidatorNotFound extends InvalidArgumentException implements Exception
{
	public function __construct(Dependent $validator, string $dependency)
	{
		$fqcn = $validator::class;
		parent::__construct("Validator {$fqcn} declares a dependency on {$dependency}, but the dependency could not be found.");
	}
}
