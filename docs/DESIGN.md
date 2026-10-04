# Design decisions

The choices that shape everything else, and what each one costs. Most of them are refusals.

If you only read one thing here, read the first two: they explain more of the API than the rest
put together.

---

## A schema is a definition, and nothing per-request touches it

A schema is built once and read by every request that follows. So a field holds *what it is* and
never *what somebody sent*:

```php
$field->validate($value)        // a question. Nothing is written back
$field->minLengthOf(3)          // a copy. The original is unchanged
```

Every field is `readonly`, and `readonly` is inherited both ways — a non-readonly class cannot
extend a readonly one — so every field is sealed whether or not its author thought about it. The
schema's field and rule sets are `private(set)` for the same reason, and their in-place mutators
are private.

**What it costs.** Configuration must be kept: `$field->minLengthOf(3);` on a line of its own
configures nothing. That is the single most common mistake when writing against this library, and
it is the price of a schema you can share across concurrent requests without thinking about it.

**Why it is worth it.** The alternative was building a schema per request, which works and costs
about a quarter of a millisecond — but it is a discipline rather than a guarantee, and a
long-lived worker is exactly where forgetting it is invisible.

---

## One conversion step, and constraints get a real type

`parse()` is the only place submitted input becomes a value. Three things hold, and everything
downstream depends on them:

- **It never receives `null`.** Absence is settled first, so `null` in the *return* means one
  thing only: unreadable.
- **It raises `MalformedValue`, and a *request* never sees it.** The lifecycle catches on the
  request path and reports an unreadable shape; it lets the exception through at definition
  time, so a bad `defaultsTo()` tells its author why. Which of those happens is not the field's
  decision — one field differing from its neighbours would make results inconsistent.
- **What it returns is what the constraints see** — so a check is typed `Number\Value` and has
  that be true by construction rather than by hoping a gate ran first.

It replaced `process()`, `validateValue()` and `transform()`, which between them parsed most
values twice and had to agree with each other to be correct.

**What it costs.** A field author has one hook and cannot reach in between the steps.

---

## Shape and constraint are different questions

*Could this be read at all?* is asked before *does it satisfy the rules?*, and when the first
fails the second is **skipped** rather than failed.

```
'not a date'  ->  shape: Failed (unreadable), from/until/interval: Skipped
```

So an error report names one problem once, instead of once per constraint. And *missing* is kept
apart from *unreadable*, because "this is required" and "this is not a valid duration" are
different sentences and a consumer that cannot tell them apart has to go back to the input and
guess.

---

## A value is assembled before it is judged

A value with named parts — an amount and its currency, a number and its country, an address — is
read in four steps, and only the last one involves the field's configuration:

```
record ──► input ──────────► assembly ────────► value ─────────► constraints
           each part as       is this a value     complete, with   does this field
           read, nothing      at all? every       nothing null     accept it?
           judged yet         problem at once
```

- **The input** is the record read part by part. A part is canonicalised where a standard says
  two spellings are one thing (`Australia` → `AU`, `QLD` → `AU-QLD`), and is `null` where it was
  absent or could not be read. It exists for any record that arrives, half-filled or not.
- **Assembly** decides whether those parts make a value: whether every *essential* part is there,
  whether each part can be read, and whether the parts agree with each other — a phone number is
  only a number *in a country*. Every problem is reported at once, against the part it is about.
- **The value** exists only when assembly found nothing. Its essential parts are not nullable —
  `Money\Value::$amount` is a `BigDecimal`, never a `?BigDecimal` — so PHP enforces them, rather
  than a guard at the top of every check.
- **Constraints** judge the value and nothing else. None of them ever sees half of one.

This replaced values that could be half-filled. Since a missing country stopped being a shape
failure, `PhoneNumber\Value` held a nullable number *and* a nullable country, `Money\Value` could
be compared while holding no amount, and every check began by asking which half was there.
That was a value that was not a value, and its constraints were paying for it.

### Which checks are assembly, and which are constraints

> **If no configuration can change the verdict, and it needs no clock, it is assembly.
> Otherwise it is a constraint.**

