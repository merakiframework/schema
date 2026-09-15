# The field definition contract

What a field author writes, and what the core does for them.

The governing idea: **a field describes itself; the core decides what happens.** A field author
says what shape it accepts and what constraints it carries. The order those are checked in, when a
constraint is skipped rather than failed, how absence differs from malformed input — none of that
is theirs to get right, because getting it wrong in one field would make results inconsistent
across a schema.

---

## The two halves

A field is made of two things that vary independently, so they are two types rather than one base
class.

**`Field`** is an interface — what every field *offers*, regardless of how it validates:

```php
interface Field
{
    public FieldName $name { get; }
    public bool $optional { get; }
    public mixed $defaultValue { get; }
    public Constraint\Set $constraints { get; }

    public function defaultsTo(mixed $value): static;
    public function makeOptional(): static;
    public function makeRequired(): static;
    public function equals(self $other): bool;

    public function resolve(mixed $given, array $appliedOutcomes = [], ValueSource $givenAs = ValueSource::Submitted): AggregatedValidationResult;
    public function validate(mixed $given, array $appliedOutcomes = [], ValueSource $givenAs = ValueSource::Submitted, PrefillPolicy $policy = PrefillPolicy::Checked): AggregatedValidationResult;
    public function resolvedValueFor(mixed $given): mixed;
}
```

Every member is `{ get; }` only, so an implementation may be `readonly` — and readonly is what
makes a schema safe to share across concurrent requests. Get-only also lets an implementation
*narrow* a type: `Field::$defaultValue` is `mixed`, and a text field is free to declare it
`?string`.

**`Field\Definition`** is a trait — everything except how the field validates. Configuration,
copy-on-change, the default check. Every field uses it, and it is where `parse()` lives.

