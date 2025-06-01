<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\EmailAddress;

enum Format: string
{
	/**
	 * Must have an "@" somewhere in the string, which cannot be
	 * the first or last character, and must not have more than
	 * one "@".
	 */
	case Basic = 'basic';

	/**
	 * Must match the HTML5 pattern for email addresses.
	 */
	case Html = 'html';

	/**
	 * Must match the RFC 5322 pattern for email addresses.
	 */
	case Rfc = 'rfc';

	/**
	 * Must match "routable" email addresses according to RFC 5321 (i.e.,
	 * those that can be used to send email over SMTP)
	 */
	case Smtp = 'smtp';

	/* https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address **/
	private const HTML5_PATTERN = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/';

	/**
	 * Must have an "@" somewhere in the string, which cannot be
	 * the first or last character, and "@" must not be present
	 * more than once.
	 */
	private const BASIC_PATTERN = '/^[^@]+@[^@]+$/';

	public function getAllowableMinLengthTotal(): int
	{
		return 3;
	}

	public function getAllowableMaxLengthTotal(): int
	{
		return 254;
	}

	public function getAllowableMaxLengthInLocalPart(): int
	{
		return 64;
	}

	public function getAllowableMaxLengthInDomainPart(): int
	{
		return 255;
	}

	public function validate(string $emailAddress): bool
	{
		return match ($this) {
			self::Basic => preg_match(self::BASIC_PATTERN, $emailAddress) === 1,
			self::Html => preg_match(self::HTML5_PATTERN, $emailAddress) === 1,
			self::Rfc => self::matchRfc5322($emailAddress),
			self::Smtp => self::matchRfc5322($emailAddress),
		};
	}

	private static function matchRfc5322(string $emailAddress): bool
	{
		return filter_var($emailAddress, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false;
	}
}
