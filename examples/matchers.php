<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Exception\InvalidRule;
use Meraki\Schema\Facade;
use Meraki\Schema\Rule\Draft;

// The twelve questions a rule can ask, and where each one's boundary sits.
//
// `branching-rules.php` covers what a rule *does* once its condition holds. This is about the
// condition: which matcher to reach for, and — the part that costs people an afternoon — whether
// the bound itself is included.

/** Builds a fresh schema, hands your fields to a callback, and adds whatever rule it returns. */
$build = static function (callable $rule): Facade {
	$schema = new Facade('matchers');

	$schema->add(
		$age = $schema->createNumberField('age')->makeOptional(),
		$starts = $schema->createDateField('starts')->makeOptional(),
		$notes = $schema->createTextField('notes')->makeOptional(),
		$country = $schema->createEnumField('country', ['AU', 'NZ', 'US'])->makeOptional(),
		$flag = $schema->createTextField('flag')->makeOptional(),
	);

	$schema->addRule($rule($age, $starts, $notes, $country)->then($flag->makeRequired()));

	return $schema;
};

/** Whether the rule fired, read off the effective definition it produced. */
$fired = static function (Facade $schema, array $submitted): string {
	return $schema->validate((object) $submitted)->forField('flag')->field->optional ? '-' : 'FIRED';
};

echo 'Inclusive or exclusive — the names carry it:' . PHP_EOL . PHP_EOL;
printf("  %-22s %-8s %-8s %-8s%s", '', 'age 17', 'age 18', 'age 19', PHP_EOL);

foreach (['isAtLeast', 'isGreaterThan', 'isAtMost', 'isLessThan'] as $matcher) {
	$schema = $build(static fn($age): Draft => $age->when()->{$matcher}(18));

	printf(
		"  %-22s %-8s %-8s %-8s%s",
		$matcher . '(18)',
		$fired($schema, ['age' => '17']),
		$fired($schema, ['age' => '18']),
		$fired($schema, ['age' => '19']),
		PHP_EOL,
	);
}

// Inclusive at both ends, and not by choice: `isBetween` holds an `isAtLeast` and an `isAtMost`
// and asks both, so it cannot drift from them.
$between = $build(static fn($age): Draft => $age->when()->isBetween(18, 65));

echo PHP_EOL . '  isBetween(18, 65)' . PHP_EOL;

foreach (['17', '18', '40', '65', '66'] as $age) {
	printf("    age %-4s %s%s", $age, $fired($between, ['age' => $age]), PHP_EOL);
}

echo PHP_EOL . 'The rest:' . PHP_EOL . PHP_EOL;

$cases = [
	// A date field resolves to a LocalDate, so the string the author wrote goes through the same
	// field the submitted value did. `until` on a Date field is exclusive for the same reason this
	// matcher is.
	["\$starts->when()->isLessThan('2030-06-01')", static fn($a, $starts): Draft => $starts->when()->isLessThan('2030-06-01'), ['starts' => '2030-05-31'], ['starts' => '2030-06-01']],

	// One question with a set of answers, rather than several conditions in an anyOf.
	["\$country->when()->isIn(['AU', 'NZ'])", static fn($a, $s, $n, $country): Draft => $country->when()->isIn(['AU', 'NZ']), ['country' => 'NZ'], ['country' => 'US']],

	// Case-sensitive. Case-insensitivity is `matches` with an `i` flag.
	["\$notes->when()->contains('urgent')", static fn($a, $s, $notes): Draft => $notes->when()->contains('urgent'), ['notes' => 'nothing urgent here'], ['notes' => 'URGENT']],

	// A PCRE with its delimiters — the same thing Text::matching() takes.
	["\$notes->when()->matches('/^INV-/')", static fn($a, $s, $notes): Draft => $notes->when()->matches('/^INV-/'), ['notes' => 'INV-42'], ['notes' => 'CR-42']],

	// Not the same as equals(null): a null expectation means the field's *authored default*.
	['$notes->when()->isEmpty()', static fn($a, $s, $notes): Draft => $notes->when()->isEmpty(), [], ['notes' => 'something']],
	['$notes->when()->isNotEmpty()', static fn($a, $s, $notes): Draft => $notes->when()->isNotEmpty(), ['notes' => 'something'], []],
];

foreach ($cases as [$label, $rule, $holds, $doesNot]) {
	$schema = $build($rule);

	printf("  %-42s %-8s %s%s", $label, $fired($schema, $holds), $fired($schema, $doesNot), PHP_EOL);
}

// The questions a field cannot answer are not there to ask. `$notes` is a Text field, so its
// matcher carries `contains` and `matches` and no ordering at all — this is a fatal, at the call
// site, and an editor greys it out before you run anything.
echo PHP_EOL . 'Not offered at all, because text has no order:' . PHP_EOL . PHP_EOL;

try {
	$build(static fn($a, $s, $notes): Draft => $notes->when()->isAtLeast(3));   // @phpstan-ignore method.notFound
} catch (Error $e) {
	echo '  ' . $e->getMessage() . PHP_EOL;
}

// The rest cannot be caught by a type, so they are caught when the rule is added — which is still
// before any request, and still not "the rule quietly never fired".
echo PHP_EOL . 'Refused at addRule(), rather than silently never firing:' . PHP_EOL . PHP_EOL;

$mistakes = [
	"\$age->when()->isAtLeast('eighteen')" => static fn($age): Draft => $age->when()->isAtLeast('eighteen'),
	'$country->when()->isIn([])' => static fn($a, $s, $n, $country): Draft => $country->when()->isIn([]),
	"\$notes->when()->matches('^INV-')" => static fn($a, $s, $notes): Draft => $notes->when()->matches('^INV-'),
];

foreach ($mistakes as $label => $mistake) {
	try {
		$build($mistake);
		printf("  %-38s accepted — which it should not have been%s", $label, PHP_EOL);
	} catch (InvalidRule $e) {
		printf("  %-38s %s%s", $label, $e->getMessage(), PHP_EOL);
	}
}
