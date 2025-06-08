<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * @extends Serialized<string|null>
 * @property-read int[] $versions
 * @internal
 */
interface SerializedUuid extends Serialized
{
}

/**
 * @extends AtomicField<string|null, SerializedUuid>
 */
final class Uuid extends AtomicField
{
	private const PATTERN = '/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}|00000000-0000-0000-0000-000000000000|ffffffff-ffff-ffff-ffff-ffffffffffff)$/i';

	public array $versions = []; // empty array means any version is allowed

	public const NULL_VERSION = 0; // 00000000-0000-0000-0000-000000000000
	public const ALL_BITS_SET_VERSION = -1; // ffffffff-ffff-ffff-ffff-ffffffffffff

	public function __construct(
		Property\Name $name,
	) {
		parent::__construct(new Property\Type('uuid', $this->validateType(...)), $name);
	}

	/**
	 * Restrict the UUID to a specific version.
	 *
	 * A version of "0" means a "null" UUID (00000000-0000-0000-0000-000000000000).
	 * A version of "-1" means the "all bits set" UUID (ffffffff-ffff-ffff-ffff-ffffffffffff).
	 */
	public function restrictToVersion(int ...$versions): self
	{
		// empty array means "any version is allowed"
		if (count($versions) === 0) {
			$this->versions = [];

			return $this;
		}

		foreach ($versions as $v) {
			if ($v < -1 || $v > 8) {
				throw new InvalidArgumentException('Version must be between -1, 0, or 1 to 8.');
			}

			if (!in_array($v, $this->versions, true)) {
				$this->versions[] = $v;
			}
		}

		return $this;
	}

	protected function cast(mixed $value): string
	{
		return $value;
	}

	protected function getConstraints(): array
	{
		return [
			'version' => $this->validateVersions(...)
		];
	}

	protected function validateType(mixed $value): bool
	{
		return is_string($value) && preg_match(self::PATTERN, $value) === 1;
	}

	private function validateVersions(mixed $value): bool
	{
		// No restrictions, any version is allowed
		if (count($this->versions) === 0) {
			return true;
		}

		// Null UUID is allowed
		if (in_array(self::NULL_VERSION, $this->versions, true) && $value === '00000000-0000-0000-0000-000000000000') {
			return true;
		}

		// "All bits set" UUID is allowed
		if (in_array(self::ALL_BITS_SET_VERSION, $this->versions, true) && $value === 'ffffffff-ffff-ffff-ffff-ffffffffffff') {
			return true;
		}

		// 1 - 8
		return in_array(hexdec($value[14]), $this->versions, true);
	}

	public function serialize(): SerializedUuid
	{
		return new class(
			type: $this->type->value,
			name: $this->name->value,
			optional: $this->optional,
			value: $this->defaultValue->unwrap(),
			versions: $this->versions,
		) implements SerializedUuid {
			/**
			 * @param int[] $versions
			 */
			public function __construct(
				public readonly string $type,
				public readonly string $name,
				public readonly bool $optional,
				public ?string $value,
				public readonly array $versions,
			) {}

			public function getConstraints(): array
			{
				return ['versions'];
			}
			public function children(): array
			{
				return [];
			}
		};
	}

	/**
	 * @param SerializedUuid $serialized
	 */
	public static function deserialize(Serialized $serialized): static
	{
		$uuid = new self(new Property\Name($serialized->name));
		$uuid->optional = $serialized->optional;

		return $uuid->restrictToVersion(...$serialized->versions)
			->prefill($serialized->value);
	}
}
