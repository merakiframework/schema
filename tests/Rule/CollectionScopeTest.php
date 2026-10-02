<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Definition;
use Meraki\Schema\Field;
use Meraki\Schema\Rule\Condition\Comparison;
use Meraki\Schema\Scope;
use Meraki\Schema\ScopeResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * A rule can ask about a collection's rows.
 *
 * `Collection\Value` was the one parsed value a scope could not reach into, so "the rush line's
 * SKU" was not a thing a rule could name. These cover the whole path — written, checked where it is
 * written, and fired against a request — because the parts pass individually and the interesting
 * failures are between them.
 */
#[Group('rule')]
#[CoversClass(Comparison::class)]
#[CoversClass(ScopeResolver::class)]
#[CoversClass(Scope::class)]
final class CollectionScopeTest extends TestCase
{
	private function order(): Definition
	{
		$schema = new Definition('order');
		$schema->add(
			$schema->createCollectionField(
				'lines',
				$schema->createTextField('sku'),
				$schema->createNumberField('qty'),
			),
			$schema->createTextField('note')->makeOptional(),
		);

		return $schema;
	}

	/** @return array<string, mixed> */
	private static function lines(string $sku): array
	{
		return ['lines' => ['rush' => (object) ['sku' => $sku, 'qty' => '1']]];
	}

	#[Test]
	public function a_rule_fires_on_what_one_named_row_holds(): void
	{
		$schema = $this->order();
		$note = $schema->fields->getByName('note');

		$schema->addRule(
			$schema->when(Scope::parse('#/fields/lines/value/rush/sku/value'))
				->equals('URGENT')
				->then($note->makeRequired()),
		);

		$hit = $schema->validate((object) self::lines('URGENT'));
		$miss = $schema->validate((object) self::lines('CALM'));

		$this->assertFalse($hit->forField('note')->field->optional, 'the rule should have fired');
		$this->assertTrue($miss->forField('note')->field->optional, 'and not otherwise');
	}

	#[Test]
	public function the_expectation_is_read_by_the_field_that_would_hold_it(): void
	{
		// The bug this exists to stop coming back. Every scope reports the *schema* field it
		// belongs to — `lines` — so a check asking "could this field hold 'URGENT'" used to ask the
		// collection, which cannot hold a string at all, and refused a perfectly good rule as one
		// that could never fire. The question belongs to `sku`.
		$schema = $this->order();
		$field = (new ScopeResolver($schema->fields))
			->fieldFor(Scope::parse('#/fields/lines/value/rush/sku/value'));

		$this->assertInstanceOf(Field\Text::class, $field);
		$this->assertSame('sku', (string) $field->name);
	}

	#[Test]
	public function a_template_field_that_is_not_there_is_refused_where_the_rule_is_written(): void
	{
		$schema = $this->order();
		$note = $schema->fields->getByName('note');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/has no field "nope" in its template/');

		$schema->addRule(
			$schema->when(Scope::parse('#/fields/lines/value/rush/nope/value'))
				->equals('x')
				->then($note->makeRequired()),
		);
	}

	#[Test]
	public function a_rule_can_ask_about_every_row_at_once(): void
	{
		// A column resolves to one value per row, under the row names — which is what makes
		// "is any of them URGENT" expressible at all.
		$schema = $this->order();
		$resolver = new ScopeResolver($schema->fields, ['lines' => [
			'rush' => (object) ['sku' => 'URGENT', 'qty' => '1'],
			'later' => (object) ['sku' => 'CALM', 'qty' => '2'],
		]]);

		$column = $resolver->resolve(Scope::parse('#/fields/lines/value/*/sku/value'));

		$this->assertSame(['rush', 'later'], array_keys($column));
		$this->assertSame(['URGENT', 'CALM'], array_map(strval(...), array_values($column)));
	}

	#[Test]
	public function a_rule_about_a_row_that_never_arrives_simply_does_not_fire(): void
	{
		// Which rows exist is a fact about a request. A rule naming one that was not submitted is
		// not an error — it is a condition that did not hold.
		$schema = $this->order();
		$note = $schema->fields->getByName('note');

		$schema->addRule(
			$schema->when(Scope::parse('#/fields/lines/value/rush/sku/value'))
				->equals('URGENT')
				->then($note->makeRequired()),
		);

		$result = $schema->validate((object) ['lines' => ['other' => (object) ['sku' => 'URGENT', 'qty' => '1']]]);

		$this->assertTrue($result->forField('note')->field->optional);
	}
}
