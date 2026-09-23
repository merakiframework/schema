<?php

declare(strict_types=1);

// A field type defined entirely outside meraki/schema.
//
// There is no registry to add to, no factory to teach, and no interface list to update. You
// write a class, and everything the built-in fields get, yours gets — sealing, copy-on-change,
// the shape/constraint split, the default check, rules, scopes, and the result shape.
//
// Braced namespaces only because this is one file; in a real project these are two.

namespace Acme\Isbn {

	require_once __DIR__ . '/../vendor/autoload.php';

	use Meraki\Schema\Comparison\Equality;
	use Meraki\Schema\Field\MalformedValue;
	use Meraki\Schema\Field\ParsedValue;

	/**
	 * What the field parses to. Every field defines one, and it decides its own equality —
	 * otherwise the answer comes from PHP's `==`, which reads the private layout of whatever
	 * class you happened to return.
	 */
	final readonly class Value implements ParsedValue
	{
		/** Digits only, with any check digit. */
		public string $isbn;

		/**
		 * The constructor enforces the invariant, so there is no way to hold one of these that a
		 * field would not have produced — and the canonicalising happens where the comparison
		 * does, which is what makes `equals()` reliable rather than dependent on how it was built.
		 *
		 * @throws MalformedValue if this is not an ISBN
		 */
		public function __construct(string $isbn)
		{
			// Canonicalising, not repairing: ISO 2108 says the hyphens are presentation.
			$digits = preg_replace('/[\s-]/', '', $isbn);

			if (preg_match('/^\d{9}[\dX]$|^\d{13}$/', $digits) !== 1) {
				throw MalformedValue::of(self::class, sprintf('"%s" is not an ISBN', $isbn));
			}

			$this->isbn = $digits;
		}

		public function equals(Equality $other): bool
		{
			// Digits only by the time it gets here, so exact is right.
			return $other instanceof self && $this->isbn === $other->isbn;
		}

		public function __toString(): string
		{
			return $this->isbn;
		}
	}
}

namespace Acme {

	use Acme\Isbn\Value;
	use Meraki\Schema\AtomicField;
	use Meraki\Schema\Field\Constraint;
	use Meraki\Schema\Field\MalformedValue;
	use Meraki\Schema\FieldName;
	use Meraki\Schema\Rule\Matcher;
	use Meraki\Schema\ValueScope;

	final readonly class Isbn extends AtomicField
	{
		public bool $thirteenOnly;

		public function __construct(public FieldName $name)
		{
			// The order matters and is not optional: the parent initialises the shared state,
			// then this field's own properties, then the constraints — which are built *from*
			// those properties and so must come last.
			parent::__construct();

			$this->thirteenOnly = false;
			$this->constraints = $this->defineConstraints();
		}

		/** Configuration is a wither: it hands back a copy, and the caller keeps it. */
		public function thirteenDigitsOnly(): static
		{
			return $this->with(['thirteenOnly' => true]);
		}

		/**
		 * The one thing a field of your own has to declare that it could not be given: which
		 * questions a rule may ask about it.
		 *
		 * An ISBN has text and no order — 978… is not *before* 979… in any sense a form means —
		 * so it offers `contains` and `matches` and withholds `isAtLeast`. Which means
		 * `$isbn->when()->isAtLeast(3)` does not compile, rather than being a rule that reads
		 * sensibly and can never fire.
		 */
		public function when(): Matcher\Text
		{
			return new Matcher\Text(ValueScope::of($this->name));
		}

		/**
		 * The one conversion hook, and almost nothing is left in it.
		 *
		 * What an ISBN *is* belongs to the value — no configuration makes `"hello"` one — so this
		 * narrows `mixed` to the type the value takes and hands over. It never receives null:
		 * absence is settled before it runs.
		 *
		 * There is no try/catch, and there should not be. Whether a refusal is absorbed or raised
		 * is the lifecycle's decision — caught on a request, raised for a bad `defaultsTo()` so
		 * the author reads the reason.
		 */
		protected function parse(mixed $value): Value
		{
			if ($value instanceof Value) {
				return $value;
			}

			if (!is_string($value)) {
				throw MalformedValue::of(Value::class, 'an ISBN is submitted as a string');
			}

			return new Value($value);
		}

		protected function defineConstraints(): Constraint\Set
		{
			return new Constraint\Set(
				// Returning null from a check means "nothing was asked", so it reports Skipped
				// rather than passing vacuously.
				new Constraint(
					'isbn13',
					fn(Value $v): ?bool => $this->thirteenOnly ? strlen($v->isbn) === 13 : null,
					$this->thirteenOnly,
				),
			);
		}
	}
}

namespace Main {

	use Acme\Isbn;
	use Meraki\Schema\Facade;
	use Meraki\Schema\FieldName;

	$schema = new Facade('library');
	$schema->add($isbn = (new Isbn(new FieldName('isbn')))->thirteenDigitsOnly());

	echo 'It behaves like any other field:' . PHP_EOL;

	foreach (['978-0-306-40615-7', '0306406152', 'not an isbn', null] as $submitted) {
		$field = $schema->validate((object) ['isbn' => $submitted])->forField('isbn');

		printf(
			"  %-20s shape %-8s isbn13 %-8s value %s" . PHP_EOL,
			$submitted === null ? '(nothing)' : $submitted,
			$field->shape->status->name,
			$field->forConstraint('isbn13')->status->name,
			$field->value instanceof Isbn\Value ? (string) $field->value : '-',
		);
	}

	echo PHP_EOL . 'And the default check comes along for free:' . PHP_EOL;

	// Configuration arrives in any order, so a default set *before* the constraint that rejects
	// it has to fail on the later call. Every wither funnels through with(), which is what makes
	// that work for a field this library has never heard of.
	try {
		(new Isbn(new FieldName('isbn')))->defaultsTo('0306406152')->thirteenDigitsOnly();
	} catch (\InvalidArgumentException $e) {
		echo '  ' . $e->getMessage() . PHP_EOL;
	}

	echo PHP_EOL . 'A rule can use it, with nothing registered anywhere:' . PHP_EOL;

	$schema->add($shelf = $schema->createTextField('shelf')->makeOptional());
	$schema->addRule($isbn->when()->equals('9780306406157')->then($shelf->makeRequired()));

	foreach (['978-0-306-40615-7', '9780306406158'] as $submitted) {
		$result = $schema->validate((object) ['isbn' => $submitted]);

		printf(
			"  %-20s shelf required: %s" . PHP_EOL,
			$submitted,
			var_export($result->forField('shelf')->wasAlteredByRule(), true),
		);
	}
}
