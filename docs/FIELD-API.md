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
found and fixed twice in this codebase. `Property\Name` should be genuinely immutable
instead, so there is never a reason to copy it.

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
failed.** An error report then names the real problem once instead of once per constraint.

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
| Nothing supplied, field has a default | checked | **skipped** | **yes** |
| Nothing supplied, no default, required | fails | skipped | no |
| Nothing supplied, no default, optional | skipped | skipped | n/a |

A default is **trusted**: it may predate the constraints now on the field, or come from a
record written when they were different. It must still be the right shape, because a
default of the wrong type is an authoring error rather than a data one.

The consequence worth stating plainly: `$resolved->value` is not guaranteed to satisfy the
field it belongs to. Anything reading values back has to accept that.

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

## Still open

- **`Composite`, `Collection` and `Variant` each override the whole validation path**, so
  for those three the order is *not* centrally enforced. Making `resolve()`/`validate()`
  final means giving them a narrower hook instead. This is the main unresolved piece.
- **When this lands.** It is Stage 3/5 work — sealing the definition and settling names —
  not Stage 1. Stage 1's seam does not require it, and doing both at once makes a large
  change larger.
