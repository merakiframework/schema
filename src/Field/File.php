<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Field\File\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;

/**
 * One uploaded file.
 *
 * Exactly one: a field holds a single value, and several files is a collection of file
 * fields rather than a field that is itself plural. That is why there is no count here —
 * `minCountOf()` belongs to {@see Collection}, which is the thing that knows about many.
 *
 *     $schema->createCollectionField(
 *         'attachments',
 *         $schema->createFileField('file')->allowDocuments(),
 *     )->minCountOf(1);
 *
 * Input is a `$_FILES`-shaped array or a {@see Value}; either way it resolves to a `Value`.
 * Note what that value can and cannot tell you — see the warning on `Value` about the
 * claimed MIME type.
 *
 * @psalm-type UploadedFile = array{name: string, type: string, size: int}
 * @extends AtomicField<UploadedFile|Value|null>
 */
final readonly class File extends AtomicField
{
	public int $minSize;
	public ?int $maxSize;
	public array $allowedTypes;
	public array $disallowedTypes;

	public function __construct(
		public FieldName $name,
	) {
		parent::__construct();

		$this->minSize = 0;
		$this->maxSize = null;
		$this->allowedTypes = [];
		$this->disallowedTypes = [];

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * @param non-negative-int $bytes
	 * @throws InvalidConfiguration if negative, or above the maximum
	 */
	public function minSizeOf(int $bytes): static
	{
		if ($bytes < 0) {
			throw InvalidConfiguration::minimumIsNegative('file size');
		}

		if ($this->maxSize !== null && $bytes > $this->maxSize) {
			throw InvalidConfiguration::minimumExceedsMaximum('file size');
		}

		return $this->with(['minSize' => $bytes]);
	}

	/**
	 * @param non-negative-int|null $bytes `null` removes the ceiling
	 * @throws InvalidConfiguration if negative, or below the minimum
	 */
	public function maxSizeOf(?int $bytes): static
	{
		if ($bytes === null) {
			return $this->with(['maxSize' => null]);
		}

		if ($bytes < 0) {
			throw InvalidConfiguration::maximumIsNegative('file size');
		}

		if ($bytes < $this->minSize) {
			throw InvalidConfiguration::maximumIsBelowMinimum('file size');
		}

		return $this->with(['maxSize' => $bytes]);
	}

	/**
	 * Adds to the accepted types, so the presets below compose rather than replace. Lifting
	 * the restriction entirely is {@see self::clearAllowedTypes()}.
	 *
	 * @param non-empty-string $type
	 * @param non-empty-string ...$types
	 * @throws InvalidConfiguration if any type is empty
	 */
	public function allowTypes(string $type, string ...$types): static
	{
		return $this->with(['allowedTypes' => $this->merge($this->allowedTypes, [$type, ...$types])]);
	}

	/**
	 * Accepts every type again, undoing {@see self::allowTypes()} and the presets built on it.
	 *
	 * Leaves the refused types alone: clearing both from one call would quietly undo a
	 * {@see self::disallowScripts()} that was never mentioned.
	 */
	public function clearAllowedTypes(): static
	{
		return $this->with(['allowedTypes' => []]);
	}

	/**
	 * Adds to the refused types. Accumulates, and clears through
	 * {@see self::clearDisallowedTypes()}, exactly as {@see self::allowTypes()} does.
	 *
	 * @param non-empty-string $type
	 * @param non-empty-string ...$types
	 * @throws InvalidConfiguration if any type is empty
	 */
	public function disallowTypes(string $type, string ...$types): static
	{
		return $this->with(['disallowedTypes' => $this->merge($this->disallowedTypes, [$type, ...$types])]);
	}

	/**
	 * Refuses nothing outright again, undoing {@see self::disallowTypes()} and
	 * {@see self::disallowScripts()}. Leaves the accepted types alone.
	 */
	public function clearDisallowedTypes(): static
	{
		return $this->with(['disallowedTypes' => []]);
	}

	public function allowImages(): static
	{
		return $this->allowTypes(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/svg+xml',
		);
	}

	public function allowVideos(): static
	{
		return $this->allowTypes(
			'video/mp4',
			'video/webm',
			'video/ogg',
			'video/quicktime',
		);
	}

	public function allowDocuments(): static
	{
		return $this->allowTypes(
			'application/pdf',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.ms-powerpoint',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'text/plain',
			'text/csv',
			'application/rtf',
		);
	}

	/**
	 * Refuses the types most often used to smuggle executable content past an upload form.
	 *
	 * A convenience against mistakes, not a defence: the type is whatever the client claimed
	 * (see {@see Value}), so anything deliberate simply claims a different one.
	 */
	public function disallowScripts(): static
	{
		return $this->disallowTypes(
			'application/x-javascript',
			'application/javascript',
			'text/javascript',
			'application/x-php',
			'text/html',
			'application/x-sh',
		);
	}

	/**
	 * Turns what was submitted into a {@see Value}.
	 *
	 * Input that cannot be one is passed through untouched rather than rejected here, so the
	 * shape check reports it with everything else — throwing from here would raise on a
	 * *definition* being built, not on the request.
	 *
	 * @param UploadedFile|Value|null $value
	 */
	/**
	 * What a rule may ask about this field: a file is described, not ranked or read as text.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Basic
	{
		return new Matcher\Basic(ValueScope::of($this->name));
	}

	/**
	 * @param array<string, mixed>|Value $value
	 */
	protected function parse(mixed $value): Value
	{
		if ($value instanceof Value) {
			return $value;
		}

		// An object is a record; an array is a list. A file's description has named parts, so
		// it arrives as the former — see Definition::recordIn().
		if (!is_object($value)) {
			throw MalformedValue::of(Value::class, 'a file is submitted as a record with a name, a type and a size');
		}

		return new Value($value);
	}



	protected function defineConstraints(): Constraint\Set
	{
		// Bounds first, then the allow/disallow pair — the order every other field reports in, and
		// the order a failure reads best in. EmailAddress is the exact parallel.
		return new Constraint\Set(
			new Constraint('minSize', $this->meetsMinSize(...), $this->minSize),
			new Constraint('maxSize', $this->meetsMaxSize(...), $this->maxSize),
			new Constraint('allowedTypes', $this->isAnAllowedType(...), $this->allowedTypes),
			new Constraint('disallowedTypes', $this->isNotADisallowedType(...), $this->disallowedTypes),
		);
	}

	/**
	 * @param list<non-empty-string> $existing
	 * @param list<string> $additional
	 * @return list<non-empty-string>
	 */
	private function merge(array $existing, array $additional): array
	{
		foreach ($additional as $type) {
			if ($type === '') {
				throw InvalidConfiguration::listMemberIsEmpty('media type');
			}
		}

		return array_values(array_unique([...$existing, ...$additional]));
	}

	private function isAnAllowedType(Value $file): ?bool
	{
		// No list means nothing was asked, so nothing was checked.
		return $this->allowedTypes === [] ? null : in_array($file->type, $this->allowedTypes, true);
	}

	private function isNotADisallowedType(Value $file): ?bool
	{
		return $this->disallowedTypes === [] ? null : !in_array($file->type, $this->disallowedTypes, true);
	}

	/** Unlike the others this never skips: zero is a bound that every file meets. */
	private function meetsMinSize(Value $file): bool
	{
		return $file->size >= $this->minSize;
	}

	private function meetsMaxSize(Value $file): ?bool
	{
		return $this->maxSize === null ? null : $file->size <= $this->maxSize;
	}
}
