# Changelog

**Generated from the commit history** by `php tools/changelog.php`. Do not edit by hand — a
hand-kept changelog is a second place to write down what a commit already said, and the two drift
the first time somebody is in a hurry.

Entries are not sorted into "Added / Changed / Fixed". That classification lives in the author's
head at commit time, and a script inferring it from a verb produces confident nonsense. Each entry
is a commit subject, with the body kept because the body is where the reasoning is.

## Unreleased

### Document the pack install that actually resolves

`af90bed6` · 2026-10-02

`composer require meraki/schema-language-english` fails: the pack has no
tags, so Packagist serves only dev-main and a default minimum-stability
refuses it. Both places this library tells somebody to run it now name the
constraint, and the README says why it is dev-main rather than leaving a
reader to wonder.

### Sixteen cases that could not fail, and two that could not detect

`aa4bac0d` · 2026-10-02

MalformedCompositeInputTest builds a four-field schema, all required, and
each of its four "fails rather than raises" tests submits one field. The
other three are absent, so anyFailed() is true whatever the value under
test does -- a *valid* price gave true just as readily. Four tests times
four provider rows is sixteen assertions that asserted nothing. The
"rather than raises" half still worked, because an exception errors the
test; the "fails" half never did. They now assert against the field.

Two tests named for memoisation compared by value, so neither could see
the thing it was named for:

  PasswordTest::the_measurement_is_taken_once_and_only_when_asked did
  assertSame($resolved->entropy, $resolved->entropy). $entropy is ?int, so
  deleting the memo and re-running zxcvbn on every read would have passed.
  It now asserts both halves: hasMeasured is false after validate(), true
  after the first read, and poisoning the memo proves the second read does
  not re-measure.

  SchemaMessagesTest::messages_are_rendered_once_rather_than_held_as_a_translator
  did the same with an array. It now asserts what the name says -- every
  sentence is already a string, and nothing the set holds is a Translator
  or a Provider.

(My first attempt at the second one laid a second pack over the provider
and asserted the sentences did not move. withPack() is a wither, so that
discarded its own result and would have replaced one tautology with
another.)

Two duplicate data-provider rows, one of which hid a gap. EmailAddress had
'nothing after the at' and 'no dot is fine, no domain is not' both holding
'kim@' -- the second name describes a case the data never tested, and
kim@intranet is in fact accepted, which nothing asserted. Now a test of its
own. NumberTest had '(float) no integral part' => [.456] beside
'(float) positive with leading 0' => [0.456]; PHP parses both to the same
double, and the string form already covers it.

Also: tests/Field/FileTest.php declared namespace Meraki\Schema\Field\Type,
left over from the pre-2.0 layout, which PSR-4 cannot map -- composer
skipped the class on every dump-autoload. And bin/schema-lang and
.githooks/pre-commit were both committed non-executable. The hook one
mattered: composer install points core.hooksPath at .githooks, and git
silently skips a hook it cannot execute, so every contributor not on
Windows had it configured and never running.

### Two samples that could not run, and five sentences left behind

`018878b1` · 2026-10-02

A documentation audit against the running library: every snippet extracted
and executed, every named class, method and constraint grepped, every link
and anchor resolved, every count recomputed. Most of it held. What did not
falls into two piles.

Two samples were copy-paste fatal:

  COOKBOOK documented `PrefillPolicy::Unchecked`, which has never existed --
  the cases are Checked and Trusted. A reader following it gets an Error,
  and the same page's own neighbours (API.md, UPGRADING.md) both say
  Trusted for exactly this.

  FIELD-API's worked Boolean::parse() returned null from a method typed
  `: Value`, on precisely the input the branch exists for. That is a
  TypeError, in the page a new field author is told to read first, nineteen
  lines above the sentence "It returns a value, or raises MalformedValue.
  There is no null." The abstract signature three paragraphs down had the
  same `?` on it.

The second pile has one cause. The recent churn landed in the code samples
and left the prose around them behind, so five pages still told the reader
the message provider is registered on the schema while the snippet three
lines away passed it to validate(). DESIGN.md was the worst of them: not a
stale call, but the argued rationale for a design that no longer exists.

The rest, each verified by running it rather than by reading:

  - API.md said Name has no configuration "at all". It has minLengthOf()
    and maxLengthOf(). It was the one wrong row in a nineteen-row table
    that is otherwise correct to the method name.
  - FIELD-API said a collection row keeps "a position for a plain list".
    A positional list is refused; that is why rows are named.
  - FIELD-API had the result hierarchy backwards -- Collection\Result and
    Password\Result both extend ResolvedField, so the contrast it drew
    between them does not exist.
  - ROADMAP said `value` holds the raw input when nothing could read it.
    It holds null; `given` is the raw input. Three other pages say so.
  - LIMITATIONS' heading said records "ignore a key they do not know",
    five lines above the body correctly saying they raise.
  - LIMITATIONS cited whenEquals() in a current-behaviour section; that is
    1.x. COOKBOOK cited getRequiredFields(), which belongs to
    commerceguys/addressing and is on no public type here.
  - Three broken anchors: #rough-edges (no such heading anywhere) and
    #transformed--dropped-and-why twice.
  - ROADMAP counted 23 fields where 18 extend AtomicField, and still had
    otherwise() as future work -- it shipped as else().
  - UPGRADING told translators to update their `.mf2` packs. The extension
    is `.mfr`, including in the loader's own glob.
  - docs/README listed 12 of the 14 examples.

And the entry that was simply missing: UPGRADING said nothing about
FieldName. Three public behaviours changed in a62aebc and none of them
reached the guide -- equals() stopped folding case, collidesWith() is where
that went, and Field\Set::add() now refuses a case-insensitive collision.
That last one breaks schemas that build today. It is the first thing a port
author would have hit and the last place they would have looked.

### A password's value is what the field made of it, like every other field

`bfb6de31` · 2026-10-02

Password::validate() wrote `$parsed ?? $raw`, so a secret the field could
not read came back out of `$value` as the submitted int or array. Every
other field answers null there. So did Password's own resolve(), forty
lines above.

ResolvedField::$value states the contract in as many words -- "Never the
raw input: that is $given, and having both mean it made the type unusable"
-- and AtomicField carries a comment on the exact line explaining why it is
`$parsed` and not `$parsed ?? $raw`. One field in nineteen disagreed, and
it disagreed with itself between its two entry points.

Nothing caught it because nothing asked the question of every field at
once, so Api\ValueObjectTest now does, over both entry points. That sweep
is also what makes the fix worth more than the one line: a twentieth field
overriding validate() cannot reintroduce this quietly.

While there: that method had a fourth private copy of rawFor(), the method
whose own docblock says "It was three answers, and they disagreed". It is
identical today only because absentValue() returns null for an atomic
field. Now it calls rawFor().

Four members deleted, none of which could run:

  Textual::textAt()            -- lost its last caller to textLinesAt()
  CreditCard\Value::isEmpty()  -- unreachable; the constructor refuses a
  Address\Value::isEmpty()        card or address with nothing in it, so
                                  both can only ever return false
  Field\Set::exists()          -- orphaned when collidesWith() replaced it

The two isEmpty() are the interesting pair: they were correct when written
and were made unreachable by a later commit tightening the constructors,
which is exactly the shape that survives review. Checked against the rule
verb before deleting -- Condition\Emptiness reads Countable, arrays and
Stringable, never a value's own isEmpty() -- and against both ports.

Docblocks the adjacent code refuses:

  - Field\Definition said `src` is "clean to level 8 with no ignore
    entries". Measured: 50 errors at level 8, 47 at level 7, and
    phpstan.neon sets level 6 with three ignores. That sentence is the
    justification for initially() existing, so overstating it is the one
    place it matters.
  - CreditCard cited expiryFormat twice as the thing that reports an
    unreadable expiry. That constraint was removed last week; the value
    refuses such an expiry outright now.
  - Textual rested its whole "why this is not checked when the rule is
    written" argument on ValueScope::of('email', 'domain') "which works
    perfectly". It is refused at addRule() since EmailAddress dropped its
    parts. The argument still holds for address parts, so it now uses one.
  - PhoneNumber\Value promised to raise "if either half is missing". A
    missing number is deliberately kept so numberRequired can name the box.
    The same docblock had a @throws inserted between a @param and its
    continuation line, so a clause about default countries had attached
    itself to the wrong tag.

And my own, from the rename two commits ago: Definition's new docblock said
the two Definitions "never appear in the same file", which BuildsFields
disproves in that same commit -- and Definition itself uses the trait. A
disambiguation note a reader can falsify in the next file is worse than
none.

Definition::resolve() and ::validate() now say that BrokenInputContract
escapes them. In prose rather than as @throws: the raise happens in a value
constructor reached through a closure, which PHPStan cannot trace, and it
reports an unused @throws type. Suppressing that is against house rules and
the reader needs the fact either way.

Found by three parallel audits of src/, docs/ and the release mechanics.
Every finding above was re-verified here by running it.

### Update history

`0a96aff6` · 2026-10-02

### Facade is now Definition

`56df5cd9` · 2026-10-02

A straight rename of the class you build a schema with. Same constructor,
same methods, same behaviour, no alias -- 2.0 is breaking already and a
shim would only let the old name survive into code written after it was
wrong.

`Facade` named a pattern rather than a thing, and not even accurately: a
facade simplifies a subsystem you could still use directly, and there is no
schema underneath this one. It *is* the schema.

The codebase had already voted and nobody had counted. Every port wrote
`Facade $schema` -- eight occurrences across two files, the type saying one
thing and the variable another every single time. The result class was
SchemaValidationResult, never FacadeValidationResult. NothingToValidate
says theSchemaHasNoFields. The docs say "schema" 445 times and "facade" 35.
And this was the only major class in src/ with no class docblock, which is
the tell: a pattern name leaves nothing to explain.

Definition names the distinction the whole library turns on. A definition
is what the author wrote; a result is what one request produced; nothing
per-request may touch the first. Every invariant in docs/DEVELOPER.md is
that sentence applied somewhere, and the class now says which side of the
line it sits on. It also reads correctly at the call site, where a
definition judging data against itself is exactly what happens.

Schema was the other candidate and would have stuttered --
Meraki\Schema\Schema. The convention here is already a structural class
name with a domain variable, as in `Field\Set $fields` and
`Rule\Set $rules`, so `Definition $schema` is the shape this codebase
already uses rather than a compromise.

It does collide with Field\Definition, the trait holding a field's
configuration half, and that is worth being precise about rather than
waving away. The trait is used by exactly two files, AtomicField and
Field\Collection, and neither mentions the schema. Measured rather than
assumed: the overlap is zero, and structurally it stays zero, because one
is for field implementations and the other is the thing that holds them.

There was exactly one place it genuinely bit, and the blanket rename found
it: BuildsFields had a sentence reading "a trait rather than methods
written directly on the Definition, for the same reason {@see Definition}
is one" -- two different classes, one sentence. It now qualifies the first
and says outright that the bare name means the trait inside this namespace.
Field\Set had a comment with the same ambiguity and now says "the schema".

What is deliberately NOT renamed: every reference to the class as it was.
The 1.x reproducers in LIMITATIONS, the removed `Facade::whenAllMatch()`
and `Facade::traverse()` in ROADMAP, the before-halves of the UPGRADING
examples, and the B8 write-up. Those describe code that really was called
Facade, and rewriting them would make the history wrong to make the
present tidy. The blanket pass caught all eleven and they were put back by
hand.

The new class finally has a docblock, including why it is not the other
Definition.

Tests renamed with it: SchemaFacadeTest -> SchemaDefinitionTest,
FacadeValidateInputTest -> DefinitionInputTest, and the `facade` PHPUnit
group -> `definition`.

### Update history

`c79b9cc0` · 2026-10-02

### A guide for working on the core, not with it

`8d4b91e8` · 2026-10-02

The docs covered using this library and writing a field for your own
project. Nothing covered changing this package, which left a contributor
reading src/ in whatever order they happened to open it and inferring the
rules from the code that follows them.

docs/DEVELOPER.md is that missing piece. Four things, in the order somebody
new needs them:

A map. What each namespace owns, and a "go here when" column, so finding
where a change belongs does not require having read the whole tree first.

One request, start to finish. Eight numbered steps from Facade::validate()
to a rendered message, with a schema small enough to paste and run, and a
table of where to put a breakpoint for each stage. This is the thing most
worth having in your head and it was nowhere.

The invariants, twelve of them, each written as a plain statement with why
it exists and the test that catches you breaking it. They were all true
before today and all discoverable only by tripping over them -- a field
that writes to itself, a parse() that returns null, a value reporting a
part nobody can submit. Writing them down also found four citations I had
to correct: BaselineTest is about defaults being strict rather than about
shape ordering, and FacadeValidateInputTest is about reading input off
objects rather than about not raising. Invariant 12, no sideways sharing
between sibling fields, has no automated check at all, and now says so
rather than implying one.

A section on things that look like mistakes. Long docblocks, duplicated
sibling fields, no base class for values, no PHPStan baseline. Each is a
decision, and each is the kind of thing a well-meant pull request tidies up
before anybody explains why it is there.

The language is deliberately plainer than the rest of the docs. A glossary
defines the twelve words this codebase uses precisely -- shape, verdict,
part, port -- because every one of them is an ordinary English word used in
a narrow sense here, and guessing wrong about any of them makes the rest
unreadable.

Every claim in it was checked by running it rather than by remembering.
The walkthrough's schema runs; the verdict table is its real output,
including the two different reasons a constraint comes back Skipped, which
is a better illustration than the one I first wrote and had wrong.

Also: the README said ten examples. There are fourteen.

### Update history

`f731447d` · 2026-10-02

### A key nobody declared is a broken contract, not a verdict

`727062c5` · 2026-10-02

Two commits ago these were ignored; one commit ago they became an
unreadable value. Both were wrong in the same way, and the second only
less so: a verdict is a judgement about a request, and this is not one.

Keys are vocabulary, not data. In every protocol something maps a payload
onto them -- a form handler, a JSON client, a producer on a queue -- and
there is no submitter action anywhere that produces `ammount`. It is a
line of code that is wrong on every request, for every submitter, until
somebody edits it. `unreadable` tells the only person who can fix it
nothing and tells everybody else something untrue.

And a verdict already decides an audience: it says *this is reportable to
whoever submitted*. This library cannot see whether that is a person, a
peer implementation or a deploy that went out wrong -- one wants a 500,
one a 400 naming the key, one an alert. Returning a verdict would be
answering a question it cannot see, which is the kind of answer a core has
no business giving. BrokenInputContract escapes instead, carrying
$unknownKeys and $valueClass so a port answers in its own protocol without
parsing English.

Where the line sits, and why not further: a declared key holding something
unusable stays a MalformedValue, absorbed and rendered from a message
pack. The temptation is to extend this to types -- nobody *types* an array
either -- but that is attribution, not severity. A JSON client chooses the
type of what it sends; a form port receives strings and chooses the type
itself. Which of them got it wrong depends on the protocol, and this
library does not know the protocol. Where the mistake can be pinned on the
builder without knowing the port, it raises; where it cannot, it reports.
Keys can; types cannot.

File stops accepting a $_FILES entry. Its tmp_name, error and full_path
were tolerated last commit on the grounds that a port should not have to
strip them, which had it backwards: a temporary path is where one
language's web SAPI put the bytes and an error code is that SAPI's verdict
on the transfer, so a schema accepting them would describe a PHP web
request rather than a file. A port in another language has none of them.
Taking the three out of an upload is exactly a port's job.

The constructors now document their records as object shapes, which is
worth having and does not finish the job. PHPStan catches a missing
required key and a declared key of the wrong type, at the call site. It
does not catch a stray key: an object shape is a floor and not a ceiling,
so object{currency: string, ammount: string} satisfies
object{currency?: string, amount?: string}. There is no sealed-object
syntax, and @param object would trade the two checks that work for the one
that cannot. So the runtime check is the only strap, not a second one.

Fixture changes that are not cosmetic. Several providers held bare scalars
and the test cast them -- `(object) 12.50` is `{scalar: 12.50}`, a shape
nothing sends, which would now raise for its key rather than fail for not
being money. They say what arrives instead, so the assertion is about the
library rather than about the cast.

docs/API.md's "What raises, and what does not" said every raising mistake
is found at boot. This is the first that cannot be: there is no record to
inspect until a request carries one. The section says so rather than
quietly acquiring an exception.

docs/EXTENDING.md gains the obligation this puts on a port, stated once
for every protocol: map, do not forward. Handing over a decoded body makes
a remote party the co-author of your key vocabulary, and then its typo is
your exception -- which is the bug being pointed at, not the typo.

### Update history

`b4629c7d` · 2026-10-02

### A record refuses a key it does not know

`a7753d82` · 2026-10-02

Money, CreditCard, PhoneNumber and File dropped an unrecognised key inside
the record. Address has refused since its parts were renamed; the other
four now do the same.

Ignoring the key means reporting whatever its absence breaks. `ammount`
was answered with "enter an amount"; `securty_code` left the security code
absent and reported *that*. In both, the one key that was actually wrong is
the only thing nobody is told, and data somebody meant to send is gone
without a word. Address's own comment has said why since the rewrite:
naming the symptom hides the stale key that caused it.

This had already bitten once. `e164` and `local_part` stopped being parts
two commits ago, so a port still sending them would have been told its
phone number or email was incomplete -- the renamed-part failure Address
was made strict to prevent, arriving on the fields that were not.

Each value does its own check against its own partNames(), duplicated
rather than shared: sibling field types stay independent, and the accepted
keys being literally the declared parts is worth more than three lines
saved.

File is the apparent exception and is not one. A $_FILES entry carries
tmp_name, error and -- since PHP 8.1 -- full_path beside the three parts
this library reads, and a port handing one straight over should not have to
strip them first. Those six are named in UPLOAD_KEYS: declared and ignored,
which is a different thing from a key nobody declared.

What this does not do is say which key it was. The verdict is `unreadable`
and the message naming the stray key is on the MalformedValue, which the
request path absorbs by design. `$resolved->given` still holds exactly what
was submitted, so it is recoverable; it is not handed over. Said plainly in
docs/LIMITATIONS.md rather than left to be discovered.

Both halves are pinned in MalformedCompositeInputTest -- the stray key
refused, the same payload without it readable -- so the test cannot pass by
refusing everything.

### Say what the code does, in the five places it did not

`5fd525da` · 2026-10-02

Each of these was found by reading the code beside the claim rather than
the claim on its own, which is the only way this kind of rot gets found:
every one of them reads perfectly until you check.

  - Facade's scope example named `PropertyScope::of('age', 'min')`. The
    property is `minValue`. Anyone copying it got a scope refused at
    addRule(), which is the good failure, but still a documented call that
    has never worked. (Rode along with the previous commit, same file.)

  - CreditCard pointed at `Value::fromInput()`, gone since the value
    objects took the whole record. docs/FIELD-API.md showed the same
    method in the worked example of how to write parse(), so somebody
    following the extension guide wrote a field that could not compile.
    Replaced with what the built-in fields actually do.

  - Collection described a `type` constraint. Readability is the shape
    now -- the precondition every constraint depends on, not one more rule
    among them -- and `type` is not a constraint on anything.

  - docs/LIMITATIONS.md demonstrated array payloads and addBooleanField(),
    both of which now raise a TypeError, in the section explaining what
    this library accepts.

  - docs/EXTENDING.md listed reaching into a collection row as impossible.
    Rows are keyed by name, so a stored rule naming one means the same row
    on every request; it was positional indices that could not be stored.
    Replaced with the limitation that is actually still there -- a part of
    a part.

While rewriting the LIMITATIONS section on records I checked rather than
assumed, and the behaviour it described is split: Address refuses a key it
does not know, and Money, CreditCard, PhoneNumber and File ignore it. That
is documented here as it stands and is being fixed next.

Also in: UPGRADING sections for the three breaking changes behind this
batch, and docs/API.md's per-field constraint tables, which had not caught
up with the new *Required names or with expiryFormat going away.

### Wording arrives with the request, not with the schema

`da967d65` · 2026-10-02

The locale always did. The provider sat on Facade's constructor beside the
fields, which made it half a property of the definition -- and the half
that mattered. A schema built in a service container was stuck with
whatever pack that container had. Serialising a schema dropped the provider
silently. Swapping the wording for one caller meant rebuilding the schema.

None of that is a fact about a definition. The same data passes or fails
identically in every language; this library's own sentence for it is
already written two lines above the constructor it contradicted.

    $schema = new Facade('signup');
    $result = $schema->validate($data, locale: 'en-AU', messages: $provider);

Facade::$messages goes with it, so there is no longer a copy of a schema
that could be carrying a different one -- which is what the clone test now
asserts instead.

resolve() takes neither. It reaches no verdict, and only a failure has
anything to say, so a provider there would have been a parameter that
could not change an outcome. Said in its docblock rather than left to be
discovered.

Everything else holds: no provider, an unsupported tag, or no tag at all
still leaves every verdict exactly as it was and every message set empty.

### A value reports the parts it is submitted with

`a4de8da1` · 2026-10-02

Two values reported a *reading* of themselves instead of their inputs.

EmailAddress\Value returned `local_part, domain` and took `kim@example.test`
-- one box on a form, one string in a payload. Neither half was ever an
input, so `#/fields/email/value/domain` resolved against something nobody
had sent, and the field's messages went into a PartedSet keyed by names no
submitter has seen. It stops implementing HasParts. Both halves are still
readable as properties, and a rule about a domain was always written as
`matches('/@example\.test$/')` rather than through a part, so nothing a
caller could do is lost.

PhoneNumber's half of this came with the previous commit: `number, country`
rather than `country, e164`.

Nothing in the suite broke, which is the finding rather than the relief --
2172 tests and not one of them noticed. So the rule is pinned twice in
Api\StructuredTypeTest, as a sweep rather than as two cases:

  - every part a value declares is a key its payload carries. Not the
    converse: a File's upload also carries tmp_name and error, which are
    plumbing rather than boxes anybody fills in, and a part missing from
    that direction is not a mistake. The direction that burns a port is a
    declared part nothing can submit.

  - only the five record-shaped fields report parts at all, asserted over
    the whole field inventory, so the next value that reports a derived
    reading fails here rather than in a port.

The test for whether something belongs in partNames(): could a submitter
fill this in on its own? If it is derived from the parts rather than one of
them, it is a method on the value -- toE164(), __toString(). That is now
written on the HasParts interface, where the next person to implement it
will read it.

### A required part that was not sent names itself

`9155bbbe` · 2026-10-02

The invariant, across every record-shaped field: a required part that is
absent, or present and null, fails that part's own constraint and names the
part. A part that was *sent* and holds nothing is unreadable instead -- `''`
was a decision somebody made, and reading it as absence would let whitespace
satisfy a requiredness check. An optional field absent or null is skipped,
which is the qualifier that makes it an invariant rather than a rule of
thumb.

Only Address honoured it. Money, CreditCard and PhoneNumber collapsed all
three cases into `unreadable`, so "you left the amount out" and "the amount
is gibberish" were one verdict carrying no part -- which is why schema-html
ended up collapsing blank records to null before handing them over.

Money gains currencyRequired and amountRequired; PhoneNumber numberRequired;
CreditCard numberRequired and expiryRequired. Each names its part, so a form
marks the box rather than being told the whole value is unreadable.

Two asymmetries worth stating, because both look like oversights:

PhoneNumber's country has no counterpart. libphonenumber cannot parse a
number without a region, and `0411 222 333` is a different number in a
different country -- so the country is to a phone number what it is to an
address, refused rather than reported. The number is the half that reports.

CreditCard loses expiryFormat. An expiry that was given and cannot be read is
now a shape failure, as a bad amount already was on Money, so the constraint
had nothing left to say that expiryRequired does not. Keeping both would mean
two names for "there is no usable expiry here".

File is deliberately untouched. Its three parts are one upload's metadata
rather than three inputs a form renders -- no page has a "file type" box to
mark -- and its own comment already says a null there is a malformed upload.
Adding *Required constraints would have added three names nothing can act on.

Also: PhoneNumber::partNames() returns the keys it is submitted with,
`number` and `country`, rather than `country` and `e164`. E.164 is a derived
reading and stays as toE164(); it was never an input, so a port could not
derive its field names from the parts and PartedSet::forPart('number') raised
on the one part a form definitely renders.

The audit lives in MalformedCompositeInputTest as one provider across all
four, because the point is that they agree -- a fifth record field that does
not fails there rather than in a port.

### A field name identifies exactly, and collides case-insensitively

`a62aebc8` · 2026-10-01

FieldName::equals() folded case, and it was the only thing in the library
that did. A payload is keyed exactly, forField() matches exactly, extractData
keys exactly, and Facade::against() buckets outcomes by the scope's spelling
and reads them back by the field's. One comparison disagreeing with all of
them was silent in both directions:

  - a collection template holding `Name` and `name` built without complaint,
    then threw DuplicateFieldName on *every request*, when eachItem() built a
    Set from it. An authoring mistake that waited for a user to find it.
  - thenIgnore('Detail') against a field called `detail` passed Guards,
    because the resolver folded case, and then never applied, because the
    outcome bucket did not.

What the folding was *for* is a good rule and stays: a schema holding both
`email` and `Email` leaves a reader guessing which one a message or a scope
path meant. It is just not the same question. "Is this the same field" is
asked on every request and must agree with every lookup; "could these be
confused" is asked once, where the schema is written.

So equals() is exact and collidesWith() is new, and Field\Set and
Collection's template both refuse a collision as they add. Collection had its
own duplicate check keyed by the name as written, which is why it disagreed
with Field\Set in the first place; it asks the shared question now.

FieldNameTest::case_does_not_distinguish_two_names is inverted rather than
deleted -- it pinned a real decision, and the replacement pins where that
decision moved to.

### A rule about an address part no longer dies quietly

`14ca7430` · 2026-10-01

Two ways to write one that was accepted where it was written and then never
fired -- the failure Rule\Guards exists to prevent, arriving through a door
it does not watch, because wouldBeParsed() asks for a ValueScope and a
PartScope is its sibling rather than its subclass.

**A spelling the field accepts as input.** An address stores AU-QLD whichever
of QLD, qld, AU-QLD or Queensland arrived, and AU for Australia. The stored
side went through that canonicalisation and the expectation did not, so
equals('QLD') compared AU-QLD against QLD and was false for every request
there would ever be. The spelling that reads most naturally was the one that
could not work.

The value canonicalises the expectation now, through a new
HasParts::canonicalPartValue(). Asked of the value rather than worked out by
the comparison, because only the value knows what it did: a subdivision needs
its country to resolve, and the submitted address is the only thing holding
one. That is also why it happens at match time -- with several countries
allowed there is no single subdivision list to resolve against. Five of the
six implementations return the expectation unchanged, because five of the six
canonicalise nothing.

**A part held as a list.** street is up to three lines, so every scalar verb
compared an array against a string. Six of the seven were dead; only isEmpty
worked, and only because 03b7c6c had already taught it about lists. The
textual verbs fold over the entries now -- "any line", the only reading that
is useful and unambiguous -- and the ordered ones are refused where the rule
is written, naming the verbs a list does answer. HasParts::listParts()
declares which parts those are, statically, because a rule is checked before
any value exists.

Not Condition\Quantified: it asserts a Scope\Column and folds rowNamesIn(),
which is collection machinery for "how many rows match" rather than "does any
line".

Two things found while doing it. IsIn had its own comparison loop and so
missed both fixes; the comparison core is now one method both go through, so
a subclass looping over operands cannot get a simpler comparison than equals
does. And the first cut of this silently did nothing, because PartScope was
not imported into Comparison -- instanceof against an undefined class returns
false rather than raising, which is the same shape of bug as the two above.

### Read the addressing library's field names rather than assuming their shape

`be578b51` · 2026-10-01

Requirements compared upstream field names strictly against string literals.
Its own docblock was candid about the risk: the addressing library documents
getRequiredFields() and getUsedFields() as returning AddressField objects,
and at runtime they are the strings those objects' constants hold.

Strict comparison against the wrong shape fails silently in the direction
that matters. requiredParts empties, so every requiredness check skips and an
address is never incomplete again; usedParts empties the same way. Only
streetLineLimit would have failed loudly, by dropping to zero. composer.json
allows ^2.0, so an update inside the permitted range could do it, and nothing
in the suite would have gone red.

Both readers now go through nameEach(), which reads a string, a backed enum
or a Stringable, and raises UnreadableAddressFormat for anything else. Loud
rather than quiet, for the reason UnknownAddressPart gives one layer down:
the silent alternative answers "not required" and lets the part go unchecked.

Two tests. One pins the current shape, so an upstream change is noticed even
though it no longer breaks anything -- it says in its own docblock that going
red means "move the assertion", not "something is wrong". The other exercises
all three shapes directly, because no version of the library produces more
than one of them at a time, so a defensive read cannot ride on the data.

### Update history

`69664130` · 2026-10-01

### Report an unusable part against the part itself

`553ea19c` · 2026-10-01

`usedParts` was one constraint covering four questions, and because which
part offends varies per request it could not name one. So `part` was null,
which meant a form had no input to mark and a message pack got a single
sentence for every variant — and with two offending parts it reported one
failure, leaving the second invisible until the submitter fixed the first
and tried again.

Four constraints now, one per part that a country can lack:
`localityUsed`, `dependentLocalityUsed`, `subdivisionUsed`,
`postalCodeUsed`. Each skips unless that part was submitted and the
country has no place for it. There is no `streetUsed` — all 206 countries
use a street.

Each carries its own part, so a GB address with both a county and a
dependent locality reports two failures a form can attach to two inputs,
with no intersecting of submitted parts against a bound and no knowing to
exclude `country` by hand.

It also completes the trio per part, which are three different questions:

    subdivisionRequired   must you give one?
    subdivisionUsed       may you give one?
    knownSubdivision      is the one you gave real?

Thirteen constraints on one field is a lot, and it is the same trade
already made for the four `*Required` checks rather than one carrying a
list. An address is a complex domain; the alternative was a caller
special-casing one constraint's bound.

Recorded on the roadmap rather than built: widening `Constraint` to carry
several parts would collapse these four into one, and **cross-field
constraints** need the same shape — `confirm_password === password` is a
rule about two fields, not one part — so the two want designing together.
Doing it for one field today would have cost `ConstraintValidationResult`,
the message key ladder, `PartedSet`'s grouping, every port, and the
tri-state check that is the simplest thing in the library.

### Update history

`46846449` · 2026-10-01

### Judge a postcode against the subdivision's own pattern

`19d081f3` · 2026-10-01

36 of 1548 subdivisions publish their own postcode pattern, across China and
Colombia alone, and the country's was being used for all of them. That let
wrong postcodes through — any six digits passed as a Macau code, whose only
valid value is 999078 — but worse, it *rejected right ones*: Taiwan's
pattern admits three to six digits where China's admits exactly six, so a
valid three-digit Taiwanese postcode was refused. An override replaces its
country's pattern rather than narrowing it, which is why it cannot be
treated as an extra check layered on top.

