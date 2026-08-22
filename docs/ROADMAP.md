# Roadmap

No dates. The ordering is real; the timing is not promised.

- [Release verdict](#release-verdict) — 1.14.0 stable, then 2.0.0 for the redesign
- [The releases](#the-release-ladder)
- [Architecture: immutable definition + `ResolvedField`](#architecture-immutable-definition--resolvedfield)
- [Rule authoring in 2.0](#rule-authoring)
- [API review](API-REVIEW.md) — the per-feature confirmation checklist
- [Planned features](#planned-features)

---

<a id="release-verdict"></a>

## Release verdict

**`1.14.0` ships the defect fixes and is stable. The redesign lands separately, as
`2.0.0`.**

The architecture is sound: the domain model is good, the package separation holds, and the
field types backed by curated standards data (`Money`, `Date`, `PhoneNumber`, `Address`,
`Uuid`) are correct and well covered by the suite. What blocked a stable release was a
short list of specific defects, not anything structural — see
[LIMITATIONS.md](LIMITATIONS.md).

Those are nearly done, so stable is close. The redesign that follows — an immutable
definition, per-request state in a `ResolvedField`, structured types that own their whole
value — is a genuine improvement but a large one, and folding it into the same release
would keep stable permanently out of reach. The library has been alpha for sixteen tags;
that is long enough.

So the two are separated. `1.14.0` is the release that answers "can I use this?". `2.0.0`
is the release that answers "is this the shape it should have been?" — and it gets to be
right rather than rushed, because a supported stable release is standing while it happens.

<a id="the-release-ladder"></a>

## The releases

### `1.14.0` — the defects, then stable

Essentially today's API, with the defects gone. No architectural change.

| Stage | Gate |
| --- | --- |
| `1.14.0-beta.1` | B3 fixed (B1, B2, B4, B5, B6 and B8 already done). Requires PHP 8.5. Dead code deleted. CI on PHP 8.4 and 8.5. PHPStan raised from its level-1 floor to at least level 5, green. `composer audit` clean. |
| `1.14.0-rc.1` | Docs accurate. Changelog complete. Both sibling packages green against it. |
| `1.14.0` | **First stable release.** The semantic-versioning commitment starts here. |

**B7 is the exception, and it is deliberate.** Sharing one schema across concurrent
requests leaks data between them, and the real fix is the `ResolvedField` seam, which
belongs to `2.0.0`. `1.14.0` therefore ships with B7 documented rather than fixed: serial
reuse is safe, concurrent reuse is not, and building the schema per request costs 0.25 ms.
That is an honest limitation with a cheap workaround, not a reason to hold up every other
fix behind a rewrite.

### `2.0.0` — the redesign

Breaking by construction, so a major version regardless.

| Theme | What changes |
| --- | --- |
| **The seam** | Immutable definition; per-request state moves into [`ResolvedField`](#architecture-immutable-definition--resolvedfield). Fixes B7 along with the purity and mutation defects. `Field` sheds `input()`, `ignoreInput()`, `acceptInput()` and its value properties. |
| **Real PHP types** | The core takes arrays, lists, objects and scalars; the UI layer converts. `EmailAddress` takes one address; `Field\AtomicMultiValue` and the comma-splitting in `EmailAddress::parseValue()` go — both are `<input type="email" multiple>` leaking into the domain. |
| **Structured types** | `Composite` is removed. `Address`, `Money` and `CreditCard` become distinct types with their own public API, each taking an object shape and validating it. Dotted constraint names (`addr.postal_code.format`) go with it, so the replacements have to be chosen deliberately — downstream message providers match on them. `Variant` stays: it is a union type, not conditional logic. |
| **Scopes** | Typed and immutable; resolution moves out of the field classes. `ScopeTarget`, `traverse()` and `Wizard\RuleScopes` all go. |
| **Rules** | [Matcher vocabulary](#rule-authoring), `otherwise()`, rules built as values. Enough expressiveness that a type like `Address` no longer hand-rolls its per-country conditionals as constraint closures. |
| **API surface** | `addXField()` becomes `createXField()` plus an explicit add; `pairWith()` and `Field::$schema` are removed; `type` stops being reported as a constraint; every row in [API-REVIEW.md](API-REVIEW.md) confirmed and the public API frozen. |

### `2.1`, `2.2`, … — feature releases

Additive, after the redesign has settled. Each is a minor version.

| Feature | Notes |
| --- | --- |
| **Richer `Uri`** | Absolute and relative, URL and URN, RFC 3986 and WHATWG, and the plain shape check. Built on PHP's native `Uri\Rfc3986\Uri` and `Uri\WhatWg\Url` rather than a hand-rolled pattern — which is the "standards data over hand-typed tables" principle applied to the one field that most violates it. Requires PHP 8.5. |
| **`Duration` on PHP's own class** | PHP 8.6 is expected to add a native duration type; adopt it in place of the current handling. Requires PHP 8.6. |
| **Typed value extraction** | `transformed` populated per field type — `BigDecimal`, `LocalDate`, a parsed phone number, an address value object. |
| **Cross-field constraints** | `confirm_password === password`, `end_date > start_date`. |
| **The rest** | Custom constraints on built-in fields, validation groups, normalisation, external validation hooks, field metadata for the UI, JSON Schema interoperability, a dictionary/map field. |

Because these gate on newer PHP versions, they are minors rather than patches: `2.0`
keeps the `2.0` floor, and a release that needs 8.5 or 8.6 says so.

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

## Rule authoring in 2.0

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

Everything planned is in [the release table above](#the-release-ladder): defects in
`1.14.0`, the redesign in `2.0.0`, additive features from `2.1` onwards. This section
records only what is deliberately *not* planned, and the one item that belongs to a
sibling package.

> **Not on the list: error messages and translations.** Those are a UI concern and are
> deliberately outside the core — see
> [Where error messages come from](../README.md#where-error-messages-come-from). The core
> emits constraint names; presentation packages turn them into prose.

### Sibling packages

**A default message provider for `meraki/schema-json`.** `meraki/schema-html` ships
`ValidationMessageProvider` and a `ValidationMessages` default; `schema-json` has no
message handling at all, so anyone building a JSON API writes the constraint-name-to-prose
mapping themselves and duplicates what already exists. The shared mapping should be
extracted so both packages consume one source. *(Belongs to `schema-json`, not the core.)*
