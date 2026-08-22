# Known limitations

This page exists so you can decide whether to adopt `meraki/schema` with your eyes open.
It is written from an audit of the library at `v1.13.0-alpha`, and every defect below has
a reproducer you can paste into a script and run.

The library is **pre-release**. See [ROADMAP.md](ROADMAP.md) for the release ladder and
[the release verdict](ROADMAP.md#release-verdict) for why.

- [Known defects](#known-defects) — things that are broken, with the release that fixes them
  ([B2](#known-defects) and [B3](#known-defects) field defects, [B7](#b7) concurrency; B1, B4, B5, B6 and B8 are fixed)
- [Design constraints](#design-constraints) — intentional behaviour that will surprise you
- [Not yet implemented](#not-yet-implemented) — advertised but inert
- [Rough edges](#rough-edges) — smaller API warts

---

## Known defects

### B2 — `Uri` validates nothing

**Fixed in:** `1.14.0-beta.1`

Every group in the URI pattern is optional, so it collapses to `is_string()`. There is
also no scheme allowlist, which makes a URI field an XSS or open-redirect foot-gun for
anything that renders the value back.

```php
$schema = new Meraki\Schema\Facade('link');
$schema->addUriField('url');

$schema->validate(['url' => 'not a url at all !!'])->anyFailed();                 // false
$schema->validate(['url' => 'javascript:alert(document.cookie)'])->anyFailed();   // false
$schema->validate(['url' => 'data:text/html;base64,PHNjcmlwdD4='])->anyFailed();  // false
```

**Work around it** with a `Text` field and your own pattern, plus `filter_var($v,
FILTER_VALIDATE_URL)` and an explicit scheme check before you render or follow the value.

### B3 — `CreditCard` has no Luhn check

**Fixed in:** `1.14.0-beta.1`

The field validates the shape of its parts but never the checksum, so it accepts numbers
that no payment processor will.

```php
$schema = new Meraki\Schema\Facade('payment');
$schema->addCreditCardField('card');

$schema->validate(['card' => [
    'holder' => 'Jane Doe',
    'number' => '4111111111111112',   // invalid Luhn checksum
    'expiry' => '2030-01',
    'security_code' => '123',
]])->anyFailed();   // false
```

**Work around it** by running your own Luhn check on the number before trusting the
result.

<a id="b7"></a>

### B7 — Sharing one schema across concurrent requests leaks data between them

**Fixed in:** `1.14.0-beta.2`

The library is meant to be usable in long-lived workers (Swoole, RoadRunner, FrankenPHP),
where a schema is built once at boot and reused. Today that is only half true.

#### What is safe

**Serial reuse.** `input()` overwrites every field, including ones absent from the
payload, and rules reset each field to its authored optionality before re-applying. So
validating the same instance repeatedly gives order-independent, correct results — which
covers RoadRunner's one-request-at-a-time worker model.

```php
$schema = new Meraki\Schema\Facade('signup');
$schema->addTextField('username')->minLengthOf(3);
$schema->addTextField('nickname')->makeOptional();
$schema->whenAllMatch(fn($r) => $r
    ->whenEquals('#/fields/username/value', 'admin')
    ->thenRequire('#/fields/nickname'));

$schema->validate(['username' => 'admin'])->anyFailed();                        // true
$schema->validate(['username' => 'bob', 'nickname' => 'bobby'])->anyFailed();   // false
```

#### What is not safe

**Concurrent reuse** — Swoole coroutines, ReactPHP, Amp, or plain fibers. Field state is
instance state, and every coroutine shares it:

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

- **`clone` does not isolate.** Neither `Facade` nor `Field\Set` defines `__clone`, so a
  clone shares the very same `Field` objects and validating it mutates the original. The
  workaround most people reach for first fails silently.
- **Input is retained after the request ends.** After
  `validate(['username' => 'alice-secret'])` the field still holds `'alice-secret'` until
  something overwrites it, so user data sits in the worker's memory indefinitely.

#### What to do today

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

#### What changes

From `1.14.0-beta.2` the definition becomes immutable and per-request state moves into a
`ResolvedField` returned by `validate()`, which makes a shared instance safe by
construction rather than by discipline. See
[the architecture decision](ROADMAP.md#architecture-immutable-definition--resolvedfield).

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

### `validate()` currently writes to the fields

The intended design is that validation is a pure query. Today it stores no *result* on
the fields, but it does write the submitted input onto them, which is the root cause of
[B7](#b7). Treat a `Facade` as per-request state, not a shared singleton, until
`1.14.0-beta.2`.

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

### `Passphrase` dictionary checking

The `dictionary` constraint appears in a passphrase field's results and would appear in
its serialized form, but it does nothing. The only accepted dictionary is `'none'`, and
the check behind the unreachable `'custom'` option is a stub that returns a hard-coded
value. Treat passphrase validation as entropy-only.

### Collection failures carry no item index

Every item in a collection is validated against the same template fields, and the results
are flattened into one list — so you can tell that *an* item failed, but not *which* one.
This makes repeatable collections hard to report on in a real form.

```php
$schema->addCollectionField('items', fn($i) => $i->addNumberField('qty')->minOf(10));
$result = $schema->validate(['items' => [['qty' => 50], ['qty' => 1]]]);

$result->anyFailed();   // true — but nothing says it was item 1
```

Fixed in `1.14.0-beta.3`, where results gain indexed paths (`items[1].qty.min`).

---

## Recently fixed

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

Smaller warts, listed so they are not surprises. All are slated for the `1.14.0` line.

| Issue | Detail |
| --- | --- |
| A rule targeting a missing field throws at validation time | A typo in a scope path surfaces as an exception on a user request rather than an error when the rule is defined. |
| `Field\Set::getByName()` never returns `null` | Its return type says `?Field` but it throws when not found. `findByName()` is the nullable one. |
| Filtering a result detaches the field | `Field\ValidationResult::__clone()` deep-clones the field, so `$fieldResult->getFailed()->field` is a *copy*, not the field in your schema. Do not compare it by identity. |
| Constraint config is publicly mutable | `$field->min = -5` bypasses the validation in `minLengthOf()`. Use the fluent setters. |
| `Rule\Outcome\_Require` has a leading underscore | Working around the `require` keyword. It will be renamed before the API freezes. |
| No `remove()` on `Field\Set` | Fields can be added to a schema but not removed. |
| Rules cannot target composite sub-fields | Neither `#/fields/addr/line1` nor `#/fields/addr.line1` resolves — `Facade::traverse()` only searches the top-level field set. Collection items (`#/fields/items/0/sku`) are likewise unreachable. |
| `Scope` carries a mutable cursor | Resolving advances an internal position, so a scope held by a rule outcome is shared mutable state. `meraki/schema-html` works around this in two places. Unsafe under concurrency. |
| The root scope `#/` cannot be constructed | The constructor trims the trailing slash, leaving `#`, which fails its own format check — so `Scope::isRoot()` and both root branches that call it are unreachable. |

---

## Reporting something not listed here

Open an issue at <https://github.com/merakiframework/schema/issues>. If it is a
validation result you disagree with, a reproducer in the shape used above — schema,
input, actual result — is the fastest path to a fix.
