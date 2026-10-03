# Working on the core

This is the guide for changing `meraki/schema` itself — the map of the codebase, the rules it
holds itself to, and where a new piece of code belongs.

You do not need to have read the rest of the docs first. Where something is covered in depth
elsewhere, there is a link.

| If you want to | Read instead |
| --- | --- |
| use the library | [the README](../README.md), then [COOKBOOK.md](COOKBOOK.md) |
| add a field type in your own project | [EXTENDING.md](EXTENDING.md) |
| know why a decision was made | [DESIGN.md](DESIGN.md) |
| know how to format code | [CODING-STYLE.md](CODING-STYLE.md) |

---

## Getting set up

You need PHP 8.5 or newer and Composer. Nothing else — no database, no server, no Docker.

```sh
git clone https://github.com/merakiframework/schema.git
cd schema
composer install
```

`composer install` also points git at `.githooks`, so a pre-commit hook formats the files you
stage. You can skip the hook with `git commit --no-verify`; CI will still catch you.

Four commands cover almost everything:

```sh
composer test        # the test suite — about a second
composer analyse     # PHPStan over src/ and tests/
composer style       # fix formatting
composer ci          # everything CI runs, in CI's order
```

Run `composer test` constantly and `composer ci` before you push. A fast suite is deliberate:
if checking your work is cheap, you check it often.

---

## The one idea

**A schema is a definition. A request never touches it.**

A `Definition` holds fields and rules. You build it once and it never changes again. Everything that
belongs to one request — what was submitted, what each check said — comes back as a *result*
object, and nothing is written back.

```php
$schema = new Definition('signup');
$schema->add($schema->createTextField('username')->minLengthOf(3));

$a = $schema->validate((object) ['username' => 'jo']);     // fails
$b = $schema->validate((object) ['username' => 'jordan']); // passes

// $schema is exactly what it was. Both results are still valid and independent.
```

This is why fields are `readonly`, why configuring one hands back a copy instead of changing it,
and why there is no `$schema->input()`. One schema can serve every request in a long-running
process, concurrently, without locking.

Almost every rule further down is this idea applied somewhere specific. [DESIGN.md](DESIGN.md)
has the full argument.

---

## Words this codebase uses precisely

These are not interchangeable. Using the wrong one in a name or a docblock is the most common
review comment.

| Word | Means |
| --- | --- |
| **definition** | the schema and its fields as the author wrote them. Never holds request data |
| **field** | one thing being asked for. `username`, `billing`. Immutable |
| **value** | what a field made of the input — always an object, like `Money\Value`, never a bare string |
| **part** | one named piece of a value: an address's `locality`, money's `currency` |
| **shape** | "could this be read as this kind of thing at all?" Asked first |
| **constraint** | one check with a name, run only after the shape passes. `minLength` |
| **verdict** | what one constraint said: passed, failed, or skipped |
| **result** | everything one request produced. `SchemaValidationResult`, `ResolvedField` |
| **rule** | "if this, then that" — changes a field's *definition* for one request |
| **outcome** | what a rule does when it fires. An operation on a field |
| **scope** | a path to something: `#/fields/billing/value/country` |
| **port** | a package that connects this one to the outside world — HTML, JSON, your app |

Two distinctions carry most of the weight:

**Shape before constraint.** "Is `banana` a date?" is a shape question. "Is this date after
1 January?" is a constraint. If the shape fails, every constraint is *skipped* rather than failed,
because nothing got far enough to be checked. One mistake, one report.

**Definition versus request.** A default is written by the author and lives on the field. A
*prefill* is one user's saved data and arrives as an argument to `validate()`. They look similar
and must never be stored in the same place, or a schema shared between requests hands one user's
address to the next.

---

## The map

```
src/
├── Definition.php              the schema: fields, rules, validate(), resolve()
├── Field.php               what every field is (interface)
├── AtomicField.php         the usual lifecycle: one value, checked
├── Scope.php               a path to something, like #/fields/x/value
├── Rule.php                if this, then that
│
├── Field/                  the nineteen field types, plus the machinery they share
├── Rule/                   conditions, outcomes, and the fluent builder
├── Scope/                  where a scope is rooted — schema field, collection row
├── Comparison/             how two values are compared
├── Message/                turning verdicts into sentences
└── Exception/              everything that raises
```

