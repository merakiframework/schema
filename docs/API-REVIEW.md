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
2. **The constraint name is the property name.** Not merely consistent with it — the same
   string. `meraki/schema-html` already reads `$field->{$constraint->name}` in 31 places, so
   this equality is load-bearing today; making it a rule turns an accident into a contract,
   and gives `#/fields/username/minLength` as a scope for free.
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
| `Collection` | `minItems()` / `maxItems()` → `$minItems` / `$maxItems` → same |
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
## Still to decide

Left out of the table above because the rule does not settle them on its own.

| Field | The question |
| --- | --- |
| `Password` | Six `Range` properties (`$length`, `$lowercase`, …) behind eleven `minNumberOfX`/`maxNumberOfX` methods, plus `satisfyAnyOf()`. The constraint names are the property names already, but the *shape* is the question: is a `Range` the right abstraction, and does `minLengthOf()` belong on a field whose length constraint is called `length`? |
| `Passphrase` | `$entropy`, `$method`, `$dictionary` — `$method` has no constraint. Configured only through presets. `getConstraints()` is public here and protected everywhere else. |
| `Enum` | Property is `$oneOf`, method is `allow()`, and no constraint is emitted at all. `allow()` is one of the four unrelated things called `allow()`. |
| `Money`, `Address`, `CreditCard` | Dotted constraint names (`cost.amount.min`). **Blocked** on the structured-type design — these cannot be settled before it is. |
| `Variant` | Emits nothing of its own; the matching alternative's result is returned. Confirm that is the contract. |
| `EmailAddress` | `$format` selects between `Basic`, `Html`, `Rfc`, `Smtp` validation modes. `Html` is a browser pattern used as a domain rule — recorded as leftover L2. |
| `Number` | `$scale` / `scaleTo()` is a property with no constraint. Confirm it is configuration rather than a rule. |
| `Boolean` | Property `$mustBeAccepted` reads as a predicate; the constraint is `accepted`. Rule 2 wants them equal, but `mustBeAccepted` is an odd name for a failure. |

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
