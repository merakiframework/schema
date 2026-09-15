# Coding style

Conventions that are decisions rather than formatting. Formatting is whatever the editor and
PHPStan already enforce; this is the part a tool cannot check.

---

## Properties read state; methods ask questions

**If something reads state, it is a property.** Not a method. `$field->minLength`, not
`$field->getMinLength()`. That holds whether the value is stored or computed on read —
`Constraint\Set::$names` derives its value every time and is still a property, because what it
answers is "what are your names", not "do something and tell me".

**If something asks a question, it is a method**, and its name should read as one. A *query
method* either takes an argument, or computes an answer that is not simply the object's state:

```php
$value->isEmpty()                 // a predicate
$results->getFailed()             // returns a new result holding only the failures
$resolved->forConstraint('minLength') // a lookup
$card->determineToday()           // goes and asks a clock
```

`getFailed()` keeps its prefix because it does not expose a property — it builds a new result
containing only the failures — and because `$results->getFailed()` reads correctly at the call
site. That is the bar for `get`: nothing else reads as well. Where something else does, use it,
and prefer a more specific name over a shorter one.

A bare `get()` never clears that bar, because it says only "fetch" and leaves the reader to work
out what. Those are gone:

| Was | Is | Returns |
| --- | --- | --- |
| `$resolved->get('minLength')` | `forConstraint()` | the verdict for one constraint |
| `$schemaResult->get('email')` | `forField()` | the result for one field |
| `$item->get('sku')` | `forField()` | the result for one field of a collection item |
| `$field->constraints->get('minLength')` | `named()` | the constraint *definition* |

**A lookup is named for what it looks up, not for what comes back.** One name for both — a
`resultFor()` on each — was tried and rejected: it read the same at every call site while meaning
different things, and `Collection\Result` and `Collection\Item` are the proof, because one takes a
constraint name and the other a field name while being held together. It also chains legibly:

```php
$schemaResult->forField('venue')->forConstraint('postalCodeFormat')->bound
```

`named()` is the exception, and deliberately so: it hands back a *definition* rather than a verdict
and reads as what it returns — "the constraint named minLength".

### When the type system forbids a property

Three cases, all structural:

| Case | Why | What to do |
| --- | --- | --- |
| A `readonly` class | PHP forbids property hooks in one — **virtual, get-only ones included** — so a derived value cannot be *computed* on read | Store it, or name the method as a question. See below |
| An `enum` | Cannot hold properties at all | Name the method as a question |
| A non-idempotent answer | Two reads can differ, which a property implies they cannot | Name the method so it says it goes and finds out |

The readonly case has a second option, taken by `Field::$constraints`: **store the derived value
and rebuild it whenever anything it derives from changes.** That keeps the property, at the price
of turning a computed value into state that has to be maintained. It is only worth it where there
is a single funnel for change — `Field\Definition::with()` is that funnel, so every wither rebuilds
the set and no field can forget.

Do not reach for it by default. The stored value is a second source of truth, so:

- The constructor must assign it **last**, after every property it reads.
- Static analysis cannot see either of those rules, so `Api\SealedFieldTest` checks them for every
  field at runtime — that a constructor assigned it, that a wither rebuilt it, and that the stored
  set still matches a freshly built one.
- It costs serialisability where the value holds closures. `serialize()` refuses a `Closure`, which
  is why the long-lived-process tests fingerprint a schema with `print_r($schema, true)` instead.

Where none of that is justified, name the method as a question — which is what every
`Field\*\Value` object still does.

So every `Field\*\Value` object — all of them readonly — uses methods for derived values, and
the names carry the question:

```php
$address->partNamed('administrative_area')   // not part()
$card->lastFourDigits()                      // not lastFour()
Strength::Strong->asBits()                   // not bits()
$card->determineToday()                      // not today()
```

The bare-noun forms of those would read as properties that happen to need parentheses, which is
exactly the confusion this avoids. `__toString()` is exempt: it is a magic method and its name is
not ours to choose.

This is as uniform as PHP's current type system allows. A value object that could use hooks would
express all four of those as properties.

---

## Names do not carry their context

A constraint is named for what it checks, never for the field it came from. `postalCodeFormat`,
not `billing.postal_code.format` — so renaming `billing` to `invoice_address` changes no constraint
name and breaks no message provider. Where a constraint concerns one part of a structured value, it
says which part separately, in `Constraint::$part`.