### What each namespace owns

| Namespace | Owns | Go here when |
| --- | --- | --- |
| **(root)** | the schema, the lifecycle, results, scopes | you are changing how a request flows |
| `Field\` | the field types and their values | you are adding or changing a field |
| `Field\Constraint` | one named check and its verdict | you are changing what a constraint reports |
| `Rule\Condition\` | the questions a rule can ask | you are adding a verb like `contains` |
| `Rule\Outcome\` | what a rule does — `Reconfigure`, `Ignore` | you are adding a new kind of effect |
| `Rule\Matcher\` | the fluent builder, split by what a value supports | a field type needs different verbs |
| `Message\` | verdicts to sentences, per language | you are changing how wording is looked up |
| `Comparison\` | `Equality`, `Comparable`, `Order` | you are changing what "equal" or "larger" means |

### The field directory

Every field type is two files and a folder:

```
Field/Money.php             the field: configuration, constraints, parse()
Field/Money/Value.php       the value: what money IS, and what it refuses
```

The **field** knows about rules the author set — which currencies are allowed, what the minimum
is. The **value** knows what money is at all. A currency code being three letters belongs to the
value; a currency being one *this field accepts* belongs to the field.

Shared machinery sits beside them:

| File | What it does |
| --- | --- |
| `Field/Definition.php` | the trait every field uses: configuration, copy-on-change, defaults |
| `Field/Constraint.php` | one check: a name, a closure, the limit, the part it concerns |
| `Field/ParsedValue.php` | the marker every value object implements |
| `Field/Input.php` | a record read part by part, and whether its parts make a value |
| `Field/HasParts.php` | implemented by anything a rule can read a part from |
| `Field/BuildsFields.php` | the `createTextField()` helpers on the definition |
| `Field/ValueClass.php` | reads a field's value type off its `parse()` signature |

---

## One request, start to finish

This is the most useful thing to have in your head. Here is a small schema:

```php
$schema = new Definition('checkout');
$schema->add(
    $pay = $schema->createEnumField('pay_by', ['card', 'invoice']),
    $card = $schema->createCreditCardField('card')->makeOptional(),
);
$schema->addRule(
    $pay->when()->equals('card')->then($card->makeRequired()),
);

$result = $schema->validate((object) [
    'pay_by' => 'card',
    'card'   => (object) ['number' => '4111111111111111', 'expiry' => '2030-01'],
]);
```

What happens, in order:

**1. The schema copies itself.** `Definition::against()` does `clone $this`. Rules are about to
change fields, and they change the copy. Your `$schema` is untouched.

**2. Rules run, interleaved.** `Rule\Application` walks the rules in the order they were added.
For each one it evaluates the condition against the submitted data, then applies the outcomes
immediately — before looking at the next rule. So a later rule sees what an earlier one did.
That is deliberate and documented, not an accident.

Here the condition `pay_by equals 'card'` holds, so `makeRequired()` is applied: the *copy* of
the `card` field is replaced with a required one.

**3. Each field is handed its value.** For every field, the definition picks what to validate:

```
submitted  →  prefilled  →  the authored default  →  nothing
```

Whichever won is recorded as `ValueSource`, so a form can show a prefilled box differently from
one somebody typed into.

**4. The field reads the value.** `parse()` turns raw input into a value object. It receives three
guarantees and must keep one:

- it is never given `null` — absence was settled in step 3
- it returns a value object, **or raises** `MalformedValue`. Never `null`
- whatever it returns is exactly what the constraints will see

**5. The shape is judged.** `AtomicField::check()` decides between four cases:

| | |
| --- | --- |
| nothing arrived, field is optional | shape **skipped**, constraints skipped |
| nothing arrived, field is required | shape **missing**, constraints skipped |
| something arrived, `parse()` refused it | shape **unreadable**, constraints skipped |
| something arrived and parsed | shape **passes**, constraints run |

Notice that constraints are *skipped* in the first three, never failed. A skipped constraint is
not a quiet pass — it means the question was never asked.

**6. Constraints run.** Each one gets the parsed value and answers `true`, `false`, or `null`.
`null` means "this does not apply here", which is how `allowedCurrencies` stays quiet when no
allow-list was set.

**7. Results come back.** A `ResolvedField` per field, inside a `SchemaValidationResult`.

```php
$card = $result->forField('card');

