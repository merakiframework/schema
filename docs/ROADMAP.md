# Roadmap

No dates. The ordering is real; the timing is not promised.

- [Release verdict](#release-verdict) — why this is still pre-release
- [The release ladder](#the-release-ladder)
- [Architecture: immutable definition + `ResolvedField`](#architecture-immutable-definition--resolvedfield)
- [Planned features](#planned-features)

---

<a id="release-verdict"></a>

## Release verdict

**Not ready for a stable release. Still pre-release — but no more alphas.**

The architecture is sound: the domain model is good, the package separation holds, and
the field types backed by curated standards data (`Money`, `Date`, `PhoneNumber`,
`Address`, `Uuid`) are correct and well covered by 957 tests.

What blocks a `1.0`-style commitment is a short list of specific defects, not anything
structural — see [LIMITATIONS.md](LIMITATIONS.md). Two of them would be enough on their
own: composite fields throw an uncaught exception on hostile input (B1), and a schema
shared across concurrent requests leaks data between them (B7) despite long-lived process
support being a stated goal. Committing to semantic versioning with those in place would
lock in an API that has to break to fix them.

None of it is a rewrite. It is a cleanup release.

<a id="the-release-ladder"></a>

## The release ladder

| Stage | Gate |
| --- | --- |
| `1.14.0-beta.1` | B1–B6 fixed. Dead code deleted. CI running the suite on PHP 8.4 and 8.5. Static analysis green. `composer audit` clean. |
| `1.14.0-beta.2` | The `ResolvedField` seam (below): per-request state moves off the fields, fixing B7 together with the purity and mutation defects. `meraki/schema-html` updated in step. |
| `1.14.0-beta.3` | Definition sealed. Indexed paths for collection results. `transformed` populated per field type. Docs accurate. |
| `1.14.0-rc.1` | Public API frozen, **including constraint names**. Changelog complete. Both sibling packages green against it. |
| `1.14.0` | First stable release. The semantic-versioning commitment starts here. |

`beta.3` is separable — once the seam exists, sealing and indexed results are additive and
could slip to `1.15.0` without blocking stability. The seam itself cannot slip; it is the
fix for B7.

### Why `1.14` and not `1.0.0` or `0.14.0`

`1.13.0-alpha` is already published, and both `meraki/schema-json` and
`meraki/schema-html` pin `^1.13.0-alpha`. Composer orders
`1.13.0-alpha < 1.14.0-beta.1 < 1.14.0`, so the ladder keeps every existing constraint
resolvable. Dropping back to `0.14.0` would sort *below* what is already published and
break those pins for no real gain.

---

<a id="architecture-immutable-definition--resolvedfield"></a>

## Architecture: immutable definition + `ResolvedField`

This is the spine of the `1.14` line and the change most worth knowing about before you
adopt.

### The problem

A `Field` currently holds both what it *is* (its name, type and constraint configuration)
and what happened to it *on this request* (the submitted value, the resolved value,
whether rules changed its optionality). Mixing the two means `validate()` writes to the
schema, which is why a shared instance leaks between concurrent requests
([B7](LIMITATIONS.md#b7)).

### The change

The definition becomes immutable, and everything per-request moves into a `ResolvedField`
produced by `validate()`:

| Definition (immutable) | Per-request (`ResolvedField`) |
| --- | --- |
| field set, names, types, nesting | submitted value, resolved value, transformed value |
| constraint configuration | constraint results |
| authored optionality | rule outcomes actually applied |
| `prefill()` defaults | |

A default is authored by the schema author rather than submitted by the user, so it stays
in the definition. The *rule* (`given ?? default`) is definition; the *outcome* is
resolution.

### What `ResolvedField` looks like

One class for every field type, holding the definition rather than duplicating it. It
extends the existing aggregated-result type, so it *is* the field's result:

```
ResolvedField
    field            // the effective definition
    given            // exactly what was submitted
    resolved         // given ?? default
    transformed      // the typed value — BigDecimal, LocalDate, parsed phone number
    appliedOutcomes  // which rules changed what, and why
    anyFailed(), getFailed(), resultsFor(...)
```

Notable consequences:

- **Effective values need no new API.** Rules produce a modified copy of the definition,
  so `$resolved->field->optional` is the post-rules answer and
  `$schema->fields->getByName('phone')->optional` is the authored one. The same holds for
  `min`, `max`, `step` and any outcome added later, with no per-property plumbing.
- **No `children` property.** Composite constraint names are already qualified paths
  (`addr.postal_code.format`), so a resolved composite is a resolved field whose result
  names are paths. Collections extend the same scheme with an index — `items[1].sku.max`
  — which is how indexed collection results arrive.
- **Reading `transformed` on a failed field throws**, naming the field and the failed
  constraints. On a skipped optional field it is `null`, because the value is legitimately
  absent rather than wrong.
- **Resolving and validating are separate steps.** Rendering a form for the first time
  resolves without validating; that state is what `ValidationStatus::Pending` has always
  described. Hence `Resolved`, not `Validated`.

### What it costs you

If you read values back off a field after validating, that moves to the result. Everything
else — building schemas, reading constraint configuration, serializing — is unchanged.
`meraki/schema-json` needs no changes at all, since it only ever reads definition state.

---

<a id="planned-features"></a>

## Planned features

> **Not on this list: error messages and translations.** Those are a UI concern and are
> deliberately outside the core — see
> [Where error messages come from](../README.md#where-error-messages-come-from). The core
> emits constraint names; presentation packages turn them into prose.

### `1.14.0` — blockers

Everything in [Known defects](LIMITATIONS.md#known-defects), plus the architecture change
above. Nothing here is a new feature; it is all correctness.

### `1.15.0` — the two that unlock the most

**Typed value extraction (hydration).** The library validates but does not transform:
after a successful `validate()` you get your raw input back, not a `BigDecimal`, a
`LocalDate`, or a parsed phone number — even though the parsing already happened
internally and was discarded. The `ResolvedField` seam gives this a home in
`transformed`, which is why it follows immediately after `1.14.0`.

**Cross-field constraints.** There is currently no way to express
`confirm_password === password` or `end_date > start_date`. Rules can only toggle
optionality; they cannot express a constraint *between* two fields. This is the single
most commonly missed capability for real forms.

### Later

**Custom constraints on built-in fields.** Adding one rule to a `Text` field currently
means subclassing `Text`. An `addConstraint(string $name, callable $check)` would cover
the long tail without a new field type.

**Validation groups / partial validation.** No way to validate a subset of fields, which
multi-step wizards need — and `meraki/schema-html` already has wizard state that would
use it.

**Normalization.** No trimming, case-folding or Unicode normalization before validation.
Deliberately absent so far (an earlier sanitization layer was removed in `1.6.0-alpha`),
but worth revisiting as an explicit, opt-in step.

**Richer rule conditions and outcomes.** Conditions are limited to `Equals`/`NotEquals`
and outcomes to `Require`/`MakeOptional`/`Ignore`. `GreaterThan`, `In`, `Matches`,
`IsEmpty` and a `SetValue` outcome are all natural additions — and under the architecture
above, new outcomes need no per-property plumbing.

**External validation hooks.** Checking a value against a database (is this email already
registered?) has no extension point, so the one check almost every signup form needs has
to live outside the schema.

**Field metadata for the UI.** Labels, help text and placeholders currently have to be
maintained separately from the schema that describes the same fields.

**JSON Schema interoperability.** Exporting to — and importing from — JSON Schema would
let a `meraki` schema drive non-PHP consumers directly. A natural fit for
`meraki/schema-json`.

**A dictionary/map field.** `Collection` covers lists; arbitrary string-keyed maps have no
field type.

### Sibling packages

**A default message provider for `meraki/schema-json`.** `meraki/schema-html` ships
`ValidationMessageProvider` and a `ValidationMessages` default; `schema-json` has no
message handling at all, so anyone building a JSON API writes the constraint-name-to-prose
mapping themselves and duplicates what already exists. The shared mapping should be
extracted so both packages consume one source. *(Belongs to `schema-json`, not the core.)*
