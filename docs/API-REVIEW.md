# API review for 2.0

Every feature and every constraint has to be confirmed across **three surfaces** before the
`2.0.0` API freeze. This page is the working checklist; nothing ships unconfirmed.

| Surface | The question it answers | What it means now |
| --- | --- | --- |
| **Definition** | How does an author declare it? | `->minLengthOf(3)` on a sealed field, via withers |
| **Rule** | How does a matcher reference it? | `PropertyScope::of('age', 'min')` — `#/fields/age/min`. The property segment is a real public property, and `addRule()` rejects one that is not |
| **Resolved** | How does it appear after validation? | The constraint name on a `ConstraintValidationResult`, and the `transformed` type on the `ResolvedField` |

A row is **confirmed** only when all three are settled and consistent with the rest of the
table. Until then it is **open**.

**This has to happen before the structured types are rewritten.** `meraki/schema-html`
matches on constraint names in three different ways — comparing the string, splitting it on
dots, and reading `$field->{$constraint->name}` as a property — so renaming after that
package is migrated means rewriting it twice.

## Why this is not a formality

The current definition surface grew field by field, and it shows. Six spellings of
"minimum" are in use right now:

| Spelling | Used by |
| --- | --- |
| `minLengthOf()` | `Text`, `Name`, `EmailAddress`, `Uri`, `Password` |
| `minOf()` | `Number`, `Duration`, `Money` |
| `atLeast()` | `File` (count) |
| `minFileSizeOf()` | `File` (size) |
| `minItems()` | `Collection` |
| `minNumberOfLowercase()` | `Password` |

And the same name means different things in different places:

| Name | Meaning |
| --- | --- |
| `allow()` | allowed countries (`Address`, `PhoneNumber`), allowed options (`Enum`), allowed currencies (`Money`) |
| `min` / `max` | *length* on `Text`, `Name`, `EmailAddress`, `Uri`; *value* on `Number`, `Duration` |
| `until()` / `to()` | the same thing on `Date` — two names for one constraint |
| `inIncrementsOf()` / `atIntervalsOf()` | the same idea on `Time`/`Duration`/`Number` vs `Date` |

Because constraint names are public API — every downstream message provider matches on
them — these have to be settled before the freeze, not after.

### What the inconsistency already costs

This is not hypothetical. Reading the names each field actually emits and comparing them
against what `meraki/schema-html` matches turns up branches that can never fire:

| Field | Renderer matches | Field emits |
| --- | --- | --- |
| `Date` | `min`, `max` | `from`, `until`, `interval` |
| `Time` | `min`, `max` | `from`, `until`, `step` |
| `DateTime` | `min`, `max` | `from`, `until`, `interval` |
| `Uuid` | `min`, `max` | `version` |
| `Password` | `min`, `max` | `length`, `lowercase`, `uppercase`, `digits`, `symbols`, `anyOf` |
| `Passphrase` | `min`, `max` | `entropy`, `dictionary` |
| `Money` | `min`, `max` | `<name>.amount.{scale,min,max,step}` |
| `CreditCard` | `min`, `max` | `<name>.number.checksum` |

The same split produced nine `DateTest` assertions that named `min`/`max`, were never
reported, and so asserted nothing at all for as long as they existed — found only when the
test helper was made to fail on an unreported constraint.

And it reaches users. A money field below its minimum:

```php
$schema->addMoneyField('cost', ['AUD' => 2])->minOf('AUD', '10.00');
$schema->validate(['cost' => ['currency' => 'AUD', 'amount' => '5.00']]);
```

fails `cost.amount.min` and renders **"Value must be a number"**. The dotted name does not
match the renderer's bare `min`, and the dot-splitting path only recognises `visitable`,
`format`, `allowed` and `required` — so it falls through to a default written for a
different problem. The user is told their input is not a number when it is.

## Removing the `type` constraint

**Decided.** `type` stops being reported as a constraint.

It never was one. Every other constraint narrows a value that is already the right shape;
`type` decides whether there *is* a value of the right shape, which is the precondition
for all of them. Reporting it alongside `min` and `pattern` puts a precondition in the
same list as the things that depend on it.

Worse, it currently conflates three different outcomes under one name:

| What actually happened | Reported today |
| --- | --- |
| No value supplied for a required field | `type` failed |
| A value was supplied, but of the wrong shape | `type` failed |
| The value is the right shape but breaks a limit | the relevant constraint failed |

Downstream has to guess which of the first two it is. `meraki/schema-html` does exactly
that, with a comment apologising for it:

```php
// A required field left empty fails its *type* check, because there is no value of
// the right type to find. Reporting that in terms of types ("A valid text must be a
// string") describes the mechanism rather than the problem.
if ($constraint->name === 'type' && !$field->hasValue()) {
    return 'This is required';
}
```