The reported bound improves with it. A failure now names the rule that
actually applied — `11\d{4}` for Bogota rather than Colombia's `\d{6}` —
so a message can quote something true.

Requirements carries both halves as public properties rather than hiding
the lookup behind postalCodeFormatFor(). The method is sugar; the data has
to serialise, because a reader in another language must be able to apply
`overrides[subdivision] ?? postalCodeFormat` from the document alone. A
map on its own would not do: 133 of 206 countries have a postcode pattern
and no subdivisions at all, so there would be nowhere to put Germany's.

`subdivisions` now maps each code to its name. The name is a matching key
as much as a label — an address may name its state in full — so the
mapping to the canonical code is the library's to publish rather than
something each port rebuilds against the same data. `subdivisionCodeFor()`
exposes it, and `postalCodeFormatFor()` accepts a bare code, a full ISO
code or a name, in any case.

That also leaves one subdivision resolver instead of two. Value had its own
copy; it now calls the one in Requirements, where the rest of the
per-country data already lived.

Recorded on the roadmap, not solved here: a document carries configuration
and never the standards data its rules are read from, so a reader in
another language needs its own copy and nothing says whether the two
agree. Embedding is not the fix — a free-form address field would carry
206 formats — so what is wanted is a way to name the dataset and version a
document assumes. Same problem as "Baselines across languages", one layer
out.

### Update history

`403c4337` · 2026-10-01

### Repair the cookbook's address snippet, and teach it the two dials

`90f5a2c2` · 2026-10-01

The snippet was invalid as written. It built an Australian field and then
submitted a street, a locality, a postcode and a country — no state — so it
failed `subdivisionRequired`. A documented example that does not run is
worse than none, and this one was broken by the same sweep that renamed
`line1` to `street` inside it: the keys were updated and what surrounded
them was not.

Nothing executes the cookbook, which is why it rotted while the suite and
`examples/*.php` stayed green. Worth fixing properly at some point; for now
every address snippet in the file has been run by hand.

While in there, the cookbook now covers what the rewrite introduced and it
had nothing on: the Precision ladder, `mustBeVisitable()`, and
`requirementsFor()` — which is the only way a port can mark an input
required before a request, and so the thing a reader most needs. Also the
`locality` versus `dependent_locality` trap, with Cardiff NSW 2285: the
suburb IS the locality, Australia has no dependent locality, and "suburb"
sounding subordinate is exactly what makes it a trap.

Separately, `declaredRequirement()` now takes the resolved rules rather
than re-deriving them. `defineConstraints()` had already asked, and the
four calls each asked again.

### Update history

`471de0ea` · 2026-10-01

### Refuse to answer about one country twice

`f2f17430` · 2026-10-01

requirementsFor('AU', 'AU') returned a single entry, so the result was
quietly shorter than the question. requirementsFor('au', 'AUS') returned
two keys holding identical answers, so a caller looping over them did the
same work twice with nothing to show that it had.

Both hide a caller bug, and the second hides it better — the map looks
right and only the contents repeat. So neither is answered now: any two
arguments resolving to the same country are refused, naming the country
and both spellings that produced it.

The no-argument form cannot trip on this. `allowCountries('AU', 'aus')` is
already one country by the time the allow-list is built, so the list it
falls back to is unique by construction.

### Update history

`9554a52f` · 2026-09-30

### Accept every subdivision this library publishes

`3f500933` · 2026-09-30

Two defects, both hidden by the same blind spot: every subdivision test
used Australia or Ireland, and both are uppercase-coded and string-keyed.
The countries that are neither were where the failures lived.

A subdivision submitted by *name* threw a TypeError out of validate().
`SubdivisionRepository::getList()` is annotated `array<string,string>`, but
PHP converts a numeric-string key to an int on the way into an array, so
returning it from a `?string` method is fatal — not a MalformedValue a
caller can catch, a hard error escaping the validation boundary entirely.
19 countries code numerically; 398 subdivision names hit it. `Tokyo`,
`Seoul` and `Bangkok` all killed the request.

Five countries — CV, HK, KY, RU and TV — code their subdivisions by name,
so the ISO 3166-2 form published for them is `HK-Kowloon`. The lookup
uppercased the candidate before an exact-key match, which cannot match a
key that is a name, and the name scan compared against the un-stripped
original. So the library refused the exact value `requirementsFor()` had
handed a port to render its options. Hong Kong, Cape Verde and the Caymans
were unusable through the documented path.

Both are gone: one case-insensitive pass over code and name, against a
candidate whose prefix has already come off, with the key cast back to the
string it was written as.

The test that would have caught it now exists and is the one worth
keeping — every subdivision of every country, round-tripped from what
`requirementsFor()` publishes back through `Value`. 1548 of them.

Also here, from the same review:

- `toArray()` emitted `[]` for an absent street, which the constructor
  refuses, so a value could not be read back from its own serialisation.
  Any persist-and-reload path hit it. `parts()` keeps the raw list, because
  a scope asking whether a street is empty wants the property.
- `Requirements::forCountry()` is memoised on country *and* floor. A field
  asks it ~15 times per request — once per constraint, again for each
  `boundFor` — and it was rebuilding a 62-element subdivision list each
  time. US validation: 155us to 22us.
- The four test blocks the plan specified and the branch skipped: the four
  countries that require nothing below themselves, HK and US in the
  cross-country table, the twelve countries that accept a dependent
  locality, and CN for the subdivisions-carry-postcodes case.
- MESSAGES.md documented a pack containing `part.organization`, which the
  vocabulary no longer asks for and PackValidator now refuses. A reader
  copying it got a hard error.

### Update history

`4a3f3a02` · 2026-09-30

### Document the address rebuild, and the rule it forced out

`096f9bc5` · 2026-09-30

UPGRADING gets the section a rename of this size needs: what now fails that
used to pass, the API and part tables, and the three changes that alter
stored data rather than calls — line1/line2 joining into a street list,
QLD becoming AU-QLD, organization moving out of the address entirely.

It also states the obligation ports now carry, because it is easy to miss
and expensive to get wrong: omit a part you have no value for, never post
`''`. A form that sends empty strings for untouched inputs makes every such
address unreadable, and the submitter is told the address cannot be read
rather than which part is missing.

FIELD-API said "a constraint's name is its property's name", which turns out
to be one case of three. Rebuilding Address forced the others out: a bound
derived from reference data cannot be a property, because it depends on the
submitted value and is unanswerable most of the time, and a constraint with
nothing to configure has neither. The doc now says all three.

examples/addresses.php runs every case, including the ones worth seeing
rather than being told: the same unconfigured field judged by Japanese,
Panamanian and British rules, and Cardiff NSW 2285 — where the suburb IS
the locality, and Newcastle is not part of the address at all.

ROADMAP records where coordinates go, and why Address will not derive them.

### Teach emptiness about a part held as a list

`03b7c6c6` · 2026-09-30

isEmpty() matched on null, Countable and Stringable|string, then fell to a
default of "not empty". A plain PHP array is none of those — it is countable
by count() but does not implement the interface — so an array-valued part
reported as not empty however little it held.

Nothing had an array-valued part until `Address\Value::$street`, where an
absent street spells itself `[]`. So `when(...'street')->isEmpty()` answered
false for an address with no street at all, which is the one case it exists
to catch.

The hole was always there; street is only the first part to stand in it.

### Read the country's own rules instead of guessing at them

`c397cab5` · 2026-09-30

Address never asked libaddressinput what a country requires. Four of its
five constraints skipped on a bare `{street, country}`, so an Australian
address with no suburb, state or postcode was valid. Three more defects
sat beside it, each found by reproducing rather than reading:

  - An unrecognised country was kept verbatim and every country-driven
    check skipped, so spelling one in ISO 3166-1 alpha-3 silently turned
    off postcode and subdivision validation on a free-form field — which
    is the default.
  - The PO-box pattern was anchored to the start of the subject and read
    line one only, so a box written on the second line slipped past a
    field that had asked for somewhere visitable.
  - Nothing tested a part for emptiness, so `'   '` satisfied a
    requiredness check. `country` was inconsistent with itself: `''` was
    unreadable and `'   '` was not.

Type spanned two independent axes at once — how deep an address is
specified, and whether it names a place a person can attend. That is why
`Postal` had nothing to do at request time, and why no third case sat
comfortably beside the other two. It came from HL7 FHIR, where those
words describe an address someone already holds rather than demand
anything of a submitter, so it was behaving exactly as designed, in the
wrong job. Two dials replace it, and every combination is legal — the
guard refusing "mailable and no street" is gone, along with a claim that
was wrong anyway, since a PO box names no street and is perfectly postal.

Requiredness is now the country's, filtered by the field's floor. Only 75
of 206 countries require a postcode and 44 a subdivision; Japan, Hong Kong
and the Emirates do not require a locality. A check for a part the
submitted country does not ask for skips rather than quietly passing.

The value object gets the renames and the refusals. Parts drop from eight
to six: `line1`/`line2` become one `street` holding a list of lines, which
is what WHATWG's `street-address` token describes and what keeps equality
honest now that nothing normalises — a delimited string would carry a
separator whose spelling HTML and JSON disagree on. `organization` goes
the way `givenName` already had: an address identifies a place, not who is
at it. `administrative_area` becomes `subdivision`, holding the full ISO
3166-2 code, which is the vocabulary the SubdivisionRepository underneath
it was already using.

An unresolvable subdivision is unreadable only where the country requires
one. That covers exactly the cases where it is undecidable: 36 of 1548
subdivisions carry their own postcode pattern, in CN and CO, and both
require one. Elsewhere it stays a reportable constraint failure.

requirementsFor() is how a port asks before a request. Every
country-driven bound is unanswerable while more than one country is
allowed, so the fields that most need the answer could not get it — which
is why schema-html re-implemented isUsedByAny() and postalCodePatternFor()
against the same data and drifted while doing it. It is the one read path:
no `postalCodeRequired` property beside it, because a fact with two
accessors is a fact that can disagree with itself.

### Add the Precision ladder that replaces Address\Type

`e9817de2` · 2026-09-30

Type tried to span two independent axes in one closed set of "kinds": how
deep an address is specified, and whether it names a place a person can
attend. That is why Postal had nothing to do at request time, and why no
third case sat comfortably beside the other two — "Area" answers the first
question while "Postal" and "Location" answer the second.

Depth is ordered, so it gets a ladder: Country < Subdivision < Locality <
Street. A field's floor filters the country's own required parts rather
than inventing any, which keeps it monotone — a shallower floor still
accepts a deeper value, so "an address or an area" stays one field and
needs no union type.

A postcode is not a rung. It names a delivery zone rather than a tier
between locality and street, and "Emerald QLD 4720" is one answer, so it
travels with the locality.

covers() throws rather than answering false for a part it does not know.
Nothing a submitter sends can reach it — an unknown key fails the shape
first — so it only fires on a typo in our own map, where the quiet answer
would leave that part unchecked.

### Update history

`74f43c68` · 2026-09-28

The v2.0.0-alpha.2 tag is back on 66defeb, which is the commit Packagist
published and froze. Packagist versions are immutable, so the reference it
recorded cannot be changed by moving the tag -- git and the published package
had come to disagree about what alpha.2 is, and this puts them back in step.

Nothing under src/ differs between that commit and here: the three commits
since are a CI workflow fix and two regenerations of this file.

### Update history

`7cf04e4c` · 2026-09-28

The v2.0.0-alpha.2 tag moved to the commit whose CI run is green, so the
release now covers the changelog-check fix and this file's own regeneration.

### Update history

`11e33426` · 2026-09-28

### Give the changelog check the history it reads

`7449dc97` · 2026-09-28

The check renders the changelog from the whole history, grouped by tag, and
compares it to the file. actions/checkout defaults to fetch-depth 1 with no
tags, so on CI it saw one commit and no releases, rendered a one-entry
changelog, and called the real one stale.

It had never run there to say so. The step was added in a411601 along with
the other two checks that the `ci` script listed and the workflow did not,
and that commit sat unpushed until now -- so the first time it executed on
CI was the first push that carried it, and it failed on its own setup rather
than on anything it was checking.

Verified both ways against the pushed remote: a --depth 1 clone reports the
changelog out of date, a full clone reports it current.

## v2.0.0-alpha.2 — 2026-09-28

### Update history

`66defeb0` · 2026-09-28

### Say 2.0.0-alpha.2 where the tag is named

`85eb4576` · 2026-09-28

Three places name the current tag: the README banner, the note in
LIMITATIONS.md about the scope format still being unfrozen, and the warning
in UPGRADING.md that the sibling packages do not work against it. All three
still say alpha.1.

### Regenerate CHANGELOG.md

`77c51b43` · 2026-09-28

### Date, Time and DateTime stay independent, and the reason is written down

`cbda7197` · 2026-09-28

Normalise the type names and the three are close to identical -- after the
four-bound change, only three genuine code differences remain between Time
and DateTime: a parameter name, the MalformedValue message, and the one
->atDate(...) hop a time of day needs to become an instant. So a shared base
or trait is the obvious suggestion, and it will keep being suggested.

It is declined. Each field is its own type and answers for itself, and the
coupling would cost more than the repetition saves.

There is a mechanical obstacle as well, recorded so nobody rediscovers it:
Brick gives LocalDate, LocalTime and LocalDateTime no common supertype. They
share Stringable and JsonSerializable and not one comparison method, so a
shared base cannot type the bound properties -- sharing would mean loosening
them to mixed or inventing a wrapper, and both read worse than two files that
say what they do.

What the duplication actually risked was drift, and it drifted once. That is
now the thing that is guarded rather than the thing that is prevented:
tests/Api/TemporalBoundsTest.php runs every bounds case against all three
fields from one provider, so a change copied to two of them and not the third
fails there. Copy by hand; let the test catch the miss.

Noted in each class docblock and in TODO.md under "Recorded, not scheduled".

### A composed condition cannot be written to

`5aa53c50` · 2026-09-28

ConditionGroup::add() wrote to $conditions and returned $this. It was the
only mutating method anywhere in the rule model -- Rule\Set::add() and
Draft::then() both clone -- and it had no caller in this package or in either
sibling.

Nothing had gone wrong yet, which is the only reason it survived. The problem
is that a rule is meant to be a value, and the concurrency guarantee in the
README rests on a composed condition not being writable by whoever happens to
be holding one. Removing the method and sealing both implementations makes
that structural rather than a matter of nobody having called it.

AllOf and AnyOf are `final readonly` now; the constructor was already their
only writer once add() was gone.

Also repoints ConditionGroup::conditions()'s docblock at Rule\Guards, which
is where the caller it names moved to.

### A matcher says how it is quantified, instead of being rebuilt by convention

`0f3aeb61` · 2026-09-28

Collection::acrossRows() reconstructed the template field's matcher with
`new ($matcher::class)($scope, $how)`. The Matcher interface required only a
scope, so the two-argument constructor it relied on was a convention that all
four built-in matchers happened to follow.

Its docblock said "a field type this library has never heard of is
quantifiable the moment it picks a matcher". That was not true. PHP passes
extra arguments to a user-defined constructor without complaint, so a matcher
of somebody else's declaring only a scope -- which is what the extension
example in AsksOrder shows -- emitted `Undefined property: $quantifier`,
dropped the quantifier, and built an unquantified condition against a column.
That compares a list of every row's value against one scalar: accepted by
both authoring guards, and it could never fire. The same dead-rule failure
the guards exist to prevent, arriving through the seam the docs advertise.

The contract is on the interface now. `quantifiedAt()` is a declared method,
implemented once in BuildsDrafts as a clone-with so it needs no knowledge of
the using class's constructor, and `$quantifier` is a declared property hook.
A matcher missing either fails at class-declaration time naming what is
absent, rather than on a request -- verified by writing one.

$quantifier goes from protected to public to satisfy the hook. That is a
break for a third-party matcher, which is cheap now and would not be later,
and it reads as the rest of the library does: if something reads state, it is
a property.

MatcherVocabularyTest excludes quantifiedAt for the same reason it already
excludes __construct -- it is not a question a rule can ask.

### A row rule is checked the way a schema rule is

`4c6f3d17` · 2026-09-28

addRule() ran two authoring checks. forEachRow() ran neither -- it had a
third of its own -- so a row rule could be written two ways a schema rule
could not, and both failed quietly:

  $age->when()->equals('eighteen')    a number is never that string, so the
                                      rule was accepted and never fired

  PropertyScope::of('age','nonesuch') accepted at authoring, then InvalidScope
                                      on whichever request first reached it

The first is defect D2 arriving through a second doorway: a dead rule raises
nothing, which makes it indistinguishable from one whose condition simply
never held. Step 1 spent a stage closing that. The second is the
500-on-a-user-request that the scope check exists to prevent, from a typo the
author made.

Both guards move to Rule\Guards, for the reason Rule\Application already
gives for living where it does: a schema applies its rules to its own fields
and a collection applies its row rules to its template, so both want one
implementation rather than a copy each. They are static and take the field
set, because for a row rule that set is the template.

assertRowRuleStaysInsideTheTemplate() stays, and runs first: its message
names the collection and lists what the template holds, which reads better
than the UnknownField the general check would raise for the same mistake.

This can refuse a row rule that was accepted before. That is the point, and
it fails at authoring time rather than on somebody's request.

### One answer to "nothing was submitted, now what"

`164a91c4` · 2026-09-28

There were three, and two of them disagreed. Definition::resolvedValueFor()
turned absence into null; AtomicField::read() repeated the same expression;
Collection::itemsIn() was a private third copy with `?? []` on the end.

So a collection nobody submitted read two different ways depending on who
asked. resolve() and validate() went through the private copy and saw an
empty list. ScopeResolver went through resolvedValueFor() and saw null -- so
a rule reading #/fields/lines/value and the result for `lines` reported
different things about the same request, and the rule got the wrong one.

`final` on resolvedValueFor() is what forced the fork. Its docblock says the
lifecycle must be uniform across fields, which is right, and Collection is
the one field for which part of it legitimately is not. That is now a hook
rather than a second implementation: rawFor() holds the rule, absentValue()
is the one part a field may vary, and Collection overrides that alone. An
empty list stays what absence means for a repeatable section -- which is what
lets minCount say "add at least one" instead of "this field is required".

sourceOf() moves to Definition as well. The two copies were byte-identical
and it reads $defaultValue, which is this trait's to know about.

Pinned by asserting the two paths agree, and that the hook does not leak:
an absent atomic field still reads as nothing.

### Documentation that teaches an API nobody can call

`7f7b109d` · 2026-09-28

Two kinds, found together because both are prose that no check reads.

EXTENDING.md's worked example declared `parse(): ?Isbn\Value` and returned
null on bad input. The contract is `parse(mixed): ParsedValue` -- non-nullable,
throwing MalformedValue -- which the same page states plainly as rule 3 forty
lines further down, and which examples/custom-field.php gets right. Anyone
following the example rather than the rule wrote a field that fails on its
first malformed input.

The page claims to *be* that example. It had drifted: the signature, the
missing when(), the namespace, and the line count. It is now the same code,
so the CI-run file and the page agree.

Four docblocks in src/ showed ->thenRequire() and ->elseMakeOptional(), an
API Rule\Draft's own docblock says does not and cannot exist -- outcomes are
the field put through its own withers. They sit on Facade::when(), allOf()
and the BuildsFields header, which are the first things anyone reads about
writing a rule. The BuildsFields one also used two variables it never
assigned; the replacement runs, and was run.

Nothing here is caught by test:examples, which executes examples/ but has no
opinion about fenced code in docs/ or in a comment. That gap is real and
worth closing, but it is a bigger job than this commit.

### Eight docblocks were documenting nothing, and CI now says so

`6397166a` · 2026-09-28

PHP binds only the last docblock before a declaration. When a member is
inserted between an existing block and the method it described -- which is
how all eight happened, most of them when when() was added to each field --
the earlier block stops documenting anything. It is invisible to PHPStan, to
the risky-rule report and to an IDE, so it survives every sweep that looks at
code, including the Step 4 rot sweep that caught this same shape elsewhere.

The one that matters is Field\Definition: the entire rationale for "every
wither routes through with() so the authored default is re-checked" -- a rule
docs/CODING-STYLE.md calls out and Api\SealedFieldTest enforces at runtime --
was filed under reconfiguredWith(), leaving with() with a bare @param stub.

Four described members that no longer exist. Address and CreditCard\Value
each carried an array-taking factory's docs from before the object-is-a-record
migration, and PhoneNumber carried two methods' worth plus a reference to an
`unambiguous` constraint that is not there either. Where those blocks said
something the surviving code did not -- the YYYY-MM expiry spelling, why an
empty string is not absence -- it has been folded into the member that does
the work rather than deleted.

The check lives in tools/check-references.php because it is the same failure
as a dead {@see}: documentation rot that nothing else notices. Blank lines in
the gap are still caught, since they change nothing about what PHP binds.
Verified by planting one and watching CI fail.

### A point in time takes four bounds, and each says which it is

`ea291f97` · 2026-09-28

from/after for the lower bound, until/through for the upper -- inclusive and
exclusive at both ends, on Date, Time and DateTime. The inclusive ones say so
in their names, which is the rule the matchers already follow: isAtLeast
includes its bound and isGreaterThan does not.

An inclusive `to()` stood beside until() once and was removed because both
reported under the constraint name `until`, so a result could not say which
had been declared. That objection was about the shared name, not about
offering the choice. Each of the four reports under a name of its own now, so
a verdict says which the author wrote -- and a language pack can word "before
17:00" differently from "at or before 17:00", which it could not do before.

The bound is stored as written. through('2026-12-31') is not folded into
until('2027-01-01'), and the reason is the wire format: the definition
serialises, so a reader in another language has to render back the bound the
author declared rather than one this library computed. Deriving it would put a
date in the document that nobody typed. It also removes any question of what
happens when precision changes after the bound was set, because nothing was
computed from the precision in the first place.

The two lower bounds are one bound said two ways, as are the two upper, so
setting either clears the other, in whichever order they are called. The
interval counts from whichever lower bound exists.

Duration is deliberately untouched: it is a length of time rather than a point
in one, so it takes minValue/maxValue like Number and both are inclusive.

### until means the same thing on all three point-in-time fields

`9d585afb` · 2026-09-28

Time::until() was inclusive while Date::until() and DateTime::until() were
exclusive. All three report under the constraint name `until`, and a language
pack writes one sentence per constraint name -- so that sentence was right for
two fields and wrong for the third. docs/API.md already stated the contract
("until is exclusive in a way max does not suggest"); Time did not keep it.

Nothing compared them. Each field's tests live in its own file and assert its
own behaviour, so three fields could disagree indefinitely. tests/Api/
TemporalBoundsTest.php runs every case against all three from one provider,
which is the part that stops this recurring.

The bounds are nullable now rather than sentinel-defaulted, matching every
other optional bound in the library (Number::$maxValue, Text::$maxLength,
File::$maxSize). Two reasons beyond consistency:

An unset bound was reported as *passed*. LocalDate::min() answers yes to every
date, so the field returned a verdict on a question nobody asked. It is
skipped now, which is what "no limit" means everywhere else here.

And the sentinel was unsound on Time specifically. LocalTime::max() is
23:59:59.999999999 -- a value a field at Precision::Nanoseconds can genuinely
hold -- so an exclusive bound defaulting to it would have refused the last
instant of the day while claiming to be unbounded. LocalDate::max() is year
999999 and hides the same bug behind an unreachable date.

`interval` counts from `from`, so it is skipped when there is no lower bound
to count from rather than measured against an arbitrary origin.

### A prefill no longer loses to the authored default

`20efa7ce` · 2026-09-28

`extractData(null)` seeded `$given` with every field's authored default, as
though each had been submitted. The precedence match then found a non-null
`$given[$name]`, chose ValueSource::Submitted, and never reached the prefill
branch -- so `resolve(null, prefilledWith: $known)` returned the default and
reported it as something the user had typed.

That inverts the precedence validate() documents: submitted, then prefilled,
then the authored default. It broke the primary prefill case, rendering a form
for the first time with what is already known about a user, and it made
$source lie on exactly the path a renderer uses to mark a prefilled field.

The seeding was also unnecessary. Every read of request data goes through
ScopeResolver::valueOf(), which calls resolvedValueFor($given[$name] ?? null)
and applies the default itself, so a null payload is now simply an empty one
and each field settles its own default. extractDefaultValues() is gone.

Nothing caught this because both spellings of "nothing was submitted" were
never compared: every field-level test calls $field->resolve(null) directly,
bypassing extractData(), and every case here passed a data object. The new
a_null_payload_says_the_same_as_an_empty_one is that comparison.

### Update history

`5ad4b9a0` · 2026-09-26

### Document reaching into a collection

`fe1747f0` · 2026-09-26

API.md said collection items were not addressable, which was true and is
the thing these five commits changed. It now documents the grammar as what
it is — four tails that can be rooted at a schema field, a named row, a
column or the template — and why the trailing `value` is there: a row field
is a field, so it has a definition as well as a value and both have to be
nameable.

LIMITATIONS.md's entry on scope depth kept the half that is still true (a
part of a part is not expressible) and dropped the half that is not.

UPGRADING.md gains the break that costs readers the most, and it is not a
code change: a collection is submitted as rows under names now, and a
positional list reports shape: unreadable. Both payloads in that section
were run rather than written from memory, along with what follows from it —
Item::$key is a string, the lookups take strings, and Value::equals() no
longer cares about row order.

### A rule can apply to one row at a time

`cff2fac8` · 2026-09-26

"If this attendee is a child, require this attendee's guardian" is a
question about one row, and a schema rule could never ask it: naming the
collection only ever speaks about the list as a whole. So a collection
carries its own rules now, applied per row.

    $attendees = $schema->createCollectionField('attendees', $age, $guardian)
        ->forEachRow($age->when()->isLessThan(18)->then($guardian->makeRequired()));

**It needed no new scope vocabulary**, which is the part worth noticing. A
row rule's field set *is* the template, so `$age->when()` produces the
ordinary `#/fields/age/value` and inside a row that is exactly what it
says — the row's values are what was submitted. Narrowing the engine from
Facade to Field\Set two commits ago is what makes that possible; while it
demanded a whole schema, none of this could reuse it.

So the fold is lifted out of Facade into Rule\Application rather than
copied: a schema applies its rules to its own fields, a collection applies
its row rules to a copy of its template, and both get the same
interleaving, the same applyTo(), and the same record of what happened. A
second implementation would have been a second place for rule ordering to
be subtly wrong.

Each row folds over its **own copy** of the template, so a rule that fired
for one row has said nothing about the next. If a rule could reach the
shared template, the first child in a list would make a guardian required
for everybody after them — and a test holds that down.

Ignore works per row too, because ignoring is about a request rather than a
definition, and a row is a request's worth of values.

A row rule may only reach fields the template holds, checked where the rule
is written: it runs against the template and nothing else, so a field
outside it could never resolve and would otherwise fail on a user's
request. Changing something outside the collection is a schema rule's job.

examples/row-rules.php shows both halves — per-row rules and the
quantifiers from the previous commit.

### Ask how many rows match, not just what they hold

`ecb7bab2` · 2026-09-25

The previous commit made a column resolvable and, in doing so, opened a
hole: `when('#/fields/lines/value/*/sku/value')->equals('HAZMAT')` compared
a *list* of one value per row against a string, so the rule was accepted,
never fired, and said nothing about why. That is the failure this library
spends most of its guards avoiding, so it does not get to stand.

A column needs a quantifier before it means anything:

    $lines->whereAny('sku')->equals('HAZMAT')->then($declaration->makeRequired());
    $lines->whereEvery('kind')->equals('digital')->then($shipping->makeOptional());

Rather than teach twelve matchers what a list is, Quantified re-roots the
inner condition at each row through Scope::rootedAt() and asks it exactly
as it would be asked of a top-level field. So a quantified isGreaterThan is
the same isGreaterThan, and a matcher added later is quantifiable for free.

whereAny() hands back the *template field's own* matcher, so a row field
offers only the questions its value can answer — whereAny('qty') has the
ordered verbs and whereAny('sku') does not. That needed one seam: the
twelve verbs now finish through BuildsDrafts::draft() instead of building a
Draft inline, which is the single place quantification is applied.

Re-rooting needs to replace a readonly property, which only the declaring
class may do, so the three condition families gained about() — the
counterpart to Scope::rootedAt(), and the reason they now say out loud
(via Scoped) that they hold a scope. A group like AllOf deliberately does
not: it asks about several places, so there is no one scope to re-root.

The quantifier is not part of the address. Both spellings name the same
values, so it serialises on the condition and the scope format is untouched
— which also means this could have landed later without changing what any
stored scope means.

Empty collections follow the standard reading, stated out loud because it
is the case people forget: "any of nothing" is false, "every one of
nothing" is true.

### Resolve a scope that reaches into a collection

`353468dd` · 2026-09-24

The locator picks which field and which value; the tail logic that was
already there runs against them unchanged. That ordering is what stops
sixteen paths becoming sixteen branches.

A column is answered by asking each row the same question, through
Scope::rootedAt() — so no tail had to learn what a list is, and
`…/value/*/sku/value` comes back as one value per row under the row names.
The read side is Collection\Value's own valueOf() and keys(), reused rather
than reimplemented.

Everything knowable without a request is checked when the scope is
resolved, which is where Facade::addRule() resolves it: that the field is a
collection at all, and that its template really holds the field named. A
row is not, because which rows exist is a fact about a request — so an
absent row resolves to nothing, the way an unfilled part of an address
does, and a rule naming it simply does not fire.

`…/template/<f>/value` raises rather than guessing. The definition is
row-agnostic and a value is not; answering "the first row" or "all of them"
would be a silent answer to a question nobody asked.

**And it found a bug in the comparison checks.** Every scope reports the
*schema* field it belongs to — the collection — so
`when(#/fields/lines/value/rush/sku/value)->equals('URGENT')` asked whether
`Collection` could hold a string, decided it could not, and refused a
working rule as one that could never fire. The same lookup fed the
expectation through the collection's parse rather than the template
field's. Both now ask ScopeResolver::fieldFor(), which answers with the
field the scope is actually about.

### A scope is a locator and a tail

`d0f4d630` · 2026-09-24

Four namespaces times four tails is sixteen paths, and writing a class per
path would have meant twelve new ones. They are two independent things, so
they are modelled that way: a locator says where to look, a tail says what
to read once there.

The four tails are the classes that already existed — FieldScope,
PropertyScope, ValueScope, PartScope — so every instanceof in the library
still dispatches on exactly what it did before. What is new is where they
can be rooted:

    #/fields/<f>                     a field the schema holds
    #/fields/<c>/value/<row>/<tf>    one named row of a collection
    #/fields/<c>/value/*/<tf>        every row, as a column
    #/fields/<c>/template/<tf>       the template, row-agnostic

parse() reads the locator off the front and applies one tail grammar to
whatever is left, so a row's field is addressed exactly like a top-level
one — and the trailing `value` that tells `…/alice/email` from
`…/alice/email/value` is the same segment that has always told
`#/fields/x/value` from `#/fields/x/minLength`. A row field is a field: it
has a definition and a value, and both have to be nameable. Deferring that
segment would not have been additive, because adding it later would change
what an already-stored scope resolves to.

