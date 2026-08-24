# The field definition contract

What a field author writes, and what the core does for them.

The governing idea: **a field describes itself; the core decides what happens.** A field
author says what shape it accepts and what constraints it carries. The order those are
checked in, when a constraint is skipped rather than failed, how absence differs from
malformed input — none of that is theirs to get right, because getting it wrong in one
field would make results inconsistent across a schema.

Everything below marked *verified* was tested against PHP 8.5.9 rather than assumed.

---

## The base

```php
abstract readonly class Field
{
    public function __construct(
        public Property\Name $name,
        public bool $optional = false,
        public mixed $default = null,
    ) {}

    /** Is this a value of the shape this field is about? */
    abstract public function accepts(null $value): bool;

    /** The value as this field's own type. */
    abstract public function cast(null $value): mixed;

    /** @return Constraints name => check */
    abstract public function constraints(): Constraints;

    final public function resolve(mixed $given): ResolvedField { /* core */ }
    final public function validate(mixed $given): ResolvedField { /* core */ }
}
```

`resolve()` and `validate()` are **final**. That is the whole point: a field author cannot
change the order of operations, because a schema whose fields disagree about when a
constraint is skipped is worse than one with a rigid rule.

### `null` as the parameter type — verified

The parent declares `null`; every field widens it to what it really takes:

```php
abstract public function accepts(null $value): bool;      // Field
public function accepts(?string $value): bool             // Text
public function accepts(int|float|null $value): bool      // Number
public function accepts(?array $value): bool              // Address
```

PHP allows widening a parameter type and forbids narrowing, so this works where `mixed`
would not — `mixed` is already the widest, and nothing can narrow it. Verified:

| | |
| --- | --- |
| `null` → `?string`, `?array`, `int\|string\|null`, `mixed` | allowed |
| `null` → `string` (dropping nullability) | **rejected** |
| core calling `$field->accepts($mixed)` through the base type | works at runtime, clean at PHPStan level 6 |

The rejection is the valuable half: a field **cannot** declare that it does not handle
absence. Every field must have an answer for "nothing was supplied", because every schema
can leave a field empty.

### `readonly` is all-or-nothing — verified

`Readonly class C cannot extend non-readonly class P`. So `Field` and every abstract
between it and a concrete field must be readonly too. Withers use `clone` with property
overrides, which works from inside the class:

```php
public function minLengthOf(int $chars): static
{
    if ($chars < 0) { throw new InvalidArgumentException(/* … */); }
    if ($chars > $this->maxLength) { throw new InvalidArgumentException(/* … */); }

    return clone($this, ['minLength' => $chars]);
}
```

Invariants are checked before the clone, so an invalid field cannot be constructed.

**No `__clone()`.** Deep-cloning `$name` there would break identity — verified that
`$original->name === $clone->name` becomes false — which is the detached-copy bug already
found and fixed twice in this codebase.

And `Property\Name` stops being mutable at all. Its one mutable part, `$prefix`, exists
solely so a composite can rename its sub-fields to `addr.line1`. Once structured types own
their whole value and their parts are internal rather than schema-registered fields,
nothing prefixes anything — `prefixWith()`, `removePrefix()` and `$prefix` all go, and a
name becomes a plain immutable value with no reason to copy it.

---

## Constraints

`constraints()` returns a `Constraints` collection, not a bare array, so the core has one
place to enumerate and the type says what it is.

**A constraint's name is its property's name.** `minLength` the constraint is `$minLength`
the property. That is a deliberate coupling: `meraki/schema-html` reads
`$field->{$constraintName}` to build a message, so the two cannot drift. Renaming a public
property is therefore an API break for message providers, and is treated as one.

```php
public function constraints(): Constraints
{
    return new Constraints(
        minLength: $this->checkMinLength(...),
        maxLength: $this->checkMaxLength(...),
        pattern:   $this->checkPattern(...),
    );
}
```

A check returns `true` (satisfied), `false` (violated), or `null` (not applicable — the
constraint is skipped, not failed).

**Not by reflection.** Discovering constraints from public properties was considered and
rejected: `$default`, `$name` and `$optional` are public and are not constraints, and a
property added for any other reason would silently become one.

---

## Shape versus constraint

The distinction the whole design rests on.

**Shape** is what the field *is*. An email address is `<local>@<domain>`, so it can never
be empty and never shorter than three characters — that is not a constraint anyone
configured, it is what an email address means. `accepts()` answers this.

