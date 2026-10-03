<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Fixture\Span;

use Meraki\Schema\Field;
use Meraki\Schema\Field\Violation;

/**
 * A span's parts as read, and whether they make a span.
 *
 * Everything is worked out where it is built, and the value only when nothing stands in its way.
 * That is the whole contract of {@see Field\Input}, written the way a field package would write it.
 */
final readonly class Input implements Field\Input
{
	public ?Value $value;

	/**
	 * @param list<Violation> $violations
	 * @param list<Part> $missingParts
	 */
	private function __construct(
		public ?int $from,
		public ?int $to,
		public ?string $label,
		public array $violations,
		public array $missingParts,
	) {
		$this->value = ($violations === [] && $from !== null && $to !== null)
			? new Value($from, $to, $label)
			: null;
	}

	/**
	 * Every part read on its own, then the one thing the parts have to agree about.
	 *
	 * @param array<string, mixed> $record
	 */
	public static function read(array $record): self
	{
		$violations = [];
		$missing = [];

		[$from, $fromProblem] = self::end($record, Part::From, Check::FromRequired, Check::FromFormat);
		[$to, $toProblem] = self::end($record, Part::To, Check::ToRequired, Check::ToFormat);

		foreach ([$fromProblem, $toProblem] as $problem) {
			if ($problem !== null) {
				$violations[] = $problem;

				if ($problem->code === Check::FromRequired || $problem->code === Check::ToRequired) {
					$missing[] = $problem->part;
				}
			}
		}

		$label = $record['label'] ?? null;

		if ($label !== null && !is_string($label)) {
			$violations[] = new Violation(Check::LabelFormat);
			$label = null;
		}

		// Canonicalised, so a rule comparing labels compares what was stored.
		$label = $label === null ? null : strtolower($label);

		if ($from !== null && $to !== null && $from > $to) {
			$violations[] = new Violation(Check::InOrder);
		}

		/** @var list<Part> $missing */
		return new self($from, $to, $label, $violations, $missing);
	}

	public static function of(Value $span): self
	{
		return new self($span->from, $span->to, $span->label, [], []);
	}

	/**
	 * @param array<string, mixed> $record
	 * @return array{?int, ?Violation}
	 */
	private static function end(array $record, Part $part, Check $required, Check $format): array
	{
		$given = $record[$part->value] ?? null;

		return match (true) {
			$given === null => [null, new Violation($required, true)],
			!is_int($given) => [null, new Violation($format)],
			default => [$given, null],
		};
	}

	public function parts(): array
	{
		return ['from' => $this->from, 'to' => $this->to, 'label' => $this->label];
	}

	public function canonicalPartValue(Field\Part $part, mixed $expected): mixed
	{
		return ($part === Part::Label && is_string($expected)) ? strtolower($expected) : $expected;
	}
}
