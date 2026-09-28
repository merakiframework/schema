# Upgrading

## `1.x` → `2.0`

`2.0` is a breaking rewrite. Nothing in this guide is a deprecation you can put off — the `1.x`
names are gone, so an upgrade is a single pass over your schema-building code.

Read [the one idea behind every change](#the-one-idea-behind-every-change) before the tables.
Almost every individual change follows from it, and the tables are much shorter if you already
know why.

### Before you start

- **PHP 8.5 is required.** `2.0` uses clone-with and property hooks; there is no 8.4 fallback.
- **The ports are not migrated yet.** `meraki/schema-html` and `meraki/schema-json` do not work
  against `2.0.0-alpha.2`. If you depend on either, stay on `1.14.0` until they are tagged.
- **Your stored documents still load.** The serialized form is unchanged: `#/fields/x/value` is
  still the scope wire format, and conditions and outcomes keep their `type`/`action` shapes.
  This is an API break, not a data break.

### The one idea behind every change

In `1.x` a `Field` held two different things at once: what it *is* (name, type, constraint
configuration) and what happened to it *on this request* (the submitted value, the resolved
value, whether a rule changed its optionality). `validate()` wrote the second kind onto the
field, which is why sharing a schema between concurrent requests leaked one user's data into
another's.

In `2.0` those are separated. **The definition is immutable and safe to share.** Everything
per-request lives on the result that `validate()` hands back — a `ResolvedField` per field.

So the mechanical rule for the whole upgrade is:

> Anything you used to **write onto** a field or schema before validating is now an **argument to
> `validate()`**. Anything you used to **read back off** a field after validating is now on the
> **result**.

### Building a schema

`addXField()` did two jobs — construct and attach — and returned either the schema or the field
depending on whether you passed a configurator. That is now two calls: `createXField()` makes a
field, `add()` attaches it.

```php
// 1.x
$schema = new Facade('signup');
$schema->addTextField('username', function (Field\Text $f): void {
    $f->minLengthOf(3)->maxLengthOf(20);
});

// 2.0
$schema = new Facade('signup');
$schema->add(
    $schema->createTextField('username')->minLengthOf(3)->maxLengthOf(20),
);
```

Two consequences worth knowing:

- **Withers return a copy.** `$field->minLengthOf(3);` on its own line now configures nothing —
  you must keep the return value. This is the most common upgrade mistake, and it fails silently
  rather than loudly.
- **A field is useful before it is attached.** Holding it in a variable is how you write rules
  about it, so most schemas now read as a series of `create…` assignments followed by one `add()`.

`Field\Factory` is gone; the schema builds its own fields. `Factory::for('AU')` is now
`(new Facade('booking'))->for('AU')`.

A collection takes its template fields directly instead of a closure:

```php
// 1.x
$schema->addCollectionField('attendees', fn (Facade $s) => $s->addNameField('name'));

// 2.0
$schema->add($schema->createCollectionField('attendees', $schema->createNameField('name')));
```

### Collection rows must be named

**This one breaks payloads, not just code.** A collection is submitted as rows under *names*, and a
positional list is refused — it reports `shape: unreadable`, like any other input a field cannot
read.

```php
// 1.x — a list
['attendees' => [['name' => 'Bilal'], ['name' => 'Chen']]]

// 2.0 — named rows
['attendees' => ['first' => (object) ['name' => 'Bilal'], 'second' => (object) ['name' => 'Chen']]]
```

A name is held to the same pattern as a field name: letters, digits, `_` and `-`, never starting with
a digit. Pick whatever your data already has — an id, a slug, a line number prefixed with a letter.

**Why.** A position meant a different row the moment anything was inserted above it, so a row could
not be reported against or addressed by a rule, and `Collection\Value` was the one value a scope
could not reach into. With names, `#/fields/attendees/value/alice/email/value` means the same row on
every request — which is what [row rules](docs/API.md#rules-that-apply-to-one-row) and
[columns](docs/API.md#asking-about-rows-collectively) are built on.

What follows from it, if you read results:

- `Collection\Item::$key` is a `string`, never an `int`.
- `Result::itemAt()`, `Value::rowAt()`, `has()`, `valueOf()` and `column()` all take a `string`.
- `Value::equals()` no longer cares about row *order*, because a name already says which row is
  which. Two lists with the same rows under the same names are equal.

If you render forms, the input names change with it: `attendees[0][name]` becomes
`attendees[first][name]`. `meraki/schema-html` has to generate those names, which is part of its own
migration.

### Supplying a request

`input()`, `prefill()` and `applyRules()` all wrote to the schema and are gone. Everything a
request carries is now an argument:

```php
// 1.x
$schema->prefill($knownAboutThisUser);
$schema->input($submitted);
$result = $schema->validate($submitted);

// 2.0
$result = $schema->validate($submitted, prefilledWith: $knownAboutThisUser);
```

Precedence is submitted → prefilled → authored default, and `$resolved->source` tells you which
one the judged value came from. `PrefillPolicy::Trusted` waives the constraints for a value that
actually survived as prefilled — trust attaches to the value, so it cannot excuse anything the
user typed over the top.

`validate()` takes an `object`, not an array. It also takes an optional `locale` for message
packs. To resolve without judging — drawing a form for the first time — call `resolve()`.

`defaultsTo()` is unchanged and stays on the definition: a default is authored by you, where a
prefill belongs to a request. That split is what makes "a serialised schema can never contain
user data" true by construction.

### Reading results

```php
// 1.x — read the field back
$schema->validate($data);
if ($field->hasValue()) { … }
$verdict = $field->validate()->get('minLength');

// 2.0 — read the result
$result   = $schema->validate($data);
$resolved = $result->forField('username');
$verdict  = $resolved->forConstraint('minLength');
```

The bare `get()` lookups are all named for what they look up now:

| `1.x` | `2.0` | Returns |
| --- | --- | --- |
| `$fieldResult->get('minLength')` | `$resolved->forConstraint('minLength')` | the verdict for one constraint |
| — | `$schemaResult->forField('email')` | the result for one field |
| — | `$item->forField('sku')` | one field of a collection item |
| `$field->constraints->get('minLength')` | `$field->constraints->named('minLength')` | the constraint *definition* |

On a `ResolvedField`:

- **`given`** is exactly what was submitted, unchanged. Redraw a rejected form from this — showing
  a coerced value in place of what someone typed turns a correction into a second mistake.
- **`value`** is what was validated: a value object this library defines (`Number\Value`,
  `Money\Value`, …), not a string.
- **Nothing on a result throws.** There is no property that blows up on exactly the requests where
  you need it most.
- **`type` is no longer a constraint.** "Is there a usable value at all" is the *shape*, asked
  before the constraints and reported separately as `$resolved->shape`. `$shape->wasMissing()`
  distinguishes "nothing was supplied" from "something was supplied that could not be read" — a
  distinction `1.x` made you disentangle by hand.
- **`transformed` is gone.** The parsed value is already the typed value, so there is no second
  property. Format from `$value` plus the field's own configuration.

### Rules

Rules are built as values and then added, instead of being declared inline through a closure
configurator. Conditions come from a named matcher vocabulary.

```php
// 1.x
$schema->whenAllMatch(function ($r) use ($method, $email): void {
    $r->when($method)->notEquals('email')->thenMakeOptional($email)->thenIgnore($email);
});

// 2.0
$schema->addRule(
    $method->when()->notEquals('email')
        ->then($email->makeOptional())
        ->thenIgnore($email),
);
```

- **An outcome is the field, configured.** `then($insurance->makeRequired()->mustBeAccepted())` —
  every wither is a rule outcome, including ones on your own field types. The rule stores the
  *difference*, so two rules touching different properties of one field both apply.
- **`allOf()` / `anyOf()` compose conditions**, never rules, so there is exactly one `then` per
  rule. `else()` and `elseIgnore()` give you what `1.x` made you write as a second rule with a
  hand-inverted condition that drifted out of step with the first.
- **The twelve matchers** are `equals`, `notEquals`, `isAtLeast`, `isGreaterThan`, `isAtMost`,
  `isLessThan`, `isBetween`, `isIn`, `contains`, `matches`, `isEmpty`, `isNotEmpty`. A field
  offers only the ones its value can answer — `$text->when()` has no `isAtLeast`.
- **Mistakes surface at `addRule()`**, not on a user's request.

If your `1.x` rules were doing nothing, you are not imagining it: outcomes used to call a wither
and discard the result, so rules had silently stopped applying. Re-check any behaviour you
believed a rule was producing.

### Fields that changed or went away

| Gone | What to do |
| --- | --- |
| `Composite` | Use the structured type that owns the value: `Address`, `Money` or `CreditCard`. Each takes one object shape and validates it, rather than being a bag of sub-fields. |
| `Variant` | Removed. Its only use was making `Password` and `Passphrase` look like one field, and those merged. |
| `Passphrase` | Use `Password`. The real distinction was how strength is measured, which is now `minStrengthOf()`. |
| `Placeholder` | Removed — a spacer is presentation, and belongs in your renderer. |
| `AtomicMultiValue` | Removed. A field holds one value; several values are a `Collection`. |
| Positional collection rows | Every row is named — see [Collection rows must be named](#collection-rows-must-be-named). A list is refused, and `Item::$key` is a `string`. |
| `EmailAddress` comma-splitting | An email field takes one address. `a@b.com, c@d.com` is now a malformed single address, not a list. Use a `Collection` for several. |
| `pairWith()` | Create both fields and write an ordinary rule. |
| `Field::$schema` | Removed. A field no longer knows its schema. |
| `Enum::allow()` | Removed — an enum is a closed set, fixed at construction. Build the full case list before creating the field. Cases must be strings. |
| `Money::allow($currency, $scale)` | `allowCurrencies([...])`. |
| `Money::minOf()` / `maxOf()` | `minAmountOf()` / `maxAmountOf()`. |
| `Money::inIncrementsOf()` | No replacement. `Money` reports `allowedCurrencies`, `minAmount`, `maxAmount` and `scale`. |
| `Field::require()` | `makeRequired()`, pairing with `makeOptional()`. |
| `Field::input()`, `prefill()`, `acceptInput()`, `ignoreInput()`, `hasValue()` | Per-request state; read it from the result instead. |
| `Field::traverse()`, `Facade::traverse()`, `ScopeTarget` | Path resolution moved to `ScopeResolver`. |
| `Field::rename()` | Removed. Name a field when you create it. |
| Presets (`Password::strong()` and friends) | Never built. Write the calls you mean. |

[docs/API.md](docs/API.md) has the full per-field surface — configuration methods, constraint
names and value type for all nineteen fields — and is the reference to check a specific call
against.

### `Time::until()` changed meaning — check any time range you wrote

**This one does not break your build.** The call still compiles and still takes the same
argument; it accepts one fewer value than it used to. That is the kind of change worth going
and looking for rather than waiting to be told about.

`Time::until()` was **inclusive** while `Date::until()` and `DateTime::until()` were exclusive.
All three are exclusive now, which is what [docs/API.md](docs/API.md) always said they were.

```php
$shift = $schema->createTimeField('shift')->until('17:00');

$schema->validate((object) ['shift' => '17:00']);   // 1.x: accepted   2.0: rejected
```

If you meant the endpoint to be included, say so — the inclusive bound is back under its own
name, and reports under its own constraint name so a message pack can word it separately:

```php
$schema->createTimeField('shift')->through('17:00');   // 17:00 accepted
```

There is a matching `after()` for the exclusive *lower* bound, so all four combinations are
nameable. See [The four temporal bounds](docs/API.md#the-four-temporal-bounds).

**Two related changes on `Date`, `Time` and `DateTime`:**

- `$from` and `$until` are **nullable** now, and default to `null` rather than to
  `LocalDate::min()`/`max()`. If you read these properties directly, or address them with a
  scope such as `#/fields/starts/until`, they can be `null`.
- An **unset** bound reports `Skipped` rather than `Passed`. Previously the sentinel answered
  every value with "yes", so the field returned a verdict on a question nobody asked. Code that
  asserted `Passed` on an unconfigured `from` or `until` needs to expect `Skipped`.

### Two things to know before you store rules

- **Every public property of a field is now wire format.** `ScopeResolver` addresses any public
  property, so `#/fields/username/minLength` is a legitimate stored target. That also means
  renaming a property is a breaking change for anyone holding stored rules.
- **The scope string format is not frozen.** A scope reaches a field, one of its public
  properties, one *part* of a structured value (`#/fields/addr/value/country`), or a named row of a
  collection (`#/fields/attendees/value/alice/email/value`) — but not a part *of* a part. Widening
  that is additive, so existing scope strings keep their meaning; see
  [docs/LIMITATIONS.md](docs/LIMITATIONS.md).

### What did not change

Stored documents, the scope wire format, `defaultsTo()`, the constraint vocabulary for the fields
that survived, and the fact that this library does not decide what a failure sounds like. Message
wording lives in installable MessageFormat 2 packs; with no provider configured the library
behaves exactly as it did.