Disambiguation stays positional, so parse() still never asks what kind of
field it is looking at. Five segments under `value` is the shortest a row
can be, one more than a value part needs, so `#/fields/billing/value/country`
is read as it always was. `template` alone still resolves as the ordinary
property it always did — the marker only takes over once a field follows it.

Nothing needed escaping, because a row key is now a name: a name cannot
contain the `/` that separates segments, and `*` cannot be a name, which is
what leaves it free to mean "every row".

Scope::$field stays the field the *schema* holds — the collection, never the
template field — so the two places that read it, grouping outcomes and
looking a field up, needed no change. It is copied from the locator rather
than read through a hook, because a readonly class may not have one.

ScopeTest becomes the grammar's specification: all sixteen shapes round-trip
through parse() and __toString(), with the rejections beside them.

Also restores three docblock examples in Comparison that the previous
commit's rename flattened: they call $schema->when(), which is a Facade
method, not a Field\Set one.

### Regenerate CHANGELOG.md

`f9a34cff` · 2026-09-24

Brings it up to date with the code-style work. The check tolerates HEAD not
being listed, so this commit does not invalidate itself.

### Document the two string rules in the coding style

`775ca7c9` · 2026-09-24

The "what the tools enforce" table listed the two fixer configs but not the
PHPStan rules in tools/CodeStyle/PhpStan/, which are a third enforcement
category: house rules that need type information and so can only report.

Adds a "building strings" section covering when interpolation wins, when sprintf
keeps its place, and the distinction between leading-dot continuation as line
wrapping and concatenation as gluing — which is the difference the concat rule
is built around.

### Prefer interpolation to concatenation where it says the same thing

`7c2a6b8e` · 2026-09-24

`$this->prefix() . '/' . $this->property` reads as three things joined;
"{$this->prefix()}/{$this->property}" reads as the path it is. Three sites.

Most concatenation in this codebase is left alone, and the rule is written to
say why rather than to be tuned until it is quiet:

  - Leading-dot continuation is line wrapping, not concatenation. It makes one
    unbroken string, which is the point, and a heredoc would put real newlines
    into an exception message. Any chain spanning lines is skipped.
  - Field::class . '\' . $kind cannot interpolate a class constant at all.
  - '~^(?:' . $pattern . ')$~' is a regex whose closing $~ would need escaping.
  - .= in Mf2\Formatter is a character-parser loop, and a different node type.

The first draft of this rule reported all three of those. Concatenation is
left-associative, so `$a . '/' . $b` is Concat(Concat($a, '/'), $b) and the rule
ran on both nodes — validating the inner pair and never seeing the rest of the
chain. It now reports only where the left operand is itself a Concat, which
fires once at the outermost node with every operand in view. The cost is that
two-operand chains are never reported: PHPStan exposes no parent pointer, so
such a node is indistinguishable from the base of a longer chain. Silence is the
right side to err on for an advisory rule.

### A condition is given the fields, not the schema

`0b42e052` · 2026-09-24

Facade appeared in a dozen condition signatures and in every one it was
used for exactly one thing: reaching ->fields. ScopeResolver was the same
— a single getByName() call behind a whole schema.

So the parameter narrows to Field\Set. Nothing about behaviour changes,
and the suite is the proof: 1851 tests green with no test rewritten except
the two that construct a ScopeResolver directly.

What it buys is the thing that comes next. A collection's rows have to be
resolved against the *template* — a field set that is nobody's schema —
and a rule applied per row has to evaluate against that same set. While
the engine demanded a Facade, none of that could reuse it, and per-row
rules would have meant a second copy of the rule engine sitting beside
this one. A set is the smallest thing resolution ever needed, and it is
the thing a row can supply.

Comparison.php also loses an import that only its comments still used.

### Prefer interpolation to sprintf where every value is already a string

`b7783e0a` · 2026-09-24

sprintf('A minimum %s cannot be negative.', $what) says the same thing as
"A minimum {$what} cannot be negative." with a format string, an argument list,
and a mapping between them for the reader to hold. Nineteen sites, most of them
exception factories, several of which shed escapes on the way:

  sprintf('Country \'%s\' is not a supported region.', $country)
  "Country '{$country}' is not a supported region."

sprintf keeps everything else: mixed types, padding and width, positional
arguments, and values built by a call. `implode(', ', $names)` reads better as
an argument than wedged into a string, and the rule never reports it — not by a
special case, but because a call with arguments cannot sit inside {...} at all.

This is a PHPStan rule rather than a fixer because the deciding question is "is
this argument a string", and no token-level tool can answer it: $this->name
might be a string or a FieldName. Reporting also leaves the judgement where it
belongs — the rewrite is mechanical, but whether a given message reads better
either way is not.

src/Exception/InvalidConfiguration.php is now visibly mixed: 17 of its factories
interpolate and the rest still use sprintf, because the rest take %d or
non-string values. That is the rule working as specified rather than a gap.

### Adopt phpstan-strict-rules, and fix the one real thing it found

`c1a95f4c` · 2026-09-24

Measured before adopting, as the whole shipped tree: 20 errors in four
categories. Everything else in the ruleset was already at zero — loose
comparison, empty(), backticks, variable variables, non-strict in_array, useless
casts, truthiness on non-booleans — so the set costs one package with no
transitive dependencies and buys insurance against all of them.

The real finding is in Facade::against(). $applied holds a list<AppliedOutcome>
from applyRules(), and a nested foreach rebound the same name to a single
AppliedOutcome. Not a bug — the list is not read again — but it is precisely the
"a variable keeps one type" rule, and a heuristic scan of the tree had reported
zero violations of it. A real analyser found one. Renamed to $appliedOutcome.

Three categories are ignored with reasons rather than fixed. Each is a design
decision the rule disagrees with: fields deliberately narrow what parse() takes,
`glob(...) ?: []` is array-or-false rather than null, and five sites of dynamic
property access want a refactor rather than a style fix.

src only. Tests stay at level 1 and reassign scratch variables freely.

### A collection row is named, and a name is all it can be

`0a6d1570` · 2026-09-24

Positional rows are gone. A row addressed by its position meant a
different row the moment anything was inserted above it, so a stored rule
naming one silently changed its mind between requests — which is why a
collection's rows were unaddressable at all, and why the scope grammar
cannot yet reach into a collection value.

A key is now held to the same pattern a field name obeys, asked through
the new FieldName::isUsable() so the rule lives in one place. That choice
removes a class of problem rather than managing it: there is nothing to
escape, because a name cannot contain a slash or a space; `*` is free to
mean "every row" later, because it cannot be a name; and the hazard that
PHP turns the array key '0' into 0 cannot arise, because a name cannot
start with a digit. keysAgree() and the three docblocks arguing about
which row `0` referred to are deleted along with the question.

A bad key fails the request rather than raising: keys arrive from a
submitter, so a positional list reports shape: unreadable like any other
input a field cannot read.

Collection\Value::equals() compared array_keys() in order while its own
docblock said naming rows was how you asked for order-insensitivity. Only
one of those could be true, and now that naming is the only way to submit
a collection it is the comparison that was wrong — two lists with the same
rows under the same names are equal whatever order they arrived in.

examples/collections.php was exiting 0 while printing "PASSES / status:
Failed" and a PHP warning, because check-examples.php only checks exit
codes.

### Rewrite the coding-style preamble now that formatting is enforced

`c4c57c87` · 2026-09-24

The document opened by saying "formatting is whatever the editor and PHPStan
already enforce" — which meant nothing enforced it, and the drift showed: 47% of
src/ files had unsorted imports and one file was space-indented in a tab
codebase.

Adds, ahead of the existing material: which conventions are fixed, which are
reported, and which stay human; the four places @PSR12 is deliberately departed
from; how the pre-commit hook behaves and why --no-verify means CI is the real
boundary; the four import tiers; the ternary rule and why a single comparison is
deliberately exempt; and "no language hacks" as a concrete list rather than a
sentiment, naming the three @-suppression sites that are allowed to stay.

The existing body — properties versus methods, withers, records versus lists,
the core does not serialise — is untouched. None of it is mechanical.

### Teach git blame to skip the reformatting commits

`3e19eece` · 2026-09-24

Seven commits on this branch reformatted the tree without changing what it does
— 117 files reordered imports, 58 dropped dead ones, 15 took the rest of PSR-12.
Left alone, those become the last change to most lines in the repo and `git
blame` stops answering the question people ask it.

GitHub reads this file with no configuration. Locally it needs
blame.ignoreRevsFile, which composer hooks:install now sets alongside
core.hooksPath.

The SHAs are branch-local: if this branch is squashed or rebased on merge they
will need regenerating against the new history.

### Check style in CI, and run the two checks that never ran there

`a4116012` · 2026-09-24

Adds a style job: composer test:style fails on anything unformatted, and
composer style:report fails on any risky finding. The pre-commit hook does the
same locally, but --no-verify skips it and a clone that never ran composer
install never had it, so this is the boundary that actually holds.

Also closes a gap that predates this work. The `ci` script listed eight steps
and the workflow ran five: tools/check-references.php and the CHANGELOG check
have existed for a while and have never once run in CI. They do now.

### Keep the pre-commit hook quiet unless it has something to say

`2d511b3c` · 2026-09-24

The risky report was streamed straight to the terminal, so every clean commit
printed the fixer banner and a "Found 0 of N files" line. Captured instead, and
echoed only when the report is non-empty.

### Format staged files on commit, and stop on what cannot be fixed safely

`071bf0f0` · 2026-09-24

Git has no hook for `git add`: nothing fires there, and post-index-change runs
after the index is written and cannot abort. So this runs at commit time, which
also catches files staged by `git add -p`, `git commit -a` or the editor rather
than only by `git add`.

It fixes the staged files with the safe config, re-stages them, then runs the
risky config as a report and aborts if it finds anything.

Two flags that are load-bearing and look optional:

  --path-mode=intersection  narrows the config's Finder to the staged files.
                            Without it, passing paths makes PHP-CS-Fixer ignore
                            the Finder entirely and format on CLI paths alone.
  --config                  PHP-CS-Fixer would discover the same file on its
                            own, but given more than one path it refuses to
                            guess: "For multiple paths config parameter is
                            required". Omitting it fails on any commit that
                            touches two files.

A file that is both staged and modified is refused rather than formatted. Doing
otherwise would format the working-tree copy and re-stage it, quietly pulling
unstaged edits into the commit — the classic version of this bug.

Not the enforcement boundary: --no-verify skips it and a fresh clone lacks it
until composer install runs. CI is what holds the line.

### Retire tools/check-conventions.php

`87c6f73f` · 2026-09-24

Both its checks are now covered, but by different configs, and the split is the
interesting part:

  unused imports  ->  no_unused_imports   safe config, fixed automatically
  T[] docblocks   ->  phpdoc_array_type   risky config, reported only

The second is better than what the script did rather than merely equivalent.
The script refused T[] and made a human choose between list<T> and array<K, V>;
auto-fixing would have silently written the weaker array<T> and thrown that
choice away. Reporting keeps the human in the loop and shows the diff — which
is how the 11 array<T> narrowings in the previous commit were found.

Retired only after both preconditions held: no_unused_imports is a no-op on
src/, and the script itself still passed.

Adds the composer scripts: style, style:report, test:style, hooks:install.
test:style and style:report join `ci` where test:conventions left it.

### Narrow array<T> to list<T> where the keys really are sequential

`c6c30ae9` · 2026-09-24

The first run of the risky report found 11 files promising only array<T> for
methods that return a list. tools/check-conventions.php could not see these: it
refuses T[] and accepts either array<T> or list<T>, so array<T> passed.

This is the report/fix split doing what it was built for. Auto-fixing would have
been the wrong shape in both directions — phpdoc_array_type silently writes the
weaker array<T>, and phpdoc_list_type asserts sequential keys it cannot verify.
Reported instead, read, then confirmed by PHPStan before committing.

Also corrects two claims in the risky config that were wrong: all 235 files do
have declare(strict_types=1) — in examples/validate-with-rules.php it sits below
a file docblock rather than on line 2 — and phpdoc_list_type was not zero-churn.

Both configs now report clean.

### Keep `new class(` unspaced, and drop a stray blank line

`fb9add5c` · 2026-09-24

PSR-12 writes `new class ()`. This codebase never puts a space before an
argument list — the same reason closure_fn_spacing is already set to none for
its 67 `fn(` sites. Both anonymous classes write `new class(`, so matching them
is what keeps the rule consistent rather than what breaks it.

Also removes a double blank line after a namespace declaration.

`composer style` is now clean across all 235 files.

### Parenthesise compound ternary conditions

`7641578c` · 2026-09-24

`$a === 1 || $a === 2 ? x : y` becomes `($a === 1 || $a === 2) ? x : y`.

The parentheses are for the reader, not the parser: && and || already bind
tighter than ?:, so this changes nothing about evaluation. That is the point —
seeing where the condition ends should not require knowing the precedence table.
Two sites in Facade.php were already written this way; this makes it the rule.

Only && and ||. A single comparison is left alone, which was a decision rather
than an oversight: === binds tighter than ?: in exactly the same way, but one
comparison already reads as one unit, and covering every comparison would have
touched 66 further sites for no gain. The reasoning is recorded on the fixer.

Safe by construction. The parentheses would NOT be a no-op for `and`/`or`/`xor`,
which bind looser than ?: — there, wrapping the condition changes the meaning.
The fixer never triggers on those, there are zero in the repo, and the risky
config's logical_operators reports the day one appears.

No rule for this exists in PHP-CS-Fixer, kubawerlos, PedroTroller or Slevomat.

### Put declare(strict_types=1) on line 2 in examples/

`05ca9d6b` · 2026-09-24

All 212 files in src/ and tests/ open with the declare immediately under the
open tag. Ten of the twelve examples had a blank line there instead, so the
examples did not look like the code they demonstrate.

No built-in rule does this. PSR-12 wants the blank line, and setting
blank_line_after_opening_tag to false only stops the rule inserting one — it
does not remove an existing one.

(examples/validate-with-rules.php has no declare at all. That is a behaviour
change, so the risky config reports it rather than this fixing it.)

### Order imports nearest-first

`9281165e` · 2026-09-24

Four tiers, one unbroken block, alphabetical within each:

  1. this project            Meraki\Schema\*
  2. other Meraki packages   Meraki\*
  3. everything else scoped  Brick\*, PHPUnit\*, Uri\*, …
  4. the root namespace      Countable, Stringable, Closure, …

There was no ordering convention before — imports were appended where needed,
and 47% of src/ files and 83% of tests/ had drifted out of any order at all.

ordered_imports cannot express this: imports_order distinguishes only class,
function and const, with no grouping by prefix. So this is a custom fixer, and
ordered_imports is on the never-enable list because it would flatten the tiers.

Tier 3 deliberately mixes PHP's own namespaced classes with third-party
packages. Nothing in the token stream separates Uri\Rfc3986\Uri (PHP 8.5) from
Brick\DateTime\Clock (composer), and a hand-maintained list of PHP's namespaced
classes would be wrong every time PHP adds one. Merging them makes the whole
classification mechanical.

This commit also registers the other two custom fixers in the config; their
changes land in the next two commits.

### Use single quotes where double quotes buy nothing

`5d0fc351` · 2026-09-24

A double-quoted string with nothing in it that needs double quotes is a question
the reader has to answer. 11 files, mostly printf format strings in examples/.

### Remove imports that do nothing

`d5f5e887` · 2026-09-24

58 files carried imports for classes in their own namespace, where the short
name already resolves. Most are Meraki\Schema\Field\MalformedValue imported from
inside Meraki\Schema\Field (21 files), and five test files importing
Meraki\Schema\Facade while themselves living in Meraki\Schema.

tools/check-conventions.php could not see these. It counts an import as used if
its short name appears anywhere later in the file, which for a same-namespace
import it always does — the name is in use, the import just is not what makes it
work. It reports the tree as clean today and did so before this commit.

Verified rather than assumed: only `use` lines are touched in the whole diff,
and PHPStan (level 6 on src, plus the tests config), 1846 tests and all 12
examples are green afterwards.

### Apply the rest of PSR-12

`85c9bc6d` · 2026-09-24

Blank lines after a class opening brace, blank line after namespace, class
declaration spacing, parentheses on argument-less `new`, and one import per
statement — which expanded the three group-use statements in tests/Rule/.

### Normalise indentation and trailing whitespace

`eeb116d9` · 2026-09-24

Tabs were already universal in src/ and tests/. The exceptions were
src/Field/HasParts.php, which was space-indented throughout, and three of the
four tools/ scripts — check-examples.php was tabbed and the other three were
not, so tools/ disagreed with itself.

Content is untouched: `git diff -w` on this commit is empty.

### Add PHP-CS-Fixer with a safe-fix / report split

`602fc3f4` · 2026-09-24

Nothing enforced formatting. docs/CODING-STYLE.md said so outright — "formatting
is whatever the editor and PHPStan already enforce" — and the drift was
measurable: 47% of src/ files had unsorted imports, one file was space-indented
in a tab codebase, and 58 files carried imports that do nothing.

Two configs, because "fix it for me" and "this might change behaviour" are
different requests:

  .php-cs-fixer.dist.php        setRiskyAllowed(false), writes
  .php-cs-fixer-risky.dist.php  setRiskyAllowed(true), --dry-run only

Nothing that could alter behaviour is ever applied automatically. The risky set
is zero-churn today apart from one examples/ file missing declare(strict_types=1);
it is insurance against `sizeof`, `and`, and `T[]` docblocks, not cleanup.

tools/check-conventions.php argued a coding-standard package was "not worth ~15
transitive dependencies to answer two questions". That was right while the
questions numbered two. This encodes about thirty, and the real cost is 34
packages — more than double what was rejected. What changed the answer is that
this fixes rather than reports.

Config choices verified against the tree rather than assumed: concat_space
leaves the 36 leading-dot continuation sites alone, binary_operator_spaces with
'=>' => null preserves the aligned data-provider tables, and
closure_fn_spacing: none keeps all 67 `fn(` sites as they are.

### Record indentation and the 110-column limit in .editorconfig

`00eda572` · 2026-09-24

Tabs at a display width of 4 were already universal — 222 of 225 files — but
nothing wrote it down, so the three space-indented files in tools/ drifted
without anything noticing.

The column limit stays soft and stays here. PHP-CS-Fixer has no line-length
rule, and the right fix for a long line is often a shorter name or fewer
parameters rather than a wrap, which is not a decision a fixer can make.

### Make git and the working tree agree on line endings

`33bfdecd` · 2026-09-24

175 of 228 PHP files were CRLF in the working tree while the index was LF
(core.autocrlf=true, no .gitattributes). Nothing noticed, because git
normalised on the way in and CI checks out LF on Linux.

It stops being invisible the moment a formatter runs: PHP-CS-Fixer compares
bytes, so it would report all 175 files as needing a line-ending fix on every
local run, forever, while CI saw a clean tree.

The renormalize is a no-op against the index, so this commit changes no content.

## v2.0.0-alpha.1 — 2026-09-24

### Update history

`9a8749be` · 2026-09-24

### Say what the 2.0 alpha changes, and what it does not freeze

`827c7fbe` · 2026-09-24

An upgrade guide is the only compatibility story a breaking major has an
audience for, and an alpha's audience is precisely the people attempting
that migration. UPGRADING.md maps every removed 1.x name to what replaces
it, organised around the one idea the rest follows from: the definition is
immutable, so what you used to write onto a field is now an argument to
validate(), and what you used to read back off it is now on the result.

Two corrections fell out of checking the claims rather than repeating them.
Scopes *can* address inside a structured value — PartScope landed and
"when the address is in AU, require the state" works end to end, so both
LIMITATIONS.md and the note this was written from were describing a
limitation that no longer exists. What is genuinely still open is a part of
a part, and collection items, which is why the scope string format is the
one part of the API deliberately left unfrozen.

The collection-index entry claimed it was fixed in 2.0 while sitting under
"not yet implemented" with a 1.x sample; moved to "recently fixed" with a
sample that was run. Also drops a sentence duplicated inline in API.md, and
a phpunit.xml comment deferring coverage metadata until a seam that has
since landed.

### Update history

`19c2c209` · 2026-09-24

### Fix wording on some docs

`ca822b5f` · 2026-09-24

### Remove unused import

`e2c39312` · 2026-09-24

### Add proper tests confirming desired shallow copy behaviour

`e4921a64` · 2026-09-24

### Remove references to obsolete copyForRequest() method

`6bf8a736` · 2026-09-24

### Update listing

`cd2de5ae` · 2026-09-24

### Remove ambiguity in testing clock references when cloning

`2dcbaa3d` · 2026-09-24

### Remove unused import statement

`9508546e` · 2026-09-24

### Remove outdated doc block

`f94ae1c0` · 2026-09-24

### Remove ambiguity in some ternary expressions and clone

`c8c79ad4` · 2026-09-24

### Remove support for magic getters on classes as input to schema

`c5acf874` · 2026-09-23

### Update changelog

`bca6a588` · 2026-09-23

### Reach PHPStan level 6, and check what it cannot

`a58077bd` · 2026-09-23

Level 6 is missing generic and iterable types in docblocks rather than unsound
code, and 91 errors turned out to be four things.

67 of them were one: `Field` is `@template AcceptedType`, so every mention of a
bare `Field` — meaning "a field of any kind", which is nearly all of them — was
reported. Annotating 67 sites with `Field<mixed>` would have said it 67 times;
`@template AcceptedType of mixed = mixed` says it once, on the declaration, and
is what a bare `Field` has always meant. `AtomicField` and
`AggregatedValidationResult` get the same.

12 were arrays whose value type was never written down: the request payload
(`array<string, mixed>`), File's two media-type lists, and Rule/Condition's
`$data`. Written out, so `matches()` now says what it takes.

Three were docblocks that were *wrong* rather than missing, which is the part
worth having found: `@return \ArrayIterator<T>` twice and `@implements
IteratorAggregate<Field>` once, all three on classes taking two type parameters.
They had been accepted for as long as nothing looked.

The rest were iterators with no annotation at all — Rule\Set, Message\Set,
Collection\Value.

Level 7 is 39 errors of a different kind again — 29 argument.type, 5
return.type, 5 assign.propertyType, most of them list-versus-array artifacts of
the spread operator — and is tracked rather than done here.

## tools/check-conventions.php

PHPStan does not report unused imports or `string[]`-style array types at any
level, and that is not an oversight: an unused import is not unsound, and
`string[]` is a type it understands. They are house style, so they get a check
of the shape tools/ already holds three of, rather than ~15 transitive
dependencies for a coding-standard package.

It found 26 unused imports — 15 in src, 11 in tests — almost all left behind
when a method was deleted or a responsibility moved into a value object. Three
in PhoneNumber and one in Date were from the dead code removed two commits ago.

It also found five `Type[]` where the rest of the library writes `list<Type>`.
Those are not the same claim: `Type[]` says nothing about the keys, and every
one of these really is a list.

Fixed by hand, since no tool should guess at them: seven `@var Type $name` where
the name repeats the declaration under it, and Text.php's method docblocks,
which were the last of the old style — tags with no blank line before them,
descriptions restating the signature, and the same `@throws
InvalidConfiguration` written twice in one block, twice.

One real bug on the way past: tools/check-references.php printed dead `{@see}`
references and then exited 0, so it could not fail CI. It exits 1 now.

### Update changelog

`647135d0` · 2026-09-23

### Reach PHPStan level 5 without suppressing anything

`683c5ccd` · 2026-09-23

src was stuck at level 3. Levels 4 and 5 cost 101 errors, and 95 of them were
one analyser blind spot: a field is a readonly class, so PHPStan types every
property as the value the constructor assigned it, and it cannot see
Definition::with(), which changes them by cloning with a string-keyed array. A
`public ?int $maxLength` initialised to null was inferred as null and nothing
else, so the `!== null` guard in front of every use of it narrowed to *NEVER* —
guard always-false, body unreachable, method "never returns bool".

Measured rather than assumed, against the real classes:

- a @var or @phpstan-var on the property does not help; the narrowing is of the
  assigned value, not the declared type
- PHPStan 2.2.8 is current and does not model PHP 8.5 clone-with at all, so no
  upgrade fixes this today
- dropping `readonly` fixes it, and would trade a real guarantee for an
  inference detail
- an optional constructor parameter per property fixes it, and would let a
  caller set a bound without the cross-property check its wither does

What works is Field\Definition::initially(), a mixed-returning seam every
configuration property is initialised through. `mixed` means PHPStan keeps the
property at its *declared* type, so a real type error is still caught. 48
initialisers across 18 field types now go through it.

Result: 101 errors to 0 at level 5, and the two blanket ignoreErrors patterns
are deleted. Those were the ones worth being rid of — they matched across
src/Field/*.php, so a genuine inverted guard written the same way would have
been suppressed with them. Three narrow entries remain, each naming one class
and one message, all three removed by PHP 8.6's readonly property defaults.

It costs one thing, and the docblock on initially() says so: a property nothing
ever configures no longer has its dead guard reported. The wither is what makes
a property configuration, so the check that matters is that one exists.

Deleting the two patterns also uncovered what the noise had been hiding — four
genuinely dead members, all left over from the composite-to-value-object
rewrite:

- Date::mustParse(), unreferenced since from()/until() started calling
  LocalDate::parse() directly
- PhoneNumber::numberIn(), ::countryIn() and ::parseForRegion(), whose work
  moved into PhoneNumber\Value

Two more real findings fixed: Collection\Value::valueOf() had a nullsafe on the
left of ??, where ?? already answers null; and Enum::parse() asserted
is_string() on a value that in_array() against a non-empty-string case list has
already proved — a crutch from before cases were restricted to strings.

1846 tests pass.

### A field can come off a schema again

`9b45ab0a` · 2026-09-23

Field\Set gains remove(), which Rule\Set has had all along — so a rule could be
taken off a schema and a field could not. That asymmetry was the whole reason;
nothing was blocked on it.

It takes a name rather than a field, because that is what identifying one costs:
you rarely hold the object you want gone, and making the caller getByName() first
would be friction with no check behind it. It refuses a name it cannot find,
matching getByName() and replace() rather than Rule\Set::remove(), which
silently no-ops — a mistyped name that removed nothing would leave the field on
the schema with nothing to say so. Order is preserved for what remains, for the
same reason replace() preserves it.

**It is not reachable from outside a Facade yet**, and that is worth saying
plainly: Facade::$fields is private(set), so $schema->fields->remove($f) builds a
set that goes nowhere. A Facade::remove() is the other half, and it is not here
because it has a decision in it that this does not — a rule naming a field that
is no longer present fails when that rule fires, which is on a request, the one
place this library works to keep failures out of. Refusing to remove a field a
rule depends on is probably right and should be decided deliberately rather than
inherited from this commit.

### Update changelog

`227228ce` · 2026-09-23

### Hold the exception promise with a test, and document it

`a8c83361` · 2026-09-23

Adds tests/Api/ExceptionTest.php, which makes three claims about src/ rather
than about behaviour:

- every Throwable defined here implements Meraki\Schema\Exception
- nothing throws a generic SPL exception
- no docblock promises one

The source scan is the point. Nothing about writing `throw new
InvalidArgumentException(...)` fails loudly — the code runs, the message is
fine, and the only symptom is a caller's catch quietly not firing for one case
out of ninety. Behaviour cannot see a throw nobody wrote a test for, and those
are exactly the throws this is about.

It immediately found seven I had missed, all of them written `throw new
\InvalidArgumentException` with a leading backslash: three in Enum and four in
Name, plus a @throws on Field::defaultsTo(). Those are migrated, with five new
factories on InvalidConfiguration. Name's four length messages are now the
shared ones.

docs/API.md gains "What raises, and what does not" — the split (a validation
failure is a fact about a request and arrives on the result; these are mistakes
in your code and are found at boot), the one catch that covers the library, and
a table of all eighteen classes. The two examples in API.md and EXTENDING.md
that printed "InvalidArgumentException:" now print the real class name, and five
example catches name the class rather than the SPL base.

1840 tests pass.

### Update changelog

`15228831` · 2026-09-23

### Name the last four, and stop pointing docblocks at generics

`671e5f2e` · 2026-09-23

Finishes the sweep: no src/ file throws InvalidArgumentException or
LogicException any more, and no @throws names one.

- Message\Mf2\NoSuchPack — a pack that is not where the provider was told to
  look. It sits with BadResource and BadMessage rather than in
  Meraki\Schema\Exception because packs are an MF2 idea: a provider reading
  messages from a database has nothing this could describe. Raised where the
  pack is registered, because a provider that shrugged would fall through to
  Silence on every request, and a form with no messages looks like a form whose
  fields all passed.
- Exception\IncompleteVocabulary — a field the vocabulary cannot build from a
  name alone, so it cannot list the keys a pack must supply for it. Refusing
  beats skipping: a skipped field leaves its keys off the list, schema-lang
  missing reports a complete pack, and the field renders with no message.

Three docblocks that described a generic now name what is really thrown —
Scope::parse() (InvalidScope), Comparable::compareTo() (IncomparableValues) —
and PhoneNumber's LogicException import had nothing left to import.

### Name the field-configuration failures

`b3a41f9a` · 2026-09-23

Fifty-four hand-written throws across thirteen field types become one class,
InvalidConfiguration, with a factory per mistake. Grouping them this way makes
three things visible that were not:

The same mistake was being written six different ways. "A minimum length cannot
exceed the maximum" was in Text, Password and EmailAddress verbatim; File and
Collection said the same about sizes and counts; Uri said it as "Minimum length
cannot be greater than maximum length". Four shared factories now take the noun
the field measures in — length, file size, count — so every field refuses the
same mistake in the same words.

Most of these are coherence failures rather than type failures: a minimum past
its own maximum, a step finer than the precision it steps through, a bound in a
currency the field does not take. PHP's types cannot state any of that, which is
why there is a wither check at all, and the class docblock now says so once
instead of thirteen docblocks saying nothing.

Money's ten are all per currency, and the class says why: a field taking AUD and
JPY holds two minimums, two maximums and two scales, so a message that did not
name one would send the author to the wrong line.

Three messages changed on the way, none of them asserted on except the first:

- Text::mustMatch() said 'Invalid regular expression provided.' It now says what
  was given and what the argument takes, matching what InvalidRule already says
  for the matches() condition. TextTest asserts the new wording.
- Uri's four length messages are now the shared ones. Its "must be a positive
  integer" was wrong as well as inconsistent: the check is < 0, and zero is
  allowed.
- Number::scaleTo() said 'Scale must be a non-negative integer' with no full
  stop; Uuid::allowVersions() said 'Version must be -1, 0, or 1 to 8' without
  saying that 0 is the nil UUID and -1 the max. Both now do.

1837 tests pass. Coverage falls to 71.5% from 74.6%: the factories are lines,
and the ones for mistakes nothing tests yet are uncovered ones.

### Name the constraint, default and comparison failures

`f774348e` · 2026-09-23

Three more concepts out of hand-written strings and into classes, each grouping
throws that were saying the same thing in several places:

- InvalidConstraint — a constraint that cannot be reported. Both members guard
  one promise: a field reports each constraint under a name and a consumer looks
  the result up by it, so a nameless or repeated name breaks the lookup rather
  than the check. Covers Constraint, Constraint\Set and ResolvedField, which were
  each writing their own sentence for the duplicate case.
- InvalidDefault — a default the field that declares it could never hold. Raised
  where the default is written, because that is the point of it: an authored
  default is trusted by construction so a request never re-checks it.
- IncomparableValues — two values with no honest ordering. Six Value classes had
  a one-line throw apiece; the sentences now live together, where it is visible
  that they are the same sentence. Money keeps its second case, which is the
  interesting one: same type, different currency, and no exchange rate to be had
  from either amount.

IncomparableValues also carries becauseTheyAreNotAlike(), so a Comparable this
library has never heard of raises what the built-in ones do rather than falling
back to InvalidArgumentException.

Messages are verbatim and every class extends what it replaced. 1837 tests pass.

### Update changelog

`630f6d04` · 2026-09-23

### Give the rule failures real exception classes

`5b662524` · 2026-09-23

Every hand-written throw in the rule machinery now names what went wrong, in
three classes split by what a caller can do about it:

- InvalidRule (InvalidArgumentException) — a rule that cannot do what it says.
  The reason almost all of these are checked at all is that a rule which cannot
  fire raises nothing, so it is indistinguishable from one whose condition never
  held. They are raised where the rule is written rather than on the request
  where it would have fired.
- IncompleteRule (LogicException) — a draft asked for something it is not ready
  to give. Nothing about the values is wrong; the sequence of calls is.
- NothingToValidate (LogicException) — validating a schema with no fields. Not a
  validation failure: no input could make it right, so there is nothing to report
  back to whoever filled the form in.

Every message is preserved verbatim and every class extends what it replaced, so
the tests catching InvalidArgumentException and asserting on message text are
untouched. 1837 tests still pass.

Two things tightened on the way past:

- Facade::assertRuleCanBeApplied() caught InvalidArgumentException around the
  resolver and rethrew. It now catches InvalidScope|UnknownField, which is what
  the resolver actually throws, and carries the cause through as previous.
- extractData()'s docblock claimed InvalidArgumentException for an array. The
  signature is object|null, so PHP raises TypeError; the docblock now says so.

### Give the scope and field-name failures real exception classes

`ac6d3f65` · 2026-09-23

The first of several passes over the ninety-odd generic throws. This one takes
the ones about *naming things*, following the shape Exception\InvalidScope
already set: a final class in Exception\, extending the SPL type it replaces,
implementing the Exception marker, with a named factory per distinct failure.

- InvalidScope gains seven factories and absorbs every remaining scope failure —
  ScopeResolver's three, PropertyScope's two, PartScope's one, and the one in
  Message\PartedSet, which is the same question asked of a message set.
- InvalidFieldName, for a name that cannot identify a field.
- UnknownField and DuplicateFieldName, kept apart because a caller catching one
  almost never wants the other: "look this up and tell me if it is missing" and
  "add this and tell me if it is already here" are different questions asked at
  different times.

MalformedValue, BadMessage and BadResource already had the shape and were missing
only the marker, so `catch (Meraki\Schema\Exception)` now reaches them too.

Every message is preserved verbatim and every class extends what it replaced, so
the sixty tests catching InvalidArgumentException and the thirty asserting on
message text are untouched. That is deliberate: the refactor is worth nothing if
it has to be verified by reading.

The @throws docblocks now name what you would actually catch, which is the half
of this that pays off at a call site rather than in a catch block.

### One way in to a value and its parts

`b27684d8` · 2026-09-23

ValueScope::of('billing')              // #/fields/billing/value
    ValueScope::of('billing', 'country')   // #/fields/billing/value/country

A part is always inside a value and has nowhere else to hang from, so nobody
should have to know PartScope exists before they can address one. PartScope::of()
stays for building one directly; this is the spelling the docs, tests and
examples now use.

### The classes stay distinct, and that is the point

They are siblings, not a subtype pair, and the factory hands back whichever the
arguments describe rather than collapsing them. Three places dispatch on the
difference, and two exclude parts for the same reason — a part resolves to
whatever the value put in it, not to a ParsedValue:

- Comparison::wouldBeParsed() reads a literal expectation through the field for a
  whole value. For a part it must not: putting 'AU' through Address::parse() gives
  null, so equals('AU') on a country would compare against nothing and never hold.
- Ordered::whyItCouldNeverHold() checks the field's value class for Comparable,
  which says nothing about the type of one part.

Which is why PartScope extends Scope rather than ValueScope. Path nesting is not
type nesting: a country is *located* inside an address, not *a kind of* address,
and asserting the second would silently break both checks above.

### The narrowing is checked, not hoped for

    /** @return ($part is null ? ValueScope : PartScope) */

