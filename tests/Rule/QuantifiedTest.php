<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use Meraki\Schema\Rule\Condition\Quantified;
use Meraki\Schema\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * A rule can ask how many of a collection's rows match.
 *
 * A column resolves to one value per row, so a bare comparison against it compares a *list* to a
 * scalar — accepted, never fires, and silent about why. `whereAny()` and `whereEvery()` are what
 * make the question answerable, and the difference between them is the whole point: one hazardous
 * line is enough to need a declaration, but only an entirely digital order can skip shipping.
 */
#[Group('rule')]
#[CoversClass(Quantified::class)]
#[CoversClass(Quantifier::class)]
final class QuantifiedTest extends TestCase
{
	/** @return array{Facade, Field\Collection, Field} */
	private function order(): array
	{
		$schema = new Facade('order');
		$lines = $schema->createCollectionField(
			'lines',
			$schema->createTextField('sku'),
			$schema->createNumberField('qty'),
		);
		$declaration = $schema->createTextField('declaration')->makeOptional();
		$schema->add($lines, $declaration);

		return [$schema, $lines, $declaration];
	}

	/** @param list<string> $skus */
	private static function rows(array $skus): object
	{
		$rows = [];

		foreach ($skus as $i => $sku) {
			$rows['row' . ($i + 1)] = (object) ['sku' => $sku, 'qty' => '1'];
		}

		return (object) ['lines' => $rows];
	}

	/**
	 * @param list<string> $skus
	 */
	#[Test]
	#[DataProvider('foldings')]
	public function a_quantifier_decides_how_many_rows_must_match(string $how, array $skus, bool $fires): void
	{
		[$schema, $lines, $declaration] = $this->order();

		$schema->addRule(
			($how === 'any' ? $lines->whereAny('sku') : $lines->whereEvery('sku'))
				->equals('HAZMAT')
				->then($declaration->makeRequired()),
		);

		$result = $schema->validate(self::rows($skus));

		$this->assertSame($fires, !$result->forField('declaration')->field->optional);
	}

	/** @return array<string, array{string, list<string>, bool}> */
	public static function foldings(): array
	{
		return [
			'any: one of two' => ['any', ['HAZMAT', 'NORMAL'], true],
			'any: both' => ['any', ['HAZMAT', 'HAZMAT'], true],
			'any: neither' => ['any', ['NORMAL', 'NORMAL'], false],
			// "any of nothing" is false and "every one of nothing" is true — the standard reading,
			// listed here because it is the case people forget.
			'any: no rows' => ['any', [], false],

			'every: one of two' => ['every', ['HAZMAT', 'NORMAL'], false],
			'every: both' => ['every', ['HAZMAT', 'HAZMAT'], true],
			'every: neither' => ['every', ['NORMAL', 'NORMAL'], false],
			'every: no rows' => ['every', [], true],
		];
	}

	#[Test]
	public function a_row_field_offers_the_questions_its_own_value_can_answer(): void
	{
		// `whereAny` hands back the template field's *own* matcher, so a number column has the
		// ordered verbs and a text column does not — the same promise Field::when() makes.
		[, $lines] = $this->order();

		$this->assertInstanceOf(Matcher\OrderedText::class, $lines->whereAny('qty'));
		$this->assertInstanceOf(Matcher\Text::class, $lines->whereAny('sku'));
	}

	#[Test]
	public function an_ordered_question_folds_the_same_way(): void
	{
		// Nothing about isGreaterThan had to learn what a list is: the inner condition is re-rooted
		// at each row and asked exactly as it would be of a top-level field.
		$schema = new Facade('order');
		$lines = $schema->createCollectionField('lines', $schema->createNumberField('qty'));
		$approval = $schema->createTextField('approval')->makeOptional();
		$schema->add($lines, $approval);

		$schema->addRule($lines->whereAny('qty')->isGreaterThan(100)->then($approval->makeRequired()));

		$big = $schema->validate((object) ['lines' => ['a' => (object) ['qty' => '5'], 'b' => (object) ['qty' => '500']]]);
		$small = $schema->validate((object) ['lines' => ['a' => (object) ['qty' => '5']]]);

		$this->assertFalse($big->forField('approval')->field->optional);
		$this->assertTrue($small->forField('approval')->field->optional);
	}

	#[Test]
	public function the_quantifier_is_not_part_of_the_address(): void
	{
		// Both quantifiers name the same values, which is why this serialises on the condition and
		// the scope format is untouched — quantifiers could have landed later without changing what
		// a stored scope means.
		[, $lines] = $this->order();

		$any = $lines->whereAny('sku')->equals('HAZMAT')->condition;
		$every = $lines->whereEvery('sku')->equals('HAZMAT')->condition;

		$this->assertInstanceOf(Quantified::class, $any);
		$this->assertInstanceOf(Quantified::class, $every);
		$this->assertSame('#/fields/lines/value/*/sku/value', (string) $any->of->scope);
		$this->assertSame((string) $any->of->scope, (string) $every->of->scope);
		$this->assertSame(Quantifier::Any, $any->quantifier);
		$this->assertSame(Quantifier::Every, $every->quantifier);
	}

	#[Test]
	public function quantifying_a_field_that_is_not_in_the_template_is_refused(): void
	{
		[, $lines] = $this->order();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/has no field \'nope\' in its template/');

		$lines->whereAny('nope');
	}

	#[Test]
	public function quantifying_something_that_is_not_a_column_is_refused(): void
	{
		// "how many of one thing match" has no reading beyond the question already asked.
		$this->expectException(InvalidArgumentException::class);

		new Quantified(Quantifier::Any, new Condition\Equals(Scope::parse('#/fields/lines/value'), 'x'));
	}
}
