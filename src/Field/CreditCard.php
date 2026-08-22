<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Composite as CompositeField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use Brick\DateTime\TimeZone;
use Brick\DateTime\ZonedDateTime;
use DateTimeImmutable;

/**
 * @extends CompositeField<array|null>
 * @property-read Field\Name $holder
 * @property-read Field\Text $number
 * @property-read Field\Date $expiry
 * @property-read Field\Text $securityCode
 */
final class CreditCard extends CompositeField
{
	public function __construct(
		Property\Name $name,
	) {
		parent::__construct(
			$name,
			$this->createHolderField(),
			$this->createNumberField(),
			$this->createExpiryField(),
			$this->createSecurityCodeField(),
		);
	}

	protected function cast(mixed $value): mixed
	{
		return $value;
	}

	protected function getConstraints(): array
	{
		$number = (string) (new Property\Name('number'))->prefixWith($this->name);

		return [
			$number . '.checksum' => $this->validateChecksum(...),
		];
	}

	/**
	 * The Luhn check digit (ISO/IEC 7812-1). Every card number carries one, so a number
	 * that fails it is not a card number — no processor will accept it, and catching that
	 * here saves a round trip.
	 *
	 * Returns null when the number is not yet in a state the checksum can speak to; the
	 * digit and length rules on the sub-field report that instead.
	 *
	 * @param array<string, mixed> $value
	 */
	private function validateChecksum(array $value): ?bool
	{
		$number = $value[(string) (new Property\Name('number'))->prefixWith($this->name)] ?? null;

		if (!is_string($number) || preg_match('/^\d{13,19}$/', $number) !== 1) {
			return null;
		}

		$sum = 0;
		$double = false;

		// Right to left: double every second digit, and cast a resulting 10-18 back down
		// by subtracting 9 (equivalent to summing its two digits).
		for ($i = strlen($number) - 1; $i >= 0; $i--) {
			$digit = (int) $number[$i];

			if ($double) {
				$digit *= 2;

				if ($digit > 9) {
					$digit -= 9;
				}
			}

			$sum += $digit;
			$double = !$double;
		}

		return $sum % 10 === 0;
	}

	private function createHolderField(): Field\Name
	{
		return new Field\Name(new Property\Name('holder'));
	}

	private function createNumberField(): Field\Text
	{
		return (new Field\Text(new Property\Name('number')))
			->minLengthOf(13)
			->maxLengthOf(19)
			->matches('/^\d+$/');
	}

	private function createExpiryField(): Field\Date
	{
		$now = ZonedDateTime::now(TimeZone::utc());

		return (new Field\Date(new Property\Name('expiry')))
			->from((string)$now->getDate());
	}

	private function createSecurityCodeField(): Field\Text
	{
		return (new Field\Text(new Property\Name('security_code')))
			->minLengthOf(3)
			->maxLengthOf(4)
			->matches('/^\d+$/');
	}

	protected function process($value): Property\Value
	{
		$value = parent::process($value);

		// Unusable input is passed through untouched, so there are no sub-field values to
		// tidy up. validate() reports it as a shape failure on the composite.
		if (!is_array($value->unwrap())) {
			return $value;
		}

		$value = $this->addDayToExpiry($value);
		$value = $this->removeWhitespaceFromNumber($value);

		return $value;
	}

	private function addDayToExpiry(Property\Value $value): Property\Value
	{
		$name = (string)(new Property\Name('expiry'))->prefixWith($this->name);
		$value = $value->unwrap();
		$expiry = $value[$name];

		// Add the last day of the month to the expiry date
		if (is_string($expiry) && preg_match('/^\d{4}-\d{2}$/', $expiry)) {
			$expiryDate = DateTimeImmutable::createFromFormat('Y-m', $expiry);

			if ($expiryDate !== false) {
				$expiryDate = $expiryDate->modify('last day of this month');
				$value[$name] = $expiryDate->format('Y-m-d');
			}
		}

		return new Property\Value($value);
	}

	private function removeWhitespaceFromNumber(Property\Value $value): Property\Value
	{
		$name = (string) (new Property\Name('number'))->prefixWith($this->name);
		$value = $value->unwrap();
		$number = $value[$name];

		if (is_string($number)) {
			$value[$name] = preg_replace('/\s+/', '', $number);
		}

		return new Property\Value($value);
	}
}