Verified against the real classes at level 6. `ValueScope::of('billing')->part` is
an undefined property before it is a runtime error, and passing a part where a
whole value is wanted is caught too. PHPStan and Psalm both implement conditional
return types, and the union never reaches a call site.

At runtime the type really is the union, which changes nothing: every dispatch on
a scope's kind is an instanceof and always was.

### Restrict enum cases to strings

`2a5462fd` · 2026-09-23

They could be any scalar, provided all of them were the same one. That looked
more general and was a trap, and the evidence is blunt:

    int enum,  given 2       -> read as 2
    int enum,  given '2'     -> UNREADABLE
    bool enum, given 'true'  -> UNREADABLE
    bool enum, given '1'     -> UNREADABLE

An HTML form submits "2", never 2, and membership is decided strictly — so an
enum of integers was unreadable for every form submission there has ever been.
It worked only for a JSON client that had sent a real integer, which makes it a
surprise rather than a feature. Nothing in this repository used one.

Nothing is lost by closing it:

- Booleans are a Boolean field — a two-case enum with a name, and with
  mustBeAccepted() and a matcher that suits yes-or-no.
- A regular numeric sequence is a Number with inIncrementsOf(), which says
  "every multiple of five from ten to fifty" in a way a list of cases cannot.
- An irregular one is a list of labels that happen to look numeric, and '3.14'
  round-trips through a form, JSON and a database column where 3.14 does not.

The empty string goes too, and that one is worth naming on its own: it is what a
select's placeholder option submits when nothing was chosen, so a case spelled
that way would be chosen by everybody who chose nothing.

It also settles the rendering question from the commit before this. Enum\Value
had to decide what a `false` looked like, because PHP casts it to the empty
string and a template would have shown nothing at all. With a string case there
is no decision left: __toString() returns it, and $case is the same string on a
property — kept for the reason Text\Value keeps $text beside its own.

### Give an enum's chosen case a string form

`1bf618a4` · 2026-09-23

You could not write `(string) $result->forField('theme')->value` — it was a fatal,
and the cookbook had to reach past it to `->case`. Now you can.

`$case` stays, and is not redundant. It holds the case at its *declared* type, so
an enum of integers compares as integers and displays as digits:

    $value->case === 2      // true
    (string) $value         // '2'

That is the arrangement every other value already has — `Number\Value` keeps a
BigDecimal on a property and renders through __toString, and so do the temporal
types. Reach for the property to compare, the string to display.

### The one scalar that needed spelling out

`Enum` allows any scalar case, provided they are all the same type — so booleans
are legal, and PHP casts false to the empty string. A template would render
nothing at all and the chosen case would appear to have gone missing. So a
boolean case renders as "true" or "false" rather than "1" and nothing.

Worth saying that a two-case boolean enum is a Boolean field written the long way
round, and Boolean has mustBeAccepted() and a matcher that suits it. This makes
the unusual case render correctly; it is not encouragement.

### The matcher followed on its own

Being Stringable moves Enum from Matcher\Basic to Matcher\Text, so an enum field
now offers `contains` and `matches` — useful for a prefixed code, redundant
beside `isIn` for most enums. Still no order: a list of options is not a ranking.

I did not have to notice that. `each_field_offers_exactly_the_questions_its_value_can_answer`
went red the moment the value gained a string form and `when()` still said Basic,
which is exactly what it is for.

### Simplify docs

`0ef9d957` · 2026-09-23

### Add a proper exception for handling invalid scope paths and cleaned up docs

`370bf472` · 2026-09-23

### Tidy docs meanings

`f1670023` · 2026-09-23

### Clarify comments

`e9bdfc05` · 2026-09-23

### Regenerate the changelog

`e7140ce0` · 2026-09-23

### Add a cookbook of common form patterns

`895071f8` · 2026-09-23

Fifteen patterns a form actually needs, one snippet each, sitting between the
README's first-schema walkthrough and API.md's full surface. The gap it fills is
real: the answer to "how do I make one field required when another says so" was
a 120-line example or nothing.

The two you asked for are the first two of substance — branching on a choice, and
getting back what somebody typed so a redrawn form can echo it. The rest are the
ones that come up next: which row of a collection failed, which part of an
address, telling a rule-driven optional from an authored one, prefilling from a
stored record.

Every snippet was run against the current API before it was written down. That
turned up one of my own: Enum\Value has no string form, so you read its "case"
property rather than casting it — which is now in the doc rather than waiting to
surprise somebody.

Named cookbook rather than quick start deliberately: a quick start is the linear
install-then-first-schema path, and the README already is one. This is the
per-task reference you come back to.

### Finish the nineteen, and make parse() raise rather than return null

`db4dd8a6` · 2026-09-23

The remaining ten values enforce their own invariants, and the contract is
tightened now that every field satisfies it: `parse()` returns a `ParsedValue`,
never null.

### A value takes what the field takes

`Money\Value` was `(string $currency, BigDecimal $amount)` while the field
accepted `{currency, amount}` — so a field read input and a value trusted
whatever it was handed. Now the constructor takes the record, with `Value::of()`
as the readable way to write one by hand:

    $price->when()->isAtLeast(Money\Value::of('AUD', '10.00'))

`of()` builds the record and hands it to the constructor rather than bypassing
it, so the invariant is enforced in one place. Same for `File`, `CreditCard` and
`Address`.

### What moved, and what could not

Most shape checks are facts about the value and moved wholesale: the email
grammar, the URI parse, the ISBN canonicalising in the example that prompted
this, and Address's country pairing — `4700` is Rockhampton in Australia and
something else elsewhere, so an address naming no country never described a
place under any configuration.

Three kept something on the field, all for the same reason — the check is a fact
about the *field*:

- `Enum` owns membership of a case list a value cannot see.
- `Time` and `DateTime` apply a precision policy that is configuration, so their
  values take either a string to parse or an already-adjusted Brick object.
- `Collection` reads each row against its template.

`Uri\Value` now keeps the scheme it had to parse anyway, so `allowedSchemes`
stops re-parsing a string already proved to be a URI. `Uuid\Value` folds case at
construction instead of in `strcasecmp()` on every comparison, so two equal UUIDs
finally read back as the same string.

### parse() no longer returns null

There was one caller left that wanted the reason — `defaultsTo()` — and it was
the only audience that could act on it. `Definition` decides who absorbs the
exception: caught on the request path, raised at definition time. A field author
writes `parse(): Value` and no try/catch, which is less to know than remembering
which failures returned null.

`Password::validate()` overrides the lifecycle to report entropy and was calling
`parse()` directly; it goes through `Definition::readable()` like everything
else, which is the hazard of having two paths and the argument for having one.

Docs updated where they taught the old contract: FIELD-API, EXTENDING, DESIGN
and examples/custom-field.php — where the hyphen-stripping now sits in the value,
which is where this started.

### Let a value refuse to be built out of something it is not

`ffa5115e` · 2026-09-23

The first nine of nineteen. A value's constructor now enforces its own invariant
and canonicalises, `parse()` raises rather than returning null, and `Definition`
decides what happens to the exception.

### Why the value has to own it

`EmailAddress\Value` lower-cases the domain, and `equals()` depends on that having
happened. The lower-casing lived in the field, so:

    $field->resolvedValueFor('kim@EXAMPLE.TEST')   // kim@example.test
    new Value('kim', 'EXAMPLE.TEST')               // and not equal to it

An invariant a value's own equality relies on cannot be enforced outside it.
`Uuid\Value` had the same fault in a different shape — `strcasecmp()` inside
`equals()` — which got comparison right and left everything else wrong: two equal
UUIDs still read back as different strings. RFC 9562 says which spelling is
canonical, so it is folded once at construction and `equals()` is `===` again.

### Why parse() raises

The definition-time check could only say *"The default for "email" is not a value
it can hold"*, because `parse()` had thrown the reason away one frame earlier —
while the constraint branch three lines below it named the constraint that failed.
The weaker message was the one whose audience could act on it. Now:

    The default for "email" is not a value it can hold. EmailAddress: the part
    before the @ is 65 octets and RFC 5321 allows 64.

`Definition` decides who absorbs it, not the field: the request path catches and
reports `null`, the definition path lets it through. Every call site of `parse()`
was already inside the lifecycle, and FIELD-API.md argues these are not the field
author's decisions to make — one field catching where its neighbours bubble would
report a bad default as a silent null.

So a field author writes `parse(): Value` and no try/catch at all, which is less
to know than the old contract, where they had to remember which failures returned
null.

`MalformedValue` is its own type because it is caught, and a catch is only as good
as its aim: Brick, libphonenumber and commerceguys all throw
`InvalidArgumentException`, and `Money::parse()` was swallowing their bugs as
"the user typed something unreadable". It names the value *class* rather than a
kind in words, because the words belong to a language pack.

Also here: `Uri\Value` keeps the scheme it had to parse anyway, so `allowedSchemes`
stops re-parsing a string already proved to be a URI.

Ten fields still to go, and the signature is still `?ParsedValue` until they are.

### Reunite parse()'s docblock with parse()

`2d321cf7` · 2026-09-23

The script that added when() to nineteen fields backed up over the preceding
docblock so the new method landed above it. The one that did the same to the
example did not, so parse()'s explanation ended up orphaned above when()'s.
Only the example was affected; the shipped fields are fine.

### Give an email address a string form

`be08d4c5` · 2026-09-23

`EmailAddress\Value` had no `__toString()`, and its docblock said so deliberately:
the split form is the field's internal representation, and an `address()` method
was how you asked for the text.

That argument does not survive contact with the rest of the library. `Uri\Value`
and `Uuid\Value` are equally internal representations and both read back, and the
existence of a single canonical spelling is the whole test — which `address()`
proved by being able to produce one.

It also cost something, which only became visible once a field's matcher was
decided by what its value can answer: no string form meant `Matcher\Basic`, so an
email field offered no `matches` and a rule could not check a domain. That is
among the likelier things to want from an email address.

So `address()` becomes `__toString()` — one spelling, the one the language already
knows about — and the field moves to `Matcher\Text`. The capability test picked the
move up on its own, which is what it is for.

The two absences that remain are deliberate, and the reason is specific rather than
"the value is internal": `Password\Value` and `CreditCard\Value` have no string form
so that no rule can read a secret by accident.

### Separate the trait imports from the constructor

`2bfabf49` · 2026-09-21

Whitespace only, in the four matcher classes. They were written by a script that
did not leave a blank line after the last `use`, which every other class here
does.

### Put the field on the left, on both halves of a rule

`0d464e08` · 2026-09-21

$schema->addRule(
        $parcelWeight->when()->isAtLeast(Weight::of('5.00', 'kg'))
            ->then($insurance->makeRequired()->mustBeAccepted()),
    );

One principle behind both changes: **the type flows from the receiver, never from
the argument**. PHP cannot vary a return type by argument, so `when($field)` and
`then($field)` could never hand back anything that knew what kind of field it had
been given. Moving the field to the left is what makes either half type-safe, and
it is the only thing that could have.

### when(): only the questions the value can answer

`Field::when()` returns one of four matchers, picked by what the field parses to:
Comparable earns the five ordered questions, Stringable earns the two textual
ones, and everything gets identity and presence. So `$text->when()->isAtLeast(3)`
is a call to a method that is not there — absent from completion, refused by
PHPStan, fatal at runtime — rather than a rule that reads sensibly and is refused
later.

The verbs live in traits rather than on a base class because PHP forbids
*narrowing* a parameter through inheritance or an interface. A shared parent
declaring `isAtLeast(mixed)` would permanently prevent a field from saying it
wants `Money\Value`. Composed from traits, each matcher owns its signatures and a
field can go further when its bound is hard to guess.

Four classes and nineteen one-line declarations, not nineteen classes. A test
checks every declaration against its value's actual capabilities, so a field that
gains Comparable and forgets to widen its matcher fails CI rather than silently
offering less than it could.

`$schema->when('age')` still works for a field named by string or a scope pointing
at a part. It cannot resolve a type, so it answers with all twelve and leans on
the check that runs when the rule is added.

### then(): the field, configured

`thenRequire()`, `thenMakeOptional()` and their else-halves are gone, along with
`Outcome\MakeRequired` and `Outcome\MakeOptional`. You call the field's own
withers and the rule records the difference:

    ->then($terms->makeRequired()->mustBeAccepted())
    ->then($discount->maxValueOf(50))

Which means **every configuration method a field has is already a rule outcome**,
including on a field type this library has never heard of. The old shape had a
fixed list of verbs, so anything not on it — "and it must be accepted" — was
simply unreachable from a rule.

docs/ROADMAP.md argued this could not work, and its two objections were real:
snapshots do not compose, and snapshots lose intent. Both are answered by storing
the *difference* rather than the field. Outcome\Reconfigure diffs the copy
against the authored field when the rule is added and keeps the properties that
changed, so two rules touching one field merge instead of clobbering, and
`$applied->outcome->changes` says what a rule did more precisely than
`instanceof MakeOptional` ever did. That section now records the correction
rather than the refusal.

Identity is what distinguishes `then($field)` from `then($field->makeOptional())`
when the field was already optional. A wither always clones, so the same instance
means no wither was called — refused. An empty *difference* is allowed, because
restoring a state explicitly is how an else-branch stays readable beside its
then-branch, and it stops being a no-op the moment another rule touches the field.

`thenIgnore()` is the one named verb left. Ignoring is about a request — the input
never reaches the field — rather than about the definition, so no wither expresses
it.

### Elsewhere

- `Field::when()` and `Field::reconfiguredWith()` are new interface members. A
  field of your own must declare the first; docs/EXTENDING.md and FIELD-API.md
  now say so, and examples/custom-field.php shows it.
- docs/LIMITATIONS.md's rough-edges table listed nine warts, eight of them fixed
  since it was written for the 1.14 line. It now lists the two that are real, one
  of which this change surfaced: EmailAddress\Value has no string form, so an
  email field offers no `matches` — a gap rather than a refusal, unlike Password
  and CreditCard where the absence is the point.

### The ten missing rule matchers

`78cc347a` · 2026-09-20

`equals` and `notEquals` were the only questions a rule could ask. The other ten
from the vocabulary in docs/ROADMAP.md now exist: isAtLeast, isGreaterThan,
isAtMost, isLessThan, isBetween, isIn, contains, matches, isEmpty, isNotEmpty.

The five ordered ones are `Comparison\Comparable` and nothing else — each is
`compareTo()` and one question put to the `Order` it returns. That interface was
split out for exactly this and had no caller in the rule engine until now: six
value types implemented `compareTo()` and no rule could ask. A seventh orderable
type gets all five for free.

Inclusivity is in the names, because getting it wrong is the defect nobody
notices for a year — the rule fires most of the time and the one input it has
wrong is the one nobody tries. `isAtLeast` and `isAtMost` include their bound;
`isGreaterThan` and `isLessThan` do not; `isLessThan` is what `Date::until()`
corresponds to, so the rule surface and the field surface agree about where a
range ends.

`isBetween` is inclusive at both ends, and not by choice: it holds an `isAtLeast`
and an `isAtMost` and asks both, so its inclusivity is inherited rather than
picked and cannot drift from theirs. Prose alone could not have promised that.

### Refused where they are written, not left to never fire

A rule that can never hold raises nothing while looking like a working rule,
which is the defect `Condition\Comparison` exists to have fixed. So each new way
of writing a dead rule is caught at `addRule()`:

- an ordered matcher on a field with no order — `when($username)->isAtLeast(3)`
  reads plausibly and is dead, and a field's value class says so without a
  request;
- a bound the field cannot hold, including one buried in an `isIn` list, since
  the check now sees through the list rather than at it;
- `isIn([])`, `contains('')` and a pattern that does not compile — each holds for
  every request or for none.

That needed `expectationIsReadable(): bool` to become
`whyItCouldNeverHold(): ?string`. The reasons differ and the caller cannot infer
which applied: "that field cannot hold 'eighteen'" and "what that field holds has
no order" are different mistakes needing different corrections, and one message
covering both would be wrong about at least one of them.

### Two narrowings, both deliberate

**`contains` is text only**, where the vocabulary said "text, collection". A
collection's rows are *records*, and `contains('SKU-1')` has no honest reading
over a record — the needle would have to name a field as well as a value, which
is a different matcher with a different signature. One verb meaning membership or
substring depending on what the request happened to submit, decided at runtime,
is worse than not having it.

**`isEmpty` is not `equals(null)`.** A null expectation means the field's authored
default, deliberately, so on a field with one they ask different questions. It
also does not use PHP's `empty()`: a boolean `false` is a submitted answer and a
number `0` is a quantity.

One consequence worth knowing rather than fixing: `Password\Value` and
`CreditCard\Value` have no string form on purpose, so `contains` and `matches`
never hold for them. A rule reading the text of a secret should be hard to write
by accident.

### Let the changelog check tolerate exactly one missing entry

`559642a9` · 2026-09-20

A changelog cannot list the commit it is committed in. The file has to be
written before that commit exists, and every entry carries a hash — so amending
the commit to carry a freshly generated changelog invalidates the hash it has
just recorded. The loop does not converge, which is why the check has been
failing rather than nobody having run it.

So "up to date" now means *up to date as of the parent*, which is the most it
can truthfully assert. The tolerance is exactly one commit wide: a file two
behind still fails, and so does one generated against a different history.

Which makes the order matter — regenerate, stage, commit — and the usage block
now says so, because doing it the other way round lands the file two behind and
the check is right to complain.

### Regenerate the changelog

`6d5df13e` · 2026-09-19

Generated from the commit history, so it can only be written after the commit it
describes exists. Amending the content commit to carry it does not work: the
changelog embeds each commit's hash, and amending changes the hash it just
recorded.

### Messages as installable language packs, with MF2 as the first format

`a29347a5` · 2026-09-19

Wording becomes part of the core, but as *data you install* rather than strings
this library writes. One integration point — `$fieldResult->messages` — a
`Message\Provider` handed to the schema, and a locale passed to `validate()`.

The split between those last two is the design. A provider is a *source*: built
once, holding every language it can serve, safe to share, registered alongside
the clock. The locale is part of the request, because that is what varies. So
one schema serves a German reader and an English one without being defined
twice, and — the property everything else rests on — a language nobody has can
never change a verdict. Ask for `de-AT` from an English-only pack and you get
every failure you would otherwise have got, with nothing to say about them.

A pack is `.mfr` files and no PHP at all, so a Rust or JavaScript implementation
of this library renders the same sentences. That is what makes "the wording
travels with the schema" true rather than aspirational. Getting one onto disk is
Composer's job: the provider takes directories, because fetching inside
`validate()` would mean a network call in the request path and a supply chain
where a moved repository silently changes what users read.

Nothing about messages is serialised, and nothing needs to be. Message keys are
constraint names, which are already in the document, so a reader holding any
pack for any language can render a schema it has never seen at a cost of zero
bytes. A locale in the document would be worse than redundant — it would pin a
definition to an audience.

MessageFormat 2 has no PHP implementation: `intl` binds MF1, and MF2 lives in
`icu::message2`, which the extension does not expose. `Mf2\Formatter` renders
the subset the packs use — variable expansion, plus quoted literals, because
`list.separator` is ", " including the space and a `key = value` file cannot
carry a trailing one. Everything else is refused loudly at load time. That is
the discipline that makes the class disposable: the swap to a real
implementation is only safe if the subset behaves identically, and leniency is
what would break it. A formatter quietly ignoring `.match` would let packs
accumulate that work today and fail months later, on somebody's form, in a
language nobody on the team reads.

`bin/schema-lang` is what stands in for a compiler when a package is nothing but
data. It reads the vocabulary off this library's own classes — so it cannot go
stale — and catches unimplemented features, keys the library never asks for, and
a message naming `{$minimum}` where the library supplies `{$bound}`. That last
one is what a translator is most likely to write and least likely to notice,
because the sentence reads perfectly until it runs.

Also here:

- `Field\ValueClass`, extracted from `ScopeResolver`. Both it and `Message\Set`
  need to know a field's value class before any request exists, and "does this
  field have parts" is not a question about scopes.
- A fix to `ConstraintNameTest`, which passed an array where an object was
  required and `country_code` where the key is `country`. The address was
  unreadable, so every constraint came back *skipped* — and a skipped constraint
  reports the declared bound, which for that one is null. Both assertions passed
  without the constraint ever having run.
- The docs that said messages were deliberately absent: README, DESIGN,
  COMPARISON and ROADMAP each argued for their absence, and each now has to
  argue for the shape they arrived in instead.

### Rewrite the API doc around the API, and add examples to the design doc

`d6c4de97` · 2026-09-16

API.md was 1099 lines and most of it was the argument that produced the API
rather than the API. The "Proposed API" section, the surface-by-surface
review tables, the open questions -- all of it was transitional and went
stale the moment the transition ended. Now 528 lines, organised as: three
surfaces, defining a schema, the field table, naming, baselines, the field
decisions worth knowing, reading a result, constraints, scopes, and what was
removed.

The reasoning is kept, because that is the expensive part to reconstruct.
What is new is the set of decisions that look wrong until you know why, each
also showing how to use the field:

  Boolean::mustBeAccepted() is field API, not a rule -- it depends on no
  other field, so it is a constraint. The general test is stated.
  Enum is closed and allow() is gone -- it was a mutator and nothing needed
  it; dynamic cases arrive at construction.
  Money scale defaults from ISO 4217 and is overridable for rates. Reaching
  for Number when you mean a rate is the common mistake, and it costs the
  currency pairing and numeric comparison.
  Password counts characters, not bytes. maxBytes is gone: bcrypt's 72-byte
  truncation is real and is the hashing layer's question.
  File believes what it is told, and why the port should hand over a handle.
  Everything stays singular, and the port converts <input multiple>.

Two errors corrected. `meetsMinValue(BigDecimal $value)` was wrong -- parse()
returns a ParsedValue, so a constraint is typed Number\Value and unwrapping
happens inside it. And `$value = parse($given) ?? $given` was wrong both as
documentation and, until the previous commit, as code.

Added: default versus prefill as a table, with the nickname/username example
that makes it concrete, and the trust levels -- an authored default is
trusted by construction, submitted input never is, a prefill only if you say
so.

Baselines not serialising now says why it matters across languages: this
library says a password floor is 8 because NIST does, and a JavaScript port
could say 7. The same document would accept different input depending on who
validated it. On the roadmap, with the File handle item.

DESIGN.md's "repairs nothing" and constraint sections have runnable examples,
all verified.

### A simpler result surface, a valid-or-nothing $value, and else* for rules

`56e9987c` · 2026-09-16

Three changes to the reading surface, all asked for after using it.

$value is now the parsed value or null, never the raw input that could not be
parsed. It used to hand back what arrived, on the grounds that a form
redrawing a rejection needs something to show -- but $given already holds
exactly that, unchanged. Having both mean it made $value a union of "the
domain type" and "whatever turned up", so nothing downstream could rely on
its type: a rule comparing equals(18) could have been handed the string
'abc'. Now $given is what was sent and $value is what the field made of it.

The result gained a $constraints aggregate beside $shape, and shorthands for
the common readings. Two kinds of answer -- could this be read, and does it
satisfy the rules -- each with the full aggregate API, rather than one list
with the shape mixed in and, for a collection, a verdict per row as well:

    $field->wasUnreadable()                     // was $field->shape->wasUnreadable()
    $field->getFailedConstraints()->getFirst()  // was $field->getFailed()->getFirst()
    $field->constraints->allPassed()            // when you want more

The second of those was the ambiguous one: getFailed() on the field returns
the shape failure too, so "the first failure" was rarely the constraint the
caller meant.

otherwise* is else*. elseMakeOptional reads as the sentence it is. Renamed as
identifiers only -- "otherwise" is an ordinary English word and appears in a
dozen docblocks with nothing to do with rules.

### Validating a schema with no fields raises

`ebb12073` · 2026-09-16

There is no honest answer to give. An empty result reports allPassed() as
true -- vacuously, nothing failed -- and status as Pending -- nothing was
judged. Both are defensible and they contradict each other, so a caller got
whichever one they happened to ask for.

A LogicException rather than a failed result, matching Draft::build(): no
input could make it right, so there is nothing to report to a user. It is a
mistake in the code.

Building one is still fine. A schema is empty for as long as it takes to add
the first field, which is the ordinary way to write one -- so the check is at
validate()/resolve() rather than in the constructor.

### Fix the last 1.x snippets in the 2.0 API doc

`4c5dae05` · 2026-09-15

Two kinds of stale, and only one was wrong. The passage explaining why dotted
constraint names were bad shows 1.x spelling because that *is* the argument
-- it now says so rather than reading as current. The passage describing the
structured types was the genuinely stale one: it described the current design
in the old API, with array input and a File\Metadata that was renamed to
File\Value. Rewritten and run to confirm it does what it claims.

docs/LIMITATIONS.md keeps its 1.x reproducers deliberately: they are the
record of what each defect was, and rewriting them in an API where the defect
cannot occur would lose the point.

### Check every {@see}, and raise PHPStan to level 3

`716214a8` · 2026-09-15

tools/check-references.php resolves every {@see} in src/ against what
actually exists, and composer ci fails if one does not. It found five dead on
its first run: FieldResult pointing at SchemaValidationResult::get(), Rule at
a Rule\Set::apply() that moved to the Facade, Date at an inclusive to() that
was deliberately removed, and two fields at a validateValue() from before
parse() absorbed it.

This is the rot that keeps recurring because nothing notices it -- an IDE
renders a dead link silently and a test suite has no opinion about comments.
The comments here carry the reasoning, which is the expensive part to
reconstruct, so a dead link erodes trust in all of them.

PHPStan src is level 3. It cost one ignore entry and no code change:
Field\Definition is a trait and initialiseDefinition() assigns  and
 from it, which PHPStan cannot model because it treats a
readonly property as assignable only in the declaring class's own
constructor. PHP 8.6's readonly defaults remove the entry entirely.

The note in phpstan.neon now says the thing the level number hides: the
analyser is blind to the entire configuration surface of every field, because
with() clones through a string-keyed array. A genuine inversion in a
cross-bound guard would look identical to the ones being ignored. What covers
them is the suite.

### Regenerate the changelog after the merge

`805bf1ac` · 2026-09-15

### Rewrite the README for 2.0, and add the missing docs

`5da537af` · 2026-09-15

The README was 1.x throughout -- addTextField(), matches(), minOf(), array
input -- so every snippet in it was wrong. Rewritten, and every claim in it
verified against the running code rather than from memory.

