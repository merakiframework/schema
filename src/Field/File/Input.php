<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\File;

use Meraki\Schema\Exception\BrokenInputContract;
use Meraki\Schema\Field;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\Violation;

/**
 * An upload as a client described it, whether or not the description is whole: each part as read,
 * and what stops them making a {@see Value}.
 *
 * | Code | The part | Wrong when |
 * | --- | --- | --- |
 * | `nameRequired`, `typeRequired`, `sizeRequired` | name, type, size | it was not sent, or sent as `null` |
 * | `nameFormat`, `typeFormat` | name, type | it was sent and holds no text |
 * | `sizeFormat` | size | it was sent and is not a whole number of bytes |
 *
 * None of them reads the field's configuration: an upload described without its size is not
 * described on any field. How big a file may be and which types a field takes are the field's to
 * say once there is a file to say it about.
 *
 * All three parts are one upload's metadata rather than three boxes a form draws, so a renderer
 * will usually show every one of these beside the single file input. They still name their parts,
 * so a port can say *which* of the three its upload handling failed to supply.
 */
final readonly class Input implements Field\Input
{
	public ?string $name;

	public ?string $type;

	public ?int $size;

	/** An upload, once all three parts are there and readable. */
	public ?Value $value;

	/** @var list<Violation> */
	public array $violations;

	/** @var list<Part> */
	public array $missingParts;

	/**
	 * Takes the record a field takes: a name, a claimed type and a reported size.
	 *
	 * **These three and nothing else.** A `$_FILES` entry also carries `tmp_name`, `error` and —
	 * since PHP 8.1 — `full_path`, and none of them belong here. A temporary path is a detail of
	 * how one language's web SAPI receives an upload; an error code is that SAPI's verdict on
	 * whether the upload finished. A schema describes what a file *is*, and a port in another
	 * language has no `$_FILES` to hand over. Handing the whole entry raises, which is the point:
	 * it is a contract this library cannot express in a signature, so it is checked here instead.
	 *
	 * @param object{name?: string|null, type?: string|null, size?: int<0, max>|numeric-string|null} $file
	 * @throws BrokenInputContract if it carries a key no upload has
	 * @throws MalformedValue if it holds nothing at all
	 */
	public function __construct(object $file)
	{
		$parts = get_object_vars($file);
		$names = array_column(Part::cases(), 'value');
		$unknown = array_diff(array_keys($parts), $names);

		// Raised, not reported. A port handing over `$_FILES['cv']` whole, or sending
		// `filename`, is a port writing to the wrong contract rather than a submitter getting
		// something wrong — see {@see BrokenInputContract}.
		if ($unknown !== []) {
			throw BrokenInputContract::recordHasKeysItDoesNotAccept(Value::class, array_values($unknown), $names);
		}

		if (array_filter($parts, static fn(mixed $part): bool => $part !== null) === []) {
			throw MalformedValue::of(Value::class, 'it has no name, type or size');
		}

		$violations = [];
		$missing = [];

		[$name, $nameProblem] = self::textIn($parts['name'] ?? null, Check::NameRequired, Check::NameFormat);
		[$type, $typeProblem] = self::textIn($parts['type'] ?? null, Check::TypeRequired, Check::TypeFormat);
		[$size, $sizeProblem] = self::sizeIn($parts['size'] ?? null);

		foreach ([$nameProblem, $typeProblem, $sizeProblem] as $problem) {
			if ($problem === null) {
				continue;
			}

			$violations[] = $problem;

			if (in_array($problem->code, [Check::NameRequired, Check::TypeRequired, Check::SizeRequired], true)) {
				$missing[] = $problem->part;
			}
		}

		$this->name = $name;
		$this->type = $type;
		$this->size = $size;
		$this->violations = $violations;
		/** @var list<Part> $missing */
		$this->missingParts = $missing;
		$this->value = ($name !== null && $type !== null && $size !== null) ? new Value($name, $type, $size) : null;
	}

	/**
	 * The input a whole upload would have been read from, so a field handed its own value reads it
	 * the way it reads anything else.
	 */
	public static function of(Value $file): self
	{
		return new self((object) ['name' => $file->name, 'type' => $file->type, 'size' => $file->size]);
	}

	/**
	 * A name or a claimed type: text, kept exactly as the client sent it.
	 *
	 * @return array{?non-empty-string, ?Violation}
	 */
	private static function textIn(mixed $given, Check $required, Check $format): array
	{
		return match (true) {
			$given === null => [null, new Violation($required, true)],
			!is_string($given) || $given === '' => [null, new Violation($format)],
			default => [$given, null],
		};
	}

	/**
	 * The reported size in bytes. A string of digits is read too, because that is how a size
	 * survives a form post.
	 *
	 * @return array{?int<0, max>, ?Violation}
	 */
	private static function sizeIn(mixed $given): array
	{
		return match (true) {
			$given === null => [null, new Violation(Check::SizeRequired, true)],
			is_int($given) && $given >= 0 => [$given, null],
			is_string($given) && ctype_digit($given) => [(int) $given, null],
			default => [null, new Violation(Check::SizeFormat)],
		};
	}

	/**
	 * `type` is the MIME the *client* claimed, not a verified one — see {@see Value}'s own warning.
	 * A rule reading it is reading an assertion by whoever uploaded the file.
	 *
	 * @return array<string, mixed>
	 */
	public function parts(): array
	{
		return [
			'name' => $this->name,
			'type' => $this->type,
			'size' => $this->size,
		];
	}

	/**
	 * Nothing here is canonicalised, so a rule compares against exactly what it was written
	 * with. {@see \Meraki\Schema\Field\Address\Input::canonicalPartValue()} is the one that
	 * has work to do.
	 */
	public function canonicalPartValue(Field\Part $part, mixed $expected): mixed
	{
		return $expected;
	}
}
