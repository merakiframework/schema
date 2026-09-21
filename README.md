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

**The honest counterweight.** It is far less proven than any mature alternative, and its messages
arrive differently from theirs — as a pack you install rather than strings bundled with the
validator, which is a real trade and not only an upside. [docs/COMPARISON.md](docs/COMPARISON.md)
works through the alternatives in detail, including where each is the better choice.

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

A verdict says *what* failed and *what the limit was*, with no wording attached:

```php
$failed = $field->getFailedConstraints()->getFirst();

$failed->name;    // 'minLength'
$failed->bound;   // 3
$failed->part;    // 'postal_code', or null for the whole value
```

That is enough to write your own sentence, and plenty of applications should. For the rest, wording
is an **installable language pack** — data, not code:

```
composer require meraki/schema-language-english
```

```php
use Meraki\Schema\Message\Mf2\Mf2Provider;

$schema = new Facade('signup', messages: Mf2Provider::fromPackage('meraki/schema-language-english'));

$result = $schema->validate($data, locale: 'en-AU');

$result->forField('billing')->messages->forPart('postal_code')->first;
// "That is not a valid postcode for the country you chose."
```

The pack is `.mfr` files in [ICU MessageFormat 2](https://unicode.org/reports/tr35/tr35-messageFormat.html)
and nothing else — no PHP — so a Rust or JavaScript implementation of this library renders the same
sentences. Which is the point: **the wording travels with the schema, not with the language you
happen to be writing in.**

Three things hold whether or not you use it:

- **The provider is registered on the schema; the language arrives with the request.** One schema
  serves every reader.
- **A missing language cannot change a verdict.** Ask for one nobody has and you get the same
  failures with nothing to say about them.
- **It is entirely optional.** With no provider, every result carries an empty message set and the
  library behaves as it did before messages existed.

[docs/MESSAGES.md](docs/MESSAGES.md) covers writing a pack, the specificity ladder, and using a
format other than MF2.

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
    $whoFor->when()->equals('someone_else')
        ->then($participantName->makeRequired())
        ->else($participantName->makeOptional()),
);
```

**An outcome is the field, configured.** There is no `thenRequire()` and there never needs to
be one: you call the field's own withers and the rule records what changed. So every
configuration method a field has is already a rule outcome — including on a field type you
wrote yourself:

```php
->then($terms->makeRequired()->mustBeAccepted())
->then($discount->maxValueOf(50))
```

It is type-safe because the field is on the *left*: `$terms` is a `Boolean`, so
`mustBeAccepted()` is on it and `minLengthOf()` is not, and your editor knows both. Fields
are immutable, so the wither hands back a copy and the schema's own field is untouched.

Twelve matchers, and the inclusive ones say so in their names:

```php
$age->when()->isAtLeast(18);            // 18 passes
$age->when()->isGreaterThan(18);        // 18 does not
$age->when()->isBetween(18, 65);        // both ends included
$country->when()->isIn(['AU', 'NZ']);
$notes->when()->matches('/^INV-/');
$company->when()->isEmpty();
```

**A field offers only the questions its value can answer.** `$notes` is text, so its matcher
carries `contains` and `matches` and no ordering at all — `$notes->when()->isAtLeast(3)` is a
call to a method that is not there, greyed out in your editor before you run anything. The
ordered questions belong to numbers, dates, times, durations and money.

`$schema->when('age')` still works for a field named by string or a scope pointing at a part.
It cannot know the type, so it offers all twelve and leans on the check that runs when the
rule is added.

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
