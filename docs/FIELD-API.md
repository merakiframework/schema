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
    public array $parts { get; }            // list<Field\Part>: empty for a value that is one thing
    public array $essentialParts { get; }   // list<Field\Part>: the parts no value can be without
    public array $checks { get; }           // list<Field\Check>: every code it can report under

    public function defaultsTo(mixed $value): static;
    public function makeOptional(): static;
    public function makeRequired(): static;
    public function equals(self $other): bool;
    public function reconfiguredWith(array $changes): static;       // a rule outcome's changes, put back
    public function when(): Rule\Matcher;                           // narrowed to the questions its value earns

    public function resolve(mixed $given, array $appliedOutcomes = [], ValueSource $givenAs = ValueSource::Submitted): AggregatedValidationResult;
    public function validate(mixed $given, array $appliedOutcomes = [], ValueSource $givenAs = ValueSource::Submitted, PrefillPolicy $policy = PrefillPolicy::Checked): AggregatedValidationResult;
    public function resultIn(SchemaValidationResult|Field\Collection\Item $results): FieldResult;
    public function resolvedValueFor(mixed $given): ?Field\ParsedValue;
    public function resolvedInputFor(mixed $given): ?Field\Input;   // the parts as read
    public function treatsAsAbsent(mixed $given): bool;             // null, or a record with nothing in it
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

Five things: the field below, and an enum naming what it checks. Here is the whole of
`Field\Boolean`, which is the smallest real field:

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

    public function when(): Matcher\Basic                  // 3. what a rule may ask it
    {
        return new Matcher\Basic(ValueScope::of($this->name));
    }

    protected function parse(mixed $value): Value          // 4. the one conversion hook
    {
        if (!is_bool($value)) {
            throw MalformedValue::of(Value::class, 'yes or no is submitted as a boolean');
        }

        return new Value($value);
    }

    protected function defineConstraints(): Constraint\Set // 5. what it checks
    {
        return new Constraint\Set(
            new Constraint(Boolean\Check::Accepted, $this->wasAccepted(...), $this->requiresAcceptance),
        );
    }

    protected static function declaredChecks(): array      //    ...and every code it reports
    {
        return Boolean\Check::cases();
    }

    private function wasAccepted(Value $parsed): ?bool
    {
        return $this->requiresAcceptance ? $parsed->answer === true : null;
    }
}
```

The codes are a string-backed enum implementing `Field\Check`. Each case's value is the name a
failure is reported under and a language pack writes a message under; `part()` says which part
of a structured value the check concerns, or `null`:

```php
enum Check: string implements Field\Check
{
    case Accepted = 'accepted';

