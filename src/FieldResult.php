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
 * This exists so {@see SchemaValidationResult::get()} can find a field's outcome by name without
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
}
