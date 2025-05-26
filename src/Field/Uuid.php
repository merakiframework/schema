<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;
use InvalidArgumentException;

final class Uuid extends AtomicField
{
	private const PATTERN = '/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}|00000000-0000-0000-0000-000000000000|ffffffff-ffff-ffff-ffff-ffffffffffff)$/i';

	public array $versions = []; // empty array means any version is allowed

	public function __construct(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	) {
		parent::__construct(new Property\Type('uuid', $this->validateType(...)), $name, $value, $defaultValue, $optional);
	}

	/**
	 * Restrict the UUID to a specific version.
	 *
	 * A version of "0" means a "null" UUID (00000000-0000-0000-0000-000000000000).
	 * A version of "-1" means the "all bits set" UUID (ffffffff-ffff-ffff-ffff-ffffffffffff).
	 */
	public function restrictToVersion(int $version): self
	{
		if ($version < -1 || $version > 8) {
			throw new InvalidArgumentException('Version must be between -1, 0, or 1 to 8.');
		}

		$this->versions[] = $version;

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
		if (in_array(0, $this->versions, true) && $value === '00000000-0000-0000-0000-000000000000') {
			return true;
		}

		// "All bits set" UUID is allowed
		if (in_array(-1, $this->versions, true) && $value === 'ffffffff-ffff-ffff-ffff-ffffffffffff') {
			return true;
		}

		// 1 - 8
		return in_array(hexdec($value[14]), $this->versions, true);
	}
}
