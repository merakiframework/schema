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
