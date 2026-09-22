# The 2.0 API

What the API *is*, and why each part of it is shaped that way.

This began as a review — a checklist with a `Status` column reading `open` on every row, written
while the design was still being argued. That is over. What is kept is the *reasoning*, because
that is the expensive part to reconstruct and the part a future change has to answer. Where a
decision only made sense during the transition from `1.x`, it is gone.

**The tables here are generated from the classes, and the tests are the authority.**
[`tests/Api/ConstraintNameTest.php`](../tests/Api/ConstraintNameTest.php) asserts the constraint
names for every field and [`tests/Api/NamingTest.php`](../tests/Api/NamingTest.php) the
configuration surface. Both run in the default suite. If this page and those tests disagree, the
tests are right.

## Contents

- [Three surfaces](#three-surfaces)
- [Defining a schema](#defining-a-schema)
- [The field surface](#the-field-surface)
- [How things are named](#how-things-are-named)
- [Baselines](#baselines)
- [Field decisions worth knowing](#field-decisions-worth-knowing)
- [Reading a result](#reading-a-result)
- [Constraints](#constraints)
- [Scopes](#scopes)
- [What was removed, and why](#what-was-removed-and-why)

---

## Three surfaces

Every feature is confirmed across three, which is the discipline that stopped them drifting apart.
A change to one is a change to all three, and naming them separately is what makes that obvious.

| Surface | The question | Example |
| --- | --- | --- |
| **Definition** | How does an author declare it? | `->minLengthOf(3)` |
| **Rule** | How does a scope reference it? | `#/fields/bio/minLength` |
| **Resolved** | How does it come back? | `$field->constraints->named('minLength')` |

The three names line up on purpose: `minLengthOf()` sets `$minLength` and reports under
`minLength`. Knowing one tells you the other two.

## Defining a schema

```php
use Meraki\Schema\Facade;

$schema = new Facade('signup');

$schema->add(
    $schema->createTextField('username')->minLengthOf(3)->maxLengthOf(20),
    $schema->createEmailAddressField('email'),
);
```

**Build, configure, then add.** A field is immutable, so every configuration method hands back a
copy and the finished one is what gets added:

```php
$field = $schema->createTextField('username');
$field->minLengthOf(3);            // configures nothing — the copy is discarded
$field = $field->minLengthOf(3);   // configures something
```

`create*Field()` builds and returns; `add()` registers. They used to be one call, and then
`$schema->addTextField('x')->minLengthOf(3)` started adding a field and configuring a *copy* of it,
leaving the schema holding the unconfigured original. Separating them makes the order impossible to
get wrong.

A schema declares a region once, and the region-aware fields inherit it:

```php
$schema = (new Facade('booking'))->for('AU');

$schema->createAddressField('billing');            // restricted to AU
$schema->createPhoneNumberField('mobile');         // ditto
$schema->createAddressField('shipping', ['NZ']);   // an explicit list still wins
$schema->createAddressField('other', []);          // and [] means free-form
```

Deliberately **not** `Money`: a currency does not follow from a region. A country may use several,
and the euro spans twenty.

## The field surface

| Field | Configuration | Constraint names | Value |
| --- | --- | --- | --- |
| `Address` | `allowCountries()`, `allowOnlyMailable()`, `allowOnlyPhysical()`, `allowWithoutStreet()`, `clearAllowedCountries()` | `allowedCountries`, `postalCodeFormat`, `administrativeArea`, `line1Visitable`, `specific` | `Address\Value` |
| `Boolean` | `mustBeAccepted()` | `accepted` | `Boolean\Value` |
| `Collection` | `allowDuplicates()`, `maxCountOf()`, `minCountOf()` | `minCount`, `maxCount`, `unique` | `Collection\Value` |
| `CreditCard` | `mustExpireInFuture()` | `numberFormat`, `numberChecksum`, `expiryFormat`, `expiryInFuture`, `expiryWithinReach`, `namePresent`, `securityCodeFormat` | `CreditCard\Value` |
| `Date` | `atIntervalsOf()`, `from()`, `until()` | `from`, `until`, `interval` | `Date\Value` |
| `DateTime` | `atIntervalsOf()`, `from()`, `until()` | `from`, `until`, `interval`, `precision` | `DateTime\Value` |
| `Duration` | `inIncrementsOf()`, `maxValueOf()`, `minValueOf()` | `minValue`, `maxValue`, `step` | `Duration\Value` |
| `EmailAddress` | `allowDomains()`, `clearAllowedDomains()`, `clearDisallowedDomains()`, `disallowDomains()`, `maxLengthOf()`, `minLengthOf()` | `minLength`, `maxLength`, `allowedDomains`, `disallowedDomains` | `EmailAddress\Value` |
| `Enum` | — | — | `Enum\Value` |
| `File` | `allowDocuments()`, `allowImages()`, `allowTypes()`, `allowVideos()`, `clearAllowedTypes()`, `clearDisallowedTypes()`, `disallowScripts()`, `disallowTypes()`, `maxSizeOf()`, `minSizeOf()` | `minSize`, `maxSize`, `allowedTypes`, `disallowedTypes` | `File\Value` |
| `Money` | `allowCurrencies()`, `clearAllowedCurrencies()`, `maxAmountOf()`, `minAmountOf()` | `allowedCurrencies`, `minAmount`, `maxAmount`, `scale` | `Money\Value` |
| `Name` | — | `minLength`, `maxLength` | `Name\Value` |
| `Number` | `inIncrementsOf()`, `maxPrecisionOf()`, `maxValueOf()`, `minValueOf()`, `scaleTo()` | `minValue`, `maxValue`, `step`, `scale`, `maxPrecision` | `Number\Value` |
| `Password` | `maxLengthOf()`, `minLengthOf()`, `minNumberOfDigits()`, `minNumberOfLowercaseChars()`, `minNumberOfSymbols()`, `minNumberOfUppercaseChars()`, `minStrengthOf()` | `minLength`, `maxLength`, `minStrength`, `minUppercaseChars`, `minLowercaseChars`, `minDigits`, `minSymbols` | `Password\Value` |
| `PhoneNumber` | `allowCountries()`, `clearAllowedCountries()`, `ofType()` | `allowedCountries`, `numberType` | `PhoneNumber\Value` |
| `Text` | `maxLengthOf()`, `minLengthOf()`, `mustMatch()` | `minLength`, `maxLength`, `pattern` | `Text\Value` |
| `Time` | `atIntervalsOf()`, `from()`, `until()` | `from`, `until`, `interval`, `precision` | `Time\Value` |
| `Uri` | `allowSchemes()`, `clearAllowedSchemes()`, `maxLengthOf()`, `minLengthOf()` | `minLength`, `maxLength`, `allowedSchemes` | `Uri\Value` |
| `Uuid` | `allowVersions()`, `clearAllowedVersions()` | `allowedVersions` | `Uuid\Value` |

**Shared by every field**, so not repeated above: `defaultsTo()`, `makeOptional()`,
`makeRequired()`, `equals()`, `resolve()`, `validate()`, `resolvedValueFor()`.

Two rows worth reading twice. **`Name` has no configuration at all** — its length bounds are a
baseline, not a dial. **`Enum` reports no constraints** — the list of cases *is* the type, so a
value outside it is a shape failure, the same way an unparseable string is for `Date`.

## How things are named

One rule, and it settles every argument about a method name: **say what is being measured.**

| | Before | Now |
| --- | --- | --- |
| Length of a string | `minOf(3)` → `$min` → `min` | `minLengthOf(3)` → `$minLength` → `minLength` |
| Magnitude of a number | `minOf(18)` → `$min` → `min` | `minValueOf(18)` → `$minValue` → `minValue` |
| Size of a file | `minFileSizeOf()` | `minSizeOf()` → `$minSize` → `minSize` |
| Rows in a collection | `minItems` | `minCountOf()` → `$minCount` → `minCount` |

`1.x` used bare `min`/`max` for all of them, so `min` meant characters on `Text`, magnitude on
`Number`, and seconds on `Duration`. A message provider matching on `min` either wrote one sentence
for all three — "value is too short", rendered for a number — or re-derived the meaning from the
field type. Both happened. The names now carry the dimension, so a provider matching `minLength`
knows it is characters without asking what field it came from.

`from`/`until` rather than `min`/`max` on the temporal fields, for the same reason: a date range is
not a magnitude, and `until` is exclusive in a way `max` does not suggest.

## Baselines

A baseline is what a field is **already** correct about, before anyone configures it.

```php
$password = $schema->createPasswordField('secret');
// minimum length is already 8 — NIST SP 800-63B's floor for a memorized secret

$password->minLengthOf(4);
// InvalidArgumentException: A minimum length of 4 is below the baseline of 8 characters...
```

**Configuration narrows; it never widens.** A field with no configuration is already correct, so
there is nothing an author can usefully get wrong. That is why `minLengthOf()` refuses to go below
the floor rather than accepting it.

Baselines come from **standards where one exists, and best practice where none does**. ISO 3166 for
countries, ISO 4217 for currency scale, ISO/IEC 7812 for card numbers, RFC 5321 for email — and
where no standard says it, current guidance does: eight characters is NIST's floor, not this
library's opinion, and `zxcvbn` measures strength rather than this library inventing an entropy
formula.

### Baselines do not serialise — and this will bite

A baseline follows from the field *type*, so a serialized schema does not carry it. Raise the
core's ceiling and every stored document gets the new one; write it into the document and every old
document goes on claiming the old one.

**That is right within one language and is an unsolved problem across several.** This library says
a password's floor is 8 because NIST does. A JavaScript port reading the same document could say 7
— through disagreement, or a bug — and the same definition would then accept different input
depending on who validated it, silently, with nothing in the document to compare against.

It is the most important unsolved problem here and it grows with adoption. Tracked in
[ROADMAP.md](ROADMAP.md).

`meraki/schema-json` wrote `min`/`max` for everything in `1.x`, so this is a real change in what a
document contains, not only a naming one.

## Field decisions worth knowing

The ones that look wrong until you know why — and that also show how to use the field.

### `Boolean::mustBeAccepted()` is field API, not a rule

It looks like conditional logic: *this checkbox must be true*. It was nearly moved to the rule
surface on that reading. It belongs to the field.

```php
$schema->createBooleanField('terms')->mustBeAccepted();
```

A rule makes one field's requirements depend on **another field's value**. This depends on nothing
— "must be true" is a fact about what this field accepts, in the same way a minimum length is. Put
it in a rule and every schema with a terms checkbox carries a rule whose condition is always true,
which is a constraint wearing a rule's clothes.

It does two things at once, and both are needed: required *and* `true`. Required alone accepts an
explicit `false`; the constraint alone accepts the field being left out.

**The general test:** if it depends only on this field's own value, it is a constraint. If it
depends on another field, it is a rule.

### `Enum` is a closed set, and `allow()` is gone

`1.x` had `Enum::allow($case)` to add a case after construction. Removed, for two reasons.

It was a mutator — `$this->oneOf[] = $value; return $this;` — which is the shared-state defect this
rewrite exists to remove. And nothing needed it: cases arrive at construction, including the
genuinely dynamic ones.

```php
// cases known only at runtime — still the constructor
$schema->createEnumField('participant', [...$participantIds, 'add_new']);
$schema->createEnumField('timezone', DateTimeZone::listIdentifiers(DateTimeZone::AUSTRALIA));
```

An enum is a closed set; that is what makes it an enum rather than a text field with a hint. If a
real case for widening one turns up it comes back as a wither returning a copy — which would
re-check the authored default for free — but inventing the capability first is how you end up with
an API nobody can explain.

### `Money` scale: usually don't, sometimes do

A currency's scale comes from ISO 4217, so you rarely say it:

```php
$schema->createMoneyField('price', ['AUD']);              // scale 2, from the standard
$schema->createMoneyField('price', ['JPY']);              // scale 0 — yen has no minor unit
$schema->createMoneyField('price', ['AUD' => 3]);         // override it
```

`1.x` made you pass the scale because it had no currency data. Now it has, so the default is right
and the override is for the case the standard does not cover: **a rate is not a price.** Fuel is
quoted to three decimal places, interest and exchange rates to more, and ISO 4217's scale is about
what you can *pay*, not what you can *quote*.

```php
$schema->createMoneyField('fuel_price', ['AUD' => 3]);    // 1.859 per litre
```

**This is the common mistake worth naming.** A rate looks numeric, so people reach for `Number`.
If it is money, use `Money` and adjust the scale — you keep the currency pairing, the per-currency
bounds, and comparison that knows `12.50` and `12.5` are one amount. A `Number` gives you none of
that and lets an amount travel without its currency.

### `Password` counts characters, not bytes

```php
$schema->createPasswordField('secret')->minLengthOf(12);   // 12 characters
```

`密` is one character and three bytes. A limit written in bytes quietly asks a third as much from
anyone not typing ASCII, so the domain language is characters and `mb_strlen()` is what counts
them.

`1.x` had `maxBytes`, defaulting to 72, because **bcrypt silently truncates there** — a hash of 72
`a`s verifies a string of 80. That is true and it is not this field's business. What a hashing
algorithm can swallow is a question about infrastructure, asked by the layer that hashes; putting
it here asked the author to tell the field something the field could not otherwise know, and
expressed an implementation detail as a rule about secrets. Pre-hash, or use Argon2id.

Composition *maximums* went too. There is no security argument for capping the number of digits in
a secret. The minimums survive because some policies still require them, and they are off by
default because current guidance discourages them: they shrink the search space while pushing
people toward predictable substitutions.

### `File` believes what it is told

```php
$file->validate((object) ['name' => 'cv.pdf', 'type' => 'application/pdf', 'size' => 1024, ...]);
```

`$type` is the MIME type the **client claimed**, not a verified one. `File\Value` says so in as
many words, and `allowedTypes` checks an assertion rather than a fact.

The library trusts it because it is all `$_FILES` offers, and because opening a path in the core
would tie it to one runtime's idea of where an upload lives. **The better shape is for the port to
hand over a handle** — a stream or an SPL file object — so the field can check the real size and
sniff the real type. On the roadmap. Until then, verify uploads yourself before trusting them.

### Everything stays singular

One field holds one value. There is no "multiple" mode on any field.

```php
// not this
$schema->createEmailAddressField('recipients')->multiple();

// this
$schema->createCollectionField('recipients', $schema->createEmailAddressField('email'));
```

`1.x` had an `AtomicMultiValue` and `EmailAddress` split its input on commas, both because
`<input type="email" multiple>` submits a comma-separated list. That is a *medium's* convention —
it is how one HTML control encodes several values — and encoding it into the domain meant an email
field behaved differently from every other field for reasons nothing in the domain explained.

**Converting is the port's job, and the port has choices the core should not make for it.** One
input with a comma-separated list, several inputs, or a repeatable section are all reasonable
renderings of the same collection, and a renderer picks. What the core guarantees is that you
always know what you are getting: one field, one value, one result shape — and a collection when
there are many.

### `PhoneNumber` and `Money` pair their value with its context

```php
$schema->validate((object) [
    'phone' => (object) ['number' => '0411 222 333', 'country' => 'AU'],
    'price' => (object) ['currency' => 'AUD', 'amount' => '12.50'],
]);
```

`0411 222 333` means nothing without a country and `12.50` means nothing without a currency, so
neither is accepted alone. `+61…` does not help either: the `+1` prefix covers **twenty-five**
regions, and libphonenumber answers `null` when asked which one a bare `+1` number is from.

### `Address` is one field, and the country is always submitted

An address used to be eight sub-fields registered in the schema's namespace. Now it is one field
holding one `Address\Value`, which is what killed the dotted constraint names.

The country is never filled in, even when exactly one is allowed. It used to be, and that rule
changed shape depending on how many countries were listed — an address was complete or incomplete
according to a detail of the field's configuration rather than according to what somebody sent.

```php
$billing = $schema->createAddressField('billing', ['AU']);

// Still needs 'country' => 'AU'. It may be a name or a code, in any case:
// 'AU', 'au' and 'Australia' are one country, stored as the code.
```

### `CreditCard` holds a clock, not a time

"Has this expired" needs *now*, and a field is built once and shared across every request after
that. So it holds a **source** of the instant:

```php
$schema = new Facade('checkout', clock: new FixedClock($instant));   // testable
$schema->createCreditCardField('card')->mustExpireInFuture();
```

Reading the date once into a property would start rejecting valid cards the day after the schema
was built. The instant actually used is on the result as `$evaluatedAt`, read **once per request**
rather than once per field, so two time-relative fields cannot disagree by microseconds.

## Reading a result

```php
$field = $schema->validate($data)->forField('email');
```

| | |
| --- | --- |
| `$field->given` | exactly what was submitted, unchanged |
| `$field->value` | what the field made of it — a `ParsedValue`, or `null` |
| `$field->source` | `Submitted` \| `Prefilled` \| `Default` \| `None` |
| `$field->shape` | could this be read at all? |
| `$field->constraints` | the constraint verdicts, on their own |
| `$field->status` | `Passed` \| `Failed` \| `Skipped` \| `Pending` |
| `$field->messages` | what to tell somebody, in the language the request asked for — empty unless a provider was registered |

### `given` and `value` mean one thing each

```php
$value = parse($whicheverSourceWon);   // or null
```

`$value` is **always valid, or nothing**. It never holds input the field could not read — that is
`$given`, which is the thing to echo into a form being redrawn.

It used to hold `parse($given) ?? $given`, so a failed parse left the raw input in `$value`. That
made the type a union of "the domain type" and "whatever arrived", so nothing downstream could rely
on it: a rule comparing `equals(18)` could have been handed the string `'abc'`.

### Simple by default, richer when you need it

`$shape` and `$constraints` each hold one kind of answer and each offer the full aggregate API. The
common readings have shorthand on the field, so you only reach for the objects when you want more:

```php
// the common case
$field->wasMissing();
$field->wasUnreadable();
$field->getFailedConstraints()->getFirst();

// when you need more
$field->constraints->allPassed();
$field->constraints->named('minLength');
foreach ($field->constraints as $verdict) { ... }
```

### Messages are applied to the verdicts, never fed into them

```php
$schema = new Facade('signup', messages: $provider);
$result = $schema->validate($data, locale: 'en-AU');
```

The provider is a *source* registered on the schema, like the clock. The **locale** is part of the
request, because that is the part that varies — so one schema serves every reader rather than being
defined once per language.

It also means a missing language cannot change an outcome: an unsupported tag, or none at all,
leaves every verdict as it was and every message set empty. A field validated on its own therefore
has no messages, because there is no schema to have carried a provider.

`$messages` is a [`FlatSet`](../src/Message/FlatSet.php) for a field holding one value and a
[`PartedSet`](../src/Message/PartedSet.php) for one whose value has named parts — decided by the
*field*, not by what happened to fail, so a consumer that checks the type once does not break on a
request that failed differently. See [MESSAGES.md](MESSAGES.md).

### Default versus prefill

Two different things that both fill a field in, and keeping them apart is what makes "a serialized
schema can never contain user data" true by construction.

| | **Default** | **Prefill** |
| --- | --- | --- |
| What it is | a constant the author typed | one user's data, looked up for this request |
| Where it lives | on the definition | passed to `validate()` |
| Who gets it | everyone | this request only |
| Serialises? | yes | **never** — it never touches the definition |
| Checked when? | where it is declared | per request |

```php
$schema->createTextField('nickname')->defaultsTo('anonymous');       // everyone

$schema->validate($submitted, prefilledWith: (object) [
    'username' => $user->username,                                   // this person
]);
```

The example that makes the distinction concrete: a **nickname** can be changed, so it takes a
default and the user may overwrite it. A **username** cannot, so it is prefilled from the database
for this request and never written into the schema. Precedence is submitted, then prefilled, then
the default — and `$field->source` says which won, so a form can mark a prefilled field differently
from one the user typed into.

`1.x` had `prefill()`, which wrote the values onto the fields. A schema shared across requests
handed one user's details to the next.

### Trust

An **authored default is trusted by construction**: it is checked against the field's own shape and
constraints where it is written, so a request never re-checks it. That is why `defaultsTo()` throws
at authoring time rather than failing at validation time.

**Submitted input is never trusted.** It is parsed and checked every time.

**A prefill is trusted only if you say so**, because the library cannot know where you got it:

```php
// default — the value still has to satisfy the field
$schema->validate($data, prefilledWith: $known);

// the rules are waived for a value that survives as a prefill
$schema->validate($data, prefilledWith: $known, policy: PrefillPolicy::Trusted);
```

`Checked` is the default and is usually right: a constraint tightens, and stored values that no
longer satisfy it should surface so the user can fix them. `Trusted` is for a value the application
vouches for and the user was never asked about — but note the shape still has to pass. Trust says a
value meets the *rules*, not that the field can read it.

## Constraints

A failed constraint carries everything a message needs, so a message provider is a lookup rather
than a parser:

```php
$failed->name;    // 'minLength'     — what was checked
$failed->part;    // 'postal_code'   — which piece of a structured value, or null
$failed->bound;   // 3               — the limit, ready to interpolate
```

No name carries the field it came from. `postalCodeFormat`, not
`billing_address.postal_code.format` — so renaming a field changes nothing downstream.

See [DESIGN.md](DESIGN.md#a-failed-constraint-says-everything-a-message-needs) for why each of the
three exists.

### A bound is a literal

`string | int | float | bool | list<string> | null`, and never a domain object.

```php
$field->constraints->named('minLength')->bound;   // 3, not a wrapper
$field->constraints->named('from')->bound;        // '2026-01-01', not a LocalDate
```

A bound is the thing a message interpolates, and interpolating a `LocalDate` means a consumer
either calls a method on it or gets whatever `__toString()` decided. More importantly, **the same
schema is meant to be implemented in more than one language**, and a bound expressed as a
`BigDecimal` is a PHP answer to a question a JavaScript or Rust port has to answer too. A literal
is the same thing everywhere, and everyone already knows how their own integers behave.

`list<string>` is in there for the allow-lists — `allowedCountries`, `allowedSchemes` — because a
message naming them has to name all of them.

## Scopes

Four kinds, and the segment count says which:

| Scope | Names |
| --- | --- |
| `#/fields/nickname` | the field — what an outcome acts on |
| `#/fields/nickname/value` | what it was given, parsed |
| `#/fields/age/minValue` | a public property of the definition |
| `#/fields/billing/value/country` | one part of the value |

A **part** belongs to a value, so it goes under `value`. The short form
`#/fields/billing/country` reads better and is ambiguous: the third segment already means a
definition property, and `#/fields/card/name` could be the field's name or the cardholder's.

Only a value that says it has parts can be read into — [`Field\HasParts`](../src/Field/HasParts.php).
Part names are the keys submitted input uses, which are also what a constraint reports as
`$constraint->part`, so there is one vocabulary rather than three.

### Writing a rule

```php
$schema->addRule(
    $parcelWeight->when()->isAtLeast(Weight::of('5.00', 'kg'))
        ->then($insurance->makeRequired()->mustBeAccepted())
        ->else($insurance->makeOptional()),
);
```

Both halves put the **field on the left**, and that is the whole of why either is type-safe. PHP
cannot vary a return type by argument, so `when($field)` and `then($field)` could never hand back
anything that knew what kind of field it had been given. `$field->when()` and
`$field->makeRequired()` both can, because the type flows from the receiver.

### The matchers

| Matcher | Holds when | Offered by |
| --- | --- | --- |
| `equals` | it is that value | every field |
| `notEquals` | it is anything else | every field |
| `isIn` | it is any one of those | every field |
| `isEmpty` | nothing was submitted for it | every field |
| `isNotEmpty` | something was | every field |
| `isAtLeast` | it is that or after — **inclusive** | ordered |
| `isGreaterThan` | it is strictly after | ordered |
| `isAtMost` | it is that or before — **inclusive** | ordered |
| `isLessThan` | it is strictly before | ordered |
| `isBetween` | it is within both — **inclusive at both ends** | ordered |
| `contains` | its text holds that text | text |
| `matches` | its text matches that pattern | text |

**A field offers only what its value can answer**, and `Field::when()` is what says so. There are
four matchers, one per capability set, and a field's declaration picks one:

| Matcher | Fields |
| --- | --- |
| [`Matcher\Basic`](../src/Rule/Matcher/Basic.php) | Address, Boolean, Collection, CreditCard, Enum, File, Password |
| [`Matcher\Ordered`](../src/Rule/Matcher/Ordered.php) | Money |
| [`Matcher\Text`](../src/Rule/Matcher/Text.php) | EmailAddress, Name, PhoneNumber, Text, Uri, Uuid |
| [`Matcher\OrderedText`](../src/Rule/Matcher/OrderedText.php) | Number, Date, DateTime, Time, Duration |

So `$notes->when()->isAtLeast(3)` is a **call to a method that is not there** — absent from
completion, refused by PHPStan, fatal at runtime. That is the difference between this and one
matcher with a `mixed` bound, which can only refuse the same mistake once the rule is being added.

Which set a field gets follows from its value: *ordered* means the value implements
[`Comparison\Comparable`](../src/Comparison/Comparable.php), *text* means it is `Stringable`. A
test asserts every field's declaration against its value's actual capabilities, so the nineteen
one-line declarations cannot drift.

`Password` and `CreditCard` have no string form **on purpose**, so neither can be pattern-matched
by a rule — a rule reading the text of a secret should be hard to write by accident. They are
the only two, and the reason is that specific one rather than "the value is internal".

`$schema->when('age')` still works, for a field named by string or a scope pointing at a part. It
cannot resolve a type, so it answers with all twelve and leans on the check that runs when the
rule is added.

The five ordered matchers are `Comparable::compareTo()` and one question put to the `Order` it
returns, so a new orderable value type gets all five without touching them. `isBetween` is
inclusive because it *holds* an `isAtLeast` and an `isAtMost` and asks both — inherited rather
than chosen, so it cannot drift from theirs.

**`contains` is text only.** A collection's rows are records, and `contains('SKU-1')` has no
honest reading over a record — the needle would have to name a field as well as a value.

**`isEmpty` is not `equals(null)`.** A null expectation means the field's *authored default*,
deliberately, so on a field with one they ask different questions.

### The outcomes

There is one: the field, configured. `then()` and `else()` take a field you have put through its
own withers, and the rule records the **difference** between it and the one on the schema.

```php
->then($terms->makeRequired()->mustBeAccepted())
->then($discount->maxValueOf(50))
```

So every configuration method a field has is already a rule outcome, including on a field type
this library has never heard of — there is no `thenRequire()` and no list of outcome classes to
extend. Storing the difference rather than the field is what makes two rules touching one field
merge instead of clobbering, and what makes an outcome serialisable.

`thenIgnore()` is the one named verb left, because ignoring is about a *request* — the input never
reaches the field — rather than about the definition, so no wither expresses it.

**An expectation can be another scope**, which is what lets a rule compare two fields:**An expectation can be another scope**, which is what lets a rule compare two fields:

```php
$schema->when(ValueScope::of('shipping'))->equals(ValueScope::of('billing'));

$schema->when(PartScope::of('shipping', 'country'))
    ->equals(PartScope::of('billing', 'country'));
```

**Collection items are not addressable.** Which row `0` is depends on what was submitted, so a
stored rule naming one would mean a different row on a different request.

### Scopes reach properties, never methods

A property scope addresses a **public property**, and every public property is addressable with no
exceptions list. That is a real versioning commitment: renaming `Text::$minLength` breaks any
stored rule addressing `#/fields/x/minLength`. It is also why the three surfaces are named
consistently — the property *is* the API.

## What was removed, and why

| Gone | Why |
| --- | --- |
| `Composite` | A field made of other fields with exactly one of each — which is a collection of length one, so it earned its own type only by being less general. `Address`, `Money` and `CreditCard` each hold one value object instead. |
| `Variant` | A union type, whose only use anywhere was making `Password` and `Passphrase` appear as one field. Those merged. It can come back if a real union turns up. |
| `Passphrase` | Absorbed into `Password`. Most people do not know the difference, current guidance treats both as one thing, and the only real distinction was how strength is measured — which is now `minStrengthOf()`. |
| `Placeholder` | A spacer with no meaning of its own. Pure presentation. |
| `AtomicMultiValue` | See [Everything stays singular](#everything-stays-singular). |
| The `type` constraint | "Is this the right kind of thing" is the *shape*, asked before the constraints and reported separately. Listing it among them is what let it be mistaken for one. |
| `transformed` | The parsed value is already the typed value. Every field parses to a value object this library defines, so there is no second property to populate. |
| `pairWith()` | A field method that added another field to the schema and registered rules — every part of it a schema operation wearing a field's clothes. It needed a back-reference from field to schema, which was a defect in its own right. |
| Presets | `Password::strong()` and friends were never built. A preset is a named bundle of calls an author can write themselves; naming the bundles before there is usage to name them from is how you get `moderate()` with nobody able to say what it means. |

