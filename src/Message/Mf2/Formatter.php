<?php
declare(strict_types=1);

namespace Meraki\Schema\Message\Mf2;

/**
 * The disposable class.
 *
 * ICU MessageFormat 2 reached Final Candidate in March 2025 and is part of Unicode LDML, but there
 * is no PHP implementation: the `intl` extension binds MessageFormat *1*, and MF2 lives in
 * `icu::message2`, which it does not expose. So until somebody writes one, this renders the subset
 * the language packs actually use — **variable expansion, and nothing else**.
 *
 * Everything in the `Meraki\Schema\Message` namespace is built so that this file is the only thing
 * that has to go when a real implementation lands. {@see Mf2Provider} owns lookup, the specificity
 * ladder and the variables; this owns "given a message and some values, produce a string". Swap
 * this for an adapter over a real parser and nothing else changes.
 *
 * ### It refuses far more than it renders, on purpose
 *
 * The swap is only safe if the subset behaves *identically* to real MF2 for every message that
 * ships. The way that goes wrong is leniency: a formatter that quietly ignores `.match` or
 * tolerates a stray brace lets packs accumulate that work today and break the day a real parser
 * arrives — and the failure would land on a user's form, months later, in a language nobody on the
 * team reads.
 *
 * So anything outside the subset raises {@see BadMessage}, and {@see PackValidator} runs the same
 * checks over a whole pack in its own CI. A pack reaching for plurals fails a build, loudly, on the
 * day it is written.
 *
 * ### What is in the subset
 *
 * A *simple message*: literal text, the four escapes, variable placeholders `{$name}`, and quoted
 * literals `{|text|}`. That is enough for every message in a language pack, because the library
 * hands over values that are already formatted — see {@see Mf2Translator} — and it is a hundred
 * lines rather than a grammar.
 *
 * The literal is in because a pack needs to express significant whitespace and there is no other
 * way to: `list.separator` is `", "` including the space, and a resource format that trims its
 * values cannot carry that. MF2's own answer to the problem is the quoted literal, so supporting it
 * means the packs written now are correct MF2 rather than something that happens to work here. It
 * is an addition in fidelity, not in leniency.
 *
 * What is refused, each with a message naming it: declarations (`.input`, `.local`), selection
 * (`.match`, and the quoted patterns it needs), function calls (`{$x :number}`), markup, options
 * and attributes.
 */
final class Formatter
{
	/**
	 * The `name` production, restricted to ASCII.
	 *
	 * MF2 allows a good deal of Unicode in a name. Every variable here comes from the library
	 * rather than from a translator, and they are all ASCII, so a wider pattern would only widen
	 * what a typo can look like.
	 */
	private const NAME = '/^[A-Za-z_][A-Za-z0-9_.\-]*$/';

	/** The only characters a backslash may precede. */
	private const ESCAPABLE = ['\\', '{', '}', '|'];

	/**
	 * Renders a message, substituting the values it asks for.
	 *
	 * @param array<string, string> $variables everything this message is allowed to name. A
	 *        message naming anything else raises rather than rendering a gap, because a gap in a
	 *        sentence shown to a user is worse than a failure in a build.
	 * @throws BadMessage if the message is malformed, reaches for an unimplemented feature, or
	 *         names a variable that was not supplied
	 */
	public function format(string $message, array $variables): string
	{
		$ignored = [];

		return $this->scan($message, $variables, $ignored);
	}

	/**
	 * Checks a message without rendering it.
	 *
	 * What a pack's build runs over every entry. Catches everything {@see self::format()} would
	 * except an unknown variable, which needs to know which variables that particular message is
	 * entitled to — that is {@see PackValidator}'s job, using {@see self::variablesIn()}.
	 *
	 * @throws BadMessage
	 */
	public function assertSupported(string $message): void
	{
		$ignored = [];

		$this->scan($message, null, $ignored);
	}

	/**
	 * Every variable a message names, in order of first appearance.
	 *
	 * @return list<string>
	 * @throws BadMessage if the message is malformed or unsupported
	 */
	public function variablesIn(string $message): array
	{
		$seen = [];

		$this->scan($message, null, $seen);

		return array_keys($seen);
	}

