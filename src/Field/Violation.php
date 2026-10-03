<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

/**
 * One thing wrong with what was submitted for one field, said once, against the part it concerns.
 *
 * Every failure becomes one of these, whichever step found it — nothing arriving for a required
 * field, a value that could not be read, a check the value failed. So a form marks a box the same
 * way whatever caught the problem, and a language pack words it under one key whatever stage it
 * came from: the stage is a fact about the result, not part of the code.
 *
 * A verdict and a violation are different things. A {@see ConstraintValidationResult} is what one
 * constraint said, passed and skipped included. A violation is only ever a failure, carrying what
 * somebody needs to be told: what was wrong, where, the limit that applied, and the sentence a
 * language pack had for it.
 *
 * Deliberately not here: what was submitted. It is already on the result as `$given`, unchanged,
 * and copying it onto every violation would put card numbers and passwords into the objects most
 * likely to end up in a log.
 */
final readonly class Violation
{
	/** The part this is about, or null when it concerns the value as a whole. Read from the code. */
	public ?Part $part;

	/** The code as the wire carries it — the key a language pack writes a message under. */
	public string $name;

	/**
	 * @param Check $code what was wrong, as a case of the field's own enum — or of
	 *        {@see ShapeProblem} when nothing about the value could be judged at all
	 * @param string|int|float|bool|list<string>|null $bound the limit that applied, ready for a
	 *        message; null when there is nothing to interpolate
	 * @param string|null $message what a language pack says about it, or null when no pack was
	 *        asked or none had wording
	 */
	public function __construct(
		public Check $code,
		public string|int|float|bool|array|null $bound = null,
		public ?string $message = null,
	) {
		$this->part = $code->part();
		$this->name = (string) $code->value;
	}

	/**
	 * The failure a constraint's verdict describes.
	 */
	public static function from(ConstraintValidationResult $failed): self
	{
		return new self($failed->code, $failed->bound);
	}

	/**
	 * The same violation, worded.
	 *
	 * The language is applied after the verdict, never before — nothing about wording may change
	 * what failed — so a violation is built without a message and given one here.
	 */
	public function withMessage(?string $message): self
	{
		return new self($this->code, $this->bound, $message);
	}
}
