<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Password;

use Meraki\Schema\Field\Serialized;

/**
 * @implements Serialized<string|null>
 * @internal
 */
final class SerializedPassword implements Serialized
{
	public function __construct(
		public readonly string $type,
		public readonly string $name,
		public readonly bool $optional,
		/** @var array{0:int|null, 1:int|null}|array{} */
		public readonly array $length,
		/** @var array{0:int|null, 1:int|null}|array{} */
		public readonly array $lowercase,
		/** @var array{0:int|null, 1:int|null}|array{} */
		public readonly array $uppercase,
		/** @var array{0:int|null, 1:int|null}|array{} */
		public readonly array $digits,
		/** @var array{0:int|null, 1:int|null}|array{} */
		public readonly array $symbols,
		/** @var string[] */
		public readonly array $anyOf,
		public readonly ?string $value,
	) {
	}

	public function getConstraints(): array
	{
		return ['length', 'lowercase', 'uppercase', 'digits', 'symbols', 'any_of'];
	}
}
