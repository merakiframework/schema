<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

/**
 * Why a field had no usable value.
 *
 * Two different problems that used to be one verdict. Both make the shape fail, and both stop
 * every constraint from running, but they are not the same thing and a form cannot say the same
 * sentence about them: one wants "this is required", the other "this is not a valid duration".
 *
 * Telling them apart used to mean reading `$given === null` at the call site — the
 * disentangling-by-hand that splitting `type` off from the constraints was supposed to end. It
 * was also not quite right, because a submitter can send a literal null, which is a value that
 * happens to be nothing rather than an absence.
 */
enum ShapeProblem
{
	/**
	 * Nothing was submitted, no default stood in, and the field required one.
	 *
	 * Note what this is not: a field that is *optional* and got nothing is skipped, not missing —
	 * there is no problem to name.
	 */
	case Missing;

	/**
	 * Something was submitted and could not be read as this field's kind of thing.
	 *
	 * Including a submitted `null`, which is why this is not derivable from the given value: the
	 * field was handed something, and that something was unusable.
	 */
	case Unreadable;
}
