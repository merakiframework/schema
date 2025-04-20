<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use Meraki\Schema\Validator\CheckType;
use \InvalidArgumentException;

final class CheckTypeValidatorIsRequired extends InvalidArgumentException implements Exception
{
	public function __construct()
	{
		$fqcn = CheckType::class;
		parent::__construct("Validator '{$fqcn}' is required.");
	}
}
