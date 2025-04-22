<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\Type;
use Meraki\Schema\Validator\CheckType;

final class File implements Type
{
	public string $name = 'file';

	public function accepts(mixed $value): bool
	{
		return is_string($value);
	}

	public function getValidator(): CheckType
	{
		return new CheckType($this);
	}
}