Under the new model the three become structurally distinct on `ResolvedField`: a value
was missing, a value was present but unusable, or constraints ran and some failed. A
message provider asks the resolved field which case it is instead of pattern-matching a
constraint name.

**Consequences to work through when this lands:**

- The constraint-name table in the README drops `type` from every row.
- `ValidationMessages::messageFor()` loses its `type` special case and gains a check on
  the resolved field.
- `Composite`, `Collection` and `Variant` each construct `type` results directly and will
  need reworking.
- Skip semantics stay: when the shape is unusable, constraints are skipped rather than
  failed.

## The field definition contract

The shape a field author writes to, and the division of responsibility between a field and
the core, are settled separately in **[FIELD-API.md](FIELD-API.md)**. The rows below are
about naming; that document is about the contract.

## The naming rule

Derived from the review as it went, and applied to every row below rather than re-argued
per field.

1. **A property is named for what it bounds, in the domain's own word.** `minLength`, not
   `min` — `$text->min` answers no question anyone asks. `minValue` on a number,
   `from`/`until` on a date, `minItems` on a collection, `minSize`/`minCount` on a file.
   Temporal types do not say "min" and "max" at all.
2. **A constraint reports its own bound; it does not expect to be looked up.**
   This started as "the constraint name is the property name", because
   `meraki/schema-html` reads `$field->{$constraint->name}` in 31 places to get the number
   into the message. That coupling is now removed rather than formalised — see
   [Constraints carry their bound](#constraints-carry-their-bound). Names should still read
   as the thing they constrain, because a scope addresses properties by name, but nothing
   depends on an exact match any more.
3. **Reading is a property, changing is a method.** State is read through a public property
   with `private(set)` visibility; anything that changes the definition is a method named
   for the change it makes, usually `<property>Of(…)` or a `must…` statement where that
   reads better.
4. **`null` means unset.** Not `PHP_INT_MAX`, not `0`, not an empty string. A sentinel that
   is a real value cannot be told apart from someone declaring that value.

The exception this rule has to survive is that the *domain's* word wins over the pattern.
`from`/`until` are not `minDate`/`maxDate`, and a fortnightly recurrence is an interval
rather than a step.

## Proposed API

Everything the rule settles without a judgement call. Awaiting confirmation; the fields it
does not settle are listed under [Still to decide](#still-to-decide).

Read each row as **method → property → constraint name**, where the last two are the same
string by [rule 2](#the-naming-rule).

### Length-bounded strings

`Text`, `Name`, `Uri`, `EmailAddress` all bound a *length*, and all currently call it
`min`/`max`.

| Current | Proposed |
| --- | --- |
| `minLengthOf()` → `$min` → `min` | `minLengthOf()` → `$minLength` → `minLength` |
| `maxLengthOf()` → `$max` → `max` | `maxLengthOf()` → `$maxLength` → `maxLength` |

`$maxLength` becomes `?int`, defaulting to `null` rather than `PHP_INT_MAX` — the sentinel
serialises into every document as `9223372036854775807` and is useless as a rule target.

### Value-bounded quantities

`Number` and `Duration` bound a *value*.

| Current | Proposed |
| --- | --- |
| `minOf()` → `$min` → `min` | `minValueOf()` → `$minValue` → `minValue` |
| `maxOf()` → `$max` → `max` | `maxValueOf()` → `$maxValue` → `maxValue` |

### Temporal bounds — already correct

`Date`, `Time` and `DateTime` keep `from()` / `until()` → `$from` / `$until` → `from` /
`until`. This is the model the rest of the table is being brought in line with, not an
exception to it.

`Date::to()` is **removed**. It is not a second name for `until()` — it is inclusive where
`until()` is exclusive, and both report under `until`, so a result cannot say which was
declared and a message cannot be phrased correctly. Half-open `[from, until)` composes;
an author wanting an inclusive bound writes `until($end->plusDays(1))`, which says so.

### Stepping — split by what is being stepped

A point in time recurs at an *interval*; a quantity moves in *steps*.

| Field | Current | Proposed |
| --- | --- | --- |
| `Date` | `atIntervalsOf()` → `$interval` → `interval` | unchanged |
| `Time` | `inIncrementsOf()` → `$step` → `step` | `atIntervalsOf()` → `$interval` → `interval` |
| `DateTime` | `inIncrementsOf()` → `$interval` → `interval` | `atIntervalsOf()` → `$interval` → `interval` |
| `Number` | `inIncrementsOf()` → `$step` → `step` | unchanged |
| `Duration` | `inIncrementsOf()` → `$step` → `step` | unchanged |

`DateTime` is the one whose method and property disagree with each other today.

### Already correct — no change

| Field | Names |
| --- | --- |
| `Collection` | Properties and constraint names are correct. The **methods** are not: `minItems(3)` and `$field->minItems` are the same identifier for a setter and a reader — see below |
| `File` | `$minCount`, `$maxCount`, `$minSize`, `$maxSize`, `$allowedTypes`, `$disallowedTypes` — every property already equals its constraint name |

### Alignments the rule forces

Properties and constraint names that disagree today, with no judgement needed:

| Field | Current property | Current constraint | Proposed (both) |
| --- | --- | --- | --- |
| `Uri` | `$allowedSchemes` | `scheme` | `allowedSchemes` |
| `Uuid` | `$versions` | `version` | `allowedVersions` |
| `PhoneNumber` | `$allowed` | `allowedCountries` | `allowedCountries` |
| `PhoneNumber` | `$allowedType` | `numberType` | `numberType` |

### Method names brought in line

| Field | Current | Proposed | Why |
| --- | --- | --- | --- |
| `Text` | `matches()` | `mustMatch()` | a mutator named for the rule it states, as `Boolean::mustBeAccepted()` already is |
| `EmailAddress` | `allowDomain()`, `disallowDomain()` | `allowDomains()`, `disallowDomains()` | they take arrays and set plural properties |
| `File` | `atLeast()`, `atMost()` | `minCountOf()`, `maxCountOf()` | say what is being counted |
| `File` | `minFileSizeOf()`, `maxFileSizeOf()` | `minSizeOf()`, `maxSizeOf()` | match `$minSize` / `$maxSize` |
| `Uuid` | `restrictToVersion()` | `allowVersions()` | plural, and matches the property |
| `Time`, `DateTime` | `precisionMode()` | *removed* | a getter for `$precision`, which is already public |

### Constants

`Text::SKIP_MATCHING` (`= null`) is dropped. With a nullable parameter it earns nothing.

### Collection arguments are variadic

Any method taking a set takes it variadically, with the first required:

```php
$field->allowDomains('hotmail.com', 'gmail.com');
$field->disallowDomains(...$blacklist);
```

Applies to `allowDomains`, `disallowDomains`, `allowSchemes`, `allowVersions`,
`PhoneNumber::allow`, `File::allowTypes`/`disallowTypes` and `Password::satisfyAnyOf`.

### The baseline principle

A field encodes the current best practice for its type — the RFCs, the standards, the
published guidance — and configuration **narrows** from there. It never widens.

Two consequences the rest of the table has to respect:

- A field with no configuration is already correct. Getting security right is not something
  an author has to opt into.
- An option that loosens the baseline does not belong. Offering four email strictnesses or
  a password minimum below the recommended floor inverts the principle.

### Settled in review

| Field | Decision |
| --- | --- |
| `Enum` | The list **is** the type, so it is checked as shape and emits no constraint — which is what the code already does. `$oneOf` → `$cases`. `allow()` is **removed**: a type is not extended after it is declared. |
| `Boolean` | `mustBeAccepted()` stays a constraint, but stops calling `require()` — it silently makes the field required today, so `makeOptional()->mustBeAccepted()` does not do what it reads like. |
| `Password` | `Password\Range` is dropped for flat `?int` properties (`$minLength`, `$maxLength`, `$minLowercase`, …). A `Range` cannot satisfy the property-equals-constraint rule, and today a failure reports `digits` without saying whether the floor or the ceiling was missed. |
| `Password` | Presets stop enforcing composition rules by default. Current guidance recommends against requiring character classes; they stay available for compliance regimes that still demand them. |
| `Passphrase` | `getConstraints()` → protected, as everywhere else. `$method` → `$entropyModel`. |
| `Number` | `scale` becomes a **constraint** rather than a shape check, so `$scale` equals its constraint name and a too-precise value says so instead of "must be a number". |

#### Why `Enum::allow()` can go

It exists for one caller: `Money::allow('AUD', 2)` appends to the internal currency enum
([Money.php:70](../src/Field/Money.php#L70)). Once fields are immutable, that wither returns
a new `Money` built with the extended currency list, constructing a fresh `Enum` — so
growing an enum in place is no longer needed to support it.

#### The three things `Number::$scale` currently collapses

| Concern | Belongs in | Today |
| --- | --- | --- |
| Is it a number? | shape | `validateValue()` ✓ |
| Is it representable at this scale without loss? | a `scale` constraint | `validateValue()` ✗ |
| Express it at that scale (`123` → `123.00`) | `cast()` | `validateValue()` ✗ |

The middle one is why a scale-2 field rejects `123.456` with "must be a number".
### Scopes reach properties, never methods

A scope addresses state. It does not call anything, and the reason is not taste:

- **A scope path arrives from untrusted JSON.** `RuleSerializer` rebuilds rule targets from
  a document, so a path is attacker-controlled input. Reaching properties is guarded by
  `property_exists()` and `NOT_ADDRESSABLE`; reaching methods would let a crafted document
  invoke any zero-argument method on a field.
- **Resolution must be a pure read.** A method may have side effects, and the whole seam
  exists to stop a rule condition writing to a shared definition.
- **A path cannot carry arguments**, so only zero-argument methods would work — and a
  zero-argument method that answers a question should have been a property anyway.

When the answer is computed rather than stored, a property hook gives you the question
*and* keeps it state, which is already the idiom in `ResolvedField::$transformed` and
`AggregatedValidationResult::$status`:

```php
public bool $requiresAcceptance { get => /* … */; }
```

### Normalisation follows the standard, and is never configurable

Every field normalises to some degree — `123` and `123.00` are the same number, `EXAMPLE.COM`
and `example.com` are the same domain. That is part of what the type *means*, so it is not
an option an author sets. It belongs in `cast()`.

The invariant that makes it safe: **normalisation may only remove a distinction the
standard says is not a distinction.** Lowercasing a domain is lossless because DNS is
case-insensitive; lowercasing an email's local part is not, because RFC 5321 permits it to
be case-sensitive — which is why `EmailAddress` normalises one half and not the other.
Scaling `123` to `123.00` is lossless; scaling `123.456` to two places is not, and so it
fails rather than rounding.

### `Password` absorbs `Passphrase`

One field. Most people do not know the difference, both are "memorized secrets" in the
guidance, and the distinction was only ever *how strength is measured*.

| Concern | Where it lands |
| --- | --- |
| Is it a string? | shape |
| Strength tier | a constraint — a weak password is a well-formed string that failed a judgement, not a malformed one |
| Composition rules | constraints, available but off by default |
| Minimum length | 8, per current guidance |
| Maximum length | **72 bytes**, and the reason is measurable |

`password_hash()` defaults to bcrypt, and bcrypt **silently truncates at 72 bytes** — on PHP
8.5 a hash of 72 `a`s verifies a string of 80 `a`s. A field that accepts more than 72 bytes
is telling the user their extra characters count when they do not. Note *bytes*, not
characters: a multibyte passphrase reaches the limit sooner than its length suggests.
### Baselines are read-only properties; configuration is `private(set)`

A baseline the consumer needs to see is exposed as a property backed by a constant, with no
setter:

```php
/** bcrypt truncates beyond this, and password_hash() defaults to bcrypt. */
public const MAX_BYTES = 72;

/** The ceiling the hashing algorithm imposes. No setter exists. */
public int $maxBytes { get => self::MAX_BYTES; }

/** Characters. The author may narrow this, never widen it. */
public private(set) ?int $maxLength = null;
```

The hook rather than a bare constant is what keeps the property-equals-constraint rule: a
message can interpolate the number and `#/fields/password/maxBytes` resolves. And it is
read-only *by construction* — the absence of a setter **is** the difference between a
baseline and something an author may narrow, so "changing this needs a core release" is
enforced rather than documented.

**Baselines do not serialise.** They follow from the field type, so writing them into a
document is redundant and lets stored data disagree with the code: raise the core's ceiling
and every old document still claims the old one. Expose them, do not persist them.
`meraki/schema-json` writes `min`/`max` today, so this changes how it treats field config.

#### Length in characters, ceiling in bytes

Both are needed, because one cannot express the other:

| Input | `maxLength` = 64 | `maxBytes` = 72 |
| --- | --- | --- |
| 80 ASCII characters | fails | fails |
| 64 CJK characters | passes | **fails** — 192 bytes |
| 40 emoji | passes | **fails** — 160 bytes |

Lengths are counted with `mb_strlen()`, which is already consistent across every field, so
they are code points rather than bytes or grapheme clusters. The byte ceiling is the case
that would otherwise be silently truncated by the hash.

The message for a byte failure is awkward — "too long; some characters take more space than
others" — and `maxLength` cannot be tuned low enough to make it unreachable without
capping passwords at 18 characters. The awkward message is the better trade.

### Password strength tiers

Five, as entropy thresholds:

| Tier | Bits | Stands for |
| --- | --- | --- |
| `weak` | ~36 | crackable offline quickly |
| `moderate` | ~60 | resists casual offline attack |
| `strong` | ~80 | the sensible default |
| `paranoid` | ~100 | deliberate overkill |
| `cryptographic` | ~128 | key-equivalent |

`Password`'s current `common` and `none` go: a tier meaning "no strength requirement"
contradicts the baseline principle.
### `Password` keeps its name; `Variant` and `satisfyAnyOf()` go

**The merged field is `Password`.** It is the term people know and search for; a passphrase
is a style of password rather than a different thing, and `MemorizedSecret` is precise but
obscure enough that nobody would look for it.

**`Variant` is removed.** Its only use across all three packages is the
`Password | Passphrase` union ([RoundTripTest.php:266](../../schema-json/tests/RoundTripTest.php#L266)),
which the merge eliminates. Keeping it means carrying `__get()` magic, prefixed sub-names, a
duplicate-type guard and a `Composite|Variant` union on `CompositeValidationResult` for a
capability with no caller. It can return when there is a second use case.

**`satisfyAnyOf()` is removed**, and it closes a defect on the way out. It means "at least
one of these named constraints must pass" — `common()` used it for *a digit or a symbol* —
and it is implemented as a small rule engine inside the field: a constraint belonging to an
`anyOf` group returns `null` when it fails, deferring the verdict to a separate `anyOf`
constraint that runs afterwards and reads a `private bool $anyOfPassed` which each of them
mutates as a side effect.

That property is defect **C4**, and it is still live — after `Password::common()->validate()`
the field's `$anyOfPassed` reads `true`. It is the same shape as B7 and B9 and the last
mutable validation state on any field, so removing `satisfyAnyOf()` closes C4 rather than
leaving it as separate work.

The feature elaborates composition rules that current guidance discourages, and its only
caller is the `common()` preset already being dropped with the tier list.

### `Password` composition methods

Explicit methods rather than a group mechanism. An adjective takes a noun; a noun stands on
its own.

| Method | Property / constraint |
| --- | --- |
| `minLengthOf()`, `maxLengthOf()` | `$minLength`, `$maxLength` |
| — (baseline, no setter) | `$maxBytes` |
| `minNumberOfUppercaseChars()`, `maxNumberOfUppercaseChars()` | `$minUppercaseChars`, `$maxUppercaseChars` |
| `minNumberOfLowercaseChars()`, `maxNumberOfLowercaseChars()` | `$minLowercaseChars`, `$maxLowercaseChars` |
| `minNumberOfDigits()`, `maxNumberOfDigits()` | `$minDigits`, `$maxDigits` |
| `minNumberOfSymbols()`, `maxNumberOfSymbols()` | `$minSymbols`, `$maxSymbols` |

Ten flat `?int` properties in place of five `Range` objects. Each carries its own constraint
name, so a failure says whether the floor or the ceiling was missed — which the `Range`
shape could not, since one constraint name covered both ends.
### Confirmed this round

| Item | Decision |
| --- | --- |
| `Rule\Outcome\_Require` | **`MakeRequired`**, pairing with `MakeOptional`. `class Require {}` is still a parse error on PHP 8.5 — namespaces accept reserved words, class names do not — so the keyword is avoided rather than worked around. `Field::require()` becomes `makeRequired()` to match. |
| `Collection` | `minItems()` → **`minCountOf()`** → `$minCount`. Unambiguous now that `File` has no count of its own. |
| `Password` presets | The five static constructors go, replaced by **`minStrengthOf(Strength::Strong)`** — a method and an enum, matching the preference for literals and enums, and reading as the floor it is. |
| `Composite` | Removed outright. The one real use — a repeatable list of multi-field items — is already what `Collection` does: it takes a template of several fields and validates each item against all of them. |

### Confirmed: the small items

| Item | Decision |
| --- | --- |
| `Field\Set::getByName()` | Returns `null`. Whether a missing field is an error is the caller's judgement, not the collection's. |
| `DateTime::withSecondPrecision()` and friends | Removed. Precision is a constructor argument and already an enum, so the three static factories are sugar over `new DateTime($name, Precision::Seconds)`. |
| `Facade::addXField()` | Becomes **`createXField()`** plus an explicit add — the call creates a field, it does not add one. |
| Rule vocabulary | **Deferred.** A stage of its own, after the field API is finished. |

### `File` method names

| Current | Becomes |
| --- | --- |
| `atLeast()`, `atMost()` | `minCountOf()`, `maxCountOf()` |
| `minFileSizeOf()`, `maxFileSizeOf()` | `minSizeOf()`, `maxSizeOf()` |

Properties and constraint names are already correct and do not move.
## Structured types

`Composite` is removed. `Address`, `Money` and `CreditCard` each become a **single field
holding a single value object**, the way `File` already holds a `File\Metadata`. Input is an
array or the value object; `cast()` normalises to the object. There are no sub-fields.

```php
$schema->addAddressField('billing')->allowCountries('AU');

$schema->validate(['billing' => [
    'line1'        => 'PO Box 42',
    'locality'     => 'Rockhampton',
    'postal_code'  => '470',        // AU postcodes are four digits
    'country_code' => 'AU',
]]);
```

### Constraint names lose the dots *and* the field name

| Today | Becomes | Part |
| --- | --- | --- |
| `billing.country_code.allowed` | `allowedCountries` | `countryCode` |
| `billing.postal_code.format` | `postalCodeFormat` | `postalCode` |
| `billing.line1.visitable` | `line1Visitable` | `line1` |
| `cost.amount.min` | `minAmount` | `amount` |
| `cost.amount.scale` | `scale` | `amount` |

Dropping the field name matters more than dropping the dots. Today a constraint name embeds
the field it came from, so renaming `billing` to `invoice_address` changes every constraint
name it emits *and* every message provider matching on them. The name is context — you got
the result by asking for `billing` — not part of the identifier.

Where a constraint has a property, it takes that property's word: `$allowedCountries` gives
`allowedCountries`, not `countryAllowed`. Where it has none, it names the check and carries
the part prefix only when the check alone would be ambiguous — `postalCodeFormat`, because
`format` could belong to several parts.

### Which part failed is data, not a substring

`ConstraintValidationResult` gains an optional part:

```php
ConstraintValidationResult::fail('postalCodeFormat', part: 'postalCode')
```

```php
$address = $result->get('billing');

$address->get('postalCodeFormat')->part;   // 'postalCode'
$address->transformed;                     // Address\Value, once transformed lands
```

What that does downstream — today `meraki/schema-html` splits the name:

```php
$separator = strrpos($constraint->name, '.');
if ($separator === false) { return null; }

return match (substr($constraint->name, $separator + 1)) {
    'format' => 'Enter a valid postal code for the country selected',
};
```

and afterwards:

```php
$message  = match ($constraint->name) {
    'postalCodeFormat' => 'Enter a valid postal code for the country selected',
};

$attachTo = $constraint->part;   // no parsing
```

A constraint about the whole address has `part === null`, which the dotted scheme could not
express at all. This also disposes of the money-message bug: `cost.amount.min` matches
neither the renderer's bare `min` nor its dot-splitting path, so a `$5` entry against a `$10`
minimum currently renders *"Value must be a number"*.

**One wrinkle to resolve during implementation.** `Money`'s bounds are per-currency —
`$min['AUD']` — so the property holds a map rather than a number, and a message cannot
interpolate `$field->{$constraint->name}` the way it can for a scalar. Either the constraint
result carries the bound that actually applied, or the provider indexes the map by the
submitted currency.

### Everything else stays singular

A field holds one value; several values is a `Collection`. `File` loses `$minCount` and
`$maxCount` — several files is a collection of file fields.

A configuration list is not multiple values: `Uri::$allowedSchemes`, `Enum::$cases` and
`PhoneNumber::$allowedCountries` describe one value's permitted range, and stay as they are.
## Constraints carry their bound

`ConstraintValidationResult` reports the bound that applied, alongside the part:

```php
ConstraintValidationResult::fail('minAmount', part: 'amount', bound: '10.00')
```

```php
$message = match ($constraint->name) {
    'minAmount' => "Must be at least {$constraint->bound}",
};
```

This replaces `$field->{$constraint->name}`, which was the original justification for making
constraint names equal property names.

**Why the lookup had to go.** It is a *dynamic* property read, so static analysis cannot
type it. A property holding a map rather than a scalar — `Money`'s per-currency bounds are
`$min['AUD']` — passes cleanly and then renders "at most Array". Verified: PHPStan at
level 9 reports nothing for

```php
/** @var array<string,int> */ public array $min = [];

return 'at most ' . $x->{$name};
```

so the type discipline that would normally catch this does not reach the one place it
matters.

What it buys beyond the map case:

- Thirty-one dynamic reads in `meraki/schema-html` become one static read, and the provider
  stops touching the field at all.
- **Computed bounds become reportable.** A per-country postal format or a per-currency scale
  has no scalar property to point at, so the lookup could never have worked for them.
- Constraint names are freed from exact equality with properties. They should still read as
  what they constrain, because scopes address properties by name, but that is a much weaker
  requirement.

### Typing the bound

A **closed union**, not `mixed` and not a generic:

```php
/** @param string|int|float|bool|list<string>|null $bound */
public readonly string|int|float|bool|array|null $bound;
```

A constraint with no bound reports `null`, as `line1Visitable` does.

Generics were considered and do not help. The `null`-widening used on `accepts()` works
because *parameter* types are contravariant; `bound` is a property on one class, so there is
no subclass to widen in. A `@template TBound` would type it where the result is created —
where the type is already known — and erase where results are collected into a
heterogeneous list on `ResolvedField`, which is exactly where a consumer reads it.

The closed union does what generics cannot. PHPStan at level 7 rejects

```php
return 'at least ' . $constraint->bound;
```

with *Binary operation "." between 'at least ' and bool|float|int|list<string>|string|null
results in an error*, forcing the consumer to narrow. The dynamic property read it replaces
passed clean at level 9, so this is the same hazard with the opposite outcome, purely
because the type can be written down.

**It only bites at level 7 and above**, and the core is at level 2 today. That makes raising
the PHPStan level load-bearing rather than tidy — `meraki/schema-html` is the consumer that
most needs it.

A result subclass per constraint kind would let `instanceof` narrow further, but it is a
class per constraint and does not extend to constraints a user defines.
## Value objects are called `Value`

A field taking a value object takes one named `Value` in its own namespace:
`Field\Address\Value`, `Field\Money\Value`, `Field\CreditCard\Value`.

`File\Metadata` is renamed to `File\Value` for the same reason — it was the only one of its
kind and the inconsistency was accidental.

**Order this after removing `Property\Value`**, which [FIELD-API.md](FIELD-API.md) already
decided to drop as unneeded complexity and which `Field.php` still references eight times.
Introducing `Field\Address\Value` while the old wrapper is still around means two `Value`
classes in scope at once.

## What `transformed` returns

| Field | Type | Why |
| --- | --- | --- |
| `Number` | `BigDecimal` | |
| `Date`, `Time`, `DateTime` | `LocalDate`, `LocalTime`, `LocalDateTime` | |
| `Duration` | `Duration` | |
| `Money` | `Money\Value` | |
| `Address` | `Address\Value` | |
| `CreditCard` | `CreditCard\Value` | |
| `File` | `File\Value` | |
| `PhoneNumber` | `PhoneNumber\Value` | stringifies to E.164, and carries the resolved country |
| everything else | its scalar | identity |

The rule: **use the library's value object when it stringifies to the value it represents.**
`BigDecimal` and `LocalDate` do. `libphonenumber\PhoneNumber` does not —

```
(string) $number                      → "Country Code: 61 National Number: 411222333"
$util->format($number, E164)          → "+61411222333"
```

Its `__toString()` is a debug representation, so `"{$resolved->transformed}"` in a template
would render that, and reaching E.164 requires the `PhoneNumberUtil` singleton, which is a
dependency the field should absorb rather than export. So `PhoneNumber` transforms to the
E.164 string.
## Time-relative constraints take a clock

A field that asks "is this in the future" needs *now*, so it holds a **`Clock`** — a source
of the current instant, never an instant itself. `brick/date-time` already ships `Clock`,
`SystemClock` and `FixedClock`, so this costs no new dependency and makes the behaviour
testable with a fixed instant.

Holding a source rather than a value is what keeps a shared definition safe: a `SystemClock`
is stateless, whereas reading `now` at definition time and storing it would be the same
mistake as B7.

Placement follows `Facade::for()` — declared on the schema, inherited by fields added
afterwards, overridable per field, defaulting to the system clock.

`ResolvedField` carries **`evaluatedAt`**, the instant the verdict was reached. That makes a
result reproducible and explainable, and it is the natural `bound` for every time-relative
constraint, so a message can say "expired as of 9 September 2026".

**The exception this forces.** Defaults are checked when they are declared, but that cannot
hold for a time-relative constraint: `defaultsTo('2027-01-01')` with `mustExpireInFuture()`
passes today and fails in 2027. Time-relative constraints are exempt from the definition-time
default check. In practice nothing sensible defaults a card expiry or a date of birth, but
the rule needs the carve-out stated rather than discovered.

## `CreditCard` expiry

| Surface | Name |
| --- | --- |
| Method | `mustExpireInFuture()` |
| Property | `$mustExpireInFuture` |
| Constraint | `expiryInFuture`, bound = the instant checked against |

No minimum: "not expired" is the constraint itself. A **maximum earns its place as a typo
guard** — cards are issued three to five years out, so `2099` should be caught — and it is a
baseline ceiling rather than configuration. An enum of permitted dates does not fit; an
expiry is whatever the card says.

## `PhoneNumber` carries its resolved country

This revises the earlier decision that `transformed` returns an E.164 string. A string
cannot carry the country the number resolved to, which a consumer needs.

```php
final readonly class Value implements Stringable
{
    public function __construct(
        public string $e164,
        public string $country,
    ) {}

    public function __toString(): string
    {
        return $this->e164;
    }
}
```

This still satisfies the rule that decided against `libphonenumber\PhoneNumber` — it
stringifies to the value it represents — while answering the country question. Input accepts
a plain string, `['number' => …, 'country' => …]`, or a `Value`.

**Parsing region.** `allow()` currently does double duty: it constrains which countries are
acceptable *and* supplies the region a national-format number is parsed against. Those are
separated. `allowCountries()` constrains; the parse region follows from it, the same way
`Address::determined()` treats a single allowed value as settled:

- one country allowed → national format parses against it
- international format → the region follows from the number
- several countries and national format → **ambiguous**

Ambiguity is a **constraint**, not a shape failure. `0411 222 333` is well-formed input that
simply cannot be resolved, so the message should ask which country rather than say the number
is invalid. Constraint `unambiguous`, with the allowed countries as its bound so the message
can list them.
## Still to decide

**Nothing.** Every row is settled. The matcher vocabulary is deferred to a stage of its
own, after the field API is implemented, and is not part of this review.

## The checklist

Constraint names are those emitted today, with `type` removed. Status is `open` until all
three surfaces are confirmed.

Constraint names below are read off the fields themselves, not from the source, so they are
what actually reaches a message provider. `type` is excluded — it is [being
removed](#removing-the-type-constraint). Names marked *(conditional)* appear only when the
matching declaration is used.

| Field | Definition surface | Constraint names emitted | Status |
| --- | --- | --- | --- |
| `Text` | `minLengthOf`, `maxLengthOf`, `matches` | `min`, `max`, `pattern` | open |
| `Name` | `minLengthOf`, `maxLengthOf` | `min`, `max` | open |
| `Number` | `scaleTo`, `minOf`, `maxOf`, `inIncrementsOf` | `min`, `max`, `step` | open |
| `Boolean` | `mustBeAccepted` | `accepted` *(conditional)* | open |
| `Enum` | `allow` | — | open |
| `Date` | `from`, `until`, `to`, `atIntervalsOf` | `from`, `until`, `interval` | open |
| `Time` | `from`, `until`, `inIncrementsOf`, `precisionMode` | `from`, `until`, `step` | open |
| `DateTime` | `from`, `until`, `inIncrementsOf`, `precisionMode` | `from`, `until`, `interval` | open |
| `Duration` | `minOf`, `maxOf`, `inIncrementsOf` | `min`, `max`, `step` | open |
| `EmailAddress` | `minLengthOf`, `maxLengthOf`, `allowDomain`, `disallowDomain` | `min`, `max`, `allowedDomains`, `disallowedDomains` | open |
| `PhoneNumber` | `allow`, `ofType` | `allowedCountries`, `numberType` | open |
| `Uri` | `minLengthOf`, `maxLengthOf`, `allowSchemes` | `min`, `max`, `scheme` | open |
| `Uuid` | `restrictToVersion` | `version` | open |
| `Password` | `minLengthOf`, `maxLengthOf`, `minNumberOf*`/`maxNumberOf*` ×4, `satisfyAnyOf` | `length`, `lowercase`, `uppercase`, `digits`, `symbols`, `anyOf` | open |
| `Passphrase` | presets only | `entropy`, `dictionary` | open |
| `File` | `atLeast`, `atMost`, `minFileSizeOf`, `maxFileSizeOf`, `allowTypes`, `disallowTypes`, `allowImages`, `allowVideos`, `allowDocuments`, `disallowScripts` | `minCount`, `maxCount`, `allowedTypes`, `disallowedTypes`, `minSize`, `maxSize` | open |
| `Money` | `allow`, `minOf`, `maxOf`, `inIncrementsOf` | `<name>.amount.{scale,min,max,step}` | open |
| `Address` | `allow`, `ofType`, `determined` | `<name>.<part>.required` ×7, `.line1.visitable`, `.postal_code.format`, `.administrative_area.allowed`, `.country_code.allowed` | open |
| `CreditCard` | — | `<name>.number.checksum` | open |
| `Collection` | `minItems`, `maxItems` | `minItems`, `maxItems` *(conditional)* | open |
| `Variant` | — | none of its own; the matching alternative's result is returned | open |

Three rows differ from what this table said before, which is worth noting because they were
wrong rather than stale: `Uri` gained `scheme` with the B2 fix, `Money` emits only dotted
names (there is no bare `min`), and `CreditCard` emits a `checksum` rather than nothing.

### Cross-cutting rows

| Feature | Open question |
| --- | --- |
| Optionality | `makeOptional()`/`require()` on the definition. Provenance **is** exposed: `$resolved->appliedOutcomes` says which rule made a field optional, so a renderer can tell an authored optional from a rule-driven one. Confirm the two spellings. |
| Defaults | **Settled** — `defaultsTo()` on the definition, `resolve($submitted, prefilledWith: $known)` per request, `$resolved->source` recording which won. See [FIELD-API.md](FIELD-API.md#defaults). |
| Ignored input | **Settled** — `ignoreInput()`/`acceptInput()` are removed. The `ignore` outcome is read from `appliedOutcomes` when resolving, so it never touches the definition. |
| Presets | `Password::strong()`, `Passphrase::moderate()`, `DateTime::withSecondPrecision()`. Confirm these survive and whether other fields gain them. |
| `transformed` type | Confirm the target type per field — `BigDecimal`, `LocalDate`, parsed phone number, an address value object. Nothing populates it yet, so this is a `2.1` decision that the naming here must not foreclose. |

### Known API leaks to close

- `Passphrase::getConstraints()` and `Variant::getConstraints()` are **public**; on every
  other field the method is protected. Still open.
- `Date` exposes both `until()` and `to()`, and they are **not** the same thing: `until()`
  is exclusive, `to()` is inclusive (it stores `date + 1 day`). Both report under the name
  `until`, so a result cannot say which was declared. Two behaviours sharing one constraint
  name is worse than two names for one behaviour, which is how this was previously recorded.
- `Field\Set::getByName()` is typed `?Field` but throws instead of returning `null`.
- `Rule\Outcome\_Require` carries a leading underscore.
- Structured types report against sub-field names (`cost.amount.min`), and a message
  provider has to split the string to get anywhere. Whatever replaces dotted names has to
  answer *which part failed* without string surgery.
