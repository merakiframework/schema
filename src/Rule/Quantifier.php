<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

/**
 * How many of a collection's rows have to answer yes.
 *
 * A column addresses one template field across every row, so it resolves to a *list* — and a
 * question put to a list is unanswerable until something says whether one row is enough or all of
 * them are needed. Without this, `whereAny('sku')->equals('HAZMAT')` and
 * `whereEvery('sku')->equals('HAZMAT')` would be the same sentence.
 *
 * It belongs to the question rather than to the address: `#/fields/lines/value/*​/sku/value` names
 * the same values either way, so the quantifier serialises on the condition and the scope format is
 * untouched by it.
 */
enum Quantifier: string
{
	/** One row answering yes is enough. */
	case Any = 'any';

	/** Every row has to answer yes. */
	case Every = 'every';

	/**
	 * What to answer when there are no rows at all.
	 *
	 * The standard reading, stated out loud because it is the case people forget: "any of nothing"
	 * is false, and "every one of nothing" is true. A rule hanging optionality off
	 * `whereEvery(...)` therefore fires on an empty collection, which is usually already failing
	 * its own `minCount` — the two verdicts are separate questions and neither is folded into the
	 * other.
	 */
	public function ofNothing(): bool
	{
		return $this === self::Every;
	}

	/**
	 * Whether the fold can stop here, and with what.
	 *
	 * `any` is done the moment one row says yes; `every` the moment one says no.
	 */
	public function settledBy(bool $matched): bool
	{
		return $matched === ($this === self::Any);
	}
}
