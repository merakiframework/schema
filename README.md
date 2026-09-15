# meraki/schema

[![Tests](https://github.com/merakiframework/schema/actions/workflows/tests.yml/badge.svg)](https://github.com/merakiframework/schema/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/meraki/schema)](https://packagist.org/packages/meraki/schema)
[![License](https://img.shields.io/packagist/l/meraki/schema)](LICENSE)

> ### `2.0` is in development
>
> The API on `main` is the `2.0` one and is not yet tagged. It is a **breaking rewrite** of
> `1.x`: fields are immutable, input is objects rather than arrays, and every field parses to a
> value object. The sibling packages are mid-migration.
>
> `1.14.0` is the last stable `1.x` release. See [CHANGELOG.md](CHANGELOG.md) and
> [docs/ROADMAP.md](docs/ROADMAP.md).

Define a form **once** — its fields, their constraints, and the rules that wire them together —
and validate input against that description. The core knows nothing about HTTP, HTML or JSON.

```php
$schema = new Facade('signup');

$schema->add(
    $schema->createEmailAddressField('email'),
    $schema->createPasswordField('password')->minLengthOf(12),
    $schema->createNumberField('age')->minValueOf(18),
);

$result = $schema->validate((object) [
    'email' => 'kim@example.test',
    'password' => 'correct horse battery staple',
    'age' => '34',
]);

$result->anyFailed();   // false
```

| Package | Responsibility |
| --- | --- |
| [`meraki/schema`](https://github.com/merakiframework/schema) | Define and validate schemas (this package) |
| [`meraki/schema-json`](https://github.com/merakiframework/schema-json) | Serialize a schema and read it back |
| [`meraki/schema-html`](https://github.com/merakiframework/schema-html) | Render a form, normalize request input, produce messages |

## Why this library

| Instead of | You get |
| --- | --- |
| Re-declaring the same rules in PHP and again in JavaScript | One definition that serializes |
| A regex that approximates a postcode | Per-country rules from libaddressinput, phone numbers from libphonenumber |
| `'age' => 'required\|integer\|min:18'` | Typed field objects your IDE and analyser can see |
| A validator welded to one framework | A core that knows nothing about HTTP, HTML or JSON |
| Registering a custom type with a factory | A class. Nothing to register — see [EXTENDING.md](docs/EXTENDING.md) |

**The honest counterweight.** Every mature alternative ships error messages and translations, and
this deliberately does not — see [below](#where-error-messages-come-from). It is also far less
proven than any of them. [docs/COMPARISON.md](docs/COMPARISON.md) works through the alternatives
in detail, including where each is the better choice.

## Requirements

PHP 8.5+.

## Installation

```bash
composer require meraki/schema
```

## A first schema

```php
use Meraki\Schema\Facade;

$schema = new Facade('contact_form');

$schema->add(
    $schema->createTextField('username')->minLengthOf(3)->maxLengthOf(20),
    $schema->createEmailAddressField('email'),
);
```

Two things to know straight away, because both are unusual and both are deliberate.

**A field is immutable.** Every configuration method hands back a *copy*, so the result has to be
kept:

```php
$username = $schema->createTextField('username');
$username->minLengthOf(3);                              // configures nothing
$username = $username->minLengthOf(3);                  // configures something
```

That is what makes a schema safe to build once and share across concurrent requests. Build,
configure, *then* `add()`.

**Input is objects, not arrays.** A value with named parts is an object; a value with many of
something is an array:

```php
$schema->validate((object) [
    'price' => (object) ['currency' => 'AUD', 'amount' => '12.50'],   // a record
    'lines' => [$row, $row],                                          // a list
]);
```

PHP cannot tell an associative array from a list, so the distinction lives in the shape of the
input. Converting `$_POST` is the port's job. `json_decode($body)` already gives you objects — it
is the `true` second argument that does not.

## Reading a result

```php
$result = $schema->validate($data);

$result->anyFailed();               // any field failed
$result->allPassed();               // every field passed
$field = $result->forField('email');
```

Each field's result carries the same things, whatever kind of field it is:

```php
$field->given;        // exactly what was submitted, unchanged
$field->value;        // what the field made of it — an EmailAddress\Value
$field->source;       // Submitted | Prefilled | Default | None
$field->shape;        // could this be read at all?
$field->status;       // Passed | Failed | Skipped | Pending
```

**Shape and constraints are different questions**, and the order matters. If a value cannot be
read at all, the constraints are *skipped* rather than failed — so a report names one problem
once instead of once per constraint:

```php
$field = $schema->validate((object) ['email' => 'not an address'])->forField('email');

$field->shape->wasUnreadable();                    // true
$field->forConstraint('maxLength')->status->name;  // 'Skipped'
```

`missing` and `unreadable` are kept apart, because "this is required" and "this is not an email
address" are different sentences.

## Where error messages come from

Not from here. The core says *what* failed and *what the limit was*:

```php
$failed = $field->getFailed()->getFirst();

$failed->name;    // 'minLength'
$failed->bound;   // 3
$failed->part;    // 'postal_code', or null for the whole value
```

Turning that into a sentence needs a locale and a context the library does not have — "must be at
least 3 characters" is wrong for a field labelled "PIN". `meraki/schema-html` ships a default set;
`examples/interactive-input.php` shows a twenty-line one.

## Field types

Nineteen, each parsing to its own value object:

`Address` · `Boolean` · `Collection` · `CreditCard` · `Date` · `DateTime` · `Duration` ·
`EmailAddress` · `Enum` · `File` · `Money` · `Name` · `Number` · `Password` · `PhoneNumber` ·
`Text` · `Time` · `Uri` · `Uuid`

[docs/API.md](docs/API.md) lists every field's configuration and the constraint names it reports.
The domain types are the point: an `Address` validates against Google's libaddressinput, a
`PhoneNumber` against libphonenumber, a `Password` against zxcvbn.

```php
$schema->add(
    $schema->createAddressField('billing', ['AU'])->allowOnlyPhysical(),
    $schema->createMoneyField('price', ['AUD' => 2])->minAmountOf('AUD', '10.00'),
);
```

Declare a region once and the region-aware fields inherit it:

```php
$schema = (new Facade('booking'))->for('AU');
$schema->createAddressField('billing');      // restricted to AU
$schema->createPhoneNumberField('mobile');   // ditto
```

## Rules

One field's value deciding another's requirements. Both branches live on the same rule, so they
cannot drift apart:

```php
$schema->addRule(
    $schema->when('who_for')->equals('someone_else')
        ->thenRequire('participant_name')
        ->otherwiseMakeOptional('participant_name'),
);
```

A rule can compare two *fields*, whole or part by part:

```php
use Meraki\Schema\{PartScope, ValueScope};

// is the shipping address the billing address?
$schema->when(ValueScope::of('shipping'))->equals(ValueScope::of('billing'));

// are they at least in the same country?
$schema->when(PartScope::of('shipping', 'country'))
    ->equals(PartScope::of('billing', 'country'));
```

Rules are checked when they are **written**, not when they fire — a field that does not exist, a
part that is misspelled, or a value the target field could never hold all raise at `addRule()`.

The result says which rules acted, so a renderer can tell a rule-driven optional from an authored
one:

```php
$result->forField('participant_name')->wasAlteredByRule();   // true
```

## Concurrency

A schema is a definition and nothing per-request touches it, so one instance serves many requests:

```php
$schema = new Facade('signup');    // built once, at boot
// ...
$alice = $schema->validate($a);    // two requests, in any order,
$mallory = $schema->validate($b);  // interleaved or not
```

Fields are `readonly`, the field and rule sets are `private(set)`, and a per-request value is
passed *in* rather than stored. Per-user data arrives with the request too, so a serialized schema
can never contain it:

```php
$schema->validate($submitted, prefilledWith: $knownAboutThisUser);
```

`tests/LongLivedProcessTest.php` interleaves two requests with fibres to prove it.

## Documentation

[docs/](docs/README.md) is the index. The short version:

- [DESIGN.md](docs/DESIGN.md) — the decisions and what each costs
- [API.md](docs/API.md) — every field's surface
- [EXTENDING.md](docs/EXTENDING.md) — writing your own field type
- [COMPARISON.md](docs/COMPARISON.md) — the alternatives, fairly
- [LIMITATIONS.md](docs/LIMITATIONS.md) — what is still wrong
- [examples/](examples/) — ten runnable examples, one aspect each

## Testing

```bash
composer test         # the suite
composer analyse      # PHPStan
composer ci           # everything CI runs, in the same order
```

`composer ci` also runs every example and checks the changelog is current. An example that stops
working fails the build — documentation that does not run is worse than none.

## License

MIT. See [LICENSE](LICENSE).
