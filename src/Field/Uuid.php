<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Field\Uuid\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;
use InvalidArgumentException;

/**
 * A UUID, in the canonical 8-4-4-4-12 hyphenated form.
 *
 * Accepts the nil and max UUIDs alongside versions 1 to 8, since both are well-formed and
 * carry meaning — but neither has a version nibble, so they are allowed by name rather than
 * by number. See {@see self::NIL} and {@see self::MAX}.
 *
 * @extends AtomicField<string|null>
 */
final readonly class Uuid extends AtomicField
{
	private const PATTERN = '/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}|00000000-0000-0000-0000-000000000000|ffffffff-ffff-ffff-ffff-ffffffffffff)$/i';

	/** The nil UUID, `00000000-0000-0000-0000-000000000000`. Has no version nibble. */
	public const NIL = 0;

	/** The max UUID, `ffffffff-ffff-ffff-ffff-ffffffffffff`. Has no version nibble. */
	public const MAX = -1;

	private const NIL_UUID = '00000000-0000-0000-0000-000000000000';
	private const MAX_UUID = 'ffffffff-ffff-ffff-ffff-ffffffffffff';

	/** @var list<int> Empty means any version is acceptable. */
	public array $allowedVersions;

	public function __construct(
		public FieldName $name,
	) {
		parent::__construct();

		$this->allowedVersions = [];

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * Restricts the field to the given versions, 1 to 8, plus {@see self::NIL} and
	 * {@see self::MAX} for the two that have no version of their own.
	 *
	 * Accumulates, so repeated calls compose. Lifting the restriction is
	 * {@see self::clearAllowedVersions()}.
	 *
	 * @throws InvalidArgumentException if a version is outside -1, 0, or 1 to 8
	 */
	public function allowVersions(int $version, int ...$versions): static
	{
		$allowed = $this->allowedVersions;

		foreach ([$version, ...$versions] as $v) {
			if ($v < self::MAX || $v > 8) {
				throw new InvalidArgumentException('Version must be -1, 0, or 1 to 8.');
			}

			if (!in_array($v, $allowed, true)) {
				$allowed[] = $v;
			}
		}

		return $this->with(['allowedVersions' => $allowed]);
	}

	/**
	 * What a rule may ask about this field: an identifier is matched, never ranked — two UUIDs have no meaningful order.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Text
	{
		return new Matcher\Text(ValueScope::of($this->name));
	}

	/**
	 * Accepts any version again.
	 */
	public function clearAllowedVersions(): static
	{
		return $this->with(['allowedVersions' => []]);
	}

	protected function parse(mixed $value): ?Value
	{
		return is_string($value) && preg_match(self::PATTERN, $value) === 1 ? new Value($value) : null;
	}

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('allowedVersions', $this->isAnAllowedVersion(...), $this->allowedVersions),
		);
	}

	private function isAnAllowedVersion(Value $parsed): ?bool
	{
		$value = $parsed->uuid;

		// No list means nothing was asked, so nothing was checked.
		if ($this->allowedVersions === []) {
			return null;
		}

		$value = strtolower($value);

		// Neither of these carries a version nibble, so each is allowed by name or not at all.
		if ($value === self::NIL_UUID) {
			return in_array(self::NIL, $this->allowedVersions, true);
		}

		if ($value === self::MAX_UUID) {
			return in_array(self::MAX, $this->allowedVersions, true);
		}

		return in_array((int) hexdec($value[14]), $this->allowedVersions, true);
	}
}
