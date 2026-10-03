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
  against `2.0.0-alpha.3`. If you depend on either, stay on `1.14.0` until they are tagged.
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

### A step must be positive, and `clearStep()` is how you remove one

`inIncrementsOf()` on `Number` and `Duration` now throws `InvalidConfiguration` for a step of
zero or less. Both used to accept it and report it per request — differently, and none of the
four combinations usefully:

| | before | now |
| --- | --- | --- |
| `Number::inIncrementsOf(0)` | `step` skipped forever — the call silently did nothing | refused |
| `Number::inIncrementsOf(-5)` | `step` failed every value forever | refused |
| `Duration::inIncrementsOf('PT0S')` | `step` failed every value forever | refused |
| `Duration::inIncrementsOf('-PT5M')` | *passed* — an accident of the modulus | refused |

A step at or below zero is a definition nothing can satisfy, or a check that quietly does not
run. Every other bound in this library refuses the unsatisfiable where it is written rather than
reporting it on somebody's request, and these two were the exception.

**`clearStep()` is new on both**, and on `Number` it is now the only way back to "any value" —
`inIncrementsOf(0)` was the undocumented spelling for that, and it is refused.

```php
$quantity->inIncrementsOf(5)->clearStep();   // accepts any number again
```

On `Duration` it *widens* past the default, which is worth knowing: an unconfigured duration
steps by the minute and refuses `PT30S`. That is the end of a dial which already turned both
ways — `inIncrementsOf('PT1S')` admits `PT30S` just as surely — rather than a new exception to
"configuration narrows".
### A field name identifies exactly, and collides case-insensitively

Two jobs that used to be one method, split — and the split is a breaking change in both
directions.

```php
// before: equals() folded case, and nothing else honoured that
(new FieldName('email'))->equals(new FieldName('Email'));      // true

// now
(new FieldName('email'))->equals(new FieldName('Email'));      // false  — exact
(new FieldName('email'))->collidesWith(new FieldName('Email')); // true  — the old behaviour
```

**`FieldName::equals()` is now an exact string comparison.** It had to be, because every lookup
around it already was: `SchemaValidationResult::forField()`, `Rule\Application::forField()`,
`Collection\Item::forField()` and the outcome keying all compare the raw string. `equals()`
answering `true` where those answered "not found" is what made `thenIgnore('Detail')` against a
field named `detail` accept at authoring time and then silently never apply — the dead-rule
failure this library spends most of its guards preventing.

**`Field\Set::add()` and a `Collection` template now refuse a case-insensitive collision**, using
the new `collidesWith()`. That is where the old folding went, and it is the half that was worth
keeping: a schema holding both `email` and `Email` is a schema whose author has made a mistake.

**What breaks.** A template or schema that builds today with two names differing only in case
now throws `DuplicateFieldName` at `add()`. Previously it built, and then threw on *every
request* instead — so this is the same defect reported somewhere you can act on it. If you are
calling `FieldName::equals()` directly and relying on it folding case, switch that call to
`collidesWith()`.
### `Facade` is now `Definition`

A straight rename of the class you build a schema with. No alias is shipped.

```php
// before
use Meraki\Schema\Facade;
$schema = new Facade('signup');

// now
use Meraki\Schema\Definition;
$schema = new Definition('signup');
```

Nothing else about it changed — same constructor, same methods, same behaviour.

`Facade` named a pattern rather than a thing, and not accurately: a facade simplifies a subsystem
you could still use directly, and there is no schema underneath this one. The codebase had
already settled on the other word — every port wrote `Facade $schema`, the result class was
`SchemaValidationResult`, and this was the only class in `src/` with no docblock, because a
pattern name leaves nothing to explain.

`Definition` names the distinction the library turns on: a definition is what the author wrote, a
result is what one request produced, and nothing per-request may touch the first.

**It is not `Meraki\Schema\Field\Definition`**, which is the trait holding the configuration half
of a field. Different namespaces, different jobs, and they do not appear in the same file.

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
$schema = new Definition('signup');
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
`(new Definition('booking'))->for('AU')`.

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