New docs:

  docs/README.md    an index, split by what the reader is trying to do
  docs/DESIGN.md    the decisions that shape everything else, and what each
                    one costs. Mostly refusals
  docs/EXTENDING.md writing your own field type, pointing at a runnable
                    example rather than a snippet nobody executes

COMPARISON gained the axis it was missing: what a new type costs in each
library, which is where the gap is widest and where this one's advantage is a
consequence of the architecture rather than a feature. It also gained the
honest other half -- the constraint axis, where Symfony ships eighty and this
ships none you can add to an existing field.

Its closing section was written from the 1.x alpha and claimed no CI, no
static analysis and no changelog. All three exist; what is actually missing
is production use, which is a different and more honest thing to say.

examples/custom-field.php is a complete field type defined outside the
package -- about forty lines, nothing registered -- so the strongest claim in
the docs is one CI runs.

### Generate the changelog, and add four focused examples

`cf31b6a7` · 2026-09-15

CHANGELOG.md is written by tools/changelog.php from the commit history, and
composer ci fails if it is out of date. A hand-kept changelog is a second
place to write down what a commit already said, and the two drift the first
time somebody is in a hurry.

It does not sort commits into Added/Changed/Fixed. That classification lives
in the author's head at commit time, and a script inferring it from a verb
produces confident nonsense.

Four examples, each about one thing:

  collections       named rows, minCount, unique across two spellings of a
                    quantity, and a failing row failing the collection
  value-equality    why a field's value decides equality rather than ==,
                    with the Order enum for the ordered ones
  branching-rules   then/otherwise on one condition, reusing a condition for
                    two rules, and allOf
  comparing-fields  whole-value and part-scope comparison between two fields

The branching example first read 'email required: true' on all three rows,
because the field was required as authored and thenRequire on an
already-required field changes nothing. Made optional so the rule's effect is
visible -- a fine way to write an example that proves nothing.

interactive-input prompts when a terminal is attached and plays scripted
answers when one is not, so CI exercises it rather than skipping it.

### Scopes can read into a structured value, and compare two fields

`00738a9d` · 2026-09-15

Two capabilities that arrived together because neither is much use alone.

A fourth segment addresses a part: #/fields/billing/value/country. Only a
value implementing Field\HasParts can be read into, and part names are the
keys submitted input already uses and the ones a constraint reports as
$constraint->part -- postal_code, not postalCode -- so there is one
vocabulary rather than three. partNames() is static so addRule() can check a
part before any request exists; without that, a mistyped part would resolve
to null on every request afterwards, which is invisible because null is a
legitimate answer for a part nobody filled in.

Four segments rather than three, and that is a deliberate departure from the
shorter form. #/fields/billing/country reads better and is ambiguous: the
third segment already means a definition property, and two fields have one
that collides with a part of their value -- #/fields/card/name could be the
field's name or the cardholder's, #/fields/resume/name the field's or the
uploaded file's. $name is on every field, so a precedence rule would make a
stored scope change meaning the day a field gained a property.

An expectation can now be a Scope, which is the cross-field half:
when(ValueScope::of('shipping'))->equals(ValueScope::of('billing')). Both
sides go through the same resolver, so a parsed value is compared against a
parsed value. The sides need not match in part or in field type -- comparing
a postal_code to a line1 is allowed and answers false. Comparison::getScopes()
reports both, so addRule() checks the expectation too.

Collection items remain unaddressable: which row 0 is depends on what was
submitted, so a stored rule naming one would mean a different row on a
different request.

### API-REVIEW becomes API, and the checklist becomes a test

`a6311b0f` · 2026-09-15

The review is over. Every row was settled or dropped, so a page headed "API
review" with a Status column reading open on all 21 rows was describing a
design rather than the code -- and it had gone stale in 21 places: Text
listed as emitting min/max, Uri as scheme, Uuid as version, Money and Address
as dotted names, plus rows for Passphrase and Variant, which are deleted.

The field table is now generated from the classes and, more to the point, is
no longer the source of truth: Api\ConstraintNameTest and Api\NamingTest
assert the names and the configuration surface for every field and run in the
default suite. If the page and the tests disagree, the tests are right. That
is the fix for a checklist going stale -- make it executable, not shorter.

Presets are dropped for 2.0 rather than pending. None were built, and a
preset is a named bundle of calls an author can write themselves; naming the
bundles before there is usage to name them from is how you end up with
moderate() and nobody able to say what it means.

Optionality settled as makeOptional()/makeRequired(). Paired spellings,
because require() reads as an imperative next to a wither that hands back a
copy.

Four of the five known API leaks were already closed and are kept struck
through as the record. The fifth is closed here: Field\Set::getByName() is
typed Field rather than ?Field, which is what it always returned -- it threw
on a miss and the nullable type was a lie every caller wrote a dead check
against. findByName() is the nullable one.

Also removed: containsDuplicateFieldTypes(), called from nowhere, and
__toArray() on both sets, which is not a magic method -- the double
underscore promises the engine calls it and nothing does. Now toArray().

Enum stays closed. Enum::allow() was a mutator (oneOf[] = $value; return
$this), which is the shared-state defect this rewrite removes, and nothing in
cqda/website, schema-html or schema-json calls it -- every ->allow() in those
projects is PhoneNumber's. Cases arrive at construction, including the
dynamic ones.

### Move the comparison vocabulary into its own namespace, and answer with an Order

`8f7f7acb` · 2026-09-15

Meraki\Schema\Comparison\ now holds Equality, Comparable, Order and Values.
Not Rule\: Collection's unique constraint compares values with no rule in
sight, so putting the interfaces there would make a field depend on the rule
namespace for a concept that is not rules'. Comparison\ also mirrors
Psl\Comparison exactly, which keeps interop a rename rather than a redesign.

Field\ParsedValue stays under Field\ and now declares nothing of its own. It
is the *role* -- the literal return type of parse() -- and extends Equality,
which is the *capability*. Two statements rather than one, and only the first
is about fields.

Fields implement none of it. A field is a definition, and Field::equals()
already means "the same field, by name"; a rule comparing two fields compares
the values they resolved to. A property scope compares raw values directly,
which is what Comparison::parsesItsExpectation() already gates on.

compareTo() answers with an Order enum instead of -1|0|1 -- the last place in
the library still returning a magic integer where everything else with a
closed set of answers uses an enum. Order::of() normalises any conventional
comparison integer, because strcmp() and several of Brick's comparisons are
promised only to be negative, zero or positive.

Money is Comparable now, ordered within a currency and raising across one.
Raising is already the contract for "these cannot be compared", which is what
lets money qualify at all: isAtLeast on a money field is an obviously wanted
rule, and excluding the type to avoid one raising case cost more than it
saved.

### A collection's result is a resolved field, not a wrapper around one

`69f58eab` · 2026-09-15

It held a private ResolvedField and re-exposed a chosen few of its members
through get hooks. So `given`, `source` and `evaluatedAt` were unreachable,
and `value` was virtual -- which meant it did not appear in a var_dump()
either, and a collection result read as though it had no value at all. A
caller could not write one piece of code that read any field's result, which
is the point of having one.

It extends ResolvedField now, so every member is a real property and a
collection is read exactly like a text field. Api\ValueObjectTest pins that
across all nineteen field types.

Rows go to the parent alongside the collection's own verdicts, so anyFailed()
and $status cover both axes: a collection whose third row failed is a failed
field, and the schema it belongs to does not report allPassed(). Asking about
each separately is still forConstraint() and itemAt(). ResolvedField's
variadic widened to ValidationResult to allow that -- everything that reads a
specific kind already filtered for it, so an extra kind passes through
$shape, $constraintNames and forConstraint() without being mistaken for a
constraint.

Collection\Value gained the accessors that make it worth being the value:
keys(), has(), rowAt(), valueOf() and column(). valueOf() is there because
$value->rows[$k]->sku ?? null has two ways to be absent in it and this has
one.

$field is inherited as Field, since PHP will not let a subclass narrow a
property type; $collection is that narrowing under an honest name.

### An enum case outside the list is a shape failure, not a constraint

`bf431bb6` · 2026-09-15

Reverting my own change. I had made membership an allowedCases constraint
carrying the cases as its bound, on the argument that a renderer wants the
list to interpolate into 'must be one of: free, pro, team'.

That argument does not hold: an enum renderer reads $cases to draw its
options at all, so it already has the list and the bound carried nothing new.
What the constraint bought was the field answering one question twice.

Reading it as shape is also the consistent choice. Date reports an
unparseable string as shape rather than as a format constraint, for exactly
this reason -- 'that is not one of these' and 'that is not a date' are the
same statement: the value is not the kind of thing this field holds. The list
of cases is the type.

Enum is now the only field with no constraints, which is the honest answer
rather than a gap.

### Settle transformed: there is no second value on a result

`f941f550` · 2026-09-15

Step 5. The feature collapsed into parse()'s return type.

Its own rule was 'use the library's value object when it stringifies to the
value it represents'. Three fields qualified, the rest got a third party's
class or a bare scalar, and PhoneNumber got a hand-made exception because
libphonenumber's object stringifies to a debug representation. That rule now
holds for every field, so the exception is the rule and the case that forced
it is one method on the value.

A result carries given and value, and nothing throws. transformed was to
throw when read on a failed field, which is the wrong shape for a property a
form needs precisely when redrawing a rejection.

What is genuinely given up is a canonical string per field. Number was to
yield 123.00 at scale 2; it does not, because padding is formatting and
formatting is a locale's business. The scale is on the field for whoever
wants to format against it.

ROADMAP, API-REVIEW and Number now say the same thing, where they previously
said three.

### Clear the documentation rot, and stop the request copy dropping its clock

`6c26c222` · 2026-09-15

Step 4 and step 6 of the audit plan.

Rot, all of it comments that outlived the code they described:

  Facade::add() pointed {@see} at Field\Factory, deleted this session, in the
  docblock of the most-called method in the library.
  Number carried two orphaned docblocks for a normalize()/transform() pair
  that was planned and never built, and its class docblock promised the
  padding they would have done. Nothing pads; the scale is on the field for a
  consumer that wants to format against it, which is where formatting belongs.
  Rule\Matcher's own example called isAtLeast(), which does not exist.
  CreditCard::parse(), Constraint::__construct() and Password::SHORTEST each
  carried two stacked docblocks, of which PHP uses the last.
  docs/LIMITATIONS.md's header and contents disagreed with its own body about
  whether B9 was open.

D4: Facade::copyForRequest() built a fresh SystemClock and lost the country
defaults. Latent -- the request's instant is read from the original and
fields are shared instances holding their own clock -- but it would have
surfaced as a fixed-clock test failing for a reason nobody would connect to
that line. ClockTest now pins it.

BuildsFields::$clock and $defaultCountries are protected rather than private.
That is the extension point: a field type defined outside this package had no
way to inherit either, so a third-party region-aware or time-relative field
was second-class in a way its author could not fix.

### Fold the 2.0 specification into CI, and finish recovering it

`93eb7c72` · 2026-09-15

The spec group is green at 260 tests and phpunit.xml no longer excludes it,
so the default suite is 1613. It was excluded on the grounds that a
specification written before its implementation is red by design; it had not
been red for some time, and a green spec CI never runs is one nobody finds
out has rotted.

Its files declare #[CoversNothing] rather than #[CoversClass]. They assert
behaviour across the whole API -- every field sealed, every parsed value
knowing its own equality, no constraint name carrying the field it came from
-- and attributing that to one class would be a fiction. Coverage comes from
the unit tests beside them.

Two things the recovery surfaced that were not drift:

  Address::parse()'s docblock still described a rule that had been deleted --
  filling the country in when the allow-list held exactly one. I "fixed" the
  code to match the docblock before Field\AddressTest told me, in as many
  words, that the rule was removed on purpose because it changed shape
  depending on how many countries were listed. Reverted; the docblock was
  the stale half.

  Four spec tests called a wither and discarded the result, so they
  configured nothing and asserted against a bare field. A field is sealed, so
  the copy has to be kept. They passed for the wrong reason.

Address's API is now allowOnlyMailable()/allowOnlyPhysical()/
allowWithoutStreet() rather than ofType()/mustBeSpecific(), and an address
requires a street by default -- configuration narrows from a correct
baseline rather than tightening a lax one.

### Bring the 2.0 spec back in step with the API it describes

`a432ce57` · 2026-09-15

Recovering tests/Api after I destroyed six files with a script whose
preg_replace failed and returned null, which then got written to disk.
Restored from HEAD -- which predates both the strictness rewrite and this
session -- and brought forward.

Mechanical drift, each a rename the library really made: Property\Name to
FieldName, getConstraints() to the $constraints property, result->get() to
forConstraint()/forField(), Instant::parse() to a zoned date-time, and array
payloads to objects. That last one was also why several card assertions were
failing: as an array a card is not a record at all, so the shape failed and
every constraint was skipped.

Deliberate removals re-applied: NamingTest's 31-row removal matrix, which
docs/ROADMAP.md records as retired; Password's maxBytes and its composition
maximums; PhoneNumber's unambiguous.

Enum now reports membership as an allowedCases constraint carrying the cases
as its bound, instead of failing the shape with nothing to interpolate. It
was the one field where membership is the whole point and the one field that
could not express it.

serialize() no longer works on a schema, because a constraint holds a
Closure. DefaultsAndPrefillTest says so and uses print_r(), which walks the
same object graph -- serialising a schema is meraki/schema-json's job, and it
writes the definition rather than the graph.

ClockTest and DefaultsAndPrefillTest are green again; the rest follow.

### Audit remediation: defects D1-D7, uniform parsed values, sealed schema

`6256356a` · 2026-09-15

Safety checkpoint. Captures the uncommitted work from the previous sessions
(the 2.0 strictness rewrite) plus this session's audit remediation, so that
none of it depends on the working tree surviving.

Steps 1 and 2 of the audit plan are complete and verified:

  D1  AggregatedValidationResult::filter() renumbers, so getFailed()->getFirst()
      stops returning null on every failing field. Found while fixing it that
      AggregatedValidationResultTestCase had no concrete subclass, so fourteen
      tests -- including the two that covered this -- had never run.
  D2  Rule conditions parse the expectation through the target field before
      comparing, so equals() works on the twelve field types that parse to an
      object. notEquals was the worse half: it fired on every request rather
      than never.
  D3  Facade::$fields/$rules are private(set); both sets' mutators are private.
      Rule\Set::apply() moved to Facade::applyRules().
  D5  Rule\Draft forks instead of mutating in place.
  D6  Fixed by the value-object change below.
  D7  AggregatedValidationResult::$results is private(set).

Field::parse() now returns ?ParsedValue -- every field defines its own value
class, and none returns a bare scalar or a third party's object. Equality was
being decided by PHP's == reading somebody else's private property layout,
which BigDecimal made visibly wrong and which LocalDate and Duration got right
only by accident.

tests/Api is in the middle of being brought back up to date; it is not green
yet and the next commit finishes it.

### AtomicValue fields are no longer a thing

`b5b6e28e` · 2026-09-10

### All fields are atomic by definition and no longer need a separate interface

`5479f3db` · 2026-09-10

### Rename File\MetaData to File\Value to keep consistency with other field values

`6972cbed` · 2026-09-10

### Remove atomicmultivalue interface as this was a relic from when the library foillowed html forms more closely

`9e672327` · 2026-09-10

### Separate building a field from registering it

`58a3ac2d` · 2026-09-10

Field\Factory builds fields; Facade::add() registers them. They were one call —
$schema->addTextField('x')->minLengthOf(3) — which stopped working the moment fields were
sealed: the field was added, then a wither returned a *copy*, and the schema kept the
unconfigured original. Splitting them makes the order explicit rather than accidental.

    $schema->add($fields->createTextField('username')->minLengthOf(3));

The factory is an instance rather than a set of statics, so a static method never reaches
out to construct objects that are not its own. for() moves across with it: which countries
a newly built region-aware field defaults to is a question about building one, not about the
schema it will live in. Its tests move to Field\FactoryTest.

Roughly 270 call sites swept. The whole configuration chain goes inside add(), because
splitting it would reintroduce the bug the separation exists to fix. Test classes hold the
factory as a property so helper methods reach it without growing a parameter.

A collection's template changes shape rather than name — it was a mini-schema the caller
filled in, and becomes a callback returning the fields one item is made of:

    $fields->createCollectionField('lessons', fn(Factory $f): array => [
        $f->createDateField('date'),
        $f->createTimeField('time'),
    ]);

Two things this leaves open. pairWith() requires its owner to already be in a schema, which
create-then-add makes impossible; the roadmap already has it and Field::$schema removed in
2.0, and this is what makes them unworkable rather than merely unwanted. Six PairWithTest
failures are that, waiting on the decision. Eight EmailAddressTest failures are in-flight
work on that field, left unstaged.

### Seal the definition: abstract $name, clone-based renaming, Number and Duration

`cbf85e22` · 2026-09-09

$name becomes an abstract property each field declares for itself, so no field calls a
parent constructor to get one. PHP requires a hook declaration on an abstract property, so
it reads `abstract public Property\Name $name { get; }`, which a promoted readonly property
satisfies — verified, including that the base scope may still clone-modify a readonly
property a subclass declares, which is what lets rename() stay on Field.

Field loses its constructor entirely. defaultValue moves behind a get hook that computes
from the field's own process(), which no static initialiser could do. It recomputes rather
than memoising: caching on first read would mean *reading* a field changes it, which is
exactly what the snapshot test in LongLivedProcessTest exists to catch.

Renaming stops being a mutation. rename() returns a copy, Set::prefixNamesWith() returns a
renamed set, and Composite and Variant rebuild theirs. That surfaced a real bug in
Address::replaceSubField(), which called rename() and discarded the result — under the old
semantics it mutated in place, under the new one the prefix was silently lost.

Number and Duration take minValueOf/maxValueOf and report minValue/maxValue. Number's scale
becomes a constraint rather than part of the shape check, so 123.456 on a scale-2 field says
so instead of reporting "must be a number" about a value that plainly is one.

Two behaviours I deliberately did not change while renaming. Duration keeps its authored
baseline of zero to one day in whole minutes — those are not sentinels, and turning them
into null would have changed validation under cover of a rename. It also keeps failing a
zero step, where Number treats the same case as "no stepping"; that inconsistency is real
and belongs in the review rather than in this commit.

An unset constraint now reports Skipped rather than Passed. Nothing was checked, so saying
it passed overstates it, and it matches how an unset maxLength or pattern already behaved.

Three tests fail, all one cause: addTextField() adds a field and returns it, then a wither
returns a *copy*, so the schema keeps the unconfigured original. That is the coupling the
review predicted — sealing the definition is what makes createXField() plus an explicit add
load-bearing rather than cosmetic.

### Rename Constraints to Constraint\Set

`bbab4b54` · 2026-09-09

Follows Field\Set and Rule\Set, which hold the same shape: ordered, and unique by the name a
member is looked up by. Strictly that is an ordered set — neither plain "list", which would
allow duplicates, nor plain "set", which would not keep order — but Set is the house term
for it here and the existing two are loose in exactly the same way.

Also drops skipAllConstraints(), whose last caller went when check() started running the
constraint list.

getConstraints() stays for now. It is the adapter fields not yet migrated still declare
through, and it goes when the last of them moves across.

### Constraints declare their own name, part and bound

`fc8f1247` · 2026-09-09

The first implementation step, and the one everything downstream reads.

A constraint was a bare callable keyed by name, so a result could say only that something
failed. Anything wanting to say more had to go back to the field and guess — splitting the
name on dots for the part, or reading $field->{$constraint->name} for the bound. A
Constraint object states all three, and Constraints holds them in the order a field checks
them.

ConstraintValidationResult carries part and bound through, plus passed()/failed()/skipped()
so a caller stops comparing enum cases by hand.

Text, Name, Uri and EmailAddress are migrated, which brings the first renames with them:
min/max become minLength/maxLength as property and constraint name alike, maxLength is
nullable because a sentinel that is also a real length cannot be told from someone declaring
it, and Text::matches() becomes mustMatch() — a mutator named for the rule it states rather
than the question it resembles.

Fields not yet migrated keep declaring the old array; Field::constraints() adapts it, so
they move across one at a time rather than all at once.

Composite gathers constraints through the new list as well. That was not optional: routing
Text through Constraints while Composite still read getConstraints() left every Text
sub-field of a credit card reporting nothing but its type.

meraki/schema-json follows the rename on both sides of the wire, since the document carries
these names.

### Bring the specification up to date with the last three rounds

`8643cfcb` · 2026-09-09

Fifty-three tests across seven files now, up from thirty-four, matching the review as it
stands rather than as it stood before the clock, the phone-number parsing rule and the
Address decision.

ClockTest states the distinction the design rests on: a field holds a source of the instant,
not an instant, so the same card passes or fails purely by moving a FixedClock, and the
result records what it was judged against. It also pins the carve-out — a time-relative
constraint is exempt from the definition-time default check, because the answer changes with
the calendar and a default valid at boot would fail years later with nothing edited.

PhoneNumberTest separates the two jobs allow() was doing. One allowed country parses a
national number against it; an international number needs none; several countries and a
national number is ambiguous, and ambiguity is a constraint carrying the countries to choose
from, because the input is well-formed and the useful message asks which country rather than
calling the number invalid.

StructuredTypeTest gains the two Address dials and the combination that must not exist:
Type says what the address is for, mustBeSpecific() says how much of it is required, and
Postal without specific is refused where it is declared.

The tables are amended for the names that moved — allowCountries on PhoneNumber, specific on
Address, unambiguous, mustExpireInFuture — and Address\Type is confirmed as surviving rather
than removed.

### Keep PhoneNumber transforming to a string; keep Address one field

`8d33f2bd` · 2026-09-09

PhoneNumber returns to an E.164 string, because nothing needs the resolved country — not
because the country can be read off the prefix. That part is worth recording since it is
easy to assume: +1 covers the US, Canada and some twenty Caribbean nations, and +7 covers
Russia and Kazakhstan, so a Toronto number and a New York number share a prefix and are told
apart only by area code and libphonenumber's metadata. A consumer that needs the region must
re-parse rather than take a substring.

The Location / PostalAddress split is rejected. With Address\Type retained the two types
would differ only in whether a street is required, and a boolean does not justify a type. So
Address keeps one class with two independent dials: Type for deliverability, and
mustBeSpecific() for granularity, which adds line1 to the required set and leaves per-country
requiredness governing the rest.

Postal with a non-specific address throws where it is declared — you cannot post to a suburb,
and a combination with no meaning is rejected at definition time as the baseline floors are.

What would justify the split later is coordinates, and that is a different reason from the
one rejected here: a coordinate is not an address, whereas a suburb is one that happens to be
vague. Adding a Location type later leaves Address undisturbed.

### Add a clock, settle card expiry, and give PhoneNumber a value object

`0c18d3b5` · 2026-09-09

A field that asks whether something is in the future needs now, so it holds a Clock — a
source of the instant, never an instant. That distinction is what keeps a shared definition
safe: a SystemClock is stateless, while reading now at definition time and storing it would
be B7 again. brick/date-time already ships the abstraction, so it costs no dependency and
makes the behaviour testable against a fixed instant.

The resolved field carries evaluatedAt, which makes a verdict reproducible and serves as the
bound for every time-relative constraint, so a message can name the moment it was judged
against.

That forces one carve-out: defaults are checked when declared, but a time-relative
constraint cannot be checked that way, since defaultsTo('2027-01-01') with
mustExpireInFuture() passes today and fails in 2027.

Card expiry becomes mustExpireInFuture(), with no minimum — "not expired" is the constraint
itself — and a baseline maximum as a typo guard, because cards are issued a few years out
and 2099 should not be accepted.

PhoneNumber revises an earlier decision. transformed was to be an E.164 string, chosen
because libphonenumber's own object stringifies to a debug representation; but a string
cannot carry the country the number resolved to. A PhoneNumber\Value that stringifies to
E.164 satisfies the rule that rejected the library's object while answering the country
question.

That also separates two jobs allow() was doing at once: constraining which countries are
acceptable, and supplying the region a national-format number parses against. Ambiguity —
several countries allowed and a national-format number — is a constraint rather than a shape
failure, because the input is well-formed and the useful message asks which country rather
than calling the number invalid.

### Write the 2.0 API as an executable specification, ahead of the implementation

`9904c919` · 2026-09-09

Thirty-four tests in tests/Api/ stating the reviewed API rather than the current one, so
every one of them is red by design. They sit in the api-2.0 group, which phpunit.xml now
excludes from the default run: the existing suite stays a signal at 1074 green, and the
specification is a target run on its own.

Naming is expressed as data tables rather than one test per name — the provider is the
thing under review, and it reads as the same table the review document holds. Everything in
NamingTest is checked by reflection on class-name strings rather than by constructing a
field, so a type that does not exist yet reports a failed assertion instead of taking the
whole file down with a fatal.

Writing them surfaced eight places the review left undecided, which is what doing this
before the implementation was for. They are named in the commit that follows.

### Type the bound as a closed union; settle value objects and transformed

`a952f42d` · 2026-09-09

The bound is a closed union rather than mixed or a generic. Generics do not help here: the
null-widening used on accepts() works because parameter types are contravariant, but bound
is a property on one class with no subclass to widen in, and a @template would erase exactly
where results are collected into a heterogeneous list — which is where a consumer reads
them.

The closed union does what a generic cannot. PHPStan at level 7 rejects concatenating it
without narrowing, where the dynamic property read it replaces passed clean at level 9. That
makes raising the PHPStan level load-bearing rather than tidy, since schema-html is the
consumer that most needs it.

Value objects are named Value in their field's namespace. File\Metadata is renamed to match;
it was the only one of its kind. This has to follow the removal of Property\Value, already
decided in FIELD-API.md and still referenced eight times in Field.php, so that two Value
classes are never in scope at once.

transformed returns the library's value object when that object stringifies to the value it
represents. BigDecimal and LocalDate do; libphonenumber\PhoneNumber does not — its
__toString() is a debug representation reading "Country Code: 61 National Number: ...", and
reaching E.164 needs the PhoneNumberUtil singleton. So PhoneNumber transforms to an E.164
string rather than exporting that dependency to every consumer.

The field API review is now closed: every row is settled.

### Have constraints report their own bound instead of being looked up

`03f6cf80` · 2026-09-09

Removes the coupling this review introduced two rounds ago rather than formalising it.
"The constraint name is the property name" existed so a message could reach the number it
interpolates, via $field->{$constraint->name} in thirty-one places in meraki/schema-html.

That lookup cannot be made safe. It is a dynamic property read, so static analysis cannot
type it: PHPStan at level 9 accepts 'at most ' . $x->{$name} where $name may resolve to a
property holding a map, and the result renders "at most Array". Money's bounds are exactly
that shape, being per-currency.

So the bound travels on the result. The provider stops touching the field, the map case
needs no special handling because the result carries the bound that actually applied, and
computed bounds become reportable at all — a per-country postal format has no scalar
property to point at, so the lookup could never have worked for it.

Constraint names still read as what they constrain, because scopes address properties by
name, but nothing depends on an exact match now.

Also settled: Field\Set::getByName() returns null, since whether a missing field is an error
is the caller's judgement; DateTime's three precision factories go, being sugar over a
constructor argument that is already an enum; addXField() becomes createXField(), because
the call creates a field rather than adding one; and the rule vocabulary is deferred to its
own stage after the field API is finished.

### Settle the structured types: one field, one value object, flat names

`55b38150` · 2026-09-09

Composite is removed. Address, Money and CreditCard each become a single field holding a
single value object, the way File already holds a File\Metadata — input is an array or the
object, and cast() normalises. No sub-fields.

Constraint names lose the dots and, more importantly, the field name. Today a name embeds
the field it came from, so renaming billing to invoice_address changes every constraint it
emits and every message provider matching on them; the field name is context, since you got
the result by asking for it. Where a constraint has a property it takes that property's
word — allowedCountries rather than countryAllowed — and Money bounds an amount rather than
a value, so minAmount.

Which part failed becomes data rather than a substring: ConstraintValidationResult carries
an optional part, so a renderer reads $constraint->part instead of splitting on the last
dot, and a constraint about the whole address can say so with a null part, which the dotted
scheme could not express. That also disposes of the money-message bug, where cost.amount.min
matched neither the renderer's bare min nor its dot-splitting path.

Composite has no remaining use. The case that seemed to need it — a repeatable list of
multi-field items, as in cqda's session timetable — is already what Collection does: it
holds a template of several fields and validates each item against all of them.

Also confirmed: _Require becomes MakeRequired, pairing with MakeOptional and avoiding the
keyword rather than working around it, since class Require {} is still a parse error on PHP
8.5 while namespaces do accept reserved words; Collection gains minCountOf(); Password's
five static constructors give way to minStrengthOf(Strength::Strong).

### Settle Password's composition methods; correct the Collection row

`2ea43771` · 2026-09-09

satisfyAnyOf() is replaced by explicit methods rather than a group mechanism, following the
rule that an adjective takes a noun and a noun stands on its own: minNumberOfUppercaseChars
and minNumberOfLowercaseChars, but minNumberOfDigits and maxNumberOfSymbols.

Ten flat ?int properties replace five Range objects, and each carries its own constraint
name — so a failure now says whether the floor or the ceiling was missed, which one
constraint name spanning both ends could not.

Also corrects a claim made earlier in this review. Collection was listed as needing no
change on the strength of its properties and constraint names, which are right; its methods
were not checked. minItems(3) and $field->minItems are the same identifier for a setter and
a reader, where every other field uses the <property>Of form.

### Merge Passphrase into Password; remove Variant and satisfyAnyOf

`6a38fe40` · 2026-09-09

The merged field keeps the name Password: it is the term people know and search for, a
passphrase is a style of password rather than a different thing, and NIST's "memorized
secret" is precise but obscure enough that nobody would look for it.

Variant goes with the merge. Its only use across all three packages is the
Password | Passphrase union in schema-json's round-trip test, so after the merge it has no
caller — and keeping it means carrying __get() magic, prefixed sub-names, a duplicate-type
guard and a Composite|Variant union on CompositeValidationResult for a capability nothing
uses. The roadmap previously said Variant stays; that predated the merge.

satisfyAnyOf() goes too, and closes a defect on the way out. It means "at least one of
these named constraints must pass", and is implemented as a rule engine inside the field: a
constraint in an anyOf group returns null when it fails, deferring to a separate anyOf
constraint that reads a private $anyOfPassed which each of them mutates as a side effect.
That property is C4, still live — after Password::common()->validate() the field's
$anyOfPassed reads true. It is the last mutable validation state on any field, so removing
the feature closes C4 rather than leaving it as separate work. Its only caller was the
common() preset, already being dropped with the tier list, and it elaborates composition
rules that current guidance discourages.

File's method names move to minCountOf/maxCountOf and minSizeOf/maxSizeOf; its properties
and constraint names were already correct.

### Separate baselines from configuration, and settle the password tiers

`3c29cac5` · 2026-09-09

