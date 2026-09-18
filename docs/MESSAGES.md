# Messages

What to tell somebody when a field does not accept what they entered — in the language they read,
from a pack you install rather than strings you write.

Optional. A schema with no provider works exactly as it did before messages existed: every result
carries an empty message set and every verdict is unchanged. That is not a courtesy, it is the
constraint the design is built around.

## The shape of it

```php
use Meraki\Schema\Facade;
use Meraki\Schema\Message\Mf2\Mf2Provider;

$provider = Mf2Provider::fromPackage('meraki/schema-language-english');

$schema = new Facade('signup', messages: $provider);
$schema->add($schema->createAddressField('billing', ['AU']));

$result = $schema->validate($data, locale: 'en-AU');

$result->forField('billing')->messages->forPart('postal_code')->first;
// "That is not a valid postcode for the country you chose."
```

Two things arrive at two different times, and the split is the whole design:

- **The provider** is registered on the schema. It is a *source* — built once, holding every
  language it can serve, safe to share — in the same way the clock is.
- **The locale** arrives with the request, because that is what varies. One schema serves a German
  reader and an English one without being defined twice.

## Why the language cannot be part of the definition

A definition is the same in every language. The same data passes or fails identically whoever is
reading, so language is applied to the verdicts rather than fed into the judging.

That has a consequence worth stating plainly: **a missing language can never change an outcome.**
Ask for `de-AT` from a pack that has only English and you get every verdict you would otherwise
have got, with empty message sets. Not an exception, not a different set of failures. If you want
to refuse an unsupported language, that is a decision about the request, and `$provider->supports()`
is there to make it before you validate anything.

It also means **a field validated on its own has no messages**:

```php
$field->validate($value)->messages->isEmpty();   // always true
```

There is no schema to have carried a provider. This is deliberate rather than an oversight — a
field that knew about languages could not be serialised the same way twice.

## Packs are data

`meraki/schema-language-english` contains `en.mfr`, `en_AU.mfr` and nothing else. No PHP, no
autoloader, no class to extend.

That is what makes the cross-implementation promise real: a Rust or JavaScript implementation of
this library reads the same files, so a sentence is written once and rendered identically wherever
the schema is used. It also means a translator never opens a PHP file.

Getting a pack onto disk is **Composer's job**, which is why the provider takes directories:

```php
Mf2Provider::fromPackage('meraki/schema-language-english')   // resolved via Composer
    ->withPack(__DIR__ . '/../resources/lang');              // your own wording, laid on top
```

Fetching, versioning, locking, integrity and caching all already exist there. Doing them inside the
provider would put a network call inside `validate()` — a latency spike, an offline failure mode,
and a supply chain where a moved repository silently changes what your users read. An unpublished or
private pack is `{"type": "vcs"}` in your `repositories`, which is the same "register the repo" step
done where it belongs.

A later pack overrides an earlier one **entry by entry**, so changing one sentence in a published
pack does not mean forking it.

## What a pack looks like

```
# meraki/schema — English
@locale = en

shape.missing = This {$kind} is required.
shape.unreadable = That is not a valid {$kind}.

kind.EmailAddress = email address
part.postal_code = postal code

list.separator = {|, |}
list.lastSeparator = {| or |}

minLength = Use at least {$bound} characters.
postalCodeFormat = That is not a valid {$part} for the country you chose.

Password.minLength = Use at least {$bound} characters. A phrase of a few words is easier to remember.
```