	/**
	 * One pass, doing double duty.
	 *
	 * `$variables` of null means "check only": names are collected into `$seen` and expand to
	 * nothing. Sharing the walk is what keeps {@see self::assertSupported()} honest — a checker
	 * written separately would drift from the renderer, and the drift would be silent.
	 *
	 * @param array<string, string>|null $variables
	 * @param array<string, true> $seen
	 */
	private function scan(string $message, ?array $variables, array &$seen): string
	{
		// A simple message cannot begin with a dot; MF2 reads that as the start of a declaration
		// or a .match, so everything after it is a grammar this does not implement.
		$trimmed = ltrim($message);

		if ($trimmed !== '' && $trimmed[0] === '.') {
			throw BadMessage::feature('declarations or selection (a message beginning with ".")', $message);
		}

		$out = '';
		$length = strlen($message);
		$i = 0;

		while ($i < $length) {
			$char = $message[$i];

			if ($char === '\\') {
				$next = $message[$i + 1] ?? '';

				if (!in_array($next, self::ESCAPABLE, true)) {
					throw BadMessage::malformed(
						sprintf('"\\%s" is not an escape; only \\\\, \\{, \\} and \\| are', $next),
						$message,
					);
				}

				$out .= $next;
				$i += 2;
				continue;
			}

			if ($char === '}') {
				throw BadMessage::malformed('A "}" with no "{" before it (write "\\}" for a literal)', $message);
			}

			if ($char !== '{') {
				$out .= $char;
				$i++;
				continue;
			}

			if (($message[$i + 1] ?? '') === '{') {
				throw BadMessage::feature('quoted patterns', $message);
			}

			$close = self::closingBrace($message, $i);

			if ($close === null) {
				throw BadMessage::malformed('A "{" that is never closed (write "\\{" for a literal)', $message);
			}

			$out .= $this->expand(trim(substr($message, $i + 1, $close - $i - 1)), $variables, $seen, $message);
			$i = $close + 1;
		}

		return $out;
	}

	/**
	 * The contents of one placeholder, which must be a bare variable.
	 *
	 * Each refusal names the feature rather than saying "invalid", because the author of a pack is
	 * a translator who did not choose this limitation and should not have to infer it.
	 *
	 * @param array<string, string>|null $variables
	 * @param array<string, true> $seen
	 */
	private function expand(string $inner, ?array $variables, array &$seen, string $source): string
	{
		if (str_starts_with($inner, '|')) {
			return self::literal($inner, $source);
		}

		$feature = match (true) {
			$inner === '' => 'an empty placeholder',
			str_starts_with($inner, ':') => 'function calls',
			str_starts_with($inner, '#'), str_starts_with($inner, '/') => 'markup',
			str_starts_with($inner, '@') => 'attributes',
			!str_starts_with($inner, '$') => 'that kind of placeholder',
			default => null,
		};

		if ($feature !== null) {
			throw BadMessage::feature($feature, $source);
		}

		$name = substr($inner, 1);

		// `{$count :number}` and `{$count @attr}` both land here: the variable is fine, what
		// follows it is not, and saying so beats reporting the whole thing as a bad name.
		if (preg_match('/\s/', $name) === 1) {
			throw BadMessage::feature('functions or options on a variable', $source);
		}

		if (preg_match(self::NAME, $name) !== 1) {
			throw BadMessage::malformed(sprintf('"%s" is not a variable name', $inner), $source);
		}

		$seen[$name] = true;

		if ($variables === null) {
			return '';
		}

		if (!array_key_exists($name, $variables)) {
			throw BadMessage::unknownVariable($name, array_keys($variables), $source);
		}

		return $variables[$name];
	}

	/**
	 * A quoted literal: the text between two pipes, with its escapes resolved.
	 *
	 * This is how a pack writes something the resource format would otherwise eat — a separator
	 * that is a comma and a space, where the space is the whole point.
	 */
	private static function literal(string $inner, string $source): string
	{
		if (strlen($inner) < 2 || !str_ends_with($inner, '|') || str_ends_with($inner, '\\|')) {
			throw BadMessage::malformed('A "{|" that is never closed by "|}"', $source);
		}

		$text = substr($inner, 1, -1);
		$out = '';
		$length = strlen($text);

		for ($i = 0; $i < $length; $i++) {
			if ($text[$i] === '|') {
				// MF2 ends a literal at the first unescaped pipe, so `{|a|b|}` is a closed literal
				// followed by rubbish rather than a literal containing a pipe. Accepting it would
				// render something a real implementation refuses.
				throw BadMessage::malformed('A "|" inside a literal must be written "\\|"', $source);
			}

			if ($text[$i] !== '\\') {
				$out .= $text[$i];
				continue;
			}

			$next = $text[$i + 1] ?? '';

			if (!in_array($next, self::ESCAPABLE, true)) {
				throw BadMessage::malformed(
					sprintf('"\\%s" is not an escape; only \\\\, \\{, \\} and \\| are', $next),
					$source,
				);
			}

			$out .= $next;
			$i++;
		}

		return $out;
	}

	/**
	 * Where a placeholder ends, skipping a "}" that an escape has spoken for.
	 *
	 * A plain search for the next "}" is wrong the moment a literal contains one: `{|a\}b|}` would
	 * be cut at the escaped brace, and the rest of the message would be read as text.
	 */
	private static function closingBrace(string $message, int $from): ?int
	{
		$length = strlen($message);

		for ($i = $from + 1; $i < $length; $i++) {
			if ($message[$i] === '\\') {
				$i++;
				continue;
			}

			if ($message[$i] === '}') {
				return $i;
			}
		}

		return null;
	}
}
