# API review for 1.14

Every feature and every constraint has to be confirmed across **three surfaces** before
the `1.14.0-rc.1` API freeze. This page is the working checklist; nothing ships
unconfirmed.

| Surface | The question it answers |
| --- | --- |
| **Definition** | How does an author declare it? (`->minLengthOf(3)`) |
| **Rule** | How does a matcher reference it? (`->whenItIsAtLeast(3)`, `#/fields/x/min`) |
| **Resolved** | How does it appear after validation? (constraint name, `transformed` type) |

A row is **confirmed** only when all three are settled and consistent with the rest of
the table. Until then it is **open**.

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

## The checklist

Constraint names are those emitted today, with `type` removed. Status is `open` until all
three surfaces are confirmed.

| Field | Definition surface | Constraint names | Status |
| --- | --- | --- | --- |
| `Text` | `minLengthOf`, `maxLengthOf`, `matches` | `min`, `max`, `pattern` | open |
| `Name` | `minLengthOf`, `maxLengthOf` | `min`, `max` | open |
| `Number` | `scaleTo`, `minOf`, `maxOf`, `inIncrementsOf` | `min`, `max`, `step` | open |
| `Boolean` | `mustBeAccepted` | — | open |
| `Enum` | `allow` | — | open |
| `Date` | `from`, `until`, `to`, `atIntervalsOf` | `from`, `until`, `interval` | open |
| `Time` | `from`, `until`, `inIncrementsOf`, `precisionMode` | `from`, `until`, `step` | open |
| `DateTime` | `from`, `until`, `inIncrementsOf`, `precisionMode` | `from`, `until`, `interval` | open |
| `Duration` | `minOf`, `maxOf`, `inIncrementsOf` | `min`, `max`, `step` | open |
| `EmailAddress` | `minLengthOf`, `maxLengthOf`, `allowDomain`, `disallowDomain` | `min`, `max`, `allowedDomains`, `disallowedDomains` | open |
| `PhoneNumber` | `allow`, `ofType` | `allowedCountries`, `numberType` | open |
| `Uri` | `minLengthOf`, `maxLengthOf` | `min`, `max` | open |
| `Uuid` | `restrictToVersion` | `version` | open |
| `Password` | `minLengthOf`, `maxLengthOf`, `minNumberOf*`/`maxNumberOf*` ×4, `satisfyAnyOf` | `length`, `lowercase`, `uppercase`, `digits`, `symbols`, `anyOf` | open |
| `Passphrase` | presets only | `entropy`, `dictionary` | open |
| `File` | `atLeast`, `atMost`, `minFileSizeOf`, `maxFileSizeOf`, `allowTypes`, `disallowTypes`, `allowImages`, `allowVideos`, `allowDocuments`, `disallowScripts` | `minCount`, `maxCount`, `allowedTypes`, `disallowedTypes`, `minSize`, `maxSize` | open |
| `Money` | `allow`, `minOf`, `maxOf`, `inIncrementsOf` | `<name>.amount.{scale,min,max,step}` + `min`, `max`, `step` | open |
| `Address` | `allow`, `ofType`, `determined` | `<name>.<part>.required`, `.postal_code.format`, `.line1.visitable`, `.administrative_area.allowed`, `.country_code.allowed` | open |
| `CreditCard` | — | sub-fields only | open |
| `Collection` | `minItems`, `maxItems` | `minItems`, `maxItems` | open |
| `Variant` | — | delegates to the matching variant | open |

### Cross-cutting rows

| Feature | Open question |
| --- | --- |
| Optionality | `makeOptional()`/`require()` on the definition; effective value on the resolved field. Confirm both spellings and whether provenance is exposed. |
| Defaults | `prefill()` is definition. Confirm `given` / `resolved` / `transformed` naming on the resolved field. |
| Ignored input | `ignoreInput()`/`acceptInput()` exist as definition methods but express a rule outcome. Confirm where they belong. |
| Presets | `Password::strong()`, `Passphrase::moderate()`, `DateTime::withSecondPrecision()`. Confirm these survive and whether other fields gain them. |
| `transformed` type | Confirm the target type per field — `BigDecimal`, `LocalDate`, parsed phone number, an address value object. |

### Known API leaks to close

- `Passphrase::getConstraints()` and `Variant::getConstraints()` are **public**; on every
  other field the method is protected.
- `Date` exposes both `until()` and `to()` for one constraint.
- `Field\Set::getByName()` is typed `?Field` but throws instead of returning `null`.
- `Rule\Outcome\_Require` carries a leading underscore.
