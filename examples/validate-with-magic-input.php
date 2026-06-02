<?php
/**
 * Reproduces a bug where Facade::validate() reports failures for *valid* input.
 *
 * Root cause: Facade::input() reads object input via extractData(), which uses
 * get_object_vars(). That only returns plain public declared properties -- it
 * does NOT trigger __get() / accessor methods. So an Input value object that
 * exposes its data through __get() yields an empty array, every field is fed
 * null, and a required field (e.g. "name") fails on otherwise-valid input.
 *
 * The manual loop (getResults() below) reads each value with
 * $input->{$field->name}, which DOES trigger __get(), so it works -- this is the
 * workaround currently used in the consuming project.
 *
 * Run: php examples/validate-with-magic-input.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Facade;
use Meraki\Schema\SchemaValidationResult;

/**
 * A typical value object: data is held privately and exposed via __get().
 * `$input->name` works (triggers __get), but get_object_vars($input) returns []
 * from outside this class -- which is exactly what Facade::extractData() does.
 */
final class Input
{
    public function __construct(private array $data)
    {
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }
}

// The exact schema shape the bug was reported against (date bounds hardcoded so
// this file runs without the Clock/TimeZone dependencies from the other project).
function makeSchema(): Facade
{
    $schema = new Facade('create-person');
    $schema->addNameField('name')->minLengthOf(1)->maxLengthOf(255);
    $schema->addDateField('dateOfBirth')->from('1900-01-01')->to('2010-01-01')->makeOptional();

    return $schema;
}

// The workaround from the consuming project: read each field directly.
function getResults(Facade $schema, Input $input): SchemaValidationResult
{
    // library has bug, we need to do manual validation for now
    $results = new SchemaValidationResult();
    foreach ($schema->fields as $field) {
        $field->input($input->{$field->name});
        $results = $results->add($field->validate());
    }

    return $results;
}

function printFailures(SchemaValidationResult $result): void
{
    foreach ($result->getFailed() as $fieldResult) {
        foreach ($fieldResult->getFailed() as $failure) {
            echo '  "' . $failure->name . '" failed for field "' . $fieldResult->field->name . '"' . PHP_EOL;
        }
    }
}

// Only "name" is supplied. "dateOfBirth" is optional and omitted on purpose, so
// the manual loop correctly skips it. (Supplying a date here would trip a
// SEPARATE library quirk: Date's default interval is Period::ofDays(1), so any
// date other than `from` or `from`+1 day fails the "interval" constraint.)
$input = new Input([
    'name' => 'John Smith',
]);

echo '== Why it breaks: get_object_vars() can\'t see __get data ==' . '<br>';
echo 'get_object_vars($input): ';
var_export(get_object_vars($input));            // []  <- root cause
echo '<br>';
echo 'direct access $input->name: ' . $input->name . '<br>' . '<br>';

echo '== Path 1: Facade::validate() (buggy) ==' . '<br>';
$viaFacade = makeSchema()->validate($input);
echo 'any failures? ' . var_export($viaFacade->failed(), true) . '<br>';   // true (BUG: valid input)
printFailures($viaFacade);
echo '<br><br>';

echo '== Path 2: manual loop workaround ==' . '<br>';
$viaWorkaround = getResults(makeSchema(), $input);
echo 'any failures? ' . var_export($viaWorkaround->failed(), true) . '<br>'; // false (name passed, dateOfBirth skipped)
printFailures($viaWorkaround);
