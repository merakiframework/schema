<?php
declare(strict_types=1);

namespace Meraki\Schema;

/**
 * One field's outcome for one request, whatever shape that outcome takes.
 *
 * {@see ResolvedField} is the usual one — a value and a verdict per constraint. A field whose
 * value has structure reports something richer: {@see Field\Collection\Result} carries the
 * collection's own verdicts *and* a result per item, so a failure can say which item failed.
 *
 * This exists so {@see SchemaValidationResult::forField()} can find a field's outcome by name without
 * knowing which shape it is. The version before it tested for each concrete result class in turn,
 * which meant every new structured type had to be added to that list before it could be looked up.
 */
interface FieldResult extends ValidationResult
{
	/**
	 * The field this is the outcome for — the *effective* definition, including any change a
	 * matching rule made.
	 */
	public Field $field { get; }

	/**
	 * Whether the value could be read as this field's kind of thing at all.
	 *
	 * Declared here because it is the one verdict every shape of result already carries and the
	 * only one that means the same thing across all of them: a collection's shape is "was that a
	 * list", an atomic field's is "was that a duration", and both are the gate deciding whether
	 * the constraints ran. Everything else differs — a collection reports per item, a password
	 * reports measured entropy — and belongs to the concrete result.
	 *
	 * It was already on both implementations. Declaring it lets a caller ask the question without
	 * testing for each concrete class in turn, which is the thing this interface exists to stop.
	 */
	public Field\ShapeValidationResult $shape { get; }

	/**
	 * This field's constraint verdicts, without the shape mixed in.
	 *
	 * The pair is deliberate: `$shape` and `$constraints` each hold one kind of answer and each
	 * offer the full aggregate API, while the shorthands below cover the readings almost everyone
	 * wants. Simple where it is simple; the richer object is one property away when it is not.
	 */
	public Field\ConstraintResults $constraints { get; }

	/** Shorthand for `$shape->wasUnreadable()`. */
	public function wasUnreadable(): bool;

	/** Shorthand for `$shape->wasMissing()`. */
	public function wasMissing(): bool;

	/** Shorthand for `$constraints->getFailed()`. */
	public function getFailedConstraints(): Field\ConstraintResults;

	/**
	 * Everything wrong with this field, each failure with its code, its part, its bound and — when
	 * a language pack had wording — its sentence.
	 *
	 * The whole of the reporting surface. Everything a language pack produces arrives here and
	 * nowhere else, which is what lets wording be optional without any of the rest of the library
	 * knowing it exists: with no provider every violation is still here, with a code and no
	 * sentence, and every other property on this interface answers exactly as it would anyway.
	 */
	public Field\Violations $violations { get; }

	/**
	 * What is wrong with one part of a structured value — shorthand for
	 * `$violations->forPart($part)`.
	 */
	public function forPart(Field\Part $part): Field\Violations;

	/**
	 * The same outcome with its violations worded in one language.
	 *
	 * Plumbing rather than something a consumer calls: {@see Definition::validate()} resolves the
	 * request's language once and hands the translator to each result. It is on the interface
	 * because a result shape somebody else wrote has to be reachable the same way — a
	 * {@see Field\Collection\Result} passes it down to every row, and one that quietly did not
	 * would produce a form where the outer errors were translated and the inner ones were blank.
	 */
	public function withMessagesFrom(?Message\Translator $translator): static;
}
