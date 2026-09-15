# Adding your own field type

A field type defined outside this package is not a second-class one. There is no registry to add
to, no factory to teach, and no interface list to update — you write a class, and everything the
built-in fields get, yours gets.

This page is the whole of what that takes. The example below is
[`examples/custom-field.php`](../examples/custom-field.php), which runs in CI — documentation that
does not run is worse than none, because it is confidently wrong.

## A worked example

An ISBN field. Forty lines, in your own namespace, touching nothing in `meraki/schema`:

```php
namespace Acme\Fields;

use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;
use Meraki\Schema\Field\Constraint;

final readonly class Isbn extends AtomicField
{
    public bool $thirteenOnly;

    public function __construct(public FieldName $name)
    {
        parent::__construct();

        $this->thirteenOnly = false;
        $this->constraints = $this->defineConstraints();   // last: it reads the above
    }

    public function thirteenDigitsOnly(): static
    {
        return $this->with(['thirteenOnly' => true]);
    }

    protected function parse(mixed $value): ?Isbn\Value
    {
        if (!is_string($value)) {
            return null;
        }

        $digits = preg_replace('/[\s-]/', '', $value);

        return preg_match('/^\d{9}[\dX]$|^\d{13}$/', $digits) === 1 ? new Isbn\Value($digits) : null;
    }

    protected function defineConstraints(): Constraint\Set
    {
        return new Constraint\Set(
            new Constraint(
                'isbn13',
                fn(Isbn\Value $v): ?bool => $this->thirteenOnly ? strlen($v->isbn) === 13 : null,
                $this->thirteenOnly,
            ),
        );
    }
}
```

and its value:

```php
namespace Acme\Fields\Isbn;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\ParsedValue;

final readonly class Value implements ParsedValue
{
    public function __construct(public string $isbn)
    {
    }

    public function equals(Equality $other): bool
    {
        return $other instanceof self && $this->isbn === $other->isbn;
    }

    public function __toString(): string
    {
        return $this->isbn;
    }
}
```

That is it. Use it like any other field:

```php
$schema->add((new Isbn(new FieldName('isbn')))->thirteenDigitsOnly());
```

## What you get without asking

| | |
| --- | --- |
| **Immutability** | `AtomicField` is `readonly`, and `readonly` is inherited both ways — your field is sealed whether or not you thought about it |
| **Copy-on-change** | `with()` is what a wither calls; it clones, rebuilds the constraints, and re-checks the default |
| **Shape vs constraint** | a value that cannot be read is *unreadable*, and the constraints are skipped rather than failed |
| **Missing vs unreadable** | two different sentences, kept apart on the result |
| **Defaults** | `defaultsTo()` is checked where it is written, against your constraints |
| **Prefill** | submitted beats prefilled beats default, and the result says which won |
| **Rules and scopes** | your field can be a rule's subject, and every public property is addressable |
| **The result shape** | `given`, `value`, `source`, `shape`, one verdict per constraint |

The default check is the one worth dwelling on, because it catches a mistake you did not write
the guard for:

```php
(new Isbn(new FieldName('isbn')))->defaultsTo('0306406152')->thirteenDigitsOnly();
// InvalidArgumentException: The default for "isbn" does not satisfy its own "isbn13" constraint.
```

Configuration arrives in any order, so a default set *before* the constraint that rejects it has
to fail on the later call. Every wither funnels through `with()`, which is what makes that work
for a field its author never anticipated.

## The four things you must get right

**1. Constructor order.** `parent::__construct()` first, then your own properties, then
`$this->constraints = $this->defineConstraints()` last — because the constraints are built *from*
those properties. `Api\SealedFieldTest` checks this for every field, including yours if you add it
to the sweep.

**2. `parse()` returns your value object, or `null`.** Never a bare scalar, never a class from a
dependency. `null` means *unreadable* and nothing else — absence is settled before `parse()` runs,
so it never receives `null`.

**3. `parse()` never raises.** It runs on attacker-controlled input. An unreadable value is
reported, not thrown; throwing belongs to definition time, where the author can act on it.

**4. Configure with withers, never setters.** `return $this->with([...])`, and the caller keeps
the copy. Writing to a field is a fatal.

## Values, and what they must answer

Every value implements [`Field\ParsedValue`](../src/Field/ParsedValue.php), which extends
[`Comparison\Equality`](../src/Comparison/Equality.php) — so it answers `equals()`.

That is not ceremony. Everything downstream eventually asks whether two values are the same: a
collection deciding whether two rows repeat, a rule deciding whether a field holds what it is
asking about. Without it the answer comes from PHP's `==`, which compares two objects property by
property — reading the *private layout* of whatever class you returned.

Two optional interfaces:

- [`Comparison\Comparable`](../src/Comparison/Comparable.php) — `compareTo(): Order`, if your
  value has an order. Numbers, dates and durations do; addresses and phone numbers do not.
- [`Field\HasParts`](../src/Field/HasParts.php) — if your value is made of named parts, so a rule
  can address one: `#/fields/isbn/value/registrant`.

## Wiring it in

**Building.** There is no extension point on `Field\BuildsFields`, so build yours directly:

```php
$schema->add((new Isbn(new FieldName('isbn')))->thirteenDigitsOnly());
```

If your field needs a clock or the schema's country list, `BuildsFields::$clock` and
`$defaultCountries` are `protected` — use the trait in your own builder and they are yours.

**Rules.** Nothing to do. Your field is a rule subject already.

**Serialization and rendering.** `meraki/schema-json` and `meraki/schema-html` dispatch per type,
so a type they have never heard of needs a case registering with them. `registerFieldRenderer()`
is the documented hook on the HTML side.

## What you cannot do

**Add a constraint to a built-in field.** Every field is `final readonly` and
`defineConstraints()` is `protected`, so adding "not a reserved word" to `Text` means
reimplementing `Text`. Tracked in [ROADMAP.md](ROADMAP.md).

**Add a verb to the fluent matcher.** `Rule\Matcher` is `final` with `equals` and `notEquals`.
You can write a `Rule\Condition` of your own and wrap it — `new Rule\Draft(new MyCondition(...))`
— so the engine is open even though the sentence is not.

**Reach inside a collection item from a scope.** Which row `0` is depends on what was submitted,
so a stored rule naming one would mean a different row on a different request.
