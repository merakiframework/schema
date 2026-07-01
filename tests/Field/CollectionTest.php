<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Facade;
use Meraki\Schema\Field\Collection;
use Meraki\Schema\Field\Date;
use Meraki\Schema\Field\Time;
use Meraki\Schema\Property\Name;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('field')]
#[CoversClass(Collection::class)]
#[CoversClass(Facade::class)]
final class CollectionTest extends TestCase
{
	private function lessons(): Collection
	{
		return new Collection(new Name('lessons'), new Date(new Name('date')), new Time(new Name('time')));
	}

	#[Test]
	public function it_prefixes_the_template_field_names(): void
	{
		$this->assertEqualsCanonicalizing(['lessons.date', 'lessons.time'], $this->lessons()->fields->listFieldNames());
	}

	#[Test]
	public function it_normalises_input_into_a_list_of_items_keyed_by_local_name(): void
	{
		$field = $this->lessons()->input([
			['date' => '2026-01-01', 'time' => '10:00:00'],
			['date' => '2026-01-02', 'time' => '11:00:00'],
		]);

		$items = $field->resolvedValue->unwrap();

		$this->assertCount(2, $items);
		$this->assertSame(['date' => '2026-01-01', 'time' => '10:00:00'], $items[0]);
	}

	#[Test]
	public function it_passes_when_every_item_is_valid(): void
	{
		$field = $this->lessons()->input([
			['date' => '2026-01-01', 'time' => '10:00:00'],
			['date' => '2026-01-02', 'time' => '11:00:00'],
		]);

		$this->assertSame(ValidationStatus::Passed, $field->validate()->status);
	}

	#[Test]
	public function it_fails_when_an_item_is_invalid(): void
	{
		$field = $this->lessons()->input([
			['date' => 'not-a-date', 'time' => '10:00:00'],
		]);

		$this->assertSame(ValidationStatus::Failed, $field->validate()->status);
	}

	#[Test]
	public function it_fails_when_there_are_fewer_than_min_items(): void
	{
		$field = $this->lessons()->minItems(2)->input([
			['date' => '2026-01-01', 'time' => '10:00:00'],
		]);

		$this->assertSame(ValidationStatus::Failed, $field->validate()->status);
	}

	#[Test]
	public function it_fails_when_there_are_more_than_max_items(): void
	{
		$field = $this->lessons()->maxItems(1)->input([
			['date' => '2026-01-01', 'time' => '10:00:00'],
			['date' => '2026-01-02', 'time' => '11:00:00'],
		]);

		$this->assertSame(ValidationStatus::Failed, $field->validate()->status);
	}

	#[Test]
	public function an_optional_empty_collection_passes(): void
	{
		$field = $this->lessons()->minItems(1)->makeOptional()->input([]);

		$this->assertSame(ValidationStatus::Passed, $field->validate()->status);
	}

	#[Test]
	public function the_facade_builds_a_collection_from_a_template_callback(): void
	{
		$schema = new Facade('booking');

		$field = $schema->addCollectionField('lessons', function (Facade $item): void {
			$item->addDateField('date');
			$item->addTimeField('time');
		})->minItems(1);

		$this->assertInstanceOf(Collection::class, $field);
		$this->assertEqualsCanonicalizing(['lessons.date', 'lessons.time'], $field->fields->listFieldNames());
	}

	#[Test]
	public function items_can_contain_a_composite_sub_field(): void
	{
		$schema = new Facade('booking');
		$field = $schema->addCollectionField('lessons', function (Facade $item): void {
			$item->addDateTimeField('when');
			$item->addAddressField('pickup');
		})->minItems(1);

		// the composite's leaves are prefixed under the collection, without doubling the
		// composite's own segment (would otherwise be `lessons.pickup.pickup.street`).
		$pickup = $field->fields->findByName('lessons.pickup');
		$this->assertContains('lessons.pickup.street', $pickup->fields->listFieldNames());

		$addr = ['street' => '1 King St', 'city' => 'Brisbane', 'state' => 'QLD', 'postcode' => '4000', 'country' => 'AU'];
		$valid = ['lessons' => [['when' => '2026-03-01T10:00:00', 'pickup' => $addr]]];
		$this->assertFalse($schema->validate($valid)->anyFailed());

		// a missing required address leaf fails through the collection
		$invalid = ['lessons' => [['when' => '2026-03-01T10:00:00', 'pickup' => ['city' => null] + $addr]]];
		$this->assertTrue($schema->validate($invalid)->anyFailed());
	}
}
