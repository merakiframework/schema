<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

/**
 * Why a field had no usable value — and the code the violation saying so is reported under, which
 * is why it is a {@see Check}: `missing` and `unreadable` are the keys a language pack words them
 * by, under `shape.`.
 *
 * Three different problems. Each makes the shape fail and stops every constraint from running, but
 * a form cannot say the same sentence about them. One wants "this is required", another "this is
 * not a valid duration", and the third, {@see self::Incomplete}, wants a sentence for each part
 * that is wrong.
 *
 * Telling them apart used to mean reading `$given === null` at the call site — the
 * disentangling-by-hand that splitting `type` off from the constraints was supposed to end. It
 * was also not quite right, because a submitter can send a literal null, which is a value that
 * happens to be nothing rather than an absence.
 */
enum ShapeProblem: string implements Check
{
	/**
	 * Nothing was submitted, no default stood in, and the field required one.
	 *
	 * Note what this is not: a field that is *optional* and got nothing is skipped, not missing —
	 * there is no problem to name.
	 */
	case Missing = 'missing';

	/**
	 * Something was submitted and could not be read as this field's kind of thing.
	 *
	 * Including a submitted `null`, which is why this is not derivable from the given value: the
	 * field was handed something, and that something was unusable.
	 */
	case Unreadable = 'unreadable';

	/**
	 * Parts arrived and do not make a value: an essential part is missing, a part cannot be read,
	 * or the parts disagree with each other.
	 *
	 * The one problem that is never a violation's code. The parts' own violations explain it, and
	 * each one names its box: "Enter the amount" says more than "That is not a valid amount", and
	 * a sentence about the whole value would repeat it less usefully. So no language pack has a
	 * `shape.incomplete` to write, and asking `wasIncomplete()` is how a renderer tells this apart.
	 */
	case Incomplete = 'incomplete';

	/**
	 * Always about the field as a whole. A problem with one part has that part's own code.
	 */
	public function part(): null
	{
		return null;
	}
}
