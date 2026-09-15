# How `meraki/schema` compares

## Positioning

`meraki/schema` is the only PHP library that lets you define a form **once**, as a
serializable, UI-agnostic schema, with real domain field types — addresses, phone
numbers, money, credit cards — validated against curated standards data rather than
hand-rolled regexes.

Nothing else in PHP occupies that spot. The JavaScript ecosystem has JSON Forms, React
JSON Schema Form and Formily solving the schema-driven-form problem; PHP has generic
validators, or framework-coupled form builders, and nothing in between.

Two consequences follow, and they are the axes on which every comparison below turns:

- **The schema is data.** It serializes (`meraki/schema-json`), so one definition can
  drive an HTML form, a JSON API, and a native client without any of them re-declaring
  the rules.
- **Messages are not in the core.** Turning `min` into "must be at least 3 characters"
  is presentation, and lives in the presentation package. See
  [Where error messages come from](../README.md#where-error-messages-come-from).

## Summary

| Library | Shape | What it does better | What `meraki/schema` does better |
| --- | --- | --- | --- |
| [symfony/validator](#symfonyvalidator) | Constraint objects on classes, attribute-driven | Enormous constraint set, mature messages + translations, groups, cascading | Schema is serializable data; domain field types rather than generic constraints |
| [symfony/form](#symfonyform) | Form definition + rendering + data mapping | Data mapping to objects, CSRF, theming, ecosystem | UI-agnostic core, rendering optional; far smaller surface; no framework coupling |
| [nette/forms](#netteforms) | Form definition + rendering + conditional rules | The closest analogue — mature, conditional rules, generates client-side JS | Serializable schema; standards-backed field types; no DI-container coupling |
| [illuminate/validation](#illuminatevalidation-laravel) | Array rule DSL | Ubiquitous, terse, excellent messages + localization | Type-safe fluent API instead of stringly-typed rules; real value objects |
| [respect/validation](#respectvalidation) | Fluent chainable rules | 200+ rules, expressive chaining | Models a *form*, not isolated value assertions |
| [cuyz/valinor](#cuyzvalinor) | Maps arrays to typed objects | Excellent hydration and type inference | Form semantics — optionality, prefill, conditional requirements |
| [opis/json-schema](#opisjson-schema) | JSON Schema validation | Standards-compliant, portable, tooling everywhere | Domain-aware field types; conditional rules JSON Schema expresses awkwardly |

---

## symfony/validator

Constraints are objects attached to class properties, usually via attributes, and a
validator walks the object graph. It is the most complete constraint library in PHP, with
messages and translations in dozens of languages, validation groups, and cascading
validation of nested objects.

**Pick it over this** when you are validating *domain objects* rather than *forms*, when
you are already in Symfony, or when you need breadth of constraints today.

**Pick this over it** when the same definition has to drive more than one medium. A
Symfony constraint graph is PHP code hanging off PHP classes; it does not serialize, so a
JavaScript client cannot consume it without you re-declaring the rules by hand.

## symfony/form

The full form stack: definition, rendering, theming, CSRF, and mapping submitted data
onto objects. Functionally it is the biggest overlap with `meraki/schema` +
`meraki/schema-html` combined.

**Pick it over this** when you are in Symfony and want data mapping and a mature theming
system out of the box.

**Pick this over it** when you want the *definition* to be independent of the rendering.
Symfony form types conflate what a field is with how it is displayed; this library keeps
them in separate packages, so a schema can be rendered as HTML, serialized as JSON, or
consumed by something that is neither.

## nette/forms

The closest analogue in PHP. Defines forms with fields, validation rules and conditional
rules, renders them, and generates matching client-side JavaScript from the same
definition — the "define once" idea that motivates this library.

**Pick it over this** for maturity. It is production-proven, has messages built in, and
the client-side generation is genuinely excellent.

**Pick this over it** for three things: the schema serializes to JSON as a first-class
concern; the field types are domain-level (an `Address` that knows Australian postcodes
differ from Singaporean ones) rather than generic input types; and there is no coupling
to the Nette DI container or application stack.

## illuminate/validation (Laravel)

Rules as strings in an array — `'age' => 'required|integer|min:18'`. Terse, familiar to a
very large number of PHP developers, with excellent messages and localization.

**Pick it over this** if you are in Laravel. The integration is worth more than anything
this library offers.

**Pick this over it** when you want the rules to be inspectable and type-safe. A pipe
string is opaque: nothing can tell you what constraints a field carries without parsing
it, and a typo in a rule name is a runtime error. Here a field is an object with typed
properties, so both your IDE and a static analyser can see it.

## respect/validation

A large catalogue of chainable rules for validating values.

**Pick it over this** for validating individual values anywhere in an application. It has
far more rules and a more expressive chaining API.

**Pick this over it** when the thing you are validating is a *form*: a named set of
fields, some optional, some conditionally required based on others, with defaults.
Respect validates values; it has no concept of a field being optional, prefilled, or
required only when another field says so.

## cuyz/valinor

Maps arrays onto strongly-typed PHP objects, inferring the mapping from type declarations
and producing detailed errors when the source does not fit.

**Pick it over this** for hydrating typed objects from untrusted input, which it does
better than anything else in PHP.

**Pick this over it** for form semantics. Valinor deliberately has no notion of an
optional field, a default value, or a conditional requirement — those are form concepts,
not mapping concepts. (Hydration is on this library's roadmap, and the two are
complementary rather than competing.)

## opis/json-schema

Validates data against JSON Schema documents. Standards-compliant, portable, with tooling
in every language.

**Pick it over this** when interoperability with the JSON Schema ecosystem matters more
than anything else, or when the schema is authored outside PHP.

**Pick this over it** for domain-aware validation. JSON Schema can express "a string
matching this pattern"; it cannot express "a valid Australian address", because the rules
for that are data, not a regex. Conditional requirements are also expressible in JSON
Schema only through `if`/`then`/`allOf` gymnastics that are painful to author and read.
(JSON Schema *export* is on the roadmap — the two are not mutually exclusive.)

---

## Extending it: what a new type costs

The axis the table above does not cover, and the one where the gap is widest. The question is
not "can I add a type" — everything here can — but **what do I keep when I do**.

| Library | Adding a type or rule | Registration | Static types kept | First-class result |
| --- | --- | --- | --- | --- |
| **meraki/schema** | Extend `AtomicField`; write `parse()` and `defineConstraints()` | **None** | Full — a real class, its own withers, its own properties | Identical to a built-in |
| symfony/validator | `Constraint` + `ConstraintValidator` pair | Autoconfigured in Symfony, manual elsewhere | Full | Yes — but it is a *constraint*; there is no field type to be first-class in |
| symfony/form | `AbstractType` subclass | Service tag | Config is a stringly-keyed options array | Yes, with DI and a theme to write |
| nette/forms | Control extending `BaseControl`, or `extensionMethod()` | Required | Partial — validators are callbacks and constants | Yes; the closest analogue overall |
| laminas-inputfilter | `ValidatorInterface` implementation | Plugin manager | Config arrays throughout | A validator, not a type |
| illuminate/validation | `Validator::extend('isbn', fn)` plus a lang string | One global call | **None** — rules are strings, arguments are strings | No type exists; a named closure |
| respect/validation | Drop a `Rule` class in a namespace | Convention only | Resolved by magic static; no completion | A rule, not a field |
| cuyz/valinor | Register a constructor or transformer | On the mapper builder | Excellent | Hydration, not form semantics |
| opis/json-schema | Custom format or keyword handler | On the validator | Schema is data, not PHP types | Portable, but no domain types |

Two of them make extension genuinely cheap — **respect/validation** (drop a class, convention
resolves it) and **illuminate/validation** (one closure) — and both pay for it with the type
system. Two make it type-safe — **symfony/validator** and **valinor** — and both charge
registration, and neither has a *field type* to extend: Symfony extends the constraint
vocabulary, valinor extends hydration.

**This library is the only one where a third-party type is indistinguishable from a built-in,
with no registration and full static types.** That is a consequence of the architecture rather
than a feature, which is why it is worth stating: serialization is something a competitor could
add, and this is not. See [EXTENDING.md](EXTENDING.md), and
[examples/custom-field.php](../examples/custom-field.php), which is a complete field type in
about forty lines.

**The honest other half.** The *field* axis is where this wins; the *constraint* axis is where
it loses. Symfony ships something like eighty constraints, and adding one to an existing type is
a two-class job. Here, adding a constraint to a built-in field is closed entirely — every field
is `final readonly` — so "I need `Text` to also reject reserved words" means reimplementing
`Text`. Anyone evaluating this will hit that within a week.

---

## On messages, where this comparison usually gets lost

Every library above bundles error messages and translations into the validator. This one
splits them out on purpose: the core emits constraint *names*, and the presentation
package owns the prose.

Frame that as a **trade**, not a deficit. You give up "install one package, get English
error strings"; you get a core that can drive an HTML form, a JSON API and a native
client from one definition, without any of them inheriting another medium's wording — and
without the core needing to know that "This is required" is the right phrasing for an
empty required field in a browser but not necessarily in an API response.

The cost is real and worth stating plainly: adopting the core alone means writing a
message provider. Today only `meraki/schema-html` ships one.

## The honest argument against

**Maturity, not design.** Every library in the table above has years of production use behind
it, a large body of answered questions, and people other than its author who know how it works.
This one has continuous integration, static analysis, a generated changelog and a test suite in
the thousands — and none of that is the same thing as having been wrong in public often enough
to have learned.

Three specific costs, none of which are bugs:

- **You will write a message provider** unless you also take `meraki/schema-html`.
- **You cannot add a constraint to a built-in field**, only a whole new field type.
- **The comparison matchers are not built yet** — rules can ask `equals` and `notEquals`, not
  `isAtLeast`. The interface they will be written against exists; the verbs do not.

For a team choosing a validator today, that — not any missing feature — is the reason to choose
something else. See [LIMITATIONS.md](LIMITATIONS.md) for what is actually broken, and
[ROADMAP.md](ROADMAP.md) for what is planned.
