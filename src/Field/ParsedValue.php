<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Comparison\Equality;

/**
 * What a field turns submitted input into.
 *
 * Named for the role rather than for a method, because the role is the point: this *is* the return
 * type of {@see Definition::parse()}, and every field has one value class of its own. Being the
 * signature is what makes it hold — a field cannot parse to a bare `string`, or to a class from a
 * dependency, without failing to compile.
 *
 * ### It declares nothing of its own
 *
 * Everything it requires comes from {@see Equality}, and deliberately: "what a field parses to" and
 * "can be compared for sameness" are different statements, and only the first is about fields. The
 * second is a capability anything could have, so it lives in `Comparison\` where a caller can reach
 * it without knowing this library has fields at all.
 *
 * So the contract reads as two facts rather than one:
 *
 * - a parsed value is a value object this library defines (this interface, as `parse()`'s return
 *   type), and
 * - every value object answers for its own equality ({@see Equality}).
 *
 * A value that also has an *order* says so with {@see Comparable}, which most do not have: two
 * addresses can be the same address and neither is before the other.
 */
interface ParsedValue extends Equality
{
}