**`AtomicField`** is one *lifecycle* built on both: the ordinary "one value, checked against
constraints" path that nearly every field wants. A field whose value is not one value implements
`Field` directly and uses `Definition` — see [A field that is not one value](#a-field-that-is-not-one-value).

The split is the point. A field should be able to pick a lifecycle without inheriting the wrong
name for what it holds.

---

## What a field author writes

Four things. Here is the whole of `Field\Boolean`, which is the smallest real field:

```php
final readonly class Boolean extends AtomicField
{
    public bool $requiresAcceptance;

    public function __construct(
        public FieldName $name,          // 1. the name, promoted
    ) {
        parent::__construct();           // initialises $optional and $defaultValue

        $this->requiresAcceptance = false;
        $this->constraints = $this->defineConstraints();   // last, once its own state is set
    }

    public function mustBeAccepted(): static               // 2. configuration, as withers
    {
        return $this->with(['requiresAcceptance' => true, 'optional' => false]);
    }

    protected function parse(mixed $value): ?Value         // 3. the one conversion hook
    {
        return is_bool($value) ? new Value($value) : null;
    }

    protected function defineConstraints(): Constraint\Set // 4. what it checks
    {
        return new Constraint\Set(
            new Constraint('accepted', $this->wasAccepted(...), $this->requiresAcceptance),
        );
    }

    private function wasAccepted(Value $parsed): ?bool
    {
        return $this->requiresAcceptance ? $parsed->answer === true : null;
    }
}
```

**The constructor order matters and is not optional.** `parent::__construct()` first, then the
field's own properties, then `$this->constraints = $this->defineConstraints()` last — because
constraints are built *from* those properties. A field that gets this wrong fails
`Api\SealedFieldTest`, which checks it for every field.

---

## `parse()` — the one hook

```php
abstract protected function parse(mixed $value): ?ParsedValue;
```

It replaced `process()`, `validateValue()` and `transform()`, which between them parsed most values
twice and had to agree with each other to be correct. Four things hold, and everything downstream
depends on them:

- **It never receives `null`.** Absence is settled before it runs — no input and no default means
  there is nothing to read, so the field is skipped or reported missing without this being called.
  `null` in the *return* therefore means one thing only: **unreadable**.
- **It never raises.** It runs on attacker-controlled input, so an unreadable value is reported,
  not thrown. Failing to parse is ordinary, not exceptional — throwing belongs to definition time,
  where the author can act on it.
- **What it returns is what the constraints see.** So a constraint is typed `Number\Value` and has
  that be true by construction rather than by hoping a gate ran first.
- **It always returns a value object this library defines.** Never a bare scalar, and never a third
  party's class. See below.

The third point is also what enforces totality: every accepted value in every test passes through
that line, so a `parse()` that raises fails the test that submitted the value.

### Why a value object, always

Everything downstream eventually has to ask whether two values are the same — a collection deciding
whether two rows repeat, a rule deciding whether a field holds what it is asking about, and every
comparison matcher. That answer used to come from PHP's `==`, which compares two objects property by
property. It was therefore reading the *private layout* of whatever class a field happened to return:

| Returned | `==` says | Truth |
| --- | --- | --- |
| `BigDecimal` `12.50` / `12.5` | different | one number — `BigDecimal` keeps the scale it was given |
| `LocalDate`, `Duration` | correct | correct **by accident** — nothing promises those layouts |
| libphonenumber's number | different | one number — it carries the raw input alongside the parsed one |

The middle row is the important one. Those work today because of how Brick happens to store them; a
memoised formatted string added in some later release would silently make equal dates unequal, with
nothing here changed and no test obviously breaking.

So every field defines its own value class, and that class is the authority on its own equality.
[`Field\ParsedValue`](../src/Field/ParsedValue.php) is the contract; [`Field\Comparable`](../src/Field/Comparable.php)
adds ordering for the values that have one — numbers, dates, times, durations — and is what the
comparison matchers will be written against.

**Scalars are wrapped too.** `===` on a string is already right, so `Text\Value` buys nothing by
itself. What it buys is that nothing downstream ever branches on whether a value happens to be an
object, and the exemption would have leaked into every consumer that compares, renders or serialises
one. The plain scalar is one property away — `$resolved->value->text`.

It also leaves somewhere to put things later: a normalising `Uri` and a `Duration` on PHP 8.6's
native type are both in [ROADMAP.md](ROADMAP.md), and behind a value object each is an internal
change rather than a break in what `parse()` returns.

There is no exemption, including for `Collection` — whose value is *many* values rather than one,
and which therefore had the strongest claim to being special. It gets a `Collection\Value` like
everything else, holding the rows under their submitted keys. So the return type is the whole
contract: a value object, or nothing.

That value is where a collection's rows are reached, because rows are *data* and the result holds
*verdicts*:

```php
$lines = $resolved->value;              // Collection\Value

$lines->keys();                         // ['first run', 'second run']
$lines->rowAt('first run')->sku;        // Text\Value  — one row's field
$lines->valueOf('second run', 'qty');   // Number\Value — or null, for either kind of absence
$lines->column('sku');                  // every row's sku, under the row keys
$lines->hasRepeats();                   // what the `unique` constraint asks

$resolved->itemAt('first run');         // Collection\Item — that row's *verdicts*
```

Two values are deliberately **not** printable: `Password\Value` and `CreditCard\Value` have no
`__toString()`, because stringifying is how a secret reaches a log or a template. Both are asserted
in `Api\ValueObjectTest`.

**It does not repair input.** Trimming whitespace or fixing case is the port's business — see
[CODING-STYLE.md](CODING-STYLE.md). It canonicalises only where a standard says two spellings are
one thing (DNS on an email domain, ISO 4217 on a currency code), and that belongs in the field's
own `Value` object rather than here.

---

## Input shapes

**An object is a record; an array is a list.** A value with named parts arrives as an object; a
value that is many of something arrives as an array. `Definition::recordIn()` is the gate, and it
accepts objects only. The full reasoning is in [CODING-STYLE.md](CODING-STYLE.md).

```php
protected function parse(mixed $value): ?Value
{
    if ($value instanceof Value) {
        return $value;                       // already this field's own type
    }

    $parts = self::recordIn($value);         // object -> named parts, anything else -> null

    if ($parts === null) {
        return null;
    }

    return Value::fromInput($parts);
}
```

---

## Constraints

A constraint carries everything a message needs:

```php
new Constraint(
    name: 'minLength',          // reported under this, and matches the $minLength property
    check: $this->longEnough(...),
    bound: $this->minLength,    // what a message interpolates
    part: null,                 // which part of a structured value, or null for the whole
    boundFor: null,             // a per-request bound, when the limit depends on the value
    timeRelative: false,        // whether the answer depends on when it is asked
);
```

**A constraint's name is its property's name.** `minLength` the constraint is `$minLength` the
property. That is what lets a consumer read the configured limit without a lookup table, and it is
checked for every field by `Api\ConstraintNameTest`.

**A check returns `?bool`.** `true` passed, `false` failed, and **`null` means skipped** — there
was nothing to ask. A constraint nobody configured skips rather than passing, so "not asked" and
"asked and fine" stay distinct in the result.

**`part`** names which piece of a structured value a constraint is about — `line1`, `amount` —
rather than encoding it in the name. Names carry no field name and no path: it is `postalCodeFormat`
with part `postal_code`, never `venue.postal_code.format`.

**`boundFor`** is for a limit that depends on the submitted value. `Money`'s minimum is per
currency, so the bound that *applied* is only known once the currency is. It is kept separate from
`$bound` rather than letting that hold a closure: a consumer reading the definition wants a scalar,
and handing it a `Closure` half the time would be worse than handing it `null`.

**`timeRelative`** says the answer depends on the calendar rather than on the value. It exempts the
constraint from the definition-time default check — see [Defaults](#defaults).

---

## Shape versus constraint

Two different questions, and the order is fixed.

**The shape** asks whether there was a usable value at all. It is not a constraint: every
constraint narrows a value that is already the right shape, so if the shape fails there is nothing
for any of them to have an opinion about.

```php
$resolved->shape->passed();
$resolved->shape->failed();
$resolved->shape->wasMissing();      // nothing arrived and the field required something
$resolved->shape->wasUnreadable();   // something arrived that could not be read
```

Those last two are the two halves of a failure, and they need different sentences: *"this is
required"* against *"this is not a valid duration"*. Telling them apart used to mean inspecting the
submitted value at the call site.

This used to be reported as a constraint named `type`, which it never was. That conflation meant
`getFailed()` returned a mix of "that is not a date" and "that date is too early", and it reserved
the name `type` so no field could ever have a real constraint called that.

**It is still one of the results**, deliberately, rather than a property beside them. Every
aggregate predicate — `anyFailed()`, `allPassed()`, `status` — derives from that list, so keeping
it there means none of them can forget it. Pulling it out would have left eight methods to fold it
back into, and the one that got missed would have reported unreadable input as fine.
`Api\SealedFieldTest` proves that across every field, using a stream as the value no field can read.

What it *is* excluded from is the constraint-facing API: `forConstraint()` and `$constraintNames`
skip it, because it is not one.

---

## Order of operations — owned by the core

`AtomicField::resolve()` and `validate()` impose an order, and that is the whole reason the core
owns them:

1. **Settle absence.** `$given`, or the authored default when nothing was submitted. If there is
   nothing at all: shape *skipped* when the field is optional, shape *missing* when it is not, and
   every constraint skipped.
2. **Read it once.** `parse()` runs exactly once per resolution. If it returns `null`: shape
   *unreadable*, every constraint skipped.
3. **Check the constraints** against the parsed value.

**These are not `final`**, because a field may need to return a richer result —
`Field\Password` overrides both so it can hand back a `Password\Result` carrying the measured
entropy. So the order is a contract rather than a lock, and what holds an override to it is
`Api\SealedFieldTest`, which checks the observable consequences for *every* field: that unreadable
input fails the field and not merely its shape, and that the constraints are skipped rather than
failed when there was nothing for them to judge.

An override that changes the order is therefore caught, but it is worth saying plainly that the
language is not what catches it. A schema whose fields disagree about when a constraint is skipped
is worse than one with a rigid rule.

---

## Configuration

Withers, never setters. `Definition::with()` does the work:

```php
final protected function with(array $changes): static
{
    $field = clone($this, $changes);
    $field = clone($field, ['constraints' => $field->defineConstraints()]);
    $field->assertDefaultCanSatisfyIt();

    return $field;
}
```

Three things happen in that order, and each is load-bearing:

- **The copy is made** with PHP 8.5's clone-with. Invariants are checked *before* it, in the wither,
  so an invalid field cannot be constructed at all.
- **Constraints are rebuilt**, because they are derived from the properties that just changed. A
  `Constraint\Set` is stored rather than computed on read, because a `readonly` class cannot declare
  a property hook — not even a virtual, get-only one. That makes `$constraints` derived state that
  has to be *kept*, which is exactly what `with()` exists to guarantee, and what
  `Api\SealedFieldTest` verifies for every wither of every field.
- **The default is re-checked**, since a tightened constraint may have invalidated it.

**`readonly` is all-or-nothing.** A readonly class cannot extend a non-readonly one, so `AtomicField`
being readonly seals every field below it whether or not its author thought about it. Note that
readonly is *shallow*: it stops a property being reassigned, not the object it holds being mutated,
so anything stored on a field must be immutable itself.

---

## Defaults

`defaultsTo()` stores the value **exactly as the author wrote it**, and it is read through `parse()`
when it stands in for a submission. So a field holds the record and the result holds the value
object.

**An authored default is checked where it is written.** A default that cannot satisfy its own field
is a bug in the schema, and blaming somebody's request for it would be the wrong place to find out.

**Time-relative constraints are exempt**, and this is the carve-out that rule forces. "Has this card
expired" is true or false depending on the calendar: a default valid the day the schema was built
would start throwing years later, inside a constructor, with nothing edited and no request to
blame. Those are judged per request like any submitted value.

**A default is not a prefill.** The default is a constant the author typed; it lives on the
definition and serialises with it. A value looked up for one user arrives with the request:

```php
$schema->validate($submitted, prefilledWith: $known, policy: PrefillPolicy::Checked);
```

Precedence is submitted, then prefilled, then the authored default. That separation is what makes
*"a serialised schema can never contain user data"* true by construction rather than by discipline —
it replaced a `prefill()` method that wrote onto the fields, which was defect B9.

`PrefillPolicy::Trusted` waives the *constraints* for a value that actually survived as prefilled.
The **shape is still checked**: trust says a value meets the rules, not that the field can read it,
and calling an unparseable value acceptable would mean reporting success and a null value together.
Trust attaches to the value, so it cannot excuse anything the user typed over the top.

---

## What a resolved field carries

```php
$resolved->field;          // the effective definition, including any change a rule made
$resolved->given;          // exactly what was submitted, unchanged
$resolved->value;          // what was actually validated
$resolved->source;         // Submitted | Prefilled | Default | None
$resolved->evaluatedAt;    // the instant judged against, or null with no clock
$resolved->shape;          // the shape verdict
$resolved->constraintNames;
$resolved->forConstraint('minLength');
$resolved->appliedOutcomes;
```

**`given` and `value` are both kept, and neither throws.** Re-rendering a rejected form must echo
back what somebody typed rather than anything coerced; the application wants the parsed value. A
single field cannot serve both.

**Nothing is written back to the field.** Resolving the same field concurrently with different
values cannot interfere, which is what makes one schema safe across many requests.

---

## A field that is not one value

`Field\Collection` implements `Field` directly and uses `Definition`, because `AtomicField`'s
lifecycle does not fit: it resolves a *list* and checks each item against a template.

Its result is a `Collection\Result` rather than a `ResolvedField`, carrying the collection's own
verdicts plus one `Collection\Item` per row. Rows keep the key they arrived under — a position for
a plain list, a name for `['first' => …, 'second' => …]` — so a failure is reported against
something a person recognises.

A field whose result has a different *shape* implements `FieldResult`, as that does. A field with
something extra to *report* subclasses `ResolvedField` instead — `Field\Password\Result` adds the
measured entropy of the secret. The seam is deliberately narrow: a subclass adds readings, never
verdicts, which still come from constraints.

---

## Not here

- **Serialisation.** The core does not serialise; `meraki/schema-json` does. See
  [CODING-STYLE.md](CODING-STYLE.md).
- **Validation groups / partial validation.** Discussed as `ValidationScope` in an earlier draft of
  this document and never built. It is a roadmap item, not part of the contract — see
  [ROADMAP.md](ROADMAP.md).
- **Method and constraint naming decisions.** [API-REVIEW.md](API-REVIEW.md) is where those are
  argued and settled; this document describes the contract they are expressed in.
