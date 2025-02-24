<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;

/**
 * Represents an email address field.
 *
 * Validates the email address format according to the HTML specification,
 * which is a subset (and saner version) of the format specified in RFC 5322.
 *
 * @see https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address
 * @todo Implement the `verify` attribute, which will verify that the mailbox exists.
 * @todo Allow for a way to specify domains that are allowed (most likely use the `pattern` attribute).
 */
class EmailAddress extends Field
{
	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('email_address'), $name, ...$attributes);

		$this->registerConstraints([
			Attribute\Min::class => new Validator\CheckMinCharCount(),
			Attribute\Max::class => new Validator\CheckMaxCharCount(),
		]);
	}

	public function minLengthOf(int $minChars): self
	{
		$this->attributes = $this->attributes->set(new Attribute\Min($minChars));

		return $this;
	}

	public function maxLengthOf(int $maxChars): self
	{
		$this->attributes = $this->attributes->set(new Attribute\Max($maxChars));

		return $this;
	}

	public function allowMultiple(): self
	{
		$this->attributes = $this->attributes->set(new Attribute\Multiple());

		return $this;
	}

	public function disallowMultiple(): self
	{
		$this->attributes = $this->attributes->remove(new Attribute\Multiple());

		return $this;
	}

	public static function getSupportedAttributes(): array
	{
		return [
			Attribute\Min::class,
			Attribute\Max::class,
			// Attribute\Pattern::class,
			Attribute\Multiple::class,
			// Attribute\Verify::class,
		];
	}

	protected static function getTypeConstraintValidator(): Validator
	{
		return new class() implements Validator {
			// https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address
			private const REGEX = '^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$';

			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				if ($field->value === null || $field->value === '') {
					return false;
				}

				if ($field->attributes->findByName('multiple')?->hasValueOf(true)) {
					$emails = explode(',', $field->value);

					foreach ($emails as $email) {
						if (preg_match('/'.self::REGEX.'/', $email) !== 1) {
							return false;
						}
					}

					return true;
				}

				return preg_match('/'.self::REGEX.'/', $field->value) === 1;
			}
		};
	}
}
