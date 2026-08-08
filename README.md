# meraki/schema

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
        // $failure->name  -> the constraint that failed (e.g. 'minLength', 'type')
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

`validate()` is a pure query: it returns the result and stores nothing on the
fields. Re-validating is safe and repeatable, and the result tree is yours to keep.

Each field is validated in two phases: first its **value/shape** (reported under
the constraint name `type`), then its individual constraints. If the shape check
fails, the remaining constraints are skipped rather than failed.

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

## Design decisions

- **Single-purpose core.** Serialization and rendering are *not* in this package.
  JSON lives in `meraki/schema-json`; HTML rendering and request normalization
  live in `meraki/schema-html`. The core depends on neither and exposes a stable
  public API they both consume.
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
- **Pure `validate()`.** Validation returns a result and stores nothing on the
  fields — no per-request state hangs off the schema definition. (HTML rendering
  threads the returned result through instead of reading it back off the field.)
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
- **camelCase keys.** Serialized field keys and constraint names use camelCase
  (e.g. `minLength`, `minCount`); `uri` is the canonical term for URL-style
  fields. (The serialized form itself is produced by `meraki/schema-json`.)
- **Standards data over hand-typed tables.** Where a field's rules are a matter of
  public record, they come from a library that curates them rather than from
  constants here: phone numbers from libphonenumber, addresses from Google's
  libaddressinput (via `commerceguys/addressing`). It also means the *data* stays
  in the core while the *words* — "Suburb" or "Prefecture" for an administrative
  area — stay in `meraki/schema-html`, which is the only consumer that needs them.

## Breaking changes

### `Address` sub-fields renamed

`Address` now follows libaddressinput's field set. Existing call sites and stored
values need updating:

| Old | New |
| --- | --- |
| `street` | `line1` (and a new `line2` for a unit or level) |
| `city` | `locality` |
| `state` | `administrative_area` |
| `postcode` | `postal_code` |
| `country` | `country_code` — now an ISO 3166-1 alpha-2 code, not a free-text name |

`organization` and `dependent_locality` are new and optional.

Property access under an old name (`$address->street`) throws. An *input* array
under an old name is a quieter failure: unrecognised keys are ignored, so
`['street' => '1 King St']` leaves `line1` empty and fails its required check.
Worth grepping for the old names rather than relying on tests to catch it.

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
with the package that owns them: [`meraki/schema-json/examples`](../schema-json/examples)
and [`meraki/schema-html/examples`](../schema-html/examples).

## Testing

```bash
composer install
vendor/bin/phpunit
```
