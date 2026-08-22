# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **The project is pre-release.** Every release so far is an alpha and the public API is
> not yet stable. See [docs/ROADMAP.md](docs/ROADMAP.md) for the path to `1.14.0`, the
> first stable release, and [docs/LIMITATIONS.md](docs/LIMITATIONS.md) for what is
> currently broken.
>
> Entries before `1.10.0-alpha` are summarised rather than itemised.

## [Unreleased]

### Changed

- **BREAKING —** requires PHP 8.5, for `Uri\Rfc3986\Uri`.

- **BREAKING —** field and schema names are validated. A name must start with a letter
  or underscore and contain only letters, digits, underscores and hyphens; `.` is allowed
  only where a composite joins its sub-field names, and a top-level field carrying one is
  rejected. Empty names, whitespace, `/` and `#` were previously accepted (**B5**).
- **BREAKING —** adding a second field under an existing name now throws instead of
  silently discarding the definition (**B6**).

### Fixed

- **B2** — `Uri` accepted any string: every group in its pattern was optional, so it
  collapsed to `is_string()`. It now parses with PHP's own `Uri\Rfc3986\Uri`. Adds
  `allowSchemes()`, which a caller should declare for anything rendered back into a page
  or followed as a redirect.

- **B1** — `Money`, `Address`, `CreditCard` and `Collection` raised an uncaught
  exception when handed a value that was not a set of sub-field values, turning a crafted
  request into a 500. It is now reported as a `type` failure against the composite, with
  its sub-fields skipped. Being optional no longer excuses malformed input: an absent
  value is skipped, a bad one fails.

### Security

- **B8** — a scope path stepping into `Field::$schema` recursed `Field` → `Facade` →
  `Field`, restarting the path each lap because `Facade::traverse()` rewound the cursor on
  entry, until memory was exhausted. Reachable from data: `meraki/schema-json`
  deserialises rule targets straight into scope strings, so a schema document from an
  untrusted source could hang the process loading it.

  The back-reference is now rejected as a scope target. It was the only property on a
  field whose type implemented `ScopeTarget`, so the recursion branch existed solely to
  enable the loop and is removed; `Facade::traverse()` no longer rewinds. Addressing a
  field's other public properties (`#/fields/x/min`, `#/fields/x/optional`) is unaffected.

### Added

- Continuous integration (`.github/workflows/tests.yml`): the suite on PHP 8.4 and 8.5,
  PHPStan, `composer validate --strict` and `composer audit`, plus a weekly scheduled run
  so a newly-published advisory surfaces without waiting for a push.
- PHPStan as a development dependency, enforced at level 1 — what the codebase passes
  cleanly today. Raising it is tracked in `docs/ROADMAP.md`; no baseline file, by design.

- `docs/ROADMAP.md` documents the rule authoring API planned for `1.14`: Jasmine-style
  matchers (`expect(...)->toBe(...)`, `toBeAtLeast`, `toBeOneOf`, `toMatch`, ...),
  `otherwise()` as an else-branch of outcomes, and typed immutable scopes. Path resolution
  moves out of the field classes; `Field::$schema` and `pairWith()` are removed. Records why
  outcomes name operations rather than carrying modified fields, and why closure-backed
  conditions were rejected.

- `docs/API-REVIEW.md` — the per-feature, per-constraint API confirmation checklist for
  `1.14`, covering the definition, rule and resolved-field surfaces. Records that `type`
  stops being reported as a constraint, and catalogues the naming inconsistencies
  (six spellings of "minimum", four meanings of `allow()`) that have to be settled before
  the API freeze.

### Removed

- **BREAKING —** the unreachable validator subsystem: `Meraki\Schema\Validator` and
  everything under `Validator\`, `Field\Validator`, the root `ConstraintValidationResult`,
  `ValidationResultMessageProvider`, `Field\Placeholder`, `Field\Structured`, and the four
  exceptions used only by it. These referenced classes that no longer existed and were a
  fatal error to touch. Nothing on the supported API path reached them.

- Composer scripts: `test`, `test:coverage`, `analyse`, and `ci` (which runs everything
  CI runs, in the same order, so a build can be reproduced locally before pushing).

### Fixed

- Two `#[CoversClass]` attributes named `Meraki\Schema\ValidationResult`, an interface,
  which is not a valid coverage target. The one on `Field\ValidationResultTest` meant the
  `Field\ValidationResult` class; the one on the abstract `ValidationResultTestCase` was
  redundant, since its subclass already covers `AggregatedValidationResult`.

