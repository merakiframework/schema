<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The names settled in docs/API-REVIEW.md, as an executable specification.
 *
 * Written before the implementation, so every one of these fails today. They are held in
 * the `api-2.0` group, which phpunit.xml excludes from the default run, so the existing
 * suite stays a signal while this one is a target.
 *
 * Everything is checked by reflection on class-name strings rather than by constructing a
 * field, so a class that does not exist yet reports a failed assertion instead of a fatal
 * error — the whole file stays runnable while it is red.
 */
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
			'PhoneNumber' => ['allow', 'ofType'],
			'Collection' => ['minCountOf', 'maxCountOf'],

			// File holds one file; several files is a collection of them.
			'File' => ['minSizeOf', 'maxSizeOf', 'allowTypes', 'disallowTypes'],

			// Password absorbs Passphrase. Composition is explicit, strength is a floor.
			'Password' => [
				'minLengthOf', 'maxLengthOf', 'minStrengthOf',
				'minNumberOfUppercaseChars', 'maxNumberOfUppercaseChars',
				'minNumberOfLowercaseChars', 'maxNumberOfLowercaseChars',
				'minNumberOfDigits', 'maxNumberOfDigits',
				'minNumberOfSymbols', 'maxNumberOfSymbols',
			],

			// Structured types: one field, one value object.
			'Address' => ['allowCountries'],
			'Money' => ['allowCurrencies', 'minAmountOf', 'maxAmountOf'],
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
				'minLength', 'maxLength', 'maxBytes', 'minStrength',
				'minUppercaseChars', 'maxUppercaseChars',
				'minLowercaseChars', 'maxLowercaseChars',
				'minDigits', 'maxDigits',
				'minSymbols', 'maxSymbols',
			],
			'Address' => ['allowedCountries'],
			'Money' => ['allowedCurrencies'],
		];

		foreach ($properties as $class => $names) {
			foreach ($names as $name) {
				yield "{$class}::\${$name}" => [$class, $name];
			}
		}
	}

	#[Test]
	#[DataProvider('removedApi')]
	public function the_removed_api_is_gone(string $fqcn, ?string $member, string $why): void
	{
		if ($member === null) {
			$this->assertFalse(class_exists($fqcn) || interface_exists($fqcn), "{$fqcn} still exists. {$why}");

			return;
		}

		// A member cannot survive a class that has gone, so both cases are one assertion.
		$this->assertFalse(
			class_exists($fqcn) && (method_exists($fqcn, $member) || property_exists($fqcn, $member)),
			"{$fqcn}::{$member} still exists. {$why}",
		);
	}

	/** @return iterable<string, array{string, ?string, string}> */
	public static function removedApi(): iterable
	{
		$schema = 'Meraki\\Schema\\';

		$cases = [
			// Whole types.
			[self::FIELD . 'Composite', null, 'Collection already holds a template of several fields.'],
			[self::FIELD . 'Variant', null, 'Its only use was the Password|Passphrase union.'],
			[self::FIELD . 'Passphrase', null, 'Absorbed into Password.'],
			[self::FIELD . 'Placeholder', null, 'Presentation, not schema.'],
			[self::FIELD . 'AtomicMultiValue', null, 'A field holds one value; several is a Collection.'],
			[self::FIELD . 'Password\\Range', null, 'Replaced by flat scalar properties.'],
			[self::FIELD . 'EmailAddress\\Format', null, 'One WHATWG baseline; no widening.'],
			[$schema . 'Property\\Value', null, 'The value wrapper goes before Field\\*\\Value arrives.'],
			[$schema . 'Rule\\Outcome\\_Require', null, 'Renamed MakeRequired, avoiding the reserved word.'],

			// The staged-input path.
			[$schema . 'Field', 'input', 'A schema cannot hold one request.'],
			[$schema . 'Field', 'prefill', 'defaultsTo() on the definition; prefilledWith: per request.'],
			[$schema . 'Field', 'ignoreInput', 'The ignore outcome is read from appliedOutcomes.'],
			[$schema . 'Field', 'acceptInput', 'Counterpart of ignoreInput().'],
			[$schema . 'Field', 'hasValue', 'Reads state a field no longer holds.'],
			[$schema . 'Facade', 'input', 'validate()/resolve() take the data.'],
			[$schema . 'Facade', 'applyRules', 'Rules apply during resolution.'],
			[$schema . 'Facade', 'addTextField', 'Becomes createTextField() plus an explicit add.'],
			[$schema . 'ResolvedField', 'given', 'Never differed from value, and failed its own contract.'],

			// Per-field removals.
			[self::FIELD . 'Enum', 'allow', 'The list is the type; it is not extended after declaration.'],
			[self::FIELD . 'Date', 'to', 'Inclusive and exclusive bounds sharing one constraint name.'],
			[self::FIELD . 'Time', 'precisionMode', 'A getter for $precision, which is public.'],
			[self::FIELD . 'DateTime', 'precisionMode', 'A getter for $precision, which is public.'],
			[self::FIELD . 'DateTime', 'withSecondPrecision', 'Sugar over a constructor argument that is already an enum.'],
			[self::FIELD . 'Password', 'satisfyAnyOf', 'Explicit methods instead; it held the last mutable validation state (C4).'],
			[self::FIELD . 'Password', 'strong', 'Replaced by minStrengthOf(Strength::Strong).'],
			[self::FIELD . 'File', 'atLeast', 'File holds one file.'],
			[self::FIELD . 'File', 'minFileSizeOf', 'Matches $minSize as minSizeOf().'],
			[self::FIELD . 'Collection', 'minItems', 'Becomes minCountOf().'],
			[self::FIELD . 'Text', 'matches', 'Becomes mustMatch().'],
			[self::FIELD . 'Text', 'SKIP_MATCHING', 'A nullable parameter says it already.'],
		];

		foreach ($cases as [$fqcn, $member, $why]) {
			$label = $member === null ? $fqcn : "{$fqcn}::{$member}";

			yield $label => [$fqcn, $member, $why];
		}
	}
}
