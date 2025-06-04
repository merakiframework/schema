<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Brick\DateTime\TimeZone;
use Brick\DateTime\ZonedDateTime;
use Meraki\Schema\Field;
use Meraki\Schema\Field\Composite as CompositeField;
use Meraki\Schema\Property;
use DateTimeImmutable;

/**
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
			new Property\Type('credit_card', $this->validateType(...)),
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
		return [];
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