    public function part(): null
    {
        return null;
    }
}
```

**`when()` is the one member nothing can derive for you.** Yes or no has no order and no text,
so a boolean offers `equals`, `isIn` and the presence pair and withholds the rest — which means
`$terms->when()->isAtLeast(1)` does not compile. Pick by what your *value* answers:
`Comparable` earns the ordered questions, `Stringable` earns the text ones, and
[EXTENDING.md](EXTENDING.md) has the table.

**The constructor order matters and is not optional.** `parent::__construct()` first, then the
field's own properties, then `$this->constraints = $this->defineConstraints()` last — because
constraints are built *from* those properties. A field that gets this wrong fails
`Api\SealedFieldTest`, which checks it for every field.

---

## `parse()` — the one hook

```php
abstract protected function parse(mixed $value): ParsedValue|Input;
```

It replaced `process()`, `validateValue()` and `transform()`, which between them parsed most values
twice and had to agree with each other to be correct. Four things hold, and everything downstream
depends on them:

- **It never receives `null`.** Absence is settled before it runs — no input and no default means
  there is nothing to read, so the field is skipped or reported missing without this being called.
- **It returns a value, the `Field\Input` a value is assembled from, or raises
  `Field\MalformedValue`.** There is no `null`, and no try/catch for you to write: who absorbs the
  refusal is the lifecycle's decision, not the field's. A value made of parts returns its input —
  see [A value made of parts](#a-value-made-of-parts).
- **A field still never raises on a request.** `Definition::readable()` catches and reports an
  unreadable shape. What raising bought is the *definition-time* path, where `defaultsTo()` lets
  the reason through to the author — the one message that could not say why, while the
  constraint branch beside it named the constraint that failed.
- **Most of the work is the value's.** Its constructor enforces the invariant and canonicalises,
  so `parse()` narrows `mixed` and hands over. `Enum` and `Collection` keep more, because
  membership of a case list and a row template are facts about the *field*.
- **What it returns is what the constraints see** — the value, or the one its input assembles
  to. So a constraint is typed `Number\Value` and has that be true by construction rather than by
  hoping a gate ran first.
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

The vocabulary lives in `Meraki\Schema\Comparison\`, not in `Field\`, because "are these the same
value" is not a question about fields — [`Collection`](../src/Field/Collection.php)'s `unique`
constraint asks it with no rule in sight:

| | |
| --- | --- |
| [`Comparison\Equality`](../src/Comparison/Equality.php) | `equals()` — the capability |
| [`Comparison\Comparable`](../src/Comparison/Comparable.php) | `compareTo(): Order` — adds an order, extends `Equality` |
| [`Comparison\Order`](../src/Comparison/Order.php) | `Less \| Equal \| Greater`, with `isAtLeast()` and friends |
| [`Field\ParsedValue`](../src/Field/ParsedValue.php) | the **role** — what `parse()` returns. Extends `Equality` and declares nothing of its own |

`ParsedValue` stays under `Field\` because it is the field contract, the literal return type of the
one hook. The three capabilities sit where a caller can reach them without knowing this library has
fields at all.

**Fields do not implement any of them.** A field is a definition, and `Field::equals()` already
means something else — "the same field, by name". A rule comparing two fields compares the values
they resolved to, never the definitions.

Six values are ordered — `Number`, `Date`, `DateTime`, `Time`, `Duration` and `Money` — and the
rest are equatable only: two addresses can be the same address, and neither is before the other.
Money is ordered *within* a currency and raises across one, because ranking AUD against USD needs
an exchange rate, which is a fact about a moment in the market rather than about either amount.

```php
$a->compareTo($b)->isAtLeast();   // rather than  $a->compareTo($b) >= 0
```

That is the whole of what the comparison matchers need: each is `compareTo()` and a question put to
the `Order`, so they are written once against the interface rather than once per field type.

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

$lines->keys();                         // ['first_run', 'second_run']
$lines->rowAt('first_run')->sku;        // Text\Value  — one row's field
$lines->valueOf('second_run', 'qty');   // Number\Value — or null, for either kind of absence
$lines->column('sku');                  // every row's sku, under the row names
$lines->hasRepeats();                   // what the `unique` constraint asks

$resolved->itemAt('first_run');         // Collection\Item — that row's *verdicts*
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
protected function parse(mixed $value): Widget\Input
{
    if ($value instanceof Widget\Value) {
        return Widget\Input::of($value);     // already a whole widget
    }

    // An object is a record; an array is a list. A value with named parts arrives as the
    // former — see Definition::recordIn().
    $record = self::recordIn($value);

    if ($record === null) {
        throw MalformedValue::of(Widget\Value::class, 'a widget is submitted as a record of its parts');
    }

    return Widget\Input::read($record);      // the input reads the record it is handed
}
```

The input takes the whole record rather than parts picked out for it, so there is one answer to
"what is a widget here" instead of a field that reads input and a value that trusts whatever it is
handed. `parse()` returns or raises; returning `null` for unreadable input is not a thing it does.