**Constraints** narrow a value that already has the right shape. `minLength`, `between`,
`allowedSchemes`.

The core enforces the consequence: **if the shape is wrong, constraints are skipped, not
failed.**

That is not only about tidy error reports, though it gives those too — one real problem
named once, rather than once per constraint. It is mainly what lets a constraint check be
written plainly:

```php
minLength: fn(string $v): bool => mb_strlen($v) >= $this->minLength,
```

No guard, no `is_string()`, no null check. A constraint only ever runs on a value that
already has the right shape, because the core guarantees it. Without that rule every
author would have to defend every check against every wrong type — and the first one to
forget would produce a `TypeError` instead of a validation failure.

---

## Order of operations — owned by the core

For each field, in this order:

1. **Was a value supplied?** No → go to 2. Yes → go to 3.
2. **Nothing supplied.** A default is used if there is one (see below). Otherwise a
   required field fails its shape check and an optional one is skipped, and either way
   every constraint is skipped.
3. **Shape.** `accepts()` says no → shape fails, every constraint is skipped.
4. **Constraints.** Each check runs; `true`/`false`/`null` become passed/failed/skipped.
5. **Cast.** Only on a value that passed, and only when read.

---

## Defaults

**A default is a schema concern, and it is static.** Every real use is an author writing a
fixed value — `->prefill('automatic')` in the examples, `schema-json` restoring a
serialised one. `meraki/schema-html` never sets one.

| Situation | Shape | Constraints | Required satisfied |
| --- | --- | --- | --- |
| Client supplied a value | checked | checked | yes |
| Nothing supplied, field has a default | checked | **skipped** by default, checked under `PrefillPolicy::Checked` | **yes** |
| Nothing supplied, no default, required | fails | skipped | no |
| Nothing supplied, no default, optional | skipped | skipped | n/a |

A default is **trusted**: it may predate the constraints now on the field, or come from a
record written when they were different. It must still be the right shape, because a
default of the wrong type is an authoring error rather than a data one.

The consequence worth stating plainly: `$resolved->value` is not guaranteed to satisfy the
field it belongs to. Anything reading values back has to accept that.

### How much a default is trusted — `PrefillPolicy`

Blanket trust is not always right. A value read from a record written years ago deserves
different treatment from a literal the author just typed. So the trust is stated where the
default is set:

```php
public function prefill(null $value, PrefillPolicy $policy = PrefillPolicy::Trusted): static
```

| Policy | Shape | Constraints |
| --- | --- | --- |
| `Trusted` (default) | checked | skipped — it may predate them |
| `Checked` | checked | checked |

**Two, not three.** A third policy that skips the shape check as well was considered and
rejected: a default of the wrong *type* is an authoring mistake, not stale data, and
nothing downstream can do anything sensible with it — `cast()` would fail on it too. Shape
is always checked, so `Trusted` means "trusted to still be valid", not "trusted blindly".

**Both are checked eagerly, when the field is built, not when a request arrives.** An
invalid default is a bug in the schema, and surfacing it as a validation failure would
blame the user for something they did not do. Throwing at `prefill()` puts the error where
the mistake is.

That has a consequence worth planning for. Constraints can be added *after* a default:

```php
$field->prefill('ab', PrefillPolicy::Checked)->minLengthOf(5);   // now stale
```

Because every wither returns a new field, each one can re-check the default and throw
here, at `minLengthOf()`. It is the immutable design that makes this affordable — there is
no way to change a field without passing through a wither. `Trusted` needs none of it,
since shape cannot change once the field's type is fixed.

### Where per-request prefilling goes

Not on the definition. A default that varies per user — their saved address, their last
answer — is application data, and writing it onto a schema that is built once and shared
is the same mistake as writing input onto it.

So `Facade::prefill($data)`, which takes a bulk array shaped like a request, goes. It has
no consumer today. Per-request values are supplied alongside input when resolving, and
land on the `ResolvedField`, never on the `Field`.

That is the line: **a default the author wrote is part of the schema; a value fetched for
this user is not.**

---

## Optionality

On the base class, not a trait. Every field can be optional — whether it is depends on the
schema, not the field type — so a trait would only add somewhere to forget it.

Required is the default. `optional` is definition state and can be changed by a rule, in
which case the resolved field records which rule did it.

