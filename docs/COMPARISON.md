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

**Maturity, not design.** Every library in the table above has continuous integration,
static analysis, published coverage, a changelog, and years of production use. This one
has none of those yet and is still tagged alpha.

For a team choosing a validator today, that — not any missing feature — is the reason to
choose something else. See [LIMITATIONS.md](LIMITATIONS.md) for what is actually broken,
and [ROADMAP.md](ROADMAP.md) for the path to a stable release.