**A record with nothing in it never arrives.** `{}`, or every part `null`, is what a form sends when
nobody touched it, so the lifecycle reads it as nothing submitted — as it reads `null` — before
`parse()` is asked: the field is missing or skipped, and a prefill or the default stands in. The
rule is the core's, read off the field's declared parts by `treatsAsAbsent()`, so a field has
nothing to write for it. A key the value does not declare is never nothing, so a record holding
one still reaches the input, which refuses it.

---

## A value made of parts

A record can arrive half-filled, and half a value is not a value. So a field whose value has parts
reads them into a **`Field\Input`** first, and the lifecycle assembles the value from it:

```
record ──► input ──────────► assembly ────────► value ─────────► constraints
           each part as       is this a value     complete, with   does this field
           read, nothing      at all? every       nothing null     accept it?
           judged yet         problem at once
```

An input is the parts as read and a verdict on them:

```php
interface Input
{
    public array $violations { get; }      // list<Violation>: what stops the parts making a value
    public array $missingParts { get; }    // list<Part>: the essential parts not supplied
    public ?ParsedValue $value { get; }    // the value, exactly when $violations is empty

    public function parts(): array;        // each part as read, keyed 'postal_code'; null when not read
    public function canonicalPartValue(Part $part, mixed $expected): mixed;
}
```

