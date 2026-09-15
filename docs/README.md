# Documentation

Start with [the README](../README.md) if you have not already — it covers installing, a first
schema, and reading a result.

## If you are deciding whether to use this

| | |
| --- | --- |
| [COMPARISON.md](COMPARISON.md) | How it compares to `symfony/validator`, `symfony/form`, `nette/forms`, Laravel, `respect/validation`, `cuyz/valinor` and `opis/json-schema` — including where each of them is the better choice |
| [DESIGN.md](DESIGN.md) | The decisions that shape everything else, and what each one costs. Mostly refusals |
| [LIMITATIONS.md](LIMITATIONS.md) | What is still wrong or absent, with reproducers you can run |
| [ROADMAP.md](ROADMAP.md) | What is planned, and what is deliberately not |

## If you are using it

| | |
| --- | --- |
| [API.md](API.md) | Every field's configuration and the constraint names it reports, with the reasoning behind each name |
| [FIELD-API.md](FIELD-API.md) | The contract a field implements — `parse()`, shape versus constraint, order of operations |
| [EXTENDING.md](EXTENDING.md) | Writing your own field type. About forty lines, and nothing to register |
| [../examples/](../examples/) | Small runnable examples, one aspect each. Every one runs in CI |

## If you are working on it

| | |
| --- | --- |
| [CODING-STYLE.md](CODING-STYLE.md) | The conventions, and why each exists |
| [../CHANGELOG.md](../CHANGELOG.md) | Generated from the commit history by `php tools/changelog.php` |

## The examples

Each is about one thing, and each runs — `composer test:examples` fails the build if any does not.

| | |
| --- | --- |
| [validate-field.php](../examples/validate-field.php) | One field on its own, no schema. Shape versus constraints |
| [validate.php](../examples/validate.php) | A whole schema, and reading the result |
| [collections.php](../examples/collections.php) | Repeatable rows: naming them, counting them, refusing duplicates |
| [value-equality.php](../examples/value-equality.php) | Why a field's value decides equality, and what `==` gets wrong |
| [branching-rules.php](../examples/branching-rules.php) | One field's value deciding another's requirements |
| [comparing-fields.php](../examples/comparing-fields.php) | Comparing two fields, whole or part by part |
| [validate-with-rules.php](../examples/validate-with-rules.php) | Rules end to end, with the result's provenance |
| [interactive-input.php](../examples/interactive-input.php) | Asking one field at a time and re-asking until it is acceptable |
| [custom-field.php](../examples/custom-field.php) | A field type defined entirely outside this package |
| [workshop-booking.php](../examples/workshop-booking.php) | A realistic form, putting the rest together |

## The sibling packages

The core knows nothing about HTTP, HTML or JSON. Those live next door:

| | |
| --- | --- |
| [`meraki/schema-json`](https://github.com/merakiframework/schema-json) | Serialize a schema and read it back |
| [`meraki/schema-html`](https://github.com/merakiframework/schema-html) | Render a schema as a form, normalize request input, produce messages |