A baseline a consumer needs to read becomes a property backed by a constant, with no
setter; anything an author may narrow stays private(set). The hook rather than a bare
constant is what keeps the property-equals-constraint rule working — a message can
interpolate the number and the scope resolves — and the missing setter is what makes
"changing this needs a core release" a fact about the type rather than a comment.

Baselines do not serialise. They follow from the field type, so persisting them is
redundant and lets a stored document disagree with the code once the core moves.

Password needs a character limit and a byte ceiling, because neither expresses the other:
64 characters is 64 bytes of ASCII and 192 of CJK. Lengths stay in code points via
mb_strlen(), already consistent across every field; the byte ceiling catches what bcrypt
would otherwise truncate in silence. The message for a byte failure is poor and cannot be
avoided by tuning the character limit without capping passwords at 18 characters, which is
the worse trade.

Strength tiers are five entropy thresholds. The existing "common" and "none" go: a tier
meaning "no strength requirement" contradicts the baseline principle.

### Settle scoping, normalisation, and the Password/Passphrase merge

`14b239d6` · 2026-09-09

Scopes reach properties and never methods. The reason is concrete rather than stylistic:
a scope path is rebuilt from an untrusted document by RuleSerializer, so it is
attacker-controlled input. Property access is guarded by property_exists() and
NOT_ADDRESSABLE; method access would let a crafted document invoke any zero-argument method
on a field. Where the answer is computed rather than stored, a property hook keeps it state
while still reading as a question — already the idiom in ResolvedField::$transformed.

That also settles Boolean. Rule 2 is refined rather than bent: it exists so a message can
reach the bound it needs to interpolate, so it binds only where a constraint has a bound.
Boolean has none, so it holds $requiresAcceptance and reports `accepted` — the property
reads as state, the constraint reads as what did not happen, and no message loses anything.

Normalisation follows the standard for the type and is not configurable, with the invariant
that makes it safe: it may only remove a distinction the standard says is not a distinction.
Lowercasing a domain is lossless because DNS is case-insensitive; lowercasing an email's
local part is not, because RFC 5321 permits it to be significant.

Password absorbs Passphrase. Strength is a constraint rather than part of the shape, since
a weak password is a well-formed string that failed a judgement. The maximum is 72 bytes,
and the reason is measurable: password_hash() defaults to bcrypt, and bcrypt silently
truncates there — on PHP 8.5 a hash of 72 "a"s verifies a string of 80 "a"s, so a field
accepting more is telling the user their extra characters count when they do not.

EmailAddress loses its format enum for a single WHATWG baseline, and configuration now
throws rather than weakening a field below the baseline it was built to encode.

### Record the naming rule and the API decisions taken so far

`085fd004` · 2026-09-09

The rule, derived over the review rather than asserted up front: a property is named for
what it bounds in the domain's own word; the constraint name is that same string; reading
is a property and changing is a method; null means unset. The domain's word wins over the
pattern, which is why dates keep from/until rather than gaining minDate/maxDate.

Also records the baseline principle now stated explicitly: a field encodes current best
practice and configuration narrows from there, never widens. That rules out offering four
email strictnesses, and it rules out a password minimum below the recommended floor.

Decisions this round: Enum's list is its type, so it stays a shape check and loses allow();
Boolean::mustBeAccepted() stops silently calling require(); Password drops the Range
abstraction for flat properties, since a Range cannot satisfy property-equals-constraint and
today a failure cannot say whether the floor or the ceiling was missed; Number's scale
becomes a constraint rather than part of the shape check.

### Refresh the API review against what the fields actually emit

`3154de00` · 2026-09-08

The document was written for a 1.14 freeze that has since shipped, and its three surfaces
predate the seam: "Rule" now means a typed scope whose property segment is checked when the
rule is added, and "Resolved" means a ResolvedField with value and source.

The constraint-name table is now read off the fields themselves rather than from the source.
Three rows were wrong rather than merely stale: Uri gained `scheme` with the B2 fix, Money
emits only dotted names (there is no bare `min` to match), and CreditCard emits a `checksum`
rather than nothing at all.

That reading also turns the argument for doing this work into evidence. Eight field types
have message branches in meraki/schema-html that can never fire, because the renderer
matches `min`/`max` on fields that emit `from`/`until`, `entropy`, `version` or a dotted
name. It is the same split that left nine DateTest assertions naming constraints that were
never reported, asserting nothing for as long as they existed.

And it reaches users. A money field below its minimum fails `cost.amount.min`, which
matches neither the renderer's bare `min` nor its dot-splitting path — that only knows
visitable, format, allowed and required — so it falls through to a default written for a
different problem and tells the user their input is not a number. Recorded in TODO.md and
deliberately not patched: the dotted-name scheme goes with the structured types, so fixing
it now is work thrown away. It must not survive that rewrite.

Two cross-cutting rows close as settled: defaults, and ignored input. Optionality is
partly answered — provenance is exposed through appliedOutcomes, which is what lets a
renderer tell an authored optional from a rule-driven one.

The Date leak is restated. until() and to() are not two names for one constraint, as
previously recorded, but two behaviours sharing one name: until() is exclusive, to() is
inclusive, and both report as `until`, so a result cannot say which was declared.

### Scrap the builder; keep the default check on the withers

`c2a07bda` · 2026-09-07

Reverses the construction decision from the previous commit. Configuration goes back to
withers on a sealed field, which is what this document described before.

The reason is not the one that prompted it — a builder is boot-time scaffolding and never
reaches a request, so it could not affect concurrency. But there is a real argument for
withers: a builder hands back a half-formed object that could plausibly be stored and
configured per request, whereas a wither returns a sealed field at every step, so there is
no unfinished definition to share by accident.

The default check survives the change. It was the builder's main justification — build()
sees a finished definition, so it can check an authored default against the constraints
that ended up on the field. Without it, every wither re-checks instead, and a constraint
added after a default throws where it is written. That costs a check per configuration
call, which the immutable design makes affordable: there is no way to change a field
without passing through a wither, so a stale default has nowhere to hide.

### Record B9, and settle how defaults and construction work in 2.0

`2a235a34` · 2026-09-07

B9: prefill() writes one request's data onto every field, exactly as input() did before it
was removed. A worker filling in what it knows about a user puts that user's data where the
next request reads it, and it stays in memory afterwards. It is B7 unchanged, in the one
method that survived it — because a default was thought of as authoring rather than as
request data. Documented with a reproducer, both halves verified.

A sixth long-lived-process test asserts the defect rather than the fix, so the suite stays
green and this turns red the moment prefilling moves to resolution. A skipped test would
hold nothing.

The fix is a split, not a move. An authored constant — quantity is 1, country is AU because
the schema is AU-only — is the same for every request, carries no user data, and belongs in
the definition and the serialised document. A per-request value is none of those things.
Two names, so the one that sounds per-request is:

    $field->defaultsTo(1);
    $schema->resolve($submitted, prefilledWith: $known);

What that buys is stronger than the fix: if the definition can only hold constants the
author typed, a serialised schema can never contain user data — a guarantee rather than a
convention.

PrefillPolicy retargets from the definition to the per-request source, where trust is a
real question, and now defaults to Checked. The scenario that decides it is legacy data: a
constraint tightens, stored values no longer satisfy it, and Checked surfaces that so the
user fixes it while Trusted carries it silently forward. A trusted prefill reports Passed
rather than Skipped, because Skipped means "nothing to check" and makes transformed return
null for a field that has a value.

ResolvedField becomes field / value / source / appliedOutcomes. `given` is dropped: it was
documented as what the user typed, so a rejected form could echo it back, but it never
differs from `value` for any field type — including the composite case it existed for,
where the parent has already distributed the processed value by the time the sub-field's
result is built. A property that fails its own contract is worse than an absent one. The
prefilled and default accessors are deferred, since adding one later is not a break.

Construction moves to a builder per field type, in front of a field that takes everything
in its constructor. That is what makes an authored default trustworthy rather than assumed:
configuration arrives in any order, so no single call can check a default, but build() sees
the finished definition and checks once. It lands before the structured types or they get
written twice.

Also records the versioning decision: no deprecation cycles inside unreleased 2.0, since a
deprecation promises something to users who do not exist yet and all three packages are on
one disk. What stays is the part that catches breakage — three green suites per commit, an
honest UPGRADING.md, lockstep tags.

### Remove the staged-input path; a schema can no longer hold a request

`61d7bb5a` · 2026-09-07

Deletes Field::input(), ignoreInput() and acceptInput(), Facade::input() and applyRules(),
and the $value, $resolvedValue, $inputGiven and $inputIgnored properties behind them. All
were deprecated last commit and their last callers are gone: meraki/schema-html now reads
per-request state from the result, and meraki/schema-json never held any.

This is what B7 was waiting on. Until now a schema was safe to share only by discipline —
nothing was required to call input(), but anything could, and doing so put one request's
data on an object other requests were reading. There is no longer a place to put it. The
value goes in as an argument and comes back on a ResolvedField.

The ignore outcome was the one piece that needed rethinking rather than deleting. It set a
flag on the field, which validate() read back — so a schema remembered, between requests,
that some earlier request's value had been discarded. against() now reads it from the
outcomes that were actually applied, which is where a fact about one request belongs.
Ignore::apply() becomes a deliberate no-op.

Address::rebuild() no longer replays held input onto its rebuilt sub-fields; only the
default needs replaying, because a submitted value is resolved against those fields when
the request arrives rather than being held between requests.

Twenty "it has no value by default" tests go: a field has no value to have. What they
stood for — nothing submitted means the authored default — is covered by
FieldTestCase::it_resolves_to_the_default_value_when_nothing_is_submitted, which reads the
result. RepeatedApplicationTest is rewritten against validate(): its guarantee, that an
outcome survives repeated application, is exactly what a long-lived worker depends on, and
it gains a test that the authored definition comes back unchanged.

The docs said a schema was "safe to share provided nothing calls input()". That
qualification is gone, along with the README section teaching input() as a way to stage
data separately.

branch-alias moves to 2.0.x-dev, which is what main has been building for several commits.

### Make scopes typed values and move resolution into the resolver

`1f8df14a` · 2026-09-07

A scope was a cursor. It implemented Iterator, and resolving one walked its position to
the end of the path — so reading a rule's target wrote to shared state. Three symptoms
came from that one fact: an outcome applied twice started from an exhausted cursor,
serialize($schema) changed as a side effect of reading, and two workarounds exist
downstream (Wizard\RuleScopes, FormRenderer:486) purely to rewind scopes before use. The
clone in Scope::resolve() treated the symptom. This removes the cursor.

A scope is now an immutable value that knows what it addresses. FieldScope names a field,
ValueScope what that field was given, PropertyScope part of its definition — three
different questions that used to be one class distinguished by counting segments wherever
it was used. Scope::parse() reads whichever kind a path describes; __toString() writes it
back unchanged, because that string is the wire format meraki/schema-json reads.

The typing pays for itself immediately. An outcome takes a FieldScope, so
"Require can only be applied to fields" stops being a runtime error and becomes
unconstructible. Applying one is then a lookup by name rather than a path walk.

Resolution moves to ScopeResolver, which is now the only place it happens. Facade and
Field stop implementing ScopeTarget, both traverse() methods go, and ScopeTarget and
ScopeResolutionResult go with them — the latter never had its $target read by anything in
any of the three packages. Defect B8 becomes structural rather than guarded: a resolver
reading a name-keyed set has no parent pointer to follow back to the root.

Scope typos now fail where the rule is written. Facade::addRule() resolves every scope a
rule mentions and rejects one the schema cannot address, so a misspelt field name stops
being a 500 on whichever user request first matches it. The cost is an ordering
constraint that did not exist before — a rule can only be added after the fields it
names — which ScopeValidationTest pins alongside the checks themselves.

Parsing got stricter in one place worth noting: the old parser ignored trailing segments,
so "#/fields/x/min/typo" silently resolved as "min". It is now rejected. That also settles
a disagreement, since ScopeResolver already required an exact three segments while
traverse() did not.

Sub-field and collection-item addressing stays unbuilt. Stage 3 deletes Composite and
gives the structured types their own parts, so building it against today's model would be
writing it twice.

Two examples are fixed here rather than separately: they called the no-arg validate()
removed in e03dd6a and had been broken since, because examples are not in CI.
validate-with-magic-input.php also reproduced a bug that has since been fixed and is now
covered by FacadeValidateInputTest, so its output contradicted its own comments; the
labels now say what actually happens. examples/test.php is left alone — it calls
Facade::serialize(), removed back in e218ed3, and is a scratch file rather than an example.

### Resolve rule conditions from the request; deprecate the staged-input path

`dc746298` · 2026-08-29

Two halves of the same thing.

Rule conditions asked what a field was given by reading a value staged onto that field.
So validate() had to write the request onto its working copies before the rules could
run — the very staging the seam exists to remove, kept alive one layer down. The comment
in against() said as much: "conditions still read values off fields".

ScopeResolver answers the question from the request instead. A scope addresses either
part of the definition — min, optional, pattern — which no request changes and which is
still read from the schema, or #/fields/<name>/value, which is now looked up in the data.
Nothing is staged, so against() hands its copies the definition alone.

That leaves input(), ignoreInput() and acceptInput() as the only writers of per-request
state, and they are now marked deprecated on both Field and Facade, naming validate()
and the reason rather than just the replacement. They cannot be removed yet: schema-html
still calls input(), so removal waits for that migration. ScopeResolver keeps one branch
for them — a field given a value directly stays the authority on its own value — so the
deprecated path behaves exactly as before. That branch goes when input() does.

resolvedValueFor() widens from protected to public: it answers "what would this field
validate, given this?", which is a fair question from outside and a pure one.

tests/ScopeResolverTest.php covers both kinds of target, the default fallback when a
field is absent from the request, and that two requests resolving the same scope get
their own values — which is what could not be true before.

### Correct which tests caught the Scope write

`8f8c1013` · 2026-08-29

Two of the five failed, not one: the clone and snapshot tests, being the two that
compare the whole serialized schema. Worth stating accurately, because it is the point
of having them — a moved cursor changes no result, so the three tests that assert on
answers could not have found it.

### Pin down long-lived-process safety with five tests; stop Scope writing back

`384ac276` · 2026-08-29

A schema is meant to be built once when a worker boots and reused for the life of the
process. Nothing held that claim down, so it was true only by inspection.

tests/LongLivedProcessTest.php takes the five claims in docs/LIMITATIONS.md#b7 one at a
time: fibers interleaved mid-request, a clone, retention after the request, a snapshot of
the whole schema, and serial reuse. Fibers are part of the language, so the interleaving
that matters reproduces everywhere the suite runs, with no extension.

Three passed as written. The snapshot test failed, and on something that is not a field
at all: Scope implements Iterator, and resolving one walks its cursor. A rule builds its
scope once in its constructor, so that cursor lives on the schema, and every request
moved it. Results were correct — 03258b8 made resolution rewind first — but the shared
definition was still being written to on every request, which is precisely the defect
B7 names.

Scope::resolve() now walks a copy. The cursor cannot escape the call, so resolving is a
read, and two requests resolving the same rule cannot move each other's position.

This closes B7 for validate() and resolve(). input() still stages data onto every field
and is unchanged; the docs now say which path is which rather than describing validate()
as unsafe.

### Fail loudly when an asserted constraint was never reported

`1fb5ee41` · 2026-08-26

assertConstraintValidationResultHasStatusOf() looped for a result carrying the named
constraint and, finding none, returned. A test naming a constraint the field does not
report asserted nothing at all, and passed.

Twelve did.

Nine DateTest assertions named "min" and "max". Date reports "from" and "until", so
none of them had ever checked anything. They now name the real constraints. This is the
min/max versus from/until split already recorded in docs/API-REVIEW.md; the names
themselves are settled in 2.0, and this only makes the tests honest about today.

to_max_constraint_passes_when_input_is_before_max_date set a maximum of 2025-02-21 and
submitted 2025-02-22 — a date *after* the maximum, despite the name. Once the assertion
ran it failed, correctly. The implementation was right; the test data was wrong.

The remaining two are composites, where a shape failure is reported against each part
rather than the whole, so CompositeTestCase asserts per sub-field.

### Take the submitted value as an argument to resolve() and validate()

`e03dd6a3` · 2026-08-26

A field described what it was *and* held what one request submitted. Sharing a schema
across concurrent requests therefore leaked values between them, and no amount of care
at the call site could prevent it.

The value now arrives as an argument and leaves as a ResolvedField, so nothing
per-request is written back onto the definition:

    $result = $field->validate($submitted);

resolveWith()/validateWith() carried this signature while the old no-arg validate()
was still deprecated-but-present. That deprecation is now removed, so the pair take
the names they should have had, and the suite is touched once rather than twice.

Variant::$matchedField goes with it. It was written during validation and read
afterwards — the same defect in miniature, and public, so anyone could be reading it.
A match already returns the matching alternative's own result, so $result->field
answers the question without shared state.

CompositeTestCase overrides the inherited default-value assertion: a composite resolves
to one result per sub-field rather than to a single value, so the check is made per part.

### Make Facade::validate() pure; add resolve() (Stage 1) — closes B7

`58a32c74` · 2026-08-24

validate() no longer writes to the schema, so an instance can serve two requests
at once without them meeting. Verified with the exact reproducer from
docs/LIMITATIONS.md: two fibers that previously had alice read mallory's data now
each see their own.

Each request runs against a private copy. Rules still work by changing fields, so
they are given copies to change and the authored definition is never touched.
A field no rule altered is reported against the authored object rather than its
copy, so identity holds for the common case and differs only where something
really did change it - which is exactly when a caller wants to know.

resolve() is the same thing without the checking. Every field comes back Pending,
which is what a form being rendered for the first time actually is, and what
ValidationStatus::Pending has always claimed to mean. It replaces input(), whose
three callers in schema-html all wanted precisely this.

Rule application now reports what it did: Rule::evaluate() and Rule\Set::apply()
return the outcomes that fired, and those land on the resolved field. That is
what will let schema-html delete deriveRuleEffects(), which re-runs this engine
in the presentation layer to answer why a field is optional. Verified: a field a
rule required reports the outcome, is optional=false on the result, and
optional=true on the schema.

thenIgnore is handled by the Facade rather than the field. A rule that ignores a
field means "treat this as though nothing was sent", so the value never reaches
the field instead of the field remembering to disregard it. Three tests caught
that omission.

SchemaValidationResult::get() looks a field's outcome up by name, returning the
aggregate for a structured field so its parts can be asked for by qualified name.

Rule\Builder::evaluate() had to match its parent's new return type.

1036 tests, both PHPStan runs clean, and both siblings still green - schema-json
26, schema-html 146 - against a core whose validate() no longer mutates.

### Port Collection and Variant to the seam (Stage 1)

`bb95469f` · 2026-08-24

Both now resolve and validate from a passed-in value, writing to nothing.

Collection: this fixes C2. The old path fed each item into the template fields
with input(), so after validating a list the template held the last item's
values - verified before and after, 'two' becomes NULL. Each item now resolves
against the template independently, which is what let the bug exist in the first
place and what makes it impossible now.

Variant: three per-request writes go. Two were catalogued - matchedField, which
is public, and the input() pushed into every alternative - and one was not:
validate() also rewrote the variant's own resolvedValue to the matched
alternative's. Verified that matchedField now stays null across a validation.

Per the decision recorded earlier, a matching variant's result belongs to that
alternative, because it is the definition that actually described the value: a
caller asking what a secret turned out to be gets Field\Passphrase, with that
field's constraint results. When nothing matches, the result belongs to the
variant and carries the shape failure rather than one alternative's failures
picked arbitrarily.

Collection needed `use Meraki\Schema\Field` explicitly. Inside namespace
Meraki\Schema\Field a bare Field resolves to Meraki\Schema\Field\Field, so the
closure type hints silently referred to a class that does not exist until called.

The differential now covers thirteen field types crossed with twenty input shapes
and both optionality settings - 520 cases. Still no regressions, and still the
same six intentional differences, all of one shape: a required field with no
value now lists its constraints as Skipped rather than omitting them.

1036 tests, both PHPStan runs clean.

### Add PrefillPolicy and ValidationScope to the field contract

`ff9184d9` · 2026-08-24

Records why constraints are skipped when the shape fails. The tidy error report
is the lesser reason; the real one is that it lets a check be written as
fn(string $v) => mb_strlen($v) >= $this->minLength with no guard. A constraint
only ever runs on a value that already has the right shape, so no author has to
defend every check against every wrong type - and the first one to forget would
raise a TypeError instead of reporting a validation failure.

PrefillPolicy states how far a default is trusted, rather than applying one
blanket rule: Trusted checks the shape and skips the constraints, Checked does
both. A third policy skipping the shape as well was rejected - a default of the
wrong type is an authoring mistake, not stale data, and cast() could not consume
it either.

Both are checked when the field is built rather than when a request arrives,
because an invalid default is a bug in the schema and reporting it as a
validation failure would blame the user for it. Constraints added after a default
would leave a Checked one stale, but every wither returns a new field, so each
can re-check - the immutable design is what makes that affordable.

ValidationScope lets a caller narrow what is checked: all(), only(...),
except(...). The whole schema is still resolved and rules still apply across all
of it, because a rule on a later step may be what makes an earlier field
optional; only constraint checking is narrowed. Out-of-scope fields come back
Pending rather than missing, so the result still describes the whole schema.

This already exists downstream. Html\Wizard\Validator::validateGroup() resolves
the whole schema then validates a subset, and its docblock explains why it must:
the whole-schema validator would fail not-yet-reached required fields. Moving it
into the core deletes that class - the fifth piece of machinery schema-html
sheds, after deriveRuleEffects(), the ruleEffects array, Wizard\RuleScopes and
the FormRenderer:486 workaround. One behavioural difference is noted: the
existing one omits out-of-scope fields rather than marking them Pending.

Also records that Property\Name loses $prefix entirely. It exists only so a
composite can rename sub-fields to addr.line1; once structured types own their
whole value, nothing prefixes anything and a name becomes a plain immutable
value.

Docs only.

### Settle the field definition contract in docs/FIELD-API.md

`38dcf6e5` · 2026-08-24

Writes down what a field author provides and what the core decides, after
checking the language assumptions the design rests on against PHP 8.5.9 rather
than assuming them.

null as the parent parameter type works, and is better than the mixed + docblock
approach: children widen null to ?string, ?array, unions or mixed, and PHP
rejects narrowing it to a non-nullable type. So a field cannot declare that it
does not handle absence - which every schema can produce. The core can still call
accepts() through the base type with a mixed value; verified at runtime and clean
at PHPStan level 6.

readonly is all-or-nothing across a hierarchy - a readonly class cannot extend a
non-readonly one - so Field and every abstract below it become readonly together,
with withers using clone-with. That pulls sealing the definition forward from
Stage 5, which is worth doing deliberately rather than discovering later.

No __clone(). Deep-cloning the name there breaks identity - verified that
$original->name === $clone->name becomes false - which is the detached-copy bug
already found and fixed twice here. Property\Name becomes genuinely immutable
instead.

Constraints stay an explicit collection rather than being discovered by
reflection: $default, $name and $optional are public and are not constraints. A
constraint's name is its property's name, deliberately, because schema-html reads
$field->{$constraintName} to build a message - so renaming a public property is
an API break for message providers and is treated as one.

Defaults are a schema concern and static. Every actual use is an author writing a
fixed value, and schema-html never sets one. A default is trusted - it may
predate the constraints now on the field - so it is shape-checked but skips them,
and it satisfies a required field. The consequence, stated plainly: a resolved
value is not guaranteed to satisfy the field it belongs to.

Facade::prefill($data) goes with it. A default that varies per user is
application data, and writing it onto a schema built once and shared is the same
mistake as writing input onto it. It has no consumer today.

Records the main unresolved piece: Composite, Collection and Variant each
override the whole validation path, so making resolve()/validate() final means
giving those three a narrower hook.

Docs only.

### Drop Field::accepts(); it was not needed

`4ed253de` · 2026-08-23

Added in the previous commit so Composite could ask a sub-field whether it
regarded a resolved value as provided. It could already do that: valueProvided()
is protected, but protected members declared on a shared ancestor are reachable
on a sibling instance, and the call dispatches on the object rather than the
caller. Composite has been calling getConstraints() that way all along.

The reason I believed otherwise was a docblock on hasValue() asserting that
valueProvided() "resolves to the caller's implementation". That is not how PHP
dispatches protected methods, and the claim is now corrected rather than left to
mislead the next reader.

There is a real question underneath - schema-html asks !$field->hasValue() to
decide whether to say "This is required" - but under the seam that asks about the
resolved value, not the field, so it belongs on the resolved result and not as a
second value-taking method on Field.

Verified: 1036 tests, PHPStan clean, and the differential against the old
validation path still shows only the one intentional difference.

### Resolve composites through the seam (Stage 1)

`ca9c88b8` · 2026-08-23

Composite gains resolveWith() and validateWith(), porting the whole of validate()
to work from a passed-in value instead of state held on the sub-fields. A
composite resolves each sub-field against its slice of the submitted value and
writes to none of them.

Field::resolveWith() now declares the shared supertype so subclasses can widen
it: an atomic field resolves to one ResolvedField, a composite to one per
sub-field. Atomic narrows it back so ordinary fields keep given, value and
transformed without a cast.

Field gains accepts(), the value-taking counterpart to hasValue(), for callers
holding a resolved value rather than reading one off the field.

CompositeValidationResult loses its __clone(). It deep-cloned the composite, so
a filtered result pointed at a copy of the field rather than the one in the
schema - the same detached-copy bug already catalogued for Field\ValidationResult
in docs/LIMITATIONS.md. The test asserting the old behaviour codified the bug and
is rewritten to assert identity is preserved, as the duplicate-field-name test
was for B6.

Verified by differential: every field type crossed with seventeen input shapes and
both optionality settings, comparing the old input()+validate() path against
validateWith(). 352 cases, no regressions, and one intentional difference.

That difference: a required field with no value used to report only type=Failed,
with its constraints absent from the result entirely. It now reports them as
Skipped, which is what the README has always documented - "if the shape check
fails, the remaining constraints are skipped rather than failed" - and matches
what the wrong-shape path already did. Six cases, all of the same shape.

### Add ResolvedField and the resolve/validate seam (Stage 1, additive)

`e579cdc1` · 2026-08-23

Introduces the object that per-request state moves to, and the one place a value
meets a field. Nothing is wired up to it yet, so this changes no behaviour: the
existing validate() path still runs and the suite is unchanged at 1036 tests.

ResolvedField extends AggregatedValidationResult, so it *is* a field's result
rather than holding one - anyFailed(), getFailed() and the computed status all
apply directly, and Field\ValidationResult can be absorbed into it later. It
carries the effective definition, the value exactly as submitted, the value
actually validated (submitted, or the default when nothing was), which rules
altered the field, and the constraint outcomes.

given and value are kept apart deliberately. Re-rendering a rejected form has to
echo back what was typed rather than a default that replaced it, or the user is
shown something they did not enter.

transformed reports the value in whatever type the field is really about. It is
null when the field was skipped, because nothing was supplied and nothing was
required, and throws when the field failed or has not been checked - returning
null there would hide the difference between absent and wrong. Field::transform()
is the per-type hook, identity by default, so richer types can be filled in from
2.1 without touching the seam.

Rule\AppliedOutcome records that a rule matched and changed a field. This is what
lets meraki/schema-html delete deriveRuleEffects(), which currently re-runs the
rule engine in the presentation layer to answer why a field is optional.

Field gains resolveWith() and validateWith(). Resolution and validation stay two
steps because a form is rendered before it is submitted, which is what Pending
has always meant. Neither writes to the field, so resolving one field
concurrently with different values cannot interfere - the property B7 needs.
validate() is marked deprecated rather than removed while callers migrate.

Adds tests/ResolvedFieldTest: 12 tests fixing the contract, including that
filtering preserves the field and its values, that a constraint cannot be
reported twice, and each of transformed's three behaviours.

## v1.14.0 — 2026-08-22

### Prepare the 1.14.0 changelog entry

`8e6d288c` · 2026-08-22

Promotes Unreleased to 1.14.0 and drops the pre-release warning from the header,
which is no longer true.

### Update the docs for a stable release; restore Field\Placeholder

`05f5fa11` · 2026-08-22

The README still described the package as alpha and named four defects that are
now fixed. It now leads with what 1.14.0 actually is - stable, with one
documented limitation - rather than a list of things that are no longer true.
The CI badges are live, the stale beta.2 and beta.3 references point at 2.0.0,
and the field tables gained allowSchemes, the scheme constraint and the card
checksum.

Adds a short section on URIs and schemes, explaining that any scheme is allowed
until allowSchemes() says otherwise. That matches every other allowlist in the
library: empty means unrestricted, and naming one blocks everything else. What
counts as a safe scheme is the application's call, which is why there is no
default.

Restores Field\Placeholder. It was removed with the dead validator subsystem on
the grounds of being unreachable, which was true of this package but not of
meraki/schema-json, whose FieldSerializer registers it as a field type. The check
that justified the removal only grepped the siblings' src for one spelling of the
name and missed three references. It carries a note that it is a presentation
concern and goes in 2.0.0.

branch-alias moves to 1.14.x-dev so the path repositories can resolve ^1.14.

### Raise src to PHPStan level 2; analyse tests separately

`1c1ccf61` · 2026-08-22

src is now clean at level 2, up from the level-1 floor the whole project sat at.
Three things got it there.

Twelve more @readonly annotations were false: Field's name, value, defaultValue,
resolvedValue, inputGiven and optional are all written after construction by
input(), prefill() and rename(), and EmailAddress and Enum had the same on
properties their fluent setters write. The intent survives as prose. They become
genuinely readonly in 2.0, when per-request state moves to ResolvedField.

Four generic annotations were wrong rather than merely imprecise:
AggregatedValidationResult carried @extends for an interface it implements,
Facade documented a Closure<T> when Closure is not generic, PhoneNumber still
passed two template arguments to Atomic after the serialization types were
removed, and Money declared a non-empty-array default of [].

ValidationResult now declares its status as a real interface property hook rather
than a @property-read docblock that nothing enforced. PHP 8.5 makes that
available, and it removes four accesses to an undeclared property.

Also deletes assertSerializedChildrenContainsFieldWithNameOf from two test cases.
It referenced Meraki\Schema\Field\Serialized, deleted with the serialization
code, and was never called.

Analysis is split in two. phpstan.neon covers src at level 2;
phpstan-tests.neon covers tests at level 1. What holds tests back is twelve
accesses to magic __get properties - $composite->nickname, $variant->password -
which PHPStan cannot type and which are the point of the tests doing it. That
magic goes away in 2.0 with Composite, so annotating around it now would be work
thrown away.

Both configs record the measured cost of going higher, so the ratchet has honest
figures: for src, level 3 costs 2 errors, level 4 costs 19, level 5 costs 24 and
level 6 costs 132. Several are worth fixing on merit rather than for the level -
dead null comparisons in Composite, and Atomic::validate() declaring a narrower
return type than it produces.

1024 tests. Both analysis runs and composer audit clean.

### Verify the Luhn check digit on card numbers (B3)

