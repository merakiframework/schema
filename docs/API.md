# The 2.0 API

What the API *is*, and why each part of it is shaped that way.

This was "API review for 2.0" — a working checklist with a `Status` column reading `open` on
every row, written while the design was still being argued. It is not a review any more: the
decisions are made and implemented, and the rows that were open are settled or recorded as
dropped. The argument is kept rather than deleted, because the reasoning is the part that is
expensive to reconstruct and the part a future change has to answer.

Every feature is confirmed across **three surfaces**, which is the discipline that kept the
three from drifting apart:

| Surface | The question it answers | What it means now |
| --- | --- | --- |
| **Definition** | How does an author declare it? | `->minLengthOf(3)` on a sealed field, via withers |
| **Rule** | How does a matcher reference it? | A scope. `#/fields/age/minValue` is a definition property, `#/fields/billing/value` is what was submitted, `#/fields/billing/value/country` is one part of it. Every one is checked by `addRule()`, so a typo fails where it is written |
| **Resolved** | How does it appear after validation? | The constraint name on a `ConstraintValidationResult`, and the value type on the `ResolvedField` |

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

And it reached users. A money field below its minimum, *as `1.x` spelled it*:

```php
// 1.x — kept here because it is the argument for the change
$schema->addMoneyField('cost', ['AUD' => 2])->minOf('AUD', '10.00');
$schema->validate(['cost' => ['currency' => 'AUD', 'amount' => '5.00']]);
```

fails `cost.amount.min` and renders **"Value must be a number"**. The dotted name does not
match the renderer's bare `min`, and the dot-splitting path only recognises `visitable`,
`format`, `allowed` and `required` — so it falls through to a default written for a
different problem. The user is told their input is not a number when it is.

## Removing the `type` constraint — done

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
| Express it at that scale (`123` → `123.00`) | the application | `validateValue()` ✗ |

The middle one is why a scale-2 field rejects `123.456` with "must be a number". The third is no
longer the library's at all — padding a number for display is a conversion only the caller can
want, so `Number` validates the scale and hands back the value unpadded.
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
*and* keeps it state, which is already the idiom in `ResolvedField::$value` and
`AggregatedValidationResult::$status`:

```php
public bool $requiresAcceptance { get => /* … */; }
```

### Normalisation follows the standard, and is never configurable

The invariant: **normalisation may only remove a distinction the standard says is not a
distinction.** Lowercasing a domain is lossless because DNS is case-insensitive; lowercasing an
email's local part is not, because RFC 5321 permits it to be case-sensitive — which is why
`EmailAddress` normalises one half and not the other.

That rule survives. Its **scope narrowed sharply**: the core does not *repair* input at all.

- **Repair is the port's job.** Trimming whitespace, fixing case the standard does not mandate,
  dropping blank rows — all of it belongs where the medium is known. `" a@b.com "` is not an
  address under RFC 5321, so the core rejects it rather than quietly trimming. An HTML form
  submits stray whitespace; a JSON client does not, and its `""` means an intentional empty
  string. Only the port can tell those apart. See docs/CODING-STYLE.md.
- **Canonicalisation lives in the field's `Value` object**, not in the field — one place per
  type, so the rule above is applied once and can be read in one place.
- **Nothing is converted for the application.** `123.5` is not padded to `123.50`; a phone
  number is not formatted to E.164. The library cannot know whether you want cents, a
  `Brick\Money`, or a decimal string, so it hands back what it validated and the application
  converts. See *One hook: `parse()`* below.

### One hook: `parse()`

`process()`, `validateValue()` and `transform()` are one method. They between them parsed most
values twice — `validateValue()` threw the result of its parse away and `transform()` parsed
again — and had to agree with each other to be correct.

```php
abstract protected function parse(mixed $value): mixed;
```

Three rules, and everything downstream rests on them:

| Rule | Why |
| --- | --- |
| Never receives `null` | Absence is settled first, so `null` in the *return* means one thing: unreadable |
| Never raises | Unreadable input is ordinary, not exceptional; throwing belongs to definition time |
| Its result is what the constraints see | So `meetsMinValue(BigDecimal $value)` is true by construction |

`type` failing now means exactly "parse returned null".

### Two values on the result, neither throwing

| Value | Is |
| --- | --- |
| `$given` | verbatim, for echoing a rejected submission back |
| `$value` | `parse($given) ?? $given` — the domain type, or what could not be turned into one |

