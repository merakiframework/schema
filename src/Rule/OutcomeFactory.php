<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule\Outcome;

/**
 * @phpstan-import-type SerializedOutcome from Outcome
 * @template T of SerializedOutcome
 */
final class OutcomeFactory
{
	public function __construct(
		/** @var array<string, class-string<Outcome>> */
		private array $outcomeMap = [
			'make_optional' => Outcome\MakeOptional::class,
			'require' => Outcome\_Require::class,
		],
	) {}

	/**
	 * @param T $data
	 */
	public function deserialize(object $data): Outcome
	{
		if (!isset($this->outcomeMap[$data->action])) {
			throw new \InvalidArgumentException('Unknown outcome action: ' . $data->action);
		}

		$class = $this->outcomeMap[$data->action];

		return $class::deserialize($data);
	}
}