---

## Withers, not setters

A field is sealed. Configuration hands back a modified copy:

```php
$field->minLengthOf(3)    // returns a new field
```

Every wither routes through `Field\Definition::with()` rather than cloning directly, because that
is where the authored default is re-checked. A wither that clones directly silently skips the check
and can leave a stale default behind.

Name them for what the author is saying, not for the property being set: `minLengthOf(3)`,
`allowOnlyPhysical()`, `allowCountries('AU')`, `allowDuplicates()`.

---

## The core does not serialise

A wire format is a port's job. `meraki/schema-json` turns a schema into JSON and back;
`meraki/schema-html` turns one into markup. The core holds the PHP object model and nothing else —
no `serialize()`, no `__serialize()`/`__sleep()`, no `JsonSerializable`, and no hand-rolled string
format either. A `role:admin;level:5` parser is a wire format wearing different clothes.

Two consequences worth stating, because both have caught us out:

- **The core makes no serialisability promise.** Fields hold closures, so PHP's `serialize()`
  refuses them outright. That is not a defect to fix; it is the absence of a promise that was never
  made. Tests wanting to prove a definition did not change, or that it retained no user data, use
  `print_r($schema, true)` — which renders closures, marks cycles as `*RECURSION*`, prints no
  object ids to destabilise it, and shows a value captured by a closure, which `serialize()` could
  not have reached. Reach for a hand-rolled graph walker only if `print_r` stops being enough.
- **"Serialised" is the wrong word for a core docblock.** Saying a separation "keeps a serialised
  schema free of user data" quietly asserts that the core serialises. It keeps a *definition* free
  of user data; what a port then does with it is the port's business.
---

## An object is a record; an array is a list

The input model, in one line. A value with **named parts** — an amount and its currency, the lines
of an address, a card's number and expiry, and a schema's whole payload — arrives as an **object**.
A value that is **many of something** — a collection's items — arrives as an **array**. Neither
shape is accepted where the other belongs.

```php
$schema->validate((object) [
    'deposit' => (object) ['currency' => 'AUD', 'amount' => '250.00'],   // a record
    'attendees' => [                                                    // a list
        (object) ['name' => 'Bilal Haddad'],
        (object) ['name' => 'Chen Wei'],
    ],
]);
```

**Why it has to be a rule rather than a guess.** PHP has one type for both: `['amount' => …]` and
`[$row1, $row2]` are indistinguishable without inspecting the keys, and inspecting the keys is
exactly the guessing this library does not do. Putting the distinction in the *shape* means a
field never has to ask what its caller probably meant.

It buys something concrete: a collection can name its rows.

```php
$schema->validate((object) [
    'line_items' => [
        'first' => (object) ['sku' => 'A1'],
        'second' => (object) ['sku' => 'B2'],
    ],
]);
```

That was impossible while a string key might have meant "a record's field name". Now it can only
mean one thing, and the name follows through to `Collection\Item::$key` — so a failure is reported
against `second` rather than `row 2`.

**Prefer named rows.** A position is an accident of ordering: insert a row and every key after it
changes, and "row 3 is wrong" tells whoever reads it very little. A name is stable and says
something. Positions remain the default because a plain list is what most input is, not because
they are better.

**Keys must all be of one kind** — all names, or all positions. A half-named list is refused rather
than repaired, because PHP numbers whatever was not named: the unnamed rows are keyed *around* the
named ones, so which row `0` refers to depends on how many names came before it. A failure reported
against a key that moves is worse than refusing the input, and forgetting one name in a list of
twenty is exactly the mistake this catches.

**Converting is the port's job.** `$_POST` and `$_FILES` are associative arrays throughout, and
`json_decode($body, true)` asks for arrays — `json_decode($body)` already gives objects. Whichever
a port starts from, it hands the core objects. This is the same division as everywhere else here:
the core takes PHP types and states what it needs; the medium's shapes are the port's problem.

## Strict by default, and never guess

Every field is required until told otherwise, and no field infers what the submitter meant. Where
input is ambiguous, the author is given a way to say what should happen rather than the library
choosing:

- `PrecisionPolicy::Truncate` / `Reject` — what to do with precision the field did not ask for
- `Collection::allowDuplicates()` — whether the same item twice was meant
- `PrefillPolicy::Checked` / `Trusted` — how much a per-request value is trusted

A default that guesses is a default that is wrong somewhere, silently.