`ResolvedField::$normalized` is gone. It threw on a failed or pending field, which forced
check-before-read ceremony on every consumer; both of these are always readable, and the verdict
says which of the two you are holding. The authored default is stored exactly as written and goes
through `parse()` when it stands in, so `defaultsTo('2026-01-01')` on a `Date` yields the same
`LocalDate` that submitting that string would.

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
/** Characters. The author may narrow this, never widen it. */
public private(set) ?int $maxLength = null;
```

The hook rather than a bare constant is what keeps the property-equals-constraint rule: a
message can interpolate the number and `#/fields/password/minLength` resolves. And it is
read-only *by construction* — the absence of a setter **is** the difference between a
baseline and something an author may narrow, so "changing this needs a core release" is
enforced rather than documented.

**Baselines do not serialise.** They follow from the field type, so writing them into a
document is redundant and lets stored data disagree with the code: raise the core's ceiling
and every old document still claims the old one. Expose them, do not persist them.
`meraki/schema-json` writes `min`/`max` today, so this changes how it treats field config.

#### Length is in characters, and there is no byte ceiling

Lengths are counted with `mb_strlen()`, consistently with every other field, so they are code
points rather than bytes or grapheme clusters. NIST SP 800-63B asks for exactly that, requiring
each Unicode code point to count as one character. A policy is written in characters because
that is what a person types and what the standard specifies — a byte-denominated limit would
admit 64 Latin characters but only 21 CJK ones, so the same written rule would mean different
things for different users.

**`$maxBytes` was removed rather than made configurable.** It existed because bcrypt silently
truncates at 72 bytes, which is a real hazard:

| Algorithm | Ceiling | Behaviour past it |
| --- | --- | --- |
| bcrypt | 72 bytes | **silently truncates** — two secrets sharing a 72-byte prefix become the same secret |
| Argon2id | 2^32-1 bytes | none in practice |

Verified rather than assumed: `password_verify()` accepts a 99-byte string against a bcrypt hash
of a *different* 87-byte string when they share a 72-byte prefix, and does not for Argon2id.
PHP's `PASSWORD_DEFAULT` is still bcrypt as of 8.5, so the obvious call has this property.

But it is not this field's hazard. A password field answers "is this an acceptable secret",
which is a question about the string and the policy; what a hashing algorithm can consume is a
different question, asked by a different layer. The field cannot know which algorithm will be
used, so expressing the ceiling here meant asking the author to declare something and then
re-checking it for them — and the usual fix is not rejection at all but **pre-hashing**
(`base64_encode(hash('sha256', $secret, true))` before bcrypt), which removes the ceiling
without refusing anything the user typed. That is an infrastructure decision, and it belongs
where the hashing happens.

The knowledge is kept in the `Password` class docblock so nobody has to rediscover it.

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
| `minNumberOfUppercaseChars()` | `$minUppercaseChars` |
| `minNumberOfLowercaseChars()` | `$minLowercaseChars` |
| `minNumberOfDigits()` | `$minDigits` |
| `minNumberOfSymbols()` | `$minSymbols` |

Flat `?int` properties in place of `Range` objects. Each carries its own constraint name, so a
failure says which rule was missed — which the `Range` shape could not, since one constraint
name covered both ends.

**There are no per-class maximums.** A minimum describes a policy that exists in the world, and
is offered off by default. A maximum is a different thing: it shrinks the search space an
attacker must cover and tells them something about its shape — "at most two digits" is a gift —
and current guidance is against composition rules generally. Dropping them also removes the only
source of combinatorial contradictions between the counts.

#### The one contradiction that remains

The four classes are **disjoint** — a character is uppercase, lowercase, a digit or a symbol,
never two at once — so requiring ten of one and ten of another really does need twenty
characters. Hence one definition-time check:

> Σ(composition minimums) ≤ `maxLength`

Guarded on every setter that can create the contradiction, since any of them may be written
last. `maxLengthOf(15)->minNumberOfUppercaseChars(10)->minNumberOfDigits(10)` throws, in any
order.

**The mirrored check would be a bug**, and is deliberately absent. It would have to say the
classes must add up to `minLength`, but the classes are disjoint and *not exhaustive*: `漢`, `א`,
`ก`, `ǅ` and `ᵃ` match none of the four patterns, because `\p{Lu}`/`\p{Ll}` miss the other
letter categories (`Lo`, `Lt`, `Lm`) and the symbol class excludes all of `\p{L}`. A
twelve-character CJK secret counts zero in every class while being twelve characters long.
### Confirmed this round