`cd077164` · 2026-08-22

CreditCard checked that a number was 13-19 digits but never its check digit, so
4111111111111112 passed. Every card number carries a Luhn check digit (ISO/IEC
7812-1), and one that fails it is not a card number - no processor will take it,
so catching it here saves a round trip.

Reported as <name>.number.checksum. It returns null, so the constraint is
skipped, when the number is not yet in a state the checksum can speak to; the
digit and length rules on the sub-field report that instead.

Every card number in this package's own fixtures failed the check. They were
generated numbers that had never been valid, and it_validates_valid_credit_cards
asserted that they were - twelve data sets, across every brand. They are repaired
by recomputing only the final digit, which keeps each brand's IIN prefix, digit
length and spacing exactly as the fixtures intended.

One of the repaired numbers is in it_fails_if_number_is_too_long, where making it
Luhn-valid sharpens the test: it now fails for its length alone rather than for
length and checksum together.

Adds tests/Field/CreditCardLuhnTest: valid checksums across seven brands and
lengths, four ways of breaking one, that the shape checks still run first, that
printed whitespace is still tolerated, and that the failure is attributed to the
number rather than the card.

1024 tests, PHPStan and composer audit clean.

### Validate URIs with PHP's RFC 3986 parser; require PHP 8.5 (B2)

`3155339a` · 2026-08-22

Every group in the Uri field's pattern was optional, so it collapsed to
is_string():

    $schema->addUriField('url');
    $schema->validate(['url' => 'not a url at all !!'])->anyFailed();  // false

It now parses with PHP's own Uri\Rfc3986\Uri. The URI grammar is a matter of
public record, so it comes from the platform rather than from a pattern of this
library's making - the same principle already applied to phone numbers and
addresses, and Uri was the field that most violated it. Absolute and relative
references, URLs and URNs all parse; malformed input does not.

This raises the requirement to PHP 8.5, where those classes landed. Doing it now
rather than later is deliberate: there is no stable release yet, so there is
nobody to disrupt, and it turns the fix into a deletion rather than an addition.
A runtime feature check was rejected - the same schema accepting different values
on different PHP versions is a bad property for a validation library
specifically. CI drops to 8.5 accordingly.

allowSchemes() is new. Without it any scheme is accepted, because a URI field is
not always a web link and urn:, mailto: and tel: are legitimate values. Anything
rendered back into a page or followed as a redirect should declare one, at which
point javascript: and data: fail. The richer story - absolute versus relative,
URL versus URN, RFC 3986 versus WHATWG - is a 2.x feature, not this.

Adds tests/Field/UriValidationTest: 16 tests over malformed input, well-formed
absolute, relative, URN and mailto references, the allowlist in both directions,
and that the length constraints still apply.

1010 tests, PHPStan and composer audit clean.

### Split the roadmap: 1.14.0 ships the defects, 2.0.0 is the redesign

`a2dafae0` · 2026-08-22

The audit found a library close to shippable, blocked by a short list of
concrete defects. Since then the plan grew - the ResolvedField seam, typed
scopes, matcher rules, createXField, removing 'type' as a constraint, and
redesigning every structured type. Each is a genuine improvement, and together
they were pushing stable further away than when the audit started. Sixteen alpha
tags is long enough.

So the two are separated:

1.14.0 is the defects, with essentially today's API and no architectural change.
B1, B4, B5, B6 and B8 are already fixed; B2 and B3 remain, plus raising PHPStan
to level 5.

B7 ships documented rather than fixed, deliberately. Its real fix is the seam,
which belongs to 2.0.0. Serial reuse is safe, concurrent reuse is not, and
building the schema per request costs 0.25 ms - an honest limitation with a cheap
workaround, rather than a reason to hold every other fix behind a rewrite.

2.0.0 is the redesign, breaking by construction and so a major regardless: the
seam, real PHP types in place of the HTML-form legacy, Composite removed in
favour of distinct structured types, Variant kept as a union type, typed scopes,
matcher rules, and the API freeze.

2.1 onwards are additive feature releases, each gating on the PHP version its
feature needs - a richer Uri on PHP's native RFC 3986 and WHATWG classes, and
Duration on PHP 8.6's own type once that exists.

Also replaces the old "Planned features" tail with a pointer to the release
table, which had started to duplicate it.

### Report malformed composite input as a failure, not an exception (B1)

`957b9ea4` · 2026-08-22

Money, Address, CreditCard and Collection raised an uncaught exception when
handed a value that was not a set of sub-field values:

    $schema->addMoneyField('price', ['AUD' => 2]);
    $schema->validate(['price' => 'not-an-array']);
    // InvalidArgumentException: Input value must be an array, an object, or null.

Form input is attacker-controlled, so any form using one of those field types was
one crafted request away from a 500.

Unusable input is now kept as it came rather than rejected during processing,
validateValue() refuses anything that is not an array, and validate() reports
'type' against the composite while skipping its sub-fields: nothing reached them,
and faulting each one would bury the single real problem.

Two things had to change beyond the throw itself.

valueProvided() returned false for a non-array, so resolveValue() discarded the
malformed input and fell back to the default. The composite then validated an
array of nulls and passed - quietly checking something the caller never sent.
Input that could not be mapped is still input, so it now counts as provided and
reaches validate() to be reported. This is also what makes an optional composite
fail on bad input rather than skip it: optional excuses an absent value, never a
malformed one.

Collection dropped a non-array item with a bare continue, silently shortening the
list. Such items are now kept so the item's required fields fail.

Address and CreditCard override process() and index the result - to settle the
country code, and to pad the expiry and strip spaces from the number - so both
now return early when the value is not a set of sub-field values.

Adds tests/MalformedCompositeInputTest: 22 tests over the four field types and
four unusable value shapes, the optional case in both directions, an unusable
item inside a list, and the shape of the reported failure. Well-formed input,
including objects, still passes.

Verified across all three packages: schema 994 tests, schema-json 22,
schema-html 146. PHPStan and composer audit clean.

### Validate names; reject duplicate field names (B5, B6)

`38b61a2d` · 2026-08-22

B5 - names were not validated at all. Empty names were accepted, as were names
containing the scope-path separators / and #, whitespace, and leading digits.

Property\Name now validates per segment: a name must start with a letter or
underscore and contain only letters, digits, underscores and hyphens. A dot is
still permitted between segments, because that is how a composite names its
sub-fields, so addr.line1 remains valid while .foo, foo. and a..b do not.

Hyphens are deliberately allowed. They are harmless - scope paths split on / so a
hyphenated name is a single segment - and schema names like 'create-person' were
already in use. Every one of the 100 distinct names across the three packages
passes the rule.

A top-level field carrying a dot is rejected separately, in Facade::addField(),
because it would be indistinguishable from an addr.line1 or price.amount
belonging to some composite. Composites are unaffected: they get their dotted
names from Composite::prefixNamesWith(), never through addField().

B6 - Field\Set::mutableAdd() silently discarded a field whose name was already
taken, so a schema quietly validated something other than what was written. It
now throws. The existing test asserting the old behaviour codified the bug and
has been rewritten as a rejection test, with a second covering two distinct
fields sharing a name, since identity is the name rather than the object.

Adds tests/FieldNamingTest with 19 tests over both defects, including the
composite sub-field naming that has to keep working.

Verified across all three packages: schema 972 tests, schema-json 22,
schema-html 146. PHPStan and composer audit clean.

Also moves B4 to the fixed section of docs/LIMITATIONS.md, where it should have
gone when the dead code was deleted.

### Reject the schema back-reference as a scope target (B8)

`ca9bc6ce` · 2026-08-22

A scope path stepping into Field::$schema recursed Field -> Facade -> Field,
restarting the path each lap because Facade::traverse() rewound the cursor on
entry, and ran until memory was exhausted:

    (new Scope('#/fields/has_log_book/schema'))->resolve($schema);
    // PHP Fatal error: Allowed memory size exhausted

This was reachable from data rather than only from code. meraki/schema-json
deserialises rule targets straight into scope strings - new Equals($data->target)
and new _Require($data->field) - so a schema document from an untrusted source
could hang the process that loaded it.

The fix is narrow, because the problem was never open addressing. A field's
public properties are its API and #/fields/x/min and #/fields/x/optional are
legitimate targets. The back-reference is the one property that is not field data
but a pointer to the field's owner, and it was the only property on a field whose
type implemented ScopeTarget - so:

- it is now rejected as a scope target, named by a NOT_ADDRESSABLE constant that
  says why;
- the instanceof ScopeTarget recursion branch is removed, since the
  back-reference was its only reachable target and it existed solely to enable
  the loop;
- Facade::traverse() no longer rewinds, so traversal walks from the cursor and
  Scope::resolve() stays the single entry point that resets it.

Adds tests/ScopeTest, which also closes the gap where Scope - the whole
rule-targeting mechanism - had no dedicated test. It covers the back-reference
rejection, that public configuration and optionality stay addressable, that value
resolves to the resolved value, and that a scope survives repeated resolution.

Verified across all three packages: schema 952 tests, schema-json 22,
schema-html 146. PHPStan and composer audit clean.

### Fix invalid coverage targets; add composer scripts for local CI parity

`0d3dcd8d` · 2026-08-22

Two #[CoversClass] attributes named Meraki\Schema\ValidationResult, which is an
interface and so not a valid coverage target:

- Field\ValidationResultTest meant the Field\ValidationResult class, which it
  already imports as FieldValidationResult. The bare name resolved to the
  interface because of the other use statement in the file.
- The abstract ValidationResultTestCase was redundant as well as invalid: its
  subclass AggregatedValidationResultTestCase already covers the abstract
  AggregatedValidationResult, which is a valid target.

Adds composer scripts so the pipeline can be reproduced locally rather than
discovered in CI:

  composer test            phpunit
  composer test:coverage   phpunit with coverage
  composer analyse         phpstan
  composer ci              everything CI runs, same order

test:coverage sets XDEBUG_MODE through @putenv rather than a shell prefix, so it
works on Windows as well as POSIX.

Verified locally with a coverage driver installed: 944 tests, no risky failures,
line coverage 73.78%.

### Fix build failing on code coverage

`81624ef8` · 2026-08-22

### Add CI; clear the dead serialization annotations; PHPStan green at level 1

`9ab8825f` · 2026-08-21

CI runs the suite on PHP 8.4 and 8.5, plus PHPStan, composer validate --strict
and composer audit, with a weekly scheduled run so a newly-published advisory
surfaces without waiting for a push.

For CI to mean anything it has to be green, which took three things.

composer audit is now clean. phpunit needed -W to move: 11.5.50 pulls newer
transitive deps that the lock file pinned, so a plain update stopped at 11.5.26
and left CVE-2026-24765 in place. Now on 11.5.56.

The @phpstan-type Serialized* annotations are gone from 43 files. Serialization
moved to meraki/schema-json in 1.12.0-alpha but its type annotations stayed
behind, describing a shape the core no longer produces - and they were circular
(SerializedField referencing itself) or unresolvable, which was most of what
PHPStan reported at level 0. The TSerialized template parameter and the second
generic argument on @extends/@implements go with them.

@readonly is removed from nine properties that carry defaults and are written by
the fluent setters, so the annotation was false. They may become genuinely
readonly when the definition is sealed in 1.14; claiming it now was wrong.

Also removes tests/ValidationResultMessageProviderTestCase, orphaned by the
previous commit.

PHPStan is enforced at level 1 - what the codebase passes cleanly - so CI is
meaningful rather than permanently red. The neon file records the counts at
levels 2, 3 and 5 for the ratchet, and raising it is now an explicit beta.1 gate.
No baseline file: errors get fixed, not recorded.

944 tests pass. PHPStan, composer validate --strict and composer audit all clean.

### Delete the unreachable validator subsystem; add PHPStan (B4)

`7e04e974` · 2026-08-21

Adds PHPStan at level 5 and removes what it immediately found.

The validator subsystem was superseded when a field's type became its class and
the shape check became validateValue(). What was left referenced classes that no
longer exist - ValidatorName, Constraint, Field\Type, SchemaValidator,
ConditionFactory, OutcomeFactory, Comparison - so touching any of it was a fatal
error. It shipped in the package and showed up in IDE autocompletion. Nothing on
the supported API path reached it.

Removed: Meraki\Schema\Validator and everything under Validator\, Field\Validator,
the root ConstraintValidationResult, ValidationResultMessageProvider,
Field\Placeholder, Field\Structured, and the four exceptions used only by
Validator\Set. Their tests go with them, so the suite drops from 957 to 944.

Field\AtomicMultiValue stays: EmailAddress and File extend it through an aliased
import, which an earlier grep missed. The test suite caught it.

Also fixed, both found by PHPStan:

- Field\Money caught MathException and TypeError without importing them, so they
  resolved to Meraki\Schema\Field\MathException and \TypeError and those catch
  clauses could never match. Latent rather than live: the shape check rejects a
  bad amount before the constraints run, so nothing reaches them today.
- Stale imports of non-existent classes in Facade, Rule and Rule\Condition\Equals.

PHPStan level 5 now reports 121 errors in src, down from 181, with every
class.notFound gone. The rest are annotation and generics issues to work through
before the API freeze.

944 tests pass.

### Confirm the 1.14 rule DSL: rules are values, allOf/anyOf compose conditions

`ed3eca12` · 2026-08-21

Rules are built as values and then added, rather than declared inline through a
closure configurator:

    $schema->addRule(
        $schema->when($hasLogBook)->equals(true)
            ->thenRequire($logBookTime)
            ->otherwiseMakeOptional($logBookTime)
    );

    $schema->addRule(
        $schema->allOf(
            $schema->when($whoFor)->equals('someone_else'),
            $schema->when($whoManages)->equals('participant'),
        )->thenRequire($email)->otherwiseIgnore($email)
    );

allOf()/anyOf() take conditions, never rules, so outcomes attach to the composed
rule and there is exactly one then/otherwise per rule - no question of whose
outcomes fire. A rule being a value means it can be held in a variable, built
elsewhere and reused.

The matcher table drops its shorthand column. An earlier sketch had
createRuleFor($f)->whenItIsAtLeast(18) alongside when($f)->isAtLeast(18); two
names for one constraint is exactly what API-REVIEW.md exists to remove, so only
the matcher spelling survives.

Docs only. No src/ or tests/ changes.

### Add the 1.14 API confirmation checklist; drop 'type' as a constraint

`27c7b6d5` · 2026-08-21

Every feature and constraint now has to be confirmed across three surfaces -
definition, rule, and resolved field - before the rc.1 freeze, tracked in
docs/API-REVIEW.md. Nothing ships unconfirmed.

The checklist is not a formality. The definition surface grew field by field and
there are six spellings of "minimum" in use (minLengthOf, minOf, atLeast,
minFileSizeOf, minItems, minNumberOfLowercase), allow() means four different
things across Address, PhoneNumber, Enum and Money, Date carries both until()
and to() for one constraint, and min/max mean length on some fields and value on
others. Constraint names are public API - downstream message providers match on
them - so these have to be settled before the freeze rather than after.

'type' stops being reported as a constraint. It never was one: every other
constraint narrows a value already known to be the right shape, while 'type'
decides whether a usable value exists at all, which is the precondition for the
rest. It also conflates "no value supplied" with "value supplied but wrong
shape", which schema-html currently disentangles by hand with a comment
apologising for it. Both become structurally distinct on ResolvedField.

Also catalogued: getConstraints() is public on Passphrase and Variant but
protected everywhere else.

Docs only. No src/ or tests/ changes; 957 tests still pass.

### Correct rule matcher naming; remove the field back-reference from the 1.14 design

`8c6edb4b` · 2026-08-21

Matchers are Jasmine-like, not Jasmine: a rule should read as a sentence, not
copy expect().toBe(). So the subject verbs are when/andWhen, and the matcher is
the predicate:

    Rule::allOf()->when($whoFor)->equals('someone_else')
                 ->andWhen($whoManages)->equals('participant')
                 ->thenRequire($email)->otherwiseIgnore($email);

    $schema->createRuleFor($age)->whenItIsAtLeast(18)->thenRequire($licence);

The vocabulary table now carries both spellings - the bare matcher for the
multi-subject form and the whenIt* shorthand where createRuleFor() has already
bound the subject - and both build the same condition object. Rule::allOf() /
anyOf() replace Facade::whenAllMatch() / whenAnyMatch(), building a rule
standalone so it can be composed and added explicitly.

Two structural decisions recorded:

Path resolution moves out of the field classes. Field and Facade are the only
ScopeTarget implementations, and traverse() moves to a resolver working against
the working set, leaving fields as plain definitions. This makes B8 impossible
rather than patched: Field::$schema is the only property on a field whose type
implements ScopeTarget, so the recursion branch exists solely to step into it.
Open addressing is unaffected - the resolver reads a field's public properties
instead of asking the field to resolve itself - and sub-field and collection-item
addressing get one place to be implemented.

Field::$schema and pairWith() are removed. The back-reference exists only for
pairWith(), which checks for a duplicate name, adds a field, and registers rules
- all schema operations wearing a field's clothes, and incoherent under
create-then-add where the field is not attached yet. The matcher API expresses
the same pairing without it. Cost is 14 references across six files, only two of
them in src/.

Docs only. No src/ or tests/ changes; 957 tests still pass.

### Document the 1.14 rule authoring API and the scope traversal defects

`1153f187` · 2026-08-21

Designing the rule layer against the ResolvedField seam surfaced one defect
serious enough to document now and three smaller gaps.

B8: a scope path stepping into Field::$schema recurses Field -> Facade ->
Field, restarting the path each lap because Facade::traverse() rewinds the
cursor on entry, until memory is exhausted. Reachable from data, since
schema-json deserializes rule targets straight into scope strings, so a schema
document you did not author can hang the process that loads it. The fix is
narrow: the back-reference is not field API (it exists only for pairWith()),
and the unconditional rewind is what makes the cycle infinite. Addressing a
field's other public properties stays - those are its API.

Also documented: rules cannot target composite sub-fields or collection items,
Scope carries a mutable cursor that is unsafe under concurrency, and the root
scope '#/' cannot be constructed.

The roadmap gains the 1.14 authoring API. Conditions become a Jasmine-style
matcher vocabulary - expect(x)->toBe(y), toBeAtLeast, toBeOneOf, toMatch,
notToBeEmpty - of which all but equals/not_equals is new capability. otherwise()
adds an else-branch of outcomes, which today needs a second rule with a
hand-inverted condition. Scopes become typed and immutable while keeping open
property addressing and the existing string wire format.

Two rejected alternatives are recorded so they are not revisited: outcomes
carrying modified fields rather than naming operations (snapshots do not
compose, and lose the intent deriveRuleEffects() matches on), and closure-backed
conditions (not serializable to JSON for another runtime, not introspectable via
getScopes(), and deserializing one is deserializing code).

Docs only. No src/ or tests/ changes; 957 tests still pass.

## v1.13.1-alpha — 2026-08-21

### Document project status, limitations, roadmap and comparisons

`8d72c30f` · 2026-08-21

The package has sat at v1.13.0-alpha across 16 alpha tags with nothing in the
README warning that it is pre-release. This adds the missing context and
corrects three claims that were wrong.

New docs:

- docs/LIMITATIONS.md - known defects, each with a runnable reproducer, plus
  the intentional behaviour that surprises people and a workaround for each
- docs/ROADMAP.md - the release verdict, the 1.14 beta ladder, and the
  immutable-definition + ResolvedField architecture that gets there
- docs/COMPARISON.md - how this compares to symfony/validator, symfony/form,
  nette/forms, Laravel, respect/validation, cuyz/valinor and opis/json-schema
- CHANGELOG.md - backfilled across all 16 tags

README:

- a pre-release status banner and a "Why this library" section
- "Where error messages come from" - the messages-are-a-UI-concern decision,
  which was previously invisible to a reader and reads as an omission
- "Long-lived processes" - serial reuse is safe, concurrent reuse is not
- "Input expectations" - the core takes typed values, not raw request strings
- a Constraint names reference table. These are public API, since downstream
  message providers match on them

Corrections:

- 'minLength' was cited as a constraint name; a Text field emits 'min'
- validate() was described as storing nothing on the fields. It stores no
  result, but it does write the submitted input onto them
- the camelCase note omitted the qualified paths composites use for the
  constraints they apply to a sub-field (addr.postal_code.format)

No src/ or tests/ changes: 957 tests still pass. composer.json gains metadata
only (keywords, homepage, support, suggest); composer.lock is a content-hash
resync with no dependency drift.

## v1.13.0-alpha — 2026-08-08

### Make Address region-aware; add Facade::for()

`6881260c` · 2026-08-08

Address was five bare Text sub-fields with no validation at all. It now takes a
whitelist of ISO 3166-1 alpha-2 countries, exactly as PhoneNumber does, with the
per-country rules coming from Google's libaddressinput data via
commerceguys/addressing rather than hand-typed tables:

  $schema->addAddressField('billing', ['AU']);

That gives a four-digit postcode, Australia's required parts, its eight states as
a closed set, and a country settled as AU without asking for it. Empty stays
free-form: anything goes, and only line1 is required.

A sub-field the whitelist leaves exactly one value for is "determined" — core
prefills it and reports it via determined(), so it is still part of the value and
the address never serializes without its country. Whether to hide it is left to
the UI.

Countries differ in more than postcodes: Singapore has no administrative area and
Hong Kong no postal code, so neither is required there. With several countries
allowed, a part is required only if every one of them requires it, while
validation applies the rules of whichever country was actually chosen.

Field\Address\Type says what the address is for, using HL7 FHIR's Address.type
vocabulary plus 'either' for no restriction. Either (the default) and Postal
accept a PO box; Physical and Both reject one. The default means the flag adds no
new failures to existing schemas.

Facade::for() declares the region once so Address and PhoneNumber need not repeat
it. Deliberately not Money: currency does not follow from a region.

This validates shape, not existence — a postcode matching \d{4} is well-formed,
not real, and postcode never implies state.

Serialization and rendering examples move to the packages that own them, and
Composite constraint names now resolve from the right so a nested composite (an
address inside a collection) can name its constraints at all.

BREAKING: sub-fields renamed to libaddressinput's field set —
street->line1 (+ new line2), city->locality, state->administrative_area,
postcode->postal_code, country->country_code (now a code, not a name).

### Let a Scope be resolved more than once

`03258b89` · 2026-08-08

Resolving a scope walks a cursor to the end of the path, and rule outcomes build
their Scope once in the constructor — so the second resolution started from an
exhausted cursor and threw OutOfBoundsException.

Rules get applied more than once in ordinary use: input() applies them, and so
does validate(). Any schema whose outcomes actually fire therefore blew up on
$schema->input($data) followed by $schema->validate($data).

resolve() now rewinds first. Resolution is a whole-path operation, so starting at
the top is what it always meant to do; rewind() already existed for the Iterator
interface and simply was not called.

### Fix optional sub-fields in Composite::validate()

`b945b42d` · 2026-08-08

Two bugs, both of which meant an optional sub-field never behaved as one.

An empty optional sub-field was type-checked anyway, and every field type rejects
null, so it reported `type: Failed` instead of `Skipped`. Nothing hit this because
every built-in composite requires all of its parts.

Separately, the skip conditions called $this->valueProvided() — the *composite's*
implementation, which requires is_array and so is always false for a scalar
sub-field. That collapsed the condition to $field->optional, so an optional
sub-field's constraints were skipped even when a value *was* provided.

The provided-check now delegates to the sub-field via a new Field::hasValue(),
which exists because valueProvided() is protected and therefore resolves to the
caller's implementation rather than the field's own.

## v1.12.1-alpha — 2026-07-04

### Require PHP 8.4 (matches the property hooks / asymmetric visibility in use)

`545a97e4` · 2026-07-04

The code uses PHP 8.4-only features; the constraint was still ^8.2, so an install
on 8.2/8.3 could hit fatals. Tighten to ^8.4 and refresh the lock hash.

## v1.12.0-alpha — 2026-07-01

### Add rule-driven conditional fields and repeatable collections

`56fa64dc` · 2026-07-01

Core support for the no-JS conditional / multi-step forms in schema-html:

- Rule\FieldBuilder + Field::pairWith(): author type-safe, serialisable rules
  against Field objects, with compound AND/OR; Field gains an ignore-input flag.
- Rule\Condition\NotEquals and Rule\Outcome\Ignore; Rule\Builder gains
  thenIgnore()/whenNotEquals() so the declarative and field-based builders match.
- Field\Collection: a repeatable list of grouped fields (minItems/maxItems), now
  allowing composite sub-fields per item (fixed nested-prefix handling in Composite).
- Boolean::mustBeAccepted(); Scope matches field names exactly (no normalisation).
- Rename Address sub-field postal_code -> postcode.

### Validate phone numbers with libphonenumber; support local format

`1aefc5f4` · 2026-06-06

Replace the hand-rolled E.164 regex with giggsey/libphonenumber-for-php-lite.
PhoneNumber now mirrors Money's allow-list ergonomics: configure allowed
countries (ISO 3166-1 alpha-2) via the constructor or allow(), and an
optional number-type restriction via ofType() (mobile/landline/either/any).

With no countries configured the field accepts any valid international
(E.164) number, as before. Once countries are allowed it also accepts those
countries' national/local format (e.g. AU "0412 345 678") and restricts a
number's country via the new 'allowedCountries' constraint; 'numberType'
enforces the type restriction.

addPhoneNumberField() gains an $allowedCountries argument (2nd position, like
addMoneyField). Tests are rewritten against real example numbers from
libphonenumber, replacing the old loose fixtures the library rightly rejects.

### Update README and examples for the simplified result API

`25326c64` · 2026-06-05

Document computed status, pure validate(), caller-owned roll-up, and
nested-only composite input; switch examples off the removed passed()/
failed() helpers and rewrite validate-field.php for the current API.

### Simplify the validation result API

`c5b39c59` · 2026-06-05

Three related changes to how validation results are produced and read:

1. Composite input accepts only nested local keys. The redundant
   fully-qualified form (['price.amount' => ...] nested under 'price')
   is gone; callers nest naturally (['price' => ['amount' => ...]]).

2. validate() is now a pure query. Fields no longer store their last
   result in a $validationResult property; the result is returned and
   owned by the caller. This removes per-request mutable state from the
   field definitions.

3. Aggregate results drop the cached $status and the rolled-up
   passed()/failed()/skipped()/pending() helpers. $status is now a
   computed (virtual) property derived on demand from a single
   calculateStatus() on the base class, so it can never go stale after
   an immutable add/remove/merge. Callers decide what "valid" means via
   the granular anyFailed()/allPassed()/... predicates.

### Document the schema core: usage, fields, rules, design decisions

`27f302ed` · 2026-06-04

Replaces the stub README with a full guide: quick start, reading
validation results, optional/default values, the field-type table,
composite/variant fields, conditional rules, and the rationale for the
recent removals (Property\Type, Field\Factory, split-out serialization
and HTML rendering).

### Remove serialization, Field\Factory and Property\Type from the core

`e218ed3c` · 2026-06-04

Serialization now lives entirely in meraki/schema-json, so the domain drops it:
- serialize()/deserialize() on every field, rule, condition, outcome and the
  Facade; the Serialization/ and Deserialization/ dirs; and the deserialization-
  only factories (Rule\ConditionFactory, Rule\OutcomeFactory, Property\Factory).