- CI failed on PHP 8.4 and 8.5 with hundreds of "executed code that is not listed as code
  to be covered" errors. `beStrictAboutCoverageMetadata` was on but had never taken effect
  locally, because it only applies when a coverage driver is present and none was
  installed; enabling coverage in CI switched it on. Turned off, with the reasoning
  recorded in `phpunit.xml`. `requireCoverageMetadata` stays on.
- Dead `@phpstan-type Serialized*` annotations across 43 files, left behind when
  serialization moved to `meraki/schema-json` in `1.12.0-alpha`. They described a shape the
  core no longer produces and were circular or unresolvable.
- `@readonly` on nine properties that carry defaults and are written by the fluent setters,
  so the annotation was simply false.
- `Field\Money` caught `MathException` and `TypeError` without importing them, so they
  resolved to `Meraki\Schema\Field\*` and those catch clauses could never match. Latent
  rather than live — the shape check rejects bad amounts before the constraints run.
- Stale imports of classes that no longer exist, in `Facade`, `Rule` and `Rule\Condition\Equals`.

### Documented

- **B8** — a scope path stepping into `Field::$schema` recurses until memory is exhausted,
  and is reachable from a deserialized schema document. See
  [docs/LIMITATIONS.md](docs/LIMITATIONS.md#b8).
- Rules cannot target composite sub-fields or collection items; `Scope` carries a mutable
  cursor that is unsafe under concurrency; the root scope `#/` cannot be constructed.

## [1.13.1-alpha] — 2026-08-21

Documentation only. No source or test changes, and no behaviour change.

### Added

- `CHANGELOG.md`, `docs/COMPARISON.md`, `docs/LIMITATIONS.md` and `docs/ROADMAP.md`.
- README sections covering project status, where error messages come from, long-lived
  process support, input expectations, and the constraint names each field emits.

### Fixed

- README documented constraint names that the library does not emit (`minLength` for a
  `Text` field, which actually emits `min`), and described `validate()` as storing nothing
  on the fields when it does store the submitted input.

## [1.13.0-alpha] — 2026-08-08

### Added

- `Facade::for()` declares the countries a schema is for, so region-aware fields
  (`Address`, `PhoneNumber`) added afterwards inherit them instead of repeating a country
  list per field. An explicit list on the field still wins; an explicit `[]` means
  free-form.
- `Address` is region-aware, applying per-country rules from Google's libaddressinput data
  via `commerceguys/addressing`. A single allowed country is treated as determined:
  prefilled, reported by `determined()`, and requiring no input.

### Changed

- **BREAKING —** `Address` sub-fields now follow libaddressinput's field set:

  | Old | New |
  | --- | --- |
  | `street` | `line1` (plus a new `line2` for a unit or level) |
  | `city` | `locality` |
  | `state` | `administrative_area` |
  | `postcode` | `postal_code` |
  | `country` | `country_code` — an ISO 3166-1 alpha-2 code, not a free-text name |

  `organization` and `dependent_locality` are new and optional. Property access under an
  old name throws. An *input* array under an old name fails more quietly: unrecognised
  keys are ignored, so a `street` key leaves `line1` empty and fails its required check.
  Grep for the old names rather than relying on tests to catch it.

### Fixed

- A `Scope` can now be resolved more than once, so validating the same schema repeatedly
  applies its rules correctly each time.
- Optional sub-fields of a composite that were left empty are now skipped rather than
  type-checked against `null` (which every field type rejects).

## [1.12.1-alpha] — 2026-07-04

### Changed

- **BREAKING —** requires PHP 8.4 or later, matching the property hooks and asymmetric
  visibility already in use.

## [1.12.0-alpha] — 2026-07-01

### Added

- Rule-driven conditional fields and repeatable collection fields
  (`Facade::addCollectionField()`).
- Phone numbers are validated with libphonenumber, including local (national) format.

### Changed

- **BREAKING —** the validation result API was simplified. Aggregate results expose
  granular predicates such as `anyFailed()`, `allPassed()`, `anySkipped()` and
  `anyPending()` rather than a single opinionated verdict, and roll-up is left to the
  caller.
- **BREAKING —** serialization, `Field\Factory` and `Property\Type` were removed from the
  core. A field's type is now its class, and the shape check is a single
  `validateValue(mixed): bool`. JSON serialization moved to `meraki/schema-json`.

## [1.11.0-alpha] — 2026-06-03

### Added

- Public `conditions()` accessors on the `AllOf` and `AnyOf` condition groups.
- Public `precisionMode()` accessors on `Time` and `DateTime`.
- The `File` field accepts `Metadata` value objects as input, not just arrays.

### Changed

- **BREAKING —** serialized field keys and constraint names use camelCase.

### Fixed

- Composite fields ignored locally-keyed and object input.

### Removed

- The unused `source` key from `File` field metadata.

## [1.10.0-alpha] — 2026-06-03

### Fixed

- Composite validation reported `Passed` when a sub-field had failed.
- `Facade::validate()` dropped input supplied as an object exposing values through
  `__get()`.
- The `Date` interval constraint rejected valid multiples of the interval.
- `Number` mishandled decimals, and rule outcomes were not reset between runs.

## [1.9.0-alpha] — 2025-07-04

### Fixed

- Rolled back an incompatible version of `brick/math` and synchronised requirements.

> `v0.9.0-alpha` is a stray tag pointing at this same commit. Ignore it.

## [1.8.0-alpha] — 2025-07-01

### Added

- Rules can require fields and compare a field against other fields or literal values.
  Conditions and rules receive the schema during evaluation.

### Removed

- Obsolete classes left over from earlier designs.

## [1.7.0-alpha] — 2025-04-13

### Changed

- Input type conversion is handled separately from the field classes.

## [1.6.0-alpha] — 2025-04-12

### Removed

- Input sanitization, and constraint validation (temporarily).

### Fixed

- `null` handling during type validation, phone number country-code formatting, and
  min/max constraints.

## [1.5.0-alpha] — 2025-03-28

### Added

- `Facade::input()` supplies data to every field at once.
- An empty string can be treated as though no input was given.

### Fixed

- Field validation state was not updated after rules were applied.

## [1.4.0-alpha] — 2025-03-25

### Added

- `Money`, `DateTime`, `Date`, `EmailAddress` and `PhoneNumber` field types.
- Value sanitization, and string-to-number coercion.

### Fixed

- Serialized composite fields were missing their name; step increments were off by a day;
  sub-whole-number steps and time increments were rejected; names disallowed commas.

## [1.3.0-alpha] — 2025-01-12

### Added

- Fields can define what counts as a value being provided; an empty string counts as no
  input.

### Fixed

- `Enum` fields failed the one-of constraint even for valid values.

## [1.2.0-alpha] — 2024-12-26

### Fixed

- Several field types reported an incorrect type name; boolean comparison against HTTP
  on/off input; an undefined array key in the one-of attribute.

## [1.1.0-alpha] — 2024-12-05

### Added

- The rules engine: conditions, condition grouping and nesting, outcomes, and applying all
  rules to a schema at once.
- Composite field types, schema properties and scope-based navigation, aggregated
  validation results, and a basic HTML renderer (since moved to `meraki/schema-html`).

## [1.0.0-alpha] — 2024-09-21

Initial release.

[Unreleased]: https://github.com/merakiframework/schema/compare/v1.13.1-alpha...HEAD
[1.13.1-alpha]: https://github.com/merakiframework/schema/compare/v1.13.0-alpha...v1.13.1-alpha
[1.13.0-alpha]: https://github.com/merakiframework/schema/compare/v1.12.1-alpha...v1.13.0-alpha
[1.12.1-alpha]: https://github.com/merakiframework/schema/compare/v1.12.0-alpha...v1.12.1-alpha
[1.12.0-alpha]: https://github.com/merakiframework/schema/compare/v1.11.0-alpha...v1.12.0-alpha
[1.11.0-alpha]: https://github.com/merakiframework/schema/compare/v1.10.0-alpha...v1.11.0-alpha
[1.10.0-alpha]: https://github.com/merakiframework/schema/compare/v1.9.0-alpha...v1.10.0-alpha
[1.9.0-alpha]: https://github.com/merakiframework/schema/compare/v1.8.0-alpha...v1.9.0-alpha
[1.8.0-alpha]: https://github.com/merakiframework/schema/compare/v1.7.0-alpha...v1.8.0-alpha
[1.7.0-alpha]: https://github.com/merakiframework/schema/compare/v1.6.0-alpha...v1.7.0-alpha
[1.6.0-alpha]: https://github.com/merakiframework/schema/compare/v1.5.0-alpha...v1.6.0-alpha
[1.5.0-alpha]: https://github.com/merakiframework/schema/compare/v1.4.0-alpha...v1.5.0-alpha
[1.4.0-alpha]: https://github.com/merakiframework/schema/compare/v1.3.0-alpha...v1.4.0-alpha
[1.3.0-alpha]: https://github.com/merakiframework/schema/compare/v1.2.0-alpha...v1.3.0-alpha
[1.2.0-alpha]: https://github.com/merakiframework/schema/compare/v1.1.0-alpha...v1.2.0-alpha
[1.1.0-alpha]: https://github.com/merakiframework/schema/compare/v1.0.0-alpha...v1.1.0-alpha
[1.0.0-alpha]: https://github.com/merakiframework/schema/releases/tag/v1.0.0-alpha
