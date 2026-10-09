<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Slot;

use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\FieldName;
use Stringable;

/**
 * The name a {@see Source} goes by.
 *
 * It is what a definition carries in place of the slots themselves. A port writing a schema down
 * writes this; one reading it back, or drawing a picker that fetches slots a week at a time, looks
 * the source up by it. So it has to survive being a key in a document and a segment in a URL —
 * which is exactly what a field name has to survive, so it is checked by the same rule rather than
 * a second copy of it.
 *
 * An object rather than a string on the interface, so a source cannot exist with an id no port
 * could use: it is refused where the source is built, not where somebody first tries to write it
 * down.
 */
final readonly class SourceId implements Stringable
{
	/**
	 * @throws InvalidConfiguration if it could not travel as a key
	 */
	public function __construct(
		private string $value,
	) {
		if (!FieldName::isUsable($this->value)) {
			throw InvalidConfiguration::sourceIdIsNotUsable($this->value);
		}
	}

	/**
	 * Exact, for the reason {@see FieldName::equals()} is: it is a key, and every lookup by it is a
	 * string comparison somewhere.
	 */
	public function equals(self $other): bool
	{
		return $this->value === $other->value;
	}

	public function __toString(): string
	{
		return $this->value;
	}
}