| Field | Assembly: is it a value? | Constraint: does this field accept it? |
| --- | --- | --- |
| `Money` | `currencyRequired`, `amountRequired`, `currencyFormat`, `amountFormat` | `knownCurrency`, `allowedCurrencies`, `minAmount`, `maxAmount`, `scale` |
| `PhoneNumber` | `countryRequired`, `numberRequired`, `knownCountry`, `numberFormat`, `numberInCountry` | `allowedCountries`, `numberType` |
| `CreditCard` | `numberRequired`, `expiryRequired`, `numberFormat`, `numberChecksum`, `expiryFormat`, `nameFormat`, `securityCodeFormat` | `expiryInFuture`, `expiryWithinReach`, `nameRequired`, `securityCodeRequired` |
| `Address` | `countryRequired`, `knownCountry`, `streetFormat`, `streetLineLimit`, `dependentLocalityFormat`, `dependentLocalityUsed`, `localityFormat`, `localityUsed`, `knownSubdivision`, `subdivisionUsed`, `postalCodeFormat`, `postalCodeUsed` | `allowedCountries`, `streetVisitable`, `streetRequired`, `localityRequired`, `subdivisionRequired`, `postalCodeRequired` |
| `File` | `nameRequired`, `typeRequired`, `sizeRequired`, `nameFormat`, `typeFormat`, `sizeFormat` | `minSize`, `maxSize`, `allowedTypes`, `disallowedTypes` |

The edge cases are where the rule earns its keep. ISO 4217 membership is a *constraint*
(`knownCurrency`), because explicitly allowing a code with a scale — `['BTC' => 8]` — changes the
verdict. A card's `expiryWithinReach` cannot be configured, but it asks what day it is, so it is a
constraint too: a default checked when the schema is written must not start failing years later.

The rule has a corollary, and it is the reason the line sits here: **rules change what a field
accepts, never what counts as a value.** An outcome is configuration — `then($field->...)` — and
assembly reads none. So assembly is a pure function of the input and the reference data, and it
behaves identically for a default checked where the schema is written, for a trusted prefill, and
on every request.

### Essential parts and demanded parts

Two different facts were both spelled "required", and only one of them is configuration:

- **Essential** — no value of this kind exists without it, whatever any form wants: a phone
  number's `number` and `country`, money's `currency` and `amount`, an address's `country`, a
  card's `number` and `expiry`. The value's `Part` enum declares it, and a port reads it as
  `$field->essentialParts`. A missing one is an assembly problem, reported as `countryRequired`
  against the `country` part, and the result is *incomplete*.
- **Demanded** — this field asks for it: an address's street, locality, subdivision and
  postcode, as far down as `minPrecisionOf()` reaches and as far as the country's own format
  requires. A missing one is a constraint failure, and a rule can change it.

A card's name is neither. It is optional, like its security code.

Moving a part from essential to demanded turns `public string $name` into `public ?string $name`
on the value class, so it is a breaking change. That is intended: what a value cannot be without
is part of its type.

### Every failure is a violation

Whichever step found it, a failure is a `Field\Violation`: a code, the part it concerns, the bound,
and — once a language pack has had its say — the sentence. The code is a backed enum case the
field declares, and its value is the key a pack writes a message under:

```php
$billingResult = $billing->resultIn($schema->validate($data));

$billingResult->wasIncomplete();                              // true: its parts make no address
$billingResult->missingParts;                                 // [Address\Part::Country]
$billingResult->forPart(Address\Part::Country)[0]->code;      // Address\Check::CountryRequired
$billingResult->forPart(Address\Part::Country)->first()?->message;  // "Choose a country."
```

Which step a violation came from is a fact about the *result* — `wasIncomplete()` — and not part
of its code. So a check can move between assembly and the constraints without a language pack
noticing, and a form marks the box the same way either way.

There is no message about the whole field when its parts have their own. "That is not a valid
address" was the sentence a form showed when the problem was the postcode.

### Why rules still run before the constraints

An outcome changes configuration, and constraints read configuration, so the constraints wait for
the rules. Conditions wait for nothing: the input and the assembled value are both independent of
configuration, so both are ready before the first rule runs.

A condition on a **part** reads the input, so it works on the half-filled form it is most often
written for — "when the billing country is AU, require an ABN" while the street is still empty. A
condition on the **whole value** reads the assembled one, and an incomplete value answers as though
nothing was submitted.

Conditioning on a value the field has *accepted* would be circular, because a rule can change
what is accepted. That is why it is on the roadmap rather than here.

### What it costs

