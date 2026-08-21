# meraki/schema

<!-- Uncomment once CI lands (see docs/ROADMAP.md).
[![Tests](https://github.com/merakiframework/schema/actions/workflows/tests.yml/badge.svg)](https://github.com/merakiframework/schema/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/meraki/schema)](https://packagist.org/packages/meraki/schema)
[![License](https://img.shields.io/packagist/l/meraki/schema)](LICENSE)
-->

> ### ⚠️ Pre-release
>
> This package is tagged **alpha** and the public API is not yet stable. It is usable —
> 957 tests, and the standards-backed field types are solid — but there are known defects
> serious enough to block a stable release, including uncaught exceptions on malformed
> input to composite fields and a data leak when one schema is shared across concurrent
> requests.
>
> **Read [docs/LIMITATIONS.md](docs/LIMITATIONS.md) before adopting.** The path to the
> first stable release (`1.14.0`) is in [docs/ROADMAP.md](docs/ROADMAP.md).

A flexible, UI-agnostic library for **defining and validating** form schemas in PHP.

You describe a form once — its fields, their constraints, and the rules that wire
fields together — and the schema validates input against that description. The core
package is deliberately focused: it knows nothing about HTTP, HTML, or JSON. Those
concerns live in sibling packages so the domain stays small and stable:

| Package | Responsibility |
| --- | --- |
| [`meraki/schema`](https://github.com/merakiframework/schema) | Define + validate schemas (this package) |
| [`meraki/schema-json`](https://github.com/merakiframework/schema-json) | JSON serialization / deserialization |
| [`meraki/schema-html`](https://github.com/merakiframework/schema-html) | Render a schema as an HTML form + normalize request input |

## Why this library

Define a form **once**, as a serializable, UI-agnostic schema, with real domain field
types — addresses, phone numbers, money, credit cards — validated against curated
standards data rather than hand-rolled regexes. Nothing else in PHP occupies that spot.

| Instead of | You get |
| --- | --- |
| Re-declaring the same rules in PHP and again in JavaScript | One definition that serializes, driving an HTML form, a JSON API, or a native client |
| A regex that approximates a postcode | Per-country address rules from Google's libaddressinput, and phone numbers from libphonenumber |
| Stringly-typed rules (`'age' => 'required|integer|min:18'`) | Typed field objects your IDE and static analyser can see |
| A validator welded to one framework | A core that knows nothing about HTTP, HTML or JSON |

The honest counterweight: every mature alternative ships error messages and translations,
and this library deliberately does not — see
[Where error messages come from](#where-error-messages-come-from). It is also far less
mature than any of them. [docs/COMPARISON.md](docs/COMPARISON.md) works through
`symfony/validator`, `symfony/form`, `nette/forms`, Laravel, `respect/validation`,
`cuyz/valinor` and `opis/json-schema` in detail.

## Requirements

- PHP 8.4+

## Installation

```bash
composer require meraki/schema
```

## Quick start

```php
use Meraki\Schema\Facade;

$schema = new Facade('contact_form');

$schema->addTextField('username')
    ->matches('/^[a-zA-Z0-9_]+$/')
    ->minLengthOf(3)
    ->maxLengthOf(20);

$schema->addNumberField('age')
    ->minOf(18)
    ->maxOf(120);

$result = $schema->validate([
    'username' => 'johndoe',
    'age'      => 25,
]);

if (!$result->anyFailed()) {
    // safe to proceed
}
```

`Facade` is the entry point. Each `addXField()` method appends a field and, when
called without a configurator, returns the field itself so constraints can be
chained fluently.

## Reading validation results

`validate()` returns a `SchemaValidationResult` — an aggregate of one result per
field. It is iterable, and rolling the per-field results up into a single verdict
is left to you, via the granular predicates:

```php
$result = $schema->validate($data);

$result->anyFailed();   // at least one field failed
$result->allPassed();   // every field passed (none skipped)
$result->anySkipped();  // at least one field was skipped
$result->anyPending();  // not yet validated

// e.g. "no errors" usually means: nothing failed and nothing is pending
$ok = !$result->anyFailed() && !$result->anyPending();

foreach ($result->getFailed() as $fieldResult) {
    foreach ($fieldResult->getFailed() as $failure) {
        // $failure->name  -> the constraint that failed (e.g. 'min', 'pattern', 'type')
        echo "\"{$failure->name}\" failed for field \"{$fieldResult->field->name}\"\n";
    }
}
```

Every result carries a `ValidationStatus`. On aggregate results it is a *computed*
property derived on demand from the contained results, so it never goes stale:

```php
use Meraki\Schema\ValidationStatus;

$fieldResult->status === ValidationStatus::Passed;
// Passed | Pending | Skipped | Failed
```

`validate()` stores no *result* on the fields, so re-validating is safe and repeatable
and the result tree is yours to keep. It does, however, write the submitted input onto
the fields, so **a schema instance is per-request state, not a shared singleton** — see
[Long-lived processes](#long-lived-processes). Making validation genuinely pure is a
`1.14.0-beta.2` goal; see the
[roadmap](docs/ROADMAP.md#architecture-immutable-definition--resolvedfield).

Each field is validated in two phases: first its **value/shape** (reported under
the constraint name `type`), then its individual constraints. If the shape check
fails, the remaining constraints are skipped rather than failed.

## Where error messages come from

**Not from this package, by design.** The core reports *which constraint failed* and
nothing else. Turning `min` on a `Text` field into "must be at least 3 characters" — in a
particular language, tone and medium — is presentation, and lives in the presentation
package.

`meraki/schema-html` ships the reference implementation:

```php
use Meraki\Schema\Html\ValidationMessages;

$messages = new ValidationMessages();
$result = $schema->validate(['username' => 'ab']);   // too short, and email is missing

foreach ($result as $fieldResult) {
    foreach ($messages->errorsFor($fieldResult->field, $fieldResult) as $error) {
        echo $error, "\n";
    }
}

// Value is too short: Expected at least 3 characters
// This is required
```

`ValidationMessageProvider` is the extension point — implement
`errorsFor(Field, ?ValidationResult): string[]` for your own wording or language.

This is a deliberate trade. You give up "install one package, get English error strings";
you get a core that can drive an HTML form, a JSON API and a native client from one
definition without any of them inheriting another medium's phrasing. The cost is real: if
you use the core on its own, you write a message provider. Today only `schema-html` ships
one — `meraki/schema-json` does not yet, and that is
[on the roadmap](docs/ROADMAP.md#planned-features).

Because downstream providers match on them, **constraint names are public API**. They are
listed under [Constraint names](#constraint-names).

## Optional fields and default values

```php
$schema->addBooleanField('subscribe')
    ->makeOptional()   // absent input is skipped, not failed
    ->prefill(false);  // resolved value when no input is given

$schema->addBooleanField('terms')->require(); // the default; explicit here
```

When a field is optional and no input is provided, all of its constraints are
**skipped**. `prefill()` sets the value used in place of missing input.

## Supplying input

`validate()` accepts an array or an object. Objects are read via their public
properties **and** `__get()` accessors, so value objects work without exposing
internals:

```php
final class Input
{
    public function __construct(private array $data) {}
    public function __get(string $name): mixed { return $this->data[$name] ?? null; }
}

$schema->validate(new Input(['username' => 'johndoe', 'age' => 25]));
```

You can also stage input separately from validation:

```php
$schema->prefill($defaults); // default values
$schema->input($data);       // user input (applies rules)
$schema->validate($data);    // input + validate in one step
```

## Input expectations

The core expects **typed PHP values**, not raw request strings. It performs no coercion:

```php
$schema->addBooleanField('subscribe');

$schema->validate(['subscribe' => 'on'])->anyFailed();    // true  — a raw form value
$schema->validate(['subscribe' => true])->anyFailed();    // false
```

Rule conditions compare with `===` for the same reason, so
`whenEquals('#/fields/x/value', true)` will not match the string `"1"`.

This is intentional: normalizing an HTTP request is `meraki/schema-html`'s job. If you
point the core straight at `$_POST`, normalize first — or use `schema-html`, which does
it for you.

## Field types

| Method | Field | Notable constraints |
| --- | --- | --- |
| `addTextField` | `Text` | `minLengthOf`, `maxLengthOf`, `matches` |
| `addNameField` | `Name` | `minLengthOf`, `maxLengthOf` |
| `addNumberField` | `Number` | `minOf`, `maxOf`, `scaleTo`, `inIncrementsOf` |
| `addBooleanField` | `Boolean` | — |
| `addEnumField` | `Enum` | `allow` (set via constructor `$options`) |
| `addDateField` | `Date` | `from`, `until` / `to`, `atIntervalsOf` |
| `addTimeField` | `Time` | `from`, `until`, `inIncrementsOf`, `precisionMode` |
| `addDateTimeField` | `DateTime` | `from`, `until`, `inIncrementsOf`, `precisionMode` |
| `addDurationField` | `Duration` | `minOf`, `maxOf`, `inIncrementsOf` |
| `addMoneyField` | `Money` | `allow`, `minOf`, `maxOf`, `inIncrementsOf` |
| `addEmailAddressField` | `EmailAddress` | `minLengthOf`, `maxLengthOf`, `allowDomain`, `disallowDomain` |
| `addPhoneNumberField` | `PhoneNumber` | `allow` (countries), `ofType` |
| `addUriField` | `Uri` | `minLengthOf`, `maxLengthOf` |
| `addUuidField` | `Uuid` | `restrictToVersion` |
| `addCreditCardField` | `CreditCard` | — |
| `addPasswordField` | `Password` | length + `minNumberOf*`/`maxNumberOf*` (lowercase, uppercase, digits, symbols), `satisfyAnyOf` |
| `addPassphraseField` | `Passphrase` | — |
| `addFileField` | `File` | `atLeast`, `atMost`, `minFileSizeOf`, `maxFileSizeOf`, `allowTypes`, `disallowTypes`, `allowImages`, `allowVideos`, `allowDocuments`, `disallowScripts` |
| `addAddressField` | `Address` (composite) | `allow` (countries), `ofType` |
| `addVariantField` | `Variant` | accepts any of several atomic field types |

Composite fields (e.g. `Address`) group sub-fields; their values can be nested
under either the local name or the fully-qualified name:

```php
$schema->addMoneyField('price', ['AUD' => 2]);
$schema->validate(['price' => ['amount' => '1500', 'currency' => 'AUD']]);
```

### Addresses

An address is a composite of `organization`, `line1`, `line2`,
`dependent_locality`, `locality`, `administrative_area`, `postal_code` and
`country_code`. Values are always codes, never names: `AU`, not `Australia`.

With no countries allowed it is free-form — anything goes, and only `line1` is
required. Allowing one or more countries applies that country's rules, taken from
Google's libaddressinput data via `commerceguys/addressing`:

```php
$schema->addAddressField('billing', ['AU']);

// four-digit postcode, suburb and state required, country settled as AU
$schema->validate(['billing' => [
    'line1' => '1 Queen St',
    'locality' => 'Brisbane',
    'administrative_area' => 'QLD',
    'postal_code' => '4000',
]]);
```

A single allowed country **determines** the country: it is prefilled, reported by
`determined()`, and needs no input (`meraki/schema-html` renders it hidden), but
it is still part of the value so the address never serializes without it.

Countries differ in more than their postcodes. Singapore has no administrative
area and Hong Kong has no postal code, so neither is required there. Allow
several countries and each part is required only if *every* one of them requires
it, while validation applies the rules of whichever country was actually chosen.

`ofType()` says what the address is for, using HL7 FHIR's `Address.type`
vocabulary plus `either` for "no restriction":

| Type | Meaning | PO box |
| --- | --- | --- |
| `Either` | either purpose is fine (**default**) | accepted |
| `Postal` | must be mailable | accepted |
| `Physical` | must be somewhere you can go | rejected |
| `Both` | must be mailable *and* visitable | rejected |

> **This validates shape, not existence.** A postcode matching `\d{4}` is a
> well-formed Australian postcode, not a real one, and a postcode never implies a
> state — Queensland is 4xxx *and* 9xxx, and the ACT's 2600–2618 sits inside New
> South Wales' 2xxx. Confirming an address exists needs a licensed verification
> service (Australia Post PAF, Loqate, USPS DPV).

### Declaring the schema's region

Rather than repeating a country list on every field that needs one, a schema can
declare it once. Fields added afterwards inherit it:

```php
$schema = (new Facade('checkout'))->for('AU');

$schema->addAddressField('billing');            // restricted to AU
$schema->addPhoneNumberField('mobile');         // ditto
$schema->addAddressField('shipping', ['NZ']);   // an explicit list still wins
$schema->addAddressField('other', []);          // an explicit [] means free-form
```

This applies to `Address` and `PhoneNumber` — the fields whose rules are
jurisdictional — and only via the typed `addXField()` helpers. It deliberately
does not apply to `Money`: currency does not follow from a region, since a
country may use several and the euro spans twenty.

A `Variant` field accepts a value that may match one of several atomic field
types; the first matching type wins:

```php
$schema->addVariantField('secret', [
    new Field\Password(new Property\Name('password')),
    new Field\Passphrase(new Property\Name('passphrase')),
]);
```

### Constraint names

A failed constraint is reported by name (`$failure->name`), and message providers match
on those names, so they are part of the public API. Every field also reports `type` for
its value/shape check.

| Field | Constraint names |
| --- | --- |
| `Text` | `type`, `min`, `max`, `pattern` |
| `Name` | `type`, `min`, `max` |
| `Number` | `type`, `min`, `max`, `step` |
| `Boolean` | `type` |
| `Enum` | `type` |
| `Date` | `type`, `from`, `until`, `interval` |
| `Time` | `type`, `from`, `until`, `step` |
| `DateTime` | `type`, `from`, `until`, `interval` |
| `Duration` | `type`, `min`, `max`, `step` |
| `EmailAddress` | `type`, `min`, `max`, `allowedDomains`, `disallowedDomains` |
| `PhoneNumber` | `type`, `allowedCountries`, `numberType` |
| `Uri` | `type`, `min`, `max` |
| `Uuid` | `type`, `version` |
| `Password` | `type`, `length`, `lowercase`, `uppercase`, `digits`, `symbols`, `anyOf` |
| `Passphrase` | `type`, `entropy`, `dictionary` |
| `File` | `type`, `minCount`, `maxCount`, `allowedTypes`, `disallowedTypes`, `minSize`, `maxSize` |
| `Collection` | `type`, `minItems`, `maxItems` |

Note that `min`/`max` mean *length* on `Text`, `Name`, `EmailAddress` and `Uri`, but
*value* on `Number` and `Duration`.

Composite fields report against their **sub-fields**, which carry both their own bare
constraint names and the qualified ones the composite applies to them:

| Field | Reported as |
| --- | --- |
| `Money` (as `price`) | on `price.amount`: `price.amount.scale`, `price.amount.min`, `price.amount.max`, `price.amount.step`, plus `min`, `max`, `step`; on `price.currency`: `type` |
| `Address` (as `addr`) | on each part: `addr.<part>.required`, plus `addr.postal_code.format`, `addr.line1.visitable`, `addr.administrative_area.allowed`, `addr.country_code.allowed` |
| `CreditCard` (as `card`) | each sub-field reports its own — `card.number` gives `min`, `max`, `pattern`; `card.expiry` gives `from`, `until`, `interval` |

> `Enum` reports only `type`: an out-of-range value fails the shape check rather than a
> separate constraint. `Boolean` and `CreditCard` have no constraints of their own.

## Conditional rules

Rules make one field's requirements depend on another field's value. Targets are
referenced by scope path (`#/fields/<name>/value`):

```php
$schema->addBooleanField('has_phone');
$schema->addTextField('phone')->makeOptional();

$schema->whenAllMatch(
    fn($rule) => $rule
        ->whenEquals('#/fields/has_phone/value', true)
        ->thenRequire('#/fields/phone')
);
```

- `whenAllMatch(...)` — all conditions must hold (`whenAnyMatch(...)` for any).
- Conditions: `whenEquals`, `andWhenEquals`, `orWhenEquals` (or pass a
  `Rule\Condition` to `when`/`andWhen`/`orWhen`).
- Outcomes: `thenRequire($scope)`, `thenMakeOptional($scope)`.

Rules are re-applied on each `input()`/`validate()` call, and each field is reset
to its author-configured optionality first, so an outcome never lingers once its
condition stops holding.

## Long-lived processes

Swoole, RoadRunner and FrankenPHP are a supported target, but **only serial reuse is safe
today**.

```php
// Safe: one request at a time over one instance (RoadRunner's worker model).
$schema->validate($requestA);
$schema->validate($requestB);   // correct, order-independent

// NOT safe: one instance shared across concurrent coroutines.
// Field state is instance state, so one request can read another's data.
```

Until `1.14.0-beta.2`, **build the schema per request**. It is cheap — a seven-field
checkout schema with two addresses, a phone number, money and a collection builds in
about 0.25 ms, against 0.43 ms to validate it once, so rebuilding costs less than
validating.

Two traps worth knowing:

- **`clone` does not isolate.** A cloned `Facade` shares the same field objects, so
  validating the clone mutates the original. Use `unserialize(serialize($schema))` if you
  need a genuine copy of a prototype.
- **Register a factory, not an instance,** in your DI container. Registering a schema as
  a service shares one instance by default, which is exactly the unsafe case.

From `1.14.0-beta.2` the definition becomes immutable and per-request state moves into a
`ResolvedField` returned by `validate()`, making a shared instance safe by construction.
Details in [docs/LIMITATIONS.md#b7](docs/LIMITATIONS.md#b7) and
[docs/ROADMAP.md](docs/ROADMAP.md#architecture-immutable-definition--resolvedfield).

## Design decisions

- **Single-purpose core.** Serialization and rendering are *not* in this package.
  JSON lives in `meraki/schema-json`; HTML rendering and request normalization
  live in `meraki/schema-html`. The core depends on neither and exposes a stable
  public API they both consume.
- **Messages are a UI concern.** The core reports constraint *names*; the words a user
  reads live in the presentation package, because the right phrasing depends on the
  medium and the language. That makes **constraint names public API** — downstream
  providers match on them. See
  [Where error messages come from](#where-error-messages-come-from).
- **No `Property\Type`, no `Field\Factory`.** Earlier versions modelled a field's
  type as a `Property\Type` value object and built fields through a factory. Both
  were removed. A field's type *is* its class, and the shape check is a single
  `validateValue(mixed): bool` method each field implements. `Facade` constructs
  fields directly in its `addXField()` methods.
- **Immutable results, computed status.** `SchemaValidationResult` and the
  aggregated/field/constraint results are immutable; combinators like
  `getFailed()`, `add()`, and `merge()` return new instances. An aggregate's
  `status` is computed on demand rather than stored, so it can never drift from
  its contents.
- **Validation should be a pure query.** The intent is that validating returns a
  result and leaves the schema untouched, so no per-request state hangs off the
  definition. Today only half of that holds: no *result* is stored, but the submitted
  input is written onto the fields. Closing the gap is the main work of the `1.14`
  line — the definition becomes immutable and per-request state moves into a
  `ResolvedField`. See [ROADMAP.md](docs/ROADMAP.md#architecture-immutable-definition--resolvedfield).
- **The caller owns the roll-up.** Aggregate results expose granular predicates
  (`anyFailed()`, `allPassed()`, `anyPending()`, ...) rather than a single
  opinionated `passed()`/`failed()`. Whether "all passed", "no failures", or
  "nothing pending" counts as success is a decision the library leaves to you.
- **Composite input nests by local name.** Sub-field values are supplied nested
  under the composite (`['price' => ['amount' => ...]]`); fully-qualified flat
  keys are not accepted.
- **Skip vs. fail.** Missing input on an optional field skips its constraints; a
  failed shape check skips (rather than fails) the dependent constraints. This
  keeps error reports focused on the real problem.
- **camelCase keys, with qualified paths for sub-fields.** Serialized field keys and
  constraint names use camelCase (e.g. `minCount`, `allowedTypes`); `uri` is the
  canonical term for URL-style fields. Constraints a composite applies to one of its
  sub-fields are named by path instead, using the sub-field's own name verbatim —
  `addr.postal_code.format`, `price.amount.scale`. See
  [Constraint names](#constraint-names) for the full list. (The serialized form itself
  is produced by `meraki/schema-json`.)
- **Standards data over hand-typed tables.** Where a field's rules are a matter of
  public record, they come from a library that curates them rather than from
  constants here: phone numbers from libphonenumber, addresses from Google's
  libaddressinput (via `commerceguys/addressing`). It also means the *data* stays
  in the core while the *words* — "Suburb" or "Prefecture" for an administrative
  area — stay in `meraki/schema-html`, which is the only consumer that needs them.

## Status and limitations

This package is **pre-release**. The architecture is settled and the standards-backed
field types are well covered, but a short list of specific defects blocks a stable
release — malformed input to a composite field raises an uncaught exception, `Uri`
accepts anything, `CreditCard` performs no Luhn check, and a schema shared across
concurrent requests leaks data between them.

- **[docs/LIMITATIONS.md](docs/LIMITATIONS.md)** — every known defect with a runnable
  reproducer, the intentional behaviour that will surprise you, and what to do about each
  in the meantime. Read this before adopting.
- **[docs/ROADMAP.md](docs/ROADMAP.md)** — the release ladder to `1.14.0`, the first
  stable release, and the architecture change that gets there.
- **[docs/COMPARISON.md](docs/COMPARISON.md)** — how this compares with the alternatives,
  including when to pick one of them instead.
- **[CHANGELOG.md](CHANGELOG.md)** — release history, including breaking changes. (The
  `Address` sub-field rename previously documented here now lives in the
  [`1.13.0-alpha` entry](CHANGELOG.md).)

## Examples

Runnable scripts live in [`examples/`](examples/):

- [`validate.php`](examples/validate.php) — basic field validation.
- [`validate-field.php`](examples/validate-field.php) — validating a single field
  on its own, without a schema.
- [`validate-with-rules.php`](examples/validate-with-rules.php) — conditional
  rules, where one field's requiredness depends on another's value.
- [`validate-with-magic-input.php`](examples/validate-with-magic-input.php) —
  validating a `__get`-based value object.

Serializing and rendering are not part of this package, so their examples live
with the package that owns them: [`meraki/schema-json/examples`](https://github.com/merakiframework/schema-json/tree/main/examples)
and [`meraki/schema-html/examples`](https://github.com/merakiframework/schema-html/tree/main/examples).

## Testing

```bash
composer install
vendor/bin/phpunit
```