- Field\Factory removed; Facade::addXField constructs fields directly.
- Property\Type removed; a field's value/shape check is now a public
  validateValue() on the field itself (previously the Type's validator closure).
  Duplicate-field detection in a Set uses the field class name.

Method renamed validateType() -> validateValue() to reflect that it validates
the value/shape, not a 'type'. Tests updated; 900 green.

## v1.11.0-alpha — 2026-06-03

### Use camelCase for all serialized field keys and constraint names

`9bf96643` · 2026-06-03

Serialized property keys and constraint names now match the camelCase PHP
properties: minCount/maxCount/minSize/maxSize/allowedTypes/disallowedTypes
(File), oneOf (Enum), anyOf (Password), allowedDomains/disallowedDomains
(EmailAddress), allowedCurrencies (Money), precisionUnit/precisionMode
(Time/DateTime). Updates field tests and the JSON fixture. Breaking change to
the serialized format and constraint identifiers.

### Remove unused 'source' from File field metadata

`75ed48c5` · 2026-06-03

source was a required input key feeding allowed_sources/disallowed_sources
constraints that were never implemented (stubbed). It did nothing but add
friction. Drop it from Metadata, the File field (properties, structure check,
serialize/deserialize, type docblocks) and the tests. 'name' is kept as
intrinsic file identity for future extension/pattern constraints.

### Let the File field accept Metadata value objects as input

`9e65b73c` · 2026-06-03

cast() only handled metadata arrays: a single Metadata instance validated as
type-invalid, and a list of Metadata threw an uncaught TypeError. Accept a
Metadata instance or a metadata array for each file, given singly or as a list
(or a mix). Keeps the field's input contract to file metadata (no resources/IO).

### Fix composite fields ignoring locally-keyed and object input

`f68737eb` · 2026-06-03

Composite::process() only filled in subfield values keyed by each subfield's
full prefixed name (e.g. 'price.currency'), so natural nested input keyed by
the local name ('currency') was dropped: inputGiven was true but every
subfield resolved to null. It also rejected objects.

Normalise the incoming value onto the subfields, accepting each value by either
the full prefixed key or the local key, and accept array|object|null. Fixes
input(), prefill() and validate() for Address/CreditCard/Money.

### Add public conditions() accessor to AllOf/AnyOf condition groups

`cf7757c9` · 2026-06-03

Lets external code (meraki/schema-json) read a group's child conditions for
serialization without reflection. Additive and non-breaking.

### Add public precisionMode() accessors to Time and DateTime

`f363d45d` · 2026-06-03

Exposes the precision mode ('truncate'/'preserve') that was only derivable
from the private caster, so external code (the upcoming meraki/schema-json
package) can serialize it without reflection. Additive and non-breaking.

### Address audit findings: number decimals, rule reset, minor fixes

`c2c55044` · 2026-06-03

Number: default `step` was 1, which (combined with the default min) made a
plain number field reject every non-integer. Default it to 0 = "no step
constraint"; integer-only stays opt-in via scaleTo(0)/inIncrementsOf().

Facade: conditional rule outcomes (require/makeOptional) mutate fields but
were never reverted, so a reused schema accumulated effects across
validations (a field required by one run stayed required when the condition
no longer held). applyRules() now snapshots each field's baseline optionality
the first time it sees it and restores it before re-applying rules.

Minor:
- Variant::input() now guards incompatible subfield input (as prefill does)
  and records $this->validationResult on every path.
- AllOf::matches() returns false for an empty group instead of being
  vacuously true, matching AnyOf so an empty rule never fires unconditionally.
- Builder now initialises its inherited Rule state so the readonly
  condition/outcomes are never left uninitialised.

Regression tests: number accepts decimals by default; rule effects reset
between validations.

### Fix composite validation reporting Passed when a subfield fails

`f821ac61` · 2026-06-03

Two related defects let composite fields (Money, Address, Variant fallback)
report Passed even when a sub-constraint failed:

1. AggregatedValidationResult cached `status` in each subclass constructor
   but the immutable add()/remove()/merge() helpers cloned without
   recomputing it. Composite::validate() builds each sub-field result
   incrementally via add(), so a failed constraint added after the initial
   'type' result never updated the cached status -> stale Passed.
2. CompositeValidationResult::calculateStatus() used allFailed() instead of
   anyFailed(), so a composite with one failed + one passed subfield was
   classified Passed.

Centralise status in AggregatedValidationResult: an abstract calculateStatus()
is computed in the constructor and re-run after add()/remove()/merge()
(filter() must not, as calculateStatus() relies on it). Fix the composite
status logic to anyFailed().

Regression tests: status recomputation on add/remove (shared across result
types) and an end-to-end Money below-minimum check.

### Reconcile test suite with refactored API (suite now green)

`4849ea26` · 2026-06-03

The suite hadn't kept up with a large refactor and couldn't even run
(fatal: Attribute implements the now-concrete Property). Bring it back to
green: 969 tests, 0 failures.

Removed obsolete types (superseded by the callable-based constraint system
and the Property model):
- src/Attribute.php, src/Constraint.php and their tests
- 6 Rule test files targeting a removed pre-refactor API (Rule::matchAll,
  OutcomeGroup, Printer, Condition::create, Outcome::require, ...)

Source fixes (genuine bugs the tests exposed):
- Address/Variant serialize(): array_map closure return type was the
  non-matching Serialized; serialize() returns a plain object (stdClass).
- File::deserialize(): read snake_case keys (min_count/max_count/min_size/
  max_size) to match what serialize() emits; previously read camelCase and
  fed null into atLeast()/atMost(), breaking the round-trip.

Test updates to current API:
- Pass the required FieldFactory to every Field::deserialize() call.
- Renamed classes: SchemaDeserializer -> Deserialization\Deserializer,
  Deserializer\Json -> Deserialization\JsonDeserializer, SchemaSerializer
  -> Serialization\Serializer, Serializer\Json -> Serialization\JsonSerializer,
  SchemaFacade -> Facade.
- Property\Type now requires a validator; Factory::createMoney needs
  allowedCurrencies.
- Serialized field shape assertions use snake_case keys; composite tests
  read ->fields instead of ->children().
- Align deserializer "missing name" expected message with current source.

### Fix Date interval constraint to accept multiples of the interval

`6dedec4d` · 2026-06-03

validateInterval() only passed when the value was exactly `from` or
exactly one interval away (it compared Period::between() with isEqualTo).
Any later date failed, so the P1D default rejected essentially every
date (e.g. a date-of-birth field with from 1900-01-01).

The interval means "valid every N", so the gap from `from` must be a
whole multiple of it. Day-based intervals (incl. the P1D default) are
checked in O(1) via the day count; month/year intervals step from `from`.

Extend the Date interval data providers to cover multiples (two weeks on
P7D, three months on P1M), a monthly interval, and a far-future date on
the daily default. Also pass the required FieldFactory to Date::deserialize
in the serialization test (stale call after the API change).

### Fix Facade::validate dropping input from object value objects

`5daae03b` · 2026-06-03

extractData() converted object input with get_object_vars(), which only
exposes plain public properties. Value objects that expose data through
__get()/accessors had every field silently fed null, so required fields
failed validation on otherwise-valid input. isset()/?? is not a fix here
either, since it invokes __isset() (commonly omitted), bypassing __get().

Read each field directly instead: public-property fast path, __get()
fallback, and a null fallback for genuinely-absent properties (avoids
warnings under failOnWarning).

This surfaced a latent crash in SchemaValidationResult: its match(true)
had no arm for a mix of passed + skipped results, throwing
UnhandledMatchError. Mirror Field\ValidationResult::calculateStatus()
(mixed -> Passed).

Add regression tests covering public-property, __get, absent-property,
and mixed pass/skip cases, plus an example reproducing the original bug.

### Store result of last validation run

`7d7a6283` · 2025-07-28

### Update to match new API changes

`d6da193c` · 2025-07-06

### Refactor unneeded class into method

`12d4006a` · 2025-07-06

### Better reflect class' purpose in name

`de372fba` · 2025-07-06

## v1.9.0-alpha — 2025-07-04

### Rollback incompatible version of brick/math

`e8570734` · 2025-07-04

### Sync requirements

`9609e600` · 2025-07-04

## v1.8.0-alpha — 2025-07-01

### Remove obsolete classes

`7a00853b` · 2025-07-01

### Remove obsolete classes

`bd3539e1` · 2025-07-01

### Update API to have ability to check equality with other variables

`2f2f980a` · 2025-07-01

### Update API to require fields and check equivalency with other fields/values

`a2e690fe` · 2025-07-01

### Add way to require fields in outcomes

`87a42f9e` · 2025-07-01

### Update implementations to meet interface's new API

`259865b2` · 2025-07-01

### Conditions should have access to Schema during matching

`c5ab7c8e` · 2025-07-01

### Provide way to require fields

`9c72502b` · 2025-07-01

### Rules should have access to Schema during evaluation/matching

`28bd1d76` · 2025-07-01

### Fix bug where field wasn't being resolved correctly

`07f8c2db` · 2025-07-01

### Fix bug for undefined variable

`3278b480` · 2025-07-01

### Always apply rules after schema modifications

`dddc607e` · 2025-07-01

### Better spacing to make it easier to read code

`40e65461` · 2025-07-01

### Provide clearer comments describing what happens

`72ae0fe1` · 2025-07-01

### Resolve Scope against schema

`7e9f2bfd` · 2025-07-01

### Update schema to match new functionality

`e4f40522` · 2025-06-25

### Show how to provide default values

`0292862e` · 2025-06-23

### Update schema to match new api changes

`07b01572` · 2025-06-23

### Use factory methods

`6bef7b0d` · 2025-06-23

### Make condition groups explicit

`c09ede61` · 2025-06-23

### Update use of Rule class to match new API

`42b1dcfe` · 2025-06-23

### Mark explicilty as condition groups and implement api

`29c66006` · 2025-06-23

### Fix clashing method names with sub classes

`06f10ada` · 2025-06-23

### Fix rules not nesting correctly

`7d960bdc` · 2025-06-23

### Remove obsolete API comments

`1ca296a7` · 2025-06-23

### Remove unneeded example

`6eefee18` · 2025-06-23

### Make scope target and remove deserialize() factory in favour of proper field factory

`3f14adb6` · 2025-06-23

### Rename facade and implement new API

`d7e22997` · 2025-06-23

### Add implementation for creating schema/field properties

`65c4c7db` · 2025-06-23

### Add way to get field by name or throw

`86bae76d` · 2025-06-23

### Make scope more useful when working with scope paths

`d32efe67` · 2025-06-23

### Add uniform way of handling scope resolution results

`6cb98c0a` · 2025-06-23

### Allow schema objects to be 'scoped' or traversed

`c75e3635` · 2025-06-23

### Remove unneeded class

`3a975eef` · 2025-06-23

### Add a 'placeholder' field which does nothing really

`20f93c33` · 2025-06-23

### Add example of traversing schema

`93988130` · 2025-06-23

### Better structure for serialization classes

`e4a9273b` · 2025-06-21

### Support field deserialization and variants

`d1265d5f` · 2025-06-21

### Make field factory requirement explicit

`254b4f19` · 2025-06-21

### Simplify rule implementations and serialization/deserialization

`2cb9324a` · 2025-06-19

### Simplify implementation of schema deserialization

`434d0d6a` · 2025-06-19

### Move code inside pre tags

`932e467f` · 2025-06-19

### Simplify serialization/deserialization type system

`b742eed9` · 2025-06-19

### Update schema to match new format

`020c2fcb` · 2025-06-17

### Uniformity in serialization and deserialization DTOs and remove unneeded methods

`03f67ec7` · 2025-06-17

### Update code to match new API

`ee2c1650` · 2025-06-17

### Allow property to be used

`5294c84e` · 2025-06-11

### Update code to use big integers to avoid any potential float/integer conversion errors

`617d0072` · 2025-06-10

### Fix implicit float/integer conversions

`1dbb52e9` · 2025-06-10

### Use correct config option to show deprecation warnings

`0a8cd8da` · 2025-06-10

### Add support for serializing and deserializing

`661f69da` · 2025-06-10

### Add support for serializing and deserializing

`808b3770` · 2025-06-10

### Use Field's deserialization method (which is just a factory method anyway)

`807f1514` · 2025-06-10

### Add missing API methods

`29ea05c6` · 2025-06-10

### Add support for serializing and deserializing

`b99ee359` · 2025-06-10

### Add ability to change scale after instantiation

`4618d225` · 2025-06-09

### Add support for serializing and deserializing

`dbd2255c` · 2025-06-08

### Add support for serializing and deserializing

`27596805` · 2025-06-08

### Provide assertion method for checking a serialized field exists by its name

`87690957` · 2025-06-08

### Implement new method added to Serialized contract

`7d4b7c31` · 2025-06-08

### Fix incorrect types when using static analysis

`276c3d3a` · 2025-06-08

### Add ability to handle sub-fields for composite/variant fields

`a91e8f6e` · 2025-06-08

### Add support for serialization/deserialization

`cd639636` · 2025-06-08

### Add support for serialization/deserialization

`8e14b6ec` · 2025-06-08

### Add support for serialization/deserialization

`f3ac6f50` · 2025-06-07

### Add support for serialization/deserialization

`515c8329` · 2025-06-07

### Add support for serialization/deserialization

`50e9af3e` · 2025-06-07

### Add support for serialization/deserialization

`a79de013` · 2025-06-06

### Add support for serialization/deserialization

`b06ebfcf` · 2025-06-06

### Add support for serialization/deserialization

`48ceb21a` · 2025-06-06

### Add support for serialization/deserialization

`3537d54a` · 2025-06-06

### Add support for serialization/deserialization

`05d7698e` · 2025-06-06

### Add support for serialization/deserialization

`194476be` · 2025-06-06

### Add support for serialization/deserialization

`59d5e47a` · 2025-06-06

### Add support for serialization/deserialization

`a6630a98` · 2025-06-06

### Add support for serialization/deserialization

`d5d8be64` · 2025-06-06

### Add support for serialization/deserialization

`66fb95ba` · 2025-06-06

### Add support for serialization/deserialization

`065f005b` · 2025-06-06

### Add serialized field dto

`7b708954` · 2025-06-06

### Add field abstraction

`635b89d7` · 2025-06-05

### Fix bug where invalid patterns were allowed

`4bd62c3d` · 2025-06-05

### Let composite field deal with setting values

`20a804f4` · 2025-06-05

### Remove unneccessary method call

`51439695` · 2025-06-05

### Fix bug where correct value wasn't being used

`7b81bbdb` · 2025-06-05

### Remove redundent code and fix bug where length constraints weren't correctly validated

`29bc0a93` · 2025-06-01

### Add way to remove characters from input before validation

`2966d199` · 2025-06-01

### Fix bug where field names were not being prefixed with composite name

`f2055604` · 2025-06-01

### Fix missing validator function for type property

`be0047c4` · 2025-06-01

### Use right implementation of an email address format

`effef535` · 2025-06-01

### Use right implementation of time precision

`33379b0a` · 2025-06-01

### Use right implementation of time precision

`1290aab7` · 2025-06-01

### Fix static analysis issue

`ffdcfe17` · 2025-06-01

### Fix bug with factory methods

`daf106f3` · 2025-06-01

### Fix bug with factory methods

`f32e36e9` · 2025-06-01

### Make Time field use its own implementation of time precision

`7d36f7bd` · 2025-06-01

### Make DateTime use its own implementation of time precision

`1a70cab3` · 2025-06-01

### Allow the use of different types of email address formats

`efa13d36` · 2025-06-01

### Simplify constraints by using 'Range' objects instead of min/max properties

`d355aac0` · 2025-06-01

### Use a value object for better handling of files

`e3151bb2` · 2025-06-01

### Simplify constructor

`e900e933` · 2025-06-01

### Better namespacing for handling datetime precisions and casting behaviour

`0d765789` · 2025-05-31

### Fix bug with results having duplicate names

`b940ed6c` · 2025-05-31

### Fix bug when trying to add result with same name more than once

`8268143d` · 2025-05-31

### Fix bug when trying to use currency as string when it could potentially be ull

`98636d43` · 2025-05-31

### Use Field::process() for pre-processing of values

`545b3932` · 2025-05-31

### Fix bug where status wasn't reflecting correctly if all fields were skipped

`e82ea9ae` · 2025-05-31

### Allow composite fields to automatically process values and their defaults

`4a2fc7cb` · 2025-05-31

### Remove redundent code

`5ed8afcb` · 2025-05-31

### Add missing tests

`1d6da533` · 2025-05-31

### Add way to get field names and ensure uniqueness in set

`71bf07db` · 2025-05-31

### Allow access to validator used to validate a field's type

`4b08f4bf` · 2025-05-31

### Add way to check equivalency

`6dbd8d8f` · 2025-05-31

### Remove old namespace implementation for better one

`09cabe08` · 2025-05-31

### Ensure only unique results are allowed and add tests

`4fe734ed` · 2025-05-31

### Fix bug where a validation result would pass even when constraints have failed

`001f5cd5` · 2025-05-27

### Allow variant field to use this validation result

`533c6193` · 2025-05-27

### Add a 'variant' field type

`fad988be` · 2025-05-27

### Update to reflect library-provided fields

`c1576826` · 2025-05-26

### Field types are now just fields

`684cb5eb` · 2025-05-26

### Add composite-specific validation

`17960c8e` · 2025-05-26

### Convert uuid type into a field

`1d4a9381` · 2025-05-26

### Convert url type into a field (and rename to uri)

`8b04596a` · 2025-05-26

### Convert credit_card type into a field

`f74bf4af` · 2025-05-25

### Mention missing line1 and line2

`6ac64f91` · 2025-05-25

### Convert address type into a field

`ab01e057` · 2025-05-25

### Fields are responsible for testing their own default values

`5202ba5f` · 2025-05-25

### Convert money type into a field

`29a46c43` · 2025-05-25

### Field type now has access to validator

`887cf989` · 2025-05-24

### Ensure composite field is cloned correctly

`b73d2194` · 2025-05-23

### Add specific validation result for constraints

`d8b3de6b` · 2025-05-23

### Make validation result return type more restrictive

`22845158` · 2025-05-23

### Add way to retrieve a specific constraint result by its name

`fa7409bf` · 2025-05-23

### Add support for composite fields

`97d334c3` · 2025-05-23

### Provide specific validation result for composite fields

`f71d7102` · 2025-05-22

### Remove overwritten methods from AggregatedValidationResult

`f9b9f7fe` · 2025-05-22

### Convert from interface to abstract class

`8e35842a` · 2025-05-22

### Write more meaningful tests

`0a430d07` · 2025-05-22

### Use more descriptive naming of methods/properties

`9f6557ce` · 2025-05-22

### Fix incorrect/missing typehints

`f4c6cfd9` · 2025-05-22

### Add tests to 'field' group

`c489037a` · 2025-05-22

### Prefer working with decimals instead of floats for accuracy

`ea652889` · 2025-05-21

### Allow prefixing with itself and make immutable

`d495d6bf` · 2025-05-18

### Remove timezone component from time field (it doesn't belong)

`d738c8dd` · 2025-05-18

### Convert time type into a field

`600ac279` · 2025-05-18

### Convert phone_number type into a field

`272d7403` · 2025-05-17

### Add 'enum' field type

`e3846313` · 2025-05-17

### Remove deprecated interface

`dc738101` · 2025-05-15

### Refactor passphrase policy back into a field

`a9fe4dc2` · 2025-05-14

### Refactor password policy back into a field

`e1dc4ef8` · 2025-05-14

### Remove individual classes in favour of using a 'secret' field type instead

`4c05c892` · 2025-05-11

### Refactor to use policy parser

`0b148a5b` · 2025-05-11

### new instances not using factory methods must provide arguments

`cde15dfc` · 2025-05-11

### Factor out shared policy parsing into its own class

`99ea55e8` · 2025-05-11

### Actually implement the password policy

`2e7b91c0` · 2025-05-09

### Implement a passphrase policy

`8f7536a2` · 2025-05-09

### Implement a password policy

`b8d3be16` · 2025-05-09

### Provide interface for secret field policies to implement

`666e88b1` · 2025-05-09

### Field types no longer need to process their own value

`ecb2cbfe` · 2025-05-07

### Convert text type into a field

`b3e590b8` · 2025-05-07

### Convert number type into a field

`af3420f9` · 2025-05-07

### Convert name type into a field

`d383bd87` · 2025-05-06

### Convert File type into a field

`37f3b560` · 2025-05-06

### Convert EmailAddress type into a field

`86d07b2e` · 2025-05-06

### Remove old implementation of credit card field

`4038495a` · 2025-05-03

### Convert Duration type into a field

`54a288ca` · 2025-05-03

### Fix method signature not matching abstract method

`85d3e628` · 2025-05-03

### Fix method signature not matching abstract method

`9643b539` · 2025-05-03

### Provide a way to differentiate between different types of fields

`0baea270` · 2025-05-03

### Convert DateTime type into a field

`77bc662f` · 2025-05-03

### Convert Date type into a field

`cf574c89` · 2025-05-01

### Convert Boolean type into a field

`87424d80` · 2025-05-01

### Convert Address type into a field

`2f9ce23d` · 2025-05-01

### Remove attributes in favour of properties

`8d472099` · 2025-04-30

This was neccessary as attributes didn't make sense in
the concept of a "schema" domain, whereas a "property"
did. In addition, it was deemed way to complicated to make
attributes reuseable (while still having logic) across
fields. In the end, it made sense to make properties
behave more like dumb wrappers.

### Add properties used for fields

`8f8f8cc5` · 2025-04-30

### Minor refactoring

`c6802bce` · 2025-04-30

### Simplify implementation

`3e8b0597` · 2025-04-30

### Remove useless method

`21c32ea1` · 2025-04-23

### Remove enum as a field type\n\n It actually makes sense to restrict the value to a defined list, when needed, by using the OneOf validator. this allows you to turn any field type into an enum. It also gets around the fact that the enum type would then be the only field type that requires a validator or has additional validation logic. which would make the implementation far more complex than it is now.

`68e39879` · 2025-04-22

### Update field factory to use composition and field types

`789bc510` · 2025-04-22

### Allow casting of field names to strings

`135023e8` · 2025-04-22

### Update example to use new API

`08b2b07b` · 2025-04-22

### Update class heirarchy to match new namespaces/layout

`76817698` · 2025-04-22

### Remove old Field* implementations

`6d24df9c` · 2025-04-22

### Remove test case for old field implementation

`f88ab50d` · 2025-04-22

### Refactor to use composition over inheritance

`e83260d2` · 2025-04-22

### Provide a way to generate error messages for failed validation results

`954e1813` · 2025-04-22

### Remove old Field* implementations

`002f1325` · 2025-04-22

### Remove uncessary 'use' statements

`077840e7` · 2025-04-22

### Make sure an aggregated validation result is still a validation result and passes validation result tests

`e3ce2470` · 2025-04-22

### Provide a field-specific validator

`6fa68365` · 2025-04-22

### Schema field have their own validation result

`736b2ed6` · 2025-04-22

### Encapsulate a field's value/defaultValue

`45cf971b` · 2025-04-22

### Validators have their own result

`bf9537af` · 2025-04-22

### Simplify interface

`0f486f55` · 2025-04-22

### Rename file to better reflect behaviour

`9c65993b` · 2025-04-22

### Add support for fields only allowing a value from a predefined list

`dacc039a` · 2025-04-22

### Use correct namespace

`4e73858c` · 2025-04-22

### Remove attributes that are now classified as validators

`2951472c` · 2025-04-22

### Remove unneeded class

`43c874a1` · 2025-04-22

### Fields will now use composition over inheritance

`9f86dbbe` · 2025-04-22

### Make a 'field name' not a field

`242164e8` · 2025-04-22

### Fix tests not passing

`db6b5885` · 2025-04-20

### Add specific validation/validator exceptions

`95ce26bf` · 2025-04-20

### Allow validators to have their own name

`3bec7df0` · 2025-04-20

### Restructure 'attribute constraints' as 'validators' for better encapsulation/control

`97225fd9` · 2025-04-20

### Remove classes used for test fixtures from namespace

`a37325f1` · 2025-04-20

### Add way to distinguish between validators with dependencies and validators without

`fb697ffe` · 2025-04-20

### Move field validation logic into dedicated validator classes

`f7085427` · 2025-04-20

### No longer need to rely on having field value passed in separately to field

`5eb62de2` · 2025-04-18

### Fix validator interface not found error

`7848bec5` · 2025-04-17

### Fix class being in wrong namespace

`a0fe730c` · 2025-04-17

### Extract out 'dependent' validators to its own interface

`1e4cc199` · 2025-04-17

### Refactor validation interface/logic

`28728012` · 2025-04-17

### Remove deprecated class

`f5f9fb5e` · 2025-04-17

### Defering validation logic is not meant to be on fields

`eb2dbc66` · 2025-04-16

### Use enum for managing validation state statuses

`c4c9618e` · 2025-04-16

### Remove commented-out code

`3679a395` · 2025-04-16

### Fix value not being added properly to attributes

`3f970d73` · 2025-04-16

### Give correct type information for

`b61e2ffd` · 2025-04-16

### Remove unused class import

`ef8fdff2` · 2025-04-16

### Value no longer uses DefaultValue objects (will be removed soon)

`6fc6d355` · 2025-04-16

### Fix bug were DefaultValue attribute was not being taken into account

`666756cb` · 2025-04-16

### Ignore test server file

`7998040b` · 2025-04-16

### A default value is used by default regardless of whether a field is optional or not

`07252098` · 2025-04-16

### Remove unneeded docblock type hints

`05e75f50` · 2025-04-14

### Add type information for better static analysis/IDE autocompletion

`a6f9eb83` · 2025-04-14

### Provide ability to prefill multiple fields at once

`7c8ff979` · 2025-04-13

## v1.7.0-alpha — 2025-04-13

### Handle conversion of input types separate to field classes

`3c088518` · 2025-04-13

## v1.6.0-alpha — 2025-04-12

### remove constraint validation (temporarily)

`1196de86` · 2025-04-12

### Fix ull not being handled properly during type validation

`f9301752` · 2025-04-12

### Fix failing test cases for valid phone numbers

`47a17fb1` · 2025-04-12

### Fix bug where formatting chars were allowed around country code

`aa5a8b0e` · 2025-04-12

### Fix failing tests for min/max constraints

`193708e3` · 2025-04-12

### Remove sanitization of input.

`b2fc43c0` · 2025-04-12

You should validate input sanitize output. In addition, the original
purpose of sanitization methods was for type conversion, which has been
delegated to a specific interface instead, as it doesn't belong in the
field.

## v1.5.0-alpha — 2025-03-28

### Provide way to input data to all fields at once

`9a9383ac` · 2025-03-28

### Fix field validation state not updating after rules applied

`c4a3dc64` · 2025-03-28

### Provide ability to treat an empty string input as if no input was given

`fd010b6b` · 2025-03-27

### Add missing test case for handling empty strings as input

`ef54d975` · 2025-03-27

## v1.4.0-alpha — 2025-03-25

### Start implementing a 'money' field type

`808de84c` · 2025-03-25

### Add a way to represent a non-composite field

`236c4e27` · 2025-03-25

### A composite field should not be tested directly but by subclasses

`d5eac712` · 2025-03-25

### Fix serialized composite fields not having name

`355a6fec` · 2025-03-25

### Fix not being able to step in less than whole steps

`60273485` · 2025-03-25

### Fix times not validating within proper increments

`4fb488e6` · 2025-03-21

### Fix step increments being off by a day

`705ba958` · 2025-03-21

### Implement a 'datetime' field type

`0087c549` · 2025-03-21

### Add way to convert a 'string number' to an actual number in fields

`67e2a284` · 2025-02-25

### Add missing test cases

`9029a2be` · 2025-02-25

### Use more appropriate methods for setting constraints

`e05bf613` · 2025-02-25

### Provide test cases for what constitutes a 'valid name'

`fb24904e` · 2025-02-24

### Fix names not allowing commas

`68fcfaea` · 2025-02-24

### Add support for an 'email address' field type

`498906c1` · 2025-02-24

### Add ability for inputs to use a multiple attribute

`2183559f` · 2025-02-24

### Implement the 'date' field type

`9ab17a48` · 2025-02-24

### Implement a 'phone number' field type

`dd145555` · 2025-02-24

### Add a way to remove characters from a field's value

`6967d6a5` · 2025-02-24

### Add ability to find attributes by their fully qualified class name

`88cad112` · 2025-02-24

### Add missing tests for 'boolean' field type

`5d57df89` · 2025-02-16

### Allow using multiple sanitizers

`380669fc` · 2025-02-16

### Add support to sanitize values (e.g. convert/coerce field value to right type)

`8db0ba5f` · 2025-02-16

## v1.3.0-alpha — 2025-01-12

### Add some basic test cases

`88787174` · 2025-01-12

### More semantic type for field validation result

`8dbed364` · 2025-01-12

### Field validation is handled automatically

`a1408296` · 2025-01-12

Validation of constraints has been simplified massively. In addition,
a field's "type" is now a constraint. You can access the validation
result at anytime throughout a field's lifecycle. Tests have been
updated to use custom assert* methods.

### Fix enum field not passing one_of constraint even when value is valid

`e5ad3631` · 2024-12-26

### Consider empty string input as no value given

`b33c6b14` · 2024-12-26

### Add ability to define how a value is considered as "provided"

`3c3a6908` · 2024-12-26

## v1.2.0-alpha — 2024-12-26

### Add support for comparting on/off boolean values from HTTP request input

`77c5209f` · 2024-12-26

### Fix using dash instead of underscore in field type value

`0f4e6ff3` · 2024-12-25

### Fix using dash insterad of underscore in field type value

`b0d3474a` · 2024-12-25

### Fix using dash instead of underscore in field type value

`6c527b91` · 2024-12-25

### Fix money field type having incorrect field type

`31a802af` · 2024-12-25

### Fix file field type having incorrect field type

`56efa7ca` · 2024-12-25

### Fix email-address field type having incorrect field type

`e5d30376` · 2024-12-25

### Fix date-time field type having incorrect field type

`0037cf4c` · 2024-12-25

### Fix date field type having incorrect field type

`8c375e6b` · 2024-12-25

### Fix credit-card field type having incorrect field type

`c8999519` · 2024-12-25

### Fix address field having incorrect field type

`e44db684` · 2024-12-25

### Fix boolean fields having incorrect field type

`e6ba819d` · 2024-12-25

### Fix undefined array key when using "one-of" attribute.

`8e6956c5` · 2024-12-24

## v1.1.0-alpha — 2024-12-05

### Remove unneeded namespace imports

`84dcb0c3` · 2024-12-05

### Update code to match API changes

`be6cb4fe` · 2024-12-05

### Fix bug where only one outcome was being serialized

`84844af0` · 2024-12-05

### Fix incorrect type for outcomes

`5720d0af` · 2024-12-05

### Add example of pretty printing schema rules

`b4217ee2` · 2024-12-05

### Fix missing namespaces

`fb49ff07` · 2024-12-05

### Fix namespace missing

`907bd1df` · 2024-12-05

### Add more complex validation example

`de07b698` · 2024-12-05

### Simplified example

`deff5546` · 2024-12-05

### Use proper constructor methods instead of magic method

`3b2f2ea7` · 2024-12-05

### Allow prefixing all names of fields at once

`3885d0dc` · 2024-12-05

### Allow grouping and nesting of conditions

`df002abe` · 2024-12-05

### Add support for conditions

`88307465` · 2024-12-05

### Add basic support for composite field types

`0a10e78e` · 2024-12-05

### Add ability to dump rules in a readable manner

`a3375630` · 2024-12-05

### Add way to deal with multiple field validation results

`a4a6f1c2` · 2024-12-05

### Add way to deal with multiple constraint validation results

`b161ad11` · 2024-12-05

### Provide uniform interface for working with groups of outcomes

`7ccd0265` · 2024-12-05

### Add ability to define outcomes for a rule

`6f713902` · 2024-12-05

### Add a way to represent a schema 'rule'

`6d0e06c8` · 2024-12-05

### Allow applying all rules at once to schema

`b5c12ad9` · 2024-12-05

### Move logic to appropriate condition/outcome groups

`876a5f40` · 2024-12-05

### Add way of handling rules in a uniform way

`66a79286` · 2024-12-05

### Add needed fixtures

`f5e946b5` · 2024-12-05

### All attributes are schema properties

`46ce08e1` · 2024-12-05

### Add way to represent properties of a schema

`c521bb50` · 2024-12-05

### Allow way to navigate and access schema attributes/properties

`ff5dc5d5` · 2024-12-04

### Track status of field's value validation

`f436c8c5` · 2024-12-04

### Remove in favour of explicit naming conventions

`cc3fa2c7` · 2024-12-04

### Add way to verify status of field validation

`f8bda2b9` · 2024-12-04

### Add way to validate schemas

`8d87bff8` · 2024-12-04

### Implementation to provide status of a constraint validation

`6bb01972` · 2024-12-04

### Add implementation to handle multiple results as if it was one

`71c9c12e` · 2024-12-04

### Is now an abstraction

`377e8f59` · 2024-12-04

### Better naming of methods and favour explicit naming rather than 'auto guessing'

`3a96af41` · 2024-12-04

### Better naming of methods and remove 'auto guessing' of names

`4ac4b2eb` · 2024-12-04

### Use better type names and add tests

`2306cbdf` · 2024-12-04

### Provide a basic HTML renderer for rendering schemas

`9cecb848` · 2024-12-04

### Remove unneeded api methods/properties

`883d6e22` · 2024-12-04

### Add validators for attributes

`0c6daa44` · 2024-12-04

### Add default fields

`52fb0c8b` · 2024-12-04

### Add default attributes

`8c08f638` · 2024-12-04

### Define what an attribute is

`09ef663c` · 2024-12-04

### Give a more appropriate name

`feff2241` · 2024-12-04

### Remove 'Constraint' namespace as all constraints are attributes

`f940f95d` · 2024-12-04

### Add missing test cases

`f878dee7` · 2024-12-04

### Add support for 'maximum' constraints

`332b6bc0` · 2024-12-04

### Add support for 'minimum' constraints

`ff3eae2e` · 2024-12-04

### Use named field type creation methods and add rule example

`a43107e7` · 2024-12-04

### Remove hardcoded schema

`ad5e9ebb` · 2024-12-04

### Add example of how to render a schema

`916db9bc` · 2024-12-04

### Add an example of what a serialised schema looks like

`7a83225b` · 2024-12-04

### Enable spell check for ABNF files

`574106f8` · 2024-12-04

### Change package name, library namespace, and add dependency

`4768eb41` · 2024-12-04

## v1.0.0-alpha — 2024-09-21

### Initial commit

`e5c06a03` · 2024-09-21

### Initial commit

`fb003843` · 2024-09-20
