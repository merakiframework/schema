<?php
/**
 * Conditional rules: a field's requiredness depends on another field's value.
 *
 * Run: php examples/validate-with-rules.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use Meraki\Schema\Rule\Builder;
use Meraki\Schema\SchemaValidationResult;

function report(SchemaValidationResult $result): void
{
	if (!$result->anyFailed()) {
		echo "Valid.\n\n";

		return;
	}

	echo "Invalid:\n";

	foreach ($result->getFailed() as $fieldResult) {
		foreach ($fieldResult->getFailed() as $failure) {
			echo '  ' . $fieldResult->field->name . ' failed "' . $failure->name . "\"\n";
		}
	}

	echo "\n";
}

// `for()` declares the region once, so the address below is validated against
// Australian rules without repeating the country on the field.
$schema = (new Facade('booking'))->for('AU');

$schema->addUuidField('id')->restrictToVersion(7);
$schema->addNameField('full_name')->minLengthOf(1)->maxLengthOf(255);
$schema->addTextField('licence_number')->minLengthOf(1)->maxLengthOf(255);
$schema->addAddressField('pickup_location');

$schema->addBooleanField('has_log_book')
	->makeOptional()
	->prefill(true);

$schema->addDurationField('log_book_time_completed')
	->makeOptional()
	->minOf('PT0M')
	->maxOf('PT200H');

$schema->addEnumField(
	'transmission_type',
	['automatic', 'manual'],
	fn(Field\Enum $type): Field\Enum => $type->prefill('automatic')
);

// Keeping a log book means the completed time has to be supplied.
$schema->whenAllMatch(fn(Builder $rule): Builder =>
	$rule->whenEquals('#/fields/has_log_book/value', true)
		->thenRequire('#/fields/log_book_time_completed'));

$data = [
	'id' => '017f22e2-79b0-7cc3-98c4-dc0c0c07398f',
	'full_name' => 'Jane Doe',
	'licence_number' => 'QLD-1234567',
	'pickup_location' => [
		'line1' => '1 Queen St',
		'locality' => 'Brisbane',
		'administrative_area' => 'QLD',
		'postal_code' => '4000',
	],
	'has_log_book' => true,
	'transmission_type' => 'automatic',
];

// has_log_book is true, so the rule requires log_book_time_completed — which is
// missing, so validation fails despite every supplied value being well-formed.
report($schema->validate($data));

// Supply it and the same schema passes. (Validating twice also applies the rules
// twice, which is exactly the case Scope::resolve() has to survive.)
report($schema->validate(['log_book_time_completed' => 'PT10H'] + $data));