Messages are [ICU MessageFormat 2](https://unicode.org/reports/tr35/tr35-messageFormat.html), which
reached Final Candidate in March 2025 and is part of Unicode LDML. The container is the message
*resource* format the same working group is building: `key = message`, `#` for comments, `@` for
metadata, with `@locale` required.

### Variants come from the file names

`en_AU` is resolved as `en` with `en_AU` laid over it. The chain is derived by dropping subtags —
`zh_Hans_CN`, then `zh_Hans`, then `zh` — and nothing in a file declares what it extends, because a
file that named its own parent would be free to disagree with its name.

Tags are matched case-insensitively and `-` and `_` are interchangeable, because a request carries
`en-AU` from `Accept-Language` and a file is conventionally named `en_AU.mfr`.

The whole of `en_AU.mfr` in the English pack is five lines:

```
@locale = en_AU

part.postal_code = postcode
part.administrative_area = state
part.locality = suburb
part.dependent_locality = locality
part.organization = organisation
```

Nothing restates `postalCodeFormat`, because that message reads *"That is not a valid {$part} for
the country you chose"* and `{$part}` is resolved through the entries above. A variant that stays
this short is a variant working properly.

### The four variables

Always supplied, so a message naming anything else is refused rather than rendering a gap:

| | |
| --- | --- |
| `{$field}` | the field's name as its author wrote it — `billing`, never a label. This library has no labels; a label is what a *form* calls something |
| `{$kind}` | the type, already in the language, via a `kind.*` entry |
| `{$part}` | the part of a structured value, already in the language, via a `part.*` entry |
| `{$bound}` | the limit that applied — a number, a date, or a list already joined |

They arrive **already translated**, which is what lets the formatter stay as small as it is. A
list-valued bound is joined using the pack's own `list.separator` and `list.lastSeparator`, because
MF2's default function registry has no `:list` — a pack could not do that itself even with a
complete implementation.

### The ladder, most specific first

| For | Keys tried, in order |
| --- | --- |
| A shape failure | `EmailAddress.shape.unreadable` → `shape.unreadable` |
| A constraint failure | `Address.postal_code.postalCodeFormat` → `Address.postalCodeFormat` → `postal_code.postalCodeFormat` → `postalCodeFormat` |

So a pack writes one sentence for every `minLength` in the library and overrides it for `Password`,
which is different advice even though it is the same constraint. A rung nobody fills in costs
nothing, and a key nobody wrote produces no message rather than an error.

A field you wrote yourself is looked up under its own class name and falls through to the generic
rung, which is usually right: the meaning is carried by the constraint's name, not by whose field
emitted it. There is deliberately no walk up the parent classes — a field extending `Text` does not
thereby mean what `Text` means.

## Reading the messages

One property, two shapes, and **the field decides which** — not what happened to fail.

```php
$messages = $result->forField('billing')->messages;

$messages->first;       // the one to show when there is room for one
$messages->all;         // every sentence, in reading order
$messages->isEmpty();
count($messages);
foreach ($messages as $said) { ... }
```

A field holding one value gives a [`FlatSet`](../src/Message/FlatSet.php). A field whose value has
named parts gives a [`PartedSet`](../src/Message/PartedSet.php), which adds:

```php
$messages->whole;               // wrong with the value itself — an unreadable address
$messages->parts;               // the parts that have something wrong, in declared order
$messages->forPart('postal_code');
```

Deciding the shape from the *results* would mean an address that happened to fail only on the whole
value came back flat, and a consumer that checked the type once would break on the request that
failed the other way.

`forPart()` refuses a part the value does not have, rather than answering emptily. "No messages" is
a legitimate answer for a part that is fine, so a typo that returned it would be invisible forever.

Every row of a collection carries its own:

```php
$result->forField('lines')->itemAt(0)->forField('sku')->messages->first;
```

## Writing a pack

Create a repository with `.mfr` files at its root, one per locale, and a `composer.json` with no
autoload section. Then:

```
composer require --dev meraki/schema
vendor/bin/schema-lang validate .
```

That is what stands in for a compiler when a package contains no code. It reads the vocabulary off
this library's own classes, so it cannot go stale, and it catches:

- a file that is not a valid resource — a line that is not `key = message`, a missing `@locale`, a
  key defined twice;
- a `@locale` that disagrees with the file name, which would make the file unreachable;
- a variant with no base — `fr_CA.mfr` claims by its name to be a variation of `fr`;
- a message using anything the formatter does not implement;
- a key this library never asks for — a typo, or wording for a constraint that was renamed;
- a message naming a variable nothing will fill, such as `{$minimum}` where the library supplies
  `{$bound}`. **This is the one a translator is most likely to write and the hardest to notice,**
  because the sentence reads perfectly until it runs.

`schema-lang keys` lists every key a pack may define and the variables each may name.
`schema-lang missing <dir>` reports coverage, which is a report rather than a verdict — a pack
covering ninety constraints out of a hundred is worth shipping.

## The MF2 subset, and why it is small

There is **no MF2 implementation for PHP**. The `intl` extension binds MessageFormat *1*; MF2 lives
in `icu::message2`, which it does not expose. So [`Formatter`](../src/Message/Mf2/Formatter.php)
renders the subset the packs actually use: literal text, the four escapes, `{$name}` placeholders,
and `{|quoted literals|}`.

Everything else is **refused, loudly, at load time** — selection, declarations, function calls,
markup, options. That is the discipline that makes the whole thing disposable: the class goes when a
real implementation arrives, and the swap is only safe if the subset behaves *identically* to real
MF2 for every message that ships. Leniency is what would break it. A formatter that quietly ignored
`.match` would let packs accumulate that work today and fail the day a real parser lands — on a
user's form, months later, in a language nobody on the team reads.

The literal is in the subset for one concrete reason: `list.separator` is `", "` *including the
space*, and a `key = value` file cannot carry a trailing one. MF2's own answer is the quoted
literal, so supporting it means the packs written today are correct MF2 rather than something that
happens to work here.

When a real library lands, `Formatter` becomes a thin adapter or disappears.
[`Mf2Provider`](../src/Message/Mf2/Mf2Provider.php) and
[`Mf2Translator`](../src/Message/Mf2/Mf2Translator.php) — lookup, the ladder, the vocabulary — are
untouched, which is the whole point of splitting them.

## Using something other than MF2

[`Message\Provider`](../src/Message/Provider.php) and
[`Message\Translator`](../src/Message/Translator.php) are the contract. MF2 is one implementation of
it, shipped because a schema with no messages out of the box is a worse default — not because it is
required.

```php
interface Provider
{
    public function supports(string $locale): bool;
    public function forLocale(string $locale): Translator;   // Silence when it cannot
}

interface Translator
{
    public string $locale { get; }
    public function forShape(Field $field, Field\ShapeValidationResult $shape): ?string;
    public function forConstraint(Field $field, Field\ConstraintValidationResult $constraint): ?string;
}
```

A translator returns *strings*; the core does the grouping. That is deliberate — putting it the
other way round would make every provider re-implement flat-versus-parted, and the first one to get
it wrong would be indistinguishable from one that simply had less to say.

`null` from a translator means "I have no wording for this". It is a real answer, not an error.

## What is not serialised

**Nothing.** A schema document carries no messages and no locale.

Message keys are derived from constraint names, and those are already in the document — `minLength`,
`postalCodeFormat`. A reader holding any pack for any language can render a schema it has never
seen, with nothing extra transmitted. That is the selling point, and it costs zero bytes.

A locale in the document would be worse than redundant: it would pin a definition to an audience,
and the schema is not language-specific. The locale is the reader's runtime choice.

The one gap: a **custom** field or constraint has a key no published pack knows. Today that falls
back to no message. Carrying optional inline fallbacks in the document is on
[ROADMAP.md](ROADMAP.md).
