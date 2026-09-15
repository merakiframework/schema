<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\File;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\HasParts;
use Meraki\Schema\Field\ParsedValue;
use InvalidArgumentException;

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
	 * @param non-empty-string $name the client's filename
	 * @param non-empty-string $type the MIME type the client *claimed*
	 * @param non-negative-int $size the size the client *reported*, in bytes
	 */
	public function __construct(
		public string $name,
		public string $type,
		public int $size,
	) {
		if ($name === '') {
			throw new InvalidArgumentException('A file must have a name.');
		}

		if ($type === '') {
			throw new InvalidArgumentException('A file must have a type.');
		}

		if ($size < 0) {
			throw new InvalidArgumentException('A file size cannot be negative.');
		}
	}

	/**
	 * Reads the `$_FILES`-shaped array a form upload arrives as.
	 *
	 * @param array<string, mixed> $file
	 * @throws InvalidArgumentException if it is not one
	 */
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

	public static function fromInput(array $file): self
	{
		foreach (['name', 'type', 'size'] as $key) {
			// array_key_exists rather than isset: a null here is a malformed upload, and
			// saying so is more useful than reporting the key as absent.
			if (!array_key_exists($key, $file)) {
				throw new InvalidArgumentException(sprintf('A file is missing its "%s".', $key));
			}
		}

		if (!is_string($file['name']) || !is_string($file['type'])) {
			throw new InvalidArgumentException('A file\'s name and type must be strings.');
		}

		if (!is_int($file['size']) && !(is_string($file['size']) && ctype_digit($file['size']))) {
			throw new InvalidArgumentException('A file\'s size must be a whole number of bytes.');
		}

		return new self($file['name'], $file['type'], (int) $file['size']);
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
}
