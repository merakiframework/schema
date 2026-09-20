<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Facade;

// The twelve questions a rule can ask, and where each one's boundary sits.
//
// `branching-rules.php` covers what a rule *does* once its condition holds. This is about the
// condition: which matcher to reach for, and — the part that costs people an afternoon — whether
// the bound itself is included.

/** Builds a fresh schema whose `flag` is optional until a rule says otherwise. */
$build = static function (callable $rule): Facade {
	$schema = new Facade('matchers');

	$schema->add(
		$schema->createNumberField('age')->makeOptional(),
		$schema->createDateField('starts')->makeOptional(),
		$schema->createTextField('notes')->makeOptional(),
		$schema->createEnumField('country', ['AU', 'NZ', 'US'])->makeOptional(),
		$schema->createTextField('flag')->makeOptional(),
	);

	$schema->addRule($rule($schema));

	return $schema;
};

/** Whether the rule fired, read off the effective definition it produced. */
$fired = static function (Facade $schema, array $submitted): string {
	return $schema->validate((object) $submitted)->forField('flag')->field->optional ? '-' : 'FIRED';
};

echo 'Inclusive or exclusive — the names carry it:' . PHP_EOL . PHP_EOL;
printf("  %-22s %-8s %-8s %-8s%s", '', 'age 17', 'age 18', 'age 19', PHP_EOL);

foreach (['isAtLeast', 'isGreaterThan', 'isAtMost', 'isLessThan'] as $matcher) {
	$schema = $build(static fn(Facade $s) => $s->when('age')->{$matcher}(18)->thenRequire('flag'));

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
$between = $build(static fn(Facade $s) => $s->when('age')->isBetween(18, 65)->thenRequire('flag'));

echo PHP_EOL . '  isBetween(18, 65)' . PHP_EOL;

foreach (['17', '18', '40', '65', '66'] as $age) {
	printf("    age %-4s %s%s", $age, $fired($between, ['age' => $age]), PHP_EOL);
}

echo PHP_EOL . 'The rest:' . PHP_EOL . PHP_EOL;

$cases = [
	// A date field resolves to a LocalDate, so the string the author wrote goes through the same
	// field the submitted value did. `until` on a Date field is exclusive for the same reason this
	// matcher is.
	["when('starts')->isLessThan('2030-06-01')", static fn(Facade $s) => $s->when('starts')->isLessThan('2030-06-01'), ['starts' => '2030-05-31'], ['starts' => '2030-06-01']],

	// One question with a set of answers, rather than four conditions in an anyOf.
	["when('country')->isIn(['AU', 'NZ'])", static fn(Facade $s) => $s->when('country')->isIn(['AU', 'NZ']), ['country' => 'NZ'], ['country' => 'US']],

	// Case-sensitive. Case-insensitivity is `matches` with an `i` flag.
	["when('notes')->contains('urgent')", static fn(Facade $s) => $s->when('notes')->contains('urgent'), ['notes' => 'nothing urgent here'], ['notes' => 'URGENT']],

	// A PCRE with its delimiters — the same thing Text::matching() takes.
	["when('notes')->matches('/^INV-/')", static fn(Facade $s) => $s->when('notes')->matches('/^INV-/'), ['notes' => 'INV-42'], ['notes' => 'CR-42']],

	// Not the same as equals(null): a null expectation means the field's *authored default*.
	["when('notes')->isEmpty()", static fn(Facade $s) => $s->when('notes')->isEmpty(), [], ['notes' => 'something']],
	["when('notes')->isNotEmpty()", static fn(Facade $s) => $s->when('notes')->isNotEmpty(), ['notes' => 'something'], []],
];

foreach ($cases as [$label, $rule, $holds, $doesNot]) {
	$schema = $build(static fn(Facade $s) => $rule($s)->thenRequire('flag'));

	printf("  %-42s %-8s %s%s", $label, $fired($schema, $holds), $fired($schema, $doesNot), PHP_EOL);
}

// A rule that could never hold is refused where it is written, not left to never fire. That is the
// whole reason the matchers know which fields have an order: `isAtLeast` on text reads plausibly
// and is dead.
echo PHP_EOL . 'Refused at addRule(), rather than silently never firing:' . PHP_EOL . PHP_EOL;

$mistakes = [
	"when('notes')->isAtLeast(3)" => static fn(Facade $s) => $s->when('notes')->isAtLeast(3),
	"when('age')->isAtLeast('eighteen')" => static fn(Facade $s) => $s->when('age')->isAtLeast('eighteen'),
	"when('country')->isIn([])" => static fn(Facade $s) => $s->when('country')->isIn([]),
	"when('notes')->matches('^INV-')" => static fn(Facade $s) => $s->when('notes')->matches('^INV-'),
];

foreach ($mistakes as $label => $mistake) {
	try {
		$build(static fn(Facade $s) => $mistake($s)->thenRequire('flag'));
		printf("  %-42s accepted — which it should not have been%s", $label, PHP_EOL);
	} catch (InvalidArgumentException $e) {
		printf("  %-42s %s%s", $label, $e->getMessage(), PHP_EOL);
	}
}
