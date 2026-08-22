# Roadmap

No dates. The ordering is real; the timing is not promised.

- [Release verdict](#release-verdict) — why this is still pre-release
- [The release ladder](#the-release-ladder)
- [Architecture: immutable definition + `ResolvedField`](#architecture-immutable-definition--resolvedfield)
- [Rule authoring in 1.14](#rule-authoring)
- [API review](API-REVIEW.md) — the per-feature confirmation checklist
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
| `1.14.0-beta.1` | B1–B6 fixed (B8 already done in `1.13.2-alpha`). Dead code deleted. CI running the suite on PHP 8.4 and 8.5. PHPStan raised from its level-1 floor to at least level 5, green. `composer audit` clean. |
| `1.14.0-beta.2` | The `ResolvedField` seam (below): per-request state moves off the fields, fixing B7 together with the purity and mutation defects. Scopes become typed and immutable. `meraki/schema-html` updated in step. |
| `1.14.0-beta.3` | Definition sealed. Indexed paths for collection results. `transformed` populated per field type. [Matcher-based rule authoring](#rule-authoring). Docs accurate. |
| `1.14.0-rc.1` | Public API frozen, **including constraint names** — every row in [API-REVIEW.md](API-REVIEW.md) confirmed. `type` no longer reported as a constraint. Changelog complete. Both sibling packages green against it. |
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

<a id="rule-authoring"></a>

## Rule authoring in 1.14

Rules keep their current semantics and their serialized form. What changes is how they
are written, and where mistakes surface.

### A fluent matcher vocabulary

Jasmine-*like* rather than Jasmine: the point is that a rule reads as a sentence, not
that it copies `expect().toBe()`. Conditions become a named matcher vocabulary instead of
a handful of `whenEquals` variants.

Rules are built as **values**, then added. `$schema->when($field)` starts a condition;
attaching outcomes completes a rule; `allOf()`/`anyOf()` compose conditions.

```php
// one condition
$schema->addRule(
    $schema->when($hasLogBook)->equals(true)
        ->thenRequire($logBookTime)
        ->otherwiseMakeOptional($logBookTime)
);

// several conditions combined into one rule
$schema->addRule(
    $schema->allOf(
        $schema->when($whoFor)->equals('someone_else'),
        $schema->when($whoManages)->equals('participant'),
    )->thenRequire($email)->otherwiseIgnore($email)
);

// several independent rules at once
$schema->addRules($ruleA, $ruleB);
```

`allOf()`/`anyOf()` take **conditions**, never rules — outcomes attach to the composed
rule, so there is exactly one `then`/`otherwise` per rule and no question of whose
outcomes fire. Because a rule is a value, it can be held in a variable, built elsewhere,
and reused.

This replaces `Facade::whenAllMatch()`/`whenAnyMatch()`, whose closure-configurator form
made a rule something you could only declare inline.

The vocabulary, each entry serializing to a condition type and translatable to
client-side JavaScript:

| Matcher | Condition type | Typical subject |
| --- | --- | --- |
| `equals` | `equals` | any |
| `notEquals` | `not_equals` | any |
| `isAtLeast` | `at_least` | number, date, time, duration |
| `isGreaterThan` | `greater_than` | number, date, time, duration |
| `isAtMost` | `at_most` | number, date, time, duration |
| `isLessThan` | `less_than` | number, date, time, duration |
| `isBetween` | `between` | number, date, time, duration |
| `isIn` | `in` | any |
| `contains` | `contains` | text, collection |
| `matches` | `matches` | text |
| `isEmpty` | `is_empty` | any |
| `isNotEmpty` | `is_not_empty` | any |

One spelling per matcher. An earlier sketch had a `createRuleFor($f)->whenItIsAtLeast(18)`
shorthand alongside `when($f)->isAtLeast(18)`; it is dropped, because two names for one
constraint is precisely what [API-REVIEW.md](API-REVIEW.md) exists to remove (see `until()`
vs `to()` on `Date`).

Only `equals` and `not_equals` exist today, so everything from the third row down is new
capability rather than a rename. The set is open: adding a matcher means adding a
condition class and a serializer case, with no change to the rule engine.
### `otherwise()`

An else-branch of outcomes, which today requires a second rule with a hand-inverted
condition that drifts out of step with the first. It is as declarative as `then`, so it
serializes the same way.

### Outcomes name operations, not states

`->thenRequire($field)` records an operation. The tempting alternative —
`->then($field->require())`, handing over an already-modified field — does not work, for
two reasons worth writing down so the idea is not revisited:

- **Snapshots do not compose.** If one rule stores a copy that is required and another
  stores a copy with a new minimum, both derived from the authored field, whichever
  applies last wins the whole field and the other change is silently lost. Operations
  compose; whole-object replacement clobbers.
- **Snapshots lose intent.** `FormRenderer::deriveRuleEffects()` asks
  `$outcome instanceof MakeOptional` to decide what a matched rule did. A replaced field
  has no operation to match on, so `HideOptionalFieldsResolvedByRules` would stop working.

The same reasoning rules out closure-backed conditions, which were considered and
rejected: a retained closure cannot be serialized to JSON for another runtime, cannot be
introspected via `Condition::getScopes()` (which the renderer needs in order to know
which fields a condition depends on), and would make deserializing a schema equivalent to
deserializing code. Builder closures that run once at definition time and record
declarative objects — the existing `whenAllMatch(fn($rule) => …)` form — are unaffected;
the distinction is whether the schema holds data or code once definition finishes.

### Build against the definition, apply against the resolution

- **Authoring** references the authored definition, capturing the field's **name** rather
  than the instance — under the modified-copy model the effective definition is a
  different object, so an instance reference would resolve against a stale one.
- **Application** reads the working set: effective definitions (post earlier outcomes)
  and resolved values. The authored definition is never mutated.

Rules observably read each other's writes today — two rules registered in opposite orders
produce different results — so the working set is threaded through rule application in
order, and each outcome replaces a field's effective definition with a modified copy.

Because an outcome produces a copy of the *same class*, the property set is invariant
between authoring and application: a scope validated when the rule is built cannot become
invalid when it is applied. That is what makes definition-time validation of scopes sound,
which in turn moves scope typos from a 500 on a user request to an error where the rule is
written.

### Scopes become typed and immutable

Addressing stays open — a field's public properties *are* its API, and `#/fields/x/min`
or `#/fields/x/optional` are legitimate targets. What changes:

- **`Property\Name` identifies the field**, not a `Field` instance.
- **Distinct scope types for distinct targets**, so passing a value scope to
  `thenRequire()` is a type error rather than a runtime "Require can only be applied to
  fields".
- **The property segment is validated at definition time** against the field's real
  public properties.
- **Immutable — no iterator cursor.** The current `Scope` mutates its position while
  resolving, which two downstream workarounds already exist to paper over:
  `Html\Wizard\RuleScopes` (four call sites, whose docblock says it "makes those
  re-applications safe without touching core") and `FormRenderer:486`, which stringifies
  an outcome's scope and rebuilds it to avoid disturbing the shared one. Both are deleted.
- **String form unchanged.** `__toString()` and a strict `parse()` keep
  `#/fields/x/value` as the wire format, so `meraki/schema-json` is unaffected.

Sub-field and collection-item addressing — `#/fields/addr/line1`, `#/fields/items/1/sku` —
becomes expressible for the first time, and lines up with the indexed result paths.

### Resolution moves out of the field classes

`Field` and `Facade` are the only two `ScopeTarget` implementations, and path resolution
lives on them as `traverse()`. It moves to a dedicated resolver that walks the working set
(field name → effective definition, plus resolved values). Fields become plain definitions
with no knowledge of paths.

`ScopeTarget`, `ScopeResolutionResult`, `Field::traverse()` and `Facade::traverse()` — and
with them the unconditional cursor rewind — all go.

Open addressing survives unchanged: the resolver reads a field's public properties, so
`#/fields/x/min` and `#/fields/x/optional` keep working. It simply reads them rather than
asking the field to resolve itself.

This also makes B8 structurally impossible rather than patched. A resolver walking a
name-keyed map has no reason to follow a parent pointer, and the
`instanceof ScopeTarget` branch that currently recurses exists *only* to step into
`Field::$schema` — nothing else reachable from a field implements the interface. Remove
the back-reference and the branch is already dead.

Sub-field and collection-item addressing — `#/fields/addr/line1`, `#/fields/items/1/sku` —
gets one place to be implemented, closing both gaps at once.

### `Field::$schema` and `pairWith()`

The back-reference goes. It exists only for `pairWith()`, which uses it to check for a
duplicate name, add the paired field, pass the schema to the configurator, and register
rules — all schema operations wearing a field's clothes. Under create-then-add it is
incoherent anyway: a field returned by `createBooleanField()` is not attached yet, so
there would be no schema to reference.

`pairWith()` goes with it, because the matcher API covers what it did:

```php
// before
$schema->addEnumField('contact_method', ['email', 'phone'])
    ->pairWith(new Field\EmailAddress(new Name('email_address')),
        function (FieldBuilder $rule, Field\EmailAddress $email): void {
            $rule->when($this)->notEquals('email')->thenMakeOptional($email)->thenIgnore($email);
        });

// after
$method = $schema->createEnumField('contact_method', ['email', 'phone']);
$email  = $schema->createEmailAddressField('email_address');

$schema->addRule(
    $schema->when($method)->notEquals('email')
        ->thenMakeOptional($email)
        ->thenIgnore($email)
);
```

No `$this` rebinding, no back-reference, and the paired field is an ordinary field. If the
colocation is worth keeping as sugar it belongs on the schema (`$schema->pair(...)`), where
the schema is already in hand — but the plain form reads well enough that it probably is
not needed.

Cost: 14 references across six files, of which only two are in `src/` (the method itself
and `Rule\FieldBuilder`). The rest are tests in this package, `schema-html` and
`schema-json`.
### `type` stops being a constraint

Every other constraint narrows a value that is already the right shape; `type` decides
whether there *is* a usable value at all, which is the precondition for the rest. It also
conflates "no value was supplied" with "a value was supplied but is the wrong shape",
which downstream code currently has to disentangle by hand. Both become structurally
distinct on `ResolvedField`. See [API-REVIEW.md](API-REVIEW.md) for the consequences.
### What does and does not change

**Unchanged:** the serialized form. `#/fields/x/value` stays the wire format, conditions
and outcomes keep their `type`/`action` shapes, and existing documents round-trip
identically. String scopes remain accepted in the authoring API — parsed and validated at
definition time rather than carried raw — so string-based rules keep working.

**Changed:** `Facade::whenAllMatch()`/`whenAnyMatch()` give way to rules built as values
(`$schema->when(...)`, composed with `allOf()`/`anyOf()`, then `addRule()`/`addRules()`);
`addXField()` becomes `createXField()` plus an explicit add; `pairWith()` and
`Field::$schema` are removed; per-request state is read from the result rather than off
the field.

All of it lands in the same breaking release as the seam, so there is one migration
rather than three.---

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