- **More types per structured field**: a `Part` enum, a `Check` enum, the input, and the value.
- **Errors can arrive in two rounds.** Constraints wait for a whole value, so on an address a bad
  postcode (assembly) holds back "enter the street" (a demanded part, so a constraint) until the
  form is submitted again. Letting a constraint run as soon as the parts it reads are sound
  removes the second round without changing what a result looks like, and is decided for `2.1`
  — see [ROADMAP.md](ROADMAP.md#constraints-that-run-when-their-parts-are-ready). `2.0` promises
  only that a constraint that cannot be judged yet is skipped, so that is not a break.
- **A trusted prefill must be complete.** Trust waives constraints, never assembly.

---

## Every value is an object this library defines

`parse()` returns a `Field\ParsedValue` — never a bare scalar, never a class from a dependency.

Everything downstream eventually asks whether two values are the same. Without this the answer
came from PHP's `==`, which compares two objects property by property, and so reads the *private
layout* of whatever class a field happened to return:

| Returned | `==` says | Truth |
| --- | --- | --- |
| `BigDecimal` `12.50` / `12.5` | different | one number — it keeps the scale it was given |
| `LocalDate`, `Duration` | correct | correct **by accident**; nothing promises those layouts |
| libphonenumber's number | different | one number — it carries the raw input alongside |

The middle row is the reason this is absolute rather than selective. Those work today because of
how Brick happens to store them; a memoised string added in a patch release would silently make
equal dates unequal, with no test obviously breaking.

**Scalars are wrapped too**, and that is the part people push back on. `===` on a string is
already right, so `Text\Value` buys nothing by itself — what it buys is that nothing downstream
ever branches on whether a value happens to be an object. The exemption would leak into every
consumer that compares, renders or serialises one. The plain scalar is one property away:
`$resolved->value->text`.

---

## The core repairs nothing

No trimming, no case-fixing, no coercion:

```php
$age = $schema->createNumberField('age');

$age->validate('42')->wasUnreadable();       // false — a numeric string is a number
$age->validate(' 42 ')->wasUnreadable();     // true  — the spaces were not removed
$age->validate('')->wasUnreadable();         // true  — an empty string is not a number

$agree = $schema->createBooleanField('agree');

$agree->validate(true)->wasUnreadable();     // false
$agree->validate('on')->wasUnreadable();     // true  — that is a checkbox, not a boolean
$agree->validate('true')->wasUnreadable();   // true  — that is JSON's spelling of one
```

Whatever was submitted is taken as intentional, because *repair is a question about the medium*.
An HTML checkbox submits `"on"`; a JSON client sends `true`; a CSV import sends `"TRUE"`. A field
that accepted all three would be encoding three media's conventions into the domain, and the day
a fourth turns up it is the field's problem. Converting is the port's job:

```php
// in meraki/schema-html, not here
$schema->validate((object) [
    'agree' => isset($_POST['agree']),
    'age'   => trim($_POST['age']),
]);
```

The one exception is **canonicalisation**, where a standard says two spellings are one thing —
and it lives in the value object rather than in `parse()`:

```php
$email = $schema->createEmailAddressField('email');

// DNS says a host is case-insensitive, so the domain is lower-cased...
$email->validate('Kim@Example.TEST')->value->domain;      // 'example.test'

// ...and RFC 5321 says the local part is the receiving server's business, so it is not.
$email->validate('Kim@Example.TEST')->value->localPart;   // 'Kim'
```

The difference is that a standard settled it, not that one medium happens to write it that way.

---

## An object is a record; an array is a list

A value with named parts arrives as an **object**. A value with many of something arrives as an
**array**. Neither is accepted where the other belongs.

```php
$schema->validate((object) [
    'price' => (object) ['currency' => 'AUD', 'amount' => '12.50'],   // a record
    'lines' => [$row, $row],                                          // a list
]);
```

PHP blurs this and nothing else will draw the line — an associative array and a list are the same
type. Putting the distinction in the *shape* of the input buys something concrete: a collection
can name its rows, `['line item 1' => …]`, because a string key on an array is no longer
ambiguous with a record's field name.

**What it costs.** `$_POST` and `$_FILES` are arrays throughout, and `json_decode($body, true)`
gives arrays. The port converts.

---

## Messages are data, and the language is part of the request

The core says *what* failed and *what the limit was*. It does not say it in English.

```php
$failed->name;    // 'minLength'
$failed->bound;   // 3
$failed->part;    // Address\Part::PostalCode, or null
```

Wording arrives separately, from an installable pack of MessageFormat 2 files with no code in
them. Two decisions hold it together, and both are refusals.

**The wording does not belong to PHP.** A pack is data, so a JavaScript or Rust implementation of
this library renders the same sentence from the same file. Bundling translations into the
validator — which every mature alternative does — makes the wording a property of the
implementation rather than of the definition, and a port has to re-translate everything.

**The language cannot reach the judging.** A definition is the same in every language: the same
data passes or fails identically whoever is reading. So both the provider and
the *locale* is passed to `validate()`, applied to the verdicts afterwards. The consequence is the
point — ask for a language nobody has and you get every failure you would otherwise have got, with
nothing to say about them. Nothing about wording can move an outcome.

It is optional throughout, and a field validated on its own has no sentences at all — its
violations carry codes and parts, and nothing worded — because there is no schema to have carried
a provider. See [MESSAGES.md](MESSAGES.md).

**What it costs.** One language ships. And MessageFormat 2 has no PHP implementation yet, so the
packs are written against a subset — variable expansion — with anything richer refused until a
real library lands.

---

## A failure says everything a message needs

Three things, and each exists because writing the message without it meant guessing.

```php
$failed = $billing->resultIn($schema->validate($data))->violations->first();

$failed->name;    // 'postalCodeFormat'  — what was checked
$failed->part;    // Address\Part::PostalCode — which piece of the value it was about
$failed->bound;   // '\d{4}'             — the limit, ready to interpolate
```

so a message provider is a lookup rather than a parser:

```php
match ($failed->name) {
    'minLength' => "Needs at least {$failed->bound} characters",
    'postalCodeFormat' => "That is not a valid postcode",
};
```

Every failure carries the three, whichever step found it: `postalCodeFormat` is reported while the
address is assembled, and `minLength` by a constraint, and a provider cannot tell — nor needs to.

**`name` — what was checked.** It used to be the only thing a result carried, so anything wanting
to say more had to go back to the field and read `$field->{$name}` — a dynamic property access
that static analysis cannot type and that renders `"Array"` for a bound held as a map.

**`part` — which piece.** A structured value fails in one place: an address's postcode, a card's
expiry. That used to be spelled into the name as `billing.postal_code.format`, so a message
provider did `strrpos($name, '.')` and split the string to find out. Worse, the name carried the
*field* — renaming `billing` to `invoice_address` changed every constraint it emitted and broke
every provider matching on them. Now the name is fixed and the part is a separate value.

**`bound` — the limit.** "Too short" is not a useful sentence; "needs at least 3 characters" is.
Some bounds are only knowable once you see the value — `Money` holds a minimum per currency,
`Address` a postcode pattern per country — so a check can report the one that applied to the
submitted value rather than declaring it up front:

```php
$money = $schema->createMoneyField('cost', ['AUD' => 2, 'USD' => 2])
    ->minAmountOf('AUD', '10.00')
    ->minAmountOf('USD', '7.00');

// The bound reported is the one that applied to the currency actually submitted.
$money->validate((object) ['currency' => 'USD', 'amount' => '5.00'])
    ->constraints->named('minAmount')->bound;   // '7.00'
```

Without that, a field allowing more than one currency could say a value was too small but not what
it should have reached.

---

## Rules are values, and an outcome is an operation

A rule is built, held in a variable if that is useful, and added explicitly. Both branches live
on one rule:

```php
$whoFor->when()->equals('someone_else')
    ->then($participantName->makeRequired())
    ->else($participantName->makeOptional());
```

Writing the else-branch as a second rule with a hand-inverted condition means two conditions
supposed to be opposites, with nothing checking that they stay so.

An outcome is `applyTo(Field): Field` — an operation, not a replacement. Operations compose where
whole-object snapshots clobber each other, and an operation can still be asked what it *was*, so
a result can say why a field is optional.

---

## Standards data, not hand-typed tables

Addresses come from Google's libaddressinput, phone numbers from libphonenumber, currencies from
ISO 4217, password strength from zxcvbn. A hand-rolled postcode regex is wrong for most of the
world and nobody notices until somebody from there tries to use the form.

**What it costs.** Four real dependencies, and their data moves under you — which is the point:
you want the list of countries to change without editing this library.

---

## Further reading

- [FIELD-API.md](FIELD-API.md) — the contract a field implements
- [API.md](API.md) — every field's surface, and why each name was chosen
- [MESSAGES.md](MESSAGES.md) — language packs, and why a locale is part of a request
- [CODING-STYLE.md](CODING-STYLE.md) — the conventions these decisions produce
- [LIMITATIONS.md](LIMITATIONS.md) — what is still wrong, with reproducers