`validate()` takes an `object`, not an array. It also takes an optional `locale` **and the
message provider itself** — see [Wording arrives with the request](#wording-arrives-with-the-request).
To resolve without judging — drawing a form for the first time — call `resolve()`.

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

<a id="wording-arrives-with-the-request"></a>

### Wording arrives with the request, not with the schema

The `messages:` constructor argument is gone. Hand the provider to `validate()` instead:

```php
// alpha.2
$schema = new Facade('signup', messages: $provider);
$result = $schema->validate($data, locale: 'en-AU');

// now
$schema = new Definition('signup');
$result = $schema->validate($data, locale: 'en-AU', messages: $provider);
```

`Facade::$messages` is gone with it. The locale always worked this way and the provider did not,
which made wording half a property of the definition: a schema built in a service container was
stuck with that container's pack, serialising a schema dropped the provider silently, and
swapping the wording for one caller meant rebuilding the schema. Nothing about wording was ever
a fact about a *definition* — the same data passes or fails identically in every language.

`resolve()` takes neither, because it reaches no verdict and only a failure has anything to say.

Everything else is unchanged: no provider, an unsupported tag, or no tag at all still leaves
every verdict exactly as it was and every message set empty.

### A required part that was not sent names itself

Every record-shaped field now answers the same three questions the same way. A **required** part
that is absent, or present and `null`, fails that part's own `*Required` constraint and carries
the part on the verdict, so a form can mark the box. A part that was *sent* and holds nothing is
a shape failure — `''` was a decision somebody made, and reading it as absence would let
whitespace satisfy a requiredness check.

`Address` already behaved this way. `Money`, `CreditCard` and `PhoneNumber` collapsed all three
cases into "unreadable", which is why a port could not tell "you left the amount out" from "the
amount is gibberish", and could not mark anything, since no part was named.

| Field | New constraints |
| --- | --- |
| `Money` | `currencyRequired`, `amountRequired` |
| `CreditCard` | `numberRequired`, `expiryRequired`, `nameRequired` |
| `PhoneNumber` | `numberRequired` |

**`CreditCard::expiryFormat` is gone.** An expiry that was given and cannot be read is now a
shape failure, the same way a bad amount already was on `Money`, so the constraint had nothing
left to say that `expiryRequired` does not. A message pack with wording for `expiryFormat` keeps
working — an unused key is not an error — but nothing will read it.

**`PhoneNumber` and `Address` report a missing country too,** as `countryRequired` — see the
next section, which is where that changed.

**`File` is unchanged.** Its `name`, `type` and `size` are one upload's metadata rather than
three inputs a form renders — no page has a "file type" box to mark — so `*Required` constraints
there would have added three names nothing can act on.

### A missing country names the country box

`Address` and `PhoneNumber` both refused a value with no country: the whole field came back
unreadable, so a form got *"That is not a valid address"* for somebody who had not reached the
country dropdown yet. Both now report `countryRequired` against the `country` part, exactly as
`Money` has always reported `currencyRequired`.

```php
$schema->validate((object) ['billing' => (object) [
    'street' => ['1 Queen St'], 'locality' => 'Brisbane', 'subdivision' => 'QLD', 'postal_code' => '4000',
]]);

// before — shape unreadable, no part named: "That is not a valid address."
// now    — countryRequired fails on part `country`:  "Choose a country."
```

The reasoning that put it there was that a country gives the rest of an address its meaning, so
there is nothing to report against. That is true, and it is just as true of the currency on
`Money` — which names the part and *skips* what it cannot judge. Everything read from a
country's own published format now skips when there is no country, so one mistake earns one
message.

**It only shows on a field that allows several countries.** With one allowed country a port
supplies it and nobody sees the box, which is why this survived two alphas.

Two things did **not** change. A country that was *given* and is not a country —
`'Zorbia'` — is still unreadable, because that is an answer nothing can use rather than a box
left empty. And a bare string for a phone number is still a shape failure, because a string
never described a pair.

`PhoneNumber` gains one more wrinkle worth knowing: with a number but no country,
`numberRequired` is **skipped** rather than failed. libphonenumber cannot read the number
without a region, so it is unread — but telling somebody to enter a number they just entered is
the wrong message. `countryRequired` reports the thing that is actually blocking it.

### An unrecognised subdivision is reported, not refused

The same part behaved three ways depending on the country. Absent gave `subdivisionRequired`;
unrecognised in a country that merely *uses* one (Ireland) gave `knownSubdivision`; unrecognised
in a country that *requires* one (Australia, China) made the whole address unreadable.

Now the middle two are one: an unrecognised subdivision is kept as submitted and
`knownSubdivision` reports it, whichever kind of country it is.

```php
// AU with subdivision 'ZZ'
// before — shape unreadable: "That is not a valid address."
// now    — knownSubdivision fails: "That is not a state we recognise for the country you chose."
```

For China and Colombia, whose subdivisions carry their own postcode patterns, the postcode is
still judged — it falls back to the country's own pattern — so a bad state no longer hides a
bad postcode.

`Address\Value::$subdivision` therefore holds the ISO 3166-2 code when the subdivision resolved
and the submitted text when it did not. It already behaved that way for Ireland; it is now
consistent. Rules written against a part are unaffected, because the expectation is
canonicalised through the same resolver as the stored value.
### A record raises on a key it does not declare

**This is the change most likely to break a working port, so read it even if you skip the rest.**

Every record-shaped field now raises `Exception\BrokenInputContract` when the record carries a key
its value does not declare. `validate()` and `resolve()` stop. Previously `Address` reported this
as an unreadable value and the other four silently dropped the key.

```php
$schema->validate((object) ['price' => (object) ['currency' => 'AUD', 'ammount' => '15.00']]);
// alpha.2 — amountRequired fails: "enter an amount"
// now     — Exception\BrokenInputContract
```

Keys are vocabulary rather than data. Something always maps a payload onto them, so a stray key is
that mapping being wrong on every request, for every submitter, until somebody edits code — and no
verdict can say so. A verdict says *this is reportable to whoever submitted*, and this library
cannot see whether that is a person, a peer implementation or a deploy that went out wrong.

**What you have to do.** A port must build the record from keys it chose, rather than forwarding a
decoded payload:

```php
// No — the remote party co-authors your key vocabulary.
$schema->validate(json_decode($request->getBody()));

// Yes.
$schema->validate((object) ['price' => (object) [
    'currency' => $body->price->currency,
    'amount' => $body->price->amount,
]]);
```

Catch it at your boundary and answer in your own protocol: a 500 for your own mapping bug, a `400`
naming `$broken->unknownKeys` for a client of a public API, a dead letter for a queue.

**The likely breakages**, in order: a payload forwarded from a decoded body; a payload built from a
wider internal record; and a part renamed in 2.0 that a port still sends — `line1`,
`administrative_area`, and now `e164` and `local_part`, which stopped being parts in this same
release.

**`File` no longer accepts a `$_FILES` entry.** It takes `name`, `type` and `size`. PHP's
`tmp_name`, `error` and `full_path` describe how one language's web SAPI received an upload rather
than anything about the file, and a port in another language has none of them. Hand over the three:

```php
$upload = $_FILES['resume'];

$schema->validate((object) ['resume' => (object) [
    'name' => $upload['name'],
    'type' => $upload['type'],
    'size' => $upload['size'],
]]);
```

**What did not change:** a value under a key that *is* declared. `['amount' => 'twelve']` is still
an ordinary unreadable value, reported as a verdict and rendered from a message pack. The line is
which keys, not what is in them — only the first can be attributed to the builder without knowing
the protocol.

### A value reports the parts it is submitted with

| | parts before | parts now |
| --- | --- | --- |
| `EmailAddress` | `local_part`, `domain` | **none** — it no longer implements `HasParts` |
| `PhoneNumber` | `country`, `e164` | `number`, `country` |

Both were reporting a *reading* of the value rather than its inputs. An email address is one box
on a form; `#/fields/email/value/domain` stops resolving, and the field's messages move from a
`PartedSet` to a `FlatSet`. `$value->localPart` and `$value->domain` are unchanged, and a rule
about a domain was always written as `matches('/@example\.test$/')` rather than through a part.

E.164 was never submitted either. `#/fields/phone/value/e164` stops resolving and
`Value::toE164()` is where it lives; in exchange, `#/fields/phone/value/number` now resolves and
`forPart('number')` no longer raises on the one part a form definitely renders.

`Api\StructuredTypeTest` holds the rule both ways, so the next value that reports a derived
reading fails there rather than in a port.

### A code is an enum case, and so is a part

Every field names what it checks with a string-backed enum of its own, and every structured value
names its parts the same way. The wire names are unchanged — `Text\Check::MinLength` is
`'minLength'`, `Address\Part::PostalCode` is `'postal_code'` — so a language pack keeps every key.

```php
// before
$result->forConstraint('minLength');
$failed->part === 'postal_code';

// now — a misspelled case does not compile; a misspelled string was a silent null
$result->forConstraint(Text\Check::MinLength);   // the wire name still works too
$failed->part === Address\Part::PostalCode;      // `->part?->value` for the string
```

What changed underneath:

- `ConstraintValidationResult` and `Constraint` carry `$code`, the enum case. `$name` is still
  there and is the case's value; `$part` is read from the code, so it is `?Field\Part` rather
  than `?string`.
- **Writing a field:** `new Constraint(Text\Check::MinLength, …)` replaces
  `new Constraint('minLength', …)`, the `part:` argument is gone — a code declares its own part —
  and a field lists its codes by overriding `declaredChecks()`. A structured field lists its parts
  with `declaredParts()`. See [EXTENDING.md](docs/EXTENDING.md).
- `HasParts::partNames()` and `HasParts::listParts()` are gone. Read `$field->parts` and
  `Field\Part::isList()`; `canonicalPartValue()` takes the `Field\Part` case.
- `Field\ValueClass::hasParts()`, `partNamesOf()` and `listPartsOf()` are gone: `$field->parts`
  answers all three.
- New on every field: `$parts`, `$essentialParts` and `$checks`.

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

### `Address` was rebuilt — new parts, new names, and data you may need to migrate

`Address` never asked the addressing library what a country requires, so four of its five
constraints skipped on a bare `{line1, country}` and an Australian address with no suburb,
state or postcode was **valid**. Fixing that meant renaming most of the field.

**What now fails that used to pass.** Each of these is a defect closed rather than a rule
added, so expect data you previously accepted to be rejected:

- An address missing a part its country requires — a suburb, state or postcode in Australia.
- Any part sent as `''` or as whitespace. It was provided, so it is not missing; it simply
  cannot be read, and treating it as absent let whitespace satisfy a requiredness check.
- A post-office box on the second line of a field that asked for somewhere visitable. The
  pattern read the first line only.
- A part the submitted country's format has no place for — a state for a country with none.
- A street of more than three lines. Every one of the 206 countries uses exactly three.
- An unrecognised country, and an unrecognised subdivision where the country requires one.
  Both are now *unreadable* rather than silently unvalidated: spelling a country in ISO 3166-1
  alpha-3 used to turn off postcode and subdivision checking altogether.

**The API.**

| Was | Now |
| --- | --- |
| `Address\Type` | Removed. It spanned two independent questions at once, which is why `Postal` had nothing to do at request time. |
| `allowOnlyMailable()` | Removed — it was inert. The default already accepts anything deliverable. |
| `allowOnlyPhysical()` | `mustBeVisitable()` |
| `allowWithoutStreet()` | `minPrecisionOf(Precision::Locality)` |
| `$field->type`, `$field->mustBeSpecific` | `$field->precision`, `$field->streetVisitable` |
| `constraints->named('x')->bound` | `$field->requirementsFor($country)[$country]->x` for anything a country decides; `$field->x` for configuration you set. |

**The parts**, which drop from eight to six:

| Was | Now |
| --- | --- |
| `line1`, `line2` | One part, `street`, holding a **list of strings**. This is what WHATWG's `street-address` token describes, and a list rather than delimited text because nothing here normalises — a separator would have to be either CRLF or LF, and HTML and JSON disagree. |
| `administrative_area` | `subdivision`, holding the **full ISO 3166-2 code**: `AU-QLD`, not `QLD`. `QLD`, `qld`, `AU-QLD` and `Queensland` are all accepted and all stored the same way. |
| `organization` | Removed. It identifies who is at a place rather than the place, which is why `givenName` and `familyName` were already absent. |

A key that is not a part is now refused by name. That is deliberate: a caller still sending
`line1` would otherwise build an address with no street at all and be told "street is
required", which names the symptom and hides the stale key.

**Constraints.** `specific` → `streetRequired`, `line1Visitable` → `streetVisitable`,
`administrativeArea` → `knownSubdivision`. New: `streetLineLimit`, `localityRequired`,
`subdivisionRequired`, `postalCodeRequired`, and four that report a part the submitted
country has no place for — `localityUsed`, `dependentLocalityUsed`, `subdivisionUsed`,
`postalCodeUsed`. There is no `streetUsed`: all 206 countries use a street. The generated message-key list
changes with them — `vendor/bin/schema-lang keys` prints the new set, and your `.mfr` packs
need updating. None are bundled here.

**Migrating stored addresses.** Three of these change persisted values, not just calls:

- `line1` and `line2` become the elements of a `street` list, dropping any that were empty.
- `administrative_area: 'QLD'` becomes `subdivision: 'AU-QLD'`.
- `organization` moves out of the address.

**If you write a port, you now have an obligation**: omit a part you have no value for, and
never submit `''`. An HTML form that posts empty strings for untouched inputs will make every
such address unreadable, and the requiredness constraints will never fire — the submitter gets
"this address cannot be read" instead of "suburb is required". Normalising request input was
already a port's job; this makes it a requirement. Since the core no longer normalises
anything, tidying is yours too: trimming, collapsing blank lines, and splitting a textarea into
the list. There is no standard normal form for an address line, so any rule you choose is a
presentation decision.

**Reading a country's rules before a request.** Every country-driven bound is unanswerable
while more than one country is allowed, and allowing any is the default, so a form with a
country selector cannot mark inputs required from the field alone:

```php
$field = $schema->createAddressField('shipping')->allowCountries('AU', 'NZ');

$rules = $field->requirementsFor('AUS');   // keyed by the spelling you asked with
$rules['AUS']->country;                    // 'AU' — the canonical code
$rules['AUS']->requiredParts;              // ['street', 'locality', 'subdivision', 'postal_code']
$rules['AUS']->subdivisions;               // ['AU-ACT' => 'Australian Capital Territory', …]
$rules['AUS']->postalCodeFormat;           // '\d{4}'
$rules['AUS']->postalCodeFormatOverrides;  // [] — subdivisions whose own pattern differs
$rules['AUS']->postalCodeFormatFor('QLD'); // '\d{4}' — the one that applies
$rules['AUS']->streetLineLimit;            // 3

$field->requirementsFor();                 // every country the field allows
```

Countries resolve through the same code path a submitted address takes, so the set you may ask
about is exactly the set the field accepts. It throws for a country outside the allow-list, for
one ISO 3166-1 does not know, and — on a field that allows any — for no arguments at all.

It also throws if you ask about one country twice under any spelling, so
`requirementsFor('au', 'AUS')` is refused rather than answered. Both outcomes of allowing it
would hide the mistake: one spelling repeated collapses to a single entry, making the result
quietly shorter than the question, and two spellings of one country give two keys holding the
same answer, so a loop over them does the work twice with nothing to show that it has.

**One honest limit.** The subdivision data is postal-address data, not the ISO 3166-2 register.
It stores the suffix, and for the United States carries 62 entries against ISO's 57 — adding
`AA`, `AE` and `AP`, which are military postal regions rather than ISO subdivisions, and
`MH`, `FM` and `PW`, which are sovereign states the USPS serves — while omitting `UM`. It
approximates ISO 3166-2 and deviates where postal delivery does.

**A subdivision may have its own postcode pattern**, and it *replaces* its country's rather
than narrowing it. 36 of 1548 do, across China and Colombia alone — `CN-MO` is the single code
`999078`, and `CN-TW` admits three to six digits where China admits exactly six. So a valid
three-digit Taiwanese postcode used to be rejected against `\d{6}`. Ask
`postalCodeFormatFor($subdivision)` for the pattern that applies; the two public properties it
reads are there so a consumer in another language can do the same with
`overrides[subdivision] ?? postalCodeFormat`.

Five countries also code their subdivisions by *name* rather than by an abbreviation, so
what `subdivision` holds for them is `HK-Kowloon`, `CV-Boa Vista` or `KY-Grand Cayman` —
the same `CC-XX` shape, but not an ISO 3166-2 code. `CV`, `HK`, `KY`, `RU` and `TV`.
Whatever `requirementsFor()->subdivisions` publishes is accepted back verbatim, which is
the property a port actually needs.

[examples/addresses.php](examples/addresses.php) runs all of this.
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
