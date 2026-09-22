# Known limitations

This page exists so you can decide whether to adopt `meraki/schema` with your eyes open.
Every defect below has a reproducer you can paste into a script and run.

It began as an audit of `v1.13.0-alpha` and has been kept current since. Where a defect is
fixed it says so and keeps the original description, because the record of what went wrong is
worth more than a shorter page.

The library is **pre-release**. See [ROADMAP.md](ROADMAP.md) for the release ladder and
[the release verdict](ROADMAP.md#release-verdict) for why.

- [Known defects](#known-defects) — all fixed; kept as the record of what they were
- [Design constraints](#design-constraints) — intentional behaviour that will surprise you
- [Not yet implemented](#not-yet-implemented) — advertised but inert
- [Rough edges](#rough-edges) — smaller API warts

---

## Known defects

<a id="b9"></a>

### B9 — `prefill()` leaks between concurrent requests

**Fixed in 2.0.** `prefill()` is gone; a prefill now arrives with the request as
`validate($submitted, prefilledWith: $known)` and is never written anywhere. The reproducer
below is now `LongLivedProcessTest::prefill_still_leaks_between_concurrent_requests`, inverted
to assert isolation, alongside `a_prefilled_value_is_never_retained_by_the_schema` for the
retention half. `ResolvedField::$source` reports which of submitted, prefilled or the authored
default the judged value came from.

The description below is kept as the record of what the defect was.

---

`prefill()` wrote one request's data onto every field, exactly as `input()` did before it
was removed. So a worker that fills in what it knows about a user — their saved email,
their last address — puts one request's data where another request reads it. This is
[B7](#b7) unchanged, in the one method that survived it, and it survived because a default
was thought of as authoring rather than as request data.

```php
$schema = new Meraki\Schema\Facade('profile');   // built once at worker boot
$schema->addTextField('email');

$request = fn(string $email) => new Fiber(function () use ($schema, $email) {
    $schema->prefill(['email' => $email]);   // "fill in what we know about this user"
    Fiber::suspend();                        // any I/O — the coroutine switches here
    return $schema->validate([])->get('email')->value;
});

$a = $request('alice@example.com');
$b = $request('mallory@example.com');
$a->start(); $b->start(); $a->resume(); $b->resume();

$a->getReturn();   // 'mallory@example.com'  ← alice's request reads mallory's data
```

The value is also retained after the request that supplied it, so user data sits in the
worker's memory indefinitely:

```php
$schema->prefill(['email' => 'alice@example.com']);

str_contains(serialize($schema), 'alice@example.com');   // true
```

The five long-lived-process tests did not catch this because none of them called
`prefill()`. A sixth now asserts the defect, so it fails the moment the fix lands.

#### What changes

Two mechanisms instead of one. An authored constant stays on the definition, renamed to
say so — `defaultsTo(1)` — and serialises. A per-request value moves to resolution:

```php
$schema->resolve($submitted, prefilledWith: $known);
```

The guarantee that buys is stronger than the fix: if the definition can only hold constants
the author typed, a serialised schema can never contain user data. See
[FIELD-API.md](FIELD-API.md#defaults).

---

<a id="b7"></a>

### B7 — Sharing one schema across concurrent requests leaks data between them

**Fixed on `main`, for `2.0.0`.** `validate()` and `resolve()` take the request's data as
an argument and return a `ResolvedField` per field, writing nothing back. A schema can be
built once at boot and shared, which is what long-lived workers (Swoole, RoadRunner,
FrankenPHP) need.

Five tests in `tests/LongLivedProcessTest.php` hold this down, one per claim below:
fibers interleaved mid-request, a clone, retention after the request, a before/after
snapshot of the whole schema, and serial reuse. They are the acceptance criteria — if any
regresses, this defect is open again.

`input()` — the path that staged a request onto every field, and the root cause of this
defect — has been removed along with the field properties behind it. There is no longer a
way to put one request's data on a schema.

The rest of this entry describes the behaviour as it was, for anyone on `1.x`.

#### What was safe

**Serial reuse.** `input()` overwrote every field, including ones absent from the payload,
and rules reset each field to its authored optionality before re-applying. So validating
the same instance repeatedly gave order-independent, correct results — which covered
RoadRunner's one-request-at-a-time worker model.

```php
$schema = new Meraki\Schema\Facade('signup');
$schema->addTextField('username')->minLengthOf(3);
$schema->addTextField('nickname')->makeOptional();
$schema->whenAllMatch(fn($r) => $r
    ->whenEquals('#/fields/username/value', 'admin')
    ->then($nickname->makeRequired()));

$schema->validate(['username' => 'admin'])->anyFailed();                        // true
$schema->validate(['username' => 'bob', 'nickname' => 'bobby'])->anyFailed();   // false
```

#### What was not safe

**Concurrent reuse** — Swoole coroutines, ReactPHP, Amp, or plain fibers. Field state was
instance state, and every coroutine shared it:

```php
$schema = new Meraki\Schema\Facade('signup');   // built once at worker boot
$schema->addTextField('username')->minLengthOf(3);

$request = fn(string $user) => new Fiber(function () use ($schema, $user) {
    $schema->input(['username' => $user]);   // this request stages its input
    Fiber::suspend();                        // any I/O — the coroutine switches here
    return $schema->fields->getByName('username')->resolvedValue->unwrap();
});

$a = $request('alice');
$b = $request('mallory');
$a->start(); $b->start(); $a->resume(); $b->resume();

$a->getReturn();   // 'mallory'  ← alice's request reads mallory's data
```

Two things make this sharper than a normal race:

- **`clone` did not isolate.** Neither `Facade` nor `Field\Set` defines `__clone`, so a
  clone shared the very same `Field` objects and validating it mutated the original. The
  workaround most people reach for first failed silently.
- **Input was retained after the request ended.** After
  `input(['username' => 'alice-secret'])` the field still held `'alice-secret'` until
  something overwrote it, so user data sat in the worker's memory indefinitely.

Both are covered by tests now: one clones a schema and validates the clone, another asserts
a submitted value cannot be found anywhere in the schema afterwards.

#### What to do on `1.x`

Build the schema per request. It is cheap — a seven-field checkout schema with two
addresses, a phone number, money and a collection builds in **0.25 ms**, against **0.43
ms** to validate it once. Rebuilding costs less than validating, so there is very little
to gain by hoisting it.

If you must keep a prototype, `serialize()`/`unserialize()` round-trips a `Facade`
cleanly and does deep-copy it:

```php
$schema = unserialize(serialize($prototype));   // ~0.13 ms, genuinely isolated
```

Be careful with dependency injection containers: registering a schema as a service shares
one instance by default, which is exactly the unsafe case. Register a **factory**, not an
instance.

#### What changed

Per-request state moved into a `ResolvedField` returned by `validate()`, so a shared
instance is safe by construction rather than by discipline. See
[the architecture decision](ROADMAP.md#architecture-immutable-definition--resolvedfield).

The last write to survive was not on a field at all. `Scope` was an `Iterator`, and
resolving one walked its cursor — but a rule builds its scope once in its constructor, so
that cursor lived on the schema and every request moved it. Results were correct, because
resolution rewound first, yet the definition was still being written to. A scope is an
immutable value now, so there is no cursor to move.

`input()`, `ignoreInput()` and `acceptInput()` are gone, and with them the `$value`,
`$resolvedValue`, `$inputGiven` and `$inputIgnored` properties. A rule that discards a
field's input says so as an outcome, which reaches the result without the schema having to
remember it between requests.

Two of the five caught it — the clone and snapshot tests, which are the two that compare
the whole serialized schema before and after. The fiber, retention and serial-reuse tests
passed throughout, because a moved cursor changes no result: this was a write nobody could
observe through the API, which is exactly why it needed a test that looks at the object
rather than at the answer.

`input()` and the field properties behind it are now gone, so a schema is safe to share
without qualification. Sealing the definition outright — making a field `readonly` rather
than merely unwritten — is the remaining work; see [ROADMAP.md](ROADMAP.md).

---

## Design constraints

These are not bugs. They are deliberate, and they will still surprise you.

### The core expects typed PHP values, not raw request strings

Validation is strict about types. `Boolean` rejects `"1"` and `"on"`; rule conditions
compare with `===`, so `whenEquals(..., true)` never matches the string `"1"`.

```php
$schema = new Meraki\Schema\Facade('prefs');
$schema->addBooleanField('subscribe');

$schema->validate(['subscribe' => 'on'])->anyFailed();   // true  — HTML form input
$schema->validate(['subscribe' => true])->anyFailed();   // false
```

This is intentional: normalizing an HTTP request is `meraki/schema-html`'s job, not the
core's. If you point the core straight at `$_POST` without normalizing, everything that
is not a string will fail.

### Validation is a query, not a step

`validate($data)` and `resolve($data)` return results and leave the schema exactly as they
found it, so a `Facade` may be built once and shared.

There is no longer a way to stage data onto a schema first. `input()`, which did that and
was the root cause of [B7](#b7), has been removed: the value goes in as an argument and
comes back on a `ResolvedField`.

### Rules are single-pass and order-dependent

`Rule\Set::apply()` iterates once, in the order rules were added. A rule whose condition
depends on a field that a later rule changes will not re-evaluate, and there is no cycle
detection. Order your rules so that dependencies come first, and avoid rules that feed
each other.

### Composite input nests by local name

Sub-field values are supplied nested under the composite. Fully-qualified flat keys are
not accepted, and unrecognised keys are silently ignored — so a typo in a sub-field name
leaves that sub-field empty and fails its required check rather than reporting the typo.

```php
$schema->validate(['price' => ['amount' => '1500', 'currency' => 'AUD']]);   // yes
$schema->validate(['price.amount' => '1500']);                               // ignored
```

---

## Not yet implemented

### Password strength is estimated against English

`Password::minStrengthOf()` measures guess-resistance with zxcvbn, whose ranked dictionaries are
English-centric: common English passwords, English words, English-speaking names and surnames. A
secret built from words in another language is scored as though those words were unknown, so it
reads as stronger than it is.

A genuinely random secret is *under*-estimated for the opposite reason — 32 random hex characters
carry 128 bits and report 104.6 — because zxcvbn's fallback model assumes a smaller alphabet than
the generator used. That direction is harmless; the dictionary gap is not.

Mitigating it properly needs locale-specific frequency lists. Several zxcvbn forks ship them
(`zone-eu/zxcvbn-php-et` adds Estonian, for instance) but there is no general mechanism here for
selecting one.

### Dictionary checking

The old `Passphrase` field carried a `dictionary` constraint that did nothing: the only
accepted value was `'none'`, and the check behind the unreachable `'custom'` option was a stub
returning a hard-coded result. It did not survive the merge into `Password` — a constraint that
appears in results and in serialized documents while doing nothing is worse than its absence.

### Collection failures carry no item index

Every item in a collection is validated against the same template fields, and the results
are flattened into one list — so you can tell that *an* item failed, but not *which* one.
This makes repeatable collections hard to report on in a real form.

```php
$schema->addCollectionField('items', fn($i) => $i->addNumberField('qty')->minOf(10));
$result = $schema->validate(['items' => [['qty' => 50], ['qty' => 1]]]);

$result->anyFailed();   // true — but nothing says it was item 1
```

Fixed in `2.0.0`, where a structured value carries its own shape rather than being flattened into sub-field results.

---

## Recently fixed

### Password strength ignored patterns and repetition

Until `Password` absorbed `Passphrase`, strength was estimated as `log2(pool size) × length`,
which counted which classes of character appeared and never looked at the arrangement. Forty
copies of the letter `x` scored 188 bits and satisfied the `Cryptographic` tier.

Fixed by measuring with zxcvbn instead, which matches against common passwords, words, names,
keyboard walks, repeats and sequences. The same secrets now score 6–14 bits and satisfy nothing:

| Secret | Before | After |
| --- | --- | --- |
| 40 × `x` | 188 bits | 8.9 bits |
| `abcdefghijklmnopqrst` | 94 bits | 6.3 bits |
| `P@ssw0rd123` | 72 bits | 13.9 bits |

The tier thresholds were recalibrated to the new scale — they are not comparable between
estimators — and `tests/Field/PasswordTest::length_alone_does_not_make_a_secret_strong()` pins it.

### B3 — `CreditCard` had no Luhn check

**Fixed in `1.14.0`.** The field checked that a number was 13–19 digits but never its
check digit, so `4111111111111112` passed. Every card number carries a Luhn check digit
(ISO/IEC 7812-1), so one that fails it is not a card number and no processor will take it.

Reported as `<name>.number.checksum`, and skipped rather than failed when the number is
not yet in a state the checksum can speak to — the digit and length rules report that
instead.

Worth knowing if you had tests of your own: every card number in this package's own
fixtures failed the check. They were generated numbers that had never been valid, and the
suite asserted they were. They have been repaired by recomputing the final digit, which
keeps each brand's prefix and length intact.
### B2 — `Uri` validated nothing

**Fixed in `1.14.0`.** Every group in the field's pattern was optional, so it collapsed to
`is_string()` and `'not a url at all !!'` passed.

It now parses with PHP's own `Uri\Rfc3986\Uri` rather than a pattern of this library's
making — the grammar is a matter of public record, which is exactly the case for taking it
from the platform. Absolute and relative references, URLs and URNs all parse; malformed
input does not.

`allowSchemes()` is new. Without it any scheme is accepted, because a URI field is not
always a web link and `urn:`, `mailto:` and `tel:` are legitimate. Anything rendered back
into a page or followed as a redirect should declare one:

```php
$schema->addUriField('website')->allowSchemes('http', 'https');
// javascript: and data: now fail
```
### B1 — malformed input on a composite field threw instead of failing

**Fixed in `1.13.2-alpha`.** `Money`, `Address`, `CreditCard` and `Collection` raised an
uncaught exception when handed a value that was not a set of sub-field values, so a
crafted request became a 500 rather than a validation error.

Unusable input is now kept as it came instead of being rejected during processing, the
shape check refuses anything that is not an array, and the failure is reported as `type`
against the composite itself while its sub-fields are skipped — nothing reached them, so
faulting each one would bury the single real problem.

Being optional no longer excuses it either: an absent value is skipped, a malformed one
fails. Previously such input was treated as absent, which quietly fell back to the default
and validated something the caller never sent.
### B5 — field names were not validated

**Fixed in `1.13.2-alpha`.** Empty names, and names containing the scope separators `/`
and `#`, whitespace or a leading digit, are now rejected by `Property\Name`. A name may
still contain `.`, because that is how a composite names its sub-fields, but a *top-level*
field carrying one is rejected — it would be indistinguishable from an `addr.line1`
belonging to some composite. Hyphens remain valid, so `create-person` is still a usable
schema name.

### B6 — duplicate field names were silently dropped

**Fixed in `1.13.2-alpha`.** Adding a second field under an existing name now throws
instead of discarding the definition without a word.

### B4 — some shipped classes referenced types that do not exist

**Fixed in `1.13.2-alpha`.** The unreachable validator subsystem — `Meraki\Schema\Validator`
and everything under `Validator\`, `Field\Validator`, the root `ConstraintValidationResult`,
`ValidationResultMessageProvider`, `Field\Placeholder`, `Field\Structured` — has been
deleted, along with the four exceptions used only by it.
<a id="b8"></a>

### B8 — a scope path could exhaust memory and hang the process

**Fixed in `1.13.2-alpha`.** `Field::$schema` is a back-reference to the owning `Facade`,
and it was the only property on a field whose type implemented `ScopeTarget` — so a scope
stepping into it climbed back to the root and walked the same path forever. It was
reachable from data, since `meraki/schema-json` deserialises rule targets straight into
scope strings.

The back-reference is now rejected as a scope target, the recursion branch that existed
solely to enable it is gone, and `Facade::traverse()` no longer rewinds the cursor on
entry. Addressing a field's other public properties — `#/fields/x/min`,
`#/fields/x/optional` — is unaffected, because a field's public properties are its API.
## Rough edges

Smaller warts, listed so they are not surprises.

| Issue | Detail |
| --- | --- |
| No `remove()` on `Field\Set` | Fields can be added to a schema but not removed. |

**Everything else that was here is fixed**, and the list is kept in the changelog rather than
above: rules are checked when written, `getByName()` returns what it says, a field is `readonly`
so its configuration cannot be written to, scopes are immutable and reach parts, and the outcome
that needed a leading underscore no longer exists.

---
## Reporting something not listed here

Open an issue at <https://github.com/merakiframework/schema/issues>. If it is a
validation result you disagree with, a reproducer in the shape used above — schema,
input, actual result — is the fastest path to a fix.
