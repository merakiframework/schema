# Cookbook

The things a form actually needs, one snippet each. Every one of these was run against the current
API before it was written down.

For the linear "install it and write your first schema" path, see [the README](../README.md). For
the whole surface of a field, [API.md](API.md). For longer, runnable versions of several of these,
[../examples/](../examples/).

- [Defining and validating](#defining-and-validating)
- [Required, optional, and a default](#required-optional-and-a-default)
- [Branching on a choice](#branching-on-a-choice)
- [Ignoring the branch nobody chose](#ignoring-the-branch-nobody-chose)
- [Showing people what they typed](#showing-people-what-they-typed)
- [Why a field failed](#why-a-field-failed)
- [Every failing field](#every-failing-field)
- [Addresses, money, cards — and their parts](#addresses-money-cards--and-their-parts)
- [Repeatable rows](#repeatable-rows)
- [Comparing two fields](#comparing-two-fields)
- [A range deciding a field](#a-range-deciding-a-field)
- [Several conditions at once](#several-conditions-at-once)
- [Prefilling from a stored record](#prefilling-from-a-stored-record)
- [Rendering before anything is submitted](#rendering-before-anything-is-submitted)
- [Was this required by a rule, or by me?](#was-this-required-by-a-rule-or-by-me)
- [Error messages in the reader's language](#error-messages-in-the-readers-language)
- [One region for the whole form](#one-region-for-the-whole-form)
- [One field, no schema](#one-field-no-schema)

---

## Defining and validating

```php
$schema = new Facade('signup');

$schema->add(
    $schema->createTextField('username')->minLengthOf(3),
    $schema->createEmailAddressField('email'),
);

$result = $schema->validate((object) $input);

$result->anyFailed();
```

**Build, configure, then add.** A field is immutable, so every configuration method hands back a
copy — `$field->minLengthOf(3);` on its own line configures nothing.

**Input is an object, not an array.** `json_decode($body)` already gives you objects; it is the
`true` second argument that does not.

## Required, optional, and a default

```php
$schema->add(
    $schema->createTextField('nickname')->makeOptional(),
    $schema->createEnumField('theme', ['light', 'dark'])->defaultsTo('light'),
);

$theme = $schema->validate((object) [])->forField('theme');

$theme->value->case;     // 'light'
$theme->source->name;    // 'Default'
```

Fields are required unless you say otherwise. A default is a constant *you* wrote — it serialises
with the schema and is the same for everyone. One belonging to a particular user is a
[prefill](#prefilling-from-a-stored-record).

`$source` is `Submitted`, `Prefilled`, `Default` or `None`, so a form can mark a value it filled
in for somebody differently from one they typed.

## Branching on a choice

The commonest shape there is: pick a contact method, and only that method's field is required.

```php
$schema->add(
    $method = $schema->createEnumField('contact_method', ['email', 'phone']),
    $email = $schema->createEmailAddressField('email')->makeOptional(),
    $phone = $schema->createPhoneNumberField('phone', ['AU'])->makeOptional(),
);

$schema->addRule(
    $method->when()->equals('email')
        ->then($email->makeRequired())
        ->else($phone->makeRequired()),
);
```

Author both fields **optional**, then let the rule require whichever one applies. Both branches
live on one rule, so they cannot drift apart the way two rules with hand-inverted conditions do.

**An outcome is the field, configured.** You call the field's own withers and the rule records
what changed — so anything a field can do is a rule outcome, including `mustBeAccepted()` on a
boolean or `maxValueOf()` on a number:

```php
->then($terms->makeRequired()->mustBeAccepted())
```

## Ignoring the branch nobody chose

Requiring the right field is half of it. The other half is what to do with whatever is sitting in
the field they *didn't* choose:

```php
$schema->addRule($method->when()->notEquals('email')->thenIgnore($email));
```

`thenIgnore()` discards the submitted value for that field, so it validates as though nothing was
sent — stale text left in a hidden input cannot fail a form the user has correctly filled in.

It is the one outcome with a named verb, because ignoring is about *this request* rather than
about the field's definition.

## Showing people what they typed

```php
$field = $result->forField('age');

$field->given;   // 'abc' — exactly what was submitted, unchanged
$field->value;   // null  — nothing valid could be read
```

**`$given` is what to echo into a form you are redrawing.** `$value` is always valid or nothing,
so it never hands back input the field could not read.

## Why a field failed

```php
$field = $result->forField('bio');

$field->wasMissing();       // nothing arrived for a required field
$field->wasUnreadable();    // something arrived that is not this kind of thing

$failed = $field->getFailedConstraints()->getFirst();

$failed->name;    // 'minLength'
$failed->bound;   // 10  — the limit that applied, ready to interpolate
$failed->part;    // null, or 'postal_code' for part of a structured value
```

**Shape and constraints are different questions.** If a value could not be read at all, the
constraints are *skipped* rather than failed, so a report names one problem once instead of once
per constraint.

## Every failing field

```php
foreach ($result as $field) {
    if ($field->anyFailed()) {
        echo $field->field->name;
    }
}
```

`$result` iterates its fields, and each one is the *effective* definition — including any change a
rule made on this request.

## Addresses, money, cards — and their parts

```php
$schema->add($schema->createAddressField('billing', ['AU']));

$schema->validate((object) [
    'billing' => (object) [
        'line1' => '1 Denham St',
        'locality' => 'Rockhampton',
        'postal_code' => '4700',
        'country' => 'AU',
    ],
]);
```

A structured value arrives as **one record**, not as separate fields, and its failures say which
part they concern:

```php
$field = $result->forField('billing');

$field->value->postalCode;                            // the parsed value, part by part
$field->getFailedConstraints()->getFirst()->part;     // 'postal_code'
```

So a renderer can attach each error to the right input instead of piling them above the fieldset.
Part names are the same vocabulary the input used.

## Repeatable rows

```php
$schema->add($schema->createCollectionField(
    'lines',
    $schema->createTextField('sku')->minLengthOf(3),
    $schema->createNumberField('qty')->minValueOf(1),
));

$lines = $result->forField('lines');

foreach ($lines->failedItems as $item) {
    echo $item->key;                        // 0, 1, … or the name it was submitted under
}

$lines->itemAt(0)->forField('sku')->value;
```

A collection fails on two axes — the list is too short, *and* row 3 is wrong — and both count
towards `anyFailed()`. `minCountOf()`, `maxCountOf()` and `allowDuplicates()` bound the list
itself.

## Comparing two fields

```php
use Meraki\Schema\{PartScope, ValueScope};

// is the shipping address the billing address?
$schema->addRule(
    $shipping->when()->notEquals(ValueScope::of('billing'))
        ->then($confirmDifferent->makeRequired()),
);

// are they at least in the same country?
$schema->addRule(
    $schema->when(PartScope::of('shipping', 'country'))
        ->notEquals(PartScope::of('billing', 'country'))
        ->then($customsNote->makeRequired()),
);
```

Both sides are read the same way, so a whole address is compared as an address and a part as a
part.

## A range deciding a field

```php
$schema->addRule($age->when()->isLessThan(18)->then($guardian->makeRequired()));
```

**The inclusive ones say so in their names.** `isAtLeast` and `isAtMost` include their bound;
`isGreaterThan` and `isLessThan` do not; `isBetween(18, 65)` includes both ends, because it *is*
the first two together.

The ordered matchers belong to numbers, dates, times, durations and money. A text field's matcher
does not carry them at all — `$notes->when()->isAtLeast(3)` will not compile.

## Several conditions at once

```php
$schema->addRule(
    $schema->allOf(
        $country->when()->equals('AU'),
        $total->when()->isAtLeast(1000),
    )->then($abn->makeRequired()),
);
```

`anyOf()` is the other one. They take *conditions*, never finished rules, so there is exactly one
set of outcomes and no question about whose fire.

## Prefilling from a stored record

```php
$result = $schema->validate(
    $submitted,
    prefilledWith: (object) ['email' => $user->email],
);

$result->forField('email')->source->name;   // 'Prefilled'
```

Submitted beats prefilled beats the authored default. **A prefill is never written to the schema**,
which is what makes "a serialised schema can never contain user data" true by construction rather
than by discipline.

Pass `policy: PrefillPolicy::Unchecked` if a value you looked up should not have to satisfy the
field's constraints — a stored phone number that predates a rule you have since tightened.

## Rendering before anything is submitted

```php
$result = $schema->resolve();

$result->forField('username')->status->name;   // 'Pending'
```

`resolve()` reads values without checking them, which is what a form being drawn for the first
time actually is. `resolve($data)` does the same with values in it.

## Was this required by a rule, or by me?

```php
$result->forField('extra')->wasAlteredByRule();   // true
```

A renderer needs to tell those apart: a field a rule made optional should not look the same as one
the author wrote that way. `$field->appliedOutcomes` has the detail, including
`$applied->outcome->changes` for exactly what was set.

## Error messages in the reader's language

```php
use Meraki\Schema\Message\Mf2\Mf2Provider;

$schema = new Facade('signup', messages: Mf2Provider::fromPackage('meraki/schema-language-english'));

$result = $schema->validate($input, locale: 'en-AU');

$result->forField('username')->messages->first;
// "Use at least 3 characters."

$result->forField('billing')->messages->forPart('postal_code')->first;
// "That is not a valid postcode for the country you chose."
```

The provider is registered once; the **language is part of the request**. A field whose value has
named parts groups its messages by part; everything else gives a flat list.

Entirely optional — with no provider, every result carries an empty message set and nothing else
changes. See [MESSAGES.md](MESSAGES.md).

## One region for the whole form

```php
$schema = (new Facade('booking'))->for('AU');

$schema->createAddressField('billing');            // restricted to AU
$schema->createPhoneNumberField('mobile');         // ditto
$schema->createAddressField('shipping', ['NZ']);   // an explicit list still wins
$schema->createAddressField('other', []);          // and [] means free-form
```

Deliberately **not** `Money`: a currency does not follow from a region. A country may use several,
and the euro spans twenty.

## One field, no schema

```php
$field = (new Field\EmailAddress(new FieldName('email')))->allowDomains('example.test');

$field->validate('kim@elsewhere.test')->getFailedConstraints()->getFirst()->name;
// 'allowedDomains'
```

Useful in a test, or for one value on its own. The one thing you do not get is
[messages](#error-messages-in-the-readers-language) — there is no schema to have carried a
provider.
