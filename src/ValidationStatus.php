<?php
declare(strict_types=1);

namespace Meraki\Schema;

enum ValidationStatus: int
{
	case Passed = 0;
	case Pending = 1;
	case Skipped = 2;
	case Failed = 3;
}