| Item | Decision |
| --- | --- |
| `Rule\Outcome\MakeRequired` | **`MakeRequired`**, pairing with `MakeOptional`. `class Require {}` is still a parse error on PHP 8.5 — namespaces accept reserved words, class names do not — so the keyword is avoided rather than worked around. `Field::require()` becomes `makeRequired()` to match. |
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

`Composite` is removed. `Address`, `Money` and `CreditCard` are each a **single field holding a
single value object**, the way `File` holds a `File\Value`. Input is a record — an object — or the
value object itself; `parse()` reads it into the object. There are no sub-fields.

```php
$schema->add($schema->createAddressField('billing', ['AU']));

$schema->validate((object) [
    'billing' => (object) [
        'line1'               => 'PO Box 42',
        'locality'            => 'Rockhampton',
        'administrative_area' => 'QLD',
        'postal_code'         => '470',      // AU postcodes are four digits
        'country'             => 'AU',       // or 'Australia'; either case
    ],
]);
```

### Constraint names lose the dots *and* the field name

A `part` is named as submitted data names it — snake_case — because that is the vocabulary the
author wrote and the one a message provider has to match.

| Today | Becomes | Part |
| --- | --- | --- |
| `billing.country_code.allowed` | `allowedCountries` | `country` |
| `billing.postal_code.format` | `postalCodeFormat` | `postal_code` |
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
$address->value;                           // Address\Value
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

## `transformed` — **dropped**, and why

There is no second value on a result. `$value` is the parsed value, and the parsed value is
already the typed one.

The table that used to be here mapped each field to the type `transformed` should return, under
one rule: *use the library's value object when it stringifies to the value it represents.* Three
fields got a library `Value`, the rest got a third party's class or a bare scalar, and
`PhoneNumber` got a hand-made exception because `libphonenumber\PhoneNumber` stringifies to
`"Country Code: 61 National Number: 411222333"`.