**What goes in it is decided by one rule:** if no configuration can change the verdict, and it
needs no clock, it is assembly; otherwise it is a constraint. An amount with no currency is not
money on any field there will ever be, so `currencyRequired` is assembly. Whether *this* field
takes AUD is configuration, so `allowedCurrencies` is a constraint. The reasoning, and the
classification of every shipped field, is in [DESIGN.md](DESIGN.md#a-value-is-assembled-before-it-is-judged).

So an input never reads the field's configuration and never asks the time. That is what lets the
same input be judged the same way for a default where the schema is written, for a trusted
prefill, and on every request.

**Work everything out where the input is built**, the value included, and narrow `$value` to your
own value class — `public ?Widget\Value $value`. Building the value there, from locals PHP has
already narrowed, means its essential parts never need to be nullable, and it is how
`Field\ValueClass` learns what the field holds without a request.

[`tests/Field/Fixture/Span`](../tests/Field/Fixture/Span) is a complete example: two essential
parts, an optional one, a check the parts must agree on, and one constraint.

**What the core does with it:**

| The input says | The result |
| --- | --- |
| *(never asked: the record has nothing in it)* | nothing was submitted — missing, or skipped when optional — and a prefill or the default stands in |
| nothing is wrong | its value goes to the constraints |
| something is | shape *incomplete*: `wasIncomplete()`, `$missingParts`, each part's own violations, and each constraint that cannot be judged yet skipped — in `2.0`, every one |
| nothing is wrong, and it made no value | `Exception\InconsistentInput` is raised: the input has a bug |

**There is no message about the whole value when its parts have their own.** An incomplete result
carries the parts' violations and nothing else, so a form marks the boxes that need fixing rather
than showing "that is not a valid widget" above them.

**A rule about one part reads the input.** "When the billing country is AU" holds on an address
whose street is still empty. A rule about the whole value reads the assembled value, which is
nothing until the parts make one — so `isEmpty()` is true of a half-filled record, as it is of an
unreadable one, and a rule comparing the whole value against half of one is refused where it is
written. `$field->resolvedInputFor($given)` is the same reading, for a port that wants it.

`canonicalPartValue()` belongs to the input for the same reason. A rule compares against a part
in the spelling the input stored it in, so it has to ask the thing that did the storing.

---

## Constraints

A constraint carries everything a message needs:

```php
new Constraint(
    code: Text\Check::MinLength,  // reported under 'minLength', matching the $minLength property
    check: $this->longEnough(...),
    bound: $this->minLength,      // what a message interpolates
    boundFor: null,               // a per-request bound, when the limit depends on the value
    timeRelative: false,          // whether the answer depends on when it is asked
);
```

The part a constraint concerns is not an argument: it is the code's own `part()`, so a check
cannot be declared about one part here and reported against another there.

**A constraint's name says where to read its bound**, and there are three cases:

- **Configuration you set** is a property of the same name. `minLength` the constraint is
  `$minLength` the property, and `allowedCountries` is both. A consumer reads the configured
  limit without a lookup table.
- **A bound derived from reference data** is not duplicated as a property, because it depends on
  the submitted value and would be unanswerable most of the time. `Address` reads its from
  `requirementsFor($country)`: which parts a country requires, its postcode pattern, its
  subdivisions. A fact with two accessors is a fact that can disagree with itself.
- **A constraint with nothing to configure** has no bound and no property. A card number passes
  Luhn or it does not, so `numberChecksum` has nothing to interpolate.

`Api\ConstraintNameTest` checks the names every field reports.

**A check returns `?bool`.** `true` passed, `false` failed, and **`null` means skipped** — there
was nothing to ask. A constraint nobody configured skips rather than passing, so "not asked" and
"asked and fine" stay distinct in the result.

**The part** names which piece of a structured value a constraint is about — `Address\Part::Street`,
`Money\Part::Amount` — rather than encoding it in the name. Names carry no field name and no path:
it is `postalCodeFormat` about `postal_code`, never `venue.postal_code.format`. It is read from the
code, so `$constraint->part` and every verdict's `->part` agree by construction.

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
$resolved->shape->wasIncomplete();   // a record's parts arrived and make no value
$resolved->shape->missingParts;      // the essential parts that were not supplied
```

Those are the three ways a shape fails, and they need different sentences: *"this is required"*,
*"this is not a valid duration"*, and one sentence for each part that is wrong. Telling them apart
used to mean inspecting the submitted value at the call site.

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
2. **Read it once.** `parse()` runs exactly once per resolution. If it raises `MalformedValue`: shape
   *unreadable*, every constraint skipped.
3. **Assemble**, when `parse()` returned an input. If its parts make no value: shape *incomplete*,
   and a constraint that cannot be judged yet is skipped — in `2.0`, every one; from `2.1`, only
   those whose own parts are not sound (see
   [ROADMAP.md](ROADMAP.md#constraints-that-run-when-their-parts-are-ready)). Not even trust skips
   this step.
4. **Waive the constraints for a trusted prefill**, which passes as it is.
5. **Check the constraints** against the value.

**These are not `final`**, because a field may need to return a richer result —
`Field\Password` overrides both so it can hand back a `Password\Result` carrying the measured
entropy. It reads through `AtomicField::read()`, which is `final`, so the reading and the order
are still the lifecycle's. So the order is a contract rather than a lock, and what holds an override to it is
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
That includes a record whose parts make no value: `InvalidDefault` names each problem and the part
it is about, in the codes a request would have been told.

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

Its result is a `Collection\Result`, carrying the collection's own verdicts plus one
`Collection\Item` per row. Rows keep the key they arrived under, and that key must be a **name**:
`['first' => …, 'second' => …]` is accepted and a plain list is refused, because a stored rule
naming row `0` would mean a different row on a different request.

Both kinds of richer result subclass `ResolvedField`, which itself implements `FieldResult`:
`Collection\Result` adds a result per item, and `Field\Password\Result` adds the measured entropy
of the secret. `FieldResult` is the interface `SchemaValidationResult::forField()` looks a field up
by, so it never has to know which shape it got. The seam is deliberately narrow either way: a
subclass adds readings, never verdicts, which still come from constraints.

---

## Not here

- **Serialisation.** The core does not serialise; `meraki/schema-json` does. See
  [CODING-STYLE.md](CODING-STYLE.md).
- **Validation groups / partial validation.** Discussed as `ValidationScope` in an earlier draft of
  this document and never built. It is a roadmap item, not part of the contract — see
  [ROADMAP.md](ROADMAP.md).
- **Method and constraint naming decisions.** [API.md](API.md) is where those are
  argued and settled; this document describes the contract they are expressed in.
