<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Fixture;

use Meraki\Schema\AtomicField;
use Meraki\Schema\Field\Constraint;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\FieldName;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\ValueScope;

/**
 * A span of whole numbers — `from` and `to`, with an optional label — and the smallest field that
 * reads its parts before it judges them.
 *
 * A test fixture rather than a shipped field. It exists so the lifecycle's assembly step is tested
 * against something no other change can move, and so a field author has one complete example of an
 * {@see Span\Input} to copy.
 */
final readonly class Span extends AtomicField
{
	public ?int $maxWidth;

	public function __construct(public FieldName $name)
	{
		parent::__construct();

		$this->maxWidth = self::initially(null);
		$this->constraints = $this->defineConstraints();
	}

	public static function named(string $name): self
	{
		return new self(new FieldName($name));
	}

	public function maxWidthOf(?int $width): static
	{
		return $this->with(['maxWidth' => $width]);
	}

	public function when(): Matcher\Basic
	{
		return new Matcher\Basic(ValueScope::of($this->name));
	}

	protected function parse(mixed $value): Span\Input
	{
		if ($value instanceof Span\Value) {
			return Span\Input::of($value);
		}

		$record = self::recordIn($value);

		// Not a record, or a record with nothing in it: not a span somebody has half written, so
		// unreadable rather than incomplete.
		if ($record === null || $record === []) {
			throw MalformedValue::of(Span\Value::class, 'a span is a record with a from and a to');
		}

		return Span\Input::read($record);
	}

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint(Span\Check::MaxWidth, $this->isNarrowEnough(...), $this->maxWidth),
		);
	}

	/**
	 * Typed with the whole value, which is the point: there is no end to find missing.
	 */
	private function isNarrowEnough(Span\Value $span): ?bool
	{
		return $this->maxWidth === null ? null : $span->to - $span->from <= $this->maxWidth;
	}

	protected static function declaredParts(): array
	{
		return Span\Part::cases();
	}

	protected static function declaredChecks(): array
	{
		return Span\Check::cases();
	}
}
