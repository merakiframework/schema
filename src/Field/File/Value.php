<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\File;

use Meraki\Schema\Comparison\Equality;
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
final readonly class Value implements ParsedValue
{
	/**
	 * Built by {@see Input} from what a client sent, or by {@see self::of()} by hand. Every part is
	 * there: an upload described without one of them is an input that has not made a value.
	 *
	 * @param non-empty-string $name the client's filename
	 * @param non-empty-string $type the MIME type the client *claimed*
	 * @param int<0, max> $size the size the client *reported*, in bytes
	 * @throws MalformedValue if the name or type is empty, or the size is negative
	 */
	public function __construct(
		public string $name,
		public string $type,
		public int $size,
	) {
		if ($name === '' || $type === '') {
			throw MalformedValue::of(self::class, 'a name and a type cannot be empty');
		}

		if ($size < 0) {
			throw MalformedValue::of(self::class, 'a size is a whole number of bytes');
		}
	}

	/**
	 * The readable way to describe one by hand — a rule's bound, a test.
	 *
	 * @throws MalformedValue if any part is unusable
	 */
	public static function of(string $name, string $type, int $size): self
	{
		return new self($name, $type, $size);
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
}