That rule generalised. Every field now returns a value object this library defines — see
[FIELD-API.md](FIELD-API.md#parse--the-one-hook) — so the exception is the rule, and the case
that forced it is one method on the value:

```php
$resolved->value->toE164();     // "+61411222333"
$resolved->value->number;       // libphonenumber's own object, unchanged
```

So the whole feature collapses into `parse()`'s return type, and the three properties the sketch
had become two: `given`, exactly what was submitted, and `value`, what the field made of it.

The one thing genuinely lost is a *canonical string per field*, and it was lost deliberately.
`Number` was to yield `123.00` for a scale-2 field; it does not, because padding is formatting and
formatting is a locale's business. The scale is on the field for a consumer that wants to format
against it.

**What this means for the ports:** there is no third value to read, and nothing to wait for. A
renderer formats from `$value` — which is a value object with a known type — plus the field's own
configuration.
## Time-relative constraints take a clock

A field that asks "is this in the future" needs *now*, so it holds a **`Clock`** — a source
of the current instant, never an instant itself. `brick/date-time` already ships `Clock`,
`SystemClock` and `FixedClock`, so this costs no new dependency and makes the behaviour
testable with a fixed instant.

Holding a source rather than a value is what keeps a shared definition safe: a `SystemClock`
is stateless, whereas reading `now` at definition time and storing it would be the same
mistake as B7.

Placement follows `Facade::for()` — declared on the schema, inherited by fields it *builds*,
overridable per field, defaulting to the system clock. Note "builds" rather than "added":
a field is immutable, so it takes the clock at construction and a schema cannot reach into one
it was handed.

`ResolvedField` carries **`evaluatedAt`**, the instant the verdict was reached — `null` on a
field with no clock, because nothing about it depends on the time. That makes a result
reproducible and explainable, and it is the natural `bound` for every time-relative constraint,
so a message can say "expired as of 9 September 2026".

`SchemaValidationResult` carries one too, read **once** per request rather than once per field.
Under a `SystemClock` two fields reading it separately would get instants microseconds apart —
harmless for a card expiry, not harmless for a rule comparing two time-relative fields to each
other. One request gets one answer to "what time is it".

**The exception this forces.** Defaults are checked when they are declared, but that cannot
hold for a time-relative constraint: `defaultsTo('2027-01-01')` with `mustExpireInFuture()`
passes today and fails in 2027. Time-relative constraints are exempt from the definition-time
default check. In practice nothing sensible defaults a card expiry or a date of birth, but
the rule needs the carve-out stated rather than discovered.

## `CreditCard` expiry

*Done.*

| Surface | Name |
| --- | --- |
| Method | `mustExpireInFuture()` |
| Property | `$mustExpireInFuture` |
| Constraint | `expiryInFuture`, bound = the instant checked against |
| Constraint | `expiryWithinReach`, bound = the ceiling in years |

No minimum: "not expired" is the constraint itself. A **maximum earns its place as a typo
guard** — cards are issued three to five years out, so `2099` should be caught — and it is a
baseline ceiling rather than configuration, so it is always asked even when expiry is not
being enforced. Twenty years, which is far outside anything real on purpose: it is there to
catch a slipped keystroke, not to have an opinion about unusual cards. An enum of permitted
dates does not fit; an expiry is whatever the card says.

**The clock moved to the constructor.** It used to arrive with `mustExpireInFuture()`, on the
argument that a card captured for later reference has no business knowing the time. Adding
`expiryWithinReach` ended that: it is not optional, and it asks the calendar too. A schema now
declares the clock once and every card field it builds inherits it, which also needs somewhere
on the field to keep it that does not depend on which rules were switched on.

## `PhoneNumber`

The value is a **`PhoneNumber\Value`**, which compares in E.164 and hands it over with
`toE164()`. A bare E.164 string was the earlier answer, so the resolved country
could travel with it, and rejected because nothing needs the country.

One thing to record, because it is easy to assume otherwise: **the country is not recoverable
from the E.164 prefix.**

```
+61411222333   cc=+61  region=AU
+14165550123   cc=+1   region=CA     Toronto
+12125550123   cc=+1   region=US     New York
```

`+1` covers the US, Canada and around twenty Caribbean nations; `+7` covers Russia and
Kazakhstan. The region comes from the area code and libphonenumber's metadata. A consumer
that needs it must re-parse — `substr($e164, 0, 3)` gets Canada wrong.

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
## `Address` stays one field

A `Location` / `PostalAddress` split was worked through and rejected. With `Address\Type`
retained, the only difference between the two would be *whether a street is required* — and
a boolean does not justify a type.

So one class, with two independent dials. Both start unrestricted and are *narrowed*, which is
why neither is a setter taking the enum:

| Dial | Expresses | Surface |
| --- | --- | --- |
| `Type` | purpose — can you post to it, can you visit it | `allowOnlyMailable()`, `allowOnlyPhysical()` → `$type` |
| granularity | is a street required | `allowWithoutStreet()` → `$mustBeSpecific` → constraint `specific` |

`allowOnlyMailable()` and `allowOnlyPhysical()` each narrow `$type` rather than assign it, so
asking for both in either order lands on `Type::Both` instead of the second call undoing the
first. `Type` itself keeps its four FHIR-aligned states; it is no longer part of the surface.

**An address names a street by default**, and `allowWithoutStreet()` is the opt-out. An address
is a place, and a suburb with a postcode is a region that *contains* places — so the vague form
is the exception, and the author says so. Without the opt-out the per-country requiredness from
libaddressinput still governs everything else.

**A mailable address that needs no street throws where it is declared.** You cannot post to a
suburb, and the pattern for a combination with no meaning is to reject it at definition time, as
the baseline floors do. Guarded on both withers rather than one, because either call can be the
second to arrive.

### The country is always submitted

The third field to make the same pairing — `Money` with a currency, `PhoneNumber` with a country,
and now `Address`. A postcode means nothing on its own: `4700` is Rockhampton in Australia and
something else elsewhere. An address without a country is a shape failure.

This removed `settleCountry()`, which filled the country in when the allow-list happened to hold
exactly one. That was defensible — with only Australia allowed, an omitted country really was
*determined* rather than guessed — but it made the rule change shape depending on how many
countries were listed, and one constant supplied by the port is cheaper than that.

**It also gained a capability.** An unrestricted address used to get no postcode validation at
all, because there was no country to derive a rule from:

```php
// Free-form means free-form: a country typed into an unrestricted address is data, not a
// rule to start enforcing a postcode format with.
if ($this->allowedCountries === []) {
    return null;
}
```

That is no longer true — the submitter names the country, so checking their postcode against it
reads what they wrote rather than inferring it. `resolvedCountry()` collapsed from four branches
to two.

Presence is what is required, not correctness: a country that is present but unrecognised passes
the shape and is reported by `allowedCountries`, which can name the list it should have come from.

### A country may be named or coded

`allowCountries()` and the submitted `country` part both take `'AU'`, `'au'` or `'Australia'`,
and `process()` canonicalises to the code. Unambiguous to accept both: libaddressinput lists 256
countries, no two share a name, and no name collides with a code. Accepting only the code would
mean a form offering a country dropdown had to map the label back before submitting.

A country that is neither is left exactly as it arrived, for `allowedCountries` to report —
rewriting it would lose what was typed, and guessing at a near-miss is not this field's business.
The part is `country` rather than `country_code`, since either form may be sent; the property
stays `countryCode`, since it holds a code.

**What would justify the split later** is coordinates, and it is a different reason from the
one rejected here: a coordinate is not an address at all, whereas "Rockhampton QLD 4700" is
one — just a vague one. Adding a `Location` type later does not disturb `Address`, so the
split is deferred rather than ruled out.
## `Money` scale defaults to the standard

`allowCurrencies()` takes two shapes, which mix in one call:

```php
$field->allowCurrencies(['AUD', 'JPY']);      // each currency's own ISO 4217 exponent
$field->allowCurrencies(['AUD' => 3]);        // an override
$field->allowCurrencies(['JPY', 'AUD' => 3]); // both
```

A bare entry is a code; a keyed entry names the code and gives it a scale. The last mention of
a currency wins, so an override may follow a plain mention.

**Why a default at all.** ISO 4217 already assigns every currency an exponent — JPY 0, AUD and
USD 2, BHD 3, CLF 4 — so making the author restate it was asking them to maintain a table the
standard already publishes, and to get it wrong quietly. `brick/money` ships that table, which
is why it is a dependency.

**Why an override at all.** "Money" covers two different quantities. A *settleable amount* is
what moves between accounts, always a whole number of minor units — you cannot pay half a cent,
so the currency's exponent is right. A *rate or unit price* — fuel at `$1.859`/L, electricity at
`$0.2345`/kWh, an ad CPM at `$0.001234` — is denominated in a currency, finer than its minor
unit, and multiplied by a quantity before anything is settled. Only the second needs the
override, and writing the number out is the point: an amount finer than the currency allows is a
typo far more often than it is intent.

**Amounts stay string decimals, not integer minor units.** Integer minor units are what most
payment APIs take, and they are right for a wire format — but `1250` only means something once
you know the currency's exponent, so the conversion would have to happen *before* validation.
This field would then be judging a number whose meaning depends on a fact it has not checked
yet: whether that currency is even allowed. A string also preserves the scale as written, which
is exactly what the `scale` constraint judges on — `"1.50"` and `"1.5"` are distinguishable and
`1250` has already thrown that away. The integer form is what the application wants on the way
*out*, which makes it something the value object exposes rather than an input shape.

**An unknown currency is refused where it is written, and reported where it is submitted.** The
same split as `Address` and its countries: an allow-list entry that is not a real currency is a
typo no input could satisfy, so it throws; a submitted currency that is not real is a value that
happens to be wrong, so `allowedCurrencies` reports it and a form can mark the right input. As
ever, an unrestricted field enforces nothing — free-form means free-form.

## Scopes, and reading into a value

Four kinds, and the segment count says which:

| Scope | Names |
| --- | --- |
| `#/fields/nickname` | the field — what an outcome acts on |
| `#/fields/nickname/value` | what it was given, parsed |
| `#/fields/age/minValue` | a public property of the definition |
| `#/fields/billing/value/country` | one part of the value |

A **part** belongs to a value, so it goes under `value` rather than beside it. The short form
`#/fields/billing/country` reads better and is ambiguous: the third segment already means a
definition property, and two fields have one that collides with a part of their value —
`#/fields/card/name` could be the field's name or the cardholder's, and `#/fields/resume/name` the
field's or the uploaded file's. `$name` is on every field, so the collision is not exotic, and a
precedence rule would make a *stored* scope change meaning if a field later gained a property.

Only a value that says it has parts can be read into: [`Field\HasParts`](../src/Field/HasParts.php),
implemented by `Address`, `Money`, `CreditCard`, `File`, `EmailAddress` and `PhoneNumber` values.
Part names are the keys submitted input uses and the ones a constraint reports as `$constraint->part`
— `postal_code`, not `postalCode` — so there is one vocabulary rather than three. `partNames()` is
static so `addRule()` can check a part before any request exists.

### An expectation can be another scope

Which is what makes a rule compare two *fields* rather than a field and a constant:

```php
// is the whole shipping address the billing address?
$schema->when(ValueScope::of('shipping'))->equals(ValueScope::of('billing'));

// are they at least in the same country?
$schema->when(PartScope::of('shipping', 'country'))
    ->equals(PartScope::of('billing', 'country'));
```

Both sides go through the same resolver, so a parsed value is compared against a parsed value.
The two sides need not be the same part or even the same kind of field — comparing a `postal_code`
to a `line1` is allowed and answers false.

**Collection items are still not addressable.** Which row `0` is depends on what was submitted, so
a stored rule naming one would mean a different row on a different request.

## Still to decide

**Nothing.** Every row is settled. The matcher vocabulary is deferred to a stage of its
own, after the field API is implemented, and is not part of this review.

## The field surface

Read off the classes, and **kept true by a test rather than by this page**:
[`tests/Api/ConstraintNameTest.php`](../tests/Api/ConstraintNameTest.php) asserts the constraint
names for every field, and [`tests/Api/NamingTest.php`](../tests/Api/NamingTest.php) asserts the
configuration methods and properties. Both run in the default suite. If this table and those tests
ever disagree, the tests are right.

That is the whole reason this section is short now. It used to be a checklist with a `Status`
column reading `open` on every row, tracking a design that had not been built — and it went stale
the moment it was, because nothing executed it.

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

A few things the table says that are worth saying in words:

- **`Name` has no configuration at all.** Its length bounds are a baseline, not a dial — a
  person's name is between 1 and 255 characters and an author has nothing useful to add.
- **`Enum` reports no constraints.** The list of cases *is* the type, so a value outside it is a
  shape failure, the same way an unparseable string is for `Date`. A renderer reads `$cases` to
  draw its options anyway, so a constraint carrying them would answer one question twice.
- **No name carries the field it came from.** `postalCodeFormat`, not
  `billing_address.postal_code.format`. Which *part* a constraint concerns is on the result as
  `$constraint->part`, so a message provider never splits a string to find out.
- **Every field parses to its own value class**, and that class answers for its own equality. See
  [FIELD-API.md](FIELD-API.md#why-a-value-object-always).

### Cross-cutting rows

| Feature | Open question |
| --- | --- |
| Optionality | **Settled** — `makeOptional()` and `makeRequired()` on the definition. Paired spellings, because `require()` reads as an imperative next to a wither that hands back a copy. Provenance is on the result: `$resolved->appliedOutcomes` says which rule made a field optional, so a renderer can tell an authored optional from a rule-driven one. |
| Defaults | **Settled** — `defaultsTo()` on the definition, `resolve($submitted, prefilledWith: $known)` per request, `$resolved->source` recording which won. See [FIELD-API.md](FIELD-API.md#defaults). |
| Ignored input | **Settled** — `ignoreInput()`/`acceptInput()` are removed. The `ignore` outcome is read from `appliedOutcomes` when resolving, so it never touches the definition. |
| ~~Presets~~ | **Dropped for `2.0`.** `Password::strong()`, `DateTime::withSecondPrecision()` and the rest were never built, and a preset is a named bundle of calls an author can write themselves. Revisit after the release, when there is usage to name them from — inventing the bundles first is how you end up with `moderate()` and nobody able to say what it means. |
| ~~`transformed` type~~ | *Settled: there is no `transformed`.* Every field parses to a value object this library defines, so the parsed value is the typed value. See [above](#transformed--dropped-and-why). |

### Known API leaks

All closed. Kept as the record of what they were, because each one is a shape worth
recognising again:

- ~~`Passphrase::getConstraints()` and `Variant::getConstraints()` are public~~ — both classes
  are gone, and `getConstraints()` became the `$constraints` property on every field.
- ~~`Date` exposes both `until()` and `to()`~~ — `to()` is gone. It was *inclusive* where
  `until()` is exclusive, and both reported under the name `until`, so a result could not say
  which had been declared. Two behaviours sharing one constraint name is worse than two names
  for one behaviour.
- ~~`Rule\Outcome\MakeRequired` carries a leading underscore~~ — renamed.
- ~~Structured types report against sub-field names (`cost.amount.min`)~~ — gone. A constraint
  carries the part it concerns as `$constraint->part`, so nothing splits a string.
- `Field\Set::getByName()` is typed `?Field` and throws instead of returning `null` — **fixed**;
  it is typed `Field` and `findByName()` is the nullable one.
