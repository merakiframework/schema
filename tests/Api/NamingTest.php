<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The names settled in docs/API.md, as an executable specification.
 *
 * Written before the implementation, so every one of these fails today. They are held in
 * the `api-2.0` group, which phpunit.xml excludes from the default run, so the existing
 * suite stays a signal while this one is a target.
 *
 * Everything is checked by reflection on class-name strings rather than by constructing a
 * field, so a class that does not exist yet reports a failed assertion instead of a fatal
 * error — the whole file stays runnable while it is red.
 */
#[CoversNothing]
#[Group('api-2.0')]
final class NamingTest extends TestCase
{
	private const FIELD = 'Meraki\\Schema\\Field\\';

	#[Test]
	#[DataProvider('definitionMethods')]
	public function a_field_declares_its_constraints_through_the_agreed_methods(string $class, string $method): void
	{
		$fqcn = self::FIELD . $class;

		$this->assertTrue(class_exists($fqcn), "Field {$class} does not exist.");
		$this->assertTrue(method_exists($fqcn, $method), "{$class}::{$method}() does not exist.");
	}

	/** @return iterable<string, array{string, string}> */
	public static function definitionMethods(): iterable
	{
		$methods = [
			// Length-bounded strings.
			'Text' => ['minLengthOf', 'maxLengthOf', 'mustMatch'],
			'Name' => ['minLengthOf', 'maxLengthOf'],
			'Uri' => ['minLengthOf', 'maxLengthOf', 'allowSchemes'],
			'EmailAddress' => ['minLengthOf', 'maxLengthOf', 'allowDomains', 'disallowDomains'],

			// Value-bounded quantities.
			'Number' => ['minValueOf', 'maxValueOf', 'inIncrementsOf', 'scaleTo'],
			'Duration' => ['minValueOf', 'maxValueOf', 'inIncrementsOf'],

			// Temporal: bounds already correct, stepping unified on intervals.
			'Date' => ['from', 'until', 'atIntervalsOf'],
			'Time' => ['from', 'until', 'atIntervalsOf'],
			'DateTime' => ['from', 'until', 'atIntervalsOf'],

			'Boolean' => ['mustBeAccepted'],
			'Uuid' => ['allowVersions'],
			'PhoneNumber' => ['allowCountries', 'ofType'],
			'Collection' => ['minCountOf', 'maxCountOf'],

			// File holds one file; several files is a collection of them.
			'File' => ['minSizeOf', 'maxSizeOf', 'allowTypes', 'disallowTypes'],

			// Password absorbs Passphrase. Composition is explicit, strength is a floor.
			'Password' => [
				'minLengthOf', 'maxLengthOf', 'minStrengthOf',
				'minNumberOfUppercaseChars', 'minNumberOfLowercaseChars',
				'minNumberOfDigits', 'minNumberOfSymbols',
			],

			// Structured types: one field, one value object.
			'Address' => ['allowCountries', 'clearAllowedCountries', 'allowOnlyMailable', 'allowOnlyPhysical', 'allowWithoutStreet'],
			'Money' => ['allowCurrencies', 'minAmountOf', 'maxAmountOf'],
			'CreditCard' => ['mustExpireInFuture'],
		];

		foreach ($methods as $class => $names) {
			foreach ($names as $name) {
				yield "{$class}::{$name}()" => [$class, $name];
			}
		}
	}

	#[Test]
	#[DataProvider('properties')]
	public function a_field_exposes_its_configuration_as_the_agreed_property(string $class, string $property): void
	{
		$fqcn = self::FIELD . $class;

		$this->assertTrue(class_exists($fqcn), "Field {$class} does not exist.");
		$this->assertTrue(property_exists($fqcn, $property), "{$class}::\${$property} does not exist.");
	}

	/** @return iterable<string, array{string, string}> */
	public static function properties(): iterable
	{
		$properties = [
			'Text' => ['minLength', 'maxLength', 'pattern'],
			'Name' => ['minLength', 'maxLength'],
			'Uri' => ['minLength', 'maxLength', 'allowedSchemes'],
			'EmailAddress' => ['minLength', 'maxLength', 'allowedDomains', 'disallowedDomains'],
			'Number' => ['minValue', 'maxValue', 'step', 'scale'],
			'Duration' => ['minValue', 'maxValue', 'step'],
			'Date' => ['from', 'until', 'interval'],
			'Time' => ['from', 'until', 'interval'],
			'DateTime' => ['from', 'until', 'interval'],
			'Boolean' => ['requiresAcceptance'],
			'Enum' => ['cases'],
			'Uuid' => ['allowedVersions'],
			'PhoneNumber' => ['allowedCountries', 'numberType'],
			'Collection' => ['minCount', 'maxCount'],
			'File' => ['minSize', 'maxSize', 'allowedTypes', 'disallowedTypes'],
			'Password' => [
				'minLength', 'maxLength', 'minStrength',
				'minUppercaseChars', 'minLowercaseChars', 'minDigits', 'minSymbols',
			],
			'Address' => ['allowedCountries', 'type', 'mustBeSpecific'],
			'Money' => ['allowedCurrencies'],
			'CreditCard' => ['mustExpireInFuture'],
		];

		foreach ($properties as $class => $names) {
			foreach ($names as $name) {
				yield "{$class}::\${$name}" => [$class, $name];
			}
		}
	}
}