$card->shape->passed();                            // true
$card->forConstraint('nameRequired')->failed();    // true — no cardholder name was sent
```

Printing every verdict for the card above shows all three answers at once:

```
numberRequired       Passed
expiryRequired       Passed
nameRequired         Failed      ← the one real problem
numberFormat         Passed
numberChecksum       Passed
expiryInFuture       Skipped     ← opt-in; nobody called mustExpireInFuture()
expiryWithinReach    Passed
securityCodeFormat   Skipped     ← no security code was sent, and it is optional
```

Two different reasons for *Skipped* there, and neither is a pass. One question was never
turned on; the other had nothing to ask about.

**8. Messages, if asked.** Only if you passed a provider. Wording is applied *after* every verdict
is settled, which is what makes "a missing translation can never change an outcome" true by
construction rather than by care.

```php
$result = $schema->validate($data, locale: 'en-AU', messages: $provider);
```

### Where to put a breakpoint

| To watch | Stop at |
| --- | --- |
| a whole request | `Definition::against()` |
| a rule firing | `Rule\Application::of()` |
| input becoming a value | your field's `parse()` |
| the shape decision | `AtomicField::check()` |
| one constraint | the closure in `defineConstraints()` |

---

## The invariants

These are the promises the codebase keeps. Each one has a reason and somewhere that catches you
if you break it. If a change of yours needs one of these to bend, that is worth discussing in an
issue before you write the code — some of them are load-bearing for several others.

### 1. A definition never holds request data

Nothing per-request is written to a field or a schema. Prefills are arguments; results are return
values.

*Why:* one schema serves many concurrent requests. A field that could be written to during one
would leak into the next.

*Caught by:* `Api\SealedFieldTest`, `LongLivedProcessTest`.

### 2. Every field is `readonly`, and configuration returns a copy

```php
$field->minLengthOf(3);           // does nothing — the copy is discarded
$field = $field->minLengthOf(3);  // right
```

*Why:* it is invariant 1, enforced by the language rather than by discipline. `readonly` is
inherited both ways, so a field below `AtomicField` is immutable whether its author thought
about it or not.

*Caught by:* `Api\SealedFieldTest`.

### 3. `parse()` returns a value object or raises — never `null`

And the object is always one this library defines. Never a bare string, never a third party's
class.

*Why:* it gives constraints a real type. `checkMinValue(Number\Value $value)` is true by
construction instead of hoping a gate ran first. Returning a third party's class would make their
next major version a breaking change for everybody.

*Caught by:* `Api\ValueObjectTest`, and by every test that submits a value.

### 4. Shape is asked before constraints, and a failed shape skips them all

*Why:* one mistake should produce one report, naming the real problem. A value that could not be
read has nothing for `minLength` to speak to.

*Caught by:* `MalformedCompositeInputTest::the_failure_is_reported_against_the_field_itself`,
which asserts every constraint is skipped when the shape fails.

### 5. A constraint carries everything a message needs

Its name, the limit that applied, and which part it concerns. A consumer never splits a name on
dots or reads `$field->{$name}`.

*Why:* dotted names like `cost.amount.min` meant renaming a field changed every constraint it
reported.

*Caught by:* `Api\ConstraintNameTest`.

### 6. A value reports the parts it is **submitted with**

If `partNames()` lists it, something can send it. A derived reading — E.164 for a phone number,
the whole string for an email — is a method on the value, not a part.

*Why:* a port builds its inputs from the part names. A part nothing can submit is a box that
cannot be drawn and a scope that resolves against nothing.

*Caught by:* `Api\StructuredTypeTest`.

### 7. A key nobody declared raises; a bad value is reported

A record carrying an unknown key raises `Exception\BrokenInputContract` and stops the request.
A declared key holding something unusable is an ordinary verdict.

*Why:* keys are vocabulary, not data. No submitter can type `ammount` — something mapped a payload
onto the wrong name, and that is wrong on every request until somebody edits code. Values *are*
submitter data, and a submitter is allowed to be wrong.

*Caught by:* `MalformedCompositeInputTest`.

### 8. Nothing raises because a form was filled in wrongly

A validation failure is a fact about a request and arrives on the result. What raises is a mistake
in *code* — a rule naming a field that does not exist, a default the field would reject.

Invariant 7 is the one exception, and only because a stray key *is* a mistake in code. See
[API.md](API.md#what-raises-and-what-does-not).

*Caught by:* `Api\ExceptionTest`, and `MalformedCompositeInputTest`, which throws every
unusable value it can think of at a field and asserts a verdict comes back.

### 9. A rule that could never fire is refused where it is written

`$age->when()->equals('eighteen')` on a number field throws at `addRule()`.

*Why:* this is the worst failure mode in the library. A dead rule raises nothing and looks exactly
like a rule whose condition never held, so it can be wrong for years in silence.

*Caught by:* `Rule\Guards`, and the tests in `tests/Rule/`.

### 10. The core repairs nothing

No trimming, no coercing `"1"` to `true`, no guessing that `Australia` was meant to be `AU` unless
a standard says those are one thing.

*Why:* a repair is a guess about intent, and a wrong guess is worse than a refusal because nobody
is told. Normalising belongs to the port, which knows the medium.

*Caught by:* `Api\BaselineTest` for the strict-by-default half, and field tests for the rest —
`Field\Address\ValueTest::a_part_that_is_not_empty_keeps_its_whitespace` is a good example of
the shape they take.

### 11. The language is applied after the verdict, never before

*Why:* the same data must pass or fail identically in every language. A missing translation
produces an empty message set and leaves every verdict exactly as it was.

*Caught by:* `Message\SchemaMessagesTest`.

### 12. Sibling field types do not share code with each other

`Money\Value` and `CreditCard\Value` both check for unknown keys, in six near-identical lines.
That duplication is deliberate.

*Why:* a shared helper makes two types move together forever. Fields are the part of this library
most likely to diverge, and the cost of them diverging *through* a shared base class is far higher
than the cost of two copies. Sharing with the *machinery* — `Definition`, `Constraint` — is fine;
sharing sideways with a peer is not.

*Caught by:* nothing automated — this one is on the reviewer. `Api\NamingTest` keeps the
*names* consistent across fields, which is the part worth sharing; the code is not.

---

## Where does my change go?

| I want to… | It goes | And you will need |
| --- | --- | --- |
| add a check to an existing field | `defineConstraints()` in `Field/X.php` | a row in `Api\ConstraintNameTest`, and wording in the language pack |
| change what a type *is* | `Field/X/Value.php` | the value's own test in `tests/Field/X/ValueTest.php` |
| add a whole new field type | `Field/X.php` + `Field/X/Value.php` | [EXTENDING.md](EXTENDING.md) end to end, plus rows in the `Api\*` sweeps |
| add a question a rule can ask | `Rule/Condition/` + the right `Rule/Matcher/` set | a guard so it cannot be written where it could never fire |
| change how a path resolves | `ScopeResolver.php` | `ScopeResolverTest`, and a thought about stored rules |
| change what a result carries | `ResolvedField.php` or `FieldResult.php` | every port reads these — say so in [UPGRADING.md](../UPGRADING.md) |
| add wording | `meraki/schema-language-english`, not here | `php bin/schema-lang validate <dir>` |
| change formatting rules | `.php-cs-fixer.dist.php` | [CODING-STYLE.md](CODING-STYLE.md) first — the safe/risky split is deliberate |

**When in doubt, ask "whose fact is this?"**

- true of this *kind of value* everywhere → the value object
- true because *this author configured it* → the field
- true of *this request* → the result
- true of *the medium* (HTTP, HTML, JSON) → not this package

---

## The tests

```
tests/
├── Api/          contracts that must hold for EVERY field — the sweeps
├── Field/        one file per field type
├── Rule/         conditions, matchers, guards
├── Message/      language packs and lookup
├── Comparison/   equality and ordering
└── fixtures/     sample language packs
```

### The sweeps are the interesting part

`tests/Api/` does not test one field. It walks *all* of them and asserts something is true of
each. That is where the invariants live.

| Sweep | Asserts |
| --- | --- |
| `SealedFieldTest` | every field is readonly, withers copy, constructor order is right |
| `ConstraintNameTest` | the exact names each field reports |
| `ValueObjectTest` | every field parses to a value object this library owns |
| `StructuredTypeTest` | parts are submittable, and only record fields have them |
| `NamingTest` | method and property names follow the house pattern |
| `BaselineTest` | an unconfigured field accepts what it should |
| `ExceptionTest` | nothing throws a generic exception |

**If you add a field type, you add a row to several of these.** That is the point: a new field
cannot quietly disagree with the other nineteen.

### Writing a test

Tests read as sentences, and the comment explains *why the test exists* — usually the bug it
would have caught.

```php
#[Test]
public function a_country_with_no_number_yet_names_the_missing_number(): void
{
    // It used to be a shape failure, which reported the whole field unreadable for the most
    // ordinary state a phone input passes through — and named neither the problem nor the
    // box to mark.
    $resolved = (new PhoneNumber(new FieldName('phone'), ['AU']))->validate((object) ['country' => 'AU']);

    $this->assertShapePassed($resolved);
    $this->assertConstraintValidationResultFailed('numberRequired', $resolved);
}
```

A test with no comment is fine when the name says everything. A test whose name needs the comment
usually wants a better name.

---

## Things that look like mistakes and are not

Worth knowing before you "fix" one in a pull request.

**The docblocks are long.** Many are longer than the code. They record *why*, including options
that were tried and rejected — so the next person does not re-litigate a decision from scratch.
Adding to one is welcome; trimming one to "set the minimum length" is not.

**Sibling fields duplicate code.** See invariant 12. On purpose.

**There is no base class for value objects.** `Money\Value` and `Address\Value` share nothing but
an interface. Same reasoning.

**`parse()` narrows its parameter type.** `Field::parse()` takes `mixed` and `Address::parse()`
takes something narrower. That is unsound in the LSP sense and PHPStan is told to allow it, with
the reasoning in `phpstan.neon`.

**There is no PHPStan baseline.** Errors get fixed, not recorded. The three `ignoreErrors` entries
each name one class and one message, with a comment saying why and when it goes away.

**The changelog is generated.** Never edit `CHANGELOG.md` by hand — `php tools/changelog.php`
rebuilds it from commit messages.

---

## Making a change

### Commit messages

They become the changelog, so they are written for someone reading the history later, not for a
reviewer who already has the diff.

- **Subject:** what is true now, as a sentence. Not "fix bug", not a ticket number.
  Real examples: *"A required part that was not sent names itself"*, *"A rule about an address
  part no longer dies quietly"*.
- **Body:** why. What was wrong, what you considered, what you chose, what it costs. Mention a
  deliberate omission — someone will otherwise read it as an oversight.

### Before you push

```sh
composer ci
```

That runs: `composer validate`, a security audit, PHPStan on `src/` and `tests/`, formatting
(both the strict pass and the risky report), the suite with coverage, every example, a `{@see}`
reference check, and the changelog check.

If you touched behaviour, also:

- update `docs/API.md` if a name or a constraint changed
- add to [UPGRADING.md](../UPGRADING.md) if a port would break
- regenerate the changelog **before** committing: `php tools/changelog.php`, then stage it with
  your change. It lands one commit behind, which is as close as it can get

### The examples are tests

Everything in `examples/` runs in CI. If you change an API, an example that uses it will fail —
which is the point. Fix the example; it is documentation that cannot go stale.

---

## Getting unstuck

| Problem | Try |
| --- | --- |
| a rule never fires | is the scope right? `(string) $scope` prints the path |
| a constraint never runs | the shape probably failed — check `$resolved->shape` first |
| a value is `null` on the result | `parse()` raised. `$resolved->given` has what arrived |
| `BrokenInputContract` | the record has a key the value does not declare — `$e->unknownKeys` |
| PHPStan disagrees with you | read the comment in `phpstan.neon`; it may already be known |
| a test fails only in CI | usually the changelog check — regenerate and stage it |

If something here is wrong, unclear, or assumes knowledge you did not have, that is a bug in this
document. Please say so.