---

## `cast()`

Replaces `transform()`, and absorbs the existing protected `cast()` methods, which already
do this for `Number` (string to `BigDecimal`). Called only on a value that passed
validation, and only when read through `ResolvedField::$transformed`.

The default is the identity, so a field with no richer type to offer says so by not
overriding it.

---

## What a field author writes, in full

```php
final readonly class Text extends Atomic
{
    public const MATCHES_ANYTHING = '/.*/';

    public function __construct(
        Property\Name $name,
        bool $optional = false,
        mixed $default = null,
        public int $minLength = 0,
        public int $maxLength = PHP_INT_MAX,
        public string $pattern = self::MATCHES_ANYTHING,
    ) {
        parent::__construct($name, $optional, $default);
    }

    public function accepts(?string $value): bool
    {
        return $value !== null;
    }

    public function cast(?string $value): ?string
    {
        return $value;
    }

    public function constraints(): Constraints
    {
        return new Constraints(
            minLength: fn(string $v): bool => mb_strlen($v) >= $this->minLength,
            maxLength: fn(string $v): bool => mb_strlen($v) <= $this->maxLength,
            pattern:   fn(string $v): bool => preg_match($this->pattern, $v) === 1,
        );
    }

    public function minLengthOf(int $chars): static { /* validate, then clone-with */ }
    public function maxLengthOf(int $chars): static { /* validate, then clone-with */ }
    public function matches(string $regex): static { /* validate, then clone-with */ }
}
```

Three abstract methods and some withers. No validation orchestration, no result
construction, no decisions about skipping — the core owns all of it.

---

## Validating part of a schema — `ValidationScope`

A stepped form cannot validate everything at once: fields on later steps have not been
asked yet, and a required one would fail for the sensible reason that the user has not got
to it. So the caller says which fields are in play.

```php
$schema->validate($data);                                    // all of it
$schema->validate($data, ValidationScope::only('a', 'b'));   // just these
$schema->validate($data, ValidationScope::except('c'));      // all but these

$schema->validate($data, ValidationScope::only(...$currentStep->fields));
```

This is UI-neutral: the core knows nothing about steps, wizards or pages — only that a
caller wants some fields checked and not others. What a step *is* stays in
`meraki/schema-html`.

**The whole schema is always resolved; the scope only limits checking.** That distinction
is the important one, and it is not a detail:

- Values are resolved for every field, in or out of scope.
- **Rules apply across the whole schema**, always. A rule on step 3 may be what makes a
  step 1 field optional, so evaluating only the current step's rules would validate step 1
  against the wrong requirements.
- Only constraint checking is narrowed.

**Out-of-scope fields come back `Pending`**, not missing. The result then describes the
whole schema, and "not checked yet" is exactly what `Pending` has always meant. A caller
can still ask `anyFailed()` and get an answer about what was actually examined.

### It replaces something that already exists

`meraki/schema-html` hand-rolls this in `Wizard\Validator::validateGroup()`, whose own
docblock explains why it has to:

> *The whole-schema validator would fail not-yet-reached required fields, so a stepped form
> must validate group by group.*

It resolves the whole schema and then validates a subset — the same semantics arrived at
above, built downstream because the core offered nothing. Moving it here deletes that
class, and it is the fifth piece of machinery `schema-html` sheds in this refactor,
alongside `deriveRuleEffects()`, the `ruleEffects` array, `Wizard\RuleScopes` and the
`FormRenderer:486` workaround.

One behavioural difference to note when it moves: the current implementation *omits*
out-of-scope fields from the result rather than marking them `Pending`.

---

## Still open

- **`Composite`, `Collection` and `Variant` each override the whole validation path**, so
  for those three the order is *not* centrally enforced. Making `resolve()`/`validate()`
  final means giving them a narrower hook instead. This is the main unresolved piece.
- **When this lands.** It is Stage 3/5 work — sealing the definition and settling names —
  not Stage 1. Stage 1's seam does not require it, and doing both at once makes a large
  change larger.
- **`ValidationScope` and rules.** Rules apply schema-wide while checking is narrowed, so
  a rule outcome can make an out-of-scope field required. That is correct, but it means a
  step can pass while the schema as a whole is not yet satisfiable — the caller has to
  validate the whole thing before acting on it, and that should be said in the docs rather
  than discovered.
