<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Fixture\Span;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\ParsedValue;

/**
 * A whole span: both ends, in order. Neither end can be null, so nothing judging one asks.
 */
final readonly class Value implements ParsedValue
{
	public function __construct(
		public int $from,
		public int $to,
		public ?string $label = null,
	) {
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self
			&& $other->from === $this->from
			&& $other->to === $this->to
			&& $other->label === $this->label;
	}
}
