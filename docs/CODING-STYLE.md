# Coding style

Formatting is enforced. `.php-cs-fixer.dist.php` fixes it, a pre-commit hook runs on the files
you stage, and CI fails on anything unformatted. What follows is the part a tool still cannot
check — conventions that are decisions rather than formatting — plus, first, a short account of
which is which.

## What the tools enforce

| | Where | What happens |
| --- | --- | --- |
| **Fixed** | `.php-cs-fixer.dist.php` | Applied automatically. Only rules that cannot change behaviour. |
| **Reported** | `.php-cs-fixer-risky.dist.php` | Shown as a diff, never applied. A finding fails CI. |
| **Reported** | `tools/CodeStyle/PhpStan/` | House rules needing type information. A finding fails CI. |
| **Human** | this document | Nothing checks it. |

```sh
composer style          # fix what can be fixed safely
composer style:report   # show what the risky rules would change; never writes
composer test:style     # fail if anything is unformatted
```

The split is the design, not an accident of what was easy. A rule that could alter behaviour is
never applied on the strength of an assumption it cannot check — it reports, and a human decides.
That is how the eleven `array<T>` docblocks that should have been `list<T>` were found: the rule
cannot verify that keys are sequential, so it asked rather than rewrote.

Formatting comes from `@PSR12` with four deliberate departures, each because this codebase had
already decided otherwise and was consistent about it: tabs rather than four spaces,
`declare(strict_types=1)` on line 2 with no blank line above it, `fn(` and `new class(` with no
space before the argument list, and multi-line argument lists left alone so the
`throw X::of(self::class, sprintf(` shape survives.

**The 110-column limit lives in `.editorconfig` and nothing enforces it.** PHP-CS-Fixer has no
line-length rule, and that suits: the right fix for a long line is often a shorter name or fewer
parameters, which is not a decision a fixer can make. A signature past the limit gets folded; a
long string gets wrapped with a leading dot. Neither is a tool's call.

### How it runs

`composer install` points git at `.githooks/`. On commit, the hook formats the files you staged,
re-stages them, and aborts if the risky report finds anything. A file that is both staged and
modified is refused rather than formatted, because formatting the working-tree copy and
re-staging it would pull your unstaged edits into the commit.

`git commit --no-verify` skips all of it, and a clone that never ran `composer install` never had
it. CI is the boundary that actually holds.

---

## Imports

Four tiers, one unbroken block, alphabetical within each:

1. this project — `Meraki\Schema\*`
2. other Meraki packages — `Meraki\*`
3. everything else with a namespace — `Brick\*`, `PHPUnit\*`, `Uri\*`
4. the root namespace — `Countable`, `Stringable`, `Closure`

Nearest-first: the block runs from the code you own to the code you merely use.

Tier 3 deliberately mixes PHP's own namespaced classes with third-party packages. Nothing
distinguishes `Uri\Rfc3986\Uri` (PHP 8.5) from `Brick\DateTime\Clock` (composer) without a
hand-maintained list that would be wrong every time PHP adds a namespace. Merging them makes the
whole classification mechanical, which is worth more than the distinction.

`Meraki/grouped_imports` does this. `ordered_imports` cannot — it sorts the block flat, with no
grouping by prefix — so it is disabled, and must stay that way.

## Compound ternary conditions

A condition containing `&&` or `||` is parenthesised:

```php
$a = ($a === 123 || $a === 456) ? 789 : null;
```

`&&` and `||` already bind tighter than `?:`, so the parentheses change nothing about how PHP
evaluates this. **That is the point.** They are for the reader: seeing where the condition ends
should not require knowing the precedence table.

A single comparison is left alone — `$x === null ? a : b` stays. `===` binds tighter than `?:` in
exactly the same way, so covering every comparison would be more consistent, and it was
considered and rejected: one comparison already reads as one unit, and the rule would have
touched 66 further sites to no benefit. The ambiguity worth spending parentheses on is *where a
multi-clause condition ends*.

## Building strings

**Interpolation where every value is already a string.** `"A minimum {$what} cannot be negative."`
rather than `sprintf('A minimum %s cannot be negative.', $what)` — the format string, the argument
list and the mapping between them are ceremony the reader has to hold.

**sprintf keeps everything else**: mixed types, padding, width, positional arguments, and any
value built by a call. `implode(', ', $names)` reads better as an argument than wedged into a
string, and `sprintf('A field named "%s" already exists.', $name)` stays too — the interpolated
form needs two escapes and reads worse.

**Concatenation is for line wrapping, not for gluing values into text.** Leading-dot continuation
of a long message is house style and makes one unbroken string:

```php
'A rule must say what happens: attach an outcome with then() or else() before '
. 'adding it to a schema.'
```

Joining a value with `.` is not: `"{$this->prefix()}/{$this->property}"`, not
`$this->prefix() . '/' . $this->property`. A heredoc is for text that is genuinely multi-line —
using one for a wrapped message would put real newlines into it.

Both are PHPStan rules rather than fixers, because the deciding question is "is this value a
string", which no token-level tool can answer: `$this->name` might be a `string` or a
`FieldName`. Both exempt anything a double-quoted string cannot hold unchanged — a class
constant, a regex, a literal `$` — and neither ever reports a call that takes arguments.

## No language hacks

A hack is a construct chosen for terseness or speed over saying what is meant.

**The fixer bans** function aliases (`count`, not `sizeof`), `die` for `exit`, `and`/`or`/`xor`
— whose precedence against `?:` and `=` is the canonical PHP gotcha — and `"$x"`/`"${x}"` in
favour of the explicit `{$x}`. It also bans `\count()`: prefixing builtins for opcode-cache
dispatch is exactly the trade this rejects, and is enforced by *not* enabling
`native_function_invocation`.

**Left to judgement**, because each has legitimate uses this codebase relies on:

- **`@` suppression.** Three sites, all justified: `@preg_match($regex, '')` is the only way to
  ask whether a user-supplied pattern is valid, and each converts the suppressed warning straight
  into an exception. A fourth needs an argument.
- **`isset()` versus `array_key_exists()`** — documented at both sites where it matters. A key
  present with a `null` value is a half-filled form, and saying so beats reporting it absent.
- **Short `?:`** — fine for an `array|false` return such as `glob(...) ?: []`, not as a stand-in
  for `??`.
- Bit-twiddling in place of arithmetic, `&$x` as an output channel, `extract()`/`compact()`,
  `array_map(null, ...)` as a zip, and `&&` used as a statement.

**A variable keeps one type.** Where the type genuinely changes, introduce a second variable.

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
$schemaResult->forField('venue')->forConstraint('postalCodeRequired')->bound
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
$address->partNamed('subdivision')   // not part()
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
`mustBeVisitable()`, `allowCountries('AU')`, `allowDuplicates()`.

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
