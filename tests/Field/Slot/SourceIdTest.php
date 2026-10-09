<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Slot;

use Meraki\Schema\Exception\InvalidConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('field')]
#[CoversClass(SourceId::class)]
final class SourceIdTest extends TestCase
{
	/** @return iterable<string, array{string}> */
	public static function usableIds(): iterable
	{
		yield 'snake case' => ['standard_consult'];
		yield 'slug case' => ['dr-smith'];
		yield 'pascal case, with a digit' => ['Room2'];
		yield 'a leading underscore' => ['_internal'];
	}

	#[Test]
	#[DataProvider('usableIds')]
	public function it_reads_back_as_written(string $id): void
	{
		$this->assertSame($id, (string) new SourceId($id));
	}

	/**
	 * An id is written into a document and, by a port, into a URL — so it has the shape of a field
	 * name, and nothing a path or a query string would have to escape.
	 *
	 * @return iterable<string, array{string}>
	 */
	public static function unusableIds(): iterable
	{
		yield 'empty' => [''];
		yield 'a space' => ['standard consult'];
		yield 'a slash' => ['clinic/room-2'];
		yield 'a dot' => ['consult.standard'];
		yield 'a leading digit' => ['2nd_room'];
	}

	#[Test]
	#[DataProvider('unusableIds')]
	public function it_refuses_what_could_not_travel_as_a_key(string $id): void
	{
		$this->expectException(InvalidConfiguration::class);

		new SourceId($id);
	}

	#[Test]
	public function two_ids_spelled_the_same_are_equal(): void
	{
		$this->assertTrue((new SourceId('standard_consult'))->equals(new SourceId('standard_consult')));
	}

	/**
	 * Exact, for the reason a field name is exact: it is a key, and every lookup by it is a string
	 * comparison somewhere.
	 */
	#[Test]
	public function an_id_is_case_sensitive(): void
	{
		$this->assertFalse((new SourceId('Consult'))->equals(new SourceId('consult')));
	}
}
