<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Field\Text;
use Meraki\Schema\Property\Name;

// Fields can be built and validated on their own, without a Facade.
$username = new Text(new Name('username'));

$username->matches('/^[a-zA-Z0-9_]+$/')
	->minLengthOf(3);

// validate() is a pure query: the value goes in as an argument and the result comes back,
// so nothing is stored on the field and the same field can serve two requests at once.
$result = $username->validate('ab'); // too short -> the "minLength" constraint will fail

echo 'Field status: ' . $result->status->name . PHP_EOL;

foreach ($result->getFailed() as $failure) {
	echo "  failed:  {$failure->name}" . PHP_EOL;
}

foreach ($result->getSkipped() as $skipped) {
	echo "  skipped: {$skipped->name}" . PHP_EOL;
}
