<?php
declare(strict_types=1);

namespace Meraki\Schema\Scope;

use Meraki\Schema\FieldName;
use Stringable;

/**
 * Where a scope's tail is rooted — which field it is about, and how that field is reached.
 *
 * A scope is two independent things: a **locator** saying where to look, and a **tail** saying what
 * to read once there. The tail is the same four shapes wherever it is rooted — the field, one of
 * its properties, its value, or one part of that value — so {@see \Meraki\Schema\Scope::parse()}
 * reads the locator first and then applies one tail grammar to whatever is left.
 *
 * That split is what lets a row's field be addressed exactly like a top-level one. A row field *is*
 * a field: it has a definition and a value, so it needs the same `value`-versus-property
 * distinction that `#/fields/x/value` and `#/fields/x/minLength` have always had. Modelling the
 * tail separately means it is written once instead of four times, and a fifth namespace would cost
 * a class rather than a grammar.
 *
 * Four exist:
 *
 * | Locator | Path |
 * | --- | --- |
 * | {@see SchemaField} | `#/fields/<f>` |
 * | {@see Row} | `#/fields/<c>/value/<row>/<tf>` |
 * | {@see Column} | `#/fields/<c>/value/*​/<tf>` |
 * | {@see Template} | `#/fields/<c>/template/<tf>` |
 */
interface Locator extends Stringable
{
	/**
	 * The **schema** field this addressing starts from, which is what
	 * {@see \Meraki\Schema\ScopeResolver} looks up in the schema's field set.
	 *
	 * For a collection locator that is the collection, never the template field inside it — so a
	 * rule naming a row still groups under the field the schema actually holds, and
	 * {@see \Meraki\Schema\Definition::addRule()} needs no special case.
	 */
	public FieldName $field { get; }

	/**
	 * The field this scope is actually *about*.
	 *
	 * The same as {@see self::$field} at the top level, and the template field inside a collection.
	 */
	public function addresses(): FieldName;
}
