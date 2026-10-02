<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\File;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\HasParts;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\ParsedValue;

/**
 * One uploaded file, as the client described it.
 *
 * **`$type` is claimed, not verified.** It is whatever the client sent in the multipart
 * `Content-Type` header, which anyone can set to anything — a `.php` renamed to `.jpg`
 * arrives here as `image/jpeg`. Constraints built on it are a usability measure, not a
 * security one: they stop honest mistakes reaching your handler, and stop nothing else.
 * Verifying the real type means inspecting the bytes, which the caller must do, and which
 * this library deliberately does not attempt — a schema describes a shape, it does not open
 * files.
 *
 * `$size` is likewise the reported length rather than a measured one.
 *
 * This is the field's *internal* representation, and there is deliberately no `__toString()`. A
 * filename is the only part a person would recognise, but it is also attacker-controlled text, and
 * stringifying invites it into a page or a path unescaped. Read {@see self::$name} and handle it
 * knowingly.
 */
final readonly class Value implements ParsedValue, HasParts
{
	/**
	 * Every key an upload may arrive with, which is more than its parts.
	 *
	 * `name`, `type` and `size` are what this reads and what it reports as parts. The other
	 * three are PHP's: a `$_FILES` entry also carries a temporary path, an error code and —
	 * since 8.1 — the client's full path. A port handing one straight over should not have to
	 * strip them first, so they are accepted and ignored.
	 *
	 * Accepted-and-ignored is a different thing from a key nobody declared. That one is
	 * refused, because it is data somebody meant to send.
	 *
	 * @var list<string>
	 */
	private const UPLOAD_KEYS = ['name', 'type', 'size', 'tmp_name', 'error', 'full_path'];

	/** @var non-empty-string the client's filename */
	public string $name;

	/** @var non-empty-string the MIME type the client *claimed* */
	public string $type;

	/** @var non-negative-int the size the client *reported*, in bytes */
	public int $size;

	/**
	 * Takes the record a field takes, which for a file is the `$_FILES` triple a port hands
	 * over: a name, a claimed type and a reported size.
	 *
	 * @param object $file with a `name`, a `type` and a `size`
	 * @throws MalformedValue if any part is missing or unreadable
	 */
	public function __construct(object $file)
	{
		$parts = get_object_vars($file);
		$unknown = array_diff(array_keys($parts), self::UPLOAD_KEYS);

		// Named rather than ignored, the rule every record-shaped value here follows. A port
		// sending `filename` or `mime_type` would otherwise be told the upload has no name,
		// which names the symptom and hides the key that caused it.
		if ($unknown !== []) {
			throw MalformedValue::of(self::class, sprintf(
				'"%s" is not part of an upload. An upload has: "%s"',
				implode('", "', $unknown),
				implode('", "', self::UPLOAD_KEYS),
			));
		}

		foreach (['name', 'type', 'size'] as $key) {
			// array_key_exists rather than isset: a null here is a malformed upload, and saying
			// so is more useful than reporting the key as absent.
			if (!array_key_exists($key, $parts)) {
				throw MalformedValue::of(self::class, sprintf('it has no "%s"', $key));
			}
		}

		if (!is_string($parts['name']) || !is_string($parts['type'])) {
			throw MalformedValue::of(self::class, 'a name and a type are strings');
		}

		if ($parts['name'] === '' || $parts['type'] === '') {
			throw MalformedValue::of(self::class, 'a name and a type cannot be empty');
		}

		if (!is_int($parts['size']) && !(is_string($parts['size']) && ctype_digit($parts['size']))) {
			throw MalformedValue::of(self::class, 'a size is a whole number of bytes');
		}

		$this->name = $parts['name'];
		$this->type = $parts['type'];
		$this->size = (int) $parts['size'];
	}

	/**
	 * The readable way to describe one by hand — a rule's bound, a test.
	 *
	 * A convenience over the constructor rather than a second way in: it builds the same record
	 * an upload arrives as and hands it over.
	 *
	 * @throws MalformedValue if any part is unusable
	 */
	public static function of(string $name, string $type, int $size): self
	{
		return new self((object) ['name' => $name, 'type' => $type, 'size' => $size]);
	}

	/**
	 * Name, claimed type and size together.
	 *
	 * Deliberately not the bytes: this object never holds them, and two uploads of the same file
	 * are the same upload as far as a form is concerned. Deliberately not the temporary path
	 * either, which is different for every request by design and would make every file unique.
	 *
	 * The type is what the *client* claimed — see this class's own warning about that — so this is
	 * an identity for form purposes and not a statement that two files have the same content.
	 */
	public function equals(Equality $other): bool
	{
		return $other instanceof self
			&& $this->name === $other->name
			&& $this->type === $other->type
			&& $this->size === $other->size;
	}

	/**
	 * `type` is the MIME the *client* claimed, not a verified one — see this class's own
	 * warning. A rule reading it is reading an assertion by whoever uploaded the file.
	 *
	 * @return list<string>
	 */
	public static function partNames(): array
	{
		return ['name', 'type', 'size'];
	}

	/**
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
	 * with. {@see \Meraki\Schema\Field\Address\Value::canonicalPartValue()} is the one that
	 * has work to do.
	 */
	public function canonicalPartValue(string $part, mixed $expected): mixed
	{
		return $expected;
	}

	/** Every part here is one string. @see HasParts::listParts() */
	public static function listParts(): array
	{
		return [];
	}
}
